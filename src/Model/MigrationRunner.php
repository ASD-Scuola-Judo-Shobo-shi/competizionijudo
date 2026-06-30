<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private const CONSOLIDATED_BASELINE = '20260630_000000_create_schema.sql';
    private const SQUASHED_VERSIONS = [
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

    private readonly string $migrationDirectory;

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
        $applied = $this->appliedVersions();

        foreach ($this->migrationFiles() as $migration) {
            $version = basename($migration);
            if (in_array($version, $applied, true)) {
                continue;
            }

            if ($version === self::CONSOLIDATED_BASELINE) {
                if ($this->canAdoptBaseline($applied)) {
                    $this->adoptBaseline($version);
                } elseif ($this->hasSquashedHistory($applied)) {
                    throw new MigrationException(
                        $version,
                        new RuntimeException('The pre-squash migration history is incomplete.')
                    );
                } else {
                    $this->applyMigration($migration);
                }
            } else {
                $this->applyMigration($migration);
            }
            $applied[] = $version;
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

    /** @return list<string> */
    private function appliedVersions(): array
    {
        $statement = $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version');
        if ($statement === false) {
            throw new RuntimeException('Unable to read migration history.');
        }

        return array_values(array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationDirectory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        return array_values($files);
    }

    /** @param list<string> $applied */
    private function canAdoptBaseline(array $applied): bool
    {
        return array_diff(self::SQUASHED_VERSIONS, $applied) === [];
    }

    /** @param list<string> $applied */
    private function hasSquashedHistory(array $applied): bool
    {
        return array_intersect(self::SQUASHED_VERSIONS, $applied) !== [];
    }

    private function applyMigration(string $path): void
    {
        $version = basename($path);
        $sql = file_get_contents($path);
        if (!is_string($sql)) {
            throw new MigrationException($version, new RuntimeException('Unable to read migration file.'));
        }

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        if (!is_array($statements)) {
            throw new MigrationException($version, new RuntimeException('Unable to parse migration statements.'));
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('Unable to begin migration transaction.');
            }

            foreach (array_filter(array_map('trim', $statements)) as $statement) {
                $this->pdo->exec($statement);
            }

            $this->recordMigration($version, $this->migrationDescription($sql));
            if ($this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new MigrationException($version, $exception);
        }
    }

    private function adoptBaseline(string $version): void
    {
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new RuntimeException('Unable to begin baseline adoption transaction.');
            }
            $this->recordMigration($version, 'Adopted complete pre-squash migration history');

            $placeholders = implode(', ', array_fill(0, count(self::SQUASHED_VERSIONS), '?'));
            $statement = $this->pdo->prepare(
                'DELETE FROM schema_migrations WHERE version IN (' . $placeholders . ')'
            );
            if ($statement === false) {
                throw new RuntimeException('Unable to prepare migration history consolidation.');
            }
            $statement->execute(self::SQUASHED_VERSIONS);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new MigrationException($version, $exception);
        }
    }

    private function recordMigration(string $version, string $description): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO schema_migrations (version, description) VALUES (?, ?)'
        );
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare migration record.');
        }
        $statement->execute([$version, $description]);
    }

    private function migrationDescription(string $sql): string
    {
        $firstStatement = '';
        $statements = preg_split('/;\s*(?:\r?\n|$)/', trim($sql));
        if (!empty($statements) && trim($statements[0]) !== '') {
            $firstStatement = preg_replace('/\s+/', ' ', trim($statements[0]));
        }
        if (!is_string($firstStatement) || $firstStatement === '') {
            return 'No SQL statement parsed';
        }

        return mb_substr($firstStatement, 0, 250);
    }
}
