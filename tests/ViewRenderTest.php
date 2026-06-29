<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Localization;
use App\Model\Event;
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
            'currentPath' => '/admin_add_event.php',
            'event' => null,
            'error' => '',
            'locations' => [],
        ], $this->layoutData('/admin_add_event.php')));

        self::assertStringNotContainsString('</parameter>', $html);
        self::assertStringNotContainsString('</write_to_file>', $html);
        self::assertMatchesRegularExpression(
            '/<form method="post" class="form-card" enctype="multipart\/form-data">.*<\/form>/s',
            $html
        );
        self::assertSame(substr_count($html, '<form'), substr_count($html, '</form>'));
    }

    public function testEventLinksDistinguishPublicDetailsFromAuthorizedEntries(): void
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
            false
        );
        $view = new View(dirname(__DIR__) . '/views');

        $authorized = $view->render('events/index', array_merge([
            'events' => [$event],
            'canViewEntries' => true,
        ], $this->layoutData('/events.php')));
        $anonymous = $view->render('events/index', array_merge([
            'events' => [$event],
            'canViewEntries' => false,
        ], $this->layoutData('/events.php')));

        self::assertStringContainsString('/event_details.php?event=101', $authorized);
        self::assertStringContainsString('/event_entries.php?event=101', $authorized);
        self::assertStringContainsString('>Details</a>', $authorized);
        self::assertStringContainsString('>Entries</a>', $authorized);
        self::assertStringNotContainsString('/event_entries.php?event=101', $anonymous);
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
        ], Navigation::context($currentPath, '', false, false));
    }
}
