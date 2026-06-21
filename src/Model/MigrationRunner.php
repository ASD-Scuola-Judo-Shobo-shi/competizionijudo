<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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

        $files = glob(base_path('migrations/*.sql')) ?: [];
        sort($files, SORT_STRING);

        return array_filter($files, static fn (string $file) => !in_array(basename($file), $applied, true));
    }

    private function applyMigration(string $path): void
    {
        $version = basename($path);
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException(sprintf('Unable to read migration file: %s', $path));
        }

        $sql = preg_replace('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\s*;?\s*$/im', '', $sql);

        $this->pdo->beginTransaction();
        try {
            $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
            foreach (array_filter(array_map('trim', $statements)) as $statement) {
                try {
                    $this->pdo->exec($statement);
                } catch (\PDOException $e) {
                    if ($e->getCode() === '42S22' && stripos($e->getMessage(), 'Unknown column') !== false) {
                        continue;
                    }

                    throw $e;
                }
            }

            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version, description) VALUES (?, ?)');
            $stmt->execute([$version, $this->migrationDescription($sql)]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
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
