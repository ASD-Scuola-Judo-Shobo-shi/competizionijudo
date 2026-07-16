<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;
use Throwable;

final class ClubRegistrationConfirmation
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $registration */
    public static function issue(array $registration): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+24 hours')
            ->format('Y-m-d H:i:s');
        $database = Database::connection();
        $upsert = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), '
                . 'registration_payload = VALUES(registration_payload), expires_at = VALUES(expires_at), '
                . 'confirmed_at = NULL'
            : 'ON CONFLICT(email) DO UPDATE SET token_hash = excluded.token_hash, '
                . 'registration_payload = excluded.registration_payload, expires_at = excluded.expires_at, '
                . 'confirmed_at = NULL';
        $statement = $database->prepare(
            'INSERT INTO club_registration_confirmations '
            . '(email, token_hash, registration_payload, expires_at) VALUES (?, ?, ?, ?) '
            . $upsert
        );
        $statement->execute([
            Club::normalizeEmail((string) ($registration['email'] ?? '')),
            hash('sha256', $token),
            json_encode($registration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $expiresAt,
        ]);

        return $token;
    }

    public static function confirm(string $token): bool
    {
        if (preg_match('/\A[a-f0-9]{64}\z/i', $token) !== 1) {
            return false;
        }

        $database = Database::connection();
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        try {
            if (!$database->beginTransaction()) {
                throw new RuntimeException('Unable to begin registration confirmation.');
            }

            $lock = $database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $candidate = $database->prepare(
                'SELECT id, registration_payload FROM club_registration_confirmations '
                . 'WHERE token_hash = ? AND confirmed_at IS NULL AND expires_at > ? '
                . 'LIMIT 1' . $lock
            );
            $candidate->execute([hash('sha256', $token), $now]);
            $record = $candidate->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) {
                $database->commit();

                return false;
            }

            $claim = $database->prepare(
                'UPDATE club_registration_confirmations SET confirmed_at = ? '
                . 'WHERE id = ? AND confirmed_at IS NULL AND expires_at > ?'
            );
            $claim->execute([$now, (int) $record['id'], $now]);
            if ($claim->rowCount() !== 1) {
                $database->rollBack();

                return false;
            }

            $payload = json_decode((string) $record['registration_payload'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('Registration confirmation payload is invalid.');
            }

            $club = Club::add($payload);
            ClubDataRightsDeclaration::record($club->id);

            if (!$database->commit()) {
                throw new RuntimeException('Unable to confirm club registration.');
            }

            return true;
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw $exception;
        }
    }
}
