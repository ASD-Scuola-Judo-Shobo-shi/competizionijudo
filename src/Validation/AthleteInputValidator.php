<?php

declare(strict_types=1);

namespace App\Validation;

use App\Model\Belt;
use App\Model\Gender;
use DateTimeImmutable;
use DateTimeZone;

final class AthleteInputValidator
{
    private function __construct()
    {
    }

    /** @return list<string> Translation keys for invalid fields. */
    public static function errors(
        string $lastName,
        string $firstName,
        string $gender,
        string $dateOfBirth,
        string $weight,
        string $belt,
        ?DateTimeImmutable $today = null
    ): array {
        $errors = [];
        $today ??= new DateTimeImmutable('today', new DateTimeZone('UTC'));

        if (trim($lastName) === '') {
            $errors[] = 'validation.athlete_last_name_required';
        }
        if (trim($firstName) === '') {
            $errors[] = 'validation.athlete_first_name_required';
        }
        if (Gender::tryFrom(trim($gender)) === null) {
            $errors[] = 'validation.athlete_gender_invalid';
        }
        $birthDate = self::date($dateOfBirth);
        if ($birthDate === null || $birthDate > $today) {
            $errors[] = 'validation.athlete_birth_date_invalid';
        }

        $normalizedWeight = str_replace(',', '.', trim($weight));
        if (!is_numeric($normalizedWeight) || (float) $normalizedWeight <= 0) {
            $errors[] = 'validation.athlete_weight_invalid';
        }
        if (Belt::tryFrom(trim($belt)) === null) {
            $errors[] = 'validation.athlete_belt_invalid';
        }

        return $errors;
    }

    private static function date(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            trim($value),
            new DateTimeZone('UTC')
        );
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format('Y-m-d') === trim($value) ? $date : null;
    }
}
