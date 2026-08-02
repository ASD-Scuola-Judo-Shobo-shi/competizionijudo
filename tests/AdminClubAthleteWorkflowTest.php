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

    public function testClubAdministrationUsesAResponsiveTableWithActionsAndCounts(): void
    {
        $request = new Request('GET', '/admin/clubs');

        $response = (new AdminController($this->view, $request))->manageClubs($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            'class="table-full responsive-table admin-list-table"',
            $response->content()
        );
        self::assertStringContainsString('class="table-actions admin-table-actions"', $response->content());
        self::assertStringContainsString('Synthetic Club', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes?club_id=201', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes/export?club_id=201', $response->content());
        self::assertStringContainsString('action="/admin/clubs/update-inline"', $response->content());
        self::assertStringContainsString('data-inline-edit', $response->content());
        self::assertStringContainsString('<th scope="col">Code</th>', $response->content());
        self::assertStringContainsString('data-label="Federal code"', $response->content());
        self::assertMatchesRegularExpression('/title="2 athletes in archive">\s*2\s*<\/strong>/', $response->content());
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
        self::assertStringContainsString(
            'class="table-full responsive-table admin-list-table"',
            $response->content()
        );
        self::assertStringContainsString('data-label="Notes"', $response->content());
    }

    public function testEventAdministrationUsesAResponsiveTableWithStatusAndCounts(): void
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

        self::assertStringContainsString('class="table-full responsive-table admin-list-table"', $html);
        self::assertStringContainsString('class="table-actions admin-table-actions"', $html);
        self::assertStringContainsString('action="/admin/events/update-inline"', $html);
        self::assertStringContainsString('<th scope="col">Show</th>', $html);
        self::assertStringContainsString('data-label="Visibility"', $html);
        self::assertStringContainsString('👁️', $html);
        self::assertStringContainsString('Synthetic International Tournament', $html);
        self::assertStringContainsString('A venue with a long mobile-visible name', $html);
        self::assertStringContainsString('Competitive', $html);
        self::assertStringContainsString('Visible', $html);
        self::assertStringContainsString('Open', $html);
        self::assertMatchesRegularExpression('/data-label="Registered clubs">\s*<strong>3<\/strong>/', $html);
        self::assertMatchesRegularExpression('/data-label="Registered athletes">\s*<strong>17<\/strong>/', $html);
    }

    public function testAdministratorCanUpdateClubSummaryFieldsInline(): void
    {
        $request = new Request('POST', '/admin/clubs/update-inline', [], [
            'csrf_token' => csrf_token(),
            'club_id' => '201',
            'page' => '1',
            'name' => 'Updated Synthetic Club',
            'federal_code' => 'UPDATED-201',
            'email' => 'UPDATED@example.test',
            'phone' => '+39 070 7654321',
            'contact_first_name' => 'Updated',
            'contact_last_name' => 'Contact',
        ]);

        $response = (new AdminController($this->view, $request))->updateClubInline($request);
        $club = $this->database->query('SELECT * FROM clubs WHERE id = 201')->fetch();

        self::assertSame(303, $response->status());
        self::assertSame('/admin/clubs?page=1#club-row-201', $response->headers()['Location']);
        self::assertIsArray($club);
        self::assertSame('Updated Synthetic Club', $club['name']);
        self::assertSame('updated@example.test', $club['email']);
        self::assertSame('Via Roma 1', $club['address_line']);
        self::assertSame('["FIJLKAM","CSEN"]', $club['affiliation']);
    }

    public function testAdministratorCanUpdateEventSummaryFieldsInline(): void
    {
        $request = new Request('POST', '/admin/events/update-inline', [], [
            'csrf_token' => csrf_token(),
            'event_id' => '101',
            'page' => '2',
            'name' => 'Updated Tournament',
            'date' => '2026-09-13',
            'location' => 'Updated Venue',
            'type' => 'precompetitive_and_competitive',
            'max_participants' => '150',
            'published' => '0',
            'closed' => '0',
        ]);

        $response = (new AdminController($this->view, $request))->updateEventInline($request);
        $event = $this->database->query('SELECT * FROM events WHERE id = 101')->fetch();

        self::assertSame(303, $response->status());
        self::assertSame('/admin/events?page=2#event-row-101', $response->headers()['Location']);
        self::assertIsArray($event);
        self::assertSame('Updated Tournament', $event['name']);
        self::assertSame('Updated Venue', $event['location']);
        self::assertSame('precompetitive_and_competitive', $event['type']);
        self::assertSame(150, $event['max_participants']);
        self::assertSame(0, $event['published']);
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
        self::assertStringContainsString('.table-scroll--responsive.is-card-view', $css);
        self::assertStringContainsString('.is-card-view .responsive-table tbody td::before', $css);
        self::assertStringContainsString('.table-density-value', $css);
        self::assertStringContainsString('.card-density-value', $css);
        self::assertStringContainsString('.table-action-label', $css);
        self::assertMatchesRegularExpression(
            '/\.is-card-view \.responsive-table \.table-action-label\s*\{[^}]*display:\s*inline;/s',
            $css
        );
        self::assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.responsive-table \.table-action-label\s*\{[^}]*display:\s*inline;/s',
            $css
        );

        $layout = file_get_contents($root . '/views/layouts/app.php');
        self::assertIsString($layout);
        self::assertStringContainsString('competizioni-judo-table-view', $layout);
        self::assertStringContainsString("querySelectorAll('.table-scroll--responsive')", $layout);
        self::assertStringContainsString("toolbar.className = 'table-view-toolbar'", $layout);

        foreach (
            [
                'views/admin/club_athletes.php',
                'views/admin/manage_clubs.php',
                'views/admin/manage_events.php',
                'views/club/area_add.php',
                'views/club/area_list.php',
                'views/club/list.php',
                'views/events/_entries_athletes.php',
                'views/events/_entries_clubs.php',
                'views/events/_entries_current_club.php',
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
                'views/admin/manage_clubs.php' => '/admin/clubs/update-inline',
                'views/admin/manage_events.php' => '/admin/events/update-inline',
                'views/club/area_add.php' => '/clubs/athletes/update-inline',
                'views/club/area_list.php' => '/clubs/athletes/update-inline',
            ] as $template => $endpoint
        ) {
            $source = file_get_contents($root . '/' . $template);
            self::assertIsString($source);
            self::assertStringContainsString('data-inline-edit', $source, $template);
            self::assertStringContainsString($endpoint, $source, $template);
            self::assertStringContainsString('class="table-action-label"', $source, $template);
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
            );
            CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT NOT NULL,
                organizer TEXT NOT NULL,
                registration_deadline TEXT,
                type TEXT NOT NULL,
                description TEXT,
                notes TEXT,
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL,
                closed INTEGER NOT NULL,
                max_participants INTEGER,
                sepa_account_holder TEXT,
                sepa_iban TEXT,
                sepa_bic TEXT
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

        $this->database->prepare(
            'INSERT INTO events VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            101,
            'Synthetic Tournament',
            '2026-09-12',
            'Synthetic Venue',
            'Synthetic Organizer',
            '2026-09-10',
            'only_competitive',
            null,
            null,
            null,
            null,
            1,
            0,
            120,
            null,
            null,
            null,
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
            'privacyControllerEmail' => 'privacy@example.test',
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
