<?php

declare(strict_types=1);

namespace App\Validation;

final class NameNormalizer
{
    // Increment when name rules change so older previews cannot authorize new matches.
    public const VERSION = 3;

    private const APEX_VOWELS = [
        'a' => 'à', 'e' => 'è', 'i' => 'ì', 'o' => 'ò', 'u' => 'ù',
        'A' => 'À', 'E' => 'È', 'I' => 'Ì', 'O' => 'Ò', 'U' => 'Ù',
    ];
    private const COMBINING_ACCENTS = [
        "a\u{0300}" => 'à', "e\u{0300}" => 'è', "i\u{0300}" => 'ì', "o\u{0300}" => 'ò', "u\u{0300}" => 'ù',
        "A\u{0300}" => 'À', "E\u{0300}" => 'È', "I\u{0300}" => 'Ì', "O\u{0300}" => 'Ò', "U\u{0300}" => 'Ù',
        "e\u{0301}" => 'é', "E\u{0301}" => 'É',
    ];

    public static function person(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        $value = strtr($value, self::COMBINING_ACCENTS);
        $value = preg_replace_callback(
            "/([aeiou])['’‘ʼ](?![\p{L}\p{M}])/iu",
            static fn(array $match): string => self::APEX_VOWELS[$match[1]],
            $value
        ) ?? $value;

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function club(string $value): string
    {
        $value = self::person($value);

        return preg_replace([
            '/(?<![\p{L}\p{M}\p{N}_])a[.\s]*s[.\s]*d(?>(?:\s*\.)?)(?![\p{L}\p{M}\p{N}_])/iu',
            '/(?<![\p{L}\p{M}\p{N}_])c[.\s]*s(?>(?:\s*\.)?)(?![\p{L}\p{M}\p{N}_])/iu',
            '/(?<![\p{L}\p{M}\p{N}_])s[.\s]*e(?>(?:\s*\.)?)(?![\p{L}\p{M}\p{N}_])/iu',
        ], ['ASD', 'CS', 'S.E.'], $value) ?? $value;
    }

    public static function personKey(string $value): string
    {
        return self::comparisonKey(self::person($value));
    }

    public static function clubKey(string $value): string
    {
        return self::comparisonKey(self::club($value));
    }

    private static function comparisonKey(string $value): string
    {
        return mb_strtolower(strtr($value, ['’' => "'", '‘' => "'", 'ʼ' => "'"]), 'UTF-8');
    }
}
