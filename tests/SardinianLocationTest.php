<?php

declare(strict_types=1);

namespace Tests;

use App\Model\SardinianLocation;
use PHPUnit\Framework\TestCase;

final class SardinianLocationTest extends TestCase
{
    public function testCurrentSardinianProvincesAndMunicipalitiesAreAvailable(): void
    {
        self::assertSame([
            'Città metropolitana di Cagliari',
            'Città metropolitana di Sassari',
            "Provincia dell'Ogliastra",
            'Provincia della Gallura Nord-Est Sardegna',
            'Provincia del Medio Campidano',
            'Provincia del Sulcis Iglesiente',
            'Provincia di Nuoro',
            'Provincia di Oristano',
        ], SardinianLocation::provinces());
        self::assertTrue(SardinianLocation::isValid('Provincia di Nuoro', 'Nuoro'));
        self::assertTrue(SardinianLocation::isValid('Provincia di Oristano', 'Oristano'));
        self::assertFalse(SardinianLocation::isValid('Provincia di Nuoro', 'Cagliari'));
        self::assertSame('08100', SardinianLocation::postalCode('Provincia di Nuoro', 'Nuoro'));
        self::assertSame('08020', SardinianLocation::postalCode(
            'Provincia della Gallura Nord-Est Sardegna',
            'San Teodoro'
        ));
        self::assertSame([
            'province' => 'Provincia di Nuoro',
            'city' => 'Nuoro',
        ], SardinianLocation::locationForPostalCode('08100'));
        self::assertNull(SardinianLocation::locationForPostalCode('09040'));
    }
}
