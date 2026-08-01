<?php

declare(strict_types=1);

namespace Tests;

use App\Service\AthleteDuplicateReconciler;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AthleteDuplicateReconcilerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->database->exec(
            'CREATE TABLE athletes (
                id INTEGER PRIMARY KEY,
                club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL,
                first_name TEXT NOT NULL,
                gender TEXT NOT NULL,
                birth_date TEXT NOT NULL,
                weight_kg REAL,
                belt TEXT,
                membership_number TEXT,
                notes TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE entries (
                id INTEGER PRIMARY KEY,
                event_id INTEGER NOT NULL,
                club_id INTEGER NOT NULL,
                athlete_id INTEGER NOT NULL,
                UNIQUE (event_id, club_id, athlete_id),
                FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
            )'
        );
    }

    public function testDryRunThenApplyReconcilesAndRemainsIdempotent(): void
    {
        $this->insertAthlete(
            1,
            201,
            'rOsSi',
            'mARIO',
            'M',
            '2012-04-05',
            50.0,
            'green',
            'OLD-001',
            'original note'
        );
        $this->insertAthlete(
            2,
            201,
            'ROSSI',
            'Mario',
            'F',
            '2012-04-05',
            55.0,
            'blue',
            'NEW-002',
            'imported note'
        );
        $this->insertEntry(10, 100, 201, 2);
        $reconciler = new AthleteDuplicateReconciler($this->database);

        $preview = $reconciler->run(false, 201);

        self::assertFalse($preview->applied);
        self::assertSame(1, $preview->duplicateAthletes());
        self::assertSame(1, $preview->entryMoves());
        self::assertSame([], $preview->blockedGroups);
        self::assertSame('rOsSi', $this->athlete(1)['last_name']);
        self::assertSame(2, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes'
        )->fetchColumn());
        self::assertSame(2, (int) $this->database->query(
            'SELECT athlete_id FROM entries WHERE id = 10'
        )->fetchColumn());

        $applied = $reconciler->run(true, 201);

        self::assertTrue($applied->applied);
        self::assertSame([2], $applied->groups[0]['duplicate_ids']);
        self::assertSame([
            'last_name' => 'normalized',
            'first_name' => 'normalized',
            'gender' => 'kept_database',
            'weight_kg' => 'kept_database',
            'belt' => 'higher_belt',
            'membership_number' => 'combined',
            'notes' => 'combined',
        ], $applied->groups[0]['resolutions'][2]);
        self::assertSame([
            'id' => 1,
            'club_id' => 201,
            'last_name' => 'Rossi',
            'first_name' => 'Mario',
            'gender' => 'M',
            'birth_date' => '2012-04-05',
            'weight_kg' => 50.0,
            'belt' => 'blue',
            'membership_number' => 'OLD-001 / NEW-002',
            'notes' => "original note\nimported note",
        ], $this->athlete(1));
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes'
        )->fetchColumn());
        self::assertSame(1, (int) $this->database->query(
            'SELECT athlete_id FROM entries WHERE id = 10'
        )->fetchColumn());

        $state = $this->athlete(1);
        $repeated = $reconciler->run(true, 201);

        self::assertSame([], $repeated->groups);
        self::assertSame($state, $this->athlete(1));
    }

    public function testApplyLeavesOverlappingEventRegistrationsUntouched(): void
    {
        $this->insertAthlete(1, 201, 'Rossi', 'Mario', 'M', '2012-04-05', 50.0, 'green');
        $this->insertAthlete(2, 201, 'ROSSI', 'MARIO', 'M', '2012-04-05', 50.0, 'blue');
        $this->insertEntry(10, 100, 201, 1);
        $this->insertEntry(11, 100, 201, 2);

        $result = (new AthleteDuplicateReconciler($this->database))->run(true);

        self::assertSame([], $result->groups);
        self::assertCount(1, $result->blockedGroups);
        self::assertSame('overlapping_entries', $result->blockedGroups[0]['reason']);
        self::assertSame([100], $result->blockedGroups[0]['overlapping_event_ids']);
        self::assertSame(2, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes'
        )->fetchColumn());
        self::assertSame([1, 2], array_map('intval', $this->database->query(
            'SELECT athlete_id FROM entries ORDER BY id'
        )->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame('green', $this->athlete(1)['belt']);
    }

    public function testSameNameWithDifferentBirthDatesOrClubsIsOnlyReported(): void
    {
        $this->insertAthlete(1, 201, 'De Luca', 'Anna', 'F', '2012-04-05', null, null);
        $this->insertAthlete(2, 201, 'DE   LUCA', 'ANNA', 'F', '2013-04-05', null, null);
        $this->insertAthlete(3, 202, 'de luca', 'anna', 'F', '2012-04-05', null, null);

        $result = (new AthleteDuplicateReconciler($this->database))->run(true);

        self::assertSame([], $result->groups);
        self::assertSame([], $result->blockedGroups);
        self::assertCount(1, $result->nameCollisions);
        self::assertSame(201, $result->nameCollisions[0]['club_id']);
        self::assertSame([1, 2], $result->nameCollisions[0]['athlete_ids']);
        self::assertSame(['2012-04-05', '2013-04-05'], $result->nameCollisions[0]['birth_dates']);
        self::assertSame(3, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes'
        )->fetchColumn());
    }

    public function testClubFilterAndCallerOwnedTransactionLimitTheChanges(): void
    {
        $this->insertAthlete(1, 201, 'Rossi', 'Mario', 'M', '2012-04-05', null, null);
        $this->insertAthlete(2, 201, 'ROSSI', 'MARIO', 'M', '2012-04-05', null, null);
        $this->insertAthlete(3, 202, 'Bianchi', 'Luca', 'M', '2011-03-02', null, null);
        $this->insertAthlete(4, 202, 'BIANCHI', 'LUCA', 'M', '2011-03-02', null, null);
        $this->database->beginTransaction();

        $result = (new AthleteDuplicateReconciler($this->database))->run(true, 201);

        self::assertTrue($this->database->inTransaction());
        self::assertCount(1, $result->groups);
        self::assertSame(201, $result->groups[0]['club_id']);
        self::assertSame(1, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
        self::assertSame(2, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 202'
        )->fetchColumn());

        $this->database->rollBack();
        self::assertSame(2, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE club_id = 201'
        )->fetchColumn());
    }

    public function testManualReviewReportUsesSurvivorIdsAfterSafeMerges(): void
    {
        $this->insertAthlete(1, 201, 'Rossi', 'Mario', 'M', '2012-04-05', null, null);
        $this->insertAthlete(2, 201, 'ROSSI', 'MARIO', 'M', '2012-04-05', null, null);
        $this->insertAthlete(3, 201, 'rossi', 'mario', 'M', '2013-04-05', null, null);

        $result = (new AthleteDuplicateReconciler($this->database))->run(true);

        self::assertCount(1, $result->groups);
        self::assertCount(1, $result->nameCollisions);
        self::assertSame([1, 3], $result->nameCollisions[0]['athlete_ids']);
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM athletes WHERE id = 2'
        )->fetchColumn());
    }

    public function testInvalidClubIdIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('positive integer');

        (new AthleteDuplicateReconciler($this->database))->run(false, 0);
    }

    private function insertAthlete(
        int $id,
        int $clubId,
        string $lastName,
        string $firstName,
        string $gender,
        string $birthDate,
        ?float $weight,
        ?string $belt,
        ?string $membership = null,
        ?string $notes = null
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO athletes
             (id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
              membership_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            $clubId,
            $lastName,
            $firstName,
            $gender,
            $birthDate,
            $weight,
            $belt,
            $membership,
            $notes,
        ]);
    }

    private function insertEntry(int $id, int $eventId, int $clubId, int $athleteId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO entries (id, event_id, club_id, athlete_id) VALUES (?, ?, ?, ?)'
        );
        $statement->execute([$id, $eventId, $clubId, $athleteId]);
    }

    /**
     * @return array{
     *     id: int,
     *     club_id: int,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string|null,
     *     membership_number: string|null,
     *     notes: string|null
     * }
     */
    private function athlete(int $id): array
    {
        $statement = $this->database->prepare(
            'SELECT id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
                    membership_number, notes
             FROM athletes WHERE id = ?'
        );
        $statement->execute([$id]);
        $athlete = $statement->fetch();
        self::assertIsArray($athlete);

        return [
            'id' => (int) $athlete['id'],
            'club_id' => (int) $athlete['club_id'],
            'last_name' => (string) $athlete['last_name'],
            'first_name' => (string) $athlete['first_name'],
            'gender' => (string) $athlete['gender'],
            'birth_date' => (string) $athlete['birth_date'],
            'weight_kg' => $athlete['weight_kg'] !== null ? (float) $athlete['weight_kg'] : null,
            'belt' => $athlete['belt'] !== null ? (string) $athlete['belt'] : null,
            'membership_number' => $athlete['membership_number'] !== null
                ? (string) $athlete['membership_number']
                : null,
            'notes' => $athlete['notes'] !== null ? (string) $athlete['notes'] : null,
        ];
    }
}
