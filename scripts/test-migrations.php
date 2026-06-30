<?php

declare(strict_types=1);

use App\Model\MigrationException;
use App\Model\MigrationRunner;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

const PRE_SQUASH_MIGRATIONS = [
    '20260619_000000_create_baseline_schema.sql',
    '20260619_000001_copy_italian_columns_to_english.sql',
    '20260620_000001_create_password_reset_tokens.sql',
    '20260622_000001_make_location_required.sql',
    '20260623_000001_add_performance_indexes.sql',
    '20260628_000001_create_authentication_throttles.sql',
    '20260628_000002_add_normalized_club_email_unique_index.sql',
    '20260629_000001_add_athlete_weight_category.sql',
    '20260629_000002_add_list_query_indexes.sql',
    '20260629_000003_snapshot_closed_event_entries.sql',
];

/**
 * This script creates and drops only databases under an explicit test prefix.
 * It never reads the application's normal DB_NAME setting.
 */
$prefix = (string) getenv('MIGRATION_TEST_DATABASE_PREFIX');
if (preg_match('/\Acompetizionijudo_test_[a-z0-9_]+\z/', $prefix) !== 1) {
    fwrite(STDERR, "MIGRATION_TEST_DATABASE_PREFIX must start with competizionijudo_test_.\n");
    exit(2);
}

$host = (string) (getenv('MIGRATION_TEST_HOST') ?: '127.0.0.1');
$port = (int) (getenv('MIGRATION_TEST_PORT') ?: 3306);
$user = (string) (getenv('MIGRATION_TEST_USER') ?: 'root');
$password = (string) (getenv('MIGRATION_TEST_PASSWORD') ?: '');
$databaseNames = [
    'clean' => $prefix . '_clean',
    'pre_squash' => $prefix . '_pre_squash',
    'guarded' => $prefix . '_guarded',
];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];
$server = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
    $user,
    $password,
    $options
);

try {
    foreach ($databaseNames as $databaseName) {
        recreateDatabase($server, $databaseName);
    }

    $clean = databaseConnection($host, $port, $databaseNames['clean'], $user, $password, $options);
    runMigrationsTwice($clean);
    assertSchemaContract($clean);
    assertCleanWritesAndReads($clean);

    $preSquash = databaseConnection(
        $host,
        $port,
        $databaseNames['pre_squash'],
        $user,
        $password,
        $options
    );
    preparePreSquashDatabase($preSquash);
    runMigrationsTwice($preSquash);
    assertSchemaContract($preSquash);
    assertPreSquashDataPreserved($preSquash);

    $guarded = databaseConnection(
        $host,
        $port,
        $databaseNames['guarded'],
        $user,
        $password,
        $options
    );
    assertExistingSchemaRejected($guarded);

    echo "Migration smoke checks passed for clean, pre-squash, and guarded schemas.\n";
} catch (Throwable $exception) {
    $message = $exception instanceof MigrationException
        ? $exception->getMessage()
        : 'Migration smoke check failed before a version could be applied.';
    fwrite(STDERR, $message . "\n");
    exit(1);
} finally {
    foreach ($databaseNames as $databaseName) {
        $server->exec('DROP DATABASE IF EXISTS ' . quoteIdentifier($databaseName));
    }
}

/** @param array<int, mixed> $options */
function databaseConnection(
    string $host,
    int $port,
    string $database,
    string $user,
    string $password,
    array $options
): PDO {
    return new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        ),
        $user,
        $password,
        $options
    );
}

