<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Model\Athlete;
use App\Model\Belt;
use App\Model\JudoCategory;

final class EntryOutlierReview
{
    /**
     * @param list<array{max_age:int, min:float, max:float}> $weightRules
     * @param array<string, int> $beltMinimumAges
     */
    public function __construct(private readonly array $weightRules, private readonly array $beltMinimumAges)
    {
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public function find(array $entries, string $eventDate): array
    {
        $results = [];
        $eventYear = Athlete::eventYearFromDate($eventDate);
        foreach ($entries as $entry) {
            $birthYear = JudoCategory::extractBirthYear((string) ($entry['birth_date'] ?? ''));
            $age = $birthYear === null ? null : $eventYear - $birthYear;
            $reasons = [];
            if ($age === null || $age < 0 || $age > 120) {
                $reasons[] = __('entry_review.outliers.invalid_age');
            } else {
                foreach ($this->weightRules as $rule) {
                    if ($age > $rule['max_age']) {
                        continue;
                    }
                    if (
                        !is_numeric($entry['weight_kg']) || (float) $entry['weight_kg'] < $rule['min']
                        || (float) $entry['weight_kg'] > $rule['max']
                    ) {
                        $reasons[] = __('entry_review.outliers.weight', [
                            'age' => (string) $age, 'min' => (string) $rule['min'], 'max' => (string) $rule['max'],
                        ]);
                    }
                    break;
                }
                $belt = Belt::tryFromValue($entry['belt'] ?? null);
                if ($belt === null) {
                    $reasons[] = __('entry_review.outliers.invalid_belt');
                } elseif ($age < ($this->beltMinimumAges[$belt->value] ?? 0)) {
                    $reasons[] = __('entry_review.outliers.belt', [
                        'age' => (string) $age, 'belt' => $belt->label(),
                        'min' => (string) $this->beltMinimumAges[$belt->value],
                    ]);
                }
            }
            if ($reasons !== []) {
                $entry['review_reasons'] = $reasons;
                $results[] = $entry;
            }
        }

        return $results;
    }
}
