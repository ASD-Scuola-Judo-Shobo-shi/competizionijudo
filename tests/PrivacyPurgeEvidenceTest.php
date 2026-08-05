<?php

declare(strict_types=1);

namespace Tests;

use App\Service\PrivacyPurgeEvidence;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PrivacyPurgeEvidenceTest extends TestCase
{
    public function testRecordsAppendTimestampedJsonLines(): void
    {
        $path = sys_get_temp_dir() . '/privacy-evidence-' . bin2hex(random_bytes(8)) . '.log';

        try {
            $evidence = new PrivacyPurgeEvidence($path);
            $evidence->record('ok', [
                'entries_purged' => 3,
                'registration_confirmations_purged' => 2,
                'password_reset_tokens_purged' => 1,
                'log_retention_days' => 30,
                'backup_retention_days' => 30,
                'application_log_age_days' => null,
            ]);
            $evidence->record('failed', [], '0123456789abcdef0123456789abcdef');

            $contents = (string) file_get_contents($path);
            $lines = array_values(array_filter(explode(PHP_EOL, trim($contents))));
            self::assertCount(2, $lines);

            $success = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($success);
            self::assertMatchesRegularExpression(
                '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z\z/',
                (string) $success['timestamp']
            );
            self::assertSame('ok', $success['status']);
            self::assertSame(3, $success['facts']['entries_purged']);
            self::assertSame(30, $success['facts']['log_retention_days']);
            self::assertNull($success['facts']['application_log_age_days']);

            $failure = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($failure);
            self::assertSame('failed', $failure['status']);
            self::assertSame('0123456789abcdef0123456789abcdef', $failure['correlation_id']);
            self::assertSame([], $failure['facts']);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testUnwritableEvidenceTargetFailsLoudly(): void
    {
        $this->expectException(RuntimeException::class);

        (new PrivacyPurgeEvidence('/nonexistent-dir/privacy-evidence.log'))->record('ok', []);
    }
}