function recreateDatabase(PDO $server, string $database): void
{
    $identifier = quoteIdentifier($database);
    $server->exec('DROP DATABASE IF EXISTS ' . $identifier);
    $server->exec('CREATE DATABASE ' . $identifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
}

function quoteIdentifier(string $identifier): string
{
    if (preg_match('/\A[a-z0-9_]+\z/', $identifier) !== 1) {
        throw new RuntimeException('Unsafe synthetic database identifier.');
    }

    return chr(96) . $identifier . chr(96);
}

function executeSqlFile(PDO $database, string $path): void
{
    $sql = file_get_contents($path);
    if (!is_string($sql)) {
        throw new RuntimeException('Unable to read synthetic schema fixture.');
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    if (!is_array($statements)) {
        throw new RuntimeException('Unable to parse synthetic schema fixture.');
    }

    foreach (array_filter(array_map('trim', $statements)) as $statement) {
        $database->exec($statement);
    }
}

function preparePreSquashDatabase(PDO $database): void
{
    executeSqlFile(
        $database,
        dirname(__DIR__) . '/migrations/20260630_000000_create_schema.sql'
    );
    $database->exec(
        'CREATE TABLE schema_migrations ('
        . 'id INT AUTO_INCREMENT PRIMARY KEY, '
        . 'version VARCHAR(255) NOT NULL UNIQUE, '
        . 'applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, '
        . 'description VARCHAR(255) NULL'
        . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $record = $database->prepare(
        'INSERT INTO schema_migrations (version, description) VALUES (?, ?)'
    );
    foreach (PRE_SQUASH_MIGRATIONS as $version) {
        $record->execute([$version, 'Synthetic pre-squash migration record']);
    }

    $database->exec(
        "INSERT INTO clubs (
            id, federal_code, name, email, phone, contact_first_name, contact_last_name,
            contact_phone, organization, recovery_email, password_hash
        ) VALUES (
            1, 'SYN-PRE-SQUASH-1', 'Synthetic Pre-squash Club',
            'presquash@example.test', '', 'Synthetic', 'Contact', '', 'TEST',
            'recovery@example.test', 'synthetic-hash'
        )"
    );
    $database->exec(
        "INSERT INTO athletes (
            id, club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt
        ) VALUES (
            1, 1, 'Synthetic', 'Athlete', 'M', '2010-01-01', 50, 'white'
        )"
    );
    $database->exec(
        "INSERT INTO events (id, name, date, location, published, closed)
         VALUES (1, 'Synthetic Pre-squash Event', '2026-07-01', 'Synthetic Venue', 1, 1)"
    );
    $database->exec(
        "INSERT INTO entries (
            id, event_id, club_id, athlete_id, snapshot_last_name,
            snapshot_first_name, snapshot_gender, snapshot_date_of_birth,
            snapshot_weight_kg, snapshot_belt, snapshot_program,
            snapshot_weight_category, snapshot_at
        ) VALUES (
            1, 1, 1, 1, 'Synthetic', 'Athlete', 'M', '2010-01-01',
            50, 'white', 'adulti', '-50 kg', '2026-06-30 12:00:00'
        )"
    );
}

function assertExistingSchemaRejected(PDO $database): void
{
    $database->exec('CREATE TABLE clubs (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');

    try {
        (new MigrationRunner($database))->run();
        throw new RuntimeException('Existing untracked application schema was accepted.');
    } catch (MigrationException $exception) {
        assertSameValue(
            '20260630_000000_create_schema.sql',
            $exception->version(),
            'Existing schema failed at the wrong migration.'
        );
    }

    $recorded = (int) $database->query(
        'SELECT COUNT(*) FROM schema_migrations'
    )->fetchColumn();
    assertSameValue(0, $recorded, 'Rejected baseline was recorded as applied.');

    $created = (int) $database->query(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events'"
    )->fetchColumn();
    assertSameValue(0, $created, 'Rejected baseline changed the application schema.');
}

function runMigrationsTwice(PDO $database): void
{
    $initialSqlMode = (string) $database->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    $runner = new MigrationRunner($database);
    $runner->run();
    assertMigrationCount($database);
    assertSameValue(
        $initialSqlMode,
        (string) $database->query('SELECT @@SESSION.sql_mode')->fetchColumn(),
        'Migration runner did not restore the session SQL mode.'
    );
    $runner->run();
    assertMigrationCount($database);
}

