<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Entry;
use RuntimeException;

final class EventEntriesCsvTransfer
{
    /** @var list<string> */
    private const HEADERS = [
        'club_name',
        'federal_code',
        'last_name',
        'first_name',
        'gender',
        'birth_date',
        'weight_kg',
        'belt',
        'membership_number',
        'type',
        'weight_category',
    ];

    public function export(int $eventId, bool $eventClosed): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the event entries CSV export stream.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            $this->writeRow($stream, self::HEADERS);

            foreach (Entry::findByEvent($eventId, null, $eventClosed) as $entry) {
                $this->writeRow($stream, [
                    $this->spreadsheetSafe((string) ($entry['club_name'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['federal_code'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['last_name'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['first_name'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['gender'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['birth_date'] ?? '')),
                    $this->formatWeight($entry['weight_kg'] ?? ''),
                    $this->spreadsheetSafe((string) ($entry['belt'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['membership_number'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['type'] ?? '')),
                    $this->spreadsheetSafe((string) ($entry['weight_category'] ?? '')),
                ]);
            }

            rewind($stream);
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                throw new RuntimeException('Unable to read the event entries CSV export stream.');
            }

            return $contents;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     * @param list<string> $fields
     */
    private function writeRow($stream, array $fields): void
    {
        if (fputcsv($stream, $fields, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('Unable to write the event entries CSV export.');
        }
    }

    private function formatWeight(mixed $weight): string
    {
        return is_numeric($weight) ? rtrim(rtrim(sprintf('%.2F', (float) $weight), '0'), '.') : '';
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }
}
