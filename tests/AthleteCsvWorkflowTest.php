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
use ZipArchive;

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
            'Last name',
            'First name',
            'Gender',
            'Birth date',
            'Weight (kg)',
            'Belt',
            'Membership number',
            'Notes',
        ], $rows[0]);
        self::assertSame('ExistingOwn', $rows[1][0]);
        self::assertSame("'=SUM(1,1)", $rows[1][7]);
        self::assertStringNotContainsString('HiddenForeign', $response->content());

        $path = $this->temporaryCsv($response->content());
        $result = (new AthleteCsvTransfer())->import($path, 201);

        self::assertSame(0, $result->created);
        self::assertSame(0, $result->updated);
        self::assertSame(1, $result->unchanged);
        self::assertSame([], $result->issues);
        self::assertSame('=SUM(1,1)', $this->database->query(
            'SELECT notes FROM athletes WHERE id = 301'
        )->fetchColumn());
    }

    public function testExportHeadingsFollowTheItalianUiLanguage(): void
    {
        Localization::setLocale('it');
        $request = new Request('GET', '/clubs/athletes-export');

        $response = (new ClubAreaController($this->view, $request))->exportAthletes($request);
        $rows = $this->parseCsv($response->content());

        self::assertSame([
            'Cognome',
            'Nome',
            'Genere',
            'Data di nascita',
            'Peso (kg)',
            'Cintura',
            'Numero tessera',
            'Note',
        ], $rows[0]);
    }

    public function testImportUpdatesByMembershipAndCreatesRowsOnlyInsideCurrentClub(): void
    {
        $csv = implode("\n", [
            'last_name;first_name;gender;birth_date;weight_kg;belt;membership_number;notes',
            'UpdatedOwn;Athlete;M;2012-04-05;43,5;blue;OWN-001;updated',
            'NewOwn;Athlete;F;2013-05-06;39;yellow;NEW-002;new',
            'SameNumberAsForeign;Athlete;F;2014-06-07;37;white;FOREIGN-001;scoped',
        ]);
        $path = $this->temporaryCsv($csv);
        $request = $this->importRequest($path);

        $response = (new ClubAreaController($this->view, $request))->importAthletes($request);

        self::assertSame(302, $response->status());
        self::assertSame('/clubs/area?view=list', $response->headers()['Location']);
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
            __('club.area.csv.import_success', [
                'created' => '2',
                'updated' => '1',
                'unchanged' => '0',
                'skipped' => '0',
            ]),
            $feedback['message']
        );
    }

    public function testInvalidRowsAreReportedWithoutDiscardingValidRows(): void
    {
        $csv = implode("\n", [
            'last_name,first_name,gender,birth_date,weight_kg,belt,membership_number,notes',
            'WouldBeAdded,Athlete,F,2013-05-06,39,yellow,NEW-003,valid',
            'Invalid,Athlete,X,not-a-date,0,unknown,NEW-004,invalid',
        ]);
        $path = $this->temporaryCsv($csv);
        $request = $this->importRequest($path);

        $response = (new ClubAreaController($this->view, $request))->importAthletes($request);

        self::assertSame(302, $response->status());
        self::assertSame(2, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
        self::assertSame(1, (int) $this->database->query(
            "SELECT COUNT(*) FROM athletes WHERE membership_number = 'NEW-003'"
        )->fetchColumn());
        self::assertSame(0, (int) $this->database->query(
            "SELECT COUNT(*) FROM athletes WHERE membership_number = 'NEW-004'"
        )->fetchColumn());
        $feedback = Session::pullFlash('athlete_csv_feedback');
        self::assertIsArray($feedback);
        self::assertSame('warning', $feedback['type']);
        self::assertStringContainsString('1 skipped', (string) $feedback['message']);
        self::assertCount(1, $feedback['report']);
        self::assertStringContainsString('Row 3', (string) $feedback['report'][0]['message']);
    }

    public function testItalianHeadersCanBeReorderedAndExtraColumnsAreIgnoredIdempotently(): void
    {
        $csv = implode("\n", [
            'Note;Numero tessera;Cintura;Peso (kg);Data di nascita;Campo extra;Genere;Nome;Cognome',
            'nota;ITA-001;Blu;44,5;07/06/2013;ignorato;Femmina;Giulia;Rossi',
        ]);
        $path = $this->temporaryCsv($csv);
        $transfer = new AthleteCsvTransfer();

        $first = $transfer->import($path, 201);
        $second = $transfer->import($path, 201);

        self::assertSame(1, $first->created);
        self::assertSame(0, $first->updated);
        self::assertSame([], $first->issues);
        self::assertSame(0, $second->created);
        self::assertSame(0, $second->updated);
        self::assertSame(1, $second->unchanged);
        self::assertSame(1, (int) $this->database->query(
            "SELECT COUNT(*) FROM athletes WHERE membership_number = 'ITA-001'"
        )->fetchColumn());
        $athlete = $this->database->query(
            "SELECT * FROM athletes WHERE membership_number = 'ITA-001'"
        )->fetch();
        self::assertIsArray($athlete);
        self::assertSame('Rossi', $athlete['last_name']);
        self::assertSame('Giulia', $athlete['first_name']);
        self::assertSame('F', $athlete['gender']);
        self::assertSame('2013-06-07', $athlete['birth_date']);
        self::assertSame('44.5', (string) $athlete['weight_kg']);
        self::assertSame('blue', $athlete['belt']);
    }

    public function testRowsWithoutAnIdentityAreReportedAndNeverDuplicated(): void
    {
        $path = $this->temporaryCsv(implode("\n", [
            'First name,Last name,Gender,Birth date,Weight (kg),Belt,Membership number',
            'No,Identity,M,2011-02-03,41,Green,',
        ]));
        $transfer = new AthleteCsvTransfer();

        $first = $transfer->import($path, 201);
        $second = $transfer->import($path, 201);

        self::assertSame(0, $first->created);
        self::assertCount(1, $first->issues);
        self::assertSame('club.area.csv.missing_identity', $first->issues[0]->translationKey);
        self::assertSame(0, $second->created);
        self::assertCount(1, $second->issues);
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
    }

    public function testXlsxRegistryRowsWithoutWeightImportAndRemainIdempotent(): void
    {
        $path = $this->temporaryXlsx([
            ['Campo extra', 'Nome', 'Cognome', 'Nato il', 'Sesso', 'Matricola', 'Cod.Tessera', 'Cintura'],
            ['ignorato', 'Athlete', 'MergedOwn', 41004, 'M', 'MAT-001', 'OWN-001', 'Nera 2° Dan'],
            ['ignorato', 'New', 'Incomplete', 41300, 'F', 'MAT-002', 'NEW-XLSX', 'Gialla'],
        ]);
        $transfer = new AthleteCsvTransfer();

        $imported = $transfer->import($path, 201);
        self::assertSame(1, $imported->created);
        self::assertSame(1, $imported->updated);
        self::assertSame([], $imported->issues);
        $athlete = $this->database->query('SELECT * FROM athletes WHERE id = 301')->fetch();
        self::assertIsArray($athlete);
        self::assertSame('MergedOwn', $athlete['last_name']);
        self::assertSame('Athlete', $athlete['first_name']);
        self::assertSame('2012-04-05', $athlete['birth_date']);
        self::assertSame('42.5', (string) $athlete['weight_kg']);
        self::assertSame('black', $athlete['belt']);
        self::assertSame('OWN-001', $athlete['membership_number']);
        self::assertNull($this->database->query(
            "SELECT weight_kg FROM athletes WHERE membership_number = 'NEW-XLSX'"
        )->fetchColumn());

        $repeated = $transfer->import($path, 201);
        self::assertSame(0, $repeated->created);
        self::assertSame(0, $repeated->updated);
        self::assertSame(2, $repeated->unchanged);
        self::assertSame([], $repeated->issues);
        self::assertSame(1, (int) $this->database->query(
            "SELECT COUNT(*) FROM athletes WHERE membership_number = 'NEW-XLSX'"
        )->fetchColumn());
    }

    public function testExistingRowsWithOtherMissingInformationAskForMerge(): void
    {
        $path = $this->temporaryXlsx([
            ['Nome', 'Cognome', 'Nato il', 'Sesso', 'Cod.Tessera'],
            ['Athlete', 'MergedOwn', 41004, 'M', 'OWN-001'],
        ]);
        $transfer = new AthleteCsvTransfer();

        $withoutMerge = $transfer->import($path, 201);
        self::assertSame(0, $withoutMerge->updated);
        self::assertCount(1, $withoutMerge->issues);
        self::assertSame('club.area.csv.merge_required', $withoutMerge->issues[0]->translationKey);
        self::assertSame(['belt'], $withoutMerge->issues[0]->fields);
        self::assertSame('ExistingOwn', $this->database->query(
            'SELECT last_name FROM athletes WHERE id = 301'
        )->fetchColumn());

        $merged = $transfer->import($path, 201, true);
        self::assertSame(1, $merged->updated);
        self::assertSame([], $merged->issues);
        $athlete = $this->database->query('SELECT * FROM athletes WHERE id = 301')->fetch();
        self::assertIsArray($athlete);
        self::assertSame('MergedOwn', $athlete['last_name']);
        self::assertSame('42.5', (string) $athlete['weight_kg']);
        self::assertSame('green', $athlete['belt']);
    }

    public function testImportRequiresAuthenticationAndCsrfProtection(): void
    {
        $path = $this->temporaryCsv(
            "last_name,first_name,gender,birth_date,weight_kg,belt,membership_number,notes\n"
            . "New,Athlete,M,2012-04-05,42,green,NEW-005,notes\n"
        );
        $request = new Request(
            'POST',
            '/club_athletes_import.php',
            [],
            [],
            [],
            null,
            ['athletes_file' => $this->upload($path)]
        );

        $this->expectException(HttpException::class);
        (new ClubAreaController($this->view, $request))->importAthletes($request);
    }

    public function testCsvEndpointsRedirectAnonymousClubsToLogin(): void
    {
        $this->destroySession();
        $exportRequest = new Request('GET', '/clubs/athletes-export');
        $importRequest = new Request('POST', '/clubs/athletes-import');

        $export = (new ClubAreaController($this->view, $exportRequest))->exportAthletes($exportRequest);
        $import = (new ClubAreaController($this->view, $importRequest))->importAthletes($importRequest);

        self::assertSame('/clubs/login', $export->headers()['Location']);
        self::assertSame('/clubs/login', $import->headers()['Location']);
    }

    private function importRequest(string $path): Request
    {
        return new Request(
            'POST',
            '/clubs/athletes-import',
            [],
            [
                'csrf_token' => csrf_token(),
                'return_view' => 'list',
            ],
            [],
            null,
            ['athletes_file' => $this->upload($path)]
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

    /** @param list<list<int|string>> $rows */
    private function temporaryXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'athlete-xlsx-');
        self::assertNotFalse($path);
        $archive = new ZipArchive();
        self::assertTrue($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->spreadsheetColumn($columnIndex) . $rowNumber;
                if (is_int($value)) {
                    $cells .= '<c r="' . $reference . '" t="n"><v>' . $value . '</v></c>';
                    continue;
                }

                $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="' . $reference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $escaped
                    . '</t></is></c>';
            }
            $sheetRows .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }

        self::assertTrue($archive->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>'
        ));
        self::assertTrue($archive->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<workbookPr date1904="false"/><sheets>'
            . '<sheet name="Athletes" sheetId="1" r:id="rId1"/>'
            . '</sheets></workbook>'
        ));
        self::assertTrue($archive->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" '
            . 'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
            . 'Target="worksheets/sheet1.xml"/>'
            . '</Relationships>'
        ));
        self::assertTrue($archive->addFromString(
            'xl/worksheets/sheet1.xml',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>'
        ));
        self::assertTrue($archive->close());
        $this->temporaryFiles[] = $path;

        return $path;
    }

    private function spreadsheetColumn(int $zeroBasedIndex): string
    {
        $column = '';
        for ($index = $zeroBasedIndex + 1; $index > 0; $index = intdiv($index - 1, 26)) {
            $column = chr((($index - 1) % 26) + 65) . $column;
        }

        return $column;
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
                affiliation TEXT NOT NULL,
                recovery_email TEXT NOT NULL,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE athletes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL,
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
             (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
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
