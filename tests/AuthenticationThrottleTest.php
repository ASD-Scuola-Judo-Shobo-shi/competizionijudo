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
            self::assertTrue($throttle->consume(
                'club-login',
                ' Club@Example.Test ',
                '198.51.100.12'
            ));
        }

        self::assertFalse($throttle->consume('club-login', 'club@example.test', '198.51.100.12'));
        $newInstance = new DatabaseAuthenticationThrottle($database, $clock);
        self::assertFalse($newInstance->consume('CLUB-LOGIN', 'CLUB@EXAMPLE.TEST', '198.51.100.12'));
        self::assertCount(3, $database->records);

        foreach (array_keys($database->records) as $key) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        }
        $persistedData = serialize([$database->records, $database->executedParameters]);
        self::assertStringNotContainsString('club@example.test', strtolower($persistedData));
        self::assertStringNotContainsString('198.51.100.12', $persistedData);

        $now = $now->modify('+301 seconds');
        self::assertTrue($newInstance->consume('club-login', 'club@example.test', '198.51.100.12'));

        $attemptCounts = array_column(array_values($database->records), 'attempt_count');
        sort($attemptCounts);
        self::assertSame([1, 1, 6], $attemptCounts);
    }

    public function testAccountLimitBlocksDistributedGuessingAcrossNetworks(): void
    {
        $database = new InMemoryThrottleDatabase();
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $throttle = new DatabaseAuthenticationThrottle($database, static fn(): DateTimeImmutable => $now);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            self::assertTrue($throttle->consume(
                'club-login',
                'target@example.test',
                '198.51.100.' . ($attempt + 1)
            ));
        }

        self::assertFalse($throttle->consume(
            'club-login',
            'target@example.test',
            '203.0.113.200'
        ));
    }

    public function testNetworkLimitBlocksBroadCredentialStuffing(): void
    {
        $database = new InMemoryThrottleDatabase();
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $throttle = new DatabaseAuthenticationThrottle($database, static fn(): DateTimeImmutable => $now);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            self::assertTrue($throttle->consume(
                'club-login',
                'candidate-' . $attempt . '@example.test',
                '203.0.113.10'
            ));
        }

        self::assertFalse($throttle->consume(
            'club-login',
            'next-candidate@example.test',
            '203.0.113.10'
        ));
    }

    public function testSuccessfulAuthenticationClearsAccountAndPairButKeepsNetworkLimit(): void
    {
        $database = new InMemoryThrottleDatabase();
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $throttle = new DatabaseAuthenticationThrottle($database, static fn(): DateTimeImmutable => $now);

        self::assertTrue($throttle->consume('club-login', 'club@example.test', '203.0.113.10'));
        self::assertCount(3, $database->records);

        $throttle->clear('club-login', 'club@example.test', '203.0.113.10');

        self::assertCount(1, $database->records);
    }

    public function testCleanupDeletesAtMostOneBoundedBatch(): void
    {
        $database = new InMemoryThrottleDatabase();
        $database->seedStaleRecords(105);
        $now = new DateTimeImmutable('2026-06-28 10:00:00', new DateTimeZone('UTC'));
        $throttle = new DatabaseAuthenticationThrottle($database, static fn(): DateTimeImmutable => $now);

        self::assertTrue($throttle->consume('password-reset', 'fixture@example.test', '203.0.113.10'));

        self::assertSame(100, $database->cleanupDeleted);
        self::assertCount(8, $database->records);
        self::assertTrue($database->sawSql('DELETE FROM authentication_throttles WHERE updated_at < ? LIMIT 100'));
    }

    public function testMigrationStoresOnlyHashedThrottleKeysAndSupportsExpiryCleanup(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260630_000000_create_schema.sql'
        );

        self::assertIsString($migration);
        self::assertStringContainsString('CREATE TABLE authentication_throttles', $migration);
        self::assertStringContainsString('throttle_key CHAR(64)', $migration);
        self::assertStringContainsString('idx_authentication_throttles_updated_at', $migration);
        self::assertDoesNotMatchRegularExpression(
            '/CREATE TABLE authentication_throttles \([^;]*\b(email|ip|remote_addr)\b/is',
            $migration
        );
    }
}
