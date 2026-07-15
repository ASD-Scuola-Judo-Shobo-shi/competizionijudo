<?php

declare(strict_types=1);

namespace App\Model;

final class ClubDataRightsDeclaration
{
    public const VERSION = '2026-07-15';

    public static function record(int $clubId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO club_data_rights_declarations '
            . '(club_id, declared_by_club_id, declaration_version) VALUES (?, ?, ?)'
        );
        $statement->execute([$clubId, $clubId, self::VERSION]);
    }
}
