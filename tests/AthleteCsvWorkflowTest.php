<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubAreaController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Service\AthleteCsvTransfer;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AthleteCsvWorkflowTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;
    private View $view;

    /** @var list<string> */
    private array $temporaryFiles = [];

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
        $this->view = new View(dirname(__DIR__) . '/views');
        Localization::setLocale('en');
        $this->resetSession();
        Session::set('club_id', 201);
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->destroySession();
    }

    public function testExportIsClubScopedDownloadAndRoundTripsSpreadsheetFormulaValues(): void
    {
        $request = new Request('GET', '/club_athletes_export.csv');
        $response = (new ClubAreaController($this->view, $request))->exportAthletes($request);

        self::assertSame(200, $response->status());
        self::assertSame('text/csv; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertStringContainsString('attachment; filename="athletes-', $response->headers()['Content-Disposition']);
        self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
        self::assertStringStartsWith("\xEF\xBB\xBF", $response->content());

        $rows = $this->parseCsv($response->content());
        self::assertSame([
            'last_name',
            'first_name',
            'gender',
            'date_of_birth',
            'weight_kg',
            'belt',
            'membership_number',
            'notes',
        ], $rows[0]);
        self::assertSame('ExistingOwn', $rows[1][0]);
        self::assertSame("'=SUM(1,1)", $rows[1][7]);
        self::assertStringNotContainsString('HiddenForeign', $response->content());

        $path = $this->temporaryCsv($response->content());
        $result = (new AthleteCsvTransfer())->import($path, 201);

        self::assertSame(0, $result->created);
        self::assertSame(1, $result->updated);
        self::assertSame('=SUM(1,1)', $this->database->query(
            'SELECT notes FROM athletes WHERE id = 301'
        )->fetchColumn());
    }

    public function testImportUpdatesByMembershipAndCreatesRowsOnlyInsideCurrentClub(): void
    {
        $csv = implode("\n", [
            'last_name;first_name;gender;date_of_birth;weight_kg;belt;membership_number;notes',
            'UpdatedOwn;Athlete;M;2012-04-05;43,5;blue;OWN-001;updated',
            'NewOwn;Athlete;F;2013-05-06;39;yellow;NEW-002;new',
            'SameNumberAsForeign;Athlete;F;2014-06-07;37;white;FOREIGN-001;scoped',
        ]);
        $path = $this->temporaryCsv($csv);
        $request = $this->importRequest($path);

        $response = (new ClubAreaController($this->view, $request))->importAthletes($request);

        self::assertSame(302, $response->status());
        self::assertSame('/club_area.php?view=list', $response->headers()['Location']);
        self::assertSame('UpdatedOwn', $this->database->query(
            'SELECT last_name FROM athletes WHERE id = 301'
        )->fetchColumn());
        self::assertSame('43.5', (string) $this->database->query(
            'SELECT weight_kg FROM athletes WHERE id = 301'
        )->fetchColumn());
        self::assertSame(3, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
        self::assertSame('HiddenForeign', $this->database->query(
            'SELECT last_name FROM athletes WHERE id = 302'
        )->fetchColumn());

        $feedback = Session::pullFlash('athlete_csv_feedback');
        self::assertIsArray($feedback);
        self::assertSame('success', $feedback['type']);
        self::assertSame(
            __('club.area.csv.import_success', ['created' => '2', 'updated' => '1']),
            $feedback['message']
        );
    }

    public function testInvalidImportRejectsTheEntireFileWithoutPartialChanges(): void
    {
        $csv = implode("\n", [
            'last_name,first_name,gender,date_of_birth,weight_kg,belt,membership_number,notes',
            'WouldBeAdded,Athlete,F,2013-05-06,39,yellow,NEW-003,valid',
            'Invalid,Athlete,X,not-a-date,0,unknown,NEW-004,invalid',
        ]);
        $path = $this->temporaryCsv($csv);
        $request = $this->importRequest($path);

        $response = (new ClubAreaController($this->view, $request))->importAthletes($request);

        self::assertSame(302, $response->status());
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
        $feedback = Session::pullFlash('athlete_csv_feedback');
        self::assertIsArray($feedback);
        self::assertSame('error', $feedback['type']);
        self::assertStringContainsString('CSV row 3 is invalid', (string) $feedback['message']);
    }

    public function testImportRequiresAuthenticationAndCsrfProtection(): void
    {
        $path = $this->temporaryCsv(
            "last_name,first_name,gender,date_of_birth,weight_kg,belt,membership_number,notes\n"
            . "New,Athlete,M,2012-04-05,42,green,NEW-005,notes\n"
        );
        $request = new Request(
            'POST',
            '/club_athletes_import.php',
            [],
            [],
            [],
            null,
            ['athletes_csv' => $this->upload($path)]
        );

        $this->expectException(HttpException::class);
        (new ClubAreaController($this->view, $request))->importAthletes($request);
    }

    public function testCsvEndpointsRedirectAnonymousClubsToLogin(): void
    {
        $this->destroySession();
        $exportRequest = new Request('GET', '/club_athletes_export.csv');
        $importRequest = new Request('POST', '/club_athletes_import.php');

        $export = (new ClubAreaController($this->view, $exportRequest))->exportAthletes($exportRequest);
        $import = (new ClubAreaController($this->view, $importRequest))->importAthletes($importRequest);

        self::assertSame('/club_login.php', $export->headers()['Location']);
        self::assertSame('/club_login.php', $import->headers()['Location']);
    }

    private function importRequest(string $path): Request
    {
        return new Request(
            'POST',
            '/club_athletes_import.php',
            [],
            [
                'csrf_token' => csrf_token(),
                'return_view' => 'list',
            ],
            [],
            null,
            ['athletes_csv' => $this->upload($path)]
        );
    }

    /** @return array<string, int|string> */
    private function upload(string $path): array
    {
        return [
            'name' => 'athletes.csv',
            'type' => 'text/csv',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => (int) filesize($path),
        ];
    }

    private function temporaryCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'athlete-csv-');
        self::assertNotFalse($path);
        self::assertNotFalse(file_put_contents($path, $contents));
        $this->temporaryFiles[] = $path;

        return $path;
    }

    /** @return list<list<string|null>> */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
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
                contact_first_name TEXT NOT NULL,
                contact_last_name TEXT NOT NULL,
                contact_phone TEXT NOT NULL,
                contact_email TEXT,
                organization TEXT NOT NULL,
                recovery_email TEXT NOT NULL,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                date_of_birth TEXT NOT NULL,
                weight_kg REAL NOT NULL,
                belt TEXT NOT NULL,
                membership_number TEXT,
                notes TEXT
            )'
        );
    }

    private function seedData(): void
    {
        $club = $this->database->prepare(
            'INSERT INTO clubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $club->execute([
            201, 'SYN-201', 'Own Club', 'own@example.test', '', 'Own', 'Contact', '', '',
            'FIJLKAM', 'own@example.test', 'hash',
        ]);
        $club->execute([
            202, 'SYN-202', 'Foreign Club', 'foreign@example.test', '', 'Foreign', 'Contact', '', '',
            'FIJLKAM', 'foreign@example.test', 'hash',
        ]);

        $athlete = $this->database->prepare(
            'INSERT INTO athletes
             (id, club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt,
              membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $athlete->execute([
            301, 201, 'ExistingOwn', 'Athlete', 'M', '2012-04-05', 42.5, 'green',
            'OWN-001', '=SUM(1,1)',
        ]);
        $athlete->execute([
            302, 202, 'HiddenForeign', 'Athlete', 'F', '2013-05-06', 39.0, 'yellow',
            'FOREIGN-001', 'private',
        ]);
    }

    private function resetSession(): void
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
