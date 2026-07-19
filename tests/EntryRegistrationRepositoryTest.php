<?php

declare(strict_types=1);

namespace Tests;

use App\Model\EntryRegistrationRepository;
use App\Model\EntryRegistrationResult;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EntryRegistrationRepositoryTest extends TestCase
{
    public function testOwnAthleteIsRegisteredByOneConstrainedStatement(): void
    {
        $repository = $this->repositoryReturningRowCount(1, 301, false);

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Registered, $result);
    }

    public function testForeignAthleteIsRejectedWithoutAnInsert(): void
    {
        $repository = $this->repositoryReturningRowCount(0, 302, false);

        $result = $repository->register(101, 201, 302, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testMissingAthleteIsRejectedWithoutAnInsert(): void
    {
        $repository = $this->repositoryReturningRowCount(0, 999, false);

        $result = $repository->register(101, 201, 999, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testDuplicateConstraintViolationReturnsAlreadyRegistered(): void
    {
        $exception = new PDOException('Synthetic duplicate entry.');
        $exception->errorInfo = ['23000', 1062, 'Synthetic duplicate entry.'];
        $repository = $this->repositoryThrowingOnRegistration($exception);

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AlreadyRegistered, $result);
    }

    public function testSqliteDuplicateConstraintViolationSupportsIntegrationFixtures(): void
    {
        $exception = new PDOException('Synthetic duplicate entry.');
        $exception->errorInfo = ['23000', 19, 'UNIQUE constraint failed: entries.event_id'];
        $repository = $this->repositoryThrowingOnRegistration($exception);

        self::assertSame(
            EntryRegistrationResult::AlreadyRegistered,
            $repository->register(101, 201, 301, '2026-06-28')
        );
    }

    public function testCapacityExceededReturnsCapacityExceededResult(): void
    {
        $capacityStatement = $this->createMock(PDOStatement::class);
        $capacityStatement->expects(self::once())
            ->method('execute')
            ->willReturn(true);
        $capacityStatement->expects(self::once())
            ->method('fetch')
            ->willReturn(['max_participants' => 10, 'current_count' => 10]);

        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->willReturn($capacityStatement);

        $repository = new EntryRegistrationRepository($database);

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::CapacityExceeded, $result);
    }

    public function testNoLimitAllowsRegistration(): void
    {
        $repository = $this->repositoryReturningRowCount(1, 301, false);

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Registered, $result);
    }

    public function testOpenEventAthleteIsUnsubscribed(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with([101, 201, 301])
            ->willReturn(true);
        $statement->expects(self::once())
            ->method('rowCount')
            ->willReturn(1);

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($statement): PDOStatement {
                if (str_contains($sql, 'closed FROM events')) {
                    $eligibilityStatement = $this->createMock(PDOStatement::class);
                    $eligibilityStatement->expects(self::once())
                        ->method('execute')
                        ->willReturn(true);
                    $eligibilityStatement->expects(self::once())
                        ->method('fetch')
                        ->willReturn(['closed' => 0]);
                    return $eligibilityStatement;
                }
                return $statement;
            });

        $repository = new EntryRegistrationRepository($database);

        $result = $repository->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Unsubscribed, $result);
    }

    public function testClosedEventReturnsUnsubscribeFailed(): void
    {
        $eligibilityStatement = $this->createMock(PDOStatement::class);
        $eligibilityStatement->expects(self::once())
            ->method('execute')
            ->willReturn(true);
        $eligibilityStatement->expects(self::once())
            ->method('fetch')
            ->willReturn(['closed' => 1]);

        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->willReturn($eligibilityStatement);

        $repository = new EntryRegistrationRepository($database);

        $result = $repository->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::UnsubscribeFailed, $result);
    }

    public function testNonexistentEntryReturnsUnsubscribeFailed(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with([101, 201, 301])
            ->willReturn(true);
        $statement->expects(self::once())
            ->method('rowCount')
            ->willReturn(0);

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($statement): PDOStatement {
                if (str_contains($sql, 'closed FROM events')) {
                    $eligibilityStatement = $this->createMock(PDOStatement::class);
                    $eligibilityStatement->expects(self::once())
                        ->method('execute')
                        ->willReturn(true);
                    $eligibilityStatement->expects(self::once())
                        ->method('fetch')
                        ->willReturn(['closed' => 0]);
                    return $eligibilityStatement;
                }
                return $statement;
            });

        $repository = new EntryRegistrationRepository($database);

        $result = $repository->unregister(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::UnsubscribeFailed, $result);
    }

    public function testBaselineSchemaRetainsTheEntryUniqueConstraint(): void
    {
        $schema = file_get_contents(dirname(__DIR__) . '/migrations/20260630_000000_create_schema.sql');

        self::assertIsString($schema);
        self::assertStringContainsString(
            'UNIQUE KEY unique_entry (event_id, club_id, athlete_id)',
            $schema
        );
    }

    private function repositoryReturningRowCount(int $rowCount, int $athleteId, bool $capacityExceeded): EntryRegistrationRepository
    {
        $registrationStatement = $this->createMock(PDOStatement::class);
        $registrationStatement->expects(self::once())
            ->method('execute')
            ->with($this->registrationParameters($athleteId))
            ->willReturn(true);
        $registrationStatement->expects(self::once())->method('rowCount')->willReturn($rowCount);

        $capacityStatement = $this->createMock(PDOStatement::class);
        $capacityStatement->expects(self::once())
            ->method('execute')
            ->willReturn(true);
        $capacityStatement->expects(self::once())
            ->method('fetch')
            ->willReturn($capacityExceeded ? ['max_participants' => 10, 'current_count' => 10] : ['max_participants' => 0, 'current_count' => 0]);

        return $this->repositoryWithStatements($capacityStatement, $registrationStatement);
    }

    private function repositoryThrowingOnRegistration(PDOException $exception): EntryRegistrationRepository
    {
        $registrationStatement = $this->createMock(PDOStatement::class);
        $registrationStatement->expects(self::once())
            ->method('execute')
            ->willThrowException($exception);

        $capacityStatement = $this->createMock(PDOStatement::class);
        $capacityStatement->expects(self::once())
            ->method('execute')
            ->willReturn(true);
        $capacityStatement->expects(self::once())
            ->method('fetch')
            ->willReturn(['max_participants' => 0, 'current_count' => 0]);

        return $this->repositoryWithStatements($capacityStatement, $registrationStatement);
    }

    /** @return array<string, int> */
    private function registrationParameters(int $athleteId): array
    {
        return [
            'event_id' => 101,
            'entry_club_id' => 201,
            'athlete_id' => $athleteId,
            'athlete_club_id' => 201,
            'event_date' => '2026-06-28',
            'deadline_date' => '2026-06-28',
        ];
    }

    private function repositoryWithStatements(
        PDOStatement&MockObject $capacityStatement,
        PDOStatement&MockObject $registrationStatement
    ): EntryRegistrationRepository {
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use ($capacityStatement, $registrationStatement): PDOStatement {
                if (str_contains($sql, 'max_participants')) {
                    return $capacityStatement;
                }
                return $registrationStatement;
            });

        return new EntryRegistrationRepository($database);
    }
}
