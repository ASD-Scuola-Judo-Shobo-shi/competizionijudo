<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubController;
use App\Core\Application;
use App\Core\FileLogger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Security\AuthenticationThrottle;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakePasswordResetRepository;
use Tests\Support\FakePasswordResetTokenIssuer;

final class ErrorLoggingTest extends TestCase
{
    private const CORRELATION_ID = '0123456789abcdef0123456789abcdef';

    private string $logPath;
    private FileLogger $logger;
    private View $view;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/competizionijudo-error-'
            . bin2hex(random_bytes(8)) . '.log';
        $this->logger = new FileLogger($this->logPath);
        $this->view = new View(dirname(__DIR__) . '/views');

        $this->startCleanSession();
        Localization::setLocale('it');
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }

        $this->destroySession();
    }

    public function testUncaughtFailureReturnsGenericLocalizedUiAndWritesRedactedRecord(): void
    {
        $internalDetail = 'password=SENSITIVE-A token=SENSITIVE-B session=SENSITIVE-C';
        $application = new Application(dirname(__DIR__), $this->logger);
        $application->router()->get(
            '/synthetic-failure',
            static fn(): Response => throw new RuntimeException($internalDetail)
        );
        $request = new Request(
            'GET',
            '/synthetic-failure',
            [],
            [],
            [],
            self::CORRELATION_ID
        );

        $response = $application->handle($request);
        $records = $this->records();
        $rawLog = (string) file_get_contents($this->logPath);

        self::assertSame(500, $response->status());
        self::assertStringContainsString(e(__('errors.unexpected_failure')), $response->content());
        self::assertStringContainsString(self::CORRELATION_ID, $response->content());
        self::assertStringNotContainsString($internalDetail, $response->content());
        self::assertStringNotContainsString('<pre>', $response->content());
        self::assertCount(1, $records);
        self::assertSame('application.unhandled_failure', $records[0]['event']);
        self::assertSame(self::CORRELATION_ID, $records[0]['correlation_id']);
        self::assertSame('[redacted]', $records[0]['exception']['message']);
        self::assertSame('/synthetic-failure', $records[0]['context']['path']);
        self::assertStringNotContainsString('SENSITIVE-', $rawLog);
    }

    public function testControllerFailureReturnsGenericErrorAndUsesRequestCorrelationId(): void
    {
        $internalDetail = 'database host detail SENSITIVE-D';
        $throttle = $this->createMock(AuthenticationThrottle::class);
        $throttle->expects(self::once())
            ->method('isBlocked')
            ->willThrowException(new RuntimeException($internalDetail));
        $request = new Request(
            'POST',
            '/club_login.php',
            [],
            [
                'csrf_token' => csrf_token(),
                'email' => 'synthetic@example.test',
                'password' => 'SENSITIVE-E',
            ],
            ['REMOTE_ADDR' => '192.0.2.60'],
            self::CORRELATION_ID
        );
        $controller = new ClubController(
            $this->view,
            $request,
            new FakePasswordResetTokenIssuer(null),
            $throttle,
            new FakePasswordResetRepository(),
            $this->logger
        );

        $response = $controller->login($request);
        $records = $this->records();
        $rawLog = (string) file_get_contents($this->logPath);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            e(__('club.login.errors.login_failed')),
            $response->content()
        );
        self::assertStringNotContainsString($internalDetail, $response->content());
        self::assertCount(1, $records);
        self::assertSame('club.login_failed', $records[0]['event']);
        self::assertSame(self::CORRELATION_ID, $records[0]['correlation_id']);
        self::assertSame('POST', $records[0]['context']['method']);
        self::assertSame('/club_login.php', $records[0]['context']['path']);
        self::assertStringNotContainsString('SENSITIVE-', $rawLog);
        self::assertStringNotContainsString('synthetic@example.test', $rawLog);
    }

    public function testLoggerRedactsContextOutsideTheExplicitSafeAllowlist(): void
    {
        $this->logger->error(
            'test.context_redaction',
            new RuntimeException('SENSITIVE-F'),
            self::CORRELATION_ID,
            [
                'path' => '/safe-path',
                'password' => 'SENSITIVE-G',
                'reset_token' => 'SENSITIVE-H',
                'session_id' => 'SENSITIVE-I',
                'athlete_record' => ['name' => 'SENSITIVE-J'],
            ]
        );

        $record = $this->records()[0];
        $rawLog = (string) file_get_contents($this->logPath);

        self::assertSame('/safe-path', $record['context']['path']);
        self::assertSame('[redacted]', $record['context']['redacted_1']);
        self::assertSame('[redacted]', $record['context']['redacted_2']);
        self::assertSame('[redacted]', $record['context']['redacted_3']);
        self::assertSame('[redacted]', $record['context']['redacted_4']);
        self::assertStringNotContainsString('SENSITIVE-', $rawLog);
        self::assertStringNotContainsString('password', $rawLog);
        self::assertStringNotContainsString('reset_token', $rawLog);
        self::assertStringNotContainsString('session_id', $rawLog);
        self::assertStringNotContainsString('athlete_record', $rawLog);
    }

    /** @return list<array<string, mixed>> */
    private function records(): array
    {
        $contents = file_get_contents($this->logPath);
        self::assertIsString($contents);
        $lines = array_values(array_filter(explode(PHP_EOL, trim($contents))));

        return array_map(
            static function (string $line): array {
                $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($record);

                return $record;
            },
            $lines
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
