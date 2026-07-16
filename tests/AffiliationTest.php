<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Affiliation;
use PHPUnit\Framework\TestCase;

final class AffiliationTest extends TestCase
{
    public function testSelectedAllowsOnlyConfiguredUniqueCodes(): void
    {
        self::assertSame(
            ['FIJLKAM', 'CSEN'],
            Affiliation::selected(['FIJLKAM', 'unknown', 'CSEN', 'FIJLKAM'])
        );
    }

    public function testEncodeAndDecodeSupportMultipleAndLegacySingleAffiliations(): void
    {
        self::assertSame(
            '["ACSI","FIJLKAM"]',
            Affiliation::encode(['ACSI', 'FIJLKAM'])
        );
        self::assertSame(['ACSI', 'FIJLKAM'], Affiliation::decode('["ACSI","FIJLKAM"]'));
        self::assertSame(['FIJLKAM'], Affiliation::decode('FIJLKAM'));
        self::assertNull(Affiliation::encode([]));
    }

    public function testOptionsIncludeAllConfiguredBodies(): void
    {
        self::assertCount(15, Affiliation::options());
        self::assertSame(
            'FIJLKAM – Federazione Italiana Judo Lotta Karate Arti Marziali',
            Affiliation::options()['FIJLKAM']
        );
    }
}
