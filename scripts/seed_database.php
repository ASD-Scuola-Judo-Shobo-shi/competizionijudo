<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use App\Model\Database;
use App\Model\Belt;
use App\Model\Gender;
use App\Model\SardinianLocation;
use App\Model\Affiliation;

/**
 * Seed the database with test data for Sardinian judo competitions.
 * Generates 6 events in Sardinia (Italian) and 50 clubs with 15-150 athletes each.
 */

// Configuration
$eventCount = 6;
$clubCount = 50;
$minAthletesPerClub = 15;
$maxAthletesPerClub = 150;

// Sardinian venue names (Italian)
$venues = [
    'Palazzetto dello Sport Cagliari',
    'PalaMandolesi Sassari',
    'Gymnasium Comunale Nuoro',
    'Palestra Arzana',
    'Centro Sportivo Oristano',
    'Area Eventi Olbia',
    'Palazzetto dello Sport Pula',
    'Gym Ortauro',
    'Palestra Comunale Alghero',
    'PalaVigor Sestu',
    'Centro Tennistico Calasetta',
    'Palazzetto Villaspeciosa',
];

// Event types
$eventTypes = ['only_precompetitive', 'only_competitive', 'precompetitive_and_competitive'];

// Age class birth years mapping (for realistic weight/age combinations)
$ageClassBirthYears = [
    'children_a' => range(2019, 2021), // 4-6 years old (2026)
    'children_b' => range(2017, 2018), // 6-7 years old
    'kids' => range(2015, 2016), // 8-9 years old
    'youth' => range(2013, 2014), // 10-11 years old
    'pre_cadets_a' => [2014], // 12 years old
    'pre_cadets_b' => [2011, 2012, 2013], // 13-14 years old
    'cadets' => range(2008, 2010), // 15-17 years old
    'juniors' => range(2006, 2007), // 18-20 years old
    'seniors' => range(1990, 2005), // 21-36 years old
    'masters' => range(1950, 1989), // 37+ years old
];

// Weight ranges by gender and age class
$weightRanges = [
    'M' => [
        'children_a' => [16, 36],
        'children_b' => [18, 40],
        'kids' => [20, 50],
        'youth' => [26, 66],
        'pre_cadets_a' => [36, 73],
        'pre_cadets_b' => [38, 81],
        'cadets' => [46, 90],
        'juniors' => [60, 100],
        'seniors' => [60, 100],
        'masters' => [60, 110],
    ],
    'F' => [
        'children_a' => [16, 36],
        'children_b' => [18, 40],
        'kids' => [20, 50],
        'youth' => [26, 60],
        'pre_cadets_a' => [36, 63],
        'pre_cadets_b' => [40, 70],
        'cadets' => [40, 78],
        'juniors' => [48, 78],
        'seniors' => [48, 78],
        'masters' => [50, 90],
    ],
];

// Sample Italian first and last names
$maleFirstNames = ['Marco', 'Luca', 'Giuseppe', 'Antonio', 'Francesco', 'Alessandro', 'Giovanni', 'Carlo', 'Roberto', 'Davide', 'Andrea', 'Matteo', 'Lorenzo', 'Pietro', 'Tommaso', 'Gabriele', 'Simone', 'Alberto', 'Stefano', 'Massimo'];
$femaleFirstNames = ['Maria', 'Anna', 'Sara', 'Laura', 'Giulia', 'Chiara', 'Francesca', 'Alessia', 'Valentina', 'Martina', 'Sofia', 'Giorgia', 'Michela', 'Ilaria', 'Elisa', 'Camilla', 'Aurora', 'Beatrice', 'Vittoria', 'Nicole'];
$lastNames = ['Santoro', 'Bianchi', 'Rossi', 'Ferrari', 'Roma', 'Leonardo', 'Martinez', 'Gonzalez', 'Herrera', 'Romano', 'Conti', 'Ricci', 'Marchetti', 'Colombo', 'Bruno', 'Mancini', 'Messina', 'Sanna', 'De Luca', 'Cabrera', 'Diaz', 'Lopez', 'Garcia', 'Perez', 'Verdi', 'Neri', 'Costa', 'Mauri', 'Fabbri', 'Leoni', 'Grassi', 'Barbieri', 'Marta', 'Carmine', 'Vitale', 'Ortu', 'Melis', 'Serra', 'Mura', 'Pinna', 'Lecca'];

