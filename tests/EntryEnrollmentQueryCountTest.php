<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Database;
use App\Model\Entry;
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

    /** @return PDO&MockObject */
    private function databaseReturning(PDOStatement $statement): PDO
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with(self::callback(static function (string $sql): bool {
                self::assertStringContainsString(
                    'COALESCE(en.snapshot_last_name, a.last_name)',
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
