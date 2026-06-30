<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\HealthController;
use App\Core\Logger;
use App\Core\Request;
use App\Core\View;
use App\Service\DeploymentHealth;
use PHPUnit\Framework\TestCase;

final class DeploymentHealthTest extends TestCase
{
    private string $revisionPath;
    private View $view;

    protected function setUp(): void
    {
        $this->revisionPath = sys_get_temp_dir() . '/competizionijudo-revision-' . bin2hex(random_bytes(8));
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        if (is_file($this->revisionPath)) {
            unlink($this->revisionPath);
        }
    }

    public function testHealthyDatabaseReturnsOnlyStatusAndExactRevision(): void
    {
        $revision = str_repeat('a', 40);
        file_put_contents($this->revisionPath, $revision . PHP_EOL);

        $response = $this->request(new DeploymentHealth(static fn(): bool => true, $this->revisionPath));

        self::assertSame(200, $response->status());
        self::assertSame(
            ['status' => 'ok', 'revision' => $revision],
            json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR)
        );
        self::assertSame('application/json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-store', $response->headers()['Cache-Control']);
    }

    public function testInvalidRevisionFailsClosedBeforeDatabaseProbe(): void
    {
        file_put_contents($this->revisionPath, 'not-a-commit');
        $probed = false;
        $health = new DeploymentHealth(static function () use (&$probed): bool {
            $probed = true;

            return true;
        }, $this->revisionPath);

        $response = $this->request($health);

        self::assertSame(503, $response->status());
        self::assertSame(['status' => 'unavailable'], json_decode($response->content(), true));
        self::assertFalse($probed);
    }

    public function testDatabaseFailureReturnsNoInternalDetail(): void
    {
        file_put_contents($this->revisionPath, str_repeat('b', 40));

        $response = $this->request(new DeploymentHealth(static fn(): bool => false, $this->revisionPath));

        self::assertSame(503, $response->status());
        self::assertSame('{"status":"unavailable"}', $response->content());
        self::assertStringNotContainsString('database', strtolower($response->content()));
    }

    private function request(DeploymentHealth $health): \App\Core\Response
    {
        $request = new Request('GET', '/health');
        $controller = new HealthController(
            $this->view,
            $request,
            $health,
            $this->createStub(Logger::class)
        );

        return $controller->show($request);
    }
}
