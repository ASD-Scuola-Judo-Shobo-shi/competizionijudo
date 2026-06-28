<?php

declare(strict_types=1);

namespace Tests;

use App\Service\DatabasePasswordResetRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PasswordResetRepositoryTest extends TestCase
{
    public function testTokenSucceedsOnceAndASecondConsumerFailsAfterTheLock(): void
    {
        $tokenHash = hash('sha256', 'one-use-token-fixture');
        $passwordHash = hash('sha256', 'replacement-password-fixture');
        $now = '2026-06-28 12:00:00';
        $candidate = $this->createMock(PDOStatement::class);
        $candidate->expects(self::exactly(2))
            ->method('execute')
            ->with([$tokenHash, $now])
            ->willReturn(true);
        $candidate->method('fetch')->willReturnOnConsecutiveCalls(
            ['id' => 41, 'club_id' => 7],
            false
        );
        $claim = $this->executingStatement([41, $now], 1);
        $password = $this->executingStatement([$passwordHash, 7], 1);
        $invalidate = $this->executingStatement([7]);
        $queries = [];
        $database = $this->database(
            static function (string $sql) use (
                $candidate,
                $claim,
                $password,
                $invalidate,
                &$queries
            ): PDOStatement {
                $queries[] = $sql;

                return match (true) {
                    str_starts_with($sql, 'SELECT id, club_id') => $candidate,
                    str_starts_with($sql, 'UPDATE password_reset_tokens SET used = 1 WHERE id') => $claim,
                    str_starts_with($sql, 'UPDATE clubs SET password_hash') => $password,
                    str_contains($sql, 'WHERE club_id = ?') => $invalidate,
                    default => throw new RuntimeException('Unexpected password reset query.'),
                };
            },
            2,
            2
        );
        $repository = $this->repository($database);

        self::assertTrue($repository->consume($tokenHash, $passwordHash));
        self::assertFalse($repository->consume($tokenHash, $passwordHash));
        self::assertTrue($this->containsSql($queries, 'LIMIT 1 FOR UPDATE'));
        self::assertTrue($this->containsSql($queries, 'WHERE id = ? AND used = 0 AND expires_at > ?'));
        self::assertTrue($this->containsSql($queries, 'WHERE club_id = ? AND used = 0'));
    }

    #[DataProvider('unavailableTokenStates')]
    public function testExpiredAndReusedTokensFailBeforePasswordWrite(string $state): void
    {
        $candidate = $this->createMock(PDOStatement::class);
        $candidate->expects(self::once())->method('execute')->willReturn(true);
        $candidate->method('fetch')->willReturn(false);
        $queries = [];
        $database = $this->database(
            static function (string $sql) use ($candidate, &$queries): PDOStatement {
                $queries[] = $sql;
                if (!str_starts_with($sql, 'SELECT id, club_id')) {
                    throw new RuntimeException('Password write was reached for an unavailable token.');
                }

                return $candidate;
            },
            1,
            1
        );

        $result = $this->repository($database)->consume(
            hash('sha256', $state . '-token-fixture'),
            hash('sha256', 'unused-password-fixture')
        );

        self::assertFalse($result);
        self::assertTrue($this->containsSql($queries, 'used = 0 AND expires_at > ?'));
    }

    /** @return iterable<string, array{string}> */
    public static function unavailableTokenStates(): iterable
    {
        yield 'expired' => ['expired'];
        yield 'reused' => ['reused'];
    }

    public function testConcurrentClaimLossRollsBackWithoutChangingPassword(): void
    {
        $candidate = $this->createMock(PDOStatement::class);
        $candidate->expects(self::once())->method('execute')->willReturn(true);
        $candidate->method('fetch')->willReturn(['id' => 52, 'club_id' => 9]);
        $claim = $this->executingStatement([52, '2026-06-28 12:00:00'], 0);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('beginTransaction')->willReturn(true);
        $database->expects(self::once())->method('rollBack')->willReturn(true);
        $database->expects(self::never())->method('commit');
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($candidate, $claim);
        $repository = $this->repository($database);

        self::assertFalse($repository->consume(
            hash('sha256', 'contended-token-fixture'),
            hash('sha256', 'contended-password-fixture')
        ));
    }

    public function testAdministrativePasswordReplacementInvalidatesOutstandingTokens(): void
    {
        $passwordHash = hash('sha256', 'administrative-password-fixture');
        $password = $this->executingStatement([$passwordHash, 13], 1);
        $invalidate = $this->executingStatement([13]);
        $database = $this->database(
            static fn(string $sql): PDOStatement => str_starts_with($sql, 'UPDATE clubs')
                ? $password
                : $invalidate,
            1,
            1
        );

        $this->repository($database)->replacePassword(13, $passwordHash);
    }

    public function testTokenInvalidationFailureRollsBackThePasswordChange(): void
    {
        $passwordHash = hash('sha256', 'rollback-password-fixture');
        $password = $this->executingStatement([$passwordHash, 21], 1);
        $invalidate = $this->createMock(PDOStatement::class);
        $invalidate->expects(self::once())
            ->method('execute')
            ->with([21])
            ->willThrowException(new RuntimeException('Synthetic invalidation failure.'));
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('beginTransaction')->willReturn(true);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($password, $invalidate);
        $database->expects(self::once())->method('inTransaction')->willReturn(true);
        $database->expects(self::once())->method('rollBack')->willReturn(true);
        $database->expects(self::never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->repository($database)->replacePassword(21, $passwordHash);
    }

    private function repository(PDO $database): DatabasePasswordResetRepository
    {
        return new DatabasePasswordResetRepository(
            $database,
            static fn(): DateTimeImmutable => new DateTimeImmutable(
                '2026-06-28 12:00:00',
                new DateTimeZone('UTC')
            )
        );
    }

    /** @param list<mixed> $parameters */
    private function executingStatement(array $parameters, int $rowCount = 0): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with($parameters)
            ->willReturn(true);
        $statement->method('rowCount')->willReturn($rowCount);

        return $statement;
    }

    /**
     * @param callable(string): PDOStatement $prepare
     */
    private function database(callable $prepare, int $begins, int $commits): PDO&MockObject
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly($begins))->method('beginTransaction')->willReturn(true);
        $database->expects(self::exactly($commits))->method('commit')->willReturn(true);
        $database->method('prepare')->willReturnCallback($prepare);

        return $database;
    }

    /** @param list<string> $queries */
    private function containsSql(array $queries, string $fragment): bool
    {
        foreach ($queries as $query) {
            if (str_contains($query, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