// Italian club name prefixes/suffixes
$clubPrefixes = ['Judo Club', 'Associazione Judo', 'Aquanera Judo', 'Shobu Kan', 'Seiryu Kan', 'Kiai', 'Spirito', 'Draghi', 'Leonesse', 'Campioni', 'Giovani', 'Speranza', 'Forza', 'Passione', 'Arte', 'Druido', 'Fucina', 'Fiamma', 'Eclisse'];
$clubSuffixes = ['Cagliari', 'Sassari', 'Nuoro', 'Oristano', 'Olbia', 'Alghero', 'Pula', 'Sestu', 'Villaspeciosa', 'Lanusei', 'Arzana', 'Alghero', 'Porto Torres', 'Iglesias', 'Ortauro', 'Tortolì', 'Tempio', 'Bosa', 'Guspini', 'Serramanna'];

/** @param non-empty-array<mixed> $arr */
function randomElement(array $arr): mixed
{
    return $arr[array_rand($arr)];
}

function randomDate(string $start, string $end): string
{
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    $randomTs = random_int((int) $startTs, (int) $endTs);
    return date('Y-m-d', $randomTs);
}

function randomBirthDate(int $minYear, int $maxYear): string
{
    $year = random_int($minYear, $maxYear);
    $month = random_int(1, 12);
    $day = random_int(1, 28);
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function randomWeight(int $min, int $max): float
{
    $intWeight = random_int($min, $max);
    $decimals = random_int(0, 99) / 100;
    return round($intWeight + $decimals, 2);
}

function randomBelt(): string
{
    $belts = array_map(fn($b) => $b->value, Belt::cases());
    return randomElement($belts);
}

/** @return array{province: string, city: string, postal_code: string} */
function randomSardinianLocation(): array
{
    $locations = SardinianLocation::all();
    $keys = array_keys($locations);
    assert(count($keys) > 0, 'Sardinian locations must not be empty');
    $province = randomElement($keys);
    $cities = $locations[$province];
    assert(is_array($cities) && count($cities) > 0, 'Cities must not be empty');
    $city = randomElement($cities);
    $postalCode = SardinianLocation::postalCode($province, $city);

    return [
        'province' => $province,
        'city' => $city,
        'postal_code' => $postalCode,
    ];
}

function randomAffiliation(): ?string
{
    $options = array_keys(Affiliation::options());
    // 10% chance of no affiliation (null)
    if (random_int(1, 10) === 1) {
        return null;
    }
    // Otherwise, pick 1-3 affiliations
    $count = random_int(1, 3);
    assert(count($options) > 0, 'Affiliation options must not be empty');
    $selected = [];
    for ($i = 0; $i < $count; $i++) {
        $selected[] = randomElement($options);
    }
    return json_encode(array_unique($selected), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

/**
 * @param array<string, list<int>> $ageClassBirthYears
 * @param array<string, array<string, array{int, int}>> $weightRanges
 * @param non-empty-array<string> $maleFirstNames
 * @param non-empty-array<string> $femaleFirstNames
 * @param non-empty-array<string> $lastNames
 * @return array{gender: string, first_name: string, last_name: string, date_of_birth: string, weight_kg: float, belt: string}
 */
function generateAthlete(array $ageClassBirthYears, array $weightRanges, array $maleFirstNames, array $femaleFirstNames, array $lastNames): array
{
    $gender = randomElement(['M', 'F']);
    $firstNames = $gender === 'M' ? $maleFirstNames : $femaleFirstNames;

    // Pick a random age class and get appropriate birth year
    $classKeys = array_keys($ageClassBirthYears);
    assert(count($classKeys) > 0, 'Age class birth years must not be empty');
    $classKey = randomElement($classKeys);
    $birthYears = $ageClassBirthYears[$classKey];
    assert(is_array($birthYears) && count($birthYears) > 0, 'Birth years must be a non-empty array');
    $birthYear = randomElement($birthYears);

    return [
        'gender' => $gender,
        'first_name' => randomElement($firstNames),
        'last_name' => randomElement($lastNames),
        'date_of_birth' => randomBirthDate($birthYear, $birthYear),
        'weight_kg' => randomWeight($weightRanges[$gender][$classKey][0], $weightRanges[$gender][$classKey][1]),
        'belt' => randomBelt(),
    ];
}

echo "Seeding database...\n\n";

$pdo = Database::connection();

// Transaction for safety
$pdo->beginTransaction();

try {
    // 0. Clear existing data
    echo "Clearing existing data...\n";
    $pdo->query('DELETE FROM entries');
    $pdo->query('DELETE FROM athletes');
    $pdo->query('DELETE FROM clubs');
    $pdo->query('DELETE FROM events');
    echo "  Done.\n\n";

    // 1. Create Events
    echo "Creating {$eventCount} events...\n";
    for ($i = 0; $i < $eventCount; $i++) {
        $eventNames = [
            'Trofei Sardi Judo',
            'Campionati Regionali Sardegna',
            'Open Judo Sardegna',
            'Gara Preagonistica Sarda',
            'Cintura e Collo Judo',
            'Festival Judo Estate',
        ];
        $location = randomElement($venues);
        $eventDate = randomDate('2026-08-01', '2027-06-30');
        $deadline = date('Y-m-d', strtotime($eventDate . ' -14 days'));
        $type = randomElement($eventTypes);

        $stmt = $pdo->prepare(
            'INSERT INTO events (name, date, location, organizer, registration_deadline, type, published, closed)
             VALUES (?, ?, ?, ?, ?, ?, 1, 0)'
        );
        $stmt->execute([
            $eventNames[$i % count($eventNames)],
            $eventDate,
            $location,
            'Comitato Regionale Judo Sardegna',
            $deadline,
            $type,
        ]);
        echo "  - Created event: {$eventNames[$i % count($eventNames)]} on {$eventDate}\n";
    }
    echo "\n";

    // 2. Create Clubs with Athletes
    echo "Creating {$clubCount} clubs with athletes (15-150 per club)...\n";
    $totalAthletes = 0;

    for ($i = 0; $i < $clubCount; $i++) {
        $location = randomSardinianLocation();
        $clubName = randomElement($clubPrefixes) . ' ' . randomElement($clubSuffixes) . ' ' . ($i + 1);
        $email = 'club' . ($i + 1) . '@example.it';

        $federalCode = 'SARD' . sprintf('%04d', $i + 1);
        $phone = '+39 0' . sprintf('%02d', $i % 90) . ' ' . sprintf('%07d', random_int(1000000, 9999999));
        $addressLine = 'Via del Judo ' . ($i + 1);
        $contactFirstName = randomElement($maleFirstNames);
        $contactLastName = randomElement($lastNames);

        $stmt = $pdo->prepare(
            'INSERT INTO clubs (federal_code, name, email, phone, address_line, postal_code, city, province, contact_first_name, contact_last_name, affiliation, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $federalCode,
            $clubName,
            mb_strtolower(trim($email)),
            $phone,
            $addressLine,
            $location['postal_code'],
            $location['city'],
            $location['province'],
            $contactFirstName,
            $contactLastName,
            randomAffiliation(),
            password_hash('000000000000', PASSWORD_DEFAULT),
        ]);

        $clubId = (int) $pdo->lastInsertId();

        // Add athletes to this club
        $athleteCount = random_int($minAthletesPerClub, $maxAthletesPerClub);
        for ($a = 0; $a < $athleteCount; $a++) {
            $athlete = generateAthlete($ageClassBirthYears, $weightRanges, $maleFirstNames, $femaleFirstNames, $lastNames);
            $stmt = $pdo->prepare(
                'INSERT INTO athletes (club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $clubId,
                $athlete['last_name'],
                $athlete['first_name'],
                $athlete['gender'],
                $athlete['date_of_birth'],
                $athlete['weight_kg'],
                $athlete['belt'],
            ]);
        }

        $totalAthletes += $athleteCount;
        if (($i + 1) % 10 === 0) {
            $progressClubs = $i + 1;
            echo "  - Created {$progressClubs} clubs, {$totalAthletes} athletes so far...\n";
        }
    }

    echo "\n";
    echo "Seeding complete!\n";
    echo "Total events: {$eventCount}\n";
    echo "Total clubs: {$clubCount}\n";
    echo "Total athletes: {$totalAthletes}\n";

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
