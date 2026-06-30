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
        $repository = $this->repositoryReturningRowCount(1, 301);

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::Registered, $result);
    }

    public function testForeignAthleteIsRejectedWithoutAnInsert(): void
    {
        $repository = $this->repositoryReturningRowCount(0, 302);

        $result = $repository->register(101, 201, 302, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testMissingAthleteIsRejectedWithoutAnInsert(): void
    {
        $repository = $this->repositoryReturningRowCount(0, 999);

        $result = $repository->register(101, 201, 999, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
    }

    public function testDuplicateConstraintViolationReturnsAlreadyRegistered(): void
    {
        $exception = new PDOException('Synthetic duplicate entry.');
        $exception->errorInfo = ['23000', 1062, 'Synthetic duplicate entry.'];
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with($this->registrationParameters(301))
            ->willThrowException($exception);
        $statement->expects(self::never())->method('rowCount');
        $repository = new EntryRegistrationRepository($this->databasePreparing($statement));

        $result = $repository->register(101, 201, 301, '2026-06-28');

        self::assertSame(EntryRegistrationResult::AlreadyRegistered, $result);
    }

    public function testSqliteDuplicateConstraintViolationSupportsIntegrationFixtures(): void
    {
        $exception = new PDOException('Synthetic duplicate entry.');
        $exception->errorInfo = ['23000', 19, 'UNIQUE constraint failed: entries.event_id'];
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('execute')->willThrowException($exception);
        $repository = new EntryRegistrationRepository($this->databasePreparing($statement));

        self::assertSame(
            EntryRegistrationResult::AlreadyRegistered,
            $repository->register(101, 201, 301, '2026-06-28')
        );
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

    private function repositoryReturningRowCount(int $rowCount, int $athleteId): EntryRegistrationRepository
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with($this->registrationParameters($athleteId))
            ->willReturn(true);
        $statement->expects(self::once())->method('rowCount')->willReturn($rowCount);

        return new EntryRegistrationRepository($this->databasePreparing($statement));
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

    private function databasePreparing(PDOStatement&MockObject $statement): PDO&MockObject
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(function (string $sql): bool {
                $normalized = preg_replace('/\s+/', ' ', trim($sql));
                self::assertIsString($normalized);
                self::assertStringStartsWith(
                    'INSERT INTO entries (event_id, club_id, athlete_id) SELECT',
                    $normalized
                );
                self::assertStringContainsString('FROM athletes AS athlete', $normalized);
                self::assertStringContainsString(
                    'JOIN events AS event_record ON event_record.id = :event_id',
                    $normalized
                );
                self::assertStringContainsString('athlete.id = :athlete_id', $normalized);
                self::assertStringContainsString('athlete.club_id = :athlete_club_id', $normalized);
                self::assertStringContainsString('event_record.published = 1', $normalized);
                self::assertStringContainsString('event_record.closed = 0', $normalized);
                self::assertStringContainsString('event_record.date >= :event_date', $normalized);
                self::assertStringContainsString(
                    'event_record.registration_deadline >= :deadline_date',
                    $normalized
                );

                return true;
            }))
            ->willReturn($statement);

        return $database;
    }
}
