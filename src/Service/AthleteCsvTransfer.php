<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Athlete;
use App\Model\Belt;
use App\Model\Database;
use App\Model\Gender;
use App\Validation\AthleteInputValidator;
use PDO;
use RuntimeException;

final class AthleteCsvTransfer
{
    public const MAX_BYTES = 2_097_152;
    public const MAX_ROWS = 5_000;

    /** @var list<string> */
    private const HEADERS = [
        'last_name',
        'first_name',
        'gender',
        'date_of_birth',
        'weight_kg',
        'belt',
        'membership_number',
        'notes',
    ];

    public function export(int $clubId): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the CSV export stream.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, self::HEADERS);

            foreach (Athlete::findByClub($clubId) as $athlete) {
                $this->writeRow($stream, [
                    $this->spreadsheetSafe($athlete->last_name),
                    $this->spreadsheetSafe($athlete->first_name),
                    $athlete->gender,
                    $athlete->date_of_birth,
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

    public function import(string $path, int $clubId): AthleteCsvImportResult
    {
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            throw new AthleteCsvImportException('club.area.csv.too_large');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new AthleteCsvImportException('club.area.csv.upload_failed');
        }

        try {
            $rows = $this->readRows($stream);
        } finally {
            fclose($stream);
        }

        return $this->persist($rows, $clubId);
    }

    /**
     * @param resource $stream
     * @return list<array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     date_of_birth: string,
     *     weight_kg: float,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }>
     */
    private function readRows($stream): array
    {
        $headerLine = fgets($stream);
        if ($headerLine === false) {
            throw new AthleteCsvImportException('club.area.csv.invalid_header');
        }

        [$headers, $delimiter] = $this->parseHeader($headerLine);
        $rows = [];
        $membershipNumbers = [];
        $rowNumber = 1;

        while (($fields = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;
            if ($this->isBlankRow($fields)) {
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw new AthleteCsvImportException('club.area.csv.too_many_rows');
            }
            if (count($fields) !== count($headers)) {
                throw new AthleteCsvImportException('club.area.csv.invalid_columns', $rowNumber);
            }

            $values = [];
            foreach ($fields as $field) {
                $value = $field === null ? '' : $field;
                if (preg_match('//u', $value) !== 1) {
                    throw new AthleteCsvImportException('club.area.csv.invalid_encoding', $rowNumber);
                }
                $values[] = $this->restoreSpreadsheetValue(trim($value));
            }

            $combined = array_combine($headers, $values);
            $gender = Gender::tryFromValue($combined['gender']);
            $belt = Belt::tryFromValue($combined['belt']);
            $weightInput = str_replace(',', '.', $combined['weight_kg']);
            $validationKeys = AthleteInputValidator::errors(
                $combined['last_name'],
                $combined['first_name'],
                $gender?->value ?? $combined['gender'],
                $combined['date_of_birth'],
                $weightInput,
                $belt?->value ?? $combined['belt']
            );
            $validationKeys = array_merge($validationKeys, $this->lengthErrors($combined));
            if ($validationKeys !== []) {
                throw new AthleteCsvImportException(
                    'club.area.csv.invalid_row',
                    $rowNumber,
                    array_values(array_unique($validationKeys))
                );
            }

            $membershipNumber = $combined['membership_number'];
            if ($membershipNumber !== '') {
                $membershipKey = strtolower($membershipNumber);
                if (isset($membershipNumbers[$membershipKey])) {
                    throw new AthleteCsvImportException(
                        'club.area.csv.duplicate_membership',
                        $rowNumber
                    );
                }
                $membershipNumbers[$membershipKey] = true;
            }

            $rows[] = [
                'last_name' => $combined['last_name'],
                'first_name' => $combined['first_name'],
                'gender' => $gender?->value ?? '',
                'date_of_birth' => $combined['date_of_birth'],
                'weight_kg' => (float) $weightInput,
                'belt' => $belt?->value ?? '',
                'membership_number' => $membershipNumber !== '' ? $membershipNumber : null,
                'notes' => $combined['notes'] !== '' ? $combined['notes'] : null,
            ];
        }

        if ($rows === []) {
            throw new AthleteCsvImportException('club.area.csv.no_rows');
        }

        return $rows;
    }

    /** @return array{0: list<string>, 1: string} */
    private function parseHeader(string $line): array
    {
        foreach ([',', ';', "\t"] as $delimiter) {
            $fields = str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', '');
            $fields[0] = preg_replace('/^\xEF\xBB\xBF/', '', $fields[0]) ?? $fields[0];
            $headers = array_map(static fn(string $field): string => strtolower(trim($field)), $fields);

            if (
                count($headers) === count(self::HEADERS)
                && count(array_unique($headers)) === count(self::HEADERS)
                && array_diff(self::HEADERS, $headers) === []
                && array_diff($headers, self::HEADERS) === []
            ) {
                return [array_values($headers), $delimiter];
            }
        }

        throw new AthleteCsvImportException('club.area.csv.invalid_header');
    }

    /**
     * @param array<string, string> $row
     * @return list<string>
     */
    private function lengthErrors(array $row): array
    {
        $errors = [];
        if ($this->length($row['last_name']) > 120) {
            $errors[] = 'club.area.csv.last_name_too_long';
        }
        if ($this->length($row['first_name']) > 120) {
            $errors[] = 'club.area.csv.first_name_too_long';
        }
        if ($this->length($row['membership_number']) > 80) {
            $errors[] = 'club.area.csv.membership_too_long';
        }
        if ($this->length($row['notes']) > 65_535) {
            $errors[] = 'club.area.csv.notes_too_long';
        }

        return $errors;
    }

    /**
     * @param list<array{
     *     last_name: string,
     *     first_name: string,
     *     gender: string,
     *     date_of_birth: string,
     *     weight_kg: float,
     *     belt: string,
     *     membership_number: string|null,
     *     notes: string|null
     * }> $rows
     */
    private function persist(array $rows, int $clubId): AthleteCsvImportResult
    {
        $database = Database::connection();
        $ownsTransaction = !$database->inTransaction();
        if ($ownsTransaction) {
            $database->beginTransaction();
        }

        try {
            $lookup = $database->prepare(
                'SELECT id FROM athletes
                 WHERE club_id = ? AND membership_number = ?
                 ORDER BY id ASC LIMIT 1'
            );
            $insert = $database->prepare(
                'INSERT INTO athletes
                 (club_id, last_name, first_name, gender, date_of_birth, weight_kg, belt,
                  membership_number, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $update = $database->prepare(
                'UPDATE athletes
                 SET last_name = ?, first_name = ?, gender = ?, date_of_birth = ?, weight_kg = ?,
                     belt = ?, membership_number = ?, notes = ?
                 WHERE id = ? AND club_id = ?'
            );
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $athleteId = false;
                if ($row['membership_number'] !== null) {
                    $lookup->execute([$clubId, $row['membership_number']]);
                    $athleteId = $lookup->fetchColumn();
                }

                if ($athleteId !== false) {
                    $update->execute([
                        $row['last_name'],
                        $row['first_name'],
                        $row['gender'],
                        $row['date_of_birth'],
                        $row['weight_kg'],
                        $row['belt'],
                        $row['membership_number'],
                        $row['notes'],
                        (int) $athleteId,
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
                    $row['date_of_birth'],
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

            return new AthleteCsvImportResult($created, $updated);
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }
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

    /** @param list<string|null> $fields */
    private function isBlankRow(array $fields): bool
    {
        foreach ($fields as $field) {
            if ($field !== null && trim($field) !== '') {
                return false;
            }
        }

        return true;
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    private function restoreSpreadsheetValue(string $value): string
    {
        return preg_match("/^'[=+\\-@]/u", $value) === 1 ? substr($value, 1) : $value;
    }

    private function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.');
    }

    private function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }
}
