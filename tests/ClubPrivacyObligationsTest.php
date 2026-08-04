<?php

declare(strict_types=1);

namespace Tests;

use App\Controller\ClubAreaController;
use App\Controller\EventController;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Localization;
use App\Model\ClubDataRightsDeclaration;
use App\Model\ClubRegistrationConfirmation;
use App\Model\ClubTermsAcceptance;
use App\Model\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ClubPrivacyObligationsTest extends TestCase
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
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                approved_at TEXT,
                password_hash TEXT NOT NULL
            );
            CREATE TABLE club_data_rights_declarations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                declared_by_club_id INTEGER NOT NULL,
                declaration_version TEXT NOT NULL,
                declared_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE club_registration_confirmations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                token_hash TEXT NOT NULL UNIQUE,
                registration_payload TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                confirmed_at TEXT
            );
            CREATE TABLE club_terms_acceptances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                accepted_by_club_id INTEGER NOT NULL,
                representative_name TEXT NOT NULL,
                accepted_account_email TEXT NOT NULL,
                terms_version TEXT NOT NULL,
                accepted_locale TEXT NOT NULL,
                accepted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            INSERT INTO clubs (id, name, email, approved_at, password_hash)
                VALUES (201, \'Synthetic Club\', \'club@example.test\', \'2026-01-01 00:00:00\', \'synthetic-hash\');
            INSERT INTO club_data_rights_declarations
                (club_id, declared_by_club_id, declaration_version)
                VALUES (201, 201, \'legacy-version\')'
        );
        $this->databaseConnection->setValue(null, $this->database);

        $this->destroySession();
        Session::start();
        Session::authenticateClub(201, hash('sha256', 'synthetic-club-credential'));
        Localization::setLocale('en');
        $this->view = new View(dirname(__DIR__) . '/views');
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
        $this->destroySession();
    }

    public function testClubCanAcceptTheVersionedArticle14Warranty(): void
    {
        $get = new Request('GET', '/clubs/agreements');
        $getResponse = (new ClubAreaController($this->view, $get))->agreements($get);

        self::assertSame(200, $getResponse->status());
        self::assertStringContainsString(
            'name="athlete_privacy_obligations" value="1" required',
            $getResponse->content()
        );
        self::assertStringContainsString('name="terms_accepted" value="1" required', $getResponse->content());
        self::assertStringContainsString('href="/privacy"', $getResponse->content());
        self::assertStringContainsString('href="/terms"', $getResponse->content());
        self::assertStringContainsString('Article 14 privacy notice', $getResponse->content());
        self::assertFalse(ClubDataRightsDeclaration::hasCurrentVersion(201));
        self::assertFalse(ClubTermsAcceptance::hasCurrentVersion(201));

        $post = new Request('POST', '/clubs/agreements', [], [
            'csrf_token' => csrf_token(),
            'terms_accepted' => '1',
            'athlete_privacy_obligations' => '1',
        ]);
        $postResponse = (new ClubAreaController($this->view, $post))->agreements($post);

        self::assertSame(303, $postResponse->status());
        self::assertSame('/clubs/area', $postResponse->headers()['Location']);
        self::assertTrue(ClubDataRightsDeclaration::hasCurrentVersion(201));
        self::assertTrue(ClubTermsAcceptance::hasCurrentVersion(201));
        self::assertSame(
            ClubDataRightsDeclaration::VERSION,
            $this->database->query(
                'SELECT declaration_version FROM club_data_rights_declarations ORDER BY id DESC LIMIT 1'
            )->fetchColumn()
        );
        self::assertSame(
            ['Synthetic Club', 'club@example.test', ClubTermsAcceptance::VERSION, 'en'],
            $this->database->query(
                'SELECT representative_name, accepted_account_email, terms_version, accepted_locale '
                . 'FROM club_terms_acceptances ORDER BY id DESC LIMIT 1'
            )->fetch(PDO::FETCH_NUM)
        );
    }

    public function testMissingWarrantyIsRejectedWithoutCreatingAnAuditRecord(): void
    {
        $request = new Request('POST', '/clubs/agreements', [], [
            'csrf_token' => csrf_token(),
        ]);

        $response = (new ClubAreaController($this->view, $request))->agreements($request);

        self::assertSame(200, $response->status());
        self::assertStringContainsString(
            e(__('validation.club_athlete_data_rights_required')),
            $response->content()
        );
        self::assertStringContainsString(e(__('validation.club_terms_required')), $response->content());
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM club_data_rights_declarations'
        )->fetchColumn());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM club_terms_acceptances'
        )->fetchColumn());
    }

    public function testLegacyDeclarationBlocksAthleteDataMutations(): void
    {
        $areaRequest = new Request('POST', '/clubs/area?view=add', ['view' => 'add'], [
            'csrf_token' => csrf_token(),
        ]);
        $areaResponse = (new ClubAreaController($this->view, $areaRequest))->index($areaRequest);

        $importRequest = new Request('POST', '/clubs/athletes-import', [], [
            'csrf_token' => csrf_token(),
        ]);
        $importResponse = (new ClubAreaController($this->view, $importRequest))->importAthletes(
            $importRequest
        );

        $eventRequest = new Request('POST', '/events/register', [], [
            'csrf_token' => csrf_token(),
            'event' => '101',
        ]);
        $eventResponse = (new EventController($this->view, $eventRequest))->register($eventRequest);

        foreach ([$areaResponse, $importResponse, $eventResponse] as $response) {
            self::assertSame(303, $response->status());
            self::assertSame('/clubs/agreements', $response->headers()['Location']);
        }
    }

    public function testPendingRegistrationCannotBeCreditedWithTermsItDidNotSee(): void
    {
        $token = str_repeat('a', 64);
        $statement = $this->database->prepare(
            'INSERT INTO club_registration_confirmations '
            . '(email, token_hash, registration_payload, expires_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            'pending@example.test',
            hash('sha256', $token),
            json_encode([
                'email' => 'pending@example.test',
                'data_rights_declaration_version' => ClubDataRightsDeclaration::VERSION,
                'terms_version' => 'legacy-version',
                'terms_locale' => 'en',
            ], JSON_THROW_ON_ERROR),
            '2099-01-01 00:00:00',
        ]);

        self::assertFalse(ClubRegistrationConfirmation::confirm($token));
        self::assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM clubs')->fetchColumn());
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM club_data_rights_declarations'
        )->fetchColumn());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM club_terms_acceptances'
        )->fetchColumn());
    }

    public function testPendingRegistrationCannotBeCreditedWithAPrivacyWarrantyItDidNotSee(): void
    {
        $token = str_repeat('b', 64);
        $statement = $this->database->prepare(
            'INSERT INTO club_registration_confirmations '
            . '(email, token_hash, registration_payload, expires_at) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([
            'privacy-pending@example.test',
            hash('sha256', $token),
            json_encode([
                'email' => 'privacy-pending@example.test',
                'data_rights_declaration_version' => 'legacy-version',
                'terms_version' => ClubTermsAcceptance::VERSION,
                'terms_locale' => 'it',
            ], JSON_THROW_ON_ERROR),
            '2099-01-01 00:00:00',
        ]);

        self::assertFalse(ClubRegistrationConfirmation::confirm($token));
        self::assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM clubs')->fetchColumn());
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM club_terms_acceptances'
        )->fetchColumn());
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
