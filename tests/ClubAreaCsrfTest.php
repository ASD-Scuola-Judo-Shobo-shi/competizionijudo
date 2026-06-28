<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubAreaController;
use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class ClubAreaCsrfTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private View $view;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
    }

    public function testMissingTokenRejectsAthleteAddBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        Session::set('club_id', 201);
        $request = $this->athleteRequest(['csrf_token' => null, 'athlete_id' => '']);

        $this->assertCsrfRejected($request);
    }

    public function testInvalidTokenRejectsAthleteEditBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        Session::set('club_id', 201);
        $request = $this->athleteRequest([
            'csrf_token' => 'synthetic-invalid-csrf',
            'athlete_id' => '301',
        ]);

        $this->assertCsrfRejected($request);
    }

    public function testValidTokenAllowsAthleteAdd(): void
    {
        $clubStatement = $this->statementFetching($this->clubRow());
        $insertStatement = $this->createMock(PDOStatement::class);
        $insertStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(static fn(array $values): bool =>
                $values[0] === 201
                && $values[1] === 'Synthetic'
                && $values[2] === 'Athlete'))
            ->willReturn(true);
        $athleteStatement = $this->queryStatementFetching($this->athleteRow());

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(static function (string $sql) use ($clubStatement, $insertStatement): PDOStatement {
                if (str_starts_with($sql, 'SELECT * FROM clubs')) {
                    return $clubStatement;
                }
                if (str_starts_with($sql, 'INSERT INTO athletes')) {
                    return $insertStatement;
                }

                throw new RuntimeException('Unexpected synthetic fixture query.');
            });
        $database->expects(self::once())
            ->method('query')
            ->with('SELECT * FROM athletes WHERE id = LAST_INSERT_ID()')
            ->willReturn($athleteStatement);
        $this->setDatabase($database);
        Session::set('club_id', 201);
        $request = $this->athleteRequest([
            'csrf_token' => csrf_token(),
            'athlete_id' => '',
        ]);

        $response = (new ClubAreaController($this->view, $request))->index($request);

        self::assertSame(302, $response->status());
    }

    public function testValidTokenAllowsOwnedAthleteEdit(): void
    {
        $clubStatement = $this->statementFetching($this->clubRow());
        $athleteStatement = $this->statementFetching($this->athleteRow());
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(static fn(array $values): bool =>
                $values[0] === 'Synthetic'
                && $values[1] === 'Athlete'
                && $values[10] === 301
                && $values[11] === 201))
            ->willReturn(true);

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnCallback(
                static function (string $sql) use ($clubStatement, $athleteStatement, $updateStatement): PDOStatement {
                    if (str_starts_with($sql, 'SELECT * FROM clubs')) {
                        return $clubStatement;
                    }
                    if (str_starts_with($sql, 'SELECT * FROM athletes')) {
                        return $athleteStatement;
                    }
                    if (str_starts_with($sql, 'UPDATE athletes')) {
                        return $updateStatement;
                    }

                    throw new RuntimeException('Unexpected synthetic fixture query.');
                }
            );
        $database->expects(self::never())->method('query');
        $this->setDatabase($database);
        Session::set('club_id', 201);
        $request = $this->athleteRequest([
            'csrf_token' => csrf_token(),
            'athlete_id' => '301',
        ]);

        $response = (new ClubAreaController($this->view, $request))->index($request);

        self::assertSame(302, $response->status());
    }

    public function testApplicationConvertsCsrfExceptionToControlled419Response(): void
    {
        $application = new Application(dirname(__DIR__));
        $application->router()->post(
            '/csrf-test',
            static fn(): Response => throw new HttpException(419, __('errors.invalid_csrf'))
        );

        $response = $application->handle(new Request('POST', '/csrf-test'));

        self::assertSame(419, $response->status());
        self::assertStringContainsString(e(__('errors.invalid_csrf')), $response->content());
    }

    /** @param array<string, mixed> $overrides */
    private function athleteRequest(array $overrides): Request
    {
        return new Request('POST', '/club_area.php?view=add', ['view' => 'add'], array_merge([
            'last_name' => 'Synthetic',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'date_of_birth' => '2010-01-01',
            'weight_kg' => '50',
            'belt' => 'white',
            'membership_number' => 'SYNTHETIC-001',
            'notes' => '',
        ], $overrides));
    }

    private function assertCsrfRejected(Request $request): void
    {
        try {
            (new ClubAreaController($this->view, $request))->index($request);
            self::fail('Expected CSRF validation to reject the athlete mutation.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode());
            self::assertSame(__('errors.invalid_csrf'), $exception->getMessage());
        }
    }

    private function databaseExpectingNoAccess(): PDO&MockObject
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::never())->method('prepare');
        $database->expects(self::never())->method('query');

        return $database;
    }

    /** @param array<string, mixed> $row */
    private function statementFetching(array $row): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->willReturn(true);
        $statement->expects(self::once())->method('fetch')->willReturn($row);

        return $statement;
    }

    /** @param array<string, mixed> $row */
    private function queryStatementFetching(array $row): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::never())->method('execute');
        $statement->expects(self::once())->method('fetch')->willReturn($row);

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
    private function athleteRow(): array
    {
        return [
            'id' => 301,
            'club_id' => 201,
            'last_name' => 'Synthetic',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'date_of_birth' => '2010-01-01',
            'weight_kg' => 50.0,
            'belt' => 'white',
            'program' => 'SYNTHETIC',
            'weight_category' => 'SYNTHETIC',
            'membership_number' => 'SYNTHETIC-001',
            'notes' => '',
        ];
    }

    private function setDatabase(PDO $database): void
    {
        $this->databaseConnection->setValue(null, $database);
    }

    private function startCleanSession(): void
    {
        $this->destroySession();
        Session::start();
    }

    private function destroySession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            Session::destroy();
        }

        $_SESSION = [];
        session_id('');
    }
}
