<?php

declare(strict_types=1);

namespace App\Validation;

use DateTimeImmutable;
use DateTimeZone;
use finfo;

final class EventInputValidator
{
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

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
        array $uploads
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

        foreach (['poster_file', 'info_file'] as $field) {
            $uploadError = self::uploadError($uploads[$field] ?? null);
            if ($uploadError !== null) {
                $errors[] = $uploadError;
            }
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string, mixed>|null $upload */
    public static function extension(?array $upload): ?string
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) ($upload['tmp_name'] ?? ''));

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
}
