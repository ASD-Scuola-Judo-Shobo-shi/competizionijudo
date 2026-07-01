<?php

declare(strict_types=1);

namespace App\Validation;

final class ClubInputValidator
{
    private function __construct()
    {
    }

    /** @return list<string> Translation keys for invalid registration fields. */
    public static function registrationErrors(
        string $name,
        string $federalCode,
        string $email,
        bool $athleteDataRightsDeclared
    ): array {
        $errors = self::identityErrors($name, $federalCode, $email);
        if (!$athleteDataRightsDeclared) {
            $errors[] = 'validation.club_athlete_data_rights_required';
        }

        return $errors;
    }

    /** @return list<string> Translation keys for invalid fields. */
    public static function errors(
        string $name,
        string $federalCode,
        string $email,
        string $contactEmail,
        string $recoveryEmail
    ): array {
        $errors = self::identityErrors($name, $federalCode, $email);
        if (trim($contactEmail) !== '' && !self::validEmail($contactEmail)) {
            $errors[] = 'validation.contact_email_invalid';
        }
        if (!self::validEmail($recoveryEmail)) {
            $errors[] = 'validation.recovery_email_invalid';
        }

        return $errors;
    }

    /** @return list<string> */
    private static function identityErrors(string $name, string $federalCode, string $email): array
    {
        $errors = [];

        if (trim($name) === '') {
            $errors[] = 'validation.club_name_required';
        }
        if (trim($federalCode) === '') {
            $errors[] = 'validation.federal_code_required';
        }
        if (!self::validEmail($email)) {
            $errors[] = 'validation.club_email_invalid';
        }

        return $errors;
    }

    private static function validEmail(string $email): bool
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }
}
