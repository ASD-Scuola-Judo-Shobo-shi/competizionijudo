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

    public function testEveryRunWritesAppendOnlyEvidenceWithRetentionFacts(): void
    {
        $command = (string) file_get_contents(
            dirname(__DIR__) . '/scripts/purge-expired-data.php'
        );
        $evidence = (string) file_get_contents(
            dirname(__DIR__) . '/src/Service/PrivacyPurgeEvidence.php'
        );

        self::assertStringContainsString(
            'new PrivacyPurgeEvidence(base_path(\'var/log/privacy-purge-evidence.log\'))',
            $command
        );
        self::assertStringContainsString("\$evidence->record('ok'", $command);
        self::assertStringContainsString("\$evidence->record('failed'", $command);
        self::assertStringContainsString("'entries_purged' =>", $command);
        self::assertStringContainsString("'registration_confirmations_purged' =>", $command);
        self::assertStringContainsString("'password_reset_tokens_purged' =>", $command);
        self::assertStringContainsString("env('APP_LOG_RETENTION_DAYS'", $command);
        self::assertStringContainsString("env('APP_BACKUP_RETENTION_DAYS'", $command);
        self::assertStringContainsString("'application_log_age_days' =>", $command);
        self::assertStringContainsString("'correlation_id' =>", $evidence);
        self::assertStringContainsString('gmdate', $evidence);
        self::assertStringContainsString('FILE_APPEND | LOCK_EX', $evidence);
    }
}
