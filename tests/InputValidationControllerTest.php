<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubAreaController;
use App\Controller\ClubController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Security\PasswordPolicy;
use App\Validation\EventInputValidator;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\FakeAuthenticationThrottle;
use Tests\Support\FakePasswordResetRepository;
use Tests\Support\FakePasswordResetTokenIssuer;

final class InputValidationControllerTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    /** @var array<string, mixed> */
    private array $originalFiles;
    private View $view;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;
        $this->originalFiles = $_FILES;

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $_FILES = $this->originalFiles;
        $this->destroySession();
    }

    public function testInvalidClubRegistrationIsRejectedBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        $request = new Request('POST', '/club_register.php', [], [
            'csrf_token' => csrf_token(),
            'name' => 'Synthetic Club',
            'federal_code' => '',
            'email' => 'not-an-email',
            'password' => str_repeat('x', PasswordPolicy::MINIMUM_LENGTH),
            'password2' => str_repeat('x', PasswordPolicy::MINIMUM_LENGTH),
        ]);

        $response = $this->clubController($request)->register($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('validation.federal_code_required')), $response->content());
        self::assertStringContainsString(e(__('validation.club_email_invalid')), $response->content());
    }

    public function testInvalidAthletePostPerformsOnlyRequiredReadQueries(): void
    {
        $club = $this->statementFetching($this->clubRow(31), [31]);
        $count = $this->createMock(PDOStatement::class);
        $count->expects(self::once())->method('execute')->with([31])->willReturn(true);
        $count->method('fetchColumn')->willReturn(0);
        $list = $this->createMock(PDOStatement::class);
        $list->expects(self::once())->method('execute')->with([31])->willReturn(true);
        $list->method('fetchAll')->willReturn([]);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(3))
            ->method('prepare')
            ->willReturnCallback(
                static function (string $sql) use ($club, $count, $list): PDOStatement {
                    if (str_starts_with($sql, 'SELECT * FROM clubs')) {
                        return $club;
                    }

                    return match (true) {
                        str_starts_with($sql, 'SELECT COUNT(*) FROM athletes') => $count,
                        str_starts_with($sql, 'SELECT * FROM athletes WHERE club_id') => $list,
                        default => throw new RuntimeException('Mutation query reached for invalid athlete input.'),
                    };
                }
            );
        $this->setDatabase($database);
        Session::set('club_id', 31);
        $request = new Request('POST', '/club_area.php?view=add', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
            'athlete_id' => '',
            'last_name' => '',
            'first_name' => '',
            'gender' => 'forged',
            'date_of_birth' => '2026-02-30',
            'weight_kg' => '-1',
            'belt' => 'forged',
        ]);

        $response = (new ClubAreaController($this->view, $request))->index($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('validation.athlete_gender_invalid')), $response->content());
        self::assertStringContainsString(e(__('validation.athlete_weight_invalid')), $response->content());
    }

    public function testOversizedEventUploadIsRejectedBeforeSqlOrFileWrites(): void
    {
        $locations = $this->createMock(PDOStatement::class);
        $locations->method('fetchAll')->willReturn([]);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('query')->willReturn($locations);
        $database->expects(self::never())->method('prepare');
        $this->setDatabase($database);
        Session::set('is_admin', true);
        $_FILES['poster_file'] = [
            'error' => UPLOAD_ERR_OK,
            'size' => EventInputValidator::MAX_UPLOAD_BYTES + 1,
            'tmp_name' => '/not-read-for-oversized-controller-upload',
        ];
        $request = new Request('POST', '/admin_add_event.php', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '',
            'name' => 'Synthetic Event',
            'date' => '2026-07-01',
            'location' => 'Synthetic Venue',
            'registration_deadline' => '2026-06-30',
            'type' => 'only_competitive',
        ]);

        $response = (new AdminController($this->view, $request))->addEvent($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('validation.event_upload_too_large')), $response->content());
    }

    public function testInvalidAdminClubEmailIsRejectedBeforeUpdate(): void
    {
        $club = $this->statementFetching($this->clubRow(42), [42]);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('prepare')->willReturn($club);
        $this->setDatabase($database);
        Session::set('is_admin', true);
        $request = $this->adminClubRequest(42, 'invalid-email');

        $response = (new AdminController(
            $this->view,
            $request,
            null,
            new FakePasswordResetRepository()
        ))->editClub($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('validation.club_email_invalid')), $response->content());
    }

    public function testDatabaseConstraintDetailsAreReplacedWithSafeAccountError(): void
    {
        $lookup = $this->createMock(PDOStatement::class);
        $lookup->expects(self::once())->method('execute')->willReturn(true);
        $lookup->method('fetch')->willReturn(false);
        $insert = $this->createMock(PDOStatement::class);
        $exception = new PDOException('Synthetic internal constraint detail', 23000);
        $insert->expects(self::once())->method('execute')->willThrowException($exception);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($lookup, $insert);
        $this->setDatabase($database);
        $request = new Request('POST', '/club_register.php', [], [
            'csrf_token' => csrf_token(),
            'name' => 'Synthetic Club',
            'federal_code' => 'SYN-42',
            'email' => 'duplicate@example.test',
            'password' => str_repeat('x', PasswordPolicy::MINIMUM_LENGTH),
            'password2' => str_repeat('x', PasswordPolicy::MINIMUM_LENGTH),
        ]);

        $response = $this->clubController($request)->register($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('errors.account_conflict')), $response->content());
        self::assertStringNotContainsString('Synthetic internal constraint detail', $response->content());
    }

    private function clubController(Request $request): ClubController
    {
        return new ClubController(
            $this->view,
            $request,
            new FakePasswordResetTokenIssuer(null),
            new FakeAuthenticationThrottle(),
            new FakePasswordResetRepository(),
            $this->createStub(Logger::class)
        );
    }

    private function adminClubRequest(int $id, string $email): Request
    {
        return new Request('POST', '/admin_edit_club.php', [], [
            'csrf_token' => csrf_token(),
            'id' => (string) $id,
            'name' => 'Synthetic Club',
            'email' => $email,
            'phone' => '',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'contact_phone' => '',
            'contact_email' => 'contact@example.test',
            'organization' => 'TEST',
            'recovery_email' => 'recovery@example.test',
            'federal_code' => 'SYN-' . $id,
            'password_hash' => '',
        ]);
    }

    /** @return array<string, mixed> */
    private function clubRow(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'Synthetic Club',
            'email' => 'club@example.test',
            'phone' => '',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'contact_phone' => '',
            'contact_email' => 'contact@example.test',
            'organization' => 'TEST',
            'recovery_email' => 'recovery@example.test',
            'password_hash' => hash('sha256', 'credential-fixture'),
            'federal_code' => 'SYN-' . $id,
        ];
    }

    /** @param array<string, mixed> $row
     *  @param list<mixed> $parameters
     */
    private function statementFetching(array $row, array $parameters): PDOStatement&MockObject
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with($parameters)->willReturn(true);
        $statement->method('fetch')->willReturn($row);

        return $statement;
    }

    private function databaseExpectingNoAccess(): PDO
    {
        $database = $this->createMock(PDO::class);
        $database->expects(self::never())->method('prepare');
        $database->expects(self::never())->method('query');

        return $database;
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
