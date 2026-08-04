<?php

declare(strict_types=1);

namespace Tests;

use App\Core\AuthContext;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\SessionPrincipal;
use App\Core\View;
use App\Model\Database;
use App\Security\CredentialFingerprint;
use App\Security\DatabaseSessionCredentialValidator;
use App\Security\SessionCredentialValidator;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SessionCredentialValidationTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;

    /** @var array<string, null|string> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE clubs (id INTEGER PRIMARY KEY, approved_at TEXT, password_hash TEXT NOT NULL)');
        $this->databaseConnection->setValue(null, $this->database);

        foreach (['ADMIN_USER', 'ADMIN_PASS_HASH'] as $key) {
            $value = env($key);
            $this->originalEnvironment[$key] = is_string($value) ? $value : null;
        }

        Session::destroy();
        Session::start();
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        foreach ($this->originalEnvironment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
        Session::destroy();
    }

    public function testClubCredentialRotationInvalidatesItsFingerprint(): void
    {
        $passwordHash = password_hash('OriginalPassword123!', PASSWORD_DEFAULT);
        self::assertIsString($passwordHash);
        $statement = $this->database->prepare('INSERT INTO clubs (id, password_hash) VALUES (?, ?)');
        $statement->execute([42, $passwordHash]);
        $validator = new DatabaseSessionCredentialValidator();
        $fingerprint = CredentialFingerprint::forClubPasswordHash($passwordHash);

        self::assertTrue($validator->isCurrent(SessionPrincipal::club(42), $fingerprint));

        $replacementHash = password_hash('ReplacementPassword123!', PASSWORD_DEFAULT);
        self::assertIsString($replacementHash);
        $this->database->prepare('UPDATE clubs SET password_hash = ? WHERE id = ?')
            ->execute([$replacementHash, 42]);

        self::assertFalse($validator->isCurrent(SessionPrincipal::club(42), $fingerprint));
    }

    public function testAdministratorCredentialRotationInvalidatesItsFingerprint(): void
    {
        $passwordHash = password_hash('OriginalAdminPassword123!', PASSWORD_DEFAULT);
        self::assertIsString($passwordHash);
        $this->setEnvironment('ADMIN_USER', 'synthetic-admin');
        $this->setEnvironment('ADMIN_PASS_HASH', $passwordHash);
        $validator = new DatabaseSessionCredentialValidator();
        $fingerprint = CredentialFingerprint::forAdministrator('synthetic-admin', $passwordHash);

        self::assertTrue($validator->isCurrent(SessionPrincipal::administrator(), $fingerprint));

        $replacementHash = password_hash('ReplacementAdminPassword123!', PASSWORD_DEFAULT);
        self::assertIsString($replacementHash);
        $this->setEnvironment('ADMIN_PASS_HASH', $replacementHash);

        self::assertFalse($validator->isCurrent(SessionPrincipal::administrator(), $fingerprint));
    }

    public function testClubValidationUsesOneBoundedCredentialQuery(): void
    {
        $passwordHash = password_hash('BoundedQueryPassword123!', PASSWORD_DEFAULT);
        self::assertIsString($passwordHash);
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with([42])->willReturn(true);
        $statement->expects(self::once())->method('fetchColumn')->willReturn($passwordHash);
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())
            ->method('prepare')
            ->with('SELECT password_hash FROM clubs WHERE id = ?')
            ->willReturn($statement);
        $this->databaseConnection->setValue(null, $database);

        self::assertTrue((new DatabaseSessionCredentialValidator())->isCurrent(
            SessionPrincipal::club(42),
            CredentialFingerprint::forClubPasswordHash($passwordHash)
        ));
    }

    public function testProtectedRouteRejectsAndClearsStaleCredentialsBeforeHandler(): void
    {
        $validator = $this->createStub(SessionCredentialValidator::class);
        $validator->method('isCurrent')->willReturn(false);
        $router = new Router(new View(dirname(__DIR__) . '/views'), $validator);
        $handled = false;
        $router->get('/protected', static function () use (&$handled): Response {
            $handled = true;

            return new Response('unsafe');
        }, AuthContext::CLUB);
        Session::set('locale', 'it');
        Session::authenticateClub(42, hash('sha256', 'stale-credential'));

        $response = $router->dispatch(new Request('GET', '/protected'));

        self::assertSame(302, $response->status());
        self::assertFalse($handled);
        self::assertFalse(AuthContext::isAuthenticated());
        self::assertSame('it', Session::get('locale'));
    }

    public function testPublicRouteSeesStaleCredentialsAsAnonymous(): void
    {
        $validator = $this->createStub(SessionCredentialValidator::class);
        $validator->method('isCurrent')->willReturn(false);
        $router = new Router(new View(dirname(__DIR__) . '/views'), $validator);
        $wasAuthenticated = true;
        $router->get('/public', static function () use (&$wasAuthenticated): Response {
            $wasAuthenticated = AuthContext::isAuthenticated();

            return new Response('public');
        });
        Session::authenticateClub(42, hash('sha256', 'stale-credential'));

        $response = $router->dispatch(new Request('GET', '/public'));

        self::assertSame(200, $response->status());
        self::assertFalse($wasAuthenticated);
    }

    private function setEnvironment(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
