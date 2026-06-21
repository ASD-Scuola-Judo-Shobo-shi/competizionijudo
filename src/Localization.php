<?php

declare(strict_types=1);

namespace App;

final class Localization
{
    private static string $locale = 'it';
    private static array $messages = [];

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        self::$messages = [];
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function trans(string $key, array $replacements = []): string
    {
        if (self::$messages === []) {
            self::loadMessages();
        }

        $value = self::getValue(self::$messages, $key) ?? $key;

        foreach ($replacements as $search => $replace) {
            $value = str_replace('{' . $search . '}', (string) $replace, $value);
        }

        return $value;
    }

    private static function loadMessages(): void
    {
        $path = dirname(__DIR__) . '/lang/' . self::$locale . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Locale file not found: %s', $path));
        }

        self::$messages = require $path;
    }

    private static function getValue(array $data, string $key): mixed
    {
        $segments = explode('.', $key);
        $value = $data;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
