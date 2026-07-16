<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Request;

final class MigrationWebhookAuthenticator
{
    private const MAX_AGE_SECONDS = 300;
    private const SIGNATURE_PREFIX = 'competizionijudo-migration-v1|';

    public function __construct(private readonly string $secret)
    {
    }

    public function accepts(Request $request, ?int $now = null): bool
    {
        $timestamp = (string) $request->server('HTTP_X_MIGRATION_TIMESTAMP', '');
        $signature = (string) $request->server('HTTP_X_MIGRATION_SIGNATURE', '');
        if (
            preg_match('/\A[0-9]{10}\z/', $timestamp) !== 1
            || preg_match('/\A[a-f0-9]{64}\z/i', $signature) !== 1
        ) {
            return false;
        }

        $now ??= time();
        if (abs($now - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', self::SIGNATURE_PREFIX . $timestamp, $this->secret);

        return hash_equals($expected, $signature);
    }

    public static function signingPayload(string $timestamp): string
    {
        return self::SIGNATURE_PREFIX . $timestamp;
    }
}
