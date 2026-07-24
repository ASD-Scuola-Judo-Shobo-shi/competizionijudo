<?php

/**
 * Local database seeding utility.
 *
 * Permanently removes local application data before creating varied clubs,
 * athletes, events, entries, closed-event snapshots, and registration exceptions.
 * Usage: php scripts/seed-local-database.php
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Model\Affiliation;
use App\Model\Belt;
use App\Model\Database;
use App\Model\EntrySnapshotService;
use App\Model\JudoCategory;
use App\Model\SardinianLocation;

// Adjust these values to control the volume of local seed data.
$clubCount = 40;
$eventCount = 9;
$athletesPerClub = 80;
$athleteCountVariation = 10;
$minimumTypicalEventEntries = 100;
$maximumTypicalEventEntries = 1000;
$minimumAthletesPerClub = max(1, $athletesPerClub - $athleteCountVariation);

/** @var list<string> $venues */
$venues = [
    'Palazzetto dello Sport Cagliari',
    'PalaMandolesi Sassari',
    'Palazzetto dello Sport Nuoro',
    'Centro Sportivo Oristano',
    'Palazzetto dello Sport Olbia',
    'PalaVigor Sestu',
    'Palazzetto dello Sport Alghero',
    'Centro Sportivo Carbonia',
];

/** @var list<string> $eventNames */
$eventNames = [
    'Trofeo Città di Cagliari',
    'Gran Premio Sardegna',
    'Coppa Sardegna Judo',
    'Memorial Gianni Mura',
    'Festival Regionale Judo',
    'Campionato Provinciale',
    'Open Judo Sardegna',
    'Trofeo delle Nuraghe',
    'Coppa del Golfo',
];

/** @var list<string> $maleFirstNames */
$maleFirstNames = [
    'Marco', 'Luca', 'Giuseppe', 'Antonio', 'Francesco', 'Alessandro', 'Giovanni', 'Carlo', 'Roberto',
    'Davide', 'Andrea', 'Matteo', 'Lorenzo', 'Pietro', 'Tommaso', 'Gabriele', 'Simone', 'Alberto',
];
/** @var list<string> $femaleFirstNames */
$femaleFirstNames = [
    'Maria', 'Anna', 'Sara', 'Laura', 'Giulia', 'Chiara', 'Francesca', 'Alessia', 'Valentina',
    'Martina', 'Sofia', 'Giorgia', 'Michela', 'Ilaria', 'Elisa', 'Camilla', 'Aurora', 'Beatrice',
];
/** @var list<string> $lastNames */
$lastNames = [
    'Santoro', 'Bianchi', 'Rossi', 'Ferrari', 'Romano', 'Conti', 'Ricci', 'Marchetti', 'Colombo',
    'Bruno', 'Mancini', 'Messina', 'Sanna', 'De Luca', 'Verdi', 'Neri', 'Costa', 'Mauri', 'Fabbri',
    'Leoni', 'Grassi', 'Barbieri', 'Vitale', 'Ortu', 'Melis', 'Serra', 'Mura', 'Pinna', 'Lecca',
];
/** @var list<string> $clubPrefixes */
$clubPrefixes = [
    'Judo Club', 'Associazione Judo', 'Polisportiva Judo', 'Shobu Kan', 'Seiryu Kan', 'Kiai',
    'Draghi', 'Leonesse', 'Campioni', 'Passione Judo', 'Fiamma Judo', 'Eclisse Judo',
];

/**
 * The first five profiles cover: open pre-competitive and competitive events,
 * a full event, a mixed event, a closed event with snapshots, a past deadline,
 * and an unpublished draft. Additional events repeat those cases at new sizes.
 *
 * @var list<array{type: string, published: bool, closed: bool, event_days: int, deadline_days: int, target: int, full: bool, exceptions: int}>
 */
