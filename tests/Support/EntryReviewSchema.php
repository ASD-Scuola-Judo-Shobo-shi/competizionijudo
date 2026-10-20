<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;

final class EntryReviewSchema
{
    public static function install(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE athlete_revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT, athlete_id INTEGER NOT NULL, club_id INTEGER NOT NULL,
                last_name TEXT NOT NULL, first_name TEXT NOT NULL, gender TEXT NOT NULL, birth_date TEXT NOT NULL,
                weight_kg REAL, belt TEXT, membership_number TEXT, recorded_at TEXT NOT NULL,
                source TEXT NOT NULL DEFAULT \'profile_write\',
                FOREIGN KEY (athlete_id) REFERENCES athletes(id) ON DELETE CASCADE
            );
            CREATE INDEX idx_revision_athlete_time ON athlete_revisions (athlete_id, recorded_at, id);
            CREATE TABLE entry_data_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT, entry_id INTEGER NOT NULL, revision_id INTEGER,
                requester_club_id INTEGER, reason TEXT NOT NULL, before_data TEXT NOT NULL, proposed_data TEXT NOT NULL,
                base_fingerprint TEXT NOT NULL, source_recorded_at TEXT NOT NULL, requested_at TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'pending\', decided_at TEXT, decision_reason TEXT, decision_actor TEXT,
                FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
                FOREIGN KEY (revision_id) REFERENCES athlete_revisions(id) ON DELETE SET NULL
            );
            CREATE INDEX idx_review_entry_status ON entry_data_reviews (entry_id, status, id)'
        );
    }

    public static function recordProfiles(PDO $database, string $recordedAt): void
    {
        $statement = $database->prepare(
            'INSERT INTO athlete_revisions
             (athlete_id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
              membership_number, recorded_at)
             SELECT id, club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number, ?
             FROM athletes'
        );
        $statement->execute([$recordedAt]);
    }
}
