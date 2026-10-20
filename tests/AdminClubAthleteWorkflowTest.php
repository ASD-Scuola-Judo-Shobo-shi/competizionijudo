<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\AdminController;
use App\Controller\AdminAthleteController;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Model\Event;
use App\Presentation\Navigation;
use PDO;
use PDOStatement;
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

        $this->database = new class ('sqlite::memory:') extends PDO {
            public int $queryCount = 0;

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->queryCount++;

                return parent::prepare($query, $options);
            }
        };
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->database->sqliteCreateFunction('LAST_INSERT_ID', fn(): string => $this->database->lastInsertId());
        $this->createSchema();
        $this->seedData();
        $this->databaseConnection->setValue(null, $this->database);

        $this->destroySession();
        Session::start();
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
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
        self::assertStringContainsString(
            '<th scope="col" data-sort-key="federal_code">Code</th>',
            $response->content()
        );
        self::assertStringContainsString('data-label="Federal code"', $response->content());
        self::assertMatchesRegularExpression('/title="2 athletes in archive">\s*2\s*<\/strong>/', $response->content());

        $sortedRequest = new Request('GET', '/admin/clubs', [
            'sort' => 'athletes',
            'direction' => 'desc',
        ]);
        $sortedResponse = (new AdminController($this->view, $sortedRequest))->manageClubs($sortedRequest);
        $ownClubPosition = strpos($sortedResponse->content(), 'Synthetic Club');
        $foreignClubPosition = strpos($sortedResponse->content(), 'Foreign Club');
        self::assertIsInt($ownClubPosition);
        self::assertIsInt($foreignClubPosition);
        self::assertLessThan($foreignClubPosition, $ownClubPosition);
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
        self::assertStringContainsString('/admin/clubs/athletes/edit?club_id=201', $response->content());
        self::assertStringContainsString('club_id=201&amp;athlete_id=301', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes/delete?club_id=201', $response->content());
        self::assertStringContainsString('/admin/clubs/athletes/import?club_id=201', $response->content());
        self::assertStringNotContainsString('/clubs/athletes-import', $response->content());
    }

    public function testAdministratorCanAddAthletesToAnyClubIncludingUnapprovedClubs(): void
    {
        foreach ([201, 202] as $clubId) {
            $get = new Request('GET', '/admin/clubs/athletes/edit', ['club_id' => $clubId]);
            $form = (new AdminAthleteController($this->view, $get))->edit($get);
            self::assertSame(200, $form->status());
            self::assertStringContainsString('name="membership_number"', $form->content());
            self::assertStringContainsString('name="notes"', $form->content());

            $request = $this->athleteRequest('edit', $clubId, [
                'club_id' => 999,
                'last_name' => 'New ' . $clubId,
            ]);
            $response = (new AdminAthleteController($this->view, $request))->edit($request);

            self::assertSame(303, $response->status());
            self::assertSame('/admin/clubs/athletes?club_id=' . $clubId, $response->headers()['Location']);
            $statement = $this->database->prepare('SELECT club_id FROM athletes WHERE last_name = ?');
            $statement->execute(['New ' . $clubId]);
            self::assertSame($clubId, (int) $statement->fetchColumn());
        }
        self::assertSame(5, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
    }

    public function testAdministratorCanEditAllAthleteFieldsWithinEitherSelectedClub(): void
    {
        foreach ([201 => 301, 202 => 303] as $clubId => $athleteId) {
            $get = new Request('GET', '/admin/clubs/athletes/edit', [
                'club_id' => $clubId, 'athlete_id' => $athleteId,
            ]);
            $form = (new AdminAthleteController($this->view, $get))->edit($get);
            self::assertSame(200, $form->status());
            self::assertStringContainsString('value="' . ($clubId === 201 ? 'Own' : 'Foreign') . '"', $form->content());

            $request = $this->athleteRequest('edit', $clubId, [
                'club_id' => 999,
                'weight_kg' => '54,5',
                'notes' => 'Updated <notes>',
            ], $athleteId);
            $response = (new AdminAthleteController($this->view, $request))->edit($request);

            self::assertSame(303, $response->status());
            $athlete = $this->database->query('SELECT * FROM athletes WHERE id = ' . $athleteId)->fetch();
            self::assertSame($clubId, (int) $athlete['club_id']);
            self::assertSame('Synthetic', $athlete['last_name']);
            self::assertSame('Updated', $athlete['first_name']);
            self::assertSame('F', $athlete['gender']);
            self::assertSame('2013-04-05', $athlete['birth_date']);
            self::assertSame(54.5, (float) $athlete['weight_kg']);
            self::assertSame('blue', $athlete['belt']);
            self::assertSame('SYN-NEW', $athlete['membership_number']);
            self::assertSame('Updated <notes>', $athlete['notes']);
        }
        self::assertSame('Second', $this->database->query('SELECT last_name FROM athletes WHERE id = 302')->fetchColumn());
    }

    public function testInvalidInputKeepsSubmittedValuesAndDoesNotChangeAthlete(): void
    {
        $request = $this->athleteRequest('edit', 201, [
            'last_name' => '<Submitted>', 'gender' => 'X', 'birth_date' => 'invalid', 'weight_kg' => '0',
            'belt' => 'invalid', 'notes' => str_repeat('x', 2001),
        ], 301);
        $response = (new AdminAthleteController($this->view, $request))->edit($request);

        self::assertSame(422, $response->status());
        self::assertStringContainsString('value="&lt;Submitted&gt;"', $response->content());
        self::assertStringContainsString(__('validation.athlete_weight_invalid'), $response->content());
        self::assertStringContainsString(__('validation.athlete_notes_too_long'), $response->content());
        self::assertSame('Own', $this->database->query('SELECT last_name FROM athletes WHERE id = 301')->fetchColumn());
    }

    public function testAthletesFromAnotherClubCannotBeReadChangedOrDeletedUnderSelectedClub(): void
    {
        foreach (['GET', 'POST'] as $method) {
            foreach ([0, 303, 999] as $athleteId) {
                $request = $method === 'POST'
                    ? $this->athleteRequest('edit', 201, [], $athleteId)
                    : new Request('GET', '/admin/clubs/athletes/edit', [
                        'club_id' => 201, 'athlete_id' => $athleteId,
                    ]);
                try {
                    (new AdminAthleteController($this->view, $request))->edit($request);
                    self::fail('An invalid or foreign athlete was accepted.');
                } catch (HttpException $exception) {
                    self::assertSame(404, $exception->statusCode());
                }
            }
        }
        $request = $this->athleteRequest('delete', 201, ['athlete_id' => 303]);
        try {
            (new AdminAthleteController($this->view, $request))->delete($request);
            self::fail('A foreign athlete was accepted for deletion.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->statusCode());
        }
        self::assertSame('Foreign', $this->database->query('SELECT last_name FROM athletes WHERE id = 303')->fetchColumn());
    }

    public function testAdministratorCanDeleteAthleteWhilePreservingClosedEntrySnapshot(): void
    {
        $this->database->exec('PRAGMA foreign_keys = ON');
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY,
                athlete_id INTEGER REFERENCES athletes(id) ON DELETE SET NULL,
                snapshot_last_name TEXT
            )'
        );
        $this->database->exec("INSERT INTO entries VALUES (1, 303, 'Historical Synthetic')");
        $request = $this->athleteRequest('delete', 202, ['athlete_id' => 303]);

        $response = (new AdminAthleteController($this->view, $request))->delete($request);

        self::assertSame(303, $response->status());
        self::assertSame('/admin/clubs/athletes?club_id=202', $response->headers()['Location']);
        self::assertSame(2, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
        self::assertSame(
            ['athlete_id' => null, 'snapshot_last_name' => 'Historical Synthetic'],
            $this->database->query('SELECT athlete_id, snapshot_last_name FROM entries')->fetch()
        );
    }

    public function testAdministratorImportsCreateAndUpdateOnlyTheSelectedClub(): void
    {
        foreach ([201 => 'OWN-001', 202 => 'FOREIGN-001'] as $clubId => $membership) {
            $response = $this->importCsv($clubId, implode("\n", [
                'last_name,first_name,gender,birth_date,weight_kg,belt,membership_number',
                'Revised,Athlete,F,2013-04-05,44,blue,' . $membership,
                'New,Athlete,F,2013-04-05,42,yellow,NEW-' . $clubId,
                'Invalid,Athlete,X,invalid,0,invalid,INVALID',
            ]));
            self::assertSame(303, $response->status());
            self::assertSame('/admin/clubs/athletes?club_id=' . $clubId, $response->headers()['Location']);
            $request = new Request('GET', '/admin/clubs/athletes', ['club_id' => $clubId]);
            $page = (new AdminController($this->view, $request))->clubAthletes($request);
            self::assertStringContainsString('1 added, 1 updated', $page->content());
            self::assertStringContainsString('1 skipped', $page->content());
            self::assertStringContainsString('Row 4', $page->content());
            self::assertStringContainsString('Revised Athlete', $page->content());
        }
        self::assertSame(5, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
        self::assertSame('Second', $this->database->query('SELECT last_name FROM athletes WHERE id = 302')->fetchColumn());
    }

    public function testImportRejectsForeignAthleteIdsAndReportLinksStayInAdminClub(): void
    {
        $this->importCsv(201, implode("\n", [
            'athlete_id,last_name,first_name,gender,birth_date,weight_kg,belt,membership_number',
            '303,Foreign,Athlete,F,2012-03-03,44,blue,',
            '301,Own,Athlete,M,2010-01-01,51,green_blue,OWN-001',
        ]));
        $request = new Request('GET', '/admin/clubs/athletes', ['club_id' => 201]);
        $page = (new AdminController($this->view, $request))->clubAthletes($request);

        self::assertStringContainsString('1 skipped', $page->content());
        self::assertSame(42.0, (float) $this->database->query('SELECT weight_kg FROM athletes WHERE id = 303')->fetchColumn());
        self::assertStringNotContainsString('/clubs/area?view=add', $page->content());
        self::assertStringNotContainsString('athlete_id=303', $page->content());
    }

    public function testIncompleteImportCanBeReviewedAndExplicitlyMergedInSelectedClub(): void
    {
        $csv = "last_name,first_name,gender,birth_date,weight_kg,belt,membership_number\n"
            . 'Renamed,Athlete,,2010-01-01,51,,OWN-001';
        $this->importCsv(201, $csv);
        $request = new Request('GET', '/admin/clubs/athletes', ['club_id' => 201]);
        $page = (new AdminController($this->view, $request))->clubAthletes($request);
        $document = new \DOMDocument();
        @$document->loadHTML($page->content());
        $links = (new \DOMXPath($document))->query('//div[@class="import-report"]//a/@href');
        self::assertNotFalse($links);
        self::assertSame('/admin/clubs/athletes/edit?club_id=201&athlete_id=301', $links->item(0)?->nodeValue);

        $this->importCsv(201, $csv, true);
        $feedback = Session::pullFlash(AdminAthleteController::importFeedbackKey(201));
        self::assertSame('success', $feedback['type']);
        self::assertSame('Renamed', $this->database->query('SELECT last_name FROM athletes WHERE id = 301')->fetchColumn());
        self::assertSame('green_blue', $this->database->query('SELECT belt FROM athletes WHERE id = 301')->fetchColumn());
    }

    public function testImportUploadFailuresAreReportedWithoutChangingData(): void
    {
        $cases = [
            [['error' => UPLOAD_ERR_PARTIAL], 'club.area.csv.upload_failed'],
            [['error' => UPLOAD_ERR_OK, 'size' => 2_097_153], 'club.area.csv.too_large'],
            [['error' => UPLOAD_ERR_OK, 'tmp_name' => '/nonexistent/synthetic.csv'], 'club.area.csv.upload_failed'],
        ];
        foreach ($cases as [$file, $key]) {
            $request = new Request('POST', '/admin/clubs/athletes/import', ['club_id' => 201], [
                'csrf_token' => csrf_token(),
            ], [], null, ['athletes_file' => $file]);
            $response = (new AdminAthleteController($this->view, $request))->import($request);
            self::assertSame(303, $response->status());
            $feedback = Session::pullFlash(AdminAthleteController::importFeedbackKey(201));
            self::assertSame('error', $feedback['type']);
            self::assertSame(__($key), $feedback['message']);
        }
        $this->importCsv(201, "unsupported,headings\nsynthetic,values");
        $feedback = Session::pullFlash(AdminAthleteController::importFeedbackKey(201));
        self::assertSame(__('club.area.csv.invalid_header'), $feedback['message']);
        self::assertSame(3, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
    }

    public function testLargeImportReportsAreCappedAndShowTheOmittedCount(): void
    {
        $csv = ['last_name,first_name,gender,birth_date,weight_kg,belt,membership_number'];
        for ($index = 0; $index < 205; $index++) {
            $csv[] = 'Synthetic' . $index . ',Athlete,X,invalid,0,invalid,INVALID-' . $index;
        }
        $this->importCsv(201, implode("\n", $csv));
        $feedback = Session::pullFlash(AdminAthleteController::importFeedbackKey(201));
        self::assertSame('error', $feedback['type']);
        self::assertCount(200, $feedback['report']);
        self::assertSame(5, $feedback['omitted']);
    }

    public function testQuotaAppliesToAdminCreationAndImportWhileAllowingEdits(): void
    {
        $original = $_ENV['CLUB_ATHLETE_LIMIT'] ?? null;
        $_ENV['CLUB_ATHLETE_LIMIT'] = '2';
        try {
            $create = $this->athleteRequest('edit', 201);
            $response = (new AdminAthleteController($this->view, $create))->edit($create);
            self::assertSame(422, $response->status());
            self::assertStringContainsString(__('errors.athlete_quota_exceeded', ['limit' => '2']), $response->content());
            $this->importCsv(201, "last_name,first_name,gender,birth_date,weight_kg,belt\nNew,Athlete,F,2013-04-05,44,blue");
            $feedback = Session::pullFlash(AdminAthleteController::importFeedbackKey(201));
            self::assertSame('error', $feedback['type']);
            $edit = $this->athleteRequest('edit', 201, [], 301);
            self::assertSame(303, (new AdminAthleteController($this->view, $edit))->edit($edit)->status());
            self::assertSame(3, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
        } finally {
            if ($original === null) {
                unset($_ENV['CLUB_ATHLETE_LIMIT']);
            } else {
                $_ENV['CLUB_ATHLETE_LIMIT'] = $original;
            }
        }
    }

    public function testAdminAthleteRoutesRejectAnonymousAndClubUsersAndRequireCsrf(): void
    {
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);
        foreach (['anonymous', 'club'] as $role) {
            $this->destroySession();
            Session::start();
            if ($role === 'club') {
                Session::authenticateClub(201, hash('sha256', 'synthetic-club-credential'));
            }
            foreach (['edit', 'delete', 'import'] as $action) {
                $request = $this->athleteRequest($action, 201);
                $response = $router->dispatch($request);
                self::assertSame('/admin/login', $response->headers()['Location']);
                $controller = new AdminAthleteController($this->view, $request);
                self::assertSame('/admin/login', $controller->$action($request)->headers()['Location']);
            }
            $response = $router->dispatch(new Request('GET', '/admin/clubs/athletes/edit', ['club_id' => 201]));
            self::assertSame('/admin/login', $response->headers()['Location']);
        }

        Session::authenticateAdministrator(hash('sha256', 'synthetic-administrator-credential'));
        $database = $this->createMock(PDO::class);
        $database->expects(self::never())->method('prepare');
        $this->databaseConnection->setValue(null, $database);
        foreach (['edit', 'delete', 'import'] as $action) {
            $request = $this->athleteRequest($action, 201, ['csrf_token' => 'synthetic-invalid']);
            try {
                $router->dispatch($request);
                self::fail('An invalid CSRF token was accepted.');
            } catch (HttpException $exception) {
                self::assertSame(419, $exception->statusCode());
            }
        }
    }

    public function testDeleteAndImportHaveNoGetRouteAndUnknownClubsCannotBeChanged(): void
    {
        $router = new Router($this->view);
        (require dirname(__DIR__) . '/routes/web.php')($router);
        foreach (['delete', 'import'] as $action) {
            try {
                $router->dispatch(new Request('GET', '/admin/clubs/athletes/' . $action, ['club_id' => 201]));
                self::fail('A state-changing GET route exists.');
            } catch (HttpException $exception) {
                self::assertSame(405, $exception->statusCode());
            }
        }
        foreach (['edit', 'delete', 'import'] as $action) {
            $request = $this->athleteRequest($action, 999);
            self::assertSame('/admin/clubs', $router->dispatch($request)->headers()['Location']);
        }
    }

    public function testImportFeedbackIsKeptForTheSelectedClub(): void
    {
        $request = $this->athleteRequest('import', 201);
        (new AdminAthleteController($this->view, $request))->import($request);
        $foreign = new Request('GET', '/admin/clubs/athletes', ['club_id' => 202]);
        $foreignPage = (new AdminController($this->view, $foreign))->clubAthletes($foreign);
        self::assertStringNotContainsString(__('club.area.csv.file_required'), $foreignPage->content());

        $own = new Request('GET', '/admin/clubs/athletes', ['club_id' => 201]);
        $ownPage = (new AdminController($this->view, $own))->clubAthletes($own);
        self::assertStringContainsString(__('club.area.csv.file_required'), $ownPage->content());
    }

    public function testStorageFailuresAreLoggedWithoutExposingDetails(): void
    {
        $this->database->exec("CREATE TRIGGER fail_update BEFORE UPDATE ON athletes BEGIN SELECT RAISE(ABORT, 'synthetic database details'); END");
        $this->database->exec("CREATE TRIGGER fail_delete BEFORE DELETE ON athletes BEGIN SELECT RAISE(ABORT, 'synthetic database details'); END");
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::exactly(2))->method('error');
        $edit = $this->athleteRequest('edit', 201, [], 301);
        $response = (new AdminAthleteController($this->view, $edit, $logger))->edit($edit);
        self::assertSame(422, $response->status());
        self::assertStringContainsString(__('errors.save_failed'), $response->content());
        self::assertStringNotContainsString('synthetic database details', $response->content());
        $delete = $this->athleteRequest('delete', 201, ['athlete_id' => 301]);
        self::assertSame(303, (new AdminAthleteController($this->view, $delete, $logger))->delete($delete)->status());
        $feedback = Session::pullFlash(AdminAthleteController::feedbackKey(201));
        self::assertSame('error', $feedback['type']);
        self::assertSame(3, (int) $this->database->query('SELECT COUNT(*) FROM athletes')->fetchColumn());
    }

    public function testAdminAthletePageQueryCountStaysConstantAndRowsRemainPaginated(): void
    {
        $request = new Request('GET', '/admin/clubs/athletes', ['club_id' => 201]);
        $this->database->queryCount = 0;
        (new AdminController($this->view, $request))->clubAthletes($request);
        self::assertSame(3, $this->database->queryCount);
        for ($index = 0; $index < 75; $index++) {
            $this->database->prepare(
                'INSERT INTO athletes (club_id, last_name, first_name, gender, birth_date, weight_kg, belt)
                 VALUES (201, ?, ?, ?, ?, ?, ?)'
            )->execute(['Synthetic ' . $index, 'Athlete', 'M', '2013-04-05', 44, 'blue']);
        }
        $this->database->queryCount = 0;
        $page = (new AdminController($this->view, $request))->clubAthletes($request);
        self::assertSame(3, $this->database->queryCount);
        self::assertSame(50, substr_count($page->content(), 'name="athlete_id"'));
        self::assertStringContainsString('page=2', $page->content());
    }

    /** @param array<string, mixed> $post */
    private function athleteRequest(string $action, int $clubId, array $post = [], ?int $athleteId = null): Request
    {
        $query = ['club_id' => $clubId];
        if ($athleteId !== null) {
            $query['athlete_id'] = $athleteId;
        }

        return new Request('POST', '/admin/clubs/athletes/' . $action, $query, array_merge([
            'csrf_token' => csrf_token(),
            'last_name' => 'Synthetic',
            'first_name' => 'Updated',
            'gender' => 'F',
            'birth_date' => '2013-04-05',
            'weight_kg' => '44',
            'belt' => 'blue',
            'membership_number' => 'SYN-NEW',
            'notes' => 'Synthetic notes',
        ], $post));
    }

    private function importCsv(int $clubId, string $csv, bool $mergeIncomplete = false): \App\Core\Response
    {
        $path = tempnam(sys_get_temp_dir(), 'admin-athletes-');
        self::assertNotFalse($path);
        file_put_contents($path, $csv);
        try {
            $request = new Request('POST', '/admin/clubs/athletes/import', ['club_id' => $clubId], [
                'csrf_token' => csrf_token(),
                'merge_incomplete' => $mergeIncomplete ? '1' : '0',
            ], [], null, ['athletes_file' => [
                'name' => 'synthetic.csv',
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($path),
            ]]);

            return (new AdminAthleteController($this->view, $request))->import($request);
        } finally {
            unlink($path);
        }
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
        self::assertStringContainsString(
            '<th scope="col" data-sort-key="visibility">Show</th>',
            $html
        );
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
                approved_at TEXT,
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
            'INSERT INTO clubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
            '2026-01-01 00:00:00',
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
