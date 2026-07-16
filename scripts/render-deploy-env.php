<?php

declare(strict_types=1);

const REQUIRED_DEPLOY_ENV_KEYS = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_URL',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'MIGRATION_WEBHOOK_SECRET',
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

const OPTIONAL_BOOLEAN_KEYS = ['APP_TEST_RESET_LINKS'];
const OPTIONAL_POSITIVE_INTEGER_KEYS = ['EVENTS_UPCOMING_LIMIT'];

exit(main($argv));

/** @param list<string> $argv */
function main(array $argv): int
{
    try {
        $rootDirectory = dirname(__DIR__);
        $templatePath = $rootDirectory . '/.env.example';
        $outputDirectory = $argv[1] ?? ($rootDirectory . '/build/runtime-env');

        $templateLines = file($templatePath, FILE_IGNORE_NEW_LINES);
        if ($templateLines === false) {
            fwrite(STDERR, "Unable to read deploy env template: {$templatePath}" . PHP_EOL);

            return 1;
        }

        $renderedLines = [];
        $resolvedValues = [];
        foreach ($templateLines as $line) {
            $entry = parseTemplateEntry($line);
            if ($entry === null) {
                $renderedLines[] = $line;
                continue;
            }

            [$key, $templateValue] = $entry;
            $resolvedValue = resolveValue($key, $templateValue);
            $renderedLines[] = $key . '=' . $resolvedValue;
            $resolvedValues[$key] = normalizeValue($resolvedValue);
        }

        $issues = validateResolvedValues($resolvedValues);
        if ($issues !== []) {
            fwrite(STDERR, "Cannot render deploy .env:" . PHP_EOL);
            foreach ($issues as $issue) {
                fwrite(STDERR, " - {$issue}" . PHP_EOL);
            }

            return 1;
        }

        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0700, true) && !is_dir($outputDirectory)) {
            fwrite(STDERR, "Unable to create output directory: {$outputDirectory}" . PHP_EOL);

            return 1;
        }

        $outputPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        $bytesWritten = file_put_contents($outputPath, implode(PHP_EOL, $renderedLines) . PHP_EOL);
        if ($bytesWritten === false) {
            fwrite(STDERR, "Unable to write deploy env file: {$outputPath}" . PHP_EOL);

            return 1;
        }
        if (!chmod($outputPath, 0600) || (fileperms($outputPath) & 0777) !== 0600) {
            fwrite(STDERR, "Unable to secure deploy env file permissions: {$outputPath}" . PHP_EOL);

            return 1;
        }

        fwrite(STDOUT, "Rendered deploy environment: {$outputPath}" . PHP_EOL);

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);

        return 1;
    }
}

/** @return array{string, string}|null */
function parseTemplateEntry(string $line): ?array
{
    if (!preg_match('/^\s*([A-Z0-9_]+)\s*=(.*)$/', $line, $matches)) {
        return null;
    }

    return [$matches[1], $matches[2]];
}

function resolveValue(string $key, string $templateValue): string
{
    $override = getenv($key);
    if ($override === false) {
        return $templateValue;
    }

    if ($override === '' && normalizeValue($templateValue) !== '') {
        return $templateValue;
    }

    assertRenderableOverride($key, $override);

    return $override;
}

function assertRenderableOverride(string $key, string $value): void
{
    if (preg_match('/[\r\n\0]/', $value) === 1) {
        throw new RuntimeException("{$key} must not contain newlines or NUL bytes.");
    }

    if ($value !== trim($value, " \t\n\r\0\x0B")) {
        throw new RuntimeException("{$key} must not start or end with whitespace.");
    }

    if (
        $value !== ''
        && (
            str_starts_with($value, '"')
            || str_ends_with($value, '"')
            || str_starts_with($value, "'")
            || str_ends_with($value, "'")
        )
    ) {
        throw new RuntimeException("{$key} must not start or end with quotes.");
    }
}

function normalizeValue(string $value): string
{
    return trim($value, " \t\n\r\0\x0B\"'");
}

/**
 * @param array<string, string> $resolvedValues
 * @return list<string>
 */
function validateResolvedValues(array $resolvedValues): array
{
    $issues = [];

    $missingKeys = [];
    foreach (REQUIRED_DEPLOY_ENV_KEYS as $key) {
        if (!array_key_exists($key, $resolvedValues) || trim($resolvedValues[$key]) === '') {
            $missingKeys[] = $key;
        }
    }
    if ($missingKeys !== []) {
        $issues[] = 'Missing required deploy env keys: ' . implode(', ', $missingKeys);
    }

    $appEnvironment = $resolvedValues['APP_ENV'] ?? '';
    if ($appEnvironment !== '' && !in_array($appEnvironment, ['production', 'development'], true)) {
        $issues[] = 'APP_ENV must be production or development.';
    }

    $appDebug = filter_var($resolvedValues['APP_DEBUG'] ?? null, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($appDebug === null) {
        $issues[] = 'APP_DEBUG must be a boolean value.';
    }

    foreach (OPTIONAL_BOOLEAN_KEYS as $key) {
        $value = $resolvedValues[$key] ?? '';
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
            $issues[] = "{$key} must be a boolean value when provided.";
        }
    }

    $appUrl = $resolvedValues['APP_URL'] ?? '';
    if (
        $appUrl !== ''
        && (
            filter_var($appUrl, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) !== 'https'
        )
    ) {
        $issues[] = 'APP_URL must be a valid HTTPS URL.';
    }

    foreach (['MAIL_FROM_ADDRESS', 'APP_OWNER_EMAIL'] as $key) {
        $email = $resolvedValues[$key] ?? '';
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $issues[] = "{$key} must be a valid email address.";
        }
    }

    $mailer = strtolower($resolvedValues['PASSWORD_RESET_MAILER'] ?? '');
    if ($mailer !== '' && $mailer !== 'aruba') {
        $issues[] = 'PASSWORD_RESET_MAILER must be aruba.';
    }

    foreach (['APP_LOG_RETENTION_DAYS', 'APP_BACKUP_RETENTION_DAYS', ...OPTIONAL_POSITIVE_INTEGER_KEYS] as $key) {
        $value = $resolvedValues[$key] ?? '';
        if ($value === '') {
            continue;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer < 1) {
            $issues[] = "{$key} must be a positive integer.";
        }
    }

    $locale = strtolower($resolvedValues['APP_LOCALE'] ?? '');
    if ($locale !== '' && !in_array($locale, ['it', 'en'], true)) {
        $issues[] = 'APP_LOCALE must be it or en when provided.';
    }

    return $issues;
}
