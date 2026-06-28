<?php

declare(strict_types=1);

namespace Tests;

use App\Core\View;
use App\Localization;
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
}
