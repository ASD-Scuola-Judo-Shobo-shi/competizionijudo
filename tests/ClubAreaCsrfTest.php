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
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
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
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $request = $this->athleteRequest(['csrf_token' => null, 'athlete_id' => '']);

        $this->assertCsrfRejected($request);
    }

    public function testInvalidTokenRejectsAthleteEditBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $request = $this->athleteRequest([
            'csrf_token' => 'synthetic-invalid-csrf',
            'athlete_id' => '301',
        ]);

        $this->assertCsrfRejected($request);
    }

    public function testValidTokenAllowsAthleteAdd(): void
    {
        $clubStatement = $this->statementFetching($this->clubRow());
        $termsStatement = $this->currentTermsStatement();
        $declarationStatement = $this->currentDeclarationStatement();
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
        $database->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnCallback(static function (string $sql) use (
                $clubStatement,
                $termsStatement,
                $declarationStatement,
                $insertStatement
            ): PDOStatement {
                if (str_starts_with($sql, 'SELECT * FROM clubs')) {
                    return $clubStatement;
                }
                if (str_starts_with($sql, 'SELECT 1 FROM club_terms_acceptances')) {
                    return $termsStatement;
                }
                if (str_starts_with($sql, 'SELECT 1 FROM club_data_rights_declarations')) {
                    return $declarationStatement;
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
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
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
        $termsStatement = $this->currentTermsStatement();
        $declarationStatement = $this->currentDeclarationStatement();
        $athleteStatement = $this->statementFetching($this->athleteRow());
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(static fn(array $values): bool =>
                $values[0] === 'Synthetic'
                && $values[1] === 'Athlete'
                && $values[8] === 301
                && $values[9] === 201))
            ->willReturn(true);

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(5))
            ->method('prepare')
            ->willReturnCallback(
                static function (string $sql) use (
                    $clubStatement,
                    $termsStatement,
                    $declarationStatement,
                    $athleteStatement,
                    $updateStatement
                ): PDOStatement {
                    if (str_starts_with($sql, 'SELECT * FROM clubs')) {
                        return $clubStatement;
                    }
                    if (str_starts_with($sql, 'SELECT 1 FROM club_terms_acceptances')) {
                        return $termsStatement;
                    }
                    if (str_starts_with($sql, 'SELECT 1 FROM club_data_rights_declarations')) {
                        return $declarationStatement;
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
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $request = $this->athleteRequest([
            'csrf_token' => csrf_token(),
            'athlete_id' => '301',
        ]);

        $response = (new ClubAreaController($this->view, $request))->index($request);

        self::assertSame(302, $response->status());
    }

    public function testInlineAthleteUpdateRejectsMissingTokenBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $request = $this->inlineAthleteRequest(['csrf_token' => null]);

        try {
            (new ClubAreaController($this->view, $request))->updateAthleteInline($request);
            self::fail('Expected CSRF validation to reject the inline athlete mutation.');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode());
        }
    }

    public function testValidTokenAllowsOwnedInlineAthleteUpdate(): void
    {
        $termsStatement = $this->currentTermsStatement();
        $declarationStatement = $this->currentDeclarationStatement();
        $athleteStatement = $this->statementFetching($this->athleteRow());
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->expects(self::once())
            ->method('execute')
            ->with(self::callback(static fn(array $values): bool =>
                $values[0] === 'Updated'
                && $values[1] === 'Athlete'
                && $values[4] === 52.5
                && $values[8] === 301
                && $values[9] === 201))
            ->willReturn(true);

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(4))
            ->method('prepare')
            ->willReturnCallback(
                static function (string $sql) use (
                    $termsStatement,
                    $declarationStatement,
                    $athleteStatement,
                    $updateStatement
                ): PDOStatement {
                    return match (true) {
                        str_starts_with($sql, 'SELECT 1 FROM club_terms_acceptances') =>
                            $termsStatement,
                        str_starts_with($sql, 'SELECT 1 FROM club_data_rights_declarations') =>
                            $declarationStatement,
                        str_starts_with($sql, 'SELECT * FROM athletes') => $athleteStatement,
                        str_starts_with($sql, 'UPDATE athletes') => $updateStatement,
                        default => throw new RuntimeException('Unexpected synthetic fixture query.'),
                    };
                }
            );
        $database->expects(self::never())->method('query');
        $this->setDatabase($database);
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        $request = $this->inlineAthleteRequest(['csrf_token' => csrf_token()]);

        $response = (new ClubAreaController($this->view, $request))->updateAthleteInline($request);

        self::assertSame(303, $response->status());
        self::assertSame(
            '/clubs/area?view=list&page=2&event=101#athlete-row-301',
            $response->headers()['Location']
        );
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
        self::assertStringContainsString(
            e(__('errors.invalid_csrf_description')),
            $response->content()
        );
    }

    /** @param array<string, mixed> $overrides */
    private function athleteRequest(array $overrides): Request
    {
        return new Request('POST', '/clubs/area?view=add', ['view' => 'add'], array_merge([
            'last_name' => 'Synthetic',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'birth_date' => '2010-01-01',
            'weight_kg' => '50',
            'belt' => 'white',
            'membership_number' => 'SYNTHETIC-001',
            'notes' => '',
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function inlineAthleteRequest(array $overrides): Request
    {
        return new Request('POST', '/clubs/athletes/update-inline', [], array_merge([
            'athlete_id' => '301',
            'last_name' => 'Updated',
            'first_name' => 'Athlete',
            'gender' => 'M',
            'birth_date' => '2010-01-01',
            'weight_kg' => '52.5',
            'belt' => 'white',
            'membership_number' => 'SYNTHETIC-001',
            'notes' => 'Updated inline',
            'return_view' => 'list',
            'page' => '2',
            'event' => '101',
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

    private function currentDeclarationStatement(): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with([201, ClubDataRightsDeclaration::VERSION])
            ->willReturn(true);
        $statement->expects(self::once())->method('fetchColumn')->willReturn(1);

        return $statement;
    }

    private function currentTermsStatement(): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())
            ->method('execute')
            ->with([201, ClubTermsAcceptance::VERSION])
            ->willReturn(true);
        $statement->expects(self::once())->method('fetchColumn')->willReturn(1);

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
            'affiliation' => 'SYNTHETIC',
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
            'birth_date' => '2010-01-01',
            'weight_kg' => 50.0,
            'belt' => 'white',
            'type' => 'SYNTHETIC',
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
