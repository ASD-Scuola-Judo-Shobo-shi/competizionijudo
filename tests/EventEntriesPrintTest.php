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
                poster_file TEXT, info_file TEXT, published INTEGER, closed INTEGER, archived_at TEXT
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

    public function testClosedPdfUsesSnapshotsAndPreservesMissingSnapshotValues(): void
    {
        $this->database->exec(
            "UPDATE events SET closed = 1 WHERE id = 101;
             UPDATE entries SET snapshot_last_name = 'Consolidated', snapshot_first_name = 'Athlete',
                snapshot_gender = 'M', snapshot_birth_date = '2085-02-03', snapshot_weight_kg = NULL,
                snapshot_belt = NULL, snapshot_weight_category = '-48 kg' WHERE id = 401"
        );

        $streams = $this->decodedStreams($this->download()->content());

        $this->assertPdfContains('Consolidated', $streams);
        $this->assertPdfContains('-48 kg', $streams);
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

    public function testAdministratorCanDownloadOneClubsCompleteEventRoster(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $this->queries = [];

        $response = $this->adminDownload([
            'club_id' => '202',
            'enrollment_page' => '99',
            'enrollment_filter_athlete' => 'NoMatch',
        ]);

        self::assertSame(200, $response->status());
        self::assertSame('application/pdf', $response->headers()['Content-Type']);
        self::assertSame('attachment; filename="club-202-athletes-event-101.pdf"', $response->headers()['Content-Disposition']);
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertStringStartsWith('%PDF-', $response->content());
        self::assertCount(2, $this->queries);
        $streams = $this->decodedStreams($response->content());
        foreach (['ForeignFamily', 'ForeignGiven', 'Foreign Club', 'SYN-202', 'Synthetic Tournament'] as $text) {
            $this->assertPdfContains($text, $streams);
        }
        foreach (['OwnFamily', 'AnotherEvent', 'Synthetic Club', 'FOREIGN-MEMBER'] as $text) {
            $this->assertPdfOmits($text, $streams);
        }
    }

    /** @return iterable<string, array{int}> */
    public static function adminClubCounts(): iterable
    {
        yield 'two clubs' => [2];
        yield 'twelve clubs' => [12];
    }

    #[DataProvider('adminClubCounts')]
    public function testAdministratorCanDownloadAllClubsWithSeparatePagesAndTwoQueries(int $clubCount): void
    {
        for ($index = 3; $index <= $clubCount; $index++) {
            $id = 500 + $index;
            $this->database->exec(
                "INSERT INTO clubs VALUES ($id, 'Paged Club $index', 'SYN-$id');
                 INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES ($id, 101, $id, 301)"
            );
        }
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $this->queries = [];

        $response = $this->adminDownload(['enrollment_page' => '99']);

        self::assertSame(200, $response->status());
        self::assertSame('attachment; filename="all-clubs-athletes-event-101.pdf"', $response->headers()['Content-Disposition']);
        self::assertCount(2, $this->queries);
        $streams = $this->decodedStreams($response->content());
        foreach (['ForeignFamily', 'OwnFamily', 'Foreign Club', 'Synthetic Club', 'SYN-201', 'SYN-202'] as $text) {
            $this->assertPdfContains($text, $streams);
        }
        $this->assertPdfOmits('AnotherEvent', $streams);
        preg_match_all('~/Type\s*/Page\b~', $response->content(), $pages);
        self::assertCount($clubCount, $pages[0]);
        self::assertSame(
            $clubCount,
            substr_count($streams, mb_convert_encoding('Synthetic Tournament', 'UTF-16BE', 'UTF-8'))
        );
        $foreignPosition = strpos($streams, mb_convert_encoding('Foreign Club', 'UTF-16BE', 'UTF-8'));
        $ownPosition = strpos($streams, mb_convert_encoding('Synthetic Club', 'UTF-16BE', 'UTF-8'));
        self::assertIsInt($foreignPosition);
        self::assertIsInt($ownPosition);
        self::assertLessThan($ownPosition, $foreignPosition);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function adminRosterScopes(): iterable
    {
        yield 'one club' => [['club_id' => '201']];
        yield 'all clubs' => [[]];
    }

    #[DataProvider('adminRosterScopes')]
    public function testAdministratorPdfIncludesAllAthletesAcrossPagesWithRepeatedHeaders(array $query): void
    {
        for ($index = 1; $index <= 75; $index++) {
            $id = 500 + $index;
            $this->database->exec(
                "INSERT INTO athletes (id, last_name, first_name, gender, birth_date, weight_kg, belt)
                    VALUES ($id, 'Paged$index', 'Athlete', 'F', '2087-01-01', 39.5, 'green');
                 INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES ($id, 101, 201, $id)"
            );
        }
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $this->queries = [];

        $response = $this->adminDownload($query + ['enrollment_page' => '99']);

        self::assertSame(200, $response->status());
        self::assertCount(2, $this->queries);
        $streams = $this->decodedStreams($response->content());
        $this->assertPdfContains('OwnFamily', $streams);
        $this->assertPdfContains('Paged75', $streams);
        preg_match_all('~/Type\s*/Page\b~', $response->content(), $pages);
        self::assertGreaterThan(1, count($pages[0]));
        self::assertSame(
            count($pages[0]),
            substr_count($streams, mb_convert_encoding(__('club.area.table.weight_category'), 'UTF-16BE', 'UTF-8'))
        );
    }

    #[DataProvider('adminRosterScopes')]
    public function testAdministratorCanPrintUnpublishedClosedEventsUsingOnlySavedData(array $query): void
    {
        $this->database->exec(
            "UPDATE events SET published = 0, closed = 1 WHERE id = 101;
             UPDATE entries SET snapshot_last_name = 'Consolidated', snapshot_first_name = 'Athlete',
                snapshot_birth_date = '2085-02-03', snapshot_weight_category = '-48 kg' WHERE id = 401"
        );
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $response = $this->adminDownload($query);

        self::assertSame(200, $response->status());
        $streams = $this->decodedStreams($response->content());
        $this->assertPdfContains('Consolidated', $streams);
        $this->assertPdfContains('-48 kg', $streams);
        foreach (['OwnFamily', '39.5 kg', 'Green'] as $text) {
            $this->assertPdfOmits($text, $streams);
        }
    }

    public function testAdministratorCanDownloadAnEmptyEventPdfInItalian(): void
    {
        $this->database->exec('DELETE FROM entries WHERE event_id = 101');
        Localization::setLocale('it');
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $response = $this->adminDownload();

        self::assertSame(200, $response->status());
        $streams = $this->decodedStreams($response->content());
        $this->assertPdfContains('Synthetic Tournament', $streams);
        $this->assertPdfContains(__('admin.event_details.no_enrolled_athletes'), $streams);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidAdminDownloads(): iterable
    {
        yield 'missing event' => [['event_id' => '0'], '/admin/events'];
        yield 'unknown event' => [['event_id' => '999'], '/admin/events'];
        yield 'unknown club' => [['club_id' => '999'], '/admin/events/details?event_id=101'];
        yield 'club not enrolled in event' => [['event_id' => '102', 'club_id' => '202'], '/admin/events/details?event_id=102'];
        yield 'empty club' => [['club_id' => ''], '/admin/events/details?event_id=101'];
        yield 'invalid club' => [['club_id' => 'invalid'], '/admin/events/details?event_id=101'];
        yield 'negative club' => [['club_id' => '-1'], '/admin/events/details?event_id=101'];
        yield 'array club' => [['club_id' => ['201']], '/admin/events/details?event_id=101'];
    }

    #[DataProvider('invalidAdminDownloads')]
    public function testInvalidAdminDownloadsRedirectWithoutGeneratingAPdf(array $query, string $location): void
    {
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $response = $this->adminDownload($query);

        self::assertSame(302, $response->status());
        self::assertSame($location, $response->headers()['Location']);
        self::assertSame('', $response->content());
    }

    /** @return iterable<string, array{bool}> */
    public static function nonAdminViewers(): iterable
    {
        yield 'anonymous' => [false];
        yield 'club' => [true];
    }

    #[DataProvider('nonAdminViewers')]
    public function testOnlyAdministratorsCanDownloadViaAdminRouteOrController(bool $club): void
    {
        Session::invalidateAuthentication();
        if ($club) {
            Session::authenticateClub(201, hash('sha256', 'test-club-credential'));
        }
        $this->queries = [];
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);
        $request = new Request('GET', '/admin/events/entries/print', ['event_id' => '101']);
        $controllerResponse = (new EventEntriesPrintController($this->view, $request))->adminDownload($request);

        foreach ([$router->dispatch($request), $controllerResponse] as $response) {
            self::assertSame(302, $response->status());
            self::assertSame('/admin/login', $response->headers()['Location']);
        }
        self::assertSame([], $this->queries);
    }

    /** @return iterable<string, array{string, string}> */
    public static function pdfUiLanguages(): iterable
    {
        foreach (['it', 'en'] as $locale) {
            foreach (['club', 'admin-club', 'admin-all'] as $scope) {
                yield $locale . ' ' . $scope => [$locale, $scope];
            }
        }
    }

    #[DataProvider('pdfUiLanguages')]
    public function testPdfUsesOnlyTheCurrentUiLanguage(string $locale, string $scope): void
    {
        Localization::setLocale($locale);
        if ($scope !== 'club') {
            Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        }

        $response = match ($scope) {
            'admin-club' => $this->adminDownload(['club_id' => '201']),
            'admin-all' => $this->adminDownload(),
            default => $this->download(),
        };

        self::assertSame(200, $response->status());
        $streams = $this->decodedStreams($response->content());
        $otherLocale = $locale === 'en' ? 'it' : 'en';
        foreach (
            [
                'events.entries_pdf_title',
                'events.location',
                'club.area.table.athlete',
                'club.area.table.weight_category',
                'club.area.table.belt',
                'gender.F',
                'belt.green',
            ] as $key
        ) {
            $this->assertPdfContains(Localization::transFor($locale, $key), $streams);
            $this->assertPdfOmits(Localization::transFor($otherLocale, $key), $streams);
        }
        $this->assertPdfContains($locale === 'en' ? 'Pre-Cadets A' : 'Esordienti A', $streams);
        $this->assertPdfOmits($locale === 'en' ? 'Esordienti A' : 'Pre-Cadets A', $streams);
        $page = ['page' => '1', 'pages' => $scope === 'admin-all' ? '2' : '1'];
        $this->assertPdfContains(Localization::transFor($locale, 'events.entries_pdf_page', $page), $streams);
        $this->assertPdfOmits(Localization::transFor($otherLocale, 'events.entries_pdf_page', $page), $streams);
    }

    /** @return iterable<string, array{string, bool, string}> */
    public static function pdfStreamPayloads(): iterable
    {
        // The zlib checksum for a form-feed byte ends in a carriage return.
        yield 'compressed trailing carriage return before LF' => ["\f", true, "\n"];
        yield 'compressed trailing carriage return before CRLF' => ["\f", true, "\r\n"];
        yield 'compressed trailing line feed' => ["\t", true, "\n"];
        yield 'uncompressed trailing carriage return' => ["Synthetic text\r", false, "\n"];
        yield 'uncompressed trailing line feed' => ["Synthetic text\n", false, "\r\n"];
        yield 'literal stream terminator' => ["Synthetic\nendstream\nremaining text", false, "\n"];
        yield 'literal stream header' => ["Synthetic\n<< /Length 1 >>\nstream\nX\nendstream", false, "\n"];
        yield 'binary compressed content' => ["Synthetic\0\r\n\xff", true, "\r\n"];
    }

    #[DataProvider('pdfStreamPayloads')]
    public function testPdfStreamDecoderPreservesDeclaredBytes(string $payload, bool $compress, string $lineEnding): void
    {
        $encoded = $compress ? gzcompress($payload) : $payload;
        self::assertIsString($encoded);
        $dictionary = '<< /Length ' . strlen($encoded) . ($compress ? ' /Filter /FlateDecode' : '') . ' >>';
        $pdf = "%PDF-1.7\n1 0 obj\n" . $dictionary . $lineEnding
            . 'stream' . $lineEnding . $encoded . $lineEnding . 'endstream' . $lineEnding . 'endobj';

        self::assertSame($payload, $this->decodedStreams($pdf));
    }

    /** @param array<string, mixed> $query */
    private function adminDownload(array $query = []): Response
    {
        $request = new Request('GET', '/admin/events/entries/print', $query + ['event_id' => '101']);
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);

        return $router->dispatch($request);
    }

    /** @param array<string, string> $query */
    private function download(array $query = []): Response
    {
        $request = new Request('GET', '/events/entries/print', $query + ['event' => '101']);

        return (new EventEntriesPrintController($this->view, $request))->download($request);
    }

    /** Read the renderer's unencrypted streams using their declared byte lengths. */
    private function decodedStreams(string $pdf): string
    {
        $content = '';
        $offset = 0;
        $streamCount = 0;
        while (preg_match('~<<(.*?)>>\s*stream(?:\r\n|\n|\r)~s', $pdf, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $dictionary = $match[1][0];
            self::assertSame(1, preg_match('~/Length\s+(\d+)\b~', $dictionary, $length));
            $start = $match[0][1] + strlen($match[0][0]);
            $bytes = substr($pdf, $start, (int) $length[1]);
            self::assertSame((int) $length[1], strlen($bytes));
            $offset = $start + strlen($bytes);
            self::assertSame(1, preg_match('~\G(?:\r\n|\n|\r)endstream\b~', $pdf, $end, 0, $offset));
            $offset += strlen($end[0]);

            $stream = str_contains($dictionary, '/FlateDecode') ? gzuncompress($bytes) : $bytes;
            self::assertIsString($stream);
            $content .= $stream;
            $streamCount++;
        }
        self::assertGreaterThan(0, $streamCount);

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
