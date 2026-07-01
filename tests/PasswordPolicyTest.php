<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\ClubController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Security\PasswordPolicy;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\FakeAuthenticationThrottle;
use Tests\Support\FakePasswordResetRepository;
use Tests\Support\FakePasswordResetTokenIssuer;

final class PasswordPolicyTest extends TestCase
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

    public function testPolicyAcceptsOnlyPasswordsAtOrAboveTheDocumentedMinimum(): void
    {
        self::assertFalse(PasswordPolicy::accepts(str_repeat('x', PasswordPolicy::MINIMUM_LENGTH - 1)));
        self::assertTrue(PasswordPolicy::accepts(str_repeat('x', PasswordPolicy::MINIMUM_LENGTH)));
        self::assertTrue(PasswordPolicy::accepts(str_repeat('à', PasswordPolicy::MINIMUM_LENGTH)));
    }

    public function testRegistrationRejectsShortPasswordBeforeDatabaseAccess(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        $shortPassword = str_repeat('x', PasswordPolicy::MINIMUM_LENGTH - 1);
        $request = new Request('POST', '/club_register.php', [], [
            'csrf_token' => csrf_token(),
            'name' => 'Synthetic Club',
            'federal_code' => 'SYN-11',
            'email' => 'registration@example.test',
            'phone' => '',
            'contact' => '',
            'password' => $shortPassword,
            'password2' => $shortPassword,
            'athlete_data_rights_declaration' => '1',
        ]);

        $response = $this->clubController($request, new FakePasswordResetRepository())->register($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e($this->policyError()), $response->content());
    }

    public function testResetRejectsShortPasswordWithoutConsumingToken(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        $repository = new FakePasswordResetRepository('reset@example.test', true);
        $shortPassword = str_repeat('x', PasswordPolicy::MINIMUM_LENGTH - 1);
        $request = new Request('POST', '/club_reset_password.php', [], [
            'csrf_token' => csrf_token(),
            'token' => bin2hex(hash('sha256', 'reset-controller-fixture', true)),
            'password' => $shortPassword,
            'password2' => $shortPassword,
        ]);

        $response = $this->clubController($request, $repository)->resetPassword($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e($this->policyError()), $response->content());
        self::assertSame([], $repository->consumed);
    }

    public function testValidResetDelegatesAtomicConsumptionAndRedirects(): void
    {
        $this->setDatabase($this->databaseExpectingNoAccess());
        $repository = new FakePasswordResetRepository('reset@example.test', true);
        $acceptedPassword = str_repeat('x', PasswordPolicy::MINIMUM_LENGTH);
        $request = new Request('POST', '/club_reset_password.php', [], [
            'csrf_token' => csrf_token(),
            'token' => bin2hex(hash('sha256', 'accepted-reset-fixture', true)),
            'password' => $acceptedPassword,
            'password2' => $acceptedPassword,
        ]);

        $response = $this->clubController($request, $repository)->resetPassword($request);

        self::assertSame(302, $response->status());
        self::assertCount(1, $repository->consumed);
        self::assertTrue(password_verify($acceptedPassword, $repository->consumed[0]['password_hash']));
    }

    public function testAdminEditRejectsShortPasswordBeforeClubUpdate(): void
    {
        $club = $this->statementFetching($this->clubRow());
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('prepare')->willReturn($club);
        $this->setDatabase($database);
        Session::set('is_admin', true);
        $repository = new FakePasswordResetRepository();
        $request = $this->adminRequest(str_repeat('x', PasswordPolicy::MINIMUM_LENGTH - 1));

        $response = (new AdminController($this->view, $request, null, $repository))->editClub($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e($this->policyError()), $response->content());
        self::assertSame([], $repository->replaced);
    }

    public function testAdminPasswordChangeDelegatesAtomicReplacement(): void
    {
        $club = $this->statementFetching($this->clubRow());
        $update = $this->createMock(PDOStatement::class);
        $update->expects(self::once())->method('execute')->willReturn(true);
        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(2))
            ->method('prepare')
            ->willReturnCallback(static function (string $sql) use ($club, $update): PDOStatement {
                return match (true) {
                    str_starts_with($sql, 'SELECT * FROM clubs') => $club,
                    str_starts_with($sql, 'UPDATE clubs SET name') => $update,
                    default => throw new RuntimeException('Unexpected club edit query.'),
                };
            });
        $this->setDatabase($database);
        Session::set('is_admin', true);
        $repository = new FakePasswordResetRepository();
        $acceptedPassword = str_repeat('x', PasswordPolicy::MINIMUM_LENGTH);
        $request = $this->adminRequest($acceptedPassword);

        $response = (new AdminController($this->view, $request, null, $repository))->editClub($request);

        self::assertSame(302, $response->status());
        self::assertCount(1, $repository->replaced);
        self::assertSame(17, $repository->replaced[0]['club_id']);
        self::assertTrue(password_verify($acceptedPassword, $repository->replaced[0]['password_hash']));
    }

    private function clubController(Request $request, FakePasswordResetRepository $repository): ClubController
    {
        return new ClubController(
            $this->view,
            $request,
            new FakePasswordResetTokenIssuer(null),
            new FakeAuthenticationThrottle(),
            $repository
        );
    }

    private function adminRequest(string $password): Request
    {
        return new Request('POST', '/admin_edit_club.php', [], [
            'csrf_token' => csrf_token(),
            'id' => '17',
            'name' => 'Synthetic Club',
            'email' => 'admin-edit@example.test',
            'phone' => '',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'contact_phone' => '',
            'contact_email' => '',
            'organization' => 'TEST',
            'recovery_email' => 'recovery@example.test',
            'federal_code' => 'SYN-17',
            'password_hash' => $password,
        ]);
    }

    /** @return array<string, mixed> */
    private function clubRow(): array
    {
        return [
            'id' => 17,
            'name' => 'Synthetic Club',
            'email' => 'admin-edit@example.test',
            'phone' => '',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'contact_phone' => '',
            'contact_email' => '',
            'organization' => 'TEST',
            'recovery_email' => 'recovery@example.test',
            'password_hash' => hash('sha256', 'existing-password-fixture'),
            'federal_code' => 'SYN-17',
        ];
    }

    /** @param array<string, mixed> $row */
    private function statementFetching(array $row): PDOStatement
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([17])->willReturn(true);
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

    private function policyError(): string
    {
        return __('errors.password_too_short', [
            'minimum' => (string) PasswordPolicy::MINIMUM_LENGTH,
        ]);
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
