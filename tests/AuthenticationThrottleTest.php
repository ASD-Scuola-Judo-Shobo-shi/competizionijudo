<?php

declare(strict_types=1);

namespace Tests;

use App\Security\DatabaseAuthenticationThrottle;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryThrottleDatabase;

final class AuthenticationThrottleTest extends TestCase
{
    public function testLimitPersistsAcrossInstancesWithoutStoringRawIdentifiers(): void
    {
        $database = new InMemoryThrottleDatabase();
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $clock = static function () use (&$now): DateTimeImmutable {
            return $now;
        };
        $throttle = new DatabaseAuthenticationThrottle($database, $clock);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $throttle->recordAttempt(
                'club-login',
                ' Club@Example.Test ',
                '198.51.100.12'
            );
        }

        self::assertTrue($throttle->isBlocked('club-login', 'club@example.test', '198.51.100.12'));
        $newInstance = new DatabaseAuthenticationThrottle($database, $clock);
        self::assertTrue($newInstance->isBlocked('CLUB-LOGIN', 'CLUB@EXAMPLE.TEST', '198.51.100.12'));
        self::assertCount(1, $database->records);

        $key = array_key_first($database->records);
        self::assertIsString($key);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        $persistedData = serialize([$database->records, $database->executedParameters]);
        self::assertStringNotContainsString('club@example.test', strtolower($persistedData));
        self::assertStringNotContainsString('198.51.100.12', $persistedData);

        $now = $now->modify('+301 seconds');
        self::assertFalse($newInstance->isBlocked('club-login', 'club@example.test', '198.51.100.12'));
        $newInstance->recordAttempt('club-login', 'club@example.test', '198.51.100.12');

        self::assertSame(1, $database->records[$key]['attempt_count']);
        self::assertNull($database->records[$key]['blocked_until']);
    }

    public function testCleanupDeletesAtMostOneBoundedBatch(): void
    {
        $database = new InMemoryThrottleDatabase();
        $database->seedStaleRecords(105);
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $throttle = new DatabaseAuthenticationThrottle($database, static fn(): DateTimeImmutable => $now);

        $throttle->recordAttempt('password-reset', 'fixture@example.test', '203.0.113.10');

        self::assertSame(100, $database->cleanupDeleted);
        self::assertCount(6, $database->records);
        self::assertTrue($database->sawSql('DELETE FROM authentication_throttles WHERE updated_at < ? LIMIT 100'));
    }

    public function testMigrationStoresOnlyHashedThrottleKeysAndSupportsExpiryCleanup(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260628_000001_create_authentication_throttles.sql'
        );

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS authentication_throttles', $migration);
        self::assertStringContainsString('throttle_key CHAR(64)', $migration);
        self::assertStringContainsString('idx_authentication_throttles_updated_at', $migration);
        self::assertDoesNotMatchRegularExpression('/\b(email|ip|remote_addr)\b/i', $migration);
    }
}
