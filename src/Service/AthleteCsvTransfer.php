<?php

declare(strict_types=1);

namespace App\Service;

use App\Localization;
use App\Model\Athlete;
use App\Model\Belt;
use App\Model\Database;
use App\Model\Gender;
use App\Validation\AthleteInputValidator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AthleteCsvTransfer
{
    public const MAX_BYTES = 2_097_152;
    public const MAX_ROWS = 5_000;

    private const HEADER_SCAN_ROWS = 25;

    /** @var list<string> */
    private const EXPORT_FIELDS = [
        'last_name',
        'first_name',
        'gender',
        'birth_date',
        'weight_kg',
        'belt',
        'membership_number',
        'notes',
    ];

    /** @var list<string> */
    private const REQUIRED_HEADER_FIELDS = [
        'last_name',
        'first_name',
        'gender',
        'birth_date',
    ];

    /** @var list<string> */
    private const REQUIRED_ATHLETE_FIELDS = [
        'last_name',
        'first_name',
        'gender',
        'birth_date',
        'belt',
    ];

    /**
     * Aliases are normalized to lowercase words separated by one space.
     * Earlier aliases win when a file contains more than one possible source
     * column (for example both "Matricola" and "Cod.Tessera").
     *
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'athlete_id' => [
            'athlete id',
            'id atleta',
            'id atleta archivio',
        ],
        'last_name' => [
            'last name',
            'surname',
            'family name',
            'cognome',
        ],
        'first_name' => [
            'first name',
            'given name',
            'name',
            'nome',
        ],
        'gender' => [
            'gender',
            'sex',
            'genere',
            'sesso',
        ],
        'birth_date' => [
            'birth date',
            'date of birth',
            'data di nascita',
            'data nascita',
            'nato il',
            'nata il',
            'nascita',
        ],
        'weight_kg' => [
            'weight kg',
            'weight',
            'peso kg',
            'peso',
        ],
        'belt' => [
            'belt',
            'cintura',
            'grado cintura',
            'grado',
        ],
        'membership_number' => [
            'membership number',
            'license number',
            'licence number',
            'numero tessera',
            'numero di tessera',
            'n tessera',
            'cod tessera',
            'codice tessera',
            'tessera',
            'matricola',
        ],
        'notes' => [
            'notes',
            'note',
            'annotazioni',
        ],
    ];

    public function export(int $clubId): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the CSV export stream.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $headers = array_map(
                static fn(string $field): string =>
                    Localization::trans('club.area.csv.headers.' . $field),
                self::EXPORT_FIELDS
            );
            $this->writeRow($stream, $headers);

            foreach (Athlete::findByClub($clubId) as $athlete) {
                $this->writeRow($stream, [
                    $this->spreadsheetSafe($athlete->last_name),
                    $this->spreadsheetSafe($athlete->first_name),
                    $athlete->gender,
                    $athlete->birth_date,
                    $this->formatWeight($athlete->weight_kg),
                    $athlete->belt,
                    $this->spreadsheetSafe($athlete->membership_number ?? ''),
                    $this->spreadsheetSafe($athlete->notes ?? ''),
                ]);
            }

            rewind($stream);
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                throw new RuntimeException('Unable to read the CSV export stream.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    public function import(
        string $path,
        int $clubId,
        bool $mergeIncomplete = false
    ): AthleteCsvImportResult {
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new AthleteCsvImportException('club.area.csv.too_large');
        }

        $table = (new SpreadsheetTableReader())->read(
            $path,
            self::MAX_ROWS + self::HEADER_SCAN_ROWS + 1
        );
        [$headerPosition, $columns] = $this->findHeader($table);
        $athletes = Athlete::findByClub($clubId);
        [$athletesById, $athletesByMembership, $athletesByIdentity] = $this->athleteIndexes($athletes);
        $operations = [];
        $issues = [];
        $seenMemberships = [];
        $seenTargets = [];
        $dataRows = 0;

        foreach (array_slice($table->rows, $headerPosition + 1) as $sourceRow) {
            if ($this->isBlankRow($sourceRow['values'])) {
                continue;
            }

            $dataRows++;
            if ($dataRows > self::MAX_ROWS) {
                throw new AthleteCsvImportException('club.area.csv.too_many_rows');
            }

            $raw = $this->mappedValues($sourceRow['values'], $columns);
            $identity = $this->rowIdentity($raw, $sourceRow['number']);
            if (!$this->hasValidEncoding($raw)) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.invalid_encoding'
                );
                continue;
            }

            $row = $this->normalizeRow($raw, $table->excelDate1904);
            $membershipKey = $this->identityValue($row['membership_number']);
            if ($membershipKey !== '' && isset($seenMemberships[$membershipKey])) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.duplicate_membership'
                );
                continue;
            }
            if ($membershipKey !== '') {
                $seenMemberships[$membershipKey] = true;
            }

            $matches = $this->matchingAthletes(
                $row,
                $athletesById,
                $athletesByMembership,
                $athletesByIdentity
            );
            if (count($matches) > 1) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.ambiguous_match'
                );
                continue;
            }
            $existing = $matches[0] ?? null;

            $missingFields = $this->missingRequiredFields($row);
            if ($missingFields !== [] && $existing !== null && !$mergeIncomplete) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.merge_required',
                    [],
                    $missingFields,
                    $existing->id
                );
                continue;
            }

            if ($existing !== null) {
                if (
                    $row['membership_number'] === ''
                    && !ctype_digit($row['athlete_id'])
                    && !$mergeIncomplete
                ) {
                    $issues[] = new AthleteImportIssue(
                        $sourceRow['number'],
                        $identity,
                        'club.area.csv.merge_identity_required',
                        [],
                        ['membership_number'],
                        $existing->id
                    );
                    continue;
                }

                $row = $this->mergeWithExisting($row, $raw, $existing);
            } elseif ($row['membership_number'] === '') {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.missing_identity',
                    [],
                    ['membership_number']
                );
                continue;
            }

            $validationKeys = $this->validationErrors($row);
            if ($validationKeys !== []) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.invalid_row',
                    $validationKeys
                );
                continue;
            }

            if ($existing !== null && isset($seenTargets[$existing->id])) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.duplicate_target',
                    [],
                    [],
                    $existing->id
                );
                continue;
            }
            if ($existing !== null) {
                $seenTargets[$existing->id] = true;
            }

            $operations[] = [
                'existing' => $existing,
                'data' => $this->persistenceRow($row),
            ];
        }

        if ($dataRows === 0) {
            throw new AthleteCsvImportException('club.area.csv.no_rows');
        }

        return $this->persist($operations, $issues, $clubId);
    }

    /**
     * @return array{
     *     0: int,
     *     1: array<string, int>
     * }
     */
    private function findHeader(SpreadsheetTable $table): array
    {
        foreach (array_slice($table->rows, 0, self::HEADER_SCAN_ROWS) as $position => $row) {
            $columns = [];
            $scores = [];

            foreach ($row['values'] as $index => $heading) {
                $normalized = $this->normalizeHeading($heading);
                if ($normalized === '') {
                    continue;
                }

                foreach (self::HEADER_ALIASES as $field => $aliases) {
                    $score = array_search($normalized, $aliases, true);
                    if ($score === false || (isset($scores[$field]) && $scores[$field] <= $score)) {
                        continue;
                    }

                    $columns[$field] = $index;
                    $scores[$field] = $score;
                }
            }

            if (array_diff(self::REQUIRED_HEADER_FIELDS, array_keys($columns)) === []) {
                return [$position, $columns];
            }
        }

        throw new AthleteCsvImportException('club.area.csv.invalid_header');
    }

    /**
     * @param list<string> $values
     * @param array<string, int> $columns
     * @return array<string, string>
     */
    private function mappedValues(array $values, array $columns): array
    {
        $mapped = [];
        foreach ($columns as $field => $index) {
            $mapped[$field] = $this->restoreSpreadsheetValue(trim($values[$index] ?? ''));
        }

        return $mapped;
    }

    /**
     * @param array<string, string> $raw
     * @return array{
     *     athlete_id: string,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: string,
     *     belt: string,
     *     membership_number: string,
     *     notes: string
     * }
     */
    private function normalizeRow(array $raw, bool $excelDate1904): array
    {
        $genderInput = $raw['gender'] ?? '';
        $gender = Gender::tryFromValue($genderInput);
        $beltInput = $raw['belt'] ?? '';
        $belt = $this->belt($beltInput);

        return [
            'athlete_id' => trim($raw['athlete_id'] ?? ''),
            'last_name' => $this->cleanText($raw['last_name'] ?? ''),
            'first_name' => $this->cleanText($raw['first_name'] ?? ''),
            'gender' => $gender?->value ?? trim($genderInput),
            'birth_date' => $this->birthDate($raw['birth_date'] ?? '', $excelDate1904),
            'weight_kg' => $this->weight($raw['weight_kg'] ?? ''),
            'belt' => $belt?->value ?? trim($beltInput),
            'membership_number' => $this->cleanText($raw['membership_number'] ?? ''),
            'notes' => trim($raw['notes'] ?? ''),
        ];
    }

    /**
     * @param list<Athlete> $athletes
     * @return array{
     *     0: array<int, Athlete>,
     *     1: array<string, list<Athlete>>,
     *     2: array<string, list<Athlete>>
     * }
     */
    private function athleteIndexes(array $athletes): array
    {
        $byId = [];
        $byMembership = [];
        $byIdentity = [];

        foreach ($athletes as $athlete) {
            $byId[$athlete->id] = $athlete;
            $membership = $this->identityValue($athlete->membership_number ?? '');
            if ($membership !== '') {
                $byMembership[$membership][] = $athlete;
            }

            $identity = $this->naturalIdentity([
                'last_name' => $athlete->last_name,
                'first_name' => $athlete->first_name,
                'gender' => $athlete->gender,
                'birth_date' => $athlete->birth_date,
            ]);
            if ($identity !== null) {
                $byIdentity[$identity][] = $athlete;
            }
        }

        return [$byId, $byMembership, $byIdentity];
    }

    /**
     * @param array{
     *     athlete_id: string,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: string,
     *     belt: string,
     *     membership_number: string,
     *     notes: string
     * } $row
     * @param array<int, Athlete> $athletesById
     * @param array<string, list<Athlete>> $athletesByMembership
     * @param array<string, list<Athlete>> $athletesByIdentity
     * @return list<Athlete>
     */
    private function matchingAthletes(
        array $row,
        array $athletesById,
        array $athletesByMembership,
        array $athletesByIdentity
    ): array {
        if (ctype_digit($row['athlete_id'])) {
            $athleteId = (int) $row['athlete_id'];

            return isset($athletesById[$athleteId]) ? [$athletesById[$athleteId]] : [];
        }

        $membership = $this->identityValue($row['membership_number']);
        if ($membership !== '') {
            return $athletesByMembership[$membership] ?? [];
        }

        $identity = $this->naturalIdentity($row);

        return $identity !== null ? ($athletesByIdentity[$identity] ?? []) : [];
    }

    /**
     * @param array<string, string> $row
     */
    private function naturalIdentity(array $row): ?string
    {
        $lastName = $this->identityValue($row['last_name'] ?? '');
        $firstName = $this->identityValue($row['first_name'] ?? '');
        $gender = strtoupper(trim($row['gender'] ?? ''));
        $birthDate = trim($row['birth_date'] ?? '');

        if ($lastName === '' || $firstName === '' || $gender === '' || $birthDate === '') {
            return null;
        }

        return implode("\0", [$lastName, $firstName, $gender, $birthDate]);
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function missingRequiredFields(array $row): array
    {
        $missing = [];
        foreach (self::REQUIRED_ATHLETE_FIELDS as $field) {
            if (trim($row[$field] ?? '') === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param array{
     *     athlete_id: string,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: string,
     *     belt: string,
     *     membership_number: string,
     *     notes: string
     * } $row
     * @param array<string, string> $raw
     * @return array{
     *     athlete_id: string,
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: string,
     *     belt: string,
     *     membership_number: string,
     *     notes: string
     * }
     */
    private function mergeWithExisting(array $row, array $raw, Athlete $existing): array
    {
        $fallbacks = [
            'last_name' => $existing->last_name,
            'first_name' => $existing->first_name,
            'gender' => $existing->gender,
            'birth_date' => $existing->birth_date,
            'weight_kg' => $this->formatWeight($existing->weight_kg),
            'belt' => $existing->belt,
            'membership_number' => $existing->membership_number ?? '',
        ];

        foreach ($fallbacks as $field => $fallback) {
            if ($row[$field] === '') {
                $row[$field] = $fallback;
            }
        }

        if (!array_key_exists('notes', $raw)) {
            $row['notes'] = $existing->notes ?? '';
        }

        return $row;
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function validationErrors(array $row): array
    {
        $validationKeys = AthleteInputValidator::errors(
            $row['last_name'],
            $row['first_name'],
            $row['gender'],
            $row['birth_date'],
            $row['weight_kg'],
            $row['belt']
        );
        if ($row['weight_kg'] === '') {
            $validationKeys = array_values(array_filter(
                $validationKeys,
                static fn(string $key): bool => $key !== 'validation.athlete_weight_invalid'
            ));
        }

        return array_values(array_unique(array_merge(
            $validationKeys,
            $this->lengthErrors($row)
        )));
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function lengthErrors(array $row): array
    {
        $errors = [];
        if ($this->length($row['last_name'] ?? '') > 120) {
            $errors[] = 'club.area.csv.last_name_too_long';
        }
        if ($this->length($row['first_name'] ?? '') > 120) {
            $errors[] = 'club.area.csv.first_name_too_long';
        }
        if ($this->length($row['membership_number'] ?? '') > 80) {
            $errors[] = 'club.area.csv.membership_too_long';
        }
        if ($this->length($row['notes'] ?? '') > 65_535) {
            $errors[] = 'club.area.csv.notes_too_long';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $row
     * @return array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }
     */
    private function persistenceRow(array $row): array
    {
        return [
            'last_name' => $row['last_name'],
            'first_name' => $row['first_name'],
            'gender' => $row['gender'],
            'birth_date' => $row['birth_date'],
            'weight_kg' => $row['weight_kg'] !== '' ? (float) $row['weight_kg'] : null,
            'belt' => $row['belt'],
            'membership_number' => $row['membership_number'] !== ''
                ? $row['membership_number']
                : null,
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ];
    }

    /**
     * @param list<array{
     *     existing: Athlete|null,
     *     data: array{
     *         last_name: string,
     *         first_name: string,
     *         gender: string,
     *         birth_date: string,
     *         weight_kg: float|null,
     *         belt: string,
     *         membership_number: string|null,
     *         notes: string|null
     *     }
     * }> $operations
     * @param list<AthleteImportIssue> $issues
     */
    private function persist(
        array $operations,
        array $issues,
        int $clubId
    ): AthleteCsvImportResult {
        $database = Database::connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }

        try {
            $insert = $database->prepare(
                'INSERT INTO athletes
                 (club_id, last_name, first_name, gender, birth_date, weight_kg, belt,
                  membership_number, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $update = $database->prepare(
                'UPDATE athletes
                 SET last_name = ?, first_name = ?, gender = ?, birth_date = ?, weight_kg = ?,
                     belt = ?, membership_number = ?, notes = ?
                 WHERE id = ? AND club_id = ?'
            );
            $created = 0;
            $updated = 0;
            $unchanged = 0;

            foreach ($operations as $operation) {
                $row = $operation['data'];
                $existing = $operation['existing'];

                if ($existing !== null) {
                    if ($this->sameAthlete($existing, $row)) {
                        $unchanged++;
                        continue;
                    }

                    $update->execute([
                        $row['last_name'],
                        $row['first_name'],
                        $row['gender'],
                        $row['birth_date'],
                        $row['weight_kg'],
                        $row['belt'],
                        $row['membership_number'],
                        $row['notes'],
                        $existing->id,
                        $clubId,
                    ]);
                    $updated++;
                    continue;
                }

                $insert->execute([
                    $clubId,
                    $row['last_name'],
                    $row['first_name'],
                    $row['gender'],
                    $row['birth_date'],
                    $row['weight_kg'],
                    $row['belt'],
                    $row['membership_number'],
                    $row['notes'],
                ]);
                $created++;
            }

            if ($ownsTransaction) {
                $database->commit();
            }

            return new AthleteCsvImportResult($created, $updated, $unchanged, $issues);
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     birth_date: string,
     *     weight_kg: float|null,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * } $row
     */
    private function sameAthlete(Athlete $athlete, array $row): bool
    {
        return $athlete->last_name === $row['last_name']
            && $athlete->first_name === $row['first_name']
            && $athlete->gender === $row['gender']
            && $athlete->birth_date === $row['birth_date']
            && $this->sameWeight($athlete->weight_kg, $row['weight_kg'])
            && $athlete->belt === $row['belt']
            && $athlete->membership_number === $row['membership_number']
            && $athlete->notes === $row['notes'];
    }

    private function sameWeight(?float $existing, ?float $imported): bool
    {
        if ($existing === null || $imported === null) {
            return $existing === $imported;
        }

        return abs($existing - $imported) < 0.0001;
    }

    /**
     * @param resource $stream
     * @param list<string> $fields
     */
    private function writeRow($stream, array $fields): void
    {
        if (fputcsv($stream, $fields, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('Unable to write the CSV export.');
        }
    }

    /** @param list<string> $fields */
    private function isBlankRow(array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim($field) !== '') {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $fields */
    private function hasValidEncoding(array $fields): bool
    {
        foreach ($fields as $field) {
            if (preg_match('//u', $field) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, string> $row */
    private function rowIdentity(array $row, int $rowNumber): string
    {
        $name = trim(implode(' ', array_filter([
            trim($row['last_name'] ?? ''),
            trim($row['first_name'] ?? ''),
        ], static fn(string $value): bool => $value !== '')));
        $membership = trim($row['membership_number'] ?? '');

        if ($name !== '' && $membership !== '') {
            return $name . ' (' . $membership . ')';
        }
        if ($name !== '') {
            return $name;
        }
        if ($membership !== '') {
            return $membership;
        }

        return '#' . $rowNumber;
    }

    private function normalizeHeading(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? trim($value);
        if (preg_match('//u', $value) !== 1) {
            return '';
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'à' => 'a',
            'á' => 'a',
            'è' => 'e',
            'é' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'ò' => 'o',
            'ó' => 'o',
            'ù' => 'u',
            'ú' => 'u',
        ]);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function cleanText(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function identityValue(string $value): string
    {
        return mb_strtolower($this->cleanText($value), 'UTF-8');
    }

    private function belt(string $value): ?Belt
    {
        $direct = Belt::tryFromValue($value);
        if ($direct !== null) {
            return $direct;
        }

        $normalized = $this->normalizeHeading($value);
        $normalized = trim(
            preg_replace('/\s+\d+\s*(?:o|dan)?(?:\s+dan)?$/u', '', $normalized) ?? $normalized
        );

        $aliases = [
            'bianca' => Belt::White,
            'bianco' => Belt::White,
            'bianca gialla' => Belt::WhiteYellow,
            'bianco giallo' => Belt::WhiteYellow,
            'gialla' => Belt::Yellow,
            'giallo' => Belt::Yellow,
            'gialla arancio' => Belt::YellowOrange,
            'gialla arancione' => Belt::YellowOrange,
            'arancio' => Belt::Orange,
            'arancione' => Belt::Orange,
            'arancio verde' => Belt::OrangeGreen,
            'arancione verde' => Belt::OrangeGreen,
            'verde' => Belt::Green,
            'verde blu' => Belt::GreenBlue,
            'blu' => Belt::Blue,
            'marrone' => Belt::Brown,
            'nera' => Belt::Black,
            'nero' => Belt::Black,
            'rossa bianca' => Belt::RedWhite,
            'rosso bianco' => Belt::RedWhite,
            'rossa' => Belt::Red,
            'rosso' => Belt::Red,
        ];
        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        foreach (Belt::cases() as $belt) {
            if (
                $normalized === $this->normalizeHeading($belt->label('en'))
                || $normalized === $this->normalizeHeading($belt->label('it'))
            ) {
                return $belt;
            }
        }

        return null;
    }

    private function birthDate(string $value, bool $excelDate1904): string
    {
        $value = trim($value);
        if (
            preg_match('/^\d+(?:\.\d+)?$/', $value) === 1
            && (float) $value >= 10_000
            && (float) $value <= 100_000
        ) {
            $days = (int) floor((float) $value);
            $base = new DateTimeImmutable(
                $excelDate1904 ? '1904-01-01' : '1899-12-30',
                new DateTimeZone('UTC')
            );

            return $base->modify('+' . $days . ' days')->format('Y-m-d');
        }

        foreach (
            [
                '!Y-m-d',
                '!d/m/Y',
                '!d-m-Y',
                '!d.m.Y',
                '!Y/m/d',
                '!Ymd',
                '!Y-m-d H:i:s',
                '!Y-m-d\TH:i:s',
            ] as $format
        ) {
            $date = DateTimeImmutable::createFromFormat(
                $format,
                $value,
                new DateTimeZone('UTC')
            );
            $errors = DateTimeImmutable::getLastErrors();
            if (
                $date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    private function weight(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s*(?:kg|chilogrammi?)$/iu', '', $value) ?? $value;

        return str_replace(',', '.', trim($value));
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    private function restoreSpreadsheetValue(string $value): string
    {
        return preg_match("/^'[=+\\-@]/u", $value) === 1 ? substr($value, 1) : $value;
    }

    private function formatWeight(?float $weight): string
    {
        if ($weight === null) {
            return '';
        }

        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }

    private function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
