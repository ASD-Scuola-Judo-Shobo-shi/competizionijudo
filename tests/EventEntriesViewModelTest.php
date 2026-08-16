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

    public function testTableSortsAreAppliedBeforeEntryPagesAreSliced(): void
    {
        $clubs = [
            ['id' => 201, 'club_name' => 'Club Uno', 'federal_code' => 'ONE'],
            ['id' => 202, 'club_name' => 'Club Due', 'federal_code' => 'TWO'],
        ];
        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'Alpha', 'Athlete', 'F', 21.0, '-24 kg', 'white'),
            $this->entry(201, 'Charlie', 'Athlete', 'M', 26.0, '-28 kg', 'yellow'),
            $this->entry(202, 'Bravo', 'Athlete', 'F', 23.0, '-24 kg', 'green'),
        ], $clubs, 201);

        $page = $report->athleteGroupsSortedPage('athlete', 'desc', 1, 1);

        self::assertSame('Bravo Athlete', $page[0]['athletes'][0]['athlete_name']);
        self::assertSame(
            [201, 202],
            array_map(
                static fn(array $club): int => (int) $club['id'],
                $report->sortedClubs('athletes', 'desc')
            )
        );
        self::assertSame(
            ['Charlie Athlete', 'Alpha Athlete'],
            array_column($report->sortedCurrentClubEntries('weight', 'desc'), 'athlete_name')
        );
    }

    public function testOpponentGroupsMatchEveryCurrentClubAgeAndWeightCombination(): void
    {
        Localization::setLocale('it');
        $olderOwnAthlete = $this->entry(201, 'Delta', 'Atleta', 'F', 35.0, '-36 kg', 'blue');
        $olderOwnAthlete['birth_date'] = '2018-01-01';
        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'Alpha', 'Atleta', 'F', 21.5, '-24 kg', 'white'),
            $this->entry(201, 'Bravo', 'Atleta', 'M', 25.0, '-28 kg', 'yellow'),
            $olderOwnAthlete,
            $this->entry(202, 'MatchOne', 'Atleta', 'F', 22.0, '-24kg', 'green'),
            $this->entry(203, 'MatchTwo', 'Atleta', 'M', 22.5, '-24 kg', 'blue'),
            $this->entry(202, 'SecondGroup', 'Atleta', 'M', 26.0, '-28 kg', 'orange'),
            $this->entry(202, 'Unrelated', 'Atleta', 'F', 30.0, '-32 kg', 'brown'),
        ], [], 201, '-24 kg');

        $groups = $report->opponentGroups();

        self::assertSame(['-24 kg', '-28 kg', '-36 kg'], array_column($groups, 'weightCategory'));
        self::assertSame([3, 2, 1], array_map(
            static fn(array $group): int => count($group['athletes']),
            $groups
        ));
        self::assertSame(
            ['Alpha Atleta', 'MatchOne Atleta', 'MatchTwo Atleta'],
            array_column($groups[0]['athletes'], 'athlete_name')
        );
        self::assertSame(
            [true, false, false],
            array_column($groups[0]['athletes'], 'is_current_club')
        );
        self::assertSame(['Delta Atleta'], array_column($groups[2]['athletes'], 'athlete_name'));
        self::assertSame(
            ['-24 kg', '-28 kg', '-36 kg'],
            array_column($report->opponentGroups(['age_class' => 'anni']), 'weightCategory')
        );
        self::assertSame(
            ['-28 kg'],
            array_column($report->opponentGroups(['weight_category' => '-28']), 'weightCategory')
        );
        self::assertSame(
            ['-24 kg'],
            array_column($report->opponentGroups(['athlete' => 'matchone']), 'weightCategory')
        );
        self::assertSame(
            ['-24 kg', '-28 kg'],
            array_column($report->opponentGroups(['club' => 'club 202']), 'weightCategory')
        );
        self::assertSame(
            ['-24 kg', '-36 kg'],
            array_column($report->opponentGroups(['gender' => 'femmina']), 'weightCategory')
        );
        self::assertSame(
            ['-24 kg'],
            array_column($report->opponentGroups(['belt' => 'verde']), 'weightCategory')
        );
        self::assertSame(
            ['-24 kg', '-28 kg', '-36 kg'],
            array_column($report->opponentGroups(['club' => 'club 201']), 'weightCategory')
        );
        self::assertSame([], $report->opponentGroups(['athlete' => 'missing']));
        self::assertCount(1, $report->currentClubEntries);
    }

    public function testEveryVisibleEntryColumnCanBeFilteredCaseInsensitively(): void
    {
        Localization::setLocale('it');
        $clubs = [
            ['id' => 201, 'club_name' => 'Club Uno', 'federal_code' => 'ONE'],
            ['id' => 202, 'club_name' => 'Club Due', 'federal_code' => 'TWO'],
        ];
        $olderAthlete = $this->entry(202, 'Charlie', 'Atleta', 'M', 31.0, '-32 kg', 'yellow');
        $olderAthlete['birth_date'] = '2018-01-01';
        $report = EventEntriesViewModel::fromRows([
            $this->entry(201, 'Alpha', 'Atleta', 'F', 21.5, '-24 kg', 'white'),
            $this->entry(201, 'Bravo', 'Atleta', 'M', 25.0, '-28 kg', 'green'),
            $olderAthlete,
        ], $clubs, 201);

        $sharedFilters = [
            'age_class' => ['fAnCiUlLi', 'Charlie Atleta'],
            'weight_category' => ['-28 KG', 'Bravo Atleta'],
            'athlete' => ['cHaRlIe', 'Charlie Atleta'],
            'club' => ['club 202', 'Charlie Atleta'],
            'gender' => ['fEmMiNa', 'Alpha Atleta'],
            'belt' => ['vErDe', 'Bravo Atleta'],
        ];
        foreach ($sharedFilters as $field => [$filter, $athleteName]) {
            self::assertSame(
                [$athleteName],
                array_column($report->filteredEntries([$field => $filter]), 'athlete_name'),
                $field
            );
        }

        $currentClubFilters = [
            'athlete' => 'alpha',
            'gender' => 'femmina',
            'weight' => '21.5',
            'weight_category' => '-24 kg',
            'belt' => 'bianca',
        ];
        foreach ($currentClubFilters as $field => $filter) {
            self::assertSame(
                ['Alpha Atleta'],
                array_column(
                    $report->filteredCurrentClubEntries([$field => $filter]),
                    'athlete_name'
                ),
                $field
            );
        }

        $clubFilters = [
            'club' => ['uno', 201],
            'federal_code' => ['two', 202],
            'athletes' => ['2', 201],
            'breakdown' => ['verde: 1', 201],
        ];
        foreach ($clubFilters as $field => [$filter, $clubId]) {
            self::assertSame(
                [$clubId],
                array_column($report->filteredClubs([$field => $filter]), 'id'),
                $field
            );
        }

        self::assertSame([], $report->filteredEntries([
            'athlete' => 'Alpha',
            'gender' => 'Maschio',
        ]));
        self::assertCount(3, $report->filteredEntries(['not_a_column' => 'ignored']));
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
