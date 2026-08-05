<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;
use DateTimeZone;
use finfo;

final class EventInputValidator
{
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    public const MAX_REGISTRATION_FEE_CENTS = 4_294_967_295;
    public const MAX_NOTES_LENGTH = 2000;

    private const EVENT_TYPES = [
        'only_precompetitive',
        'only_competitive',
        'precompetitive_and_competitive',
    ];

    private const UPLOAD_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    private function __construct()
    {
    }

/**
      * @param array<string, array<string, mixed>> $uploads
      * @return list<string> Translation keys for invalid fields.
      */
    public static function errors(
        string $name,
        string $date,
        string $location,
        string $registrationDeadline,
        string $type,
        array $uploads,
        ?string $maxParticipants = null,
        string $notes = ''
    ): array {
        $errors = [];
        $eventDate = self::date($date);
        $deadline = trim($registrationDeadline) === '' ? null : self::date($registrationDeadline);

        if (trim($name) === '') {
            $errors[] = 'validation.event_name_required';
        }
        if ($eventDate === null) {
            $errors[] = 'validation.event_date_invalid';
        }
        if (trim($location) === '') {
            $errors[] = 'validation.event_location_required';
        }
        if (trim($registrationDeadline) !== '' && $deadline === null) {
            $errors[] = 'validation.event_deadline_invalid';
        } elseif ($eventDate !== null && $deadline !== null && $deadline > $eventDate) {
            $errors[] = 'validation.event_deadline_after_event';
        }
        if (!in_array(trim($type), self::EVENT_TYPES, true)) {
            $errors[] = 'validation.event_type_invalid';
        }

        if ($maxParticipants !== null && $maxParticipants !== '') {
            $maxParticipantsInt = filter_var($maxParticipants, FILTER_VALIDATE_INT);
            if ($maxParticipantsInt === false || $maxParticipantsInt <= 0) {
                $errors[] = 'validation.event_max_participants_invalid';
            }
        }

        foreach (['poster_file', 'info_file'] as $field) {
            $uploadError = self::uploadError($uploads[$field] ?? null);
            if ($uploadError !== null) {
                $errors[] = $uploadError;
            }
        }

        if (mb_strlen(trim($notes)) > self::MAX_NOTES_LENGTH) {
            $errors[] = 'validation.event_notes_too_long';
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param list<array{
     *     id:int|null,
     *     name:string,
     *     fee_amount:string,
     *     fee_cents:int|null,
     *     is_default:bool
     * }> $options
     * @return list<string> Translation keys for invalid registration/payment fields.
     */
    public static function registrationConfigurationErrors(
        array $options,
        string $accountHolder,
        string $iban,
        string $bic
    ): array {
        $errors = [];
        if ($options === []) {
            $errors[] = 'validation.event_registration_option_required';
        }

        $defaultCount = 0;
        $names = [];
        foreach ($options as $option) {
            $name = trim($option['name']);
            if ($name === '' || mb_strlen($name) > 120) {
                $errors[] = 'validation.event_registration_option_name_invalid';
            } else {
                $normalizedName = mb_strtolower($name);
                if (isset($names[$normalizedName])) {
                    $errors[] = 'validation.event_registration_option_duplicate';
                }
                $names[$normalizedName] = true;
            }

            $feeCents = $option['fee_cents'];
            if (
                $feeCents === null
                || $feeCents < 0
                || $feeCents > self::MAX_REGISTRATION_FEE_CENTS
            ) {
                $errors[] = 'validation.event_registration_option_fee_invalid';
            }

            if ($option['is_default']) {
                $defaultCount++;
            }
        }

        if ($options !== [] && $defaultCount !== 1) {
            $errors[] = 'validation.event_registration_option_default_invalid';
        }

        $accountHolder = trim($accountHolder);
        $iban = self::normalizeIban($iban);
        $bic = self::normalizeBic($bic);
        $hasAnySepaValue = $accountHolder !== '' || $iban !== '' || $bic !== '';
        if ($hasAnySepaValue) {
            if ($accountHolder === '' || mb_strlen($accountHolder) > 70) {
                $errors[] = 'validation.event_sepa_account_holder_invalid';
            }
            if (!self::validIban($iban)) {
                $errors[] = 'validation.event_sepa_iban_invalid';
            }
            if ($bic !== '' && preg_match('/\A[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?\z/', $bic) !== 1) {
                $errors[] = 'validation.event_sepa_bic_invalid';
            }
        }

        return array_values(array_unique($errors));
    }

    public static function registrationFeeCents(string $value): ?int
    {
        $value = str_replace(',', '.', trim($value));
        if (preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', $value) !== 1) {
            return null;
        }

        [$euros, $decimals] = array_pad(explode('.', $value, 2), 2, '');
        $decimals = str_pad($decimals, 2, '0');
        if (strlen($euros) > 10) {
            return null;
        }

        $cents = ((int) $euros * 100) + (int) $decimals;

        return $cents <= self::MAX_REGISTRATION_FEE_CENTS ? $cents : null;
    }

    public static function normalizeIban(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($iban)) ?? '');
    }

    public static function normalizeBic(string $bic): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($bic)) ?? '');
    }

    /** @param array<string, mixed>|null $upload */
    public static function extension(?array $upload): ?string
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => null,
        };
    }

    /** @param array<string, mixed>|null $upload */
    private static function uploadError(?array $upload): ?string
    {
        if ($upload === null) {
            return null;
        }

        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            return 'validation.event_upload_failed';
        }

        $size = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
        if ($size === false || $size < 0) {
            return 'validation.event_upload_failed';
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            return 'validation.event_upload_too_large';
        }

        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_file($temporaryPath)) {
            return 'validation.event_upload_failed';
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

        return in_array($mime, self::UPLOAD_MIME_TYPES, true)
            ? null
            : 'validation.event_upload_type_invalid';
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

    private static function validIban(string $iban): bool
    {
        if (
            preg_match('/\A[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\z/', $iban) !== 1
            || strlen($iban) > 34
        ) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $remainder = 0;
        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character)
                ? (string) (ord($character) - ord('A') + 10)
                : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
