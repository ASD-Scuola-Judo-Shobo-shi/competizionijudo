<?php

declare(strict_types=1);

namespace Tests;

use App\Service\DatabasePasswordResetTokenIssuer;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PasswordResetTokenIssuerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE clubs (
                id INTEGER PRIMARY KEY,
                email TEXT NOT NULL,
                normalized_email TEXT NOT NULL UNIQUE,
                approved_at TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE password_reset_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                club_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            "INSERT INTO clubs (id, email, normalized_email)
             VALUES (7, 'known@example.test', 'known@example.test')"
        );
    }

    public function testIssuingATokenAtomicallyDeletesThePreviousToken(): void
    {
        $this->database->exec(
            "INSERT INTO password_reset_tokens (club_id, token_hash, expires_at, used)
             VALUES (7, 'previous-token-hash', '2026-08-03 13:00:00', 0)"
        );

        $rawToken = $this->issuer()->issueForEmail(' KNOWN@EXAMPLE.TEST ');

        self::assertNotNull($rawToken);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $rawToken);
        $tokens = $this->database->query(
            'SELECT token_hash, expires_at, used FROM password_reset_tokens ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $tokens);
        self::assertSame(hash('sha256', $rawToken), $tokens[0]['token_hash']);
        self::assertSame('2026-08-03 13:00:00', $tokens[0]['expires_at']);
        self::assertSame(0, (int) $tokens[0]['used']);
        self::assertStringNotContainsString($rawToken, serialize($tokens));
    }

    public function testUnknownEmailDoesNotCreateAToken(): void
    {
        self::assertNull($this->issuer()->issueForEmail('unknown@example.test'));
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM password_reset_tokens'
        )->fetchColumn());
    }

    public function testFailedInsertRollsBackPreviousTokenInvalidation(): void
    {
        $this->database->exec(
            "INSERT INTO password_reset_tokens (club_id, token_hash, expires_at, used)
             VALUES (7, 'still-valid-token-hash', '2026-08-03 13:00:00', 0)"
        );
        $this->database->exec(
            "CREATE TRIGGER reject_reset_token_insert
             BEFORE INSERT ON password_reset_tokens
             BEGIN
                 SELECT RAISE(ABORT, 'synthetic insert failure');
             END"
        );

        try {
            $this->issuer()->issueForEmail('known@example.test');
            self::fail('The synthetic token insert unexpectedly succeeded.');
        } catch (PDOException) {
            self::assertSame(0, (int) $this->database->query(
                "SELECT used FROM password_reset_tokens WHERE token_hash = 'still-valid-token-hash'"
            )->fetchColumn());
            self::assertFalse($this->database->inTransaction());
        }
    }

    private function issuer(): DatabasePasswordResetTokenIssuer
    {
        return new DatabasePasswordResetTokenIssuer(
            $this->database,
            static fn(): DateTimeImmutable => new DateTimeImmutable(
                '2026-08-03 12:00:00',
                new DateTimeZone('UTC')
            )
        );
    }
}
