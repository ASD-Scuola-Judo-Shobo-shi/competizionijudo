<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

try {
    App\Service\AutomaticMigrationSafety::assertSafe($argv[1] ?? null);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

echo "Automatic migration safety check passed.\n";
