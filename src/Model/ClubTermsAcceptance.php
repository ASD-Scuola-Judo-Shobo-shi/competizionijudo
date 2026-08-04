<?php

declare(strict_types=1);

namespace App\Model;

final class ClubTermsAcceptance
{
    /** Bump this whenever the contractual terms change materially. */
    public const VERSION = '2026-08-04';

    public static function hasCurrentVersion(int $clubId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM club_terms_acceptances '
            . 'WHERE club_id = ? AND terms_version = ? LIMIT 1'
        );
        $statement->execute([$clubId, self::VERSION]);

        return $statement->fetchColumn() !== false;
    }

    public static function record(Club $club, string $locale): void
    {
        $acceptedLocale = in_array($locale, ['en', 'it'], true) ? $locale : 'it';
        $representativeName = trim($club->contact_first_name . ' ' . $club->contact_last_name);
        if ($representativeName === '') {
            $representativeName = $club->name;
        }
        $statement = Database::connection()->prepare(
            'INSERT INTO club_terms_acceptances '
            . '(club_id, accepted_by_club_id, representative_name, accepted_account_email, '
            . 'terms_version, accepted_locale) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $club->id,
            $club->id,
            $representativeName,
            $club->email,
            self::VERSION,
            $acceptedLocale,
        ]);
    }
}
