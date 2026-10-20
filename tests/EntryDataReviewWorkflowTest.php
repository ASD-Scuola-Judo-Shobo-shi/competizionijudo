<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\EntryDataController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use App\Model\Entry;
use App\Model\EntrySnapshotService;
use App\Service\AthleteRevisionRetentionService;
use App\Service\EntryDataReviewException;
use App\Service\EntryDataReviewService;
use App\Service\EntrySnapshotRetentionService;
use App\Service\EventEntriesCsvTransfer;
use App\Service\TournamentData;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tests\Support\EntryReviewSchema;

final class EntryDataReviewWorkflowTest extends TestCase
{
    private PDO $database;
    private ReflectionProperty $connection;
    private ?PDO $original;
    private EntryDataReviewService $service;

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $this->original = $this->connection->getValue();
        $this->database = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->schema();
        EntryReviewSchema::install($this->database);
        $this->seed();
        $this->connection->setValue(null, $this->database);
        $this->service = new EntryDataReviewService($this->database);
        Session::destroy();
        Session::start();
        Localization::setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->connection->setValue(null, $this->original);
        Session::destroy();
    }

    public function testClubRequestKeepsSnapshotUntilAdministratorApprovesTheExactRevision(): void
    {
        $before = $this->service->details(101, 401, 201);
        $originalCsv = (new EventEntriesCsvTransfer())->export(101, true);
        $this->service->requestRevision(101, 401, 1, 201, 'Confirm corrected weight', $before['fingerprint']);
        self::assertSame($originalCsv, (new EventEntriesCsvTransfer())->export(101, true));
        self::assertCount(1, $this->service->pending(101));
        $this->database->exec("UPDATE athletes SET last_name = 'Later profile', weight_kg = 90 WHERE id = 301");

        $this->service->decide(101, 401, 1, true, 'Verified source revision');

        $after = $this->service->details(101, 401, 201);
        self::assertSame('Revised', $after['current']['last_name']);
        self::assertSame('45.50', $after['current']['weight_kg']);
        self::assertSame('approved', $after['history'][0]['status']);
        self::assertSame('administrator', $after['history'][0]['decision_actor']);
        self::assertSame('2026-01-01 00:00:00', $after['entry']['snapshot_at']);
        self::assertStringContainsString('Revised', (new EventEntriesCsvTransfer())->export(101, true));
        self::assertStringNotContainsString('Later profile', (new EventEntriesCsvTransfer())->export(101, true));
        self::assertSame([], $this->service->pending(101));
        self::assertSame('Revised', Entry::findByEvent(101, 201, true)[0]['last_name']);
        self::assertSame('Other tournament', Entry::findByEvent(102, null, true)[0]['last_name']);
    }

    public function testRejectionIsVisibleWithoutChangingTheSnapshot(): void
    {
        $this->submitRequest();
        $this->service->decide(101, 401, 1, false, 'Insufficient evidence');
        $details = $this->service->details(101, 401, 201);
        self::assertSame('Original', $details['current']['last_name']);
        self::assertSame('rejected', $details['history'][0]['status']);
        self::assertSame('Insufficient evidence', $details['history'][0]['decision_reason']);
        self::assertSame('Original', $details['history'][0]['before']['last_name']);
        self::assertSame('Revised', $details['history'][0]['proposed']['last_name']);
    }

    public function testReadUsesDatabaseClosureEvenIfTheCallerLoadedTheEventBeforeItClosed(): void
    {
        $this->database->exec('UPDATE entries SET snapshot_at = NULL WHERE id = 401');
        self::assertSame('Original', Entry::findByEvent(101, 201, false, '2099-06-01')[0]['last_name']);
    }

    public function testAdministratorCanApplyAPreCutoffRevisionAfterTheTournamentEnds(): void
    {
        $this->database->exec("UPDATE events SET date = '2026-06-01' WHERE id = 101");
        $details = $this->service->details(101, 401, null);
        self::assertFalse($details['canEdit']);
        $this->service->requestRevision(101, 401, 1, null, 'Late approval of timely data', $details['fingerprint'], true);
        self::assertSame('Revised', $this->service->details(101, 401, null)['current']['last_name']);
    }

    public function testAdminCorrectionIsTournamentSpecificAndSurvivesClosingAnOpenTournament(): void
    {
        $this->database->exec('UPDATE events SET closed = 0 WHERE id = 101; UPDATE entries SET snapshot_at = NULL WHERE id = 401');
        $details = $this->service->details(101, 401, null);
        $data = $details['current'];
        $data['last_name'] = 'Tournament correction';
        $data['gender'] = 'F';
        $this->service->edit(101, 401, $data, 'Verified identity', $details['fingerprint']);
        self::assertSame('Tournament correction', Entry::findByEvent(101, 201, false, '2099-06-01')[0]['last_name']);
        self::assertSame('Revised', $this->database->query('SELECT last_name FROM athletes WHERE id = 301')->fetchColumn());
        $snapshotAt = $this->service->details(101, 401, null)['entry']['snapshot_at'];
        (new EntrySnapshotService($this->database))->consolidate(101, '2099-06-01');
        self::assertSame('Tournament correction', Entry::findByEvent(101, 201, true)[0]['last_name']);
        self::assertSame($snapshotAt, $this->service->details(101, 401, null)['entry']['snapshot_at']);
    }

    public function testEndedOpenTournamentUsesOnlyTheLatestPreCutoffRevisionAndCannotBeReopenedToBypassIt(): void
    {
        $this->database->exec("UPDATE events SET closed = 0, date = '2026-06-01' WHERE id = 101;
            UPDATE entries SET snapshot_at = NULL WHERE id = 401;
            UPDATE athletes SET last_name = 'After event', weight_kg = 100 WHERE id = 301");
        $this->revision(3, '2026-06-02 00:00:00', 'After event', 100);
        $rows = Entry::findByEvent(101, 201, false, '2026-06-01');
        self::assertSame('Revised', $rows[0]['last_name']);
        self::assertSame(45.5, $rows[0]['weight_kg']);
        self::assertStringNotContainsString('After event', (new EventEntriesCsvTransfer())->export(101, false));
        (new EntrySnapshotService($this->database))->consolidate(101, '2026-06-01');
        self::assertSame('Revised', Entry::findByEvent(101, 201, true)[0]['last_name']);
    }

    public function testUnknownHistoricalFactsNeverFallBackToCurrentProfileWhenAnEndedEventIsClosed(): void
    {
        $this->database->exec("UPDATE events SET closed = 0, date = '2020-01-01' WHERE id = 101;
            UPDATE entries SET snapshot_at = NULL WHERE id = 401");
        $rows = Entry::findByEvent(101, 201, false, '2020-01-01');
        self::assertNull($rows[0]['last_name']);
        self::assertSame('', $rows[0]['weight_category']);
        self::assertNull($this->service->details(101, 401, null)['current']['last_name']);
        (new EntrySnapshotService($this->database))->consolidate(101, '2020-01-01');
        self::assertNull(Entry::findByEvent(101, 201, true)[0]['weight_category']);
    }

    /** @return iterable<string, array{string, string}> */
    public static function rejectedRequests(): iterable
    {
        yield 'wrong club' => ['foreign_club', 'not_found'];
        yield 'wrong event' => ['foreign_event', 'not_found'];
        yield 'wrong athlete revision' => ['foreign_revision', 'revision'];
        yield 'after cutoff' => ['after_cutoff', 'revision'];
        yield 'stale form' => ['stale', 'stale'];
        yield 'missing reason' => ['reason', 'reason'];
        yield 'duplicate pending' => ['pending', 'pending'];
        yield 'unchanged data' => ['unchanged', 'unchanged'];
        yield 'open event' => ['live', 'live'];
        yield 'club self approval' => ['self_approval', 'invalid'];
    }

    #[DataProvider('rejectedRequests')]
    public function testInvalidRequestsCannotChangeTheTournament(string $case, string $expected): void
    {
        $details = $this->service->details(101, 401, 201);
        $clubId = $case === 'foreign_club' ? 202 : 201;
        $eventId = $case === 'foreign_event' ? 102 : 101;
        $revisionId = $case === 'foreign_revision' ? 2 : 1;
        if ($case === 'after_cutoff') {
            $this->database->exec("UPDATE events SET date = '2020-01-01' WHERE id = 101");
        } elseif ($case === 'pending') {
            $this->submitRequest();
        } elseif ($case === 'unchanged') {
            $this->database->exec("UPDATE athlete_revisions SET last_name = 'Original', weight_kg = 40 WHERE id = 1");
            $this->database->exec("UPDATE entries SET snapshot_weight_category = '-60 kg' WHERE id = 401");
            $details = $this->service->details(101, 401, 201);
        } elseif ($case === 'live') {
            $this->database->exec('UPDATE events SET closed = 0 WHERE id = 101; UPDATE entries SET snapshot_at = NULL WHERE id = 401');
        }
        try {
            $this->service->requestRevision(
                $eventId,
                401,
                $revisionId,
                $clubId,
                $case === 'reason' ? '' : 'Review data',
                $case === 'stale' ? 'stale' : $details['fingerprint'],
                $case === 'self_approval'
            );
            self::fail('Request should be rejected');
        } catch (EntryDataReviewException $exception) {
            self::assertSame($expected, $exception->getMessage());
        }
        self::assertSame('Original', $this->database->query('SELECT snapshot_last_name FROM entries WHERE id = 401')->fetchColumn());
        self::assertFalse($this->database->inTransaction());
    }

    public function testConflictingAdminCorrectionMakesAPendingRequestStale(): void
    {
        $this->submitRequest();
        $details = $this->service->details(101, 401, null);
        $data = $details['current'];
        $data['last_name'] = 'Admin correction';
        $this->service->edit(101, 401, $data, 'Corrected independently', $details['fingerprint']);
        try {
            $this->service->decide(101, 401, 1, true, 'Try obsolete approval');
            self::fail('Approval must detect stale data');
        } catch (EntryDataReviewException $exception) {
            self::assertSame('stale', $exception->getMessage());
        }
        self::assertSame('Admin correction', $this->service->details(101, 401, null)['current']['last_name']);
        $this->service->decide(101, 401, 1, false, 'Superseded by correction');
        self::assertCount(0, $this->service->pending(101));
    }

    public function testApprovalIsOneTimeAndRollsBackIfTheSnapshotWriteFails(): void
    {
        $this->submitRequest();
        $this->database->exec("CREATE TRIGGER fail_review BEFORE UPDATE OF snapshot_last_name ON entries
            BEGIN SELECT RAISE(FAIL, 'Synthetic write failure'); END");
        try {
            $this->service->decide(101, 401, 1, true, 'Verify rollback');
            self::fail('Expected snapshot write failure');
        } catch (\PDOException) {
            self::assertSame('pending', $this->service->details(101, 401, null)['history'][0]['status']);
        }
        $this->database->exec('DROP TRIGGER fail_review');
        $this->service->decide(101, 401, 1, true, 'Successful retry');
        $this->expectExceptionMessage('not_pending');
        $this->service->decide(101, 401, 1, true, 'Repeated approval');
    }

    public function testNewAdminCorrectionsAfterTheCutoffAreRejected(): void
    {
        $this->database->exec("UPDATE events SET date = '2026-06-01' WHERE id = 101");
        $details = $this->service->details(101, 401, null);
        $this->expectExceptionMessage('cutoff');
        $this->service->edit(101, 401, $details['current'], 'Late edit', $details['fingerprint']);
    }

    public function testEndedClosedEventCannotAcquireANewLiveSnapshot(): void
    {
        $this->database->exec("UPDATE events SET date = '2020-01-01' WHERE id = 101;
            UPDATE entries SET snapshot_at = NULL, snapshot_last_name = NULL WHERE id = 401");
        try {
            (new EntrySnapshotService($this->database))->consolidateClosedEntry(101, 201, 301);
            self::fail('Ended events cannot import newly registered live facts');
        } catch (\RuntimeException) {
            self::assertNull($this->database->query('SELECT snapshot_last_name FROM entries WHERE id = 401')->fetchColumn());
        }
    }

    public function testRevisionsAtOrAfterRomeMidnightAreExcluded(): void
    {
        $this->database->exec("UPDATE events SET date = '2026-07-01' WHERE id = 101");
        $this->revision(3, '2026-07-01 21:59:59.999999', 'Last eligible', 45);
        $this->revision(4, '2026-07-01 22:00:00', 'Too late', 60);
        $details = $this->service->details(101, 401, 201);
        self::assertSame([3, 1], array_column($details['revisions'], 'id'));
        try {
            $this->service->requestRevision(101, 401, 4, 201, 'Too late', $details['fingerprint']);
            self::fail('Midnight revision must be excluded');
        } catch (EntryDataReviewException $exception) {
            self::assertSame('revision', $exception->getMessage());
        }
        $this->service->requestRevision(101, 401, 3, null, 'Eligible before midnight', $details['fingerprint'], true);
        self::assertSame('Last eligible', $this->service->details(101, 401, 201)['current']['last_name']);
    }

    public function testControllerReportsStorageFailureWithoutDisclosingDatabaseDetails(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'synthetic-admin'));
        $this->database->exec("CREATE TRIGGER fail_review BEFORE UPDATE OF snapshot_last_name ON entries
            BEGIN SELECT RAISE(FAIL, 'Synthetic private database failure'); END");
        $details = $this->service->details(101, 401, null);
        $post = $details['current'];
        $post['last_name'] = 'HTTP correction';
        $request = new Request('POST', '/admin/events/entry-data', [], array_merge($post, [
            'event_id' => '101', 'entry_id' => '401', 'csrf_token' => csrf_token(),
            'operation' => 'edit', 'fingerprint' => $details['fingerprint'], 'reason' => 'Test failure',
        ]));
        $logger = $this->createMock(\App\Core\Logger::class);
        $logger->expects(self::once())->method('error');
        $response = (new EntryDataController(new View(dirname(__DIR__) . '/views'), $request, $logger))->adminReview($request);
        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('Synthetic private database failure', $response->content());
        self::assertSame('Original', $this->service->details(101, 401, null)['current']['last_name']);
    }

    public function testControllerShowsDiffAndAllowsClubRequestThenAdminApproval(): void
    {
        Session::authenticateClub(201, hash('sha256', 'synthetic-credential'));
        $get = $this->dispatch(false, 'GET');
        self::assertSame(200, $get->status());
        self::assertStringContainsString('Proposed values', $get->content());
        self::assertStringNotContainsString('Approve and save correction', $get->content());
        $details = $this->service->details(101, 401, 201);
        $request = $this->dispatch(false, 'POST', [
            'operation' => 'request', 'revision_id' => '1', 'fingerprint' => $details['fingerprint'], 'reason' => 'Update weight',
        ]);
        self::assertSame(303, $request->status());
        Session::authenticateAdministrator(hash('sha256', 'synthetic-admin'));
        self::assertSame(200, $this->dispatch(true, 'GET')->status());
        $response = $this->dispatch(true, 'POST', ['operation' => 'approve', 'review_id' => '1', 'reason' => 'Verified']);
        self::assertSame(303, $response->status());
        self::assertSame('Revised', $this->service->details(101, 401, null)['current']['last_name']);
    }

    public function testAdminControllerValidatesEditsAndRejectsStaleOrInvalidActions(): void
    {
        Session::authenticateAdministrator(hash('sha256', 'synthetic-admin'));
        $details = $this->service->details(101, 401, null);
        self::assertSame(422, $this->dispatch(true, 'POST', ['operation' => 'edit'])->status());
        self::assertSame(409, $this->dispatch(true, 'POST', ['operation' => 'invalid'])->status());
        self::assertSame(409, $this->dispatch(true, 'POST', ['operation' => ['edit'], 'reason' => ['invalid']])->status());
        $post = $details['current'] + ['operation' => 'edit', 'reason' => 'Correct name', 'fingerprint' => $details['fingerprint']];
        $post['last_name'] = 'Admin HTTP correction';
        self::assertSame(303, $this->dispatch(true, 'POST', $post)->status());
        self::assertSame(409, $this->dispatch(true, 'POST', $post)->status());
        $fresh = $this->service->details(101, 401, null);
        self::assertSame(303, $this->dispatch(true, 'POST', [
            'operation' => 'apply_revision', 'revision_id' => '1', 'reason' => 'Use stored revision', 'fingerprint' => $fresh['fingerprint'],
        ])->status());
    }

    public function testClubMutationRequiresCurrentAgreementsAndCannotApprove(): void
    {
        Session::authenticateClub(201, hash('sha256', 'synthetic-credential'));
        self::assertSame(409, $this->dispatch(false, 'POST', ['operation' => 'approve'])->status());
        $this->database->exec('DELETE FROM club_terms_acceptances');
        self::assertSame('/clubs/agreements', $this->dispatch(false, 'POST', ['operation' => 'request'])->headers()['Location']);
    }

    public function testMutationRequiresCsrfAndForeignEntryReturns404(): void
    {
        Session::authenticateClub(201, hash('sha256', 'synthetic-credential'));
        try {
            $this->dispatch(false, 'POST', ['csrf_token' => 'invalid']);
            self::fail('CSRF must be enforced');
        } catch (HttpException $exception) {
            self::assertSame(419, $exception->statusCode());
        }
        $this->expectExceptionCode(404);
        $this->dispatch(false, 'GET', ['entry_id' => '403']);
    }

    public function testAnonymousAndClubPrincipalsCannotUseAdminActions(): void
    {
        self::assertSame('/admin/login', $this->dispatch(true, 'GET')->headers()['Location']);
        self::assertSame('/clubs/login', $this->dispatch(false, 'GET')->headers()['Location']);
        Session::authenticateClub(201, hash('sha256', 'synthetic-credential'));
        self::assertSame('/admin/login', $this->dispatch(true, 'POST')->headers()['Location']);
    }

    public function testPendingAndOutlierPagesAreReadOnlyAndAdministratorOnly(): void
    {
        $this->submitRequest();
        Session::authenticateAdministrator(hash('sha256', 'synthetic-admin'));
        $request = new Request('GET', '/admin/events/pending-updates', ['event_id' => '101']);
        $controller = new EntryDataController(new View(dirname(__DIR__) . '/views'), $request);
        self::assertStringContainsString('Synthetic Club', $controller->pending($request)->content());
        $this->database->exec("UPDATE entries SET snapshot_weight_kg = 500 WHERE id = 401");
        self::assertStringContainsString('review range', $controller->outliers($request)->content());
        Session::destroy();
        self::assertSame('/admin/login', $controller->pending($request)->headers()['Location']);
        self::assertSame('/admin/login', $controller->outliers($request)->headers()['Location']);
    }

    public function testReviewHistoryCascadesWithEntryRetentionAndUnneededRevisionsExpire(): void
    {
        $this->database->exec('PRAGMA foreign_keys = ON');
        $this->submitRequest();
        $this->revision(3, '2026-06-02 00:00:00', 'Newest', 50);
        $retention = new AthleteRevisionRetentionService($this->database);
        self::assertSame(0, $retention->purgeBefore('2026-06-01 00:00:00'));
        self::assertSame(2, (new EntrySnapshotRetentionService($this->database))->purgeBefore('2026-01-02 00:00:00'));
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM entry_data_reviews')->fetchColumn());
        self::assertSame(1, $retention->purgeBefore('2026-06-01 00:00:00'));
        self::assertSame(0, $retention->purgeBefore('2026-06-01 00:00:00'));
    }

    /** @param array<string, mixed> $post */
    private function dispatch(bool $admin, string $method, array $post = []): Response
    {
        $path = $admin ? '/admin/events/entry-data' : '/events/entry-data';
        $values = array_merge(['event_id' => '101', 'entry_id' => '401', 'csrf_token' => csrf_token()], $post);
        $request = new Request($method, $path, $method === 'GET' ? $values : [], $method === 'POST' ? $values : []);
        $controller = new EntryDataController(new View(dirname(__DIR__) . '/views'), $request);

        return $admin ? $controller->adminReview($request) : $controller->clubReview($request);
    }

    private function submitRequest(): void
    {
        $details = $this->service->details(101, 401, 201);
        $this->service->requestRevision(101, 401, 1, 201, 'Review corrected facts', $details['fingerprint']);
    }

    private function revision(int $id, string $time, string $lastName, float $weight): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO athlete_revisions
             (id, athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, recorded_at)
             VALUES (?, 301, 201, ?, \'Athlete\', \'M\', \'2012-01-01\', ?, \'green\', \'SYN-301\', ?)'
        );
        $statement->execute([$id, $lastName, $weight, $time]);
    }

    private function seed(): void
    {
        $this->database->exec("INSERT INTO clubs (id, name, federal_code, email) VALUES
            (201, 'Synthetic Club', 'SYN-201', 'club@example.test'), (202, 'Other Club', 'SYN-202', 'other@example.test');
            INSERT INTO events (id, name, date, closed, published) VALUES
            (101, 'Synthetic Event', '2099-06-01', 1, 1), (102, 'Other Event', '2099-06-02', 1, 1);
            INSERT INTO athletes (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number)
            VALUES (301, 201, 'Revised', 'Athlete', 'M', '2012-01-01', 45.5, 'green', 'SYN-301'),
            (302, 202, 'Foreign', 'Athlete', 'F', '2013-01-01', 40, 'yellow', 'SYN-302');
            INSERT INTO entries (id, event_id, club_id, athlete_id, snapshot_last_name, snapshot_first_name,
                snapshot_gender, snapshot_birth_date, snapshot_weight_kg, snapshot_belt, snapshot_membership_number,
                snapshot_program, snapshot_weight_category, snapshot_at)
            VALUES (401, 101, 201, 301, 'Original', 'Athlete', 'M', '2012-01-01', 40, 'green', 'SYN-301',
                'competitive', '-60 kg', '2026-01-01 00:00:00'),
            (402, 102, 201, 301, 'Other tournament', 'Athlete', 'M', '2012-01-01', 40, 'green', 'SYN-301',
                'competitive', '-60 kg', '2026-01-01 00:00:00'),
            (403, 101, 202, 302, 'Foreign', 'Athlete', 'F', '2013-01-01', 40, 'yellow', 'SYN-302',
                'competitive', '-48 kg', '2026-06-01 00:00:00')");
        $this->revision(1, '2026-05-01 12:00:00', 'Revised', 45.5);
        $this->database->exec("INSERT INTO athlete_revisions
            (id, athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, recorded_at)
            VALUES (2, 302, 202, 'Foreign', 'Athlete', 'F', '2013-01-01', 40, 'yellow', 'SYN-302', '2026-05-01 12:00:00')");
        $this->database->prepare('INSERT INTO club_data_rights_declarations VALUES (201, ?)')->execute([ClubDataRightsDeclaration::VERSION]);
        $this->database->prepare('INSERT INTO club_terms_acceptances VALUES (201, ?)')->execute([ClubTermsAcceptance::VERSION]);
    }

    private function schema(): void
    {
        $this->database->exec('CREATE TABLE clubs (
            id INTEGER PRIMARY KEY, name TEXT, federal_code TEXT, email TEXT, phone TEXT DEFAULT \'\',
            contact_first_name TEXT DEFAULT \'\', contact_last_name TEXT DEFAULT \'\', contact_phone TEXT DEFAULT \'\',
            contact_email TEXT, affiliation TEXT DEFAULT \'\', recovery_email TEXT, password_hash TEXT DEFAULT \'synthetic\', approved_at TEXT
        );
        CREATE TABLE events (id INTEGER PRIMARY KEY, name TEXT, date TEXT, closed INTEGER, published INTEGER,
            location TEXT DEFAULT \'Synthetic Venue\', organizer TEXT, registration_deadline TEXT, type TEXT,
            description TEXT, notes TEXT, poster_file TEXT, info_file TEXT);
        CREATE TABLE athletes (id INTEGER PRIMARY KEY, club_id INTEGER, last_name TEXT, first_name TEXT,
            gender TEXT, birth_date TEXT, weight_kg REAL, belt TEXT, membership_number TEXT, notes TEXT);
        CREATE TABLE entries (id INTEGER PRIMARY KEY, event_id INTEGER, club_id INTEGER, athlete_id INTEGER,
            snapshot_last_name TEXT, snapshot_first_name TEXT, snapshot_gender TEXT, snapshot_birth_date TEXT,
            snapshot_weight_kg REAL, snapshot_belt TEXT, snapshot_membership_number TEXT, snapshot_program TEXT,
            snapshot_weight_category TEXT, snapshot_at TEXT);
        CREATE TABLE club_data_rights_declarations (club_id INTEGER, declaration_version TEXT);
        CREATE TABLE club_terms_acceptances (club_id INTEGER, terms_version TEXT)');
    }
}
