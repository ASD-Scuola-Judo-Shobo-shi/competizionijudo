<?php

declare(strict_types=1);

namespace App\Validation;

use App\Model\SardinianLocation;

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
        string $phone,
        string $addressLine,
        string $postalCode,
        string $province,
        string $city,
        bool $athleteDataRightsDeclared,
        bool $termsAccepted
    ): array {
        $errors = self::errors(
            $name,
            $federalCode,
            $email,
            $phone,
            $addressLine,
            $postalCode,
            $province,
            $city
        );
        if (!$athleteDataRightsDeclared) {
            $errors[] = 'validation.club_athlete_data_rights_required';
        }
        if (!$termsAccepted) {
            $errors[] = 'validation.club_terms_required';
        }

        return $errors;
    }

    /** @return list<string> Translation keys for invalid fields. */
    public static function errors(
        string $name,
        string $federalCode,
        string $email,
        string $phone,
        string $addressLine,
        string $postalCode,
        string $province,
        string $city
    ): array {
        $errors = self::identityErrors($name, $federalCode, $email);
        if (trim($phone) === '') {
            $errors[] = 'validation.club_phone_required';
        }
        if (trim($addressLine) === '') {
            $errors[] = 'validation.club_address_line_required';
        }
        if (trim($postalCode) === '') {
            $errors[] = 'validation.club_postal_code_required';
        }
        if (!array_key_exists($province, SardinianLocation::all())) {
            $errors[] = 'validation.club_province_invalid';
        }
        if (!SardinianLocation::isValid($province, $city)) {
            $errors[] = 'validation.club_city_invalid';
        }

        return $errors;
    }

    /** @return list<string> Translation keys for fields editable from the club table. */
    public static function summaryErrors(
        string $name,
        string $federalCode,
        string $email,
        string $phone
    ): array {
        $errors = self::identityErrors($name, $federalCode, $email);
        if (trim($phone) === '') {
            $errors[] = 'validation.club_phone_required';
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
