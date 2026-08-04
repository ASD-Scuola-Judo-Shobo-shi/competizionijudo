<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class PrivacyPurgeCommandTest extends TestCase
{
    public function testFailureIsRedactedAndCorrelatedThroughTheApplicationLogger(): void
    {
        $command = (string) file_get_contents(
            dirname(__DIR__) . '/scripts/purge-expired-data.php'
        );

        self::assertStringContainsString('use App\\Core\\FileLogger;', $command);
        self::assertStringContainsString("'privacy.expired_data_purge_failure'", $command);
        self::assertStringContainsString('FileLogger::application()->error(', $command);
        self::assertStringNotContainsString('$exception->getMessage()', $command);
        self::assertStringContainsString('reference %s', $command);
    }
}
