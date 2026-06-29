<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Localization;
use App\Model\Event;
use PHPUnit\Framework\TestCase;

final class ViewRenderTest extends TestCase
{
    public function testAddEventFormRendersWithoutEditorArtifacts(): void
    {
        Localization::setLocale('it');
        $_GET = [];

        $view = new View(dirname(__DIR__) . '/views');
        $html = $view->render('admin/add_event', [
            'currentPath' => '/admin_add_event.php',
            'event' => null,
            'error' => '',
            'locations' => [],
        ]);

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

        $authorized = $view->render('events/index', [
            'currentPath' => '/events.php',
            'events' => [$event],
            'canViewEntries' => true,
        ]);
        $anonymous = $view->render('events/index', [
            'currentPath' => '/events.php',
            'events' => [$event],
            'canViewEntries' => false,
        ]);

        self::assertStringContainsString('/event_details.php?event=101', $authorized);
        self::assertStringContainsString('/event_entries.php?event=101', $authorized);
        self::assertStringContainsString('>Details</a>', $authorized);
        self::assertStringContainsString('>Entries</a>', $authorized);
        self::assertStringNotContainsString('/event_entries.php?event=101', $anonymous);
    }
}
