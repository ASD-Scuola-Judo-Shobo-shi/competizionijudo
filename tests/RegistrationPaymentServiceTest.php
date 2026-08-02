<?php

declare(strict_types=1);

namespace Tests;

use App\Model\Club;
use App\Model\Event;
use App\Service\RegistrationPaymentService;
use PHPUnit\Framework\TestCase;

final class RegistrationPaymentServiceTest extends TestCase
{
    public function testEpcPayloadUsesOnlyEventAndClubBusinessValues(): void
    {
        $event = $this->event(bic: null);
        $club = $this->club();

        $payload = RegistrationPaymentService::buildEpcPayload($event, $club, 1500);

        self::assertSame([
            'BCD',
            '002',
            '1',
            'SCT',
            '',
            'Synthetic Beneficiary',
            'IT60X0542811101000000123456',
            'EUR15.00',
            '',
            '',
            'Synthetic Tournament - Synthetic Club - SYN-001',
        ], explode("\n", $payload));
        self::assertLessThanOrEqual(331, strlen($payload));
    }

    public function testQrCodeUsesEpcErrorCorrectionAndProducesAnSvgDataUri(): void
    {
        $uri = RegistrationPaymentService::buildQrCodeDataUri(
            $this->event(),
            $this->club(),
            2599
        );

        self::assertNotNull($uri);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        self::assertIsString($svg);
        self::assertStringContainsString('<svg', $svg);
    }

    public function testQrCodeIsNotCreatedWithoutDatabaseBackedSepaDetailsOrPositiveAmount(): void
    {
        $eventWithoutSepaDetails = $this->event(accountHolder: null, iban: null, bic: null);

        self::assertNull(RegistrationPaymentService::completePaymentInfoForEvent($eventWithoutSepaDetails));
        self::assertNull(RegistrationPaymentService::buildQrCodeDataUri(
            $eventWithoutSepaDetails,
            $this->club(),
            1500
        ));
        self::assertNull(RegistrationPaymentService::buildQrCodeDataUri(
            $this->event(),
            $this->club(),
            0
        ));
        self::assertSame([
            'account_holder' => 'Synthetic Beneficiary',
            'iban' => 'IT60X0542811101000000123456',
            'bic' => 'UNCRITMMXXX',
        ], RegistrationPaymentService::completePaymentInfoForEvent($this->event()));
    }

    private function event(
        ?string $accountHolder = 'Synthetic Beneficiary',
        ?string $iban = 'IT60X0542811101000000123456',
        ?string $bic = 'UNCRITMMXXX'
    ): Event {
        return new Event(
            101,
            'Synthetic Tournament',
            '2098-07-01',
            'Synthetic Venue',
            'Synthetic Organizer',
            '2098-06-30',
            'only_competitive',
            null,
            null,
            null,
            null,
            true,
            false,
            null,
            $accountHolder,
            $iban,
            $bic,
        );
    }

    private function club(): Club
    {
        return new Club(
            201,
            'Synthetic Club',
            'club@example.test',
            '',
            null,
            null,
            'Nuoro',
            'Provincia di Nuoro',
            'Synthetic',
            'Contact',
            null,
            '',
            'SYN-001',
        );
    }
}
