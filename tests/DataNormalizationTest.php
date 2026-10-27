<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\DataNormalizationController;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\Database;
use App\Service\DataNormalization;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class DataNormalizationTest extends TestCase
{
    private PDO $database;
    private mixed $originalConnection;
    private ReflectionProperty $connection;

    protected function setUp(): void
    {
        $this->connection = new ReflectionProperty(Database::class, 'pdo');
        $this->originalConnection = $this->connection->getValue();
        $this->database = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY, name TEXT, contact_first_name TEXT, contact_last_name TEXT,
                email TEXT UNIQUE, phone TEXT, address_line TEXT, postal_code TEXT, federal_code TEXT UNIQUE
             );
             CREATE TABLE athletes (
                id INTEGER PRIMARY KEY, club_id INTEGER, last_name TEXT, first_name TEXT, membership_number TEXT,
                gender TEXT, birth_date TEXT, weight_kg REAL, belt TEXT, notes TEXT
             );
             CREATE TABLE entries (id INTEGER PRIMARY KEY, athlete_id INTEGER, snapshot_last_name TEXT)'
        );
        $club = $this->database->prepare('INSERT INTO clubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $club->execute([1, '  ASD  Synthetic Judo ', ' sYNTHETIC ', ' cONTACT ', ' CLUB@example.test ', ' 070  000 ', ' Via  Esempio 1 ', ' 09000 ', ' syn-1 ']);
        $club->execute([2, 'Other Club', 'Other', 'Contact', 'other@example.test', '070000', null, null, 'SYN-2']);
        $athlete = $this->database->prepare('INSERT INTO athletes VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $athlete->execute([10, 1, ' d’ESPOsITO ', ' sYNTHETIC ', ' SYN-10 ', 'M', '2012-01-01', null, 'blue', 'Original note']);
        $athlete->execute([11, 2, ' rOSSI ', ' aTHLETE ', null, 'F', '2012-01-02', 40, 'yellow', 'Other note']);
        $this->database->exec("INSERT INTO entries VALUES (1, 10, 'FROZEN NAME')");
        $this->connection->setValue(null, $this->database);
        Session::destroy();
        Session::start();
        Session::authenticateAdministrator(hash('sha256', 'test-administrator-credential'));
        Localization::setLocale('en');
    }

    protected function tearDown(): void
    {
        $this->connection->setValue(null, $this->originalConnection);
        Session::destroy();
    }

    public function testPreviewAndApplyNormalizeOnlySelectedFormattingFields(): void
    {
        $service = new DataNormalization($this->database);
        $preview = $service->preview('clubs', 0);
        self::assertCount(1, $preview);
        self::assertSame('ASD Synthetic Judo', $preview[0]['changes']['name']['after']);
        self::assertSame('club@example.test', $preview[0]['changes']['email']['after']);
        self::assertSame('SYN-1', $preview[0]['changes']['federal_code']['after']);
        self::assertSame('  ASD  Synthetic Judo ', $this->database->query('SELECT name FROM clubs WHERE id = 1')->fetchColumn());
        self::assertSame(1, $service->apply('clubs', [1 => $preview[0]['fingerprint']]));
        self::assertSame('ASD Synthetic Judo', $this->database->query('SELECT name FROM clubs WHERE id = 1')->fetchColumn());
        self::assertSame('Other Club', $this->database->query('SELECT name FROM clubs WHERE id = 2')->fetchColumn());
        self::assertSame([], $service->preview('clubs', 0));
    }

    public function testAthletesAcrossClubsKeepOwnershipSportingDataAndFrozenSnapshots(): void
    {
        $before = $this->database->query('SELECT club_id, gender, birth_date, weight_kg, belt, notes FROM athletes ORDER BY id')->fetchAll();
        $service = new DataNormalization($this->database);
        $preview = $service->preview('athletes', 0);
        self::assertCount(2, $preview);
        self::assertSame(2, $service->apply('athletes', array_column($preview, 'fingerprint', 'id')));
        self::assertSame('D’esposito', $this->database->query('SELECT last_name FROM athletes WHERE id = 10')->fetchColumn());
        self::assertSame($before, $this->database->query('SELECT club_id, gender, birth_date, weight_kg, belt, notes FROM athletes ORDER BY id')->fetchAll());
        self::assertSame('FROZEN NAME', $this->database->query('SELECT snapshot_last_name FROM entries')->fetchColumn());
        self::assertSame([], $service->preview('athletes', 0));
    }

    public function testStaleSelectionRejectsTheEntireBatch(): void
    {
        $service = new DataNormalization($this->database);
        $preview = $service->preview('athletes', 0);
        $this->database->exec("UPDATE athletes SET club_id = 1 WHERE id = 11");
        try {
            $service->apply('athletes', array_column($preview, 'fingerprint', 'id'));
            self::fail('Expected stale preview.');
        } catch (RuntimeException $exception) {
            self::assertSame('normalization.stale', $exception->getMessage());
        }
        self::assertSame(' d’ESPOsITO ', $this->database->query('SELECT last_name FROM athletes WHERE id = 10')->fetchColumn());
        self::assertFalse($this->database->inTransaction());
    }

    public function testDeletedRecordRejectsTheEntireSelection(): void
    {
        $service = new DataNormalization($this->database);
        $preview = $service->preview('athletes', 0);
        $this->database->exec('DELETE FROM athletes WHERE id = 11');
        $this->expectExceptionMessage('normalization.stale');
        $service->apply('athletes', array_column($preview, 'fingerprint', 'id'));
    }

    public function testIdentifierConflictsRollBackAllSelectedUpdates(): void
    {
        $this->database->exec("UPDATE clubs SET federal_code = 'SYN-1', name = ' Other  Club ' WHERE id = 2");
        $service = new DataNormalization($this->database);
        $preview = $service->preview('clubs', 0);
        try {
            $service->apply('clubs', array_column($preview, 'fingerprint', 'id'));
            self::fail('Expected unique identifier conflict.');
        } catch (PDOException) {
            self::assertFalse($this->database->inTransaction());
        }
        self::assertSame('  ASD  Synthetic Judo ', $this->database->query('SELECT name FROM clubs WHERE id = 1')->fetchColumn());
        self::assertSame(' Other  Club ', $this->database->query('SELECT name FROM clubs WHERE id = 2')->fetchColumn());
    }

    public function testPreviewIsBoundedAndPagesAcrossAllClubs(): void
    {
        $statement = $this->database->prepare('INSERT INTO athletes (id, club_id, last_name, first_name) VALUES (?, 2, ?, ?)');
        for ($id = 12; $id <= 84; $id++) {
            $statement->execute([$id, ' sYNTHETIC ', ' aTHLETE ']);
        }
        $service = new DataNormalization($this->database);
        self::assertSame(75, $service->count('athletes'));
        self::assertCount(50, $service->preview('athletes', 0));
        self::assertCount(25, $service->preview('athletes', 50));
    }

    /** @return iterable<string, array{int}> */
    public static function recordCounts(): iterable
    {
        yield 'one' => [1];
        yield 'many' => [75];
    }

    #[DataProvider('recordCounts')]
    public function testPreviewUsesTwoQueriesRegardlessOfRecordCount(int $count): void
    {
        $countStatement = $this->createMock(PDOStatement::class);
        $countStatement->method('fetchColumn')->willReturn($count);
        $page = $this->createMock(PDOStatement::class);
        $page->expects(self::once())->method('bindValue')->with(1, 0, PDO::PARAM_INT)->willReturn(true);
        $page->expects(self::once())->method('execute')->willReturn(true);
        $page->method('fetchAll')->willReturn(array_fill(0, min(50, $count), [
            'id' => 1, 'club_id' => 2, 'club_name' => 'Synthetic Club', 'last_name' => ' rOSSI ',
            'first_name' => ' aTHLETE ', 'membership_number' => null,
        ]));
        $database = $this->createMock(PDO::class);
        $database->expects(self::once())->method('query')->with('SELECT COUNT(*) FROM athletes')->willReturn($countStatement);
        $database->expects(self::once())->method('prepare')->with(self::stringContains('LIMIT 50 OFFSET ?'))->willReturn($page);
        $service = new DataNormalization($database);
        self::assertSame($count, $service->count('athletes'));
        self::assertCount(min(50, $count), $service->preview('athletes', 0));
    }

    public function testControllerRendersEscapedPreviewAndAppliesOnlySelectedRecords(): void
    {
        $this->database->exec("UPDATE athletes SET first_name = '<SYNTHETIC>' WHERE id = 10");
        $preview = $this->dispatch('GET', ['type' => 'athletes']);
        self::assertSame('private, no-store, max-age=0', $preview->headers()['Cache-Control']);
        self::assertStringContainsString('&lt;SYNTHETIC&gt;', $preview->content());
        self::assertStringNotContainsString('<SYNTHETIC>', $preview->content());
        $response = $this->dispatch('POST', ['type' => 'athletes'], [
            'csrf_token' => csrf_token(), 'preview_token' => $this->previewToken(), 'selected' => ['10'],
        ]);
        self::assertSame('/admin/normalization?type=athletes&page=1', $response->headers()['Location']);
        self::assertSame(' rOSSI ', $this->database->query('SELECT last_name FROM athletes WHERE id = 11')->fetchColumn());
        self::assertSame('D’esposito', $this->database->query('SELECT last_name FROM athletes WHERE id = 10')->fetchColumn());
        self::assertStringContainsString('Updated records: 1.', $this->dispatch('GET', ['type' => 'athletes'])->content());
    }

    public function testControllerRejectsExpiredForgedEmptyAndReplayedPreviews(): void
    {
        foreach (['forged_token', 'forged_id', 'expired', 'empty'] as $case) {
            $this->dispatch('GET', ['type' => 'athletes']);
            $token = $this->previewToken();
            if ($case === 'expired') {
                $preview = Session::get('admin_normalization_preview');
                $preview['expires'] = time() - 1;
                Session::set('admin_normalization_preview', $preview);
            }
            $response = $this->dispatch('POST', ['type' => 'athletes'], [
                'csrf_token' => csrf_token(), 'preview_token' => $case === 'forged_token' ? 'forged' : $token,
                'selected' => $case === 'empty' ? [] : [$case === 'forged_id' ? '999' : '10'],
            ]);
            self::assertSame(200, $response->status());
            self::assertStringContainsString(e(__($case === 'empty' ? 'normalization.select' : 'normalization.stale')), $response->content());
            self::assertSame(' d’ESPOsITO ', $this->database->query('SELECT last_name FROM athletes WHERE id = 10')->fetchColumn());
        }
        $this->dispatch('GET', ['type' => 'athletes']);
        $post = ['csrf_token' => csrf_token(), 'preview_token' => $this->previewToken(), 'selected' => ['10']];
        self::assertSame(302, $this->dispatch('POST', ['type' => 'athletes'], $post)->status());
        self::assertStringContainsString(e(__('normalization.stale')), $this->dispatch('POST', ['type' => 'athletes'], $post)->content());
    }

    public function testControllerReportsConstraintFailuresWithoutExposingDetails(): void
    {
        $this->database->exec("UPDATE clubs SET federal_code = 'SYN-1' WHERE id = 2");
        $this->dispatch('GET', []);
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('error')->with('admin.normalization_failed', self::isInstanceOf(PDOException::class), self::anything(), self::anything());
        $request = new Request('POST', '/admin/normalization', [], [
            'csrf_token' => csrf_token(), 'preview_token' => $this->previewToken(), 'selected' => ['1'],
        ]);
        $response = (new DataNormalizationController(new View(dirname(__DIR__) . '/views'), $request, $logger))->review($request);
        self::assertStringContainsString(e(__('normalization.failed')), $response->content());
        self::assertStringNotContainsString('SQLSTATE', $response->content());
    }

    public function testControllerRequiresAdminAndCsrf(): void
    {
        $this->expectException(HttpException::class);
        try {
            $this->dispatch('POST', [], []);
        } finally {
            Session::destroy();
            Session::start();
            self::assertSame('/admin/login', $this->dispatch('GET', [])->headers()['Location']);
            Session::authenticateClub(1, hash('sha256', 'test-club-credential'));
            self::assertSame('/admin/login', $this->dispatch('GET', [])->headers()['Location']);
        }
    }

    public function testServiceRejectsUnlistedTablesAndEmptySelections(): void
    {
        $service = new DataNormalization($this->database);
        foreach (['entries', 'clubs; DELETE FROM athletes'] as $type) {
            try {
                $service->preview($type, 0);
                self::fail('Unlisted table accepted.');
            } catch (RuntimeException $exception) {
                self::assertSame('normalization.type_invalid', $exception->getMessage());
            }
        }
        $this->expectExceptionMessage('normalization.select');
        $service->apply('athletes', []);
    }

    private function previewToken(): string
    {
        return Session::get('admin_normalization_preview')['token'];
    }

    /** @param array<string, mixed> $query @param array<string, mixed>|null $post */
    private function dispatch(string $method, array $query, ?array $post = null): Response
    {
        $request = new Request($method, '/admin/normalization', $query, $post ?? []);
        return (new DataNormalizationController(new View(dirname(__DIR__) . '/views'), $request))->review($request);
    }
}
