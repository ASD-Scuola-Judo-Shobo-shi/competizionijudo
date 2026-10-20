<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\EventEntriesPrintController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EventEntriesPrintTest extends TestCase
{
    private ReflectionProperty $connection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;
    private string $originalLocale;
    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $this->originalConnection = $this->connection->getValue();
        $this->database = new class ($this->queries) extends PDO {
            /** @param list<string> $queries */
            public function __construct(private array &$queries)
            {
                parent::__construct('sqlite::memory:', null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }

            /** @param array<int, mixed> $options */
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->queries[] = $query;

                return parent::prepare($query, $options);
            }
        };
        $this->connection->setValue(null, $this->database);
        $this->database->exec(
            "CREATE TABLE clubs (id INTEGER PRIMARY KEY, name TEXT, federal_code TEXT);
             CREATE TABLE events (
                id INTEGER PRIMARY KEY, name TEXT, date TEXT, location TEXT, organizer TEXT,
                registration_deadline TEXT, type TEXT, description TEXT, notes TEXT,
                poster_file TEXT, info_file TEXT, published INTEGER, closed INTEGER
             );
             CREATE TABLE event_registration_exceptions (event_id INTEGER, club_id INTEGER);
             CREATE TABLE athletes (
                id INTEGER PRIMARY KEY, last_name TEXT, first_name TEXT, gender TEXT,
                birth_date TEXT, weight_kg REAL, belt TEXT, membership_number TEXT
             );
             CREATE TABLE entries (
                id INTEGER PRIMARY KEY, event_id INTEGER, club_id INTEGER, athlete_id INTEGER,
                snapshot_last_name TEXT, snapshot_first_name TEXT, snapshot_gender TEXT,
                snapshot_birth_date TEXT, snapshot_weight_kg REAL, snapshot_belt TEXT,
                snapshot_membership_number TEXT, snapshot_program TEXT, snapshot_weight_category TEXT,
                snapshot_at TEXT
             );
             INSERT INTO clubs VALUES (201, 'Synthetic Club', 'SYN-201'), (202, 'Foreign Club', 'SYN-202');
             INSERT INTO events (id, name, date, location, type, published, closed)
                VALUES (101, 'Synthetic Tournament', '2099-07-01', 'Synthetic Venue', 'only_competitive', 1, 0),
                       (102, 'Another Tournament', '2099-07-02', 'Another Venue', 'only_competitive', 1, 0);
             INSERT INTO athletes VALUES
                (301, 'OwnFamily', 'OwnGiven', 'F', '2087-01-01', 39.5, 'green', 'PRIVATE-MEMBER'),
                (302, 'ForeignFamily', 'ForeignGiven', 'M', '2087-01-01', 42, 'blue', 'FOREIGN-MEMBER'),
                (303, 'AnotherEvent', 'OwnAthlete', 'M', '2087-01-01', 42, 'blue', 'OTHER-MEMBER');
             INSERT INTO entries (id, event_id, club_id, athlete_id)
                VALUES (401, 101, 201, 301), (402, 101, 202, 302), (403, 102, 201, 303)"
        );
        $this->view = new View(dirname(__DIR__) . '/views');
        $this->originalLocale = Localization::getLocale();
        Localization::setLocale('en');
        Session::destroy();
        Session::start();
        Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
    }

    protected function tearDown(): void
    {
        $this->connection->setValue(null, $this->originalConnection);
        Localization::setLocale($this->originalLocale);
        Session::destroy();
    }

    public function testPdfContainsOnlyTheSignedInClubsEventRosterDespiteForgedParameters(): void
    {
        $response = $this->download([
            'club_id' => '202',
            'athlete_id' => '302',
            'club_entries_filter_athlete' => 'NoMatch',
            'weight_category' => 'NoMatch',
            'club_entries_page' => '99',
        ]);

        self::assertSame(200, $response->status());
        self::assertSame('application/pdf', $response->headers()['Content-Type']);
        self::assertSame('attachment; filename="club-athletes-event-101.pdf"', $response->headers()['Content-Disposition']);
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertStringStartsWith('%PDF-', $response->content());
        $streams = $this->decodedStreams($response->content());
        foreach (['OwnFamily', 'OwnGiven', 'Synthetic Tournament', 'Synthetic Club', 'SYN-201', '39.5 kg'] as $text) {
            $this->assertPdfContains($text, $streams);
        }
        foreach (['ForeignFamily', 'AnotherEvent', 'PRIVATE-MEMBER', '2087-01-01'] as $text) {
            $this->assertPdfOmits($text, $streams);
        }
    }

    public function testClosedPdfUsesSnapshotValues(): void
    {
        $this->database->exec(
            "UPDATE events SET closed = 1 WHERE id = 101;
             UPDATE entries SET snapshot_last_name = 'Consolidated', snapshot_first_name = 'Athlete',
                snapshot_gender = 'M', snapshot_birth_date = '2085-02-03', snapshot_weight_kg = 43.5,
                snapshot_belt = 'blue', snapshot_weight_category = '-48 kg' WHERE id = 401"
        );

        $streams = $this->decodedStreams($this->download()->content());

        $this->assertPdfContains('Consolidated', $streams);
        $this->assertPdfContains('-48 kg', $streams);
        $this->assertPdfContains('43.5 kg', $streams);
        $this->assertPdfContains('Blue', $streams);
        $this->assertPdfOmits('OwnFamily', $streams);
        $this->assertPdfOmits('39.5 kg', $streams);
        $this->assertPdfOmits('Green', $streams);
    }

    /** @return iterable<string, array{int}> */
    public static function rosterSizes(): iterable
    {
        yield 'one athlete' => [1];
        yield 'seventy-five athletes' => [75];
    }

    #[DataProvider('rosterSizes')]
    public function testCompleteRosterUsesThreeQueriesAndRepeatsHeadersOnEachPage(int $size): void
    {
        for ($index = 2; $index <= $size; $index++) {
            $id = 500 + $index;
            $this->database->exec(
                "INSERT INTO athletes (id, last_name, first_name, gender, birth_date, weight_kg, belt)
                    VALUES ($id, 'Paged$index', 'Athlete', 'F', '2087-01-01', 39.5, 'green');
                 INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES ($id, 101, 201, $id)"
            );
        }
        $this->queries = [];

        $response = $this->download();

        self::assertSame(200, $response->status());
        self::assertCount(3, $this->queries);
        $streams = $this->decodedStreams($response->content());
        $this->assertPdfContains('OwnFamily', $streams);
        if ($size > 1) {
            $this->assertPdfContains('Paged75', $streams);
            preg_match_all('~/Type\s*/Page\b~', $response->content(), $pages);
            self::assertGreaterThan(1, count($pages[0]));
            self::assertSame(
                count($pages[0]),
                substr_count($streams, mb_convert_encoding(__('club.area.table.weight_category'), 'UTF-16BE', 'UTF-8'))
            );
        }
    }

    public function testEmptyRosterProducesALocalizedPdf(): void
    {
        $this->database->exec('DELETE FROM entries WHERE club_id = 201 AND event_id = 101');
        Localization::setLocale('it');

        $response = $this->download();

        self::assertSame(200, $response->status());
        $streams = $this->decodedStreams($response->content());
        $this->assertPdfContains(__('events.entries_pdf_title'), $streams);
        $this->assertPdfContains(__('club.area.no_entries'), $streams);
    }

    public function testUnicodeAndHtmlMetacharactersAreRenderedAsText(): void
    {
        $this->database->exec("UPDATE athletes SET last_name = 'D’Àlfa<&>', first_name = 'Éva' WHERE id = 301");

        $streams = $this->decodedStreams($this->download()->content());

        $this->assertPdfContains('D’Àlfa<&>', $streams);
        $this->assertPdfContains('Éva', $streams);
    }

    /** @return iterable<string, array{string}> */
    public static function inaccessibleEvents(): iterable
    {
        yield 'missing parameter' => ['0'];
        yield 'unknown event' => ['999'];
        yield 'unpublished event' => ['101'];
        yield 'closed event without own entries' => ['102'];
    }

    #[DataProvider('inaccessibleEvents')]
    public function testInaccessibleEventsRedirectWithoutGeneratingAPdf(string $event): void
    {
        $this->database->exec(
            'UPDATE events SET published = 0 WHERE id = 101;
             UPDATE events SET closed = 1 WHERE id = 102;
             DELETE FROM entries WHERE event_id = 102'
        );

        $response = $this->download(['event' => $event]);

        self::assertSame(302, $response->status());
        self::assertSame('/events', $response->headers()['Location']);
        self::assertSame('', $response->content());
    }

    /** @return iterable<string, array{bool}> */
    public static function nonClubViewers(): iterable
    {
        yield 'anonymous' => [false];
        yield 'administrator' => [true];
    }

    #[DataProvider('nonClubViewers')]
    public function testNonClubViewersCannotDownloadThroughTheRouteOrController(bool $administrator): void
    {
        Session::invalidateAuthentication();
        if ($administrator) {
            Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        }
        $this->queries = [];
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);

        $routed = $router->dispatch(new Request('GET', '/events/entries/print', ['event' => '101']));
        foreach ([$routed, $this->download()] as $response) {
            self::assertSame(302, $response->status());
            self::assertSame('/clubs/login', $response->headers()['Location']);
        }
        self::assertSame([], $this->queries);
    }

    public function testDeletedClubCannotDownload(): void
    {
        $this->database->exec('DELETE FROM clubs WHERE id = 201');

        $response = $this->download();

        self::assertSame(302, $response->status());
        self::assertSame('/clubs/login', $response->headers()['Location']);
    }

    /** @param array<string, string> $query */
    private function download(array $query = []): Response
    {
        $request = new Request('GET', '/events/entries/print', $query + ['event' => '101']);

        return (new EventEntriesPrintController($this->view, $request))->download($request);
    }

    /** Read the renderer's unencrypted content streams without an external PDF tool. */
    private function decodedStreams(string $pdf): string
    {
        preg_match_all('~<<(.*?)>>\s*stream\r?\n(.*?)\r?\nendstream~s', $pdf, $matches, PREG_SET_ORDER);
        self::assertNotEmpty($matches);
        $content = '';
        foreach ($matches as $match) {
            $stream = str_contains($match[1], '/FlateDecode') ? gzuncompress($match[2]) : $match[2];
            self::assertIsString($stream);
            $content .= $stream;
        }

        return $content;
    }

    private function assertPdfContains(string $text, string $streams): void
    {
        self::assertTrue(str_contains($streams, mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), $text);
    }

    private function assertPdfOmits(string $text, string $streams): void
    {
        self::assertFalse(str_contains($streams, mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), $text);
    }
}
