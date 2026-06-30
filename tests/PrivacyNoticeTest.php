<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\HomeController;
use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Localization;
use PHPUnit\Framework\TestCase;

final class PrivacyNoticeTest extends TestCase
{
    public function testPrivacyNoticeIsPubliclyRoutableAndTechnicalCookieHasNoConsentBanner(): void
    {
        Localization::setLocale('it');
        $view = new View(dirname(__DIR__) . '/views');
        $router = new Router($view);
        (require dirname(__DIR__) . '/routes/web.php')($router);

        $response = $router->dispatch(new Request('GET', '/privacy'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Informativa privacy', $response->content());
        self::assertStringContainsString('Cookie tecnici', $response->content());
        self::assertStringNotContainsString('cookie-consent', $response->content());
    }

    public function testPrivacyConfigurationDerivesControllerFactsFromEnvironment(): void
    {
        $original = $_ENV['PRIVACY_CONTROLLER_NAME'] ?? null;
        $_ENV['PRIVACY_CONTROLLER_NAME'] = 'Synthetic Environment Controller';

        try {
            $privacy = require dirname(__DIR__) . '/config/privacy.php';
            self::assertSame('Synthetic Environment Controller', $privacy['controller_name']);
        } finally {
            if ($original === null) {
                unset($_ENV['PRIVACY_CONTROLLER_NAME']);
            } else {
                $_ENV['PRIVACY_CONTROLLER_NAME'] = $original;
            }
        }
    }

    public function testHomepageNoLongerLoadsHardCodedCompetitionData(): void
    {
        $controller = new HomeController(
            new View(dirname(__DIR__) . '/views'),
            new Request('GET', '/')
        );

        self::assertSame(200, $controller->index(new Request('GET', '/'))->status());
        self::assertFileDoesNotExist(dirname(__DIR__) . '/src/Model/Competition.php');
    }
}
