<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Model\Event;
use App\Presentation\Navigation;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AdminClubAthleteWorkflowTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->createSchema();
        $this->seedData();
        $this->databaseConnection->setValue(null, $this->database);

        $this->destroySession();
        Session::start();
        Session::authenticateAdministrator();
        Localization::setLocale('en');
        $this->view = new View(dirname(__DIR__) . '/views');
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
        $_GET = [];
    }

    public function testClubAdministrationUsesCardsWithAthleteActionsAndCounts(): void
    {
        $request = new Request('GET', '/admin/clubs');

        $response = (new AdminController($this->view, $request))->manageClubs($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('class="admin-card-list"', $response->content());
        self::assertStringNotContainsString('<table', $response->content());
        self::assertStringContainsString('Synthetic Club', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes?club_id=201', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes/export?club_id=201', $response->content());
        self::assertMatchesRegularExpression('/<strong>2<\/strong>\s*athletes in archive/', $response->content());
    }

    public function testAdministratorCanSeeEveryFieldForOnlyTheSelectedClubsAthletes(): void
    {
        $request = new Request('GET', '/admin/clubs/athletes', ['club_id' => '201']);

        $response = (new AdminController($this->view, $request))->clubAthletes($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Athletes — Synthetic Club', $response->content());
        self::assertStringContainsString('Own Athlete', $response->content());
        self::assertStringContainsString('Second Athlete', $response->content());
        self::assertStringContainsString('OWN-001', $response->content());
        self::assertStringContainsString('Private &lt;note&gt;', $response->content());
        self::assertStringContainsString('class="belt-badge__visual"', $response->content());
        self::assertStringContainsString('class="belt-badge__band"', $response->content());
        self::assertStringContainsString('class="belt-badge__knot"', $response->content());
        self::assertStringContainsString('Green / Blue', $response->content());
        self::assertStringNotContainsString('Foreign Athlete', $response->content());
        self::assertStringNotContainsString('<table', $response->content());
    }

    public function testEventAdministrationUsesCardsWithFullyVisibleStatusAndCounts(): void
    {
        $event = new Event(
            101,
            'Synthetic International Tournament',
            '2026-09-12',
            'A venue with a long mobile-visible name',
            'Synthetic Organizer',
            '2026-09-10',
            'only_competitive',
            null,
            null,
            null,
            null,
            true,
            false,
            120
        );
        $html = $this->view->render('admin/manage_events', array_merge([
            'title' => __('admin.events.title'),
            'events' => [$event],
            'entry_counts' => [101 => ['clubs' => 3, 'athletes' => 17]],
            'pagination' => paginate(1, 1, 100),
        ], $this->layoutData('/admin/events')));

        self::assertStringContainsString('class="admin-card-list"', $html);
        self::assertStringNotContainsString('<table', $html);
        self::assertStringContainsString('Synthetic International Tournament', $html);
        self::assertStringContainsString('A venue with a long mobile-visible name', $html);
        self::assertStringContainsString('Competitive', $html);
        self::assertStringContainsString('Visible', $html);
        self::assertStringContainsString('Open', $html);
        self::assertMatchesRegularExpression('/Registered clubs<\/dt>\s*<dd><strong>3<\/strong>/', $html);
        self::assertMatchesRegularExpression('/Registered athletes<\/dt>\s*<dd><strong>17<\/strong>/', $html);
    }

    public function testEveryRemainingTableHasACompleteMobilePresentation(): void
    {
        $root = dirname(__DIR__);
        $css = file_get_contents($root . '/public/assets/css/app.css');
        self::assertIsString($css);
        self::assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.table-scroll--responsive\s*\{[^}]*overflow:\s*visible;/s',
            $css
        );
        self::assertMatchesRegularExpression(
            '/\.responsive-table tbody td::before\s*\{[^}]*content:\s*attr\(data-label\);/s',
            $css
        );
        self::assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.event-info-table td:first-child\s*\{[^}]*white-space:\s*normal;/s',
            $css
        );

        foreach (
            [
                'views/club/area_add.php',
                'views/club/area_list.php',
                'views/events/entries.php',
                'views/events/register.php',
            ] as $template
        ) {
            $source = file_get_contents($root . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString('table-scroll--responsive', $source, $template);
            self::assertStringContainsString('responsive-table', $source, $template);
            self::assertStringContainsString('data-label=', $source, $template);
            self::assertStringContainsString('role="region"', $source, $template);
            self::assertStringContainsString('tabindex="0"', $source, $template);
        }

        foreach (
            [
                'views/events/details.php',
                'views/events/register.php',
            ] as $template
        ) {
            $source = file_get_contents($root . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString('class="event-info-table"', $source, $template);
        }

        $publicClubList = file_get_contents($root . '/views/club/list.php');
        self::assertIsString($publicClubList);
        self::assertStringContainsString('class="public-club-list"', $publicClubList);
        self::assertStringContainsString('class="public-club-card', $publicClubList);
        self::assertStringNotContainsString('<table', $publicClubList);
    }

    public function testAdministratorCanExportOnlyTheSelectedClubsAthletes(): void
    {
        $request = new Request('GET', '/admin/clubs/athletes/export', ['club_id' => '201']);

        $response = (new AdminController($this->view, $request))->exportClubAthletes($request);

        self::assertSame(200, $response->status());
        self::assertSame('text/csv; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString(
            'attachment; filename="athletes-synthetic-club-',
            $response->headers()['Content-Disposition']
        );
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertStringContainsString('Own,Athlete', $response->content());
        self::assertStringContainsString('Second,Athlete', $response->content());
        self::assertStringNotContainsString('Foreign,Athlete', $response->content());
    }

    public function testClubAthleteAdministrationRedirectsAnonymousVisitors(): void
    {
        $this->destroySession();
        $listRequest = new Request('GET', '/admin/clubs/athletes', ['club_id' => '201']);
        $exportRequest = new Request('GET', '/admin/clubs/athletes/export', ['club_id' => '201']);

        $list = (new AdminController($this->view, $listRequest))->clubAthletes($listRequest);
        $export = (new AdminController($this->view, $exportRequest))->exportClubAthletes($exportRequest);

        self::assertSame('/admin/login', $list->headers()['Location']);
        self::assertSame('/admin/login', $export->headers()['Location']);
    }

    private function createSchema(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                address_line TEXT,
                postal_code TEXT,
                city TEXT NOT NULL,
                province TEXT NOT NULL,
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                affiliation TEXT,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                belt TEXT NOT NULL,
                membership_number TEXT,
                notes TEXT
            )'
        );
    }

    private function seedData(): void
    {
        $clubStatement = $this->database->prepare(
            'INSERT INTO clubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $clubStatement->execute([
            201,
            'SYN-201',
            'Synthetic Club',
            'club@example.test',
            '+39 070 123456',
            'Via Roma 1',
            '09100',
            'Cagliari',
            'CA',
            'Synthetic',
            'Contact',
            '["FIJLKAM","CSEN"]',
            'synthetic-hash',
        ]);
        $clubStatement->execute([
            202,
            'SYN-202',
            'Foreign Club',
            'foreign@example.test',
            '',
            null,
            null,
            'Sassari',
            'SS',
            'Foreign',
            'Contact',
            null,
            'foreign-hash',
        ]);

        $athleteStatement = $this->database->prepare(
            'INSERT INTO athletes VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $athleteStatement->execute([
            301,
            201,
            'Own',
            'Athlete',
            'M',
            '2010-01-01',
            50.5,
            'green_blue',
            'OWN-001',
            'Private <note>',
        ]);
        $athleteStatement->execute([
            302,
            201,
            'Second',
            'Athlete',
            'F',
            '2011-02-02',
            48.0,
            'blue',
            null,
            null,
        ]);
        $athleteStatement->execute([
            303,
            202,
            'Foreign',
            'Athlete',
            'F',
            '2012-03-03',
            42.0,
            'yellow',
            'FOREIGN-001',
            'Hidden',
        ]);
    }

    /** @return array<string, mixed> */
    private function layoutData(string $currentPath): array
    {
        return array_merge([
            'appName' => 'Competizioni Judo',
            'locale' => Localization::getLocale(),
            'isLoggedIn' => false,
            'isAdmin' => true,
            'clubEmail' => null,
            'privacyControllerName' => 'Synthetic Controller',
            'privacyControllerAddress' => 'Synthetic Address',
            'privacyControllerFiscalCode' => 'SYNTHETIC-FISCAL-CODE',
        ], Navigation::context($currentPath, '', true, false));
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
