<?php

declare(strict_types=1);

namespace App\Model;

final class ClubDataRightsDeclaration
{
    public const VERSION = '2026-08-04-article-14';

    public static function hasCurrentVersion(int $clubId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM club_data_rights_declarations '
            . 'WHERE club_id = ? AND declaration_version = ? LIMIT 1'
        );
        $statement->execute([$clubId, self::VERSION]);

        return $statement->fetchColumn() !== false;
    }

    public static function record(int $clubId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO club_data_rights_declarations '
            . '(club_id, declared_by_club_id, declaration_version) VALUES (?, ?, ?)'
        );
        $statement->execute([$clubId, $clubId, self::VERSION]);
    }
}
