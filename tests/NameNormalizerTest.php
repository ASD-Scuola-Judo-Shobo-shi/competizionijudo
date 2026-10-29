<?php

declare(strict_types=1);

namespace Tests;

use App\Validation\NameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NameNormalizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function clubNames(): iterable
    {
        yield 'dotted ASD' => ['a.s.d. SYNTHETIC JUDO', 'ASD Synthetic Judo'];
        yield 'mixed ASD' => ['A.sD. synthetic JUDO', 'ASD Synthetic Judo'];
        yield 'spaced ASD' => [" A . S . D .  SYNTHETIC\tJUDO ", 'ASD Synthetic Judo'];
        yield 'initials ASD' => ['a s d SYNTHETIC JUDO', 'ASD Synthetic Judo'];
        yield 'plain ASD' => ['asd SYNTHETIC JUDO', 'ASD Synthetic Judo'];
        yield 'dotted CS' => ['c.s. SYNTHETIC JUDO', 'CS Synthetic Judo'];
        yield 'initials CS' => ['c s SYNTHETIC JUDO', 'CS Synthetic Judo'];
        yield 'plain CS' => ['cs SYNTHETIC JUDO', 'CS Synthetic Judo'];
        yield 'mixed SE' => ['S.e. SYNTHETIC JUDO', 'S.E. Synthetic Judo'];
        yield 'plain SE' => ['SE SYNTHETIC JUDO', 'S.E. Synthetic Judo'];
        yield 'lowercase SE' => ['se SYNTHETIC JUDO', 'S.E. Synthetic Judo'];
        yield 'dotted SE without final dot' => ['s.e SYNTHETIC JUDO', 'S.E. Synthetic Judo'];
        yield 'spaced SE' => [' s . E . SYNTHETIC JUDO ', 'S.E. Synthetic Judo'];
        yield 'initials SE' => ['s e SYNTHETIC JUDO', 'S.E. Synthetic Judo'];
        yield 'canonical SE' => ['S.E. Synthetic Judo', 'S.E. Synthetic Judo'];
        yield 'SE in parentheses' => ['JUDO (s.e.)', 'Judo (S.E.)'];
        yield 'both acronyms and accents' => ["a.s.d. c.s. JUDO CITTA' GIU’", 'ASD CS Judo Città Giù'];
        yield 'acronyms in parentheses' => ['JUDO (a.s.d.) - c.s.', 'Judo (ASD) - CS'];
        yield 'do not change word fragments' => ['ASDRUBALE CSEN ACCADEMIA', 'Asdrubale Csen Accademia'];
        yield 'do not change SE word fragments' => ['SERIE SESTO ESEMPIO', 'Serie Sesto Esempio'];
        yield 'do not change embedded initials' => ['XASD JUDO_CS', 'Xasd Judo_Cs'];
        yield 'normalized' => ['ASD Synthetic Judo Città', 'ASD Synthetic Judo Città'];
        yield 'empty' => [" \t\n", ''];
    }

    #[DataProvider('clubNames')]
    public function testClubNamesNormalizeAcronymsAndTitleCaseTheRest(string $input, string $expected): void
    {
        self::assertSame($expected, NameNormalizer::club($input));
        self::assertSame($expected, NameNormalizer::club($expected));
    }

    /** @return iterable<string, array{string, string}> */
    public static function personNames(): iterable
    {
        yield 'grave vowels' => ["a' e' i' o' u'", 'À È Ì Ò Ù'];
        yield 'uppercase word' => ["GIU'", 'Giù'];
        yield 'typographic apices' => [" GIU’  PERUʼ ", 'Giù Perù'];
        yield 'existing accented letters' => ['GIÙ PÉREZ', 'Giù Pérez'];
        yield 'decomposed grave accents' => ["CITTA\u{0300} GIU\u{0300}", 'Città Giù'];
        yield 'decomposed acute accent' => ["PE\u{0301}REZ", 'Pérez'];
        yield 'internal apostrophes' => ["D'ANGELO O'CONNOR", "D'angelo O'connor"];
        yield 'internal typographic apostrophe' => ['D’ESPOSITO', 'D’esposito'];
        yield 'person initials remain initials' => ['A.S.D. C.S.', 'A.s.d. C.s.'];
        yield 'SE is specific to club names' => ['SE S.E.', 'Se S.e.'];
    }

    #[DataProvider('personNames')]
    public function testPersonNamesNormalizeAccentsAndPreserveInternalApostrophes(string $input, string $expected): void
    {
        self::assertSame($expected, NameNormalizer::person($input));
        self::assertSame($expected, NameNormalizer::person($expected));
    }

    public function testIdentityComparisonUsesTheSameNormalizedNames(): void
    {
        self::assertSame(NameNormalizer::clubKey("a.s.d. CITTA'"), NameNormalizer::clubKey('ASD Città'));
        self::assertSame(NameNormalizer::clubKey('C.S. SYNTHETIC'), NameNormalizer::clubKey('CS Synthetic'));
        self::assertSame(NameNormalizer::clubKey('SE SYNTHETIC'), NameNormalizer::clubKey('S.E. Synthetic'));
        self::assertSame(NameNormalizer::personKey("GIU'"), NameNormalizer::personKey('Giù'));
        self::assertSame(NameNormalizer::personKey("O'CONNOR"), NameNormalizer::personKey('O’Connor'));
        self::assertSame(NameNormalizer::personKey("PE\u{0301}REZ"), NameNormalizer::personKey('Pérez'));
        self::assertNotSame(NameNormalizer::personKey('Giu'), NameNormalizer::personKey('Giù'));
        self::assertNotSame(NameNormalizer::clubKey('CS'), NameNormalizer::clubKey('CSEN'));
    }
}
