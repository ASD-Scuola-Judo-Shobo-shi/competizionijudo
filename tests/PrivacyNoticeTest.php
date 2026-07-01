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

    public function testPrivacyNoticeExplainsClubDeclarationInBothLocales(): void
    {
        $view = new View(dirname(__DIR__) . '/views');
        $privacy = [
            'controller_name' => 'Synthetic Controller',
            'controller_address' => '1 Test Street',
            'controller_fiscal_code' => 'SYNTHETIC-FISCAL-CODE',
            'contact_email' => 'privacy@example.test',
            'hosting_provider' => 'Synthetic Host',
            'hosting_location' => 'European Union',
            'log_retention_days' => 30,
            'backup_retention_days' => 30,
        ];

        foreach (['en', 'it'] as $locale) {
            Localization::setLocale($locale);
            $html = $view->render('home/privacy', [
                'title' => __('privacy.title'),
                'privacy' => $privacy,
            ], 'layouts/error');

            self::assertStringContainsString(e(__('privacy.source_title')), $html);
            self::assertStringContainsString(e(__('privacy.account_legal_basis')), $html);
            self::assertStringContainsString(e(__('privacy.athlete_legal_basis')), $html);
            self::assertStringContainsString(e(__('privacy.club_declaration')), $html);
            self::assertStringContainsString('Synthetic Host', $html);
        }
    }

    public function testPrivacyConfigurationDerivesControllerFactsFromEnvironment(): void
    {
        $originalName = $_ENV['APP_OWNER'] ?? null;
        $originalFiscalCode = $_ENV['APP_OWNER_FISCAL_CODE'] ?? null;
        $_ENV['APP_OWNER'] = 'Synthetic Environment Controller';
        $_ENV['APP_OWNER_FISCAL_CODE'] = 'SYNTHETIC-FISCAL-CODE';

        try {
            $privacy = require dirname(__DIR__) . '/config/privacy.php';
            self::assertSame('Synthetic Environment Controller', $privacy['controller_name']);
            self::assertSame('SYNTHETIC-FISCAL-CODE', $privacy['controller_fiscal_code']);
        } finally {
            if ($originalName === null) {
                unset($_ENV['APP_OWNER']);
            } else {
                $_ENV['APP_OWNER'] = $originalName;
            }
            if ($originalFiscalCode === null) {
                unset($_ENV['APP_OWNER_FISCAL_CODE']);
            } else {
                $_ENV['APP_OWNER_FISCAL_CODE'] = $originalFiscalCode;
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
