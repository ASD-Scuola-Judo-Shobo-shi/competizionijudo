<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Localization;
use App\Model\AgeClass;
use App\Model\Event;
use App\Model\JudoCategory;
use App\Presentation\Navigation;
use PHPUnit\Framework\TestCase;

final class ViewRenderTest extends TestCase
{
    public function testAddEventFormRendersWithoutEditorArtifacts(): void
    {
        Localization::setLocale('it');
        $_GET = [];

        $view = new View(dirname(__DIR__) . '/views');
        $html = $view->render('admin/add_event', array_merge([
            'currentPath' => '/admin/events/add',
            'event' => null,
            'error' => '',
            'locations' => [],
        ], $this->layoutData('/admin/events/add')));

        self::assertStringNotContainsString('</parameter>', $html);
        self::assertStringNotContainsString('</write_to_file>', $html);
        self::assertMatchesRegularExpression(
            '/<form method="post" class="form-card" enctype="multipart\/form-data">.*<\/form>/s',
            $html
        );
        self::assertSame(4, substr_count($html, 'class="required-marker"'));
        self::assertSame(substr_count($html, '<form'), substr_count($html, '</form>'));
    }

    public function testEventEntriesLinkIsVisibleToAllVisitors(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $_SESSION = [];
        $event = new Event(
            101,
            'Synthetic Event',
            '2026-06-29',
            'Synthetic Venue',
            'Synthetic Organizer',
            '2026-06-28',
            'only_competitive',
            null,
            null,
            null,
            null,
            true,
            false,
            null
        );
        $view = new View(dirname(__DIR__) . '/views');

        $authorized = $view->render('events/index', array_merge([
            'events' => [$event],
            'canViewEntries' => true,
        ], $this->layoutData('/events')));
        $anonymous = $view->render('events/index', array_merge([
            'events' => [$event],
            'canViewEntries' => false,
        ], $this->layoutData('/events')));

        self::assertStringContainsString('/events/details?event=101', $authorized);
        self::assertStringContainsString('/events/entries?event=101', $authorized);
        self::assertStringContainsString('>Details</a>', $authorized);
        self::assertStringContainsString('>Entries</a>', $authorized);
        // Entries link should now be visible to all visitors
        self::assertStringContainsString('/events/entries?event=101', $anonymous);
    }

    public function testAthletePreviewEmbedsSharedCategoryDefinitions(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $view = new View(dirname(__DIR__) . '/views');

        $html = $view->render('club/area_add', array_merge([
            'edit' => null,
            'errors' => [],
            'athletes' => [],
            'pagination' => paginate(0, 1, 50),
        ], $this->layoutData('/clubs/area')));

        self::assertStringContainsString('const ageClasses = ' . AgeClass::definitionsJson('en'), $html);
        self::assertStringContainsString(
            'const weightDefs = ' . JudoCategory::weightCategoryDefinitionsJson(),
            $html
        );
        self::assertStringContainsString('ageDisplay.textContent = ac.label;', $html);
        self::assertStringContainsString('weightDefs.limits[classKey]', $html);
        self::assertStringNotContainsString('childMap', $html);
        self::assertStringNotContainsString('adultMap', $html);
        self::assertStringContainsString('href="/clubs/athletes-export"', $html);
        self::assertStringContainsString('action="/clubs/athletes-import?"', $html);
        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString('name="athletes_csv"', $html);
    }

    public function testPrivacyAndErrorPagesUseSharedTranslucentContentPanel(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $view = new View(dirname(__DIR__) . '/views');

        $privacy = $view->render('static/privacy', array_merge([
            'privacy' => [
                'controller_fiscal_code' => 'SYNTHETIC-FISCAL-CODE',
            ],
        ], $this->layoutData('/privacy')));
        $error = $view->render('errors/500', [
            'title' => __('errors.server_error'),
            'message' => __('errors.unexpected_failure'),
            'reference' => __('errors.reference', ['id' => 'synthetic-reference']),
        ], 'layouts/error');
        $css = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');

        self::assertIsString($css);
        self::assertMatchesRegularExpression('/background:\s*rgba\(255,\s*255,\s*255,\s*\.75\)/', $css);
        self::assertStringContainsString('class="content-panel privacy-notice"', $privacy);
        self::assertSame(2, substr_count($privacy, 'Fiscal code: SYNTHETIC-FISCAL-CODE'));
        self::assertStringContainsString('class="content-panel error-card"', $error);
        self::assertMatchesRegularExpression(
            '#<link rel="stylesheet" href="/assets/css/app\.css\?v=[a-f0-9]{12}">#',
            $error
        );
        self::assertStringContainsString('synthetic-reference', $error);
    }

    public function testLayoutsUseTheSharedEmbeddedJudogiSvgFavicon(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $view = new View(dirname(__DIR__) . '/views');
        $favicon = (string) config('app.favicon');
        $expected = '<link rel="icon" href="' . $favicon . '">';
        $app = $view->render('static/index', $this->layoutData('/'));
        $error = $view->render('errors/500', [
            'title' => __('errors.server_error'),
            'message' => __('errors.unexpected_failure'),
            'reference' => __('errors.reference', ['id' => 'synthetic-reference']),
        ], 'layouts/error');

        self::assertSame(
            'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🥋</text></svg>',
            $favicon
        );
        foreach ([$app, $error] as $html) {
            self::assertStringContainsString($expected, $html);
        }
    }

    public function testExistingLogoAppearsInPageHeadingAndHomepage(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $view = new View(dirname(__DIR__) . '/views');
        $logoPath = '/assets/competizioni-judo-logo-optim.svgz';

        // Logo appears in header (site-heading-logo) and in about page (landing-logo)
        $html = $view->render('static/about', $this->layoutData('/about'));

        self::assertSame(2, substr_count($html, 'src="' . $logoPath . '?v='));
        self::assertStringContainsString('class="site-heading-logo"', $html);
        self::assertStringContainsString('class="landing-logo"', $html);
        self::assertSame(2, substr_count($html, 'alt="Competizioni Judo logo"'));
        self::assertFileExists(dirname(__DIR__) . '/public' . $logoPath);
    }

    public function testClubRegistrationRequiresAthleteDataRightsDeclaration(): void
    {
        Localization::setLocale('en');
        $_GET = [];
        $view = new View(dirname(__DIR__) . '/views');

        $html = $view->render('club/register', array_merge([
            'errors' => [],
            'success' => null,
        ], $this->layoutData('/clubs/register')));

        self::assertStringContainsString(
            'name="athlete_data_rights_declaration" value="1" required',
            $html
        );
        self::assertSame(13, substr_count($html, 'class="required-marker"'));
        foreach (
            [
                'contact_first_name',
                'contact_last_name',
                'address_line',
                'postal_code',
                'province',
                'city',
                'affiliation[]',
            ] as $field
        ) {
            self::assertStringContainsString('name="' . $field . '"', $html);
        }
        self::assertStringContainsString(
            e(__('club.register.athlete_data_rights_declaration')),
            $html
        );
        self::assertStringContainsString('value="FIJLKAM"', $html);
        self::assertStringContainsString('href="/privacy"', $html);
    }

    /** @return array<string, mixed> */
    private function layoutData(string $currentPath): array
    {
        return array_merge([
            'appName' => 'Competizioni Judo',
            'locale' => Localization::getLocale(),
            'isLoggedIn' => false,
            'isAdmin' => false,
            'clubEmail' => null,
            'privacyControllerFiscalCode' => 'SYNTHETIC-FISCAL-CODE',
        ], Navigation::context($currentPath, '', false, false));
    }
}
