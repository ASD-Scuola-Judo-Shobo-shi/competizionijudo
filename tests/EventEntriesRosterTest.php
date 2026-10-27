<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use App\Presentation\EventEntriesRoster;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventEntriesRosterTest extends TestCase
{
    private string $originalLocale;

    protected function setUp(): void
    {
        $this->originalLocale = Localization::getLocale();
        Localization::setLocale('en');
    }

    protected function tearDown(): void
    {
        Localization::setLocale($this->originalLocale);
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function rosterLanguages(): iterable
    {
        yield 'English' => ['en', 'Children A 4-5 yr', '♀ Female', 'White / Yellow'];
        yield 'Italian' => ['it', 'Bambini A 4-5 anni', '♀ Femmina', 'Bianca / Gialla'];
    }

    #[DataProvider('rosterLanguages')]
    public function testNormalizationUsesTheEventYearAndCurrentLanguageWithoutChangingSavedValues(
        string $locale,
        string $ageCategory,
        string $genderLabel,
        string $beltLabel
    ): void {
        Localization::setLocale($locale);
        $row = [
            'entry_id' => 401,
            'club_id' => '201',
            'last_name' => 'Synthetic',
            'first_name' => 'Athlete',
            'birth_date' => '2021-01-01',
            'event_date' => '2026-08-02',
            'gender' => 'F',
            'weight_kg' => '21.50',
            'weight_category' => ' -24 kg ',
            'belt' => 'white_yellow',
            'membership_number' => 'SYN-401',
            'uses_snapshot' => 1,
        ];

        $entry = EventEntriesRoster::normalizeRows([$row])[0];

        self::assertSame(201, $entry['club_id']);
        self::assertSame('Synthetic Athlete', $entry['athlete_name']);
        self::assertSame($ageCategory, $entry['age_category']);
        self::assertSame($genderLabel, $entry['gender_label']);
        self::assertSame($beltLabel, $entry['belt_label']);
        self::assertSame('21.5 kg', $entry['weight_display']);
        self::assertSame('-24 kg', $entry['weight_label']);
        foreach (['entry_id', 'birth_date', 'event_date', 'membership_number', 'uses_snapshot'] as $field) {
            self::assertSame($row[$field], $entry[$field], $field);
        }
        self::assertSame('21.50', $row['weight_kg']);
    }

    public function testMissingSavedValuesAndUnknownLabelsRemainVisibleWithoutInventingData(): void
    {
        $entries = EventEntriesRoster::normalizeRows([
            ['birth_date' => null, 'weight_kg' => null, 'weight_category' => null, 'belt' => null],
            ['weight_kg' => 'unknown', 'gender' => 'X', 'belt' => 'Legacy belt'],
        ]);

        foreach ($entries as $entry) {
            self::assertNull($entry['weight_kg']);
            self::assertSame('—', $entry['weight_display']);
            self::assertSame('', $entry['weight_category']);
            self::assertSame(__('events.no_weight'), $entry['weight_label']);
            self::assertSame(__('events.no_category'), $entry['age_category']);
        }
        self::assertSame('', $entries[0]['belt_label']);
        self::assertSame('X', $entries[1]['gender_label']);
        self::assertSame('Legacy belt', $entries[1]['belt_label']);
    }

    /** @return iterable<string, array{string, string, list<int>}> */
    public static function rosterSorts(): iterable
    {
        yield 'athlete names and entry IDs' => ['athlete', 'asc', [2, 4, 1, 3]];
        yield 'descending names and IDs' => ['athlete', 'desc', [3, 1, 4, 2]];
        yield 'exact weights' => ['weight', 'asc', [1, 3, 2, 4]];
        yield 'descending weights' => ['weight', 'desc', [4, 2, 3, 1]];
        yield 'weight categories' => ['weight_category', 'asc', [1, 3, 2, 4]];
        yield 'age class then weight category' => ['age_class', 'asc', [3, 2, 4, 1]];
        yield 'club then athlete' => ['club', 'asc', [2, 4, 3, 1]];
        yield 'gender then athlete' => ['gender', 'asc', [2, 4, 1, 3]];
        yield 'belts with unknown belts last' => ['belt', 'asc', [2, 4, 1, 3]];
        yield 'unknown sort defaults to athlete' => ['unknown', 'asc', [2, 4, 1, 3]];
    }

    /** @param list<int> $expectedIds */
    #[DataProvider('rosterSorts')]
    public function testRosterSortsKeepDeterministicOrdering(string $sort, string $direction, array $expectedIds): void
    {
        $entries = EventEntriesRoster::normalizeRows([
            ['entry_id' => 1, 'last_name' => 'Bravo', 'club_name' => 'Zulu', 'birth_date' => '2012-01-01',
                'event_date' => '2026-08-02', 'weight_kg' => 39.5, 'weight_category' => '-40 kg', 'gender' => 'M',
                'belt' => 'blue'],
            ['entry_id' => 4, 'last_name' => 'alpha', 'club_name' => 'Alpha', 'birth_date' => '2014-01-01',
                'event_date' => '2026-08-02', 'weight_kg' => 45.5, 'weight_category' => '-48 kg', 'gender' => 'F',
                'belt' => 'white'],
            ['entry_id' => 3, 'last_name' => 'Charlie', 'club_name' => 'Alpha', 'birth_date' => '2014-01-01',
                'event_date' => '2026-08-02', 'weight_kg' => 41.0, 'weight_category' => '-44 kg', 'gender' => 'M',
                'belt' => 'unknown'],
            ['entry_id' => 2, 'last_name' => 'Alpha', 'club_name' => 'Alpha', 'birth_date' => '2014-01-01',
                'event_date' => '2026-08-02', 'weight_kg' => 45.5, 'weight_category' => '-48 kg', 'gender' => 'F',
                'belt' => 'white'],
        ]);

        self::assertSame($expectedIds, array_column(
            EventEntriesRoster::sortedEntries($entries, $sort, $direction),
            'entry_id'
        ));
        self::assertSame([1, 4, 3, 2], array_column($entries, 'entry_id'));
    }

    public function testClubRostersPreserveDistinctClubsWithIdenticalNames(): void
    {
        $entries = EventEntriesRoster::normalizeRows([
            ['club_id' => 203, 'club_name' => 'Zulu', 'federal_code' => 'SYN-203', 'last_name' => 'Charlie'],
            ['club_id' => 201, 'club_name' => 'Alpha', 'federal_code' => 'SYN-201', 'last_name' => 'Delta'],
            ['club_id' => 202, 'club_name' => 'Alpha', 'federal_code' => 'SYN-202', 'last_name' => 'Bravo'],
            ['club_id' => 201, 'club_name' => 'Alpha', 'federal_code' => 'SYN-201', 'last_name' => 'Alpha'],
        ]);

        $rosters = EventEntriesRoster::clubRosters($entries);

        self::assertSame(['SYN-201', 'SYN-202', 'SYN-203'], array_column($rosters, 'federal_code'));
        self::assertSame(['Alpha', 'Delta'], array_column($rosters[0]['athletes'], 'athlete_name'));
        self::assertSame(['Bravo'], array_column($rosters[1]['athletes'], 'athlete_name'));
        self::assertSame(['Charlie'], array_column($rosters[2]['athletes'], 'athlete_name'));
    }

    public function testWeightCategoriesAreUniqueTrimmedAndSortedNumericallyIncludingDecimalWeights(): void
    {
        $entries = array_map(static fn(string $category): array => ['weight_category' => $category], [
            'Open', '+100 kg', '-28 kg', ' -24 kg ', '-24 kg', '-24,5 kg', '-24.2 kg', '', 'Absolute',
        ]);

        self::assertSame(
            ['-24 kg', '-24.2 kg', '-24,5 kg', '-28 kg', '+100 kg', 'Absolute', 'Open'],
            EventEntriesRoster::weightCategories($entries)
        );
    }

    public function testEmptyRostersNeedNoSpecialHandling(): void
    {
        self::assertSame([], EventEntriesRoster::normalizeRows([]));
        self::assertSame([], EventEntriesRoster::sortedEntries([], 'athlete', 'asc'));
        self::assertSame([], EventEntriesRoster::clubRosters([]));
        self::assertSame([], EventEntriesRoster::weightCategories([]));
    }
}
