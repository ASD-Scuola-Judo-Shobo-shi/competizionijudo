<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private const LEGACY_COPY_MIGRATION = '20260619_000001_copy_italian_columns_to_english.sql';
    private const LEGACY_MARKER_COLUMNS = [
        ['clubs', 'codice_federale'],
        ['events', 'nome_evento'],
        ['athletes', 'cognome'],
    ];
    private const LEGACY_COPY_COLUMNS = [
        'clubs.federal_code' => ['federal_code', 'codice_federale'],
        'clubs.name' => ['name', 'nome_societa'],
        'clubs.email' => ['email', 'email_societa'],
        'clubs.phone' => ['phone', 'telefono_societa'],
        'clubs.contact_first_name' => ['contact_first_name', 'nome_referente'],
        'clubs.contact_last_name' => ['contact_last_name', 'cognome_referente'],
        'clubs.contact_phone' => ['contact_phone', 'telefono_referente'],
        'clubs.contact_email' => ['contact_email', 'email_referente'],
        'clubs.organization' => ['organization', 'ente'],
        'clubs.recovery_email' => ['recovery_email', 'email_recupero'],
        'events.name' => ['name', 'nome_evento'],
        'events.date' => ['date', 'data_gara'],
        'events.location' => ['location', 'luogo'],
        'events.organizer' => ['organizer', 'organizzatore'],
        'events.registration_deadline' => ['registration_deadline', 'scadenza_iscrizioni'],
        'events.type' => ['type', 'tipo_evento'],
        'events.description' => ['description', 'descrizione'],
        'events.notes' => ['notes', 'note'],
        'events.poster_file' => ['poster_file'],
        'events.info_file' => ['info_file'],
        'events.published' => ['published', 'pubblicato'],
        'events.closed' => ['closed', 'chiuso'],
        'athletes.last_name' => ['last_name', 'cognome'],
        'athletes.first_name' => ['first_name', 'nome'],
        'athletes.gender' => ['gender', 'sesso'],
        'athletes.date_of_birth' => ['date_of_birth', 'nascita'],
        'athletes.weight_kg' => ['weight_kg', 'actual_weight_kg', 'peso_reale_kg'],
        'athletes.belt' => ['belt', 'cintura'],
        'athletes.age_class' => ['age_class', 'classe_eta'],
        'athletes.program' => ['program', 'programma'],
        'athletes.weight_category' => ['weight_category', 'categoria_peso'],
        'athletes.membership_number' => ['membership_number', 'numero_tessera'],
        'athletes.notes' => ['notes', 'note'],
    ];

    private readonly string $migrationDirectory;
    /** @var array<string, list<string>> */
    private array $tableColumns = [];

    public function __construct(private readonly PDO $pdo, ?string $migrationDirectory = null)
    {
        $this->migrationDirectory = rtrim(
            $migrationDirectory ?? base_path('migrations'),
            DIRECTORY_SEPARATOR
        );
    }

    public function run(): void
    {
        $this->ensureMigrationTableExists();

        foreach ($this->pendingMigrations() as $migration) {
            $this->applyMigration($migration);
        }
    }

    private function ensureMigrationTableExists(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (' .
            'id INT AUTO_INCREMENT PRIMARY KEY, ' .
            'version VARCHAR(255) NOT NULL UNIQUE, ' .
            'applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ' .
            'description VARCHAR(255) NULL' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return string[] */
    private function pendingMigrations(): array
    {
        $applied = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $files = glob($this->migrationDirectory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_filter($files, static fn (string $file) => !in_array(basename($file), $applied, true));
    }

    private function applyMigration(string $path): void
    {
        $version = basename($path);
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new MigrationException(
                $version,
                new RuntimeException('Unable to read migration file.')
            );
        }

        $sql = preg_replace('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\s*;?\s*$/im', '', $sql);
        if (!is_string($sql)) {
            throw new MigrationException(
                $version,
                new RuntimeException('Unable to normalize migration statements.')
            );
        }

        $applicable = false;
        $originalSqlMode = null;
        $failure = null;
        try {
            $applicable = $version !== self::LEGACY_COPY_MIGRATION || $this->legacySchemaPresent();
            $originalSqlMode = $applicable && $version === self::LEGACY_COPY_MIGRATION
                ? $this->enableLegacyDateCompatibility()
                : null;
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('Unable to begin migration transaction.');
            }

            if ($applicable) {
                $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
                if (!is_array($statements)) {
                    throw new RuntimeException('Unable to parse migration statements.');
                }

                foreach (array_filter(array_map('trim', $statements)) as $statement) {
                    if (
                        $version === self::LEGACY_COPY_MIGRATION
                        && !$this->legacyStatementApplicable($statement)
                    ) {
                        continue;
                    }

                    $this->pdo->exec($statement);
                }
            }

            if ($originalSqlMode !== null) {
                $this->restoreSqlMode($originalSqlMode);
                $originalSqlMode = null;
            }

            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, description) VALUES (?, ?)');
            if ($stmt === false) {
                throw new RuntimeException('Unable to prepare migration record.');
            }
            $description = $applicable
                ? $this->migrationDescription($sql)
                : 'Legacy copy not applicable to this schema';
            $stmt->execute([$version, $description]);
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $failure = $exception;
        }

        if ($originalSqlMode !== null) {
            try {
                $this->restoreSqlMode($originalSqlMode);
            } catch (\Throwable $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure !== null) {
            throw new MigrationException($version, $failure);
        }
    }

    private function legacySchemaPresent(): bool
    {
        foreach (self::LEGACY_MARKER_COLUMNS as [$table, $column]) {
            if (in_array($column, $this->columnsFor($table), true)) {
                return true;
            }
        }

        return false;
    }

    private function legacyStatementApplicable(string $statement): bool
    {
        if (preg_match('/\A(?:--[^\n]*\n\s*)*UPDATE\s+(\w+)\s+SET\s+(\w+)\s*=/i', $statement, $matches) !== 1) {
            throw new RuntimeException('Unexpected statement in legacy copy migration.');
        }

        $table = strtolower($matches[1]);
        $target = strtolower($matches[2]);
        $requiredColumns = self::LEGACY_COPY_COLUMNS[$table . '.' . $target] ?? null;
        if ($requiredColumns === null) {
            throw new RuntimeException('Unknown target in legacy copy migration.');
        }

        $availableColumns = $this->columnsFor($table);
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $availableColumns, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        if (isset($this->tableColumns[$table])) {
            return $this->tableColumns[$table];
        }

        $statement = $this->pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to inspect migration schema.');
        }
        $statement->execute([$table]);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN);

        $this->tableColumns[$table] = array_values(array_map('strval', $columns ?: []));

        return $this->tableColumns[$table];
    }

    private function enableLegacyDateCompatibility(): ?string
    {
        $original = (string) $this->pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
        $modes = array_values(array_filter(
            explode(',', $original),
            static fn(string $mode): bool => !in_array(
                $mode,
                [
                    'NO_ZERO_DATE',
                    'NO_ZERO_IN_DATE',
                    'STRICT_ALL_TABLES',
                    'STRICT_TRANS_TABLES',
                ],
                true
            )
        ));
        $compatible = implode(',', $modes);
        if ($compatible === $original) {
            return null;
        }

        $this->pdo->exec('SET SESSION sql_mode = ' . $this->pdo->quote($compatible));

        return $original;
    }

    private function restoreSqlMode(string $sqlMode): void
    {
        $this->pdo->exec('SET SESSION sql_mode = ' . $this->pdo->quote($sqlMode));
    }

    private function migrationDescription(string $sql): string
    {
        $firstStatement = '';
        $statements = preg_split('/;\s*(?:\r?\n|$)/', trim($sql));

        if (!empty($statements) && trim($statements[0]) !== '') {
            $firstStatement = preg_replace('/\s+/', ' ', trim($statements[0]));
        }

        if ($firstStatement === '') {
            return 'No SQL statement parsed';
        }

        return mb_substr($firstStatement, 0, 250);
    }
}
