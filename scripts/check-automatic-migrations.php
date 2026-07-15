<?php

declare(strict_types=1);

const CONSOLIDATED_BASELINE = '20260630_000000_create_schema.sql';

$directory = $argv[1] ?? dirname(__DIR__) . '/migrations';
if (!is_dir($directory)) {
    fwrite(STDERR, "Migration directory is unavailable.\n");
    exit(1);
}

$migrations = glob(rtrim($directory, DIRECTORY_SEPARATOR) . '/*.sql') ?: [];
sort($migrations, SORT_STRING);

foreach ($migrations as $migration) {
    if (basename($migration) === CONSOLIDATED_BASELINE) {
        continue;
    }

    $sql = file_get_contents($migration);
    if (!is_string($sql)) {
        fwrite(STDERR, "Unable to read migration " . basename($migration) . ".\n");
        exit(1);
    }

    $statements = preg_split('/;\s*(?:\r?\n|$)/', preg_replace('/^\s*--.*$/m', '', $sql) ?? '');
    if (!is_array($statements)) {
        fwrite(STDERR, "Unable to parse migration " . basename($migration) . ".\n");
        exit(1);
    }

    foreach (array_filter(array_map('trim', $statements)) as $statement) {
        if (
            preg_match(
                '/\ACREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?[a-z0-9_]+`?\s*\(/i',
                $statement
            ) !== 1
        ) {
            fwrite(
                STDERR,
                'Automatic deployment accepts only additive CREATE TABLE IF NOT EXISTS migrations: '
                . basename($migration) . ".\n"
            );
            exit(1);
        }
    }
}

echo "Automatic migration safety check passed.\n";
