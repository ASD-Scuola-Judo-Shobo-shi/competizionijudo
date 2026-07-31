<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Club;
use App\Model\Event;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use InvalidArgumentException;
use Throwable;

final class RegistrationPaymentService
{
    private const MAX_EPC_PAYLOAD_BYTES = 331;
    private const MAX_EPC_AMOUNT_CENTS = 99_999_999_999;

    /**
     * @return array{account_holder:string, iban:string, bic:string}
     */
    public static function paymentInfoForEvent(Event $event): array
    {
        return [
            'account_holder' => self::singleLine($event->sepa_account_holder ?? ''),
            'iban' => self::compactUppercase($event->sepa_iban ?? ''),
            'bic' => self::compactUppercase($event->sepa_bic ?? ''),
        ];
    }

    public static function formatAmount(int $feeCents): string
    {
        $sign = $feeCents < 0 ? '-' : '';
        $absoluteCents = abs($feeCents);

        return sprintf(
            '%s€%d.%02d',
            $sign,
            intdiv($absoluteCents, 100),
            $absoluteCents % 100
        );
    }

    public static function buildPaymentReason(Event $event, Club $club): string
    {
        $parts = array_filter([
            self::singleLine($event->name),
            self::singleLine($club->name),
            self::singleLine($club->federal_code),
        ], static fn(string $part): bool => $part !== '');

        return self::truncate(implode(' - ', $parts), 140);
    }

    public static function buildEpcPayload(Event $event, Club $club, int $amountCents): string
    {
        if ($amountCents < 1 || $amountCents > self::MAX_EPC_AMOUNT_CENTS) {
            throw new InvalidArgumentException('The EPC transfer amount is outside the supported range.');
        }

        $paymentInfo = self::paymentInfoForEvent($event);
        if (
            $paymentInfo['account_holder'] === ''
            || mb_strlen($paymentInfo['account_holder']) > 70
            || preg_match('/\A[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\z/', $paymentInfo['iban']) !== 1
            || (
                $paymentInfo['bic'] !== ''
                && preg_match('/\A[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?\z/', $paymentInfo['bic']) !== 1
            )
        ) {
            throw new InvalidArgumentException('The event does not have complete SEPA payment details.');
        }

        // EPC069-12 version 002 permits an omitted BIC for EEA transfers.
        $lines = [
            'BCD',
            '002',
            '1',
            'SCT',
            $paymentInfo['bic'],
            $paymentInfo['account_holder'],
            $paymentInfo['iban'],
            self::formatEpcAmount($amountCents),
            '',
            '',
            self::buildPaymentReason($event, $club),
        ];
        $payload = rtrim(implode("\n", $lines), "\n");
        if (strlen($payload) > self::MAX_EPC_PAYLOAD_BYTES) {
            throw new InvalidArgumentException('The EPC payload exceeds 331 bytes.');
        }

        return $payload;
    }

    public static function buildQrCodeDataUri(Event $event, Club $club, int $amountCents): ?string
    {
        try {
            $payload = self::buildEpcPayload($event, $club, $amountCents);
            $options = new QROptions([
                'eccLevel' => EccLevel::M,
                'versionMax' => 13,
                'outputInterface' => QRMarkupSVG::class,
                'outputBase64' => true,
                'addQuietzone' => true,
                'quietzoneSize' => 4,
                'connectPaths' => true,
            ]);

            return (new QRCode($options))->render($payload);
        } catch (Throwable) {
            return null;
        }
    }

    private static function formatEpcAmount(int $feeCents): string
    {
        return sprintf('EUR%d.%02d', intdiv($feeCents, 100), $feeCents % 100);
    }

    private static function compactUppercase(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
    }

    private static function singleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private static function truncate(string $value, int $maximumCharacters): string
    {
        if (mb_strlen($value) <= $maximumCharacters) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maximumCharacters));
    }
}
