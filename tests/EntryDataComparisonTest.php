<?php

declare(strict_types=1);

namespace Tests;

use App\Presentation\EntryDataComparison;
use App\Service\TournamentData;
use PHPUnit\Framework\TestCase;

final class EntryDataComparisonTest extends TestCase
{
    public function testFutureFrozenEntryExplainsProfileDifferencesWithoutClaimingAnExportMismatch(): void
    {
        $profile = $this->profile();
        $frozen = array_merge($profile, TournamentData::categorize($profile, '2099-06-01'), [
            'last_name' => 'Frozen', 'weight_kg' => 40, 'weight_category' => '-60 kg', 'uses_snapshot' => 1,
        ]);

        $report = EntryDataComparison::fromRows([$frozen], [$frozen], [$profile], '2099-06-01', true);

        self::assertSame(1, $report->total);
        self::assertSame(0, $report->exportDifferenceCount);
        self::assertSame(1, $report->profileDifferenceCount);
        self::assertSame(1, $report->alignableCount);
        self::assertSame('snapshot', $report->differences[0]['source']);
        self::assertSame(['last_name', 'weight_kg'], array_column($report->differences[0]['fields'], 'field'));
        self::assertSame(['40.00', '40.00', '45.50'], array_values(array_slice(
            $report->differences[0]['fields'][1],
            1
        )));
    }

    public function testComparisonMatchesEntryIdsAndIgnoresNumericAndBlankFormatting(): void
    {
        $profile = $this->profile();
        $first = array_merge($profile, TournamentData::categorize($profile, '2099-06-01'));
        $second = array_merge($first, ['entry_id' => 402, 'last_name' => 'Second']);
        $csvFirst = array_merge($first, ['weight_kg' => '45.500', 'membership_number' => null]);

        $report = EntryDataComparison::fromRows(
            [$first, $second],
            [$second, $csvFirst],
            [$second, $profile],
            '2099-06-01',
            false
        );

        self::assertSame(2, $report->total);
        self::assertSame([], $report->differences);
        self::assertSame(0, $report->alignableCount);
    }

    public function testComparisonIncludesMismatchedValuesAndMissingEntriesFromEverySource(): void
    {
        $first = $this->profile();
        $second = array_merge($first, ['entry_id' => 402]);
        $third = array_merge($first, ['entry_id' => 403]);
        $fourth = array_merge($first, ['entry_id' => 404]);
        $report = EntryDataComparison::fromRows(
            [$first, $second],
            [array_merge($first, ['belt' => 'blue']), $third],
            [$first, $second, $fourth],
            '2099-06-01',
            false
        );

        self::assertSame(4, $report->total);
        self::assertSame(3, $report->exportDifferenceCount);
        self::assertSame(3, $report->profileDifferenceCount);
        self::assertFalse($report->differences[0]['websiteMissing']);
        self::assertTrue($report->differences[1]['exportMissing']);
        self::assertTrue($report->differences[2]['profileMissing']);
        self::assertTrue($report->differences[3]['websiteMissing']);
        self::assertSame('profile', $report->differences[0]['source']);
    }

    public function testEndedOpenEntriesUseRevisionSourceAndCannotAlign(): void
    {
        $profile = $this->profile();
        $record = array_merge($profile, ['last_name' => 'Eligible revision']);

        $report = EntryDataComparison::fromRows([$record], [$record], [$profile], '2020-06-01', false);

        self::assertSame('revision', $report->differences[0]['source']);
        self::assertSame(0, $report->alignableCount);
    }

    public function testApprovedEntryInReopenedCompetitionRemainsAlignable(): void
    {
        $profile = $this->profile();
        $record = array_merge($profile, ['last_name' => 'Approved', 'uses_snapshot' => 1]);

        $report = EntryDataComparison::fromRows([$record], [$record], [$profile], '2099-06-01', false);

        self::assertSame('snapshot', $report->differences[0]['source']);
        self::assertSame(1, $report->alignableCount);
    }

    /** @return array<string, mixed> */
    private function profile(): array
    {
        return [
            'entry_id' => 401, 'club_id' => 201, 'club_name' => 'Synthetic Club', 'federal_code' => 'SYN-201',
            'last_name' => 'Current', 'first_name' => 'Athlete', 'gender' => 'M', 'birth_date' => '2012-01-01',
            'weight_kg' => 45.5, 'belt' => 'green', 'membership_number' => '',
        ];
    }
}