function assertMigrationCount(PDO $database): void
{
    $expected = count(glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
    $actual = (int) $database->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    assertSameValue($expected, $actual, 'Not every migration was recorded exactly once.');
}

function assertSchemaContract(PDO $database): void
{
    $requiredTables = [
        'schema_migrations',
        'clubs',
        'events',
        'athletes',
        'entries',
        'password_reset_tokens',
        'authentication_throttles',
    ];
    foreach ($requiredTables as $table) {
        $statement = $database->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);
        assertSameValue(1, (int) $statement->fetchColumn(), 'Missing required table: ' . $table);
    }

    assertColumn($database, 'clubs', 'normalized_email');
    assertColumn($database, 'events', 'location', 'NO');
    assertColumn($database, 'entries', 'athlete_id');
    $snapshotColumns = [
        'snapshot_last_name',
        'snapshot_first_name',
        'snapshot_gender',
        'snapshot_date_of_birth',
        'snapshot_weight_kg',
        'snapshot_belt',
        'snapshot_membership_number',
        'snapshot_program',
        'snapshot_weight_category',
        'snapshot_at',
    ];
    foreach ($snapshotColumns as $snapshotColumn) {
        assertColumn($database, 'entries', $snapshotColumn, 'YES');
    }
    assertColumnMissing($database, 'athletes', 'program');
    assertColumnMissing($database, 'athletes', 'weight_category');

    assertUniqueIndex(
        $database,
        'clubs',
        'uniq_clubs_normalized_email',
        'normalized_email'
    );
    assertUniqueIndex(
        $database,
        'entries',
        'unique_entry',
        'event_id,club_id,athlete_id'
    );
    assertIndex($database, 'clubs', 'idx_clubs_name_id', 'name,id');
    assertIndex(
        $database,
        'athletes',
        'idx_athletes_club_name_id',
        'club_id,last_name,first_name,id'
    );
    assertIndex($database, 'entries', 'idx_entries_club_event', 'club_id,event_id');
    assertIndexMissing($database, 'athletes', 'idx_athletes_club_id');

    $expectedForeignKeys = [
        'athletes.club_id=clubs.id',
        'entries.athlete_id=athletes.id',
        'entries.club_id=clubs.id',
        'entries.event_id=events.id',
        'password_reset_tokens.club_id=clubs.id',
    ];
    $statement = $database->query(
        'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
        . 'FROM information_schema.KEY_COLUMN_USAGE '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $actualForeignKeys = [];
    foreach ($statement->fetchAll() as $row) {
        $actualForeignKeys[] = sprintf(
            '%s.%s=%s.%s',
            $row['TABLE_NAME'],
            $row['COLUMN_NAME'],
            $row['REFERENCED_TABLE_NAME'],
            $row['REFERENCED_COLUMN_NAME']
        );
    }

    foreach ($expectedForeignKeys as $foreignKey) {
        if (!in_array($foreignKey, $actualForeignKeys, true)) {
            throw new RuntimeException('Missing required foreign key: ' . $foreignKey);
        }
    }
}

function assertColumn(
    PDO $database,
    string $table,
    string $column,
    ?string $nullable = null
): void {
    $statement = $database->prepare(
        'SELECT IS_NULLABLE FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    $actualNullable = $statement->fetchColumn();
    if (!is_string($actualNullable)) {
        throw new RuntimeException(sprintf('Missing required column: %s.%s', $table, $column));
    }

    if ($nullable !== null) {
        assertSameValue($nullable, $actualNullable, sprintf('Wrong nullability for %s.%s.', $table, $column));
    }
}

function assertColumnMissing(PDO $database, string $table, string $column): void
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);
    assertSameValue(0, (int) $statement->fetchColumn(), sprintf('Unexpected column: %s.%s', $table, $column));
}

function assertUniqueIndex(
    PDO $database,
    string $table,
    string $index,
    string $columns
): void {
    $statement = $database->prepare(
        'SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list '
        . 'FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? '
        . 'GROUP BY NON_UNIQUE'
    );
    $statement->execute([$table, $index]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Missing required unique index: ' . $index);
    }

    assertSameValue(0, (int) $row['NON_UNIQUE'], 'Index is not unique: ' . $index);
    assertSameValue($columns, (string) $row['columns_list'], 'Unexpected columns for index: ' . $index);
}

