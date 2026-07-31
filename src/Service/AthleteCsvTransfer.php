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
        [
            $athletesById,
            $athletesByMembership,
            $athletesByIdentity,
            $athletesByName,
        ] = $this->athleteIndexes($athletes);
        $operations = [];
        $issues = [];
        $reconciliations = [];
        $seenMemberships = [];
        $seenNames = [];
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

            $nameMatches = $this->matchingAthletesByName($row, $athletesByName);
            if ($matches === [] && $nameMatches !== []) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.duplicate_name',
                    [],
                    [],
                    count($nameMatches) === 1 ? $nameMatches[0]->id : null
                );
                continue;
            }
            if ($matches !== []) {
                $matchedId = $matches[0]->id;
                $hasNameConflict = array_filter(
                    $nameMatches,
                    static fn(Athlete $athlete): bool => $athlete->id !== $matchedId
                ) !== [];
                if ($hasNameConflict) {
                    $issues[] = new AthleteImportIssue(
                        $sourceRow['number'],
                        $identity,
                        'club.area.csv.ambiguous_match'
                    );
                    continue;
                }
            }
            $existing = $matches[0] ?? null;
            $importedIdentity = $this->athleteIdentity($row);
            $reconcilesIdentity = $existing !== null
                && $importedIdentity !== null
                && $importedIdentity === $this->athleteIdentity([
                    'last_name' => $existing->last_name,
                    'first_name' => $existing->first_name,
                    'birth_date' => $existing->birth_date,
                ]);

            $missingFields = $this->missingRequiredFields($row);
            if (
                $missingFields !== []
                && $existing !== null
                && !$reconcilesIdentity
                && !$mergeIncomplete
            ) {
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

            $resolutions = [];
            if ($existing !== null) {
                if (
                    $row['membership_number'] === ''
                    && !ctype_digit($row['athlete_id'])
                    && !$reconcilesIdentity
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

                if ($reconcilesIdentity) {
                    [$row, $resolutions] = $this->reconcileWithExisting($row, $existing);
                } else {
                    $row = $this->mergeWithExisting($row, $raw, $existing);
                }
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

            $name = $this->nameIdentity($row);
            if ($name !== null && isset($seenNames[$name])) {
                $issues[] = new AthleteImportIssue(
                    $sourceRow['number'],
                    $identity,
                    'club.area.csv.duplicate_name'
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
            if ($name !== null) {
                $seenNames[$name] = true;
            }
            if ($existing !== null && $resolutions !== []) {
                $reconciliations[] = new AthleteImportReconciliation(
                    $sourceRow['number'],
                    $identity,
                    $existing->id,
                    $resolutions
                );
            }

            $operations[] = [
                'existing' => $existing,
                'data' => $this->persistenceRow($row),
            ];
        }

        if ($dataRows === 0) {
            throw new AthleteCsvImportException('club.area.csv.no_rows');
        }

        return $this->persist($operations, $issues, $reconciliations, $clubId);
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
            'last_name' => $this->titleCaseName($raw['last_name'] ?? ''),
            'first_name' => $this->titleCaseName($raw['first_name'] ?? ''),
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
     *     2: array<string, list<Athlete>>,
     *     3: array<string, list<Athlete>>
     * }
     */
    private function athleteIndexes(array $athletes): array
    {
        $byId = [];
        $byMembership = [];
        $byIdentity = [];
        $byName = [];

        foreach ($athletes as $athlete) {
            $byId[$athlete->id] = $athlete;
            $membership = $this->identityValue($athlete->membership_number ?? '');
            if ($membership !== '') {
                $byMembership[$membership][] = $athlete;
            }

            $identity = $this->athleteIdentity([
                'last_name' => $athlete->last_name,
                'first_name' => $athlete->first_name,
                'birth_date' => $athlete->birth_date,
            ]);
            if ($identity !== null) {
                $byIdentity[$identity][] = $athlete;
            }

            $name = $this->nameIdentity([
                'last_name' => $athlete->last_name,
                'first_name' => $athlete->first_name,
            ]);
            if ($name !== null) {
                $byName[$name][] = $athlete;
            }
        }

        return [$byId, $byMembership, $byIdentity, $byName];
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
        $matches = [];
        if (ctype_digit($row['athlete_id'])) {
            $athleteId = (int) $row['athlete_id'];
            if (!isset($athletesById[$athleteId])) {
                return [];
            }

            $matches[$athleteId] = $athletesById[$athleteId];
        } else {
            $membership = $this->identityValue($row['membership_number']);
            if ($membership !== '') {
                foreach ($athletesByMembership[$membership] ?? [] as $athlete) {
                    $matches[$athlete->id] = $athlete;
                }
            }
        }

        $identity = $this->athleteIdentity($row);
        if ($identity !== null) {
            foreach ($athletesByIdentity[$identity] ?? [] as $athlete) {
                $matches[$athlete->id] = $athlete;
            }
        }

        return array_values($matches);
    }

    /**
     * @param array<string, string> $row
     * @param array<string, list<Athlete>> $athletesByName
     * @return list<Athlete>
     */
    private function matchingAthletesByName(array $row, array $athletesByName): array
    {
        $name = $this->nameIdentity($row);

        return $name !== null ? ($athletesByName[$name] ?? []) : [];
    }

    /**
     * @param array<string, string> $row
     */
    private function athleteIdentity(array $row): ?string
    {
        $name = $this->nameIdentity($row);
        $birthDate = trim($row['birth_date'] ?? '');

        if ($name === null || $birthDate === '') {
            return null;
        }

        return $name . "\0" . $birthDate;
    }

    /**
     * @param array<string, string> $row
     */
    private function nameIdentity(array $row): ?string
    {
        $lastName = $this->identityValue($row['last_name'] ?? '');
        $firstName = $this->identityValue($row['first_name'] ?? '');

        if ($lastName === '' || $firstName === '') {
            return null;
        }

        return implode("\0", [$lastName, $firstName]);
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
            'last_name' => $this->titleCaseName($existing->last_name),
            'first_name' => $this->titleCaseName($existing->first_name),
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
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function reconcileWithExisting(array $row, Athlete $existing): array
    {
        $resolutions = [];
        $lastName = $this->titleCaseName($existing->last_name);
        $firstName = $this->titleCaseName($existing->first_name);
        if ($existing->last_name !== $row['last_name']) {
            $resolutions['last_name'] = 'normalized';
        }
        if ($existing->first_name !== $row['first_name']) {
            $resolutions['first_name'] = 'normalized';
        }
        $row['last_name'] = $lastName;
        $row['first_name'] = $firstName;

        [$row['gender'], $resolution] = $this->reconcileDatabaseValue(
            $existing->gender,
            $row['gender']
        );
        if ($resolution !== null) {
            $resolutions['gender'] = $resolution;
        }

        [$row['weight_kg'], $resolution] = $this->reconcileWeight(
            $existing->weight_kg,
            $row['weight_kg']
        );
        if ($resolution !== null) {
            $resolutions['weight_kg'] = $resolution;
        }

        [$row['belt'], $resolution] = $this->reconcileBelt($existing->belt, $row['belt']);
        if ($resolution !== null) {
            $resolutions['belt'] = $resolution;
        }

        [$row['membership_number'], $resolution] = $this->reconcileText(
            $existing->membership_number,
            $row['membership_number'],
            ' / ',
            80,
            true
        );
        if ($resolution !== null) {
            $resolutions['membership_number'] = $resolution;
        }

        [$row['notes'], $resolution] = $this->reconcileText(
            $existing->notes,
            $row['notes'],
            "\n",
            65_535,
            false
        );
        if ($resolution !== null) {
            $resolutions['notes'] = $resolution;
        }

        return [$row, $resolutions];
    }

    /** @return array{0: string, 1: string|null} */
    private function reconcileDatabaseValue(string $existing, string $imported): array
    {
        if ($existing === '') {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }
        if ($imported === '') {
            return [$existing, 'used_database'];
        }
        if ($existing === $imported) {
            return [$existing, null];
        }

        return [$existing, 'kept_database'];
    }

    /** @return array{0: string, 1: string|null} */
    private function reconcileWeight(?float $existing, string $imported): array
    {
        if ($existing === null) {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }

        $databaseWeight = $this->formatWeight($existing);
        if ($imported === '') {
            return [$databaseWeight, 'used_database'];
        }
        if (is_numeric($imported) && $this->sameWeight($existing, (float) $imported)) {
            return [$databaseWeight, null];
        }

        return [$databaseWeight, 'kept_database'];
    }

    /** @return array{0: string, 1: string|null} */
    private function reconcileBelt(string $existing, string $imported): array
    {
        if ($existing === '') {
            return [$imported, $imported !== '' ? 'used_imported' : null];
        }
        if ($imported === '') {
            return [$existing, 'used_database'];
        }
        if ($existing === $imported) {
            return [$existing, null];
        }

        $existingRank = $this->beltRank($existing);
        $importedRank = $this->beltRank($imported);
        if ($existingRank === null || $importedRank === null) {
            return [$existing, 'kept_database'];
        }

        return [
            $importedRank > $existingRank ? $imported : $existing,
            'higher_belt',
        ];
    }

    /** @return array{0: string, 1: string|null} */
    private function reconcileText(
        ?string $existing,
        string $imported,
        string $separator,
        int $maximumLength,
        bool $caseInsensitive
    ): array {
        $existing = $existing !== null && trim($existing) !== '' ? trim($existing) : null;
        $imported = trim($imported);
        $imported = $imported !== '' ? $imported : null;

        if ($existing === null) {
            return [$imported ?? '', $imported !== null ? 'used_imported' : null];
        }
        if ($imported === null) {
            return [$existing, 'used_database'];
        }

        if ($this->containsTextValue($existing, $imported, $separator, $caseInsensitive)) {
            return [$existing, null];
        }
        $combined = $this->containsTextValue($imported, $existing, $separator, $caseInsensitive)
            ? $imported
            : $existing . $separator . $imported;
        if ($this->length($combined) > $maximumLength) {
            return [$existing, 'kept_database'];
        }

        return [$combined, 'combined'];
    }

    private function containsTextValue(
        string $container,
        string $value,
        string $separator,
        bool $caseInsensitive
    ): bool {
        $container = $separator . $container . $separator;
        $value = $separator . $value . $separator;
        if ($caseInsensitive) {
            $container = mb_strtolower($container, 'UTF-8');
            $value = mb_strtolower($value, 'UTF-8');
        }

        return str_contains($container, $value);
    }

    private function beltRank(string $value): ?int
    {
        $belt = Belt::tryFromValue($value);
        if ($belt === null) {
            return null;
        }

        $rank = array_search($belt, Belt::cases(), true);

        return is_int($rank) ? $rank : null;
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
     * @param list<AthleteImportReconciliation> $reconciliations
     */
    private function persist(
        array $operations,
        array $issues,
        array $reconciliations,
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

            return new AthleteCsvImportResult(
                $created,
                $updated,
                $unchanged,
                $issues,
                $reconciliations
            );
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

    private function titleCaseName(string $value): string
    {
        return mb_convert_case($this->cleanText($value), MB_CASE_TITLE, 'UTF-8');
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
