<?php

declare(strict_types=1);

namespace App;

final class Localization
{
    private static string $locale = 'it';

    /** @var array<string, array<string, mixed>> */
    private static array $messages = [];

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    /** @param array<string, string> $replacements */
    public static function trans(string $key, array $replacements = []): string
    {
        return self::transFor(self::$locale, $key, $replacements);
    }

    /** @param array<string, string> $replacements */
    public static function transFor(string $locale, string $key, array $replacements = []): string
    {
        if (!array_key_exists($locale, self::$messages)) {
            self::$messages[$locale] = self::loadMessages($locale);
        }

        $value = self::getValue(self::$messages[$locale], $key);
        if (!is_string($value)) {
            return $key;
        }

        foreach ($replacements as $search => $replace) {
            $value = str_replace('{' . $search . '}', (string) $replace, $value);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function loadMessages(string $locale): array
    {
        $path = dirname(__DIR__) . '/lang/' . $locale . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Locale file not found: %s', $path));
        }

        $messages = require $path;
        if (!is_array($messages)) {
            throw new \RuntimeException(sprintf('Locale file must return an array: %s', $path));
        }

        return $messages;
    }

    /** @param array<string, mixed> $data */
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
