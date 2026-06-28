<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Club;
use App\Validation\AthleteInputValidator;
use App\Validation\ClubInputValidator;
use App\Validation\EventInputValidator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class InputValidatorTest extends TestCase
{
    public function testClubValidatorRequiresIdentityAndValidEmailFields(): void
    {
        self::assertSame([
            'validation.club_name_required',
            'validation.federal_code_required',
            'validation.club_email_invalid',
        ], ClubInputValidator::registrationErrors('', '', 'invalid'));
        self::assertSame([
            'validation.club_name_required',
            'validation.federal_code_required',
            'validation.club_email_invalid',
            'validation.contact_email_invalid',
            'validation.recovery_email_invalid',
        ], ClubInputValidator::errors('', '', 'invalid', 'invalid', ''));

        self::assertSame([], ClubInputValidator::errors(
            'Synthetic Club',
            'SYN-12',
            'club@example.test',
            'contact@example.test',
            'recovery@example.test'
        ));
        self::assertSame('club@example.test', Club::normalizeEmail(' Club@Example.Test '));
    }

    public function testAthleteValidatorRejectsForgedEnumsDatesAndWeights(): void
    {
        $today = new DateTimeImmutable('2026-06-28', new DateTimeZone('UTC'));

        self::assertSame([
            'validation.athlete_last_name_required',
            'validation.athlete_first_name_required',
            'validation.athlete_gender_invalid',
            'validation.athlete_birth_date_invalid',
            'validation.athlete_weight_invalid',
            'validation.athlete_belt_invalid',
        ], AthleteInputValidator::errors('', '', 'forged', '2026-02-30', '0', 'forged', $today));
        self::assertContains(
            'validation.athlete_birth_date_invalid',
            AthleteInputValidator::errors('A', 'B', 'F', '2026-06-29', '45.2', 'white', $today)
        );
        self::assertContains(
            'validation.athlete_weight_invalid',
            AthleteInputValidator::errors('A', 'B', 'M', '2000-01-01', '12abc', 'black', $today)
        );
        self::assertSame([], AthleteInputValidator::errors(
            'Athlete',
            'Synthetic',
            'F',
            '2000-02-29',
            '45,2',
            'blue',
            $today
        ));
    }

    public function testEventValidatorRejectsInvalidDatesOrderingTypeAndOversizedUpload(): void
    {
        $oversizedUpload = [
            'error' => UPLOAD_ERR_OK,
            'size' => EventInputValidator::MAX_UPLOAD_BYTES + 1,
            'tmp_name' => '/not-read-for-oversized-upload',
        ];
        $errors = EventInputValidator::errors(
            '',
            '2026-02-30',
            '',
            '2026-07-01',
            'forged',
            ['poster_file' => $oversizedUpload]
        );

        self::assertContains('validation.event_name_required', $errors);
        self::assertContains('validation.event_date_invalid', $errors);
        self::assertContains('validation.event_location_required', $errors);
        self::assertContains('validation.event_type_invalid', $errors);
        self::assertContains('validation.event_upload_too_large', $errors);
        self::assertContains(
            'validation.event_deadline_after_event',
            EventInputValidator::errors(
                'Synthetic Event',
                '2026-06-28',
                'Synthetic Venue',
                '2026-06-29',
                'only_competitive',
                []
            )
        );
        self::assertSame([], EventInputValidator::errors(
            'Synthetic Event',
            '2026-06-28',
            'Synthetic Venue',
            '2026-06-28',
            'precompetitive_and_competitive',
            []
        ));
    }

    public function testEventValidatorRejectsUnsupportedUploadContent(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'c12-upload-');
        self::assertIsString($temporaryFile);
        file_put_contents($temporaryFile, 'synthetic plain text fixture');

        try {
            $errors = EventInputValidator::errors(
                'Synthetic Event',
                '2026-06-28',
                'Synthetic Venue',
                '',
                'only_precompetitive',
                ['info_file' => [
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($temporaryFile),
                    'tmp_name' => $temporaryFile,
                ]]
            );

            self::assertSame(['validation.event_upload_type_invalid'], $errors);
        } finally {
            unlink($temporaryFile);
        }
    }

    public function testMigrationPreflightsHashedEmailsBeforeAddingUniqueNormalizedIndex(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__) . '/migrations/20260628_000002_add_normalized_club_email_unique_index.sql'
        );

        self::assertIsString($migration);
        self::assertStringContainsString('SHA2(LOWER(TRIM(email)), 256)', $migration);
        self::assertStringContainsString('UPDATE clubs SET email = LOWER(TRIM(email))', $migration);
        self::assertStringContainsString('GENERATED ALWAYS AS (LOWER(TRIM(email))) STORED', $migration);
        self::assertStringContainsString('UNIQUE INDEX uniq_clubs_normalized_email', $migration);
        self::assertStringNotContainsString('SELECT LOWER(TRIM(email)) FROM clubs', $migration);
    }
}
