<?php

declare(strict_types=1);

namespace App\Core;

final class Cache
{
    private static string $dir = '';

    public static function init(string $cacheDir): void
    {
        self::$dir = rtrim($cacheDir, '/');
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }
    }

    public static function get(string $key): mixed
    {
        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }

        $data = file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $payload = unserialize($data);
        if (!is_array($payload) || !isset($payload['expires'], $payload['value'])) {
            return null;
        }

        if (time() > $payload['expires']) {
            unlink($path);
            return null;
        }

        return $payload['value'];
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 300): void
    {
        $path = self::path($key);
        $payload = serialize([
            'expires' => time() + $ttlSeconds,
            'value' => $value,
        ]);
        file_put_contents($path, $payload, LOCK_EX);
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function path(string $key): string
    {
        $hash = hash('sha256', $key);

        return self::$dir . '/cache_' . $hash;
    }
}