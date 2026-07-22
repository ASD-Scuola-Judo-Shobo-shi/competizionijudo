<?php

declare(strict_types=1);

namespace App\Maintenance;

use App\Model\Database;
use App\Service\MigrationExecutor;
use PDO;
use RuntimeException;
use Throwable;

const MAX_PARTICIPANTS_MIGRATION = '20260717_000003_add_max_participants_to_events.sql';
const REGISTRATION_EXCEPTIONS_MIGRATION = '20260718_000001_create_event_registration_exceptions.sql';

if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "This temporary repair script must be invoked through the browser and deleted immediately afterwards.\n");
    exit(2);
}

$rootDirectory = projectRoot();
require $rootDirectory . '/vendor/autoload.php';
require $rootDirectory . '/src/helpers.php';

\load_env($rootDirectory . '/.env');

header('Cache-Control: no-store');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'');
header('X-Content-Type-Options: nosniff');

if (!isHttpsRequest()) {
    http_response_code(400);
    echo 'HTTPS is required.';
    exit;
}

if (!isAuthorizedAdministrator()) {
    header('WWW-Authenticate: Basic realm="Production schema repair", charset="UTF-8"');
    http_response_code(401);
    echo 'Authentication required.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderForm();
    exit;
}

$confirmation = is_string($_POST['confirmation'] ?? null) ? $_POST['confirmation'] : '';
if (!hash_equals('REPAIR EVENT SCHEMA', $confirmation)) {
    http_response_code(400);
    echo 'Confirmation text did not match.';
    exit;
}

try {
    $repair = new EventSchemaRepair(Database::connection());
    $messages = $repair->run();
    renderResult(
        implode(PHP_EOL, $messages) . PHP_EOL
        . 'Production event schema repair completed. Delete this temporary file now.',
        200
    );
} catch (Throwable $exception) {
    $detail = (new MigrationExecutor())->failureDetail($exception);
    renderResult("Production event schema repair stopped.\n{$detail}", 500);
}

/** @phpstan-type SchemaState 'absent'|'ready'|'invalid' */
final class EventSchemaRepair
{
    private readonly bool $hasMigrationHistory;

    public function __construct(
        private readonly PDO $database
    ) {
        $this->hasMigrationHistory = $this->schemaMigrationsTableExists();
    }

    /** @return list<string> */
    public function run(): array
    {
        return [
            $this->repairMaxParticipants(),
            $this->repairRegistrationExceptions(),
        ];
    }

    private function repairMaxParticipants(): string
    {
        $recorded = $this->hasMigrationHistory && $this->migrationRecorded(MAX_PARTICIPANTS_MIGRATION);
        $columnState = $this->maxParticipantsColumnState();

        if ($recorded) {
            if ($columnState !== 'ready') {
                throw new RuntimeException(
                    MAX_PARTICIPANTS_MIGRATION
                    . ' is recorded but events.max_participants is missing or incompatible.'
                );
            }

            return MAX_PARTICIPANTS_MIGRATION . ' is already recorded and verified.';
        }

        if ($columnState === 'invalid') {
            throw new RuntimeException(
                'events.max_participants exists but does not have the expected nullable unsigned integer definition.'
            );
        }

        $constraintExists = $this->namedConstraintExists('events', 'chk_events_max_participants_positive');
        $changed = false;
        if ($columnState === 'absent') {
            $this->database->exec(
                'ALTER TABLE events ADD COLUMN max_participants INT UNSIGNED NULL AFTER closed'
            );
            $changed = true;
        }
        if (!$constraintExists) {
            $this->database->exec(
                'ALTER TABLE events ADD CONSTRAINT chk_events_max_participants_positive '
                . 'CHECK (max_participants IS NULL OR max_participants > 0)'
            );
            $changed = true;
        }

        if ($this->maxParticipantsColumnState() !== 'ready') {
            throw new RuntimeException('events.max_participants could not be verified after the repair.');
        }

        if ($this->hasMigrationHistory) {
            $this->recordMigration(
                MAX_PARTICIPANTS_MIGRATION,
                'Manual production repair: events.max_participants'
            );

            return MAX_PARTICIPANTS_MIGRATION . ' was applied and recorded.';
        }

        return $changed
            ? 'events.max_participants was repaired. schema_migrations is absent, so no migration history was recorded.'
            : 'events.max_participants is already verified. schema_migrations is absent, so no migration history was recorded.';
    }

    private function repairRegistrationExceptions(): string
    {
        $recorded = $this->hasMigrationHistory && $this->migrationRecorded(REGISTRATION_EXCEPTIONS_MIGRATION);
        $tableState = $this->registrationExceptionsTableState();

        if ($recorded) {
            if ($tableState !== 'ready') {
                throw new RuntimeException(
                    REGISTRATION_EXCEPTIONS_MIGRATION
                    . ' is recorded but event_registration_exceptions is missing or incompatible.'
                );
            }

            return REGISTRATION_EXCEPTIONS_MIGRATION . ' is already recorded and verified.';
        }

        if ($tableState === 'invalid') {
            throw new RuntimeException(
                'event_registration_exceptions exists but does not match the required event registration exception schema.'
            );
        }

        $created = false;
        if ($tableState === 'absent') {
            $this->database->exec(
                'CREATE TABLE IF NOT EXISTS event_registration_exceptions ('
                . 'id INT AUTO_INCREMENT PRIMARY KEY, '
                . 'event_id INT NOT NULL, '
                . 'club_id INT NOT NULL, '
                . 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, '
                . 'UNIQUE KEY unique_event_club (event_id, club_id), '
                . 'KEY idx_event_registration_exceptions_event_id (event_id), '
                . 'KEY idx_event_registration_exceptions_club_id (club_id), '
                . 'CONSTRAINT fk_event_registration_exceptions_event '
                . 'FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE, '
                . 'CONSTRAINT fk_event_registration_exceptions_club '
                . 'FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $created = true;
        }

        if ($this->registrationExceptionsTableState() !== 'ready') {
            throw new RuntimeException('event_registration_exceptions could not be verified after the repair.');
        }

        if ($this->hasMigrationHistory) {
            $this->recordMigration(
                REGISTRATION_EXCEPTIONS_MIGRATION,
                'Manual production repair: event registration exceptions'
            );

            return REGISTRATION_EXCEPTIONS_MIGRATION . ' was applied and recorded.';
        }

        return $created
            ? 'event_registration_exceptions was created. schema_migrations is absent, so no migration history was recorded.'
            : 'event_registration_exceptions is already verified. schema_migrations is absent, so no migration history was recorded.';
    }

    /** @return SchemaState */
    private function maxParticipantsColumnState(): string
    {
        $statement = $this->database->prepare(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute(['events', 'max_participants']);
        $column = $statement->fetch();
        if ($column === false) {
            return 'absent';
        }

        return strtolower((string) $column['DATA_TYPE']) === 'int'
            && str_contains(strtolower((string) $column['COLUMN_TYPE']), 'unsigned')
            && $column['IS_NULLABLE'] === 'YES'
            ? 'ready'
            : 'invalid';
    }

    /** @return SchemaState */
    private function registrationExceptionsTableState(): string
    {
        $statement = $this->database->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute(['event_registration_exceptions']);
        $columns = array_map(
            static fn(array $row): string => (string) $row['COLUMN_NAME'],
            $statement->fetchAll() ?: []
        );
        if ($columns === []) {
            return 'absent';
        }

        $requiredColumns = ['id', 'event_id', 'club_id', 'created_at'];
        if (array_diff($requiredColumns, $columns) !== []) {
            return 'invalid';
        }

        $requiredIndexes = [
            'PRIMARY',
            'unique_event_club',
            'idx_event_registration_exceptions_event_id',
            'idx_event_registration_exceptions_club_id',
        ];
        if (array_diff($requiredIndexes, $this->indexNames('event_registration_exceptions')) !== []) {
            return 'invalid';
        }

        $requiredForeignKeys = [
            'fk_event_registration_exceptions_event',
            'fk_event_registration_exceptions_club',
        ];

        return array_diff($requiredForeignKeys, $this->foreignKeyNames('event_registration_exceptions')) === []
            ? 'ready'
            : 'invalid';
    }

    private function migrationRecorded(string $version): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $statement->execute([$version]);

        return $statement->fetchColumn() !== false;
    }

    private function schemaMigrationsTableExists(): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute(['schema_migrations']);

        return $statement->fetchColumn() !== false;
    }

    private function namedConstraintExists(string $table, string $constraint): bool
    {
        $statement = $this->database->prepare(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?'
        );
        $statement->execute([$table, $constraint]);

        return $statement->fetchColumn() !== false;
    }

    /** @return list<string> */
    private function indexNames(string $table): array
    {
        $statement = $this->database->prepare(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** @return list<string> */
    private function foreignKeyNames(string $table): array
    {
        $statement = $this->database->prepare(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ?'
        );
        $statement->execute([$table, 'FOREIGN KEY']);

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    private function recordMigration(string $version, string $description): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO schema_migrations (version, description) VALUES (?, ?)'
        );
        $statement->execute([$version, $description]);
    }
}

function projectRoot(): string
{
    foreach ([__DIR__, dirname(__DIR__), __DIR__ . '/prod', dirname(__DIR__) . '/prod'] as $candidate) {
        if (is_file($candidate . '/vendor/autoload.php') && is_file($candidate . '/src/helpers.php')) {
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to locate the application root.');
}

function isHttpsRequest(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    return $https === 'on' || $https === '1' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function isAuthorizedAdministrator(): bool
{
    $expectedUser = (string) \env('ADMIN_USER', '');
    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $providedPassword = $_SERVER['PHP_AUTH_PW'] ?? null;

    return $expectedUser !== ''
        && is_string($providedUser)
        && is_string($providedPassword)
        && hash_equals($expectedUser, $providedUser)
        && hash_equals($expectedUser, $providedPassword);
}

function renderForm(): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<'HTML'
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>Production event schema repair</title>
<style>body{font:16px system-ui,sans-serif;max-width:42rem;margin:3rem auto;padding:0 1rem}label{display:block;margin-top:1rem}input{width:100%;box-sizing:border-box;padding:.5rem}button{margin-top:1.25rem;padding:.6rem 1rem}code{white-space:nowrap}</style>
<h1>Production event schema repair</h1>
<p>This one-time repair adds only <code>events.max_participants</code> and <code>event_registration_exceptions</code>. It records migrations only when a migration ledger already exists.</p>
<p>Confirm that a verified database backup exists. After a successful run, delete this temporary PHP file immediately.</p>
<form method="post" autocomplete="off">
  <label>Type <code>REPAIR EVENT SCHEMA</code> exactly<input name="confirmation" required autocomplete="off"></label>
  <button type="submit">Apply production event schema repair</button>
</form>
</html>
HTML;
}

function renderResult(string $message, int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message . PHP_EOL;
}