function assertIndex(PDO $database, string $table, string $index, string $columns): void
{
    $statement = $database->prepare(
        'SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list '
        . 'FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->execute([$table, $index]);
    $actualColumns = $statement->fetchColumn();
    if (!is_string($actualColumns)) {
        throw new RuntimeException('Missing required index: ' . $index);
    }

    assertSameValue($columns, $actualColumns, 'Unexpected columns for index: ' . $index);
}

function assertIndexMissing(PDO $database, string $table, string $index): void
{
    $statement = $database->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $statement->execute([$table, $index]);
    assertSameValue(0, (int) $statement->fetchColumn(), 'Redundant index remains: ' . $index);
}

function assertCleanWritesAndReads(PDO $database): void
{
    $database->exec(
        "INSERT INTO clubs (
            id, federal_code, name, email, phone, contact_first_name, contact_last_name,
            contact_phone, organization, recovery_email, password_hash
        ) VALUES (
            101, 'SYN-CLEAN-101', 'Synthetic Clean Club', 'clean@example.test', '',
            'Synthetic', 'Contact', '', 'TEST', 'recovery@example.test', 'synthetic-hash'
        )"
    );
    $database->exec(
        "INSERT INTO athletes (
            id, club_id, last_name, first_name, gender, date_of_birth,
            weight_kg, belt
        ) VALUES (
            201, 101, 'Synthetic', 'Athlete', 'M', '2010-01-01',
            50, 'white'
        )"
    );
    $database->exec(
        "INSERT INTO events (id, name, date, location, published, closed)
         VALUES (301, 'Synthetic Clean Event', '2026-07-01', 'Synthetic Venue', 1, 0)"
    );
    $database->exec(
        'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (301, 101, 201)'
    );
    $weight = $database->query(
        'SELECT athletes.weight_kg FROM entries '
        . 'INNER JOIN athletes ON athletes.id = entries.athlete_id '
        . 'WHERE entries.event_id = 301 AND entries.club_id = 101'
    )->fetchColumn();

    assertSameValue('50.00', $weight, 'Clean schema athlete write or entry read failed.');
}

function assertPreSquashDataPreserved(PDO $database): void
{
    $club = $database->query(
        'SELECT federal_code, email FROM clubs WHERE id = 1'
    )->fetch();
    if (!is_array($club)) {
        throw new RuntimeException('Pre-squash club fixture disappeared.');
    }
    assertSameValue('SYN-PRE-SQUASH-1', $club['federal_code'], 'Pre-squash federal code changed.');
    assertSameValue('presquash@example.test', $club['email'], 'Pre-squash email changed.');

    $event = $database->query(
        'SELECT name, date, location FROM events WHERE id = 1'
    )->fetch();
    if (!is_array($event)) {
        throw new RuntimeException('Pre-squash event fixture disappeared.');
    }
    assertSameValue('Synthetic Pre-squash Event', $event['name'], 'Pre-squash event name changed.');
    assertSameValue('2026-07-01', $event['date'], 'Pre-squash event date changed.');
    assertSameValue('Synthetic Venue', $event['location'], 'Pre-squash event location changed.');

    $athlete = $database->query('SELECT last_name, first_name FROM athletes WHERE id = 1')->fetch();
    if (!is_array($athlete)) {
        throw new RuntimeException('Pre-squash athlete fixture disappeared.');
    }
    assertSameValue('Synthetic', $athlete['last_name'], 'Pre-squash athlete surname changed.');
    assertSameValue('Athlete', $athlete['first_name'], 'Pre-squash athlete name changed.');

    $snapshot = $database->query(
        'SELECT snapshot_last_name, snapshot_date_of_birth, snapshot_weight_kg, '
        . 'snapshot_program, snapshot_weight_category, snapshot_at FROM entries WHERE id = 1'
    )->fetch();
    if (!is_array($snapshot)) {
        throw new RuntimeException('Pre-squash closed-entry snapshot disappeared.');
    }
    assertSameValue('Synthetic', $snapshot['snapshot_last_name'], 'Pre-squash snapshot name changed.');
    assertSameValue('2010-01-01', $snapshot['snapshot_date_of_birth'], 'Pre-squash snapshot birth date changed.');
    assertSameValue('50.00', $snapshot['snapshot_weight_kg'], 'Pre-squash snapshot weight changed.');
    assertSameValue('adulti', $snapshot['snapshot_program'], 'Pre-squash snapshot program changed.');
    assertSameValue('-50 kg', $snapshot['snapshot_weight_category'], 'Pre-squash snapshot category changed.');
    if (!is_string($snapshot['snapshot_at']) || $snapshot['snapshot_at'] === '') {
        throw new RuntimeException('Pre-squash snapshot timestamp disappeared.');
    }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message);
    }
}
