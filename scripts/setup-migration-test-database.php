<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/helpers.php';

$envPath = $argv[1] ?? dirname(__DIR__) . '/.env.dev';

if (!is_file($envPath)) {
    fwrite(STDERR, "Missing .env.dev. Copy .env.dev.example to .env.dev and fill the values.\n");
    exit(2);
}

load_env($envPath);

$prefix = requiredEnv('MIGRATION_TEST_DATABASE_PREFIX');
if (preg_match('/\Acompetizionijudo_test_[a-z0-9_]+\z/', $prefix) !== 1) {
    fwrite(STDERR, "MIGRATION_TEST_DATABASE_PREFIX must start with competizionijudo_test_.\n");
    exit(2);
}

$testUser = requiredEnv('MIGRATION_TEST_USER');
$testPassword = requiredEnv('MIGRATION_TEST_PASSWORD');
$testHosts = envList('MIGRATION_TEST_USER_HOSTS', requiredEnv('MIGRATION_TEST_HOST'));

$adminSocket = optionalEnv('MIGRATION_TEST_ADMIN_SOCKET');
$adminHost = optionalEnv('MIGRATION_TEST_ADMIN_HOST') ?: optionalEnv('MIGRATION_TEST_HOST') ?: '127.0.0.1';
$adminPort = envPort('MIGRATION_TEST_ADMIN_PORT', envPort('MIGRATION_TEST_PORT', 3306));
$adminUser = optionalEnv('MIGRATION_TEST_ADMIN_USER') ?: 'root';
$adminPassword = optionalEnv('MIGRATION_TEST_ADMIN_PASSWORD');
$databases = [
    $prefix . '_clean',
    $prefix . '_pre_squash',
    $prefix . '_guarded',
];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $server = new PDO(adminDsn($adminSocket, $adminHost, $adminPort), $adminUser, $adminPassword, $options);

    foreach ($testHosts as $testHost) {
        $account = quoteLiteral($server, $testUser) . '@' . quoteLiteral($server, $testHost);
        $password = quoteLiteral($server, $testPassword);

        $server->exec('CREATE USER IF NOT EXISTS ' . $account . ' IDENTIFIED BY ' . $password);
        $server->exec('ALTER USER ' . $account . ' IDENTIFIED BY ' . $password);

        foreach ($databases as $database) {
            $server->exec('GRANT ALL PRIVILEGES ON ' . quoteIdentifier($database) . '.* TO ' . $account);
        }
    }

    $server->exec('FLUSH PRIVILEGES');

    echo "Prepared migration test user {$testUser} for:\n";
    foreach ($databases as $database) {
        echo "- {$database}\n";
    }
    echo "Run composer test:migrations or composer ci.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Unable to prepare the migration test database user.\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

function requiredEnv(string $key): string
{
    $value = optionalEnv($key);
    if ($value === '') {
        fwrite(STDERR, "{$key} must be set in .env.dev.\n");
        exit(2);
    }

    return $value;
}

function optionalEnv(string $key): string
{
    $value = env($key, '');

    return is_scalar($value) ? trim((string) $value) : '';
}

/**
 * @return list<string>
 */
function envList(string $key, string $default): array
{
    $value = optionalEnv($key) ?: $default;
    $items = array_values(array_filter(array_map('trim', explode(',', $value))));

    if ($items === []) {
        fwrite(STDERR, "{$key} must contain at least one MySQL account host.\n");
        exit(2);
    }

    return $items;
}

function envPort(string $key, int $default): int
{
    $value = optionalEnv($key);
    if ($value === '') {
        return $default;
    }

    if (ctype_digit($value) && (int) $value > 0 && (int) $value <= 65535) {
        return (int) $value;
    }

    fwrite(STDERR, "{$key} must be a TCP port between 1 and 65535.\n");
    exit(2);
}

function adminDsn(string $socket, string $host, int $port): string
{
    if ($socket !== '') {
        return 'mysql:unix_socket=' . $socket . ';charset=utf8mb4';
    }

    return sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
}

function quoteLiteral(PDO $server, string $value): string
{
    $quoted = $server->quote($value);
    if (!is_string($quoted)) {
        throw new RuntimeException('Unable to quote SQL literal.');
    }

    return $quoted;
}

function quoteIdentifier(string $identifier): string
{
    if (preg_match('/\A[a-z0-9_]+\z/', $identifier) !== 1) {
        throw new RuntimeException('Unsafe synthetic database identifier.');
    }

    return chr(96) . $identifier . chr(96);
}