$eventProfiles = [
    ['type' => 'only_precompetitive', 'published' => true, 'closed' => false, 'event_days' => 21, 'deadline_days' => 7, 'target' => 100, 'full' => false, 'exceptions' => 0],
    ['type' => 'only_competitive', 'published' => true, 'closed' => false, 'event_days' => 35, 'deadline_days' => 25, 'target' => 1000, 'full' => true, 'exceptions' => 0],
    ['type' => 'precompetitive_and_competitive', 'published' => true, 'closed' => false, 'event_days' => 49, 'deadline_days' => 39, 'target' => 550, 'full' => false, 'exceptions' => 0],
    ['type' => 'only_competitive', 'published' => true, 'closed' => true, 'event_days' => -21, 'deadline_days' => -31, 'target' => 350, 'full' => false, 'exceptions' => 2],
    ['type' => 'only_precompetitive', 'published' => true, 'closed' => false, 'event_days' => 28, 'deadline_days' => -30, 'target' => 200, 'full' => false, 'exceptions' => 0],
    ['type' => 'precompetitive_and_competitive', 'published' => false, 'closed' => false, 'event_days' => 70, 'deadline_days' => 60, 'target' => 0, 'full' => false, 'exceptions' => 0],
    ['type' => 'only_competitive', 'published' => true, 'closed' => false, 'event_days' => 84, 'deadline_days' => 74, 'target' => 750, 'full' => false, 'exceptions' => 0],
    ['type' => 'only_precompetitive', 'published' => true, 'closed' => false, 'event_days' => 98, 'deadline_days' => 88, 'target' => 150, 'full' => false, 'exceptions' => 0],
    ['type' => 'precompetitive_and_competitive', 'published' => true, 'closed' => false, 'event_days' => 112, 'deadline_days' => 102, 'target' => 900, 'full' => false, 'exceptions' => 0],
];

/**
 * Each group is represented before random choices begin, so normal-size club
 * rosters include pre-competitive, competitive, and master athletes.
 *
 * @var list<array{minimum: int, maximum: int}>
 */
$ageGroups = [
    ['minimum' => 4, 'maximum' => 5],
    ['minimum' => 6, 'maximum' => 7],
    ['minimum' => 8, 'maximum' => 9],
    ['minimum' => 10, 'maximum' => 11],
    ['minimum' => 12, 'maximum' => 12],
    ['minimum' => 13, 'maximum' => 14],
    ['minimum' => 15, 'maximum' => 17],
    ['minimum' => 18, 'maximum' => 20],
    ['minimum' => 21, 'maximum' => 35],
    ['minimum' => 36, 'maximum' => 60],
];

seedAssertValidConfiguration(
    $clubCount,
    $eventCount,
    $athletesPerClub,
    $athleteCountVariation,
    $minimumTypicalEventEntries,
    $maximumTypicalEventEntries
);

foreach (
    seedVariationWarnings(
        $clubCount,
        $eventCount,
        $minimumAthletesPerClub,
        $minimumTypicalEventEntries
    ) as $warning
) {
    fwrite(STDERR, "WARNING: {$warning}\n");
}

echo "Purging existing local data...\n";

$database = Database::connection();
purgeSeedData($database);

echo sprintf(
    "Creating %d clubs, about %d athletes per club, and %d events...\n",
    $clubCount,
    $athletesPerClub,
    $eventCount
);

$passwordHash = password_hash('000000000000', PASSWORD_DEFAULT);

