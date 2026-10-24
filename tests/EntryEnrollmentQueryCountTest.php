<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Database;
use App\Model\Entry;
use App\Presentation\EntryDataComparison;
use App\Service\EntryDataReviewService;
use App\Service\EventEntriesCsvTransfer;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EntryEnrollmentQueryCountTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
    }

    /** @return iterable<string, array{int}> */
    public static function enrollmentCounts(): iterable
    {
        yield 'one enrollment' => [1];
        yield 'seventy-five enrollments' => [75];
    }

    #[DataProvider('enrollmentCounts')]
    public function testEnrollmentListUsesOneQueryAsTheNumberOfAthletesGrows(int $enrollmentCount): void
    {
        $rows = [];
        for ($index = 1; $index <= $enrollmentCount; $index++) {
            $rows[] = [
                'club_id' => 201,
                'club_name' => 'Synthetic Club',
                'federal_code' => 'SYN-201',
                'last_name' => 'Athlete' . $index,
                'first_name' => 'Synthetic' . $index,
                'gender' => 'M',
                'birth_date' => '2012-01-01',
                'weight_kg' => 42.5,
                'belt' => 'green',
                'membership_number' => 'MEM-' . $index,
                'type' => 'competitive',
                'weight_category' => '-46 kg',
                'event_name' => 'Synthetic Event',
                'event_date' => '2026-07-01',
            ];
        }

        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([101])->willReturn(true);
        $statement->expects(self::once())->method('fetchAll')->willReturn($rows);
        $database = $this->databaseReturning($statement);
        $this->databaseConnection->setValue(null, $database);

        $enrolledAthletes = Entry::findByEvent(101, null, true);

        self::assertCount($enrollmentCount, $enrolledAthletes);
        self::assertSame('Athlete1', $enrolledAthletes[0]['last_name']);
        self::assertSame('Synthetic Club', $enrolledAthletes[0]['club_name']);
        self::assertSame('SYN-201', $enrolledAthletes[0]['federal_code']);
    }

    #[DataProvider('enrollmentCounts')]
    public function testSnapshotCsvExportUsesOneQueryAsTheNumberOfAthletesGrows(int $enrollmentCount): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([101])->willReturn(true);
        $statement->expects(self::once())->method('fetchAll')->willReturn(array_fill(0, $enrollmentCount, [
            'club_name' => 'Synthetic Club',
            'federal_code' => 'SYN-201',
            'last_name' => 'Snapshot',
        ]));
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('prepare')->willReturn($statement);
        $database->expects(self::never())->method('query');
        $this->databaseConnection->setValue(null, $database);

        $csv = (new EventEntriesCsvTransfer())->export(101, true);

        self::assertSame($enrollmentCount, substr_count($csv, 'Snapshot'));
    }

    #[DataProvider('enrollmentCounts')]
    public function testEndedOpenEventCsvUsesOneQueryAsTheNumberOfAthletesGrows(int $enrollmentCount): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with(['2000-01-01 23:00:00', 101])->willReturn(true);
        $statement->expects(self::once())->method('fetchAll')->willReturn(array_fill(0, $enrollmentCount, [
            'last_name' => 'EligibleRevision', 'uses_snapshot' => 0, 'birth_date' => null,
        ]));
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('prepare')
            ->with(self::callback(static fn(string $sql): bool => str_contains($sql, 'LEFT JOIN athlete_revisions r')))
            ->willReturn($statement);
        $database->expects(self::never())->method('query');
        $this->databaseConnection->setValue(null, $database);

        $csv = (new EventEntriesCsvTransfer())->export(101, false, eventDate: '2000-01-01');

        self::assertSame($enrollmentCount, substr_count($csv, 'EligibleRevision'));
    }

    #[DataProvider('enrollmentCounts')]
    public function testDifferenceReportUsesFourDataQueriesAsTheNumberOfAthletesGrows(int $enrollmentCount): void
    {
        $rows = [];
        for ($index = 1; $index <= $enrollmentCount; $index++) {
            $rows[] = [
                'id' => $index, 'entry_id' => $index, 'club_id' => 201, 'club_name' => 'Synthetic Club',
                'last_name' => 'Athlete' . $index, 'first_name' => 'Synthetic', 'gender' => 'M',
                'birth_date' => '2012-01-01', 'weight_kg' => 42.5, 'belt' => 'green',
                'uses_snapshot' => 1, 'snapshot_at' => '2026-01-01 00:00:00',
                'type' => 'competitive', 'weight_category' => '-60 kg',
            ];
        }
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::exactly(4))->method('execute')->with([101])->willReturn(true);
        $statement->expects(self::once())->method('fetch')->willReturn([
            'id' => 101, 'date' => '2099-06-01', 'closed' => 1,
        ]);
        $statement->expects(self::exactly(3))->method('fetchAll')->willReturn($rows);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(4))->method('prepare')->willReturn($statement);
        $database->expects(self::never())->method('query');
        $this->databaseConnection->setValue(null, $database);

        $preview = (new EntryDataReviewService($database))->alignmentPreview(101);
        $report = EntryDataComparison::fromRows(
            Entry::findByEvent(101, null, true, '2099-06-01'),
            (new EventEntriesCsvTransfer())->rows(101, true, eventDate: '2099-06-01'),
            $preview['entries'],
            '2099-06-01',
            true
        );

        self::assertSame($enrollmentCount, $report->total);
        self::assertSame(0, $report->exportDifferenceCount);
    }

    /** @return PDO&MockObject */
    private function databaseReturning(PDOStatement $statement): PDO
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(static function (string $sql): bool {
                self::assertStringContainsString(
                    'en.snapshot_last_name AS last_name',
                    $sql
                );
                self::assertStringContainsString('JOIN clubs c', $sql);

                return true;
            }))
            ->willReturn($statement);
        $database->expects(self::never())->method('query');

        return $database;
    }
}
