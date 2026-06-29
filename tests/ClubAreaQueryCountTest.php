<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubAreaController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Model\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class ClubAreaQueryCountTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        Session::destroy();
        Session::start();
        Session::set('club_id', 201);
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        Session::destroy();
    }

    /** @return iterable<string, array{int}> */
    public static function athleteCounts(): iterable
    {
        yield 'one athlete' => [1];
        yield 'seventy-five athletes' => [75];
    }

    #[DataProvider('athleteCounts')]
    public function testClubAreaQueryCountIsConstantAsAthletesGrow(int $athleteCount): void
    {
        $athletes = [];
        $entries = [];
        for ($index = 1; $index <= $athleteCount; $index++) {
            $athleteId = 300 + $index;
            $athletes[] = $this->athleteRow($athleteId);
            $entries[] = [
                'event_id' => 101,
                'athlete_id' => $athleteId,
                'nome_evento' => 'Synthetic Event',
                'data_gara' => '2026-06-29',
            ];
            if ($index === 1) {
                $entries[] = [
                    'event_id' => 102,
                    'athlete_id' => $athleteId,
                    'nome_evento' => 'Other Event',
                    'data_gara' => '2026-07-01',
                ];
            }
        }

        $clubStatement = $this->statementReturning($this->clubRow(), 1);
        $entryStatement = $this->statementReturningAll($entries);
        $athleteStatement = $this->statementReturningAll($athletes);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnCallback(
                static function (string $sql) use (
                    $clubStatement,
                    $entryStatement,
                    $athleteStatement
                ): PDOStatement {
                    if (str_starts_with($sql, 'SELECT * FROM clubs WHERE id')) {
                        return $clubStatement;
                    }
                    if (str_starts_with($sql, 'SELECT en.*, e.name')) {
                        return $entryStatement;
                    }
                    if (str_starts_with($sql, 'SELECT * FROM athletes WHERE club_id')) {
                        return $athleteStatement;
                    }

                    throw new RuntimeException('Unexpected query in club-area count fixture.');
                }
            );
        $database->expects(self::never())->method('query');
        $this->databaseConnection->setValue(null, $database);
        $request = new Request('GET', '/club_area.php?view=list&event=101', [
            'view' => 'list',
            'event' => '101',
        ]);

        $response = (new ClubAreaController(
            new View(dirname(__DIR__) . '/views'),
            $request
        ))->index($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Synthetic1 Athlete1', $response->content());
        self::assertMatchesRegularExpression(
            '/Synthetic1 Athlete1.*?<td>1<\/td>\s*<td>.*?edit=301/s',
            $response->content()
        );
    }

    /** @return PDOStatement&MockObject */
    private function statementReturning(array $row, int $calls): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::exactly($calls))->method('execute')->willReturn(true);
        $statement->expects(self::exactly($calls))->method('fetch')->willReturn($row);

        return $statement;
    }

    /** @param list<array<string, mixed>> $rows */
    private function statementReturningAll(array $rows): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->willReturn(true);
        $statement->expects(self::once())->method('fetchAll')->willReturn($rows);

        return $statement;
    }

    /** @return array<string, mixed> */
    private function clubRow(): array
    {
        return [
            'id' => 201,
            'name' => 'Synthetic Club',
            'email' => 'club@example.test',
            'phone' => '',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'contact_phone' => '',
            'contact_email' => 'contact@example.test',
            'organization' => 'SYNTHETIC',
            'recovery_email' => 'recovery@example.test',
            'password_hash' => 'synthetic-hash',
            'federal_code' => 'SYNTHETIC-CODE',
        ];
    }

    /** @return array<string, mixed> */
    private function athleteRow(int $id): array
    {
        return [
            'id' => $id,
            'club_id' => 201,
            'last_name' => 'Synthetic' . ($id - 300),
            'first_name' => 'Athlete' . ($id - 300),
            'gender' => 'M',
            'date_of_birth' => '2010-01-01',
            'weight_kg' => 50.0,
            'belt' => 'white',
            'program' => 'SYNTHETIC',
            'weight_category' => 'SYNTHETIC',
            'membership_number' => 'SYNTHETIC-' . $id,
            'notes' => '',
        ];
    }
}