$clubStatement = $database->prepare(
    'INSERT INTO clubs (
        federal_code, name, email, phone, address_line, postal_code, city, province,
        contact_first_name, contact_last_name, affiliation, password_hash
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$athleteStatement = $database->prepare(
    'INSERT INTO athletes (
        club_id, last_name, first_name, gender, birth_date, weight_kg, belt, membership_number
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$eventStatement = $database->prepare(
    'INSERT INTO events (
        name, date, location, organizer, registration_deadline, max_participants,
        type, description, published, closed
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$entryStatement = $database->prepare(
    'INSERT INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
);
$exceptionStatement = $database->prepare(
    'INSERT INTO event_registration_exceptions (event_id, club_id) VALUES (?, ?)'
);

if (
    $clubStatement === false
    || $athleteStatement === false
    || $eventStatement === false
    || $entryStatement === false
    || $exceptionStatement === false
) {
    throw new RuntimeException('Unable to prepare a local seed statement.');
}

/** @var list<array{id: int, club_id: int, birth_date: string}> $athletes */
$athletes = [];
/** @var list<int> $clubIds */
$clubIds = [];
$totalAthletes = 0;
$seedYear = (int) date('Y');
$belts = array_map(static fn(Belt $belt): string => $belt->value, Belt::cases());
$affiliations = array_keys(Affiliation::options());

$database->beginTransaction();
try {
    for ($clubIndex = 0; $clubIndex < $clubCount; $clubIndex++) {
        $location = seedRandomLocation();
        $clubNumber = $clubIndex + 1;
        $clubName = seedRandomElement($clubPrefixes) . ' ' . $location['city'] . ' ' . $clubNumber;
        $email = sprintf('club%03d@example.test', $clubNumber);
        $phone = sprintf('+39 3%02d %07d', random_int(20, 99), random_int(1000000, 9999999));
        $affiliationCount = random_int(1, min(3, count($affiliations)));
        $selectedAffiliations = seedRandomSubset($affiliations, $affiliationCount);

        $clubStatement->execute([
            'SARD' . sprintf('%04d', $clubNumber),
            $clubName,
            $email,
            $phone,
            'Via del Judo ' . $clubNumber,
            $location['postal_code'],
            $location['city'],
            $location['province'],
            seedRandomElement($maleFirstNames),
            seedRandomElement($lastNames),
            Affiliation::encode($selectedAffiliations),
            $passwordHash,
        ]);
        $clubId = (int) $database->lastInsertId();
        $clubIds[] = $clubId;

        $athleteCount = max(1, $athletesPerClub + random_int(-$athleteCountVariation, $athleteCountVariation));
        for ($athleteIndex = 0; $athleteIndex < $athleteCount; $athleteIndex++) {
            $ageGroup = $ageGroups[$athleteIndex % count($ageGroups)];
            if ($athleteIndex >= count($ageGroups)) {
                $ageGroup = seedRandomElement($ageGroups);
            }
            $age = random_int($ageGroup['minimum'], $ageGroup['maximum']);
            $gender = $athleteIndex % 2 === 0 ? 'M' : 'F';
            $firstName = $gender === 'M'
                ? seedRandomElement($maleFirstNames)
                : seedRandomElement($femaleFirstNames);
            $weight = seedWeightForAge($age, $gender);
            $membershipNumber = sprintf('MEM-%03d-%04d', $clubNumber, $athleteIndex + 1);
            $birthDate = sprintf(
                '%04d-%02d-%02d',
                $seedYear - $age,
                random_int(1, 12),
                random_int(1, 28)
            );

            $athleteStatement->execute([
                $clubId,
                seedRandomElement($lastNames),
                $firstName,
                $gender,
                $birthDate,
                $weight,
                seedBeltForAge($belts, $age),
                $membershipNumber,
            ]);
            $athletes[] = [
                'id' => (int) $database->lastInsertId(),
                'club_id' => $clubId,
                'birth_date' => $birthDate,
            ];
            $totalAthletes++;
        }
    }

    /** @var list<array{name: string, id: int, date: string, closed: bool, target: int, enrolled: int, type: string}> $eventStats */
    $eventStats = [];
    $today = new DateTimeImmutable('today');
    for ($eventIndex = 0; $eventIndex < $eventCount; $eventIndex++) {
        $profile = $eventProfiles[$eventIndex % count($eventProfiles)];
        $eventDate = $today->modify(sprintf('%+d days', $profile['event_days']))->format('Y-m-d');
        $deadline = $today->modify(sprintf('%+d days', $profile['deadline_days']))->format('Y-m-d');
        $target = $profile['target'];
        $maxParticipants = $target === 0
            ? $minimumTypicalEventEntries
            : ($profile['full'] ? $target : min($maximumTypicalEventEntries, $target + 100));
        $eventName = $eventNames[$eventIndex % count($eventNames)] . ' #' . ($eventIndex + 1);

        $eventStatement->execute([
            $eventName,
            $eventDate,
            seedRandomElement($venues),
            'Comitato Regionale Judo Sardegna',
            $deadline,
            $maxParticipants,
            $profile['type'],
            seedEventDescription($profile, $target),
            $profile['published'] ? 1 : 0,
            $profile['closed'] ? 1 : 0,
        ]);
        $eventId = (int) $database->lastInsertId();

        $selectedAthletes = seedAthletesForEvent($athletes, $profile['type'], $eventDate, $target);
        foreach ($selectedAthletes as $athlete) {
            $entryStatement->execute([$eventId, $athlete['club_id'], $athlete['id']]);
        }

        if ($profile['closed']) {
            $exceptionClubIds = seedRandomSubset($clubIds, min($profile['exceptions'], count($clubIds)));
            foreach ($exceptionClubIds as $clubId) {
                $exceptionStatement->execute([$eventId, $clubId]);
            }
            (new EntrySnapshotService($database))->consolidate($eventId, $eventDate);
        }

        $eventStats[] = [
            'name' => $eventName,
            'id' => $eventId,
            'date' => $eventDate,
            'closed' => $profile['closed'],
            'target' => $target,
            'enrolled' => count($selectedAthletes),
            'type' => $profile['type'],
        ];
    }

    $database->commit();
} catch (Throwable $exception) {
    if ($database->inTransaction()) {
        $database->rollBack();
    }

    throw $exception;
}

$totalEntries = array_sum(array_column($eventStats, 'enrolled'));
echo "\nSeed complete.\n";
echo "  Clubs: {$clubCount}\n";
echo "  Athletes: {$totalAthletes}\n";
echo "  Events: {$eventCount}\n";
echo "  Entries: {$totalEntries}\n";
echo "  Club password: 000000000000\n";
echo "\nEvent enrolments:\n";
foreach ($eventStats as $event) {
    $state = $event['closed'] ? 'closed / snapshotted' : 'open';
    echo sprintf(
        "  - #%d %s (%s, %s): %d enrolled%s\n",
        $event['id'],
        $event['name'],
        $event['type'],
        $state,
        $event['enrolled'],
        $event['target'] === 0 ? ' (draft without enrolments)' : ' of ' . $event['target'] . ' targeted'
    );
    if ($event['enrolled'] < $event['target']) {
        fwrite(
            STDERR,
            sprintf(
                "WARNING: %s could only enrol %d of %d requested eligible athletes.\n",
                $event['name'],
                $event['enrolled'],
                $event['target']
            )
        );
    }
}

/**
 * @return list<string>
 */
function seedVariationWarnings(
    int $clubCount,
    int $eventCount,
    int $minimumAthletesPerClub,
    int $minimumTypicalEventEntries
): array {
    $warnings = [];
    if ($clubCount < 6) {
        $warnings[] = 'Use at least 6 clubs to vary entry distribution across clubs.';
    }
    if ($eventCount < 6) {
        $warnings[] = 'Use at least 6 events to cover every open, closed, full, expired-deadline, and draft case.';
    }
    if ($minimumAthletesPerClub < 20) {
        $warnings[] = 'Keep at least 20 athletes per club after variation to represent age and gender categories reliably.';
    }
    if ($clubCount * $minimumAthletesPerClub < $minimumTypicalEventEntries) {
        $warnings[] = sprintf(
            'The nominal roster (%d athletes) cannot fill a typical %d-athlete event.',
            $clubCount * $minimumAthletesPerClub,
            $minimumTypicalEventEntries
        );
    }

    return $warnings;
}

function seedAssertValidConfiguration(
    int $clubCount,
    int $eventCount,
    int $athletesPerClub,
    int $athleteCountVariation,
    int $minimumTypicalEventEntries,
    int $maximumTypicalEventEntries
): void {
    if (
        $clubCount < 1
        || $eventCount < 1
        || $athletesPerClub < 1
        || $athleteCountVariation < 0
        || $minimumTypicalEventEntries < 1
        || $maximumTypicalEventEntries < $minimumTypicalEventEntries
    ) {
        fwrite(STDERR, "Invalid local seed configuration.\n");
        exit(1);
    }
}

function purgeSeedData(PDO $database): void
{
    $tables = [
        'entries',
        'event_registration_exceptions',
        'password_reset_tokens',
        'club_data_rights_declarations',
        'club_registration_confirmations',
        'authentication_throttles',
        'athletes',
        'events',
        'clubs',
    ];

    $database->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tables as $table) {
            $database->exec('TRUNCATE TABLE ' . $table);
        }
    } finally {
        $database->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

/**
 * @template T
 * @param array<array-key, T> $values
 * @return T
 */
function seedRandomElement(array $values): mixed
{
    if ($values === []) {
        throw new RuntimeException('Cannot select a random element from an empty list.');
    }

    return $values[array_rand($values)];
}

/**
 * @template T
 * @param list<T> $values
 * @return list<T>
 */
function seedRandomSubset(array $values, int $count): array
{
    if ($count <= 0 || $values === []) {
        return [];
    }

    shuffle($values);

    return array_slice($values, 0, min($count, count($values)));
}

/** @return array{province: string, city: string, postal_code: string} */
function seedRandomLocation(): array
{
    $locations = SardinianLocation::all();
    $provinces = array_keys($locations);
    $province = seedRandomElement($provinces);
    $city = seedRandomElement($locations[$province]);
    $postalCode = SardinianLocation::postalCode($province, $city);

    return [
        'province' => $province,
        'city' => $city,
        'postal_code' => $postalCode,
    ];
}

function seedWeightForAge(int $age, string $gender): float
{
    $minimum = match (true) {
        $age <= 5 => 16,
        $age <= 7 => 18,
        $age <= 9 => 20,
        $age <= 11 => 26,
        $age <= 14 => 36,
        $age <= 17 => $gender === 'M' ? 46 : 40,
        default => $gender === 'M' ? 60 : 48,
    };
    $maximum = match (true) {
        $age <= 5 => 36,
        $age <= 7 => 40,
        $age <= 9 => 50,
        $age <= 11 => 66,
        $age <= 14 => $gender === 'M' ? 81 : 70,
        $age <= 17 => $gender === 'M' ? 90 : 78,
        default => $gender === 'M' ? 110 : 90,
    };

    return round(random_int($minimum * 10, $maximum * 10) / 10, 1);
}

/** @param non-empty-list<string> $belts */
function seedBeltForAge(array $belts, int $age): string
{
    $count = match (true) {
        $age <= 7 => 3,
        $age <= 10 => 5,
        $age <= 13 => 7,
        $age <= 17 => 9,
        default => count($belts),
    };

    return seedRandomElement(array_slice($belts, 0, $count));
}

/**
 * @param list<array{id: int, club_id: int, birth_date: string}> $athletes
 * @return list<array{id: int, club_id: int, birth_date: string}>
 */
function seedAthletesForEvent(array $athletes, string $eventType, string $eventDate, int $target): array
{
    if ($target === 0) {
        return [];
    }

    /** @var array<int, list<array{id: int, club_id: int, birth_date: string}>> $eligibleByClub */
    $eligibleByClub = [];
    foreach ($athletes as $athlete) {
        if (!JudoCategory::matchesEventType($eventType, $athlete['birth_date'], $eventDate)) {
            continue;
        }
        $eligibleByClub[$athlete['club_id']][] = $athlete;
    }

    foreach ($eligibleByClub as &$eligibleAthletes) {
        shuffle($eligibleAthletes);
    }
    unset($eligibleAthletes);
    $clubIds = array_keys($eligibleByClub);
    shuffle($clubIds);

    $selected = [];
    while (count($selected) < $target) {
        $selectedThisRound = 0;
        foreach ($clubIds as $clubId) {
            $athlete = array_pop($eligibleByClub[$clubId]);
            if ($athlete === null) {
                continue;
            }
            $selected[] = $athlete;
            $selectedThisRound++;
            if (count($selected) === $target) {
                break;
            }
        }

        if ($selectedThisRound === 0) {
            break;
        }
    }

    return $selected;
}

/**
 * @param array{type: string, published: bool, closed: bool, event_days: int, deadline_days: int, target: int, full: bool, exceptions: int} $profile
 */
function seedEventDescription(array $profile, int $target): string
{
    if (!$profile['published']) {
        return 'Bozza non pubblicata, senza iscrizioni.';
    }
    if ($profile['closed']) {
        return 'Evento chiuso con iscrizioni storiche e snapshot delle categorie.';
    }
    if ($profile['deadline_days'] < $profile['event_days'] - 14) {
        return 'Evento pubblicato con termine di iscrizione scaduto.';
    }
    if ($profile['full']) {
        return sprintf('Evento aperto, pieno a %d iscritti.', $target);
    }

    return sprintf('Evento aperto con circa %d iscritti.', $target);
}
