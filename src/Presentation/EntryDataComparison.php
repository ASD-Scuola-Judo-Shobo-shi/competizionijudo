<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Service\EventEntriesCsvTransfer;
use App\Service\TournamentData;

final class EntryDataComparison
{
    /**
     * @param list<array{entry_id:int, athlete:string, club:string, source:string,
     *     websiteMissing:bool, exportMissing:bool, profileMissing:bool,
     *     fields:list<array{field:string, website:string, export:string, profile:string}>}> $differences
     */
    private function __construct(
        public readonly array $differences,
        public readonly int $total,
        public readonly int $exportDifferenceCount,
        public readonly int $profileDifferenceCount,
        public readonly int $alignableCount
    ) {
    }

    /**
     * @param list<array<string, mixed>> $websiteRows
     * @param list<array<string, mixed>> $exportRows
     * @param list<array<string, mixed>> $profileRows
     */
    public static function fromRows(
        array $websiteRows,
        array $exportRows,
        array $profileRows,
        string $eventDate,
        bool $eventClosed
    ): self {
        $websiteRows = self::byEntry($websiteRows);
        $exportRows = self::byEntry($exportRows);
        $profileRows = self::byEntry($profileRows);
        $allRows = $websiteRows + $exportRows + $profileRows;
        $ended = TournamentData::now() >= TournamentData::cutoff($eventDate);
        $differences = [];
        $exportCount = 0;
        $profileCount = 0;
        $alignableCount = 0;
        foreach ($allRows as $entryId => $row) {
            $website = self::values($websiteRows[$entryId] ?? []);
            $export = self::values($exportRows[$entryId] ?? []);
            $profile = isset($profileRows[$entryId])
                ? self::values(array_merge($profileRows[$entryId], TournamentData::categorize(
                    $profileRows[$entryId],
                    $eventDate
                ))) : self::values([]);
            $websiteMissing = !isset($websiteRows[$entryId]);
            $exportMissing = !isset($exportRows[$entryId]);
            $profileMissing = !isset($profileRows[$entryId]);
            $exportDiffers = $websiteMissing !== $exportMissing || $website !== $export;
            $profileDiffers = $websiteMissing !== $profileMissing || $website !== $profile;
            $exportCount += (int) $exportDiffers;
            $profileCount += (int) $profileDiffers;
            $frozen = $eventClosed || !empty($websiteRows[$entryId]['uses_snapshot']);
            $alignableCount += (int) (!$ended && $frozen && !$profileMissing && $profileDiffers);
            if (!$exportDiffers && !$profileDiffers) {
                continue;
            }
            $fields = [];
            foreach (EventEntriesCsvTransfer::headers() as $field) {
                if ($website[$field] !== $export[$field] || $website[$field] !== $profile[$field]) {
                    $fields[] = [
                        'field' => $field, 'website' => $website[$field],
                        'export' => $export[$field], 'profile' => $profile[$field],
                    ];
                }
            }
            $differences[] = [
                'entry_id' => $entryId,
                'athlete' => trim((string) ($row['last_name'] ?? '') . ' ' . (string) ($row['first_name'] ?? '')),
                'club' => (string) ($row['club_name'] ?? ''),
                'source' => $frozen ? 'snapshot' : ($ended ? 'revision' : 'profile'),
                'websiteMissing' => $websiteMissing, 'exportMissing' => $exportMissing,
                'profileMissing' => $profileMissing, 'fields' => $fields,
            ];
        }

        return new self($differences, count($allRows), $exportCount, $profileCount, $alignableCount);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function byEntry(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['entry_id']] = $row;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private static function values(array $row): array
    {
        $values = array_merge($row, TournamentData::normalize($row));
        $result = [];
        foreach (EventEntriesCsvTransfer::headers() as $field) {
            $result[$field] = (string) ($values[$field] ?? '');
        }

        return $result;
    }
}
