<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\EventController;
use App\Core\Application;
use App\Core\FileLogger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use App\Model\EntryRegistrationRepository;
use App\Model\EntryRegistrationResult;
use App\Model\EventRegistrationException;
use App\Model\Event;
use App\Security\CredentialFingerprint;
use App\Service\EventEntriesCsvTransfer;
use App\Service\EventRegistrationSelection;
use App\Service\EventUploadStorage;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\FakePasswordResetMailer;

final class EventLifecycleTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;
    private ?string $logPath = null;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchemaAndActors();
        $this->databaseConnection->setValue(null, $this->database);

        $this->startCleanSession();
        Localization::setLocale('it');
        $this->view = new View(dirname(__DIR__) . '/views');
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        if ($this->logPath !== null && is_file($this->logPath)) {
            unlink($this->logPath);
        }
        $this->destroySession();
    }

    public function testPublicIdLookupDoesNotReturnAnUnpublishedEvent(): void
    {
        $this->insertEvent(published: false, description: 'UNPUBLISHED-EVENT-DATA');

        self::assertNull(Event::findPublishedById(101));

        $request = new Request('GET', '/events/details?event=101', ['event' => '101']);
        $response = (new EventController($this->view, $request))->details($request);

        self::assertSame(302, $response->status());
        self::assertStringNotContainsString('UNPUBLISHED-EVENT-DATA', $response->content());
    }

    public function testPublicDetailsShowEveryRegistrationFeeAndTheDefaultOption(): void
    {
        $this->insertEvent();
        $this->insertAdditionalEvent(102, 'Future Public Event');
        $this->database->exec(
            "INSERT INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (502, 101, 'Premium', 2500, 0, 1, 1)"
        );

        $request = new Request('GET', '/events/details?event=101', ['event' => '101']);
        $response = (new EventController($this->view, $request))->details($request);
        $plainText = preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($response->content()), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );

        self::assertIsString($plainText);
        self::assertStringContainsString(__('events.registration_fees'), $plainText);
        self::assertStringContainsString('Standard €15.00', $plainText);
        self::assertStringContainsString('Premium €25.00', $plainText);
        self::assertStringContainsString(__('events.registration_option_default'), $plainText);
        self::assertStringContainsString('Future Public Event', $plainText);
        self::assertStringContainsString('href="/events/details?event=102"', $response->content());
    }

    public function testPosterEndpointRequiresAuthenticationAndStreamsThePublishedPoster(): void
    {
        $this->insertEvent();
        $application = new Application(dirname(__DIR__));
        (require dirname(__DIR__) . '/routes/web.php')($application->router());

        $anonymous = $application->handle(new Request(
            'GET',
            '/events/poster?event=101',
            ['event' => '101']
        ));
        $anonymousAdmin = $application->handle(new Request(
            'GET',
            '/admin/events/poster?event=101',
            ['event' => '101']
        ));

        self::assertSame(302, $anonymous->status());
        self::assertSame('/clubs/login', $anonymous->headers()['Location']);
        self::assertSame(302, $anonymousAdmin->status());
        self::assertSame('/admin/login', $anonymousAdmin->headers()['Location']);

        $publicRoot = sys_get_temp_dir() . '/competizionijudo-poster-' . bin2hex(random_bytes(8));
        mkdir($publicRoot . '/uploads/events', 0755, true);
        $poster = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        self::assertIsString($poster);
        file_put_contents($publicRoot . '/uploads/events/poster_synthetic.png', $poster);

        try {
            Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
            $request = new Request('GET', '/events/poster?event=101', ['event' => '101']);
            $response = (new EventController(
                $this->view,
                $request,
                null,
                null,
                new EventUploadStorage($publicRoot)
            ))->poster($request);

            self::assertSame(200, $response->status());
            self::assertSame($poster, $response->content());
            self::assertSame('image/png', $response->headers()['Content-Type']);
            self::assertSame(
                'inline; filename="poster_synthetic.png"',
                $response->headers()['Content-Disposition']
            );
            self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        } finally {
            unlink($publicRoot . '/uploads/events/poster_synthetic.png');
            rmdir($publicRoot . '/uploads/events');
            rmdir($publicRoot . '/uploads');
            rmdir($publicRoot);
        }
    }

    /**
     * @return iterable<string, array{bool, bool, string, string|null, bool}>
     */
    public static function registrationLifecycleCases(): iterable
    {
        yield 'deadline equal and event today' => [true, false, '2026-06-28', '2026-06-28', true];
        yield 'unpublished' => [false, false, '2026-06-29', '2026-06-28', false];
        yield 'closed' => [true, true, '2026-06-29', '2026-06-28', false];
        yield 'deadline past' => [true, false, '2026-06-29', '2026-06-27', false];
        yield 'event past without deadline' => [true, false, '2026-06-27', null, false];
    }

    #[DataProvider('registrationLifecycleCases')]
    public function testRegistrationEnforcesEventLifecycleAtReadAndWriteBoundaries(
        bool $published,
        bool $closed,
        string $eventDate,
        ?string $deadline,
        bool $eligible
    ): void {
        $this->insertEvent($published, $closed, $eventDate, $deadline);

        $event = Event::findRegistrationEligibleById(101, '2026-06-28');
        $result = (new EntryRegistrationRepository($this->database))->register(
            101,
            201,
            301,
            501,
            '2026-06-28'
        );

        if ($eligible) {
            self::assertInstanceOf(Event::class, $event);
            self::assertSame(EntryRegistrationResult::Registered, $result);
            self::assertSame(1, $this->entryCount());

            return;
        }

        self::assertNull($event);
        self::assertSame(EntryRegistrationResult::AthleteRejected, $result);
        self::assertSame(0, $this->entryCount());
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function clubEntryQueries(): iterable
    {
        yield 'no requested club' => [['event' => '101']];
        yield 'forged foreign club' => [['event' => '101', 'club' => '202']];
    }

    /** @param array<string, string> $query */
    #[DataProvider('clubEntryQueries')]
    public function testClubEntryDetailsAreAlwaysScopedToTheSessionClub(array $query): void
    {
        $this->seedEntriesForTwoClubs();
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries($query);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Own Club', $response->content());
    }

    public function testAnonymousCanonicalEntryRouteShowsEntriesForPublishedEvent(): void
    {
        $this->seedEntriesForTwoClubs();
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $response = $this->dispatchEntries(['event' => '101', 'club' => '202']);

        self::assertSame(200, $response->status());
    }

    public function testAdminEventDetailsListsEnrolledAthletesWithTheirClubs(): void
    {
        $this->insertEvent(closed: true);
        $this->insertAdditionalEvent(102, 'Future Admin Event');
        $statement = $this->database->prepare(
            'INSERT INTO entries (
                event_id, club_id, athlete_id, snapshot_last_name, snapshot_first_name,
                snapshot_gender, snapshot_birth_date, snapshot_weight_kg, snapshot_belt,
                snapshot_membership_number, snapshot_program, snapshot_weight_category
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            101, 201, 301, 'SnapshotOwn', 'Athlete', 'M', '2012-01-01', 42.5,
            'green', 'SNAPSHOT-OWN', 'competitive', '-46 kg',
        ]);
        $statement->execute([
            101, 202, 302, 'SnapshotForeign', 'Athlete', 'F', '2013-02-03', 39,
            'yellow', 'SNAPSHOT-FOREIGN', 'competitive', '-40 kg',
        ]);
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('GET', '/admin/events/details', ['event_id' => '101']);

        $response = (new AdminController($this->view, $request))->eventDetails($request);
        self::assertMatchesRegularExpression(
            '/<section class="card admin-event-enrollments">(?<entries>.*?)<\/section>/s',
            $response->content()
        );
        preg_match(
            '/<section class="card admin-event-enrollments">(?<entries>.*?)<\/section>/s',
            $response->content(),
            $matches
        );
        $plainText = preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags((string) ($matches['entries'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );

        self::assertSame(200, $response->status());
        self::assertIsString($plainText);
        foreach (EventEntriesCsvTransfer::headers() as $field) {
            self::assertStringContainsString(
                e(__('admin.event_details.enrollment_fields.' . $field)),
                (string) ($matches['entries'] ?? ''),
                $field
            );
            self::assertStringContainsString(
                __('admin.event_details.enrollment_field_abbreviations.' . $field),
                $plainText,
                $field
            );
            self::assertStringContainsString(
                'data-sort-key="' . $field . '"',
                (string) ($matches['entries'] ?? ''),
                $field
            );
        }
        self::assertStringContainsString(__('admin.event_details.enrolled_athletes') . ' (2)', $plainText);
        self::assertStringContainsString(__('admin.event_details.all_clubs'), $plainText);
        self::assertStringContainsString('SnapshotOwn Athlete', $plainText);
        self::assertStringContainsString('Own Club', $plainText);
        self::assertStringContainsString('OWN-201', $plainText);
        self::assertStringContainsString('2012-01-01', $plainText);
        self::assertStringContainsString('42.5', $plainText);
        self::assertStringContainsString(__('belt.green'), $plainText);
        self::assertStringContainsString('SNAPSHOT-OWN', $plainText);
        self::assertStringContainsString(__('gender.M'), $plainText);
        self::assertStringContainsString('♂', $plainText);
        self::assertStringContainsString('gender-badge--male', $response->content());
        self::assertStringContainsString('gender-badge--female', $response->content());
        self::assertStringContainsString(__('admin.events.type_tooltip.competitive'), $plainText);
        self::assertStringContainsString('class="belt-badge"', $response->content());
        self::assertStringContainsString('class="belt-badge__visual"', $response->content());
        self::assertStringContainsString('-46 kg', $plainText);
        self::assertStringContainsString('SnapshotForeign Athlete', $plainText);
        self::assertStringContainsString('Foreign Club', $plainText);
        self::assertStringNotContainsString('OwnFamily OwnGiven', $plainText);
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $plainText);
        self::assertStringContainsString('class="card upcoming-events-card"', $response->content());
        self::assertStringContainsString('Future Admin Event', $response->content());
        self::assertStringContainsString(
            'href="/admin/events/details?event_id=102"',
            $response->content()
        );
        self::assertStringContainsString(
            'href="/admin/events/poster?event=101"',
            $response->content()
        );
        self::assertStringNotContainsString(
            'href="/uploads/events/poster_synthetic.png"',
            $response->content()
        );
        self::assertStringNotContainsString('href="/events/details?event=102"', $response->content());

        $sortedRequest = new Request('GET', '/admin/events/details', [
            'event_id' => '101',
            'enrollment_sort' => 'last_name',
            'enrollment_direction' => 'asc',
        ]);
        $sortedResponse = (new AdminController($this->view, $sortedRequest))->eventDetails($sortedRequest);
        $foreignPosition = strpos($sortedResponse->content(), 'SnapshotForeign');
        $ownPosition = strpos($sortedResponse->content(), 'SnapshotOwn');
        self::assertIsInt($foreignPosition);
        self::assertIsInt($ownPosition);
        self::assertLessThan($ownPosition, $foreignPosition);

        $filteredRequest = new Request('GET', '/admin/events/details', [
            'event_id' => '101',
            'club_id' => '201',
        ]);
        $filteredResponse = (new AdminController($this->view, $filteredRequest))->eventDetails($filteredRequest);

        self::assertSame(200, $filteredResponse->status());
        self::assertStringContainsString('SnapshotOwn', $filteredResponse->content());
        self::assertStringNotContainsString('SnapshotForeign', $filteredResponse->content());
        self::assertMatchesRegularExpression(
            '/<option\s+value="201"\s+selected\s*>Own Club<\/option>/',
            $filteredResponse->content()
        );
    }

    public function testAdminEventEnrollmentTableUsesAFilteredBoundedPage(): void
    {
        $this->insertEvent();
        $entry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $entry->execute([101, 201, 301]);
        for ($index = 1; $index <= 50; $index++) {
            $athleteId = 399 + $index;
            $this->insertOwnAthlete($athleteId, sprintf('Paged%02d', $index), 'Athlete');
            $entry->execute([101, 201, $athleteId]);
        }
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        $request = new Request('GET', '/admin/events/details', [
            'event_id' => '101',
            'club_id' => '201',
            'enrollment_page' => '2',
        ]);

        $response = (new AdminController($this->view, $request))->eventDetails($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            __('admin.event_details.enrolled_athletes') . ' (51)',
            preg_replace('/\s+/u', ' ', strip_tags($response->content())) ?? ''
        );
        self::assertStringContainsString('Paged50', $response->content());
        self::assertStringNotContainsString('OwnFamily', $response->content());
        self::assertStringContainsString('enrollment_page=1', $response->content());
        self::assertStringContainsString('club_id=201', $response->content());
    }

    public function testOpenEntryReadDoesNotRequireClosedEventSnapshotColumns(): void
    {
        $this->seedEntriesForTwoClubs();
        $this->database->exec('ALTER TABLE entries DROP COLUMN snapshot_birth_date');

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Own Club', $response->content());
        self::assertStringContainsString('Foreign Club', $response->content());
    }

    public function testClubEntryDetailsDoNotExposeUnpublishedEventMetadata(): void
    {
        $this->insertEvent(published: false, description: 'UNPUBLISHED-ENTRY-METADATA');
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(302, $response->status());
        self::assertSame('/events', $response->headers()['Location']);
        self::assertStringNotContainsString('UNPUBLISHED-ENTRY-METADATA', $response->content());
    }

    public function testDuplicateRegistrationFeedbackSurvivesRedirectAndIsShownOnce(): void
    {
        $today = date('Y-m-d');
        $eventDate = date('Y-m-d', strtotime('+1 day'));
        $this->insertEvent(date: $eventDate, deadline: $today);
        $this->database->exec(
            "CREATE TRIGGER synthetic_entry_failure
             BEFORE INSERT ON entries
             WHEN NEW.athlete_id = 303
             BEGIN SELECT RAISE(FAIL, 'Synthetic entry failure'); END"
        );
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $this->logPath = sys_get_temp_dir() . '/competizionijudo-registration-'
            . bin2hex(random_bytes(8)) . '.log';
        $post = new Request(
            'POST',
            '/events/register?event=101',
            ['event' => '101'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['301', '302', '301', '303', 'invalid'],
                'registration_option_id' => '501',
            ]
        );

        $postResponse = (new EventController(
            $this->view,
            $post,
            new FileLogger($this->logPath),
            new FakePasswordResetMailer(true)
        ))->register($post);
        $get = new Request('GET', '/events/register?event=101', ['event' => '101']);
        $firstGet = (new EventController($this->view, $get))->register($get);
        $secondGet = (new EventController($this->view, $get))->register($get);

        self::assertSame(302, $postResponse->status());
        self::assertSame(200, $firstGet->status());
        self::assertStringContainsString('Aggiunti: 1', $firstGet->content());
        self::assertStringContainsString('Rifiutati: 2', $firstGet->content());
        self::assertStringContainsString('Non riusciti: 1', $firstGet->content());
        self::assertStringContainsString(__('events.registration_recap_delivery_failed'), $firstGet->content());
        self::assertStringNotContainsString('registration-results', $secondGet->content());
        self::assertStringContainsString(
            '"event":"event.registration_failed"',
            (string) file_get_contents($this->logPath)
        );
        self::assertStringContainsString(
            '"event":"event.registration_recap_delivery_failed"',
            (string) file_get_contents($this->logPath)
        );
    }

    public function testRegistrationOnASecondAthletePagePreservesHiddenRegistrations(): void
    {
        $today = date('Y-m-d');
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: $today);
        for ($index = 1; $index <= 50; $index++) {
            $this->insertOwnAthlete(
                399 + $index,
                sprintf('Paged%02d', $index),
                'Athlete'
            );
        }
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 201, 301]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $get = new Request('GET', '/events/register', [
            'event' => '101',
            'athletes_page' => '2',
        ]);
        $page = (new EventController($this->view, $get))->register($get);

        self::assertSame(200, $page->status());
        self::assertStringContainsString('Paged50 Athlete', $page->content());
        self::assertStringNotContainsString('OwnFamily OwnGiven', $page->content());
        self::assertStringContainsString('athletes_page=1', $page->content());

        $post = new Request(
            'POST',
            '/events/register',
            ['event' => '101', 'athletes_page' => '2'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['449'],
                'registration_option_id' => '501',
            ]
        );
        $response = (new EventController(
            $this->view,
            $post,
            null,
            new FakePasswordResetMailer()
        ))->register($post);

        self::assertSame(302, $response->status());
        self::assertSame('/events/register?event=101&athletes_page=2', $response->headers()['Location']);
        self::assertSame(
            [301, 449],
            $this->database->query(
                'SELECT athlete_id FROM entries WHERE event_id = 101 ORDER BY athlete_id'
            )->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /** @return iterable<string, array{bool}> */
    public static function initialRegistrationStates(): iterable
    {
        yield 'select a new athlete' => [false];
        yield 'deselect an enrolled athlete' => [true];
    }

    #[DataProvider('initialRegistrationStates')]
    public function testDraftSelectionSurvivesPagingAndSortingUntilAllPagesAreSaved(bool $initiallyRegistered): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: date('Y-m-d'));
        for ($index = 1; $index <= 50; $index++) {
            $this->insertOwnAthlete(399 + $index, sprintf('Paged%02d', $index), 'Athlete');
        }
        $entry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (101, 201, ?)'
        );
        $entry->execute([400]);
        if ($initiallyRegistered) {
            $entry->execute([301]);
        }
        $this->database->exec(
            "INSERT INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (502, 101, 'Premium', 2500, 0, 1, 1)"
        );
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $pageOneSelection = $initiallyRegistered ? ['400'] : ['301', '400'];
        $originalEntryCount = $this->entryCount();

        // Navigation must work before a required registration option is chosen.
        $navigation = $this->dispatchRegistration(['event' => '101'], [
            'registration_action' => 'navigate',
            'athletes' => $pageOneSelection,
            'next_page' => '2',
        ]);
        self::assertSame(303, $navigation->status());
        self::assertSame(
            '/events/register?event=101&athletes_page=2&registration_sort=athlete&registration_direction=asc',
            $navigation->headers()['Location']
        );
        self::assertSame($originalEntryCount, $this->entryCount());

        $secondPage = $this->dispatchRegistration(['event' => '101', 'athletes_page' => '2']);
        $document = new \DOMDocument();
        @$document->loadHTML($secondPage->content());
        $xpath = new \DOMXPath($document);
        self::assertSame(0, $xpath->query('//*[@id="save-changes-btn"]/@disabled')->length);
        self::assertSame(0, $xpath->query('//*[@id="change-indicator"]/@hidden')->length);

        $sortNavigation = $this->dispatchRegistration(['event' => '101', 'athletes_page' => '2'], [
            'registration_action' => 'navigate',
            'athletes' => ['449'],
            'registration_option_id' => '502',
            'next_page' => '1',
            'next_sort' => 'weight',
            'next_direction' => 'desc',
        ]);
        self::assertSame(303, $sortNavigation->status());
        self::assertStringContainsString('registration_sort=weight', $sortNavigation->headers()['Location']);
        self::assertSame($originalEntryCount, $this->entryCount());

        $pageOneQuery = ['event' => '101', 'registration_sort' => 'weight', 'registration_direction' => 'desc'];
        $firstPage = $this->dispatchRegistration($pageOneQuery);
        @$document->loadHTML($firstPage->content());
        $xpath = new \DOMXPath($document);
        self::assertSame(
            $initiallyRegistered ? 0 : 1,
            $xpath->query('//input[@name="athletes[]" and @value="301"]/@checked')->length
        );
        self::assertSame(1, $xpath->query('//option[@value="502"]/@selected')->length);

        $saved = $this->dispatchRegistration($pageOneQuery, [
            'athletes' => $pageOneSelection,
            'registration_option_id' => '502',
        ]);
        self::assertSame(302, $saved->status());
        self::assertSame(
            $initiallyRegistered ? [400, 449] : [301, 400, 449],
            $this->database->query(
                'SELECT athlete_id FROM entries WHERE event_id = 101 ORDER BY athlete_id'
            )->fetchAll(PDO::FETCH_COLUMN)
        );
        self::assertSame(502, $this->database->query(
            'SELECT registration_option_id FROM entries WHERE athlete_id = 449'
        )->fetchColumn());
        self::assertSame([], (new EventRegistrationSelection(201, 101))->changes());

        $savedPage = $this->dispatchRegistration(['event' => '101', 'athletes_page' => '2']);
        @$document->loadHTML($savedPage->content());
        $xpath = new \DOMXPath($document);
        self::assertSame(1, $xpath->query('//input[@value="449"]/@checked')->length);
        self::assertSame(1, $xpath->query('//*[@id="save-changes-btn"]/@disabled')->length);
    }

    public function testNavigationCannotStageForeignOrUnvisitedAthletesAndSaveRechecksOwnership(): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: date('Y-m-d'));
        for ($index = 1; $index <= 50; $index++) {
            $this->insertOwnAthlete(399 + $index, sprintf('Paged%02d', $index), 'Athlete');
        }
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $this->dispatchRegistration(['event' => '101'], [
            'registration_action' => 'navigate',
            'athletes' => ['301', '302', '449'],
            'next_page' => '2',
            'next_sort' => 'invalid',
            'next_direction' => 'invalid',
        ]);
        self::assertSame([301 => true], (new EventRegistrationSelection(201, 101))->changes());
        self::assertSame(0, $this->entryCount());

        // A staged athlete may change clubs before the user saves another page.
        $this->database->exec('UPDATE athletes SET club_id = 202 WHERE id = 301');
        $this->dispatchRegistration(['event' => '101', 'athletes_page' => '2'], [
            'athletes' => ['449'],
            'registration_option_id' => '501',
        ]);

        self::assertSame([449], $this->database->query('SELECT athlete_id FROM entries')->fetchAll(PDO::FETCH_COLUMN));
        $feedback = $this->dispatchRegistration(['event' => '101']);
        self::assertStringContainsString(__('events.registration_rejected', ['count' => '1']), $feedback->content());
    }

    public function testInvalidRegistrationOptionPreservesTheDraftForCorrection(): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: date('Y-m-d'));
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchRegistration(['event' => '101'], [
            'athletes' => ['301'],
            'registration_option_id' => '999',
        ]);
        self::assertSame(302, $response->status());
        self::assertSame(0, $this->entryCount());
        $page = $this->dispatchRegistration(['event' => '101']);
        $document = new \DOMDocument();
        @$document->loadHTML($page->content());
        self::assertSame(1, (new \DOMXPath($document))->query('//input[@value="301"]/@checked')->length);
        self::assertStringContainsString(e(__('events.registration_option_required')), $page->content());
    }

    public function testDraftNavigationRequiresCsrfValidation(): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: date('Y-m-d'));
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $application = new Application(dirname(__DIR__));
        (require dirname(__DIR__) . '/routes/web.php')($application->router());

        $response = $application->handle(new Request('POST', '/events/register', ['event' => '101'], [
            'csrf_token' => 'invalid',
            'registration_action' => 'navigate',
            'athletes' => ['301'],
            'next_page' => '2',
        ]));

        self::assertSame(419, $response->status());
        self::assertSame([], (new EventRegistrationSelection(201, 101))->changes());
        self::assertSame(0, $this->entryCount());
    }

    /** @return iterable<string, array{int}> */
    public static function registrationAthleteCounts(): iterable
    {
        yield 'one athlete' => [1];
        yield 'seventy-five athletes' => [75];
    }

    #[DataProvider('registrationAthleteCounts')]
    public function testDraftNavigationUsesSevenQueriesRegardlessOfAthleteCount(int $athleteCount): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: date('Y-m-d'));
        $this->database->exec('DELETE FROM athletes WHERE id = 303');
        for ($index = 2; $index <= $athleteCount; $index++) {
            $this->insertOwnAthlete(399 + $index, sprintf('Paged%02d', $index), 'Athlete');
        }
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $database = $this->createMock(PDO::class);
        $database->expects(self::exactly(7))->method('prepare')->willReturnCallback(
            fn(string $sql, array $options = []): \PDOStatement|false => $this->database->prepare($sql, $options)
        );
        $database->expects(self::never())->method('query');
        $this->databaseConnection->setValue(null, $database);

        $response = $this->dispatchRegistration(['event' => '101'], [
            'registration_action' => 'navigate',
            'athletes' => ['301'],
            'registration_option_id' => '501',
            'next_page' => '2',
        ]);

        self::assertSame(303, $response->status());
        self::assertSame([301 => true], (new EventRegistrationSelection(201, 101))->changes());
        self::assertSame(0, $this->entryCount());
    }

    public function testRegistrationAttemptWithoutWeightIsRejectedAndWarned(): void
    {
        $today = date('Y-m-d');
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: $today);
        $this->database->exec('UPDATE athletes SET weight_kg = NULL WHERE id = 303');
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $post = new Request(
            'POST',
            '/events/register?event=101',
            ['event' => '101'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['303'],
                'registration_option_id' => '501',
            ]
        );

        $postResponse = (new EventController($this->view, $post))->register($post);
        $get = new Request('GET', '/events/register?event=101', ['event' => '101']);
        $feedback = (new EventController($this->view, $get))->register($get);

        self::assertSame(302, $postResponse->status());
        self::assertSame(0, $this->entryCount());
        self::assertStringContainsString(
            __('events.registration_missing_weight', ['count' => '1']),
            $feedback->content()
        );
        self::assertStringContainsString(e(__('events.registration_missing_weight_notice')), $feedback->content());
        self::assertMatchesRegularExpression('/value="303"[^>]*disabled/s', $feedback->content());
    }

    public function testRegistrationSummaryPricesNewAndRemovedEnrollmentsFromTheirStoredOptions(): void
    {
        $today = date('Y-m-d');
        $eventDate = date('Y-m-d', strtotime('+1 day'));
        $this->insertEvent(date: $eventDate, deadline: $today);
        $this->database->exec(
            "UPDATE events
             SET sepa_account_holder = 'Synthetic Beneficiary',
                 sepa_iban = 'IT60X0542811101000000123456',
                 sepa_bic = 'UNCRITMMXXX'
             WHERE id = 101"
        );
        $this->database->exec(
            "INSERT INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (502, 101, 'Premium', 2500, 0, 1, 1)"
        );
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 201, 301]);
        $this->database->exec(
            'UPDATE event_registration_options SET fee_cents = 1900 WHERE id = 501'
        );
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $post = new Request(
            'POST',
            '/events/register?event=101',
            ['event' => '101'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['303'],
                'registration_option_id' => '502',
            ]
        );
        $mailer = new FakePasswordResetMailer();
        $postResponse = (new EventController($this->view, $post, null, $mailer))->register($post);
        $get = new Request('GET', '/events/register?event=101', ['event' => '101']);
        $summary = (new EventController($this->view, $get))->register($get);

        self::assertSame(302, $postResponse->status());
        self::assertSame(
            [
                [303, 502, 'Premium', 2500],
            ],
            $this->database->query(
                'SELECT athlete_id, registration_option_id, registration_option_name,
                        registration_fee_cents
                 FROM entries
                 WHERE event_id = 101 AND club_id = 201'
            )->fetchAll(PDO::FETCH_NUM)
        );
        $plainText = preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($summary->content()), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        self::assertIsString($plainText);
        self::assertStringContainsString('FailureFamily FailureGiven — Premium', $plainText);
        self::assertStringContainsString('OwnFamily OwnGiven — Standard', $plainText);
        self::assertStringContainsString('+€25.00', $plainText);
        self::assertStringContainsString('−€15.00', $plainText);
        self::assertStringContainsString('€10.00', $plainText);
        self::assertStringContainsString('IT60X0542811101000000123456', $plainText);
        self::assertStringContainsString('data:image/svg+xml;base64,', $summary->content());

        self::assertCount(1, $mailer->registrationRecaps);
        self::assertSame('own@example.test', $mailer->registrationRecaps[0]['recipient']);
        self::assertStringContainsString('Synthetic Event', $mailer->registrationRecaps[0]['subject']);
        self::assertStringContainsString(
            __('events.registration_recap_event'),
            $mailer->registrationRecaps[0]['message']
        );
        self::assertStringContainsString('Synthetic Venue', $mailer->registrationRecaps[0]['message']);
        self::assertStringContainsString('FailureFamily FailureGiven — Premium', $mailer->registrationRecaps[0]['message']);
        self::assertStringContainsString('OwnFamily OwnGiven — Standard', $mailer->registrationRecaps[0]['message']);
        self::assertStringContainsString('IT60X0542811101000000123456', $mailer->registrationRecaps[0]['message']);
    }

    public function testPaidRegistrationWithoutIbanOmitsSepaDetailsAndEmailsTheRecap(): void
    {
        $today = date('Y-m-d');
        $eventDate = date('Y-m-d', strtotime('+1 day'));
        $this->insertEvent(date: $eventDate, deadline: $today);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $mailer = new FakePasswordResetMailer();
        $post = new Request(
            'POST',
            '/events/register?event=101',
            ['event' => '101'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['301'],
                'registration_option_id' => '501',
            ]
        );

        $postResponse = (new EventController($this->view, $post, null, $mailer))->register($post);
        $get = new Request('GET', '/events/register?event=101', ['event' => '101']);
        $summary = (new EventController($this->view, $get))->register($get);
        $plainText = preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($summary->content()), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );

        self::assertSame(302, $postResponse->status());
        self::assertIsString($plainText);
        self::assertStringContainsString(__('events.amount_due') . ' €15.00', $plainText);
        self::assertStringNotContainsString(__('events.payment_info'), $plainText);
        self::assertStringNotContainsString(__('events.payment_iban'), $plainText);
        self::assertStringNotContainsString(__('events.payment_qr_code_unavailable'), $plainText);
        self::assertStringNotContainsString('data:image/svg+xml;base64,', $summary->content());

        self::assertCount(1, $mailer->registrationRecaps);
        $message = $mailer->registrationRecaps[0]['message'];
        self::assertStringContainsString('Synthetic Event', $message);
        self::assertStringContainsString($eventDate, $message);
        self::assertStringContainsString('Synthetic Venue', $message);
        self::assertStringContainsString('OwnFamily OwnGiven — Standard', $message);
        self::assertStringContainsString(__('events.amount_due') . ': €15.00', $message);
        self::assertStringNotContainsString(__('events.payment_info'), $message);
        self::assertStringNotContainsString(__('events.payment_iban'), $message);
    }

    public function testRegistrationRejectsChangesWhenNoActiveOptionIsSelected(): void
    {
        $today = date('Y-m-d');
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: $today);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));
        $post = new Request(
            'POST',
            '/events/register?event=101',
            ['event' => '101'],
            [
                'csrf_token' => csrf_token(),
                'athletes' => ['301'],
            ]
        );

        $response = (new EventController($this->view, $post))->register($post);
        $get = new Request('GET', '/events/register?event=101', ['event' => '101']);
        $feedback = (new EventController($this->view, $get))->register($get);

        self::assertSame(302, $response->status());
        self::assertSame(0, $this->entryCount());
        self::assertStringContainsString(
            __('events.registration_option_required'),
            html_entity_decode($feedback->content(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }

    public function testEntriesWithoutEventIdShowsUpcomingEvents(): void
    {
        $this->insertEvent(date: '2099-06-29');
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $response = $this->dispatchEntries([]);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(__('events.entries'), $response->content());
        self::assertStringContainsString('Synthetic Event', $response->content());
    }

    public function testClosedEntriesWithMissingSnapshotsUseTheCurrentAthleteData(): void
    {
        $this->insertEvent(closed: true);
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 201, 301]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily', $response->content());
        self::assertStringContainsString('Bianca', $response->content());
        self::assertStringContainsString('-42 kg', $response->content());
    }

    public function testAnonymousClosedEntriesHideAthleteLevelTable(): void
    {
        $this->insertEvent(closed: true);
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 201, 301]);

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(__('events.entries_clubs_heading'), $response->content());
        self::assertStringNotContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringNotContainsString(__('events.entries_class_weight_breakdown'), $response->content());
        self::assertStringNotContainsString(__('events.entries_club_breakdown'), $response->content());
        self::assertStringNotContainsString('name="clubs_filter_breakdown"', $response->content());
        self::assertStringNotContainsString('closed-event-opponents', $response->content());
        self::assertStringNotContainsString('OwnFamily OwnGiven', $response->content());
    }

    public function testClosedCurrentClubTableHasIndependentPagination(): void
    {
        $this->insertEvent(closed: true);
        $entry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $entry->execute([101, 201, 301]);
        for ($index = 1; $index <= 50; $index++) {
            $athleteId = 399 + $index;
            $this->insertOwnAthlete($athleteId, sprintf('Paged%02d', $index), 'Athlete');
            $entry->execute([101, 201, $athleteId]);
        }
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries([
            'event' => '101',
            'athletes_page' => '2',
            'club_entries_page' => '2',
        ]);

        self::assertSame(200, $response->status());
        self::assertSame(3, substr_count($response->content(), 'Paged50 Athlete'));
        self::assertStringContainsString('athletes_page=1', $response->content());
        self::assertStringContainsString('club_entries_page=1', $response->content());
    }

    public function testParticipatingClubWithCurrentAgreementsCanViewAllClosedEventAthletes(): void
    {
        $this->insertEvent(closed: true);
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringContainsString('ForeignFamily ForeignGiven', $response->content());
        self::assertStringContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringContainsString(e(__('events.entries_shared_roster_help')), $response->content());
        self::assertStringContainsString('href="/privacy"', $response->content());
        self::assertStringContainsString('href="/terms"', $response->content());
        self::assertStringContainsString('closed-event-opponents', $response->content());
    }

    public function testClosedEntriesGroupOtherClubOpponentsAfterTheCurrentClubTable(): void
    {
        $this->insertEvent(closed: true);
        $this->database->prepare(
            'INSERT INTO athletes (
                id, club_id, last_name, first_name, gender, birth_date,
                weight_kg, weight_category, belt, program, membership_number, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            304,
            202,
            'MatchedForeign',
            'Opponent',
            'M',
            '2012-01-01',
            40.0,
            '-40',
            'yellow',
            'competitive',
            'MATCHED-FOREIGN',
            null,
        ]);
        $statement = $this->database->prepare(
            'INSERT INTO entries (
                event_id, club_id, athlete_id, snapshot_weight_kg, snapshot_weight_category
             ) VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([101, 201, 301, null, null]);
        $statement->execute([101, 201, 303, 55, '-55 kg']);
        $statement->execute([101, 202, 302, null, null]);
        $statement->execute([101, 202, 304, null, null]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        $currentClubPosition = strpos($response->content(), 'class="card closed-event-club-entries"');
        $opponentsPosition = strpos($response->content(), 'class="card closed-event-opponents"');
        $clubsPosition = strpos($response->content(), e(__('events.entries_clubs_heading')));
        self::assertIsInt($currentClubPosition);
        self::assertIsInt($opponentsPosition);
        self::assertIsInt($clubsPosition);
        self::assertLessThan($opponentsPosition, $currentClubPosition);
        self::assertLessThan($clubsPosition, $opponentsPosition);

        preg_match(
            '/<section class="card closed-event-opponents">(?<opponents>.*?)<\/section>/s',
            $response->content(),
            $matches
        );
        $opponentsSection = (string) ($matches['opponents'] ?? '');
        self::assertSame(2, substr_count($opponentsSection, 'class="responsive-table opponent-table"'));
        self::assertStringContainsString('-42 kg', $opponentsSection);
        self::assertStringContainsString('-55 kg', $opponentsSection);
        self::assertStringContainsString('OwnFamily OwnGiven', $opponentsSection);
        self::assertStringContainsString('FailureFamily FailureGiven', $opponentsSection);
        self::assertStringContainsString('MatchedForeign Opponent', $opponentsSection);
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $opponentsSection);
        self::assertSame(2, substr_count($opponentsSection, 'class="club-row--current"'));
        self::assertSame(
            2,
            substr_count($opponentsSection, 'class="club-badge--current club-badge--icon"')
        );
        self::assertSame(2, substr_count($opponentsSection, '>👥</span>'));
        self::assertStringContainsString('data-client-pagination="50"', $opponentsSection);

        $filteredResponse = $this->dispatchEntries([
            'event' => '101',
            'opponents_filter_age_class' => 'Esordienti',
            'opponents_filter_weight_category' => '-42',
            'opponents_filter_athlete' => 'MatchedForeign',
            'opponents_filter_club' => 'Foreign',
            'opponents_filter_gender' => 'Maschio',
            'opponents_filter_belt' => 'Gialla',
        ]);
        preg_match(
            '/<section class="card closed-event-opponents">(?<opponents>.*?)<\/section>/s',
            $filteredResponse->content(),
            $filteredMatches
        );
        $filteredSection = (string) ($filteredMatches['opponents'] ?? '');
        self::assertSame(1, substr_count($filteredSection, 'class="responsive-table opponent-table"'));
        self::assertStringContainsString('MatchedForeign Opponent', $filteredSection);
        self::assertStringNotContainsString('-55 kg', $filteredSection);
        $filterNames = [
            'opponents_filter_age_class',
            'opponents_filter_weight_category',
            'opponents_filter_athlete',
            'opponents_filter_club',
            'opponents_filter_gender',
            'opponents_filter_belt',
        ];
        foreach ($filterNames as $filterName) {
            self::assertStringContainsString('name="' . $filterName . '"', $filteredSection);
        }
    }

    public function testClubMustAcceptCurrentAgreementsBeforeViewingOtherClubsAthletes(): void
    {
        $this->insertEvent(closed: true);
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
        $this->database->exec('DELETE FROM club_terms_acceptances WHERE club_id = 201');
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $response->content());
        self::assertStringNotContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringNotContainsString('name="athletes_filter_athlete"', $response->content());
        self::assertStringNotContainsString('closed-event-opponents', $response->content());
        self::assertStringNotContainsString('name="opponents_filter_athlete"', $response->content());
        self::assertStringContainsString(
            e(__('events.entries_roster_agreements_required')),
            $response->content()
        );
        self::assertStringContainsString('href="/clubs/agreements"', $response->content());
    }

    public function testClosedRosterRemainsScopedToParticipatingClubs(): void
    {
        $this->insertEvent(closed: true);
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 202, 302]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(302, $response->status());
        self::assertSame('/events', $response->headers()['Location']);
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $response->content());
    }

    public function testClubWithRegistrationExceptionCannotViewSharedRosterWhileItCanEnroll(): void
    {
        $this->insertEvent(closed: true);
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
        EventRegistrationException::setForEvent(101, [201]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $response->content());
        self::assertStringNotContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringNotContainsString('closed-event-opponents', $response->content());
        self::assertStringNotContainsString(
            __('events.entries_roster_agreements_required'),
            $response->content()
        );
    }

    public function testAdministratorCanViewAllClosedEventAthletes(): void
    {
        $this->insertEvent(closed: true);
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));

        $request = new Request('GET', '/events/entries', ['event' => '101']);
        $response = (new EventController($this->view, $request))->entries($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringContainsString('ForeignFamily ForeignGiven', $response->content());
        self::assertStringContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringNotContainsString('closed-event-opponents', $response->content());
    }

    public function testClosedEntriesCanFilterTheCurrentClubByWeightCategory(): void
    {
        $this->insertEvent(closed: true);
        $insert = $this->database->prepare(
            'INSERT INTO entries (
                event_id, club_id, athlete_id, snapshot_weight_kg, snapshot_weight_category
             ) VALUES (?, ?, ?, ?, ?)'
        );
        $insert->execute([101, 201, 301, 40, '-42 kg']);
        $insert->execute([101, 201, 303, 55, '-55 kg']);
        $insert->execute([101, 202, 302, 40, '-42 kg']);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries([
            'event' => '101',
            'weight_category' => '-42 kg',
        ]);

        self::assertSame(200, $response->status());
        self::assertMatchesRegularExpression(
            '/<section class="card closed-event-club-entries">(?<club>.*?)<\/section>/s',
            $response->content()
        );
        preg_match(
            '/<section class="card closed-event-club-entries">(?<club>.*?)<\/section>/s',
            $response->content(),
            $matches
        );
        $clubSection = (string) ($matches['club'] ?? '');
        self::assertStringContainsString('OwnFamily OwnGiven', $clubSection);
        self::assertStringNotContainsString('FailureFamily FailureGiven', $clubSection);
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $clubSection);
        self::assertMatchesRegularExpression('/value="-42 kg"\s+selected/', $clubSection);
        self::assertStringContainsString(
            '/events/entries/export?event=101&amp;weight_category=-42%20kg',
            $clubSection
        );
    }

    public function testClosedEntryTablesExposeAndApplyAFilterForEveryVisibleColumn(): void
    {
        $this->insertEvent(closed: true);
        $insert = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $insert->execute([101, 201, 301]);
        $insert->execute([101, 201, 303]);
        $insert->execute([101, 202, 302]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries([
            'event' => '101',
            'clubs_filter_club' => 'Foreign',
            'clubs_filter_federal_code' => 'FOREIGN',
            'clubs_filter_athletes' => '1',
            'clubs_filter_breakdown' => 'Femmina: 1',
            'athletes_filter_age_class' => 'Esordienti',
            'athletes_filter_weight_category' => '-40 kg',
            'athletes_filter_athlete' => 'ForeignFamily',
            'athletes_filter_club' => 'Foreign',
            'athletes_filter_gender' => 'Femmina',
            'athletes_filter_belt' => 'Bianca',
            'club_entries_filter_athlete' => 'OwnFamily',
            'club_entries_filter_gender' => 'Maschio',
            'club_entries_filter_weight' => '40',
            'weight_category' => '-42 kg',
            'club_entries_filter_belt' => 'Bianca',
        ]);

        self::assertSame(200, $response->status());
        $filterNames = [
            'clubs_filter_club',
            'clubs_filter_federal_code',
            'clubs_filter_athletes',
            'clubs_filter_breakdown',
            'athletes_filter_age_class',
            'athletes_filter_weight_category',
            'athletes_filter_athlete',
            'athletes_filter_club',
            'athletes_filter_gender',
            'athletes_filter_belt',
            'club_entries_filter_athlete',
            'club_entries_filter_gender',
            'club_entries_filter_weight',
            'weight_category',
            'club_entries_filter_belt',
        ];
        foreach ($filterNames as $filterName) {
            self::assertStringContainsString('name="' . $filterName . '"', $response->content());
        }

        $clubsHeading = preg_quote(e(__('events.entries_clubs_heading')), '/');
        $athletesHeading = preg_quote(e(__('events.entries_athletes_heading')), '/');
        self::assertMatchesRegularExpression(
            '/<section class="card">\s*<h2>' . $clubsHeading
                . '<\/h2>.*?<tbody>(?<clubs>.*?)<\/tbody>/s',
            $response->content()
        );
        preg_match(
            '/<section class="card">\s*<h2>' . $clubsHeading
                . '<\/h2>.*?<tbody>(?<clubs>.*?)<\/tbody>/s',
            $response->content(),
            $clubMatches
        );
        self::assertStringContainsString('Foreign Club', (string) ($clubMatches['clubs'] ?? ''));
        self::assertStringNotContainsString('Own Club', (string) ($clubMatches['clubs'] ?? ''));

        $athletesPattern = '/<section class="card">\s*<h2>' . $athletesHeading
            . '<\/h2>.*?<tbody>(?<athletes>.*?)<\/tbody>/s';
        self::assertMatchesRegularExpression($athletesPattern, $response->content());
        preg_match(
            $athletesPattern,
            $response->content(),
            $athleteMatches
        );
        self::assertStringContainsString(
            'ForeignFamily ForeignGiven',
            (string) ($athleteMatches['athletes'] ?? '')
        );
        self::assertStringNotContainsString(
            'OwnFamily OwnGiven',
            (string) ($athleteMatches['athletes'] ?? '')
        );

        preg_match(
            '/<section class="card closed-event-club-entries">.*?<tbody>'
                . '(?<current>.*?)<\/tbody>/s',
            $response->content(),
            $currentClubMatches
        );
        self::assertStringContainsString(
            'OwnFamily OwnGiven',
            (string) ($currentClubMatches['current'] ?? '')
        );
        self::assertStringNotContainsString(
            'FailureFamily FailureGiven',
            (string) ($currentClubMatches['current'] ?? '')
        );
        self::assertStringContainsString(
            'club_entries_filter_athlete=OwnFamily',
            html_entity_decode($response->content(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
    }

    public function testCurrentClubFiltersAreAppliedBeforePagination(): void
    {
        $this->insertEvent(closed: true);
        $entry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $entry->execute([101, 201, 301]);
        for ($index = 1; $index <= 50; $index++) {
            $athleteId = 499 + $index;
            $this->insertOwnAthlete($athleteId, sprintf('Paged%02d', $index), 'Athlete');
            $entry->execute([101, 201, $athleteId]);
        }
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries([
            'event' => '101',
            'club_entries_filter_athlete' => 'OwnFamily',
            'club_entries_page' => '2',
        ]);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringContainsString(__('events.entries_filtered_count', ['count' => '1']), $response->content());
    }

    public function testOpenEntriesShowOnlyTheCurrentClubAthletesAndPrintableLink(): void
    {
        $today = date('Y-m-d');
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')), deadline: $today);
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 201, 301]);
        $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        )->execute([101, 202, 302]);
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries(['event' => '101']);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('closed-event-club-entries', $response->content());
        self::assertStringContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringContainsString('/events/entries/print?event=101', $response->content());
        self::assertStringNotContainsString('/events/entries/export', $response->content());
        self::assertStringNotContainsString('/events/entry-data?', $response->content());
        self::assertStringNotContainsString('ForeignFamily ForeignGiven', $response->content());
        self::assertStringNotContainsString(__('events.entries_athletes_heading'), $response->content());
        self::assertStringNotContainsString('closed-event-opponents', $response->content());
    }

    public function testOpenCurrentClubRosterSupportsFiltersAndIndependentPagination(): void
    {
        $this->insertEvent(date: date('Y-m-d', strtotime('+1 day')));
        $entry = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $entry->execute([101, 201, 301]);
        for ($index = 1; $index <= 51; $index++) {
            $athleteId = 499 + $index;
            $this->insertOwnAthlete($athleteId, sprintf('Paged%02d', $index), 'Athlete');
            $entry->execute([101, 201, $athleteId]);
        }
        Session::authenticateClub(201, CredentialFingerprint::forClubPasswordHash('synthetic-hash'));

        $response = $this->dispatchEntries([
            'event' => '101',
            'club_entries_filter_athlete' => 'Paged',
            'club_entries_sort' => 'athlete',
            'club_entries_page' => '2',
        ]);

        self::assertSame(200, $response->status());
        self::assertStringContainsString('Paged51 Athlete', $response->content());
        self::assertStringNotContainsString('Paged01 Athlete', $response->content());
        self::assertStringNotContainsString('OwnFamily OwnGiven', $response->content());
        self::assertStringContainsString(__('events.entries_filtered_count', ['count' => '51']), $response->content());
        self::assertStringContainsString('/events/entries/print?event=101', $response->content());
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function nonClubEntryViewers(): iterable
    {
        yield 'anonymous open' => [false, false];
        yield 'anonymous closed' => [false, true];
        yield 'administrator open' => [true, false];
        yield 'administrator closed' => [true, true];
    }

    #[DataProvider('nonClubEntryViewers')]
    public function testOwnClubCardAndPrintLinkAreHiddenForNonClubViewers(bool $administrator, bool $closed): void
    {
        $this->insertEvent(closed: $closed);
        if ($administrator) {
            Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        }

        $request = new Request('GET', '/events/entries', ['event' => '101']);
        $response = (new EventController($this->view, $request))->entries($request);

        self::assertSame(200, $response->status());
        self::assertStringNotContainsString('closed-event-club-entries', $response->content());
        self::assertStringNotContainsString('/events/entries/print', $response->content());
    }

    public function testClosedEventExceptionRegistrationCreatesAnEntrySnapshot(): void
    {
        $this->insertEvent(closed: true);
        EventRegistrationException::setForEvent(101, [201]);

        $result = (new EntryRegistrationRepository($this->database))->register(
            101,
            201,
            301,
            501,
            '2026-06-28'
        );

        self::assertSame(EntryRegistrationResult::Registered, $result);
        self::assertSame(
            [
                'OwnFamily',
                'OwnGiven',
                'M',
                'white',
                '-42 kg',
            ],
            $this->database->query(
                'SELECT snapshot_last_name, snapshot_first_name, snapshot_gender, snapshot_belt, snapshot_weight_category
                 FROM entries WHERE event_id = 101 AND club_id = 201 AND athlete_id = 301'
            )->fetch(PDO::FETCH_NUM)
        );
    }

    private function createSchemaAndActors(): void
    {
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                federal_code TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL,
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                contact_phone TEXT NOT NULL,
                contact_email TEXT,
                affiliation TEXT NOT NULL,
                recovery_email TEXT NOT NULL,
                approved_at TEXT,
                password_hash TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE events (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                date TEXT NOT NULL,
                location TEXT,
                organizer TEXT,
                registration_deadline TEXT,
                type TEXT,
                description TEXT,
                notes TEXT,
                max_participants INTEGER,
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL,
                closed INTEGER NOT NULL,
                sepa_iban TEXT,
                sepa_bic TEXT,
                sepa_account_holder TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE event_registration_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                fee_cents INTEGER NOT NULL DEFAULT 0,
                is_default INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            'CREATE TABLE event_registration_exceptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, club_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL,
                weight_category TEXT,
                belt TEXT,
                program TEXT NOT NULL,
                membership_number TEXT,
                notes TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE club_data_rights_declarations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                declared_by_club_id INTEGER NOT NULL,
                declaration_version TEXT NOT NULL,
                declared_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->database->exec(
            'CREATE TABLE club_terms_acceptances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                accepted_by_club_id INTEGER NOT NULL,
                representative_name TEXT NOT NULL,
                accepted_account_email TEXT NOT NULL,
                terms_version TEXT NOT NULL,
                accepted_locale TEXT NOT NULL,
                accepted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                registration_option_id INTEGER NOT NULL DEFAULT 501,
                registration_option_name TEXT NOT NULL DEFAULT \'Standard\',
                registration_fee_cents INTEGER NOT NULL DEFAULT 1500,
                snapshot_last_name TEXT,
                snapshot_first_name TEXT,
                snapshot_gender TEXT,
                snapshot_birth_date TEXT,
                snapshot_weight_kg REAL,
                snapshot_belt TEXT,
                snapshot_membership_number TEXT,
                snapshot_program TEXT,
                snapshot_weight_category TEXT,
                snapshot_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (event_id, club_id, athlete_id)
            )'
        );

        $club = $this->database->prepare(
            'INSERT INTO clubs (
                id, federal_code, name, email, phone, contact_first_name,
                contact_last_name, contact_phone, contact_email, affiliation,
                recovery_email, approved_at, password_hash
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            201,
            'OWN-201',
            'Own Club',
            'own@example.test',
            '',
            'Own',
            'Contact',
            '',
            null,
            'TEST',
            'own-recovery@example.test',
            '2026-01-01 00:00:00',
            'synthetic-hash',
        ]);
        $club->execute([
            202,
            'FOREIGN-202',
            'Foreign Club',
            'foreign@example.test',
            '',
            'Foreign',
            'Contact',
            '',
            null,
            'TEST',
            'foreign-recovery@example.test',
            '2026-01-01 00:00:00',
            'synthetic-hash',
        ]);
        $declaration = $this->database->prepare(
            'INSERT INTO club_data_rights_declarations '
            . '(club_id, declared_by_club_id, declaration_version) VALUES (?, ?, ?)'
        );
        $declaration->execute([201, 201, ClubDataRightsDeclaration::VERSION]);
        $declaration->execute([202, 202, ClubDataRightsDeclaration::VERSION]);
        $terms = $this->database->prepare(
            'INSERT INTO club_terms_acceptances '
            . '(club_id, accepted_by_club_id, representative_name, accepted_account_email, '
            . 'terms_version, accepted_locale) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $terms->execute([201, 201, 'Own Contact', 'own@example.test', ClubTermsAcceptance::VERSION, 'en']);
        $terms->execute([
            202,
            202,
            'Foreign Contact',
            'foreign@example.test',
            ClubTermsAcceptance::VERSION,
            'en',
        ]);

        $athlete = $this->database->prepare(
            'INSERT INTO athletes (
                id, club_id, last_name, first_name, gender, birth_date,
                weight_kg, weight_category, belt, program, membership_number, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $athlete->execute([
            301,
            201,
            'OwnFamily',
            'OwnGiven',
            'M',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'OWN-MEMBER',
            null,
        ]);
        $athlete->execute([
            302,
            202,
            'ForeignFamily',
            'ForeignGiven',
            'F',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'FOREIGN-MEMBER',
            null,
        ]);
        $athlete->execute([
            303,
            201,
            'FailureFamily',
            'FailureGiven',
            'M',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'FAILURE-MEMBER',
            null,
        ]);
    }

    private function insertEvent(
        bool $published = true,
        bool $closed = false,
        string $date = '2026-06-29',
        ?string $deadline = '2026-06-28',
        ?string $description = null
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO events (
                id, name, date, location, organizer, registration_deadline,
                type, description, notes, poster_file, info_file, published, closed
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            101,
            'Synthetic Event',
            $date,
            'Synthetic Venue',
            'Synthetic Organizer',
            $deadline,
            'only_competitive',
            $description,
            null,
            'uploads/events/poster_synthetic.png',
            null,
            $published ? 1 : 0,
            $closed ? 1 : 0,
        ]);
        $this->database->prepare(
            'INSERT OR IGNORE INTO event_registration_options (
                id, event_id, name, fee_cents, is_default, is_active, sort_order
             ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([501, 101, 'Standard', 1500, 1, 1, 0]);
    }

    private function insertAdditionalEvent(int $id, string $name): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO events (
                id, name, date, location, organizer, registration_deadline,
                type, description, notes, poster_file, info_file, published, closed
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            $name,
            '2099-01-02',
            'Future Synthetic Venue',
            'Future Synthetic Organizer',
            '2099-01-01',
            'only_competitive',
            null,
            null,
            null,
            null,
            1,
            0,
        ]);
    }

    private function insertOwnAthlete(int $id, string $lastName, string $firstName): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO athletes (
                id, club_id, last_name, first_name, gender, birth_date,
                weight_kg, weight_category, belt, program, membership_number, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            201,
            $lastName,
            $firstName,
            'M',
            '2012-01-01',
            40.0,
            '-40',
            'white',
            'competitive',
            'OWN-' . $id,
            null,
        ]);
    }

    private function seedEntriesForTwoClubs(): void
    {
        $this->insertEvent();
        $statement = $this->database->prepare(
            'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
        );
        $statement->execute([101, 201, 301]);
        $statement->execute([101, 202, 302]);
    }

    private function entryCount(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM entries')->fetchColumn();
    }

    /** @param array<string, string> $query */
    private function dispatchEntries(array $query): Response
    {
        $application = new Application(dirname(__DIR__));
        (require dirname(__DIR__) . '/routes/web.php')($application->router());

        return $application->handle(new Request('GET', '/events/entries', $query));
    }

    /**
     * @param array<string, string> $query
     * @param array<string, mixed>|null $post
     */
    private function dispatchRegistration(array $query, ?array $post = null): Response
    {
        $request = new Request(
            $post === null ? 'GET' : 'POST',
            '/events/register',
            $query,
            $post === null ? [] : array_merge(['csrf_token' => csrf_token()], $post)
        );

        return (new EventController($this->view, $request, null, new FakePasswordResetMailer()))->register($request);
    }

    private function startCleanSession(): void
    {
        $this->destroySession();
        Session::start();
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
