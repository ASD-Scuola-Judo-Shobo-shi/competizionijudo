<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Request;
use App\Core\Router;
use App\Core\View;
use App\Localization;
use App\Model\ClubTermsAcceptance;
use PHPUnit\Framework\TestCase;

final class TermsOfServiceTest extends TestCase
{
    public function testTermsArePubliclyRoutableAndDisplayTheContractVersion(): void
    {
        Localization::setLocale('it');
        $view = new View(dirname(__DIR__) . '/views');
        $router = new Router($view);
        (require dirname(__DIR__) . '/routes/web.php')($router);

        $response = $router->dispatch(new Request('GET', '/terms'));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Condizioni di servizio', $response->content());
        self::assertStringContainsString(ClubTermsAcceptance::VERSION, $response->content());
        self::assertStringContainsString('href="/privacy"', $response->content());
    }

    public function testTermsContainTheMaterialClubObligationsInBothLocales(): void
    {
        $view = new View(dirname(__DIR__) . '/views');
        $operator = [
            'controller_name' => 'Synthetic Operator',
            'controller_address' => '1 Test Street',
            'controller_fiscal_code' => 'SYNTHETIC-FISCAL-CODE',
            'contact_email' => 'terms@example.test',
        ];

        foreach (['en', 'it'] as $locale) {
            Localization::setLocale($locale);
            $html = $view->render('static/terms', [
                'title' => __('terms.title'),
                'termsVersion' => ClubTermsAcceptance::VERSION,
                'operator' => $operator,
            ], 'layouts/error');

            self::assertStringContainsString(e(__('terms.scope')), $html);
            self::assertStringContainsString(e(__('terms.authority')), $html);
            self::assertStringContainsString(e(__('terms.notice_delivery')), $html);
            self::assertStringContainsString(e(__('terms.sensitive_data')), $html);
            self::assertStringContainsString(e(__('terms.changes')), $html);
            self::assertStringContainsString('Synthetic Operator', $html);
            self::assertStringContainsString('terms@example.test', $html);
        }
    }
}
