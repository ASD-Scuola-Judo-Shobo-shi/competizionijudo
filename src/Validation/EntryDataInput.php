<?php

declare(strict_types=1);

namespace App\Validation;

final class EntryDataInput
{
    /**
     * @param array<string, string> $data
     * @return list<string>
     */
    public static function errors(array $data): array
    {
        $errors = AthleteInputValidator::errors(
            $data['last_name'],
            $data['first_name'],
            $data['gender'],
            $data['birth_date'],
            $data['weight_kg'],
            $data['belt']
        );
        if ($data['weight_kg'] === '') {
            $errors = array_values(array_diff($errors, ['validation.athlete_weight_invalid']));
        }
        if (
            mb_strlen($data['last_name']) > 120 || mb_strlen($data['first_name']) > 120
            || mb_strlen($data['membership_number']) > 80 || (float) $data['weight_kg'] > 9999.99
        ) {
            $errors[] = 'entry_review.errors.invalid';
        }

        return $errors;
    }
}
