<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use App\Presentation\EventEntriesViewModel;
use PHPUnit\Framework\TestCase;

final class EventEntriesViewModelTest extends TestCase
{
    public function testItBuildsTheReportAndFiltersTheCurrentClubInOnePass(): void
    {
        Localization::setLocale('it');
        $clubs = [
            ['id' => 201, 'club_name' => 'Club Uno', 'federal_code' => 'ONE'],
            ['id' => 202, 'club_name' => 'Club Due', 'federal_code' => 'TWO'],
        ];
        $rows = [
            $this->entry(201, 'Rossi', 'Anna', 'F', 21.5, '-24 kg', 'white_yellow'),
            $this->entry(201, 'Bianchi', 'Luca', 'M', 25.0, '-28 kg', 'green_blue'),
            $this->entry(202, 'Verdi', 'Giulia', 'F', 23.0, '-24 kg', 'white_yellow'),
        ];

        $report = EventEntriesViewModel::fromRows($rows, $clubs, 201, '-24 kg');

        self::assertCount(3, $report->entries);
        self::assertSame([201 => 2, 202 => 1], $report->clubAthleteCounts);
        self::assertSame(['-24 kg', '-28 kg'], $report->currentClubWeightCategories);
        self::assertSame('-24 kg', $report->selectedWeightCategory);
        self::assertCount(1, $report->currentClubEntries);
        self::assertSame('Rossi Anna', $report->currentClubEntries[0]['athlete_name']);
        self::assertSame('21.5 kg', $report->currentClubEntries[0]['weight_display']);
        self::assertSame('Bianca / Gialla', $report->currentClubEntries[0]['belt_label']);
        self::assertSame(['category', 'weight', 'belt', 'gender'], array_column($report->dimensions, 'key'));
        self::assertSame(2, $report->dimensions[0]['clubCounts'][201]['Bambini A 4-5 anni']);
        self::assertCount(2, $report->athleteGroups);
        self::assertCount(1, $report->categoryWeightBars);
        self::assertSame(3, $report->categoryWeightBars[0]['total']);
        self::assertSame(
            ['-24 kg', '-28 kg'],
            array_column($report->categoryWeightBars[0]['segments'], 'label')
        );
        self::assertSame([2, 1], array_column($report->categoryWeightBars[0]['segments'], 'count'));
        self::assertEqualsWithDelta(
            [66.6667, 33.3333],
            array_column($report->categoryWeightBars[0]['segments'], 'percentage'),
            0.0001
        );
    }

    public function testItBuildsOneStackedWeightBarForEachAgeCategory(): void
    {
        Localization::setLocale('it');
        $olderAthlete = $this->entry(202, 'Verdi', 'Giulia', 'F', 28.0, '-32 kg', 'yellow');
        $olderAthlete['birth_date'] = '2018-01-01';

        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'Rossi', 'Anna', 'F', 21.5, '-24 kg', 'white'),
            $this->entry(201, 'Bianchi', 'Luca', 'M', 25.0, '-28 kg', 'yellow'),
            $olderAthlete,
        ], [], null);

        self::assertSame(
            ['Bambini A 4-5 anni', 'Fanciulli 8-9 anni'],
            array_column($report->categoryWeightBars, 'category')
        );
        self::assertSame([2, 1], array_column($report->categoryWeightBars, 'total'));
        self::assertCount(2, $report->categoryWeightBars[0]['segments']);
        self::assertCount(1, $report->categoryWeightBars[1]['segments']);
        self::assertSame(
            [50.0, 50.0],
            array_column($report->categoryWeightBars[0]['segments'], 'percentage')
        );
        self::assertSame(
            [50.0],
            array_column($report->categoryWeightBars[1]['segments'], 'percentage')
        );
    }

    public function testItIgnoresUnknownFiltersAndSortsWeightCategoriesNumerically(): void
    {
        $entries = [
            ['weight_category' => 'Open'],
            ['weight_category' => '+100 kg'],
            ['weight_category' => '-28 kg'],
            ['weight_category' => '-24 kg'],
            ['weight_category' => ''],
        ];

        self::assertSame(
            ['-24 kg', '-28 kg', '+100 kg', 'Open'],
            EventEntriesViewModel::weightCategories($entries)
        );

        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'Rossi', 'Anna', 'F', 21.5, '-24 kg', 'white'),
            $this->entry(201, 'Bianchi', 'Luca', 'M', 25.0, '-28 kg', 'yellow'),
        ], [], 201, 'unknown');

        self::assertSame('', $report->selectedWeightCategory);
        self::assertCount(2, $report->currentClubEntries);
    }

    public function testAthletePagesRetainGroupHeadingsAcrossPageBoundaries(): void
    {
        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'First', 'Athlete', 'F', 21.0, '-24 kg', 'white'),
            $this->entry(201, 'Second', 'Athlete', 'M', 22.0, '-24 kg', 'yellow'),
            $this->entry(201, 'Third', 'Athlete', 'F', 25.0, '-28 kg', 'green'),
        ], [], null);

        $page = $report->athleteGroupsPage(1, 2);

        self::assertSame(['-24 kg', '-28 kg'], array_column($page, 'weight'));
        self::assertSame('Second Athlete', $page[0]['athletes'][0]['athlete_name']);
        self::assertSame('Third Athlete', $page[1]['athletes'][0]['athlete_name']);
    }

    /** @return array<string, mixed> */
    private function entry(
        int $clubId,
        string $lastName,
        string $firstName,
        string $gender,
        float $weight,
        string $weightCategory,
        string $belt
    ): array {
        return [
            'club_id' => $clubId,
            'club_name' => 'Club ' . $clubId,
            'federal_code' => 'CODE-' . $clubId,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'gender' => $gender,
            'birth_date' => '2021-01-01',
            'event_date' => '2026-08-02',
            'weight_kg' => $weight,
            'weight_category' => $weightCategory,
            'belt' => $belt,
        ];
    }
}
