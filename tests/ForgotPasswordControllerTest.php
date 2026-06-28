<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Security\AuthenticationThrottle;
use App\Service\PasswordResetTokenIssuer;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeAuthenticationThrottle;
use Tests\Support\FakePasswordResetTokenIssuer;

final class ForgotPasswordControllerTest extends TestCase
{
    private const ENVIRONMENT_KEYS = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_TEST_RESET_LINKS',
        'APP_URL',
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

    public function testProductionKnownAndUnknownEmailsReceiveTheSameUnavailableResponse(): void
    {
        $this->setResetEnvironment('production', true, true);
        $knownIssuer = new FakePasswordResetTokenIssuer(hash('sha256', 'known-club-fixture'));
        $unknownIssuer = new FakePasswordResetTokenIssuer(null);

        $knownResponse = $this->submit('known@example.test', $knownIssuer);
        $unknownResponse = $this->submit('unknown@example.test', $unknownIssuer);

        self::assertSame(200, $knownResponse->status());
        self::assertSame($knownResponse->status(), $unknownResponse->status());
        self::assertSame($knownResponse->content(), $unknownResponse->content());
        self::assertStringContainsString(
            e(__('club.forgot_password.unavailable_message')),
            $knownResponse->content()
        );
        self::assertStringNotContainsString('token=', $knownResponse->content());
        self::assertSame([], $knownIssuer->requestedEmails);
        self::assertSame([], $unknownIssuer->requestedEmails);
    }

    public function testLocalTestModeDisplaysTheLinkForAKnownEmail(): void
    {
        $this->setResetEnvironment('local', true, true);
        $issuer = new FakePasswordResetTokenIssuer(hash('sha256', 'local-club-fixture'));

        $response = $this->submit('known@example.test', $issuer);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringContainsString('/club_reset_password.php?token=', $response->content());
        self::assertSame(['known@example.test'], $issuer->requestedEmails);
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

            $response = $this->submit('known@example.test', $issuer);

            self::assertStringNotContainsString('token=', $response->content());
            self::assertSame([], $issuer->requestedEmails);
        }
    }

    public function testBlockedResetRequestKeepsGenericResponseAndDoesNotIssueToken(): void
    {
        $this->setResetEnvironment('local', true, true);
        $issuer = new FakePasswordResetTokenIssuer(hash('sha256', 'blocked-fixture'));
        $throttle = new FakeAuthenticationThrottle(1);
        $throttle->recordAttempt('password-reset', 'known@example.test', '192.0.2.40');

        $response = $this->submit('known@example.test', $issuer, $throttle);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(e(__('club.forgot_password.success_message')), $response->content());
        self::assertStringNotContainsString('token=', $response->content());
        self::assertSame([], $issuer->requestedEmails);
        self::assertCount(1, $throttle->recorded);
    }

    private function setResetEnvironment(string $environment, bool $debug, bool $testResetLinks): void
    {
        $_ENV['APP_ENV'] = $environment;
        $_ENV['APP_DEBUG'] = $debug ? 'true' : 'false';
        $_ENV['APP_TEST_RESET_LINKS'] = $testResetLinks ? 'true' : 'false';
        $_ENV['APP_URL'] = 'http://localhost';
    }

    private function submit(
        string $email,
        PasswordResetTokenIssuer $issuer,
        ?AuthenticationThrottle $throttle = null
    ): Response {
        $request = new Request('POST', '/club_forgot_password.php', [], [
            'csrf_token' => csrf_token(),
            'email' => $email,
        ], ['REMOTE_ADDR' => '192.0.2.40']);
        $controller = new ClubController(
            $this->view,
            $request,
            $issuer,
            $throttle ?? new FakeAuthenticationThrottle()
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
