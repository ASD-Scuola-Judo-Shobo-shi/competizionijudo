<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use App\Security\AuthenticationThrottle;
use App\Security\PasswordPolicy;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\FakeAuthenticationThrottle;
use Tests\Support\FakePasswordResetMailer;
use Tests\Support\FakePasswordResetRepository;
use Tests\Support\FakePasswordResetTokenIssuer;
use Throwable;

final class ClubRegistrationControllerTest extends TestCase
{
    private const ENVIRONMENT_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_TEST_RESET_LINKS',
        'APP_URL',
        'APP_OWNER_EMAIL',
    ];

    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;

    /** @var array<string, array{exists: bool, value: mixed}> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (self::ENVIRONMENT_KEYS as $key) {
            $this->originalEnvironment[$key] = [
                'exists' => array_key_exists($key, $_ENV),
                'value' => $_ENV[$key] ?? null,
            ];
        }

        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['APP_TEST_RESET_LINKS'] = 'false';
        $_ENV['APP_URL'] = 'https://registration.example.test';
        $_ENV['APP_OWNER_EMAIL'] = 'owner@example.test';

        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->database->sqliteCreateFunction(
            'LAST_INSERT_ID',
            fn(): int => (int) $this->database->lastInsertId()
        );
        $this->createSchema();
        $this->databaseConnection->setValue(null, $this->database);

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['exists']) {
                $_ENV[$key] = $original['value'];
            } else {
                unset($_ENV[$key]);
            }
        }

        $this->destroySession();
    }

    public function testProductionRegistrationSendsConfirmationEmailWithoutExposingToken(): void
    {
        $mailer = new FakePasswordResetMailer();

        $response = $this->submit($mailer, $this->createStub(Logger::class));

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.register.confirmation_sent')), $response->content());
        self::assertStringNotContainsString('token=', $response->content());
        self::assertCount(1, $mailer->confirmationSent);
        self::assertSame('new.club@example.test', $mailer->confirmationSent[0]['recipient']);
        self::assertMatchesRegularExpression(
            '#\Ahttps://registration\.example\.test/clubs/confirm-registration\?token=[a-f0-9]{64}\z#',
            $mailer->confirmationSent[0]['confirmation_url']
        );
        self::assertSame(
            1,
            (int) $this->database->query('SELECT COUNT(*) FROM club_registration_confirmations')->fetchColumn()
        );
        $payload = json_decode(
            (string) $this->database->query(
                'SELECT registration_payload FROM club_registration_confirmations'
            )->fetchColumn(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($payload);
        self::assertSame(
            ClubDataRightsDeclaration::VERSION,
            $payload['data_rights_declaration_version'] ?? null
        );
        self::assertSame(ClubTermsAcceptance::VERSION, $payload['terms_version'] ?? null);
        self::assertSame('it', $payload['terms_locale'] ?? null);
    }

    public function testRegistrationTransportFailureIsReportedAsDeliveryFailure(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'club.registration_confirmation_delivery_failed',
                self::isInstanceOf(Throwable::class),
                self::matchesRegularExpression('/\A[a-f0-9]{32}\z/'),
                ['method' => 'POST', 'path' => '/clubs/register']
            );

        $response = $this->submit(new FakePasswordResetMailer(true), $logger);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            e(__('club.register.errors.confirmation_delivery_failed')),
            $response->content()
        );
        self::assertStringNotContainsString(e(__('club.register.confirmation_sent')), $response->content());
        self::assertStringNotContainsString('token=', $response->content());
    }

    public function testRegistrationThrottlePreventsConfirmationEmailAbuse(): void
    {
        $throttle = new FakeAuthenticationThrottle(1);
        self::assertTrue($throttle->consume(
            'club-registration',
            'new.club@example.test',
            '192.0.2.50'
        ));
        $mailer = new FakePasswordResetMailer();

        $response = $this->submit($mailer, $this->createStub(Logger::class), $throttle);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            e(__('club.register.errors.too_many_attempts')),
            $response->content()
        );
        self::assertSame([], $mailer->confirmationSent);
        self::assertSame(
            0,
            (int) $this->database->query('SELECT COUNT(*) FROM club_registration_confirmations')->fetchColumn()
        );
    }

    public function testConfirmedRegistrationNotifiesOwnerOnlyOnce(): void
    {
        $mailer = new FakePasswordResetMailer();
        $this->submit($mailer, $this->createStub(Logger::class));
        $token = $this->confirmationToken($mailer);

        $response = $this->confirm($token, $mailer, $this->createStub(Logger::class));

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.confirm_registration.success')), $response->content());
        self::assertCount(1, $mailer->newClubNotifications);
        self::assertSame([
            'recipient' => 'owner@example.test',
            'club_name' => 'New Synthetic Club',
            'club_email' => 'new.club@example.test',
            'admin_url' => 'https://registration.example.test/admin/clubs',
        ], $mailer->newClubNotifications[0]);

        $secondResponse = $this->confirm($token, $mailer, $this->createStub(Logger::class));

        self::assertStringContainsString(
            e(__('club.confirm_registration.invalid_token')),
            $secondResponse->content()
        );
        self::assertCount(1, $mailer->newClubNotifications);
    }

    public function testOwnerNotificationFailureDoesNotUndoConfirmedRegistration(): void
    {
        $submissionMailer = new FakePasswordResetMailer();
        $this->submit($submissionMailer, $this->createStub(Logger::class));
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'club.registration_owner_notification_failed',
                self::isInstanceOf(Throwable::class),
                self::matchesRegularExpression('/\A[a-f0-9]{32}\z/'),
                ['method' => 'GET', 'path' => '/clubs/confirm-registration']
            );

        $response = $this->confirm(
            $this->confirmationToken($submissionMailer),
            new FakePasswordResetMailer(true),
            $logger
        );

        self::assertStringContainsString(e(__('club.confirm_registration.success')), $response->content());
        self::assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM clubs')->fetchColumn());
        self::assertSame(
            0,
            (int) $this->database->query('SELECT COUNT(*) FROM club_registration_confirmations')->fetchColumn()
        );
    }

    public function testNormalizedClubNameDuplicateIsRejectedBeforeSendingConfirmation(): void
    {
        $this->database->prepare(
            'INSERT INTO clubs (federal_code, name, email, phone, city, province, contact_first_name, contact_last_name, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            'SYN-EXISTING', "a.s.d. SYNTHETIC CITTA'", 'existing@example.test', '0700000000',
            'Nuoro', 'Provincia di Nuoro', 'Synthetic', 'Contact', 'synthetic-hash',
        ]);
        $mailer = new FakePasswordResetMailer();

        $response = $this->submit($mailer, $this->createStub(Logger::class), null, 'ASD Synthetic Città');

        self::assertStringContainsString(e(__('club.register.errors.club_exists')), $response->content());
        self::assertSame([], $mailer->confirmationSent);
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM club_registration_confirmations')->fetchColumn());
    }

    private function submit(
        FakePasswordResetMailer $mailer,
        Logger $logger,
        ?AuthenticationThrottle $throttle = null,
        string $clubName = 'New Synthetic Club'
    ): \App\Core\Response {
        $password = str_repeat('x', PasswordPolicy::MINIMUM_LENGTH);
        $request = new Request('POST', '/clubs/register', [], [
            'csrf_token' => csrf_token(),
            'name' => $clubName,
            'federal_code' => 'SYN-NEW',
            'email' => 'New.Club@Example.Test',
            'phone' => '0700000000',
            'address_line' => 'Via Roma 1',
            'postal_code' => '08100',
            'province' => 'Provincia di Nuoro',
            'city' => 'Nuoro',
            'contact_first_name' => 'Synthetic',
            'contact_last_name' => 'Contact',
            'affiliation' => ['FIJLKAM'],
            'password' => $password,
            'password2' => $password,
            'terms_accepted' => '1',
            'athlete_data_rights_declaration' => '1',
        ], ['REMOTE_ADDR' => '192.0.2.50']);
        $controller = new ClubController(
            $this->view,
            $request,
            new FakePasswordResetTokenIssuer(null),
            $throttle ?? new FakeAuthenticationThrottle(),
            new FakePasswordResetRepository(),
            $logger,
            $mailer
        );

        return $controller->register($request);
    }

    private function confirmationToken(FakePasswordResetMailer $mailer): string
    {
        self::assertCount(1, $mailer->confirmationSent);
        $query = parse_url($mailer->confirmationSent[0]['confirmation_url'], PHP_URL_QUERY);
        self::assertIsString($query);
        parse_str($query, $parameters);
        $token = $parameters['token'] ?? null;
        self::assertIsString($token);

        return $token;
    }

    private function confirm(string $token, FakePasswordResetMailer $mailer, Logger $logger): \App\Core\Response
    {
        $request = new Request('GET', '/clubs/confirm-registration', ['token' => $token]);
        $controller = new ClubController(
            $this->view,
            $request,
            new FakePasswordResetTokenIssuer(null),
            new FakeAuthenticationThrottle(),
            new FakePasswordResetRepository(),
            $logger,
            $mailer
        );

        return $controller->confirmRegistration($request);
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                phone TEXT NOT NULL,
                address_line TEXT,
                postal_code TEXT,
                city TEXT NOT NULL,
                province TEXT NOT NULL,
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                affiliation TEXT,
                approved_at TEXT,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE club_registration_confirmations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL UNIQUE,
                registration_payload TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                confirmed_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE club_data_rights_declarations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                declared_by_club_id INTEGER NOT NULL,
                declaration_version TEXT NOT NULL,
                declared_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE club_terms_acceptances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                accepted_by_club_id INTEGER NOT NULL,
                representative_name TEXT NOT NULL,
                accepted_account_email TEXT NOT NULL,
                terms_version TEXT NOT NULL,
                accepted_locale TEXT NOT NULL,
                accepted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );'
        );
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
