<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Security\AuthenticationThrottle;
use App\Service\PasswordResetMailer;
use App\Service\PasswordResetTokenIssuer;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuthenticationThrottle;
use Tests\Support\FakePasswordResetMailer;
use Tests\Support\FakePasswordResetTokenIssuer;

final class ForgotPasswordControllerTest extends TestCase
{
    private const ENVIRONMENT_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_TEST_RESET_LINKS',
        'APP_URL',
        'PASSWORD_RESET_MAILER',
        'MAIL_FROM_ADDRESS',
    ];

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

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['exists']) {
                $_ENV[$key] = $original['value'];
            } else {
                unset($_ENV[$key]);
            }
        }

        $this->destroySession();
    }

    public function testProductionKnownAndUnknownEmailsReceiveTheSameGenericResponse(): void
    {
        $this->setResetEnvironment('production', true, true);
        $knownIssuer = new FakePasswordResetTokenIssuer(hash('sha256', 'known-club-fixture'));
        $unknownIssuer = new FakePasswordResetTokenIssuer(null);
        $knownMailer = new FakePasswordResetMailer();
        $unknownMailer = new FakePasswordResetMailer();

        $knownResponse = $this->submit('known@example.test', $knownIssuer, null, $knownMailer);
        $unknownResponse = $this->submit('unknown@example.test', $unknownIssuer, null, $unknownMailer);

        self::assertSame(200, $knownResponse->status());
        self::assertSame($knownResponse->status(), $unknownResponse->status());
        self::assertSame($knownResponse->content(), $unknownResponse->content());
        self::assertStringContainsString(
            e(__('club.forgot_password.success_message')),
            $knownResponse->content()
        );
        self::assertStringNotContainsString('token=', $knownResponse->content());
        self::assertSame(['known@example.test'], $knownIssuer->requestedEmails);
        self::assertSame(['unknown@example.test'], $unknownIssuer->requestedEmails);
        self::assertCount(1, $knownMailer->sent);
        self::assertSame('known@example.test', $knownMailer->sent[0]['recipient']);
        self::assertStringStartsWith('https://', $knownMailer->sent[0]['reset_url']);
        self::assertSame([], $unknownMailer->sent);
    }

    public function testLocalTestModeDisplaysTheLinkForAKnownEmail(): void
    {
        $this->setResetEnvironment('local', true, true);
        $issuer = new FakePasswordResetTokenIssuer(hash('sha256', 'local-club-fixture'));
        $mailer = new FakePasswordResetMailer();

        $response = $this->submit('known@example.test', $issuer, null, $mailer);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringContainsString('/club_reset_password.php?token=', $response->content());
        self::assertSame(['known@example.test'], $issuer->requestedEmails);
        self::assertSame([], $mailer->sent);
    }

    public function testLocalTestModeKeepsAnUnknownEmailGeneric(): void
    {
        $this->setResetEnvironment('local', true, true);
        $issuer = new FakePasswordResetTokenIssuer(null);

        $response = $this->submit('unknown@example.test', $issuer);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringNotContainsString('token=', $response->content());
        self::assertSame(['unknown@example.test'], $issuer->requestedEmails);
    }

    public function testEveryLocalDisclosureFlagIsRequired(): void
    {
        $configurations = [
            ['production', true, true],
            ['local', false, true],
            ['local', true, false],
        ];

        foreach ($configurations as [$environment, $debug, $testResetLinks]) {
            $this->setResetEnvironment($environment, $debug, $testResetLinks);
            $issuer = new FakePasswordResetTokenIssuer(hash('sha256', 'disabled-fixture'));

            $mailer = new FakePasswordResetMailer();
            $response = $this->submit('known@example.test', $issuer, null, $mailer);

            self::assertStringNotContainsString('token=', $response->content());
            self::assertSame(['known@example.test'], $issuer->requestedEmails);
            self::assertCount(1, $mailer->sent);
        }
    }

    public function testBlockedResetRequestKeepsGenericResponseAndDoesNotIssueToken(): void
    {
        $this->setResetEnvironment('local', true, true);
        $issuer = new FakePasswordResetTokenIssuer(hash('sha256', 'blocked-fixture'));
        $mailer = new FakePasswordResetMailer();
        $throttle = new FakeAuthenticationThrottle(1);
        $throttle->recordAttempt('password-reset', 'known@example.test', '192.0.2.40');

        $response = $this->submit('known@example.test', $issuer, $throttle, $mailer);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringNotContainsString('token=', $response->content());
        self::assertSame([], $issuer->requestedEmails);
        self::assertSame([], $mailer->sent);
        self::assertCount(1, $throttle->recorded);
    }

    public function testProductionMailFailureRemainsGenericAndDoesNotExposeTheToken(): void
    {
        $this->setResetEnvironment('production', false, false);
        $issuer = new FakePasswordResetTokenIssuer('synthetic-secret-token');

        $response = $this->submit(
            'known@example.test',
            $issuer,
            null,
            new FakePasswordResetMailer(true)
        );

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringNotContainsString('synthetic-secret-token', $response->content());
        self::assertStringNotContainsString(__('club.forgot_password.errors.request_failed'), $response->content());
    }

    private function setResetEnvironment(string $environment, bool $debug, bool $testResetLinks): void
    {
        $_ENV['APP_ENV'] = $environment;
        $_ENV['APP_DEBUG'] = $debug ? 'true' : 'false';
        $_ENV['APP_TEST_RESET_LINKS'] = $testResetLinks ? 'true' : 'false';
        $_ENV['APP_URL'] = 'https://www.competizionijudo.it';
        $_ENV['PASSWORD_RESET_MAILER'] = 'aruba';
        $_ENV['MAIL_FROM_ADDRESS'] = 'postmaster@competizionijudo.it';
    }

    private function submit(
        string $email,
        PasswordResetTokenIssuer $issuer,
        ?AuthenticationThrottle $throttle = null,
        ?PasswordResetMailer $mailer = null,
        ?Logger $logger = null
    ): Response {
        $request = new Request('POST', '/club_forgot_password.php', [], [
            'csrf_token' => csrf_token(),
            'email' => $email,
        ], ['REMOTE_ADDR' => '192.0.2.40']);
        $controller = new ClubController(
            $this->view,
            $request,
            $issuer,
            $throttle ?? new FakeAuthenticationThrottle(),
            null,
            $logger ?? $this->createStub(Logger::class),
            $mailer ?? new FakePasswordResetMailer()
        );

        return $controller->forgotPassword($request);
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
