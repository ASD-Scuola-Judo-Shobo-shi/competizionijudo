<?php

/**
 * Database Seeding Utility
 *
 * Completely purges existing tables (events, clubs, athletes, entries) before execution.
 * Generates mock data: 6 Italian regional events, 50 clubs scattered across Sardinian
 * provinces, and roughly ~4,600+ distributed athletes.
 *
 * Usage: php scripts/seed_database.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Model\Database;
use App\Model\AgeClass;
use App\Model\Belt;
use App\Model\JudoCategory;

// Load environment
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        putenv(trim($line));
    }
}

$db = Database::connection();

echo "Purging existing data...\n";

// Disable foreign key checks for truncation
$db->exec('SET FOREIGN_KEY_CHECKS = 0');
$db->exec('TRUNCATE TABLE entries');
$db->exec('TRUNCATE TABLE athletes');
$db->exec('TRUNCATE TABLE event_registration_exceptions');
$db->exec('TRUNCATE TABLE events');
$db->exec('TRUNCATE TABLE clubs');
$db->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Creating 75 clubs...\n";

$provinces = ['CA', 'NU', 'OR', 'SS', 'SU'];
$citiesByProvince = [
    'CA' => ['Cagliari', 'Quartu Sant\'Elena', 'Selargius', 'Assemini', 'Capoterra', 'Sestu', 'Monserrato', 'Senorbì', 'Dolianova', 'Serdiana'],
    'NU' => ['Nuoro', 'Siniscola', 'Macomer', 'Dorgali', 'Orosei', 'Bitti', 'Orgosolo', 'Mamoiada', 'Oliena', 'Gavoi'],
    'OR' => ['Oristano', 'Cabras', 'Terralba', 'Marrubiu', 'Santa Giusta', 'Bosa', 'Ghilarza', 'Mogoro', 'Ales', 'Nurachi'],
    'SS' => ['Sassari', 'Alghero', 'Porto Torres', 'Olbia', 'Tempio Pausania', 'Ozieri', 'Ittiri', 'Sennori', 'Castelsardo', 'Ploaghe'],
    'SU' => ['Carbonia', 'Iglesias', 'Sant\'Antioco', 'Guspini', 'Villacidro', 'Sanluri', 'Muravera', 'San Gavino Monreale', 'Serrenti', 'Gonnosfanadiga'],
];

$clubNames = [
    'Judo Club', 'Polisportiva Judo', 'ASD Judo', 'Circolo Judo', 'Accademia Judo',
    'Centro Judo', 'Società Judo', 'Team Judo', 'Scuola Judo', 'Sport Judo',
    'Judo Shobo-shi', 'Judo Kodokan', 'Judo Bushido', 'Judo Samurai', 'Judo Rising Sun',
    'Judo Dragon', 'Judo Phoenix', 'Judo Tiger', 'Judo Falcon', 'Judo Eagle',
    'Judo Warrior', 'Judo Spirit', 'Judo Elite', 'Judo Pro', 'Judo Champion',
    'Judo Star', 'Judo Gold', 'Judo Silver', 'Judo Bronze', 'Judo Diamond',
    'Judo Master', 'Judo Expert', 'Judo Legend', 'Judo Hero', 'Judo Pride',
    'Judo Honor', 'Judo Glory', 'Judo Victory', 'Judo Triumph', 'Judo Force',
    'Judo Power', 'Judo Energy', 'Judo Dynamic', 'Judo Active', 'Judo Fit',
    'Judo Health', 'Judo Sport', 'Judo Fun', 'Judo Joy', 'Judo Happy',
];

$passwordHash = password_hash('000000000000', PASSWORD_DEFAULT);

$clubStmt = $db->prepare(
    'INSERT INTO clubs (federal_code, name, email, phone, contact_first_name, contact_last_name, contact_phone, contact_email, organization, recovery_email, password_hash, address_line, postal_code, city, province)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$clubIds = [];
for ($i = 0; $i < 75; $i++) {
    $province = $provinces[$i % count($provinces)];
    $cities = $citiesByProvince[$province];
    $city = $cities[$i % count($cities)];
    $federalCode = 'FED' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT);
    $name = $clubNames[$i] . ' ' . $city;
    $email = 'club' . ($i + 1) . '@example.com';
    $phone = '+39 3' . random_int(20, 99) . ' ' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $contactFirst = ['Marco', 'Luca', 'Giovanni', 'Andrea', 'Francesco', 'Roberto', 'Alessandro', 'Paolo', 'Simone', 'Fabio'][$i % 10];
    $contactLast = ['Rossi', 'Bianchi', 'Verdi', 'Russo', 'Ferrari', 'Esposito', 'Romano', 'Gallo', 'Costa', 'Fontana'][$i % 10];
    $postalCode = (string) random_int(9010, 98100);

    $clubStmt->execute([
        $federalCode,
        $name,
        $email,
        $phone,
        $contactFirst,
        $contactLast,
        $phone,
        $email,
        'CSEN',
        $email,
        $passwordHash,
        'Via ' . $contactLast . ' ' . random_int(1, 100),
        $postalCode,
        $city,
        $province,
    ]);
    $clubIds[] = (int) $db->lastInsertId();
}

echo "Creating 6 events...\n";

$eventNames = [
    'Trofeo Città di Cagliari',
    'Gran Premio Sardegna',
    'Coppa Italia Judo Sardegna',
    'Memorial Gianni Mura',
    'Torneo Regionale Under',
    'Campionato Provinciale',
];

$eventLocations = [
    'Cagliari',
    'Sassari',
    'Oristano',
    'Nuoro',
    'Carbonia',
    'Olbia',
];

$eventTypes = ['only_precompetitive', 'only_competitive', 'precompetitive_and_competitive'];

$eventStmt = $db->prepare(
    'INSERT INTO events (name, date, location, organizer, registration_deadline, max_participants, type, description, published, closed)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$eventIds = [];
$baseDate = strtotime('2026-09-01');
for ($i = 0; $i < 6; $i++) {
    $eventDate = date('Y-m-d', strtotime('+' . ($i * 14) . ' days', $baseDate));
    $deadline = date('Y-m-d', strtotime('-7 days', strtotime($eventDate)));
    $maxParticipants = [100, 150, 200, 120, 180, 250][$i];
    $published = $i < 4 ? 1 : 1; // All published
    $closed = $i >= 4 ? 1 : 0; // Last 2 are closed

    $eventStmt->execute([
        $eventNames[$i],
        $eventDate,
        $eventLocations[$i],
        'ASD Scuola Judo Shobo-shi',
        $deadline,
        $maxParticipants,
        $eventTypes[$i % count($eventTypes)],
        'Evento di judo aperto a tutte le società affiliate.',
        $published,
        $closed,
    ]);
    $eventIds[] = (int) $db->lastInsertId();
}

echo "Creating ~4,600+ athletes...\n";

$firstNamesM = ['Marco', 'Luca', 'Giovanni', 'Andrea', 'Francesco', 'Roberto', 'Alessandro', 'Paolo', 'Simone', 'Fabio', 'Matteo', 'Stefano', 'Davide', 'Federico', 'Antonio', 'Giuseppe', 'Claudio', 'Michele', 'Emanuele', 'Vincenzo'];
$firstNamesF = ['Sofia', 'Giulia', 'Aurora', 'Alice', 'Ginevra', 'Emma', 'Giorgia', 'Beatrice', 'Anna', 'Martina', 'Chiara', 'Francesca', 'Elena', 'Sara', 'Valentina', 'Alessia', 'Camilla', 'Serena', 'Ilaria', 'Veronica'];
$lastNames = ['Rossi', 'Bianchi', 'Verdi', 'Russo', 'Ferrari', 'Esposito', 'Romano', 'Gallo', 'Costa', 'Fontana', 'Conti', 'Marino', 'Greco', 'Bruno', 'Rizzo', 'Barbieri', 'Lombardi', 'Moretti', 'Fabbri', 'Martini'];
$belts = ['white', 'white_yellow', 'yellow', 'yellow_orange', 'orange', 'orange_green', 'green', 'green_blue', 'blue', 'brown', 'black'];

$athleteStmt = $db->prepare(
    'INSERT INTO athletes (club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt, membership_number)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$entryStmt = $db->prepare(
    'INSERT IGNORE INTO entries (event_id, club_id, athlete_id) VALUES (?, ?, ?)'
);

$totalAthletes = 0;
$totalEntries = 0;

foreach ($clubIds as $clubId) {
    // Each club gets between 80-120 athletes
    $numAthletes = random_int(80, 120);
    $athleteIdsForClub = [];

    for ($a = 0; $a < $numAthletes; $a++) {
        $gender = random_int(0, 1) === 0 ? 'M' : 'F';
        $firstNames = $gender === 'M' ? $firstNamesM : $firstNamesF;
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];

        // Age range: 4 to 60 years old
        $age = random_int(4, 60);
        $birthYear = 2026 - $age;
        $birthMonth = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        $birthDay = str_pad((string) random_int(1, 28), 2, '0', STR_PAD_LEFT);
        $dateOfBirth = $birthYear . '-' . $birthMonth . '-' . $birthDay;

        // Weight based on age
        if ($age <= 7) {
            $weightKg = round(random_int(180, 300) / 10, 1);
        } elseif ($age <= 10) {
            $weightKg = round(random_int(250, 400) / 10, 1);
        } elseif ($age <= 13) {
            $weightKg = round(random_int(300, 550) / 10, 1);
        } elseif ($age <= 17) {
            $weightKg = round(random_int(450, 750) / 10, 1);
        } else {
            $weightKg = round(random_int(500, 1000) / 10, 1);
        }

        // Belt based on age
        if ($age <= 7) {
            $belt = $belts[array_rand(array_slice($belts, 0, 3))];
        } elseif ($age <= 10) {
            $belt = $belts[array_rand(array_slice($belts, 0, 5))];
        } elseif ($age <= 13) {
            $belt = $belts[array_rand(array_slice($belts, 0, 7))];
        } elseif ($age <= 17) {
            $belt = $belts[array_rand(array_slice($belts, 0, 9))];
        } else {
            $belt = $belts[array_rand($belts)];
        }

        $membershipNumber = 'MEM' . str_pad((string) ($totalAthletes + 1), 8, '0', STR_PAD_LEFT);

        $athleteStmt->execute([
            $clubId,
            $lastName,
            $firstName,
            $gender,
            $dateOfBirth,
            $weightKg,
            $belt,
            $membershipNumber,
        ]);
        $athleteIdsForClub[] = (int) $db->lastInsertId();
        $totalAthletes++;
    }

    // Register athletes for events (each club registers for 2-4 events)
    $numEvents = random_int(2, 4);
    $selectedEvents = (array) array_rand(array_flip($eventIds), min($numEvents, count($eventIds)));
    foreach ($selectedEvents as $eventId) {
        // Register 30-70% of athletes for each event
        $numToRegister = (int) round(count($athleteIdsForClub) * (random_int(30, 70) / 100));
        $selectedAthletes = (array) array_rand(array_flip($athleteIdsForClub), min($numToRegister, count($athleteIdsForClub)));
        foreach ($selectedAthletes as $athleteId) {
            $entryStmt->execute([$eventId, $clubId, $athleteId]);
            $totalEntries++;
        }
    }

    if ($clubId % 10 === 0) {
        echo "  Processed club ID {$clubId} ({$numAthletes} athletes)...\n";
    }
}

echo "\nSeeding complete!\n";
echo "  Clubs: 50\n";
echo "  Events: 6\n";
echo "  Athletes: {$totalAthletes}\n";
echo "  Entries: {$totalEntries}\n";
echo "  Club password (all): 000000000000\n";
