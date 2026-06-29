<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Club;
use App\Model\Database;
use App\Model\Event;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ListFreshnessTest extends TestCase
{
    private ReflectionProperty $databaseConnection;
    private ?PDO $originalConnection;
    private PDO $database;

    protected function setUp(): void
    {
        $this->databaseConnection = new ReflectionProperty(Database::class, 'pdo');
        $connection = $this->databaseConnection->getValue();
        self::assertTrue($connection === null || $connection instanceof PDO);
        $this->originalConnection = $connection;
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema();
        $this->databaseConnection->setValue(null, $this->database);
    }

    protected function tearDown(): void
    {
        $this->databaseConnection->setValue(null, $this->originalConnection);
    }

    public function testPublishedEventListReflectsCreateUpdateAndDeleteImmediately(): void
    {
        $this->insertEvent(101, 'First Event');
        self::assertSame(['First Event'], $this->eventNames());

        $this->insertEvent(102, 'Second Event');
        self::assertSame(['First Event', 'Second Event'], $this->eventNames());

        $this->database->exec('UPDATE events SET closed = 1 WHERE id = 102');
        self::assertSame(['First Event'], $this->eventNames());

        Event::remove(101);
        self::assertSame([], $this->eventNames());
    }

    public function testClubListReflectsCreateUpdateAndDeleteImmediately(): void
    {
        $this->insertClub(201, 'First Club');
        self::assertSame(['First Club'], $this->clubNames());

        $this->insertClub(202, 'Second Club');
        self::assertSame(['First Club', 'Second Club'], $this->clubNames());

        $this->database->exec("UPDATE clubs SET name = 'Updated Club' WHERE id = 202");
        self::assertSame(['First Club', 'Updated Club'], $this->clubNames());

        Club::remove(201);
        self::assertSame(['Updated Club'], $this->clubNames());
    }

    public function testCacheAndProfilerInfrastructureIsAbsent(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__) . '/src/Core/Cache.php');
        $source = '';
        foreach (glob(dirname(__DIR__) . '/src/{Core,Model,Presentation}/*.php', GLOB_BRACE) ?: [] as $path) {
            $source .= (string) file_get_contents($path);
        }

        self::assertStringNotContainsString('Cache::', $source);
        self::assertStringNotContainsString('renderProfiler', $source);
        self::assertStringNotContainsString('recordQuery', $source);
    }

    /** @return list<string> */
    private function eventNames(): array
    {
        return array_map(static fn(Event $event): string => $event->name, Event::allPublished());
    }

    /** @return list<string> */
    private function clubNames(): array
    {
        return array_map(static fn(Club $club): string => $club->name, Club::all());
    }

    private function insertEvent(int $id, string $name): void
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
            '2026-07-01',
            'Synthetic Venue',
            'Synthetic Organizer',
            '2026-06-30',
            'only_competitive',
            null,
            null,
            null,
            null,
            1,
            0,
        ]);
    }

    private function insertClub(int $id, string $name): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO clubs (
                id, federal_code, name, email, phone, contact_first_name,
                contact_last_name, contact_phone, contact_email, organization,
                recovery_email, password_hash
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            'CODE-' . $id,
            $name,
            'club' . $id . '@example.test',
            '',
            'Synthetic',
            'Contact',
            '',
            null,
            'SYNTHETIC',
            'recovery' . $id . '@example.test',
            'synthetic-hash',
        ]);
    }

    private function createSchema(): void
    {
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
                poster_file TEXT,
                info_file TEXT,
                published INTEGER NOT NULL,
                closed INTEGER NOT NULL
            )'
        );
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
            )'
        );
    }
}
