<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ProductionConfiguration
{
    private const REQUIRED_KEYS = [
        'APP_URL',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'ADMIN_USER',
        'ADMIN_PASS_HASH',
        'PASSWORD_RESET_MAILER',
        'MAIL_FROM_ADDRESS',
        'APP_OWNER',
        'APP_OWNER_ADDRESS',
        'APP_OWNER_FISCAL_CODE',
        'APP_OWNER_EMAIL',
        'APP_WEBHOST',
        'APP_WEBHOST_LOCATION',
        'APP_LOG_RETENTION_DAYS',
        'APP_BACKUP_RETENTION_DAYS',
    ];

    /** @param array<string, mixed>|null $configuration */
    public static function assertReady(Logger $logger, ?array $configuration = null): void
    {
        $configuration ??= self::fromEnvironment();
        $issues = self::issues($configuration);
        if ($issues === []) {
            return;
        }

        $exception = new RuntimeException('Production configuration is invalid. Review the application log.');
        $correlationId = bin2hex(random_bytes(16));
        foreach ($issues as $issue) {
            $logger->error('configuration.' . $issue, $exception, $correlationId);
        }

        throw $exception;
    }

    /** @return array<string, mixed> */
    private static function fromEnvironment(): array
    {
        $configuration = [];
        foreach ([...self::REQUIRED_KEYS, 'APP_DEBUG'] as $key) {
            $configuration[$key] = env($key);
        }

        return $configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     * @return list<string>
     */
    private static function issues(array $configuration): array
    {
        $issues = [];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $configuration) || trim((string) $configuration[$key]) === '') {
                $issues[] = 'missing.' . strtolower($key);
            }
        }

        $appUrl = trim((string) ($configuration['APP_URL'] ?? ''));
        if (
            $appUrl !== ''
            && (
                filter_var($appUrl, FILTER_VALIDATE_URL) === false
                || strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) !== 'https'
            )
        ) {
            $issues[] = 'invalid.app_url';
        }

        $debug = $configuration['APP_DEBUG'] ?? false;
        if (filter_var($debug, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false) {
            $issues[] = 'invalid.app_debug';
        }

        foreach (['MAIL_FROM_ADDRESS', 'APP_OWNER_EMAIL'] as $key) {
            $email = trim((string) ($configuration[$key] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $issues[] = 'invalid.' . strtolower($key);
            }
        }

        $mailer = strtolower(trim((string) ($configuration['PASSWORD_RESET_MAILER'] ?? '')));
        if ($mailer !== '' && $mailer !== 'aruba') {
            $issues[] = 'invalid.password_reset_mailer';
        }

        foreach (['APP_LOG_RETENTION_DAYS', 'APP_BACKUP_RETENTION_DAYS'] as $key) {
            $days = filter_var($configuration[$key] ?? null, FILTER_VALIDATE_INT);
            if ($days === false || $days < 1) {
                $issues[] = 'invalid.' . strtolower($key);
            }
        }

        return $issues;
    }
}
