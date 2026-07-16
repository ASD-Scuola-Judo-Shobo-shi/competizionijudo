<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

final class AutomaticMigrationSafety
{
    private const CONSOLIDATED_BASELINE = '20260630_000000_create_schema.sql';

    public static function assertSafe(?string $directory = null): void
    {
        $directory ??= base_path('migrations');
        if (!is_dir($directory)) {
            throw new RuntimeException('Migration directory is unavailable.');
        }

        $migrations = glob(rtrim($directory, DIRECTORY_SEPARATOR) . '/*.sql') ?: [];
        sort($migrations, SORT_STRING);

        foreach ($migrations as $migration) {
            if (basename($migration) === self::CONSOLIDATED_BASELINE) {
                continue;
            }

            $sql = file_get_contents($migration);
            if (!is_string($sql)) {
                throw new RuntimeException('Unable to read migration ' . basename($migration) . '.');
            }

            $statements = preg_split('/;\s*(?:\r?\n|$)/', preg_replace('/^\s*--.*$/m', '', $sql) ?? '');
            if (!is_array($statements)) {
                throw new RuntimeException('Unable to parse migration ' . basename($migration) . '.');
            }

            foreach (array_filter(array_map('trim', $statements)) as $statement) {
                if (
                    preg_match(
                        '/\ACREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?[a-z0-9_]+`?\s*\(/i',
                        $statement
                    ) !== 1
                ) {
                    throw new RuntimeException(
                        'Automatic deployment accepts only additive CREATE TABLE IF NOT EXISTS migrations: '
                        . basename($migration) . '.'
                    );
                }
            }
        }
    }
}
