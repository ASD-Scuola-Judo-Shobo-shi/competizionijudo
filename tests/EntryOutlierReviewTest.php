<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use App\Presentation\EntryOutlierReview;
use App\Service\TournamentData;
use App\Validation\EntryDataInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntryOutlierReviewTest extends TestCase
{
    /** @return iterable<string, array{string, float|null, string, int}> */
    public static function reviewCases(): iterable
    {
        yield 'young advanced belt and unusual weight' => ['2020-01-01', 100, 'black', 2];
        yield 'missing weight' => ['2016-01-01', null, 'yellow', 1];
        yield 'low weight' => ['2016-01-01', 11.9, 'yellow', 1];
        yield 'lower boundary' => ['2016-01-01', 12, 'yellow', 0];
        yield 'upper boundary' => ['2016-01-01', 100, 'yellow', 0];
        yield 'high weight' => ['2016-01-01', 100.1, 'yellow', 1];
        yield 'adult beginner' => ['1990-01-01', 80, 'white', 0];
        yield 'unknown belt' => ['2012-01-01', 45, 'unknown', 1];
        yield 'invalid birth date' => ['', 40, 'white', 1];
        yield 'future birth year' => ['2030-01-01', 40, 'white', 1];
    }

    #[DataProvider('reviewCases')]
    public function testReviewFlagsUseTournamentYearAndExplainThresholds(
        string $birthDate,
        ?float $weight,
        string $belt,
        int $reasons
    ): void {
        Localization::setLocale('en');
        $review = new EntryOutlierReview(config('entry_review.weight_by_age'), config('entry_review.belt_minimum_ages'));
        $input = [['entry_id' => 401, 'birth_date' => $birthDate, 'weight_kg' => $weight, 'belt' => $belt]];
        $results = $review->find($input, '2026-12-01');
        self::assertCount($reasons > 0 ? 1 : 0, $results);
        self::assertCount($reasons, $results[0]['review_reasons'] ?? []);
        self::assertArrayNotHasKey('review_reasons', $input[0]);
    }

    public function testRulesCanBeAdjustedWithoutChangingAthletes(): void
    {
        $review = new EntryOutlierReview([['max_age' => 120, 'min' => 10.0, 'max' => 500.0]], []);
        self::assertSame([], $review->find([
            ['birth_date' => '2020-01-01', 'weight_kg' => 100, 'belt' => 'black'],
        ], '2026-06-01'));
    }

    public function testRomeCutoffAccountsForWinterSummerAndTheEntireEventDate(): void
    {
        self::assertSame('2026-01-01 23:00:00', TournamentData::cutoff('2026-01-01'));
        self::assertSame('2026-07-01 22:00:00', TournamentData::cutoff('2026-07-01'));
        self::assertSame('2026-03-29 22:00:00', TournamentData::cutoff('2026-03-29'));
    }

    public function testEntryCorrectionsAllowUnknownWeightButValidateEnumsDatesAndDatabaseLimits(): void
    {
        $data = [
            'last_name' => 'Synthetic', 'first_name' => 'Athlete', 'gender' => 'F',
            'birth_date' => '2012-01-01', 'weight_kg' => '', 'belt' => 'white', 'membership_number' => '',
        ];
        self::assertSame([], EntryDataInput::errors($data));
        $data['last_name'] = str_repeat('x', 121);
        $data['weight_kg'] = '10000';
        self::assertContains('entry_review.errors.invalid', EntryDataInput::errors($data));
        $data['gender'] = 'unknown';
        self::assertContains('validation.athlete_gender_invalid', EntryDataInput::errors($data));
    }
}
