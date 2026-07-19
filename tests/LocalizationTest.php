<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use PHPUnit\Framework\TestCase;

final class LocalizationTest extends TestCase
{
    private const LOCALES = ['en', 'it'];

    protected function setUp(): void
    {
        Localization::setLocale('it');
    }

    public function testSetAndGetLocale(): void
    {
        self::assertSame('it', Localization::getLocale());
        Localization::setLocale('en');
        self::assertSame('en', Localization::getLocale());
        Localization::setLocale('it');
    }

    public function testTransWithSimpleKey(): void
    {
        $result = Localization::trans('nav.home');
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }

    public function testTransWithUnknownKeyReturnsKey(): void
    {
        $result = Localization::trans('nonexistent.key.path');
        self::assertSame('nonexistent.key.path', $result);
    }

    public function testTransWithReplacements(): void
    {
        $result = Localization::trans('errors.password_too_short', ['minimum' => '12']);
        self::assertStringContainsString('12', $result);
    }

    public function testTransReturnsEnglishWhenLocaleSet(): void
    {
        Localization::setLocale('en');
        $result = Localization::trans('nav.home');
        self::assertSame('Home', $result);
        Localization::setLocale('it');
    }

    public function testTransForDoesNotChangeActiveLocale(): void
    {
        self::assertSame('Home', Localization::transFor('en', 'nav.home'));
        self::assertSame('it', Localization::getLocale());
        self::assertSame('Home', Localization::transFor('en', 'nav.home'));
    }

    public function testTransWithNestedKey(): void
    {
        $result = Localization::trans('admin.login.title');
        self::assertIsString($result);
        self::assertNotEmpty($result);
    }

    public function testLocaleCatalogsHaveMatchingNonEmptyKeysAndPlaceholders(): void
    {
        $catalogs = $this->catalogs();
        $englishKeys = array_keys($catalogs['en']);
        $italianKeys = array_keys($catalogs['it']);

        self::assertSame($englishKeys, $italianKeys);

        foreach ($englishKeys as $key) {
            self::assertNotSame('', trim($catalogs['en'][$key]), 'Empty English translation: ' . $key);
            self::assertNotSame('', trim($catalogs['it'][$key]), 'Empty Italian translation: ' . $key);
            self::assertSame(
                $this->placeholders($catalogs['en'][$key]),
                $this->placeholders($catalogs['it'][$key]),
                'Placeholder mismatch for translation: ' . $key
            );
        }
    }

    public function testProductionTranslationReferencesExistInEveryLocale(): void
    {
        $catalogs = $this->catalogs();

        foreach ($this->productionTranslationKeys() as $key) {
            foreach (self::LOCALES as $locale) {
                self::assertArrayHasKey(
                    $key,
                    $catalogs[$locale],
                    sprintf('Missing %s translation for production key: %s', $locale, $key)
                );
            }
        }
    }

    /** @return array{en: array<string, string>, it: array<string, string>} */
    private function catalogs(): array
    {
        $english = $this->flatten(require dirname(__DIR__) . '/lang/en.php');
        $italian = $this->flatten(require dirname(__DIR__) . '/lang/it.php');
        ksort($english);
        ksort($italian);

        return ['en' => $english, 'it' => $italian];
    }

    /**
     * @param array<string, mixed> $messages
     * @return array<string, string>
     */
    private function flatten(array $messages, string $prefix = ''): array
    {
        $flattened = [];
        foreach ($messages as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flattened += $this->flatten($value, $path);
                continue;
            }

            self::assertIsString($value, 'Translation must be a string: ' . $path);
            $flattened[$path] = $value;
        }

        return $flattened;
    }

    /** @return list<string> */
    private function placeholders(string $translation): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)\}/', $translation, $matches);
        $placeholders = $matches[1];
        sort($placeholders);

        return $placeholders;
    }

    /** @return list<string> */
    private function productionTranslationKeys(): array
    {
        $keys = [];
        foreach ($this->productionPhpFiles() as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);

            preg_match_all(
                '/\b(?:__|translate)\(\s*([\'\"])([^\'\"]+)\1/',
                $source,
                $calls,
                PREG_SET_ORDER
            );
            foreach ($calls as $call) {
                if (!str_ends_with($call[2], '.')) {
                    $keys[$call[2]] = true;
                }
            }

            preg_match_all('/[\'\"](validation\.[A-Za-z0-9_.]+)[\'\"]/', $source, $validationKeys);
            foreach ($validationKeys[1] as $validationKey) {
                $keys[$validationKey] = true;
            }
        }

        ksort($keys);

        return array_keys($keys);
    }

    /** @return list<string> */
    private function productionPhpFiles(): array
    {
        $files = [];
        foreach (['config', 'public', 'routes', 'scripts', 'src', 'views'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $directory)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }
}
