<?php

declare(strict_types=1);

namespace Tests;

use App\Localization;
use App\Service\ArubaPhpMailPasswordResetMailer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ArubaPhpMailPasswordResetMailerTest extends TestCase
{
    private bool $senderExisted;
    private mixed $originalSender;

    protected function setUp(): void
    {
        $this->senderExisted = array_key_exists('MAIL_FROM_ADDRESS', $_ENV);
        $this->originalSender = $_ENV['MAIL_FROM_ADDRESS'] ?? null;
        $_ENV['MAIL_FROM_ADDRESS'] = 'postmaster@mailer.example.test';
        Localization::setLocale('it');
    }

    protected function tearDown(): void
    {
        if ($this->senderExisted) {
            $_ENV['MAIL_FROM_ADDRESS'] = $this->originalSender;
        } else {
            unset($_ENV['MAIL_FROM_ADDRESS']);
        }
    }

    public function testSendsLocalizedPlainTextResetMessageThroughArubaPhpMail(): void
    {
        $calls = [];
        $mailer = new ArubaPhpMailPasswordResetMailer(static function (
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ) use (&$calls): bool {
            $calls[] = compact('recipient', 'subject', 'message', 'headers');

            return true;
        });
        $url = 'https://www.mailer.example.test/club_reset_password.php?token=synthetic-token';

        $mailer->sendResetLink('club@example.test', $url);

        self::assertCount(1, $calls);
        self::assertSame('club@example.test', $calls[0]['recipient']);
        self::assertSame(__('club.reset_email.subject'), mb_decode_mimeheader($calls[0]['subject']));
        self::assertStringContainsString($url, quoted_printable_decode($calls[0]['message']));
        self::assertSame('postmaster@mailer.example.test', $calls[0]['headers']['From']);
        self::assertSame('text/plain; charset=UTF-8', $calls[0]['headers']['Content-Type']);
        self::assertSame('quoted-printable', $calls[0]['headers']['Content-Transfer-Encoding']);
    }

    public function testSendsLocalizedRegistrationConfirmationMessageThroughArubaPhpMail(): void
    {
        $calls = [];
        $mailer = new ArubaPhpMailPasswordResetMailer(static function (
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ) use (&$calls): bool {
            $calls[] = compact('recipient', 'subject', 'message', 'headers');

            return true;
        });
        $url = 'https://www.mailer.example.test/club_confirm_registration.php?token=synthetic-token';

        $mailer->sendRegistrationConfirmationLink('club@example.test', $url);

        self::assertCount(1, $calls);
        self::assertSame(
            __('club.registration_confirmation_email.subject'),
            mb_decode_mimeheader($calls[0]['subject'])
        );
        self::assertMatchesRegularExpression('/\A[\x20-\x7E]+\z/', $calls[0]['subject']);
        self::assertStringNotContainsString('società', $calls[0]['subject']);
        self::assertStringContainsString($url, quoted_printable_decode($calls[0]['message']));
        self::assertSame('quoted-printable', $calls[0]['headers']['Content-Transfer-Encoding']);
    }

    public function testSendsRegistrationRecapAsPlainText(): void
    {
        $calls = [];
        $mailer = new ArubaPhpMailPasswordResetMailer(static function (
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ) use (&$calls): bool {
            $calls[] = compact('recipient', 'subject', 'message', 'headers');

            return true;
        });
        $message = "Informazioni sulla competizione\nCompetizione: Trofeo sintetico\n";

        $mailer->sendRegistrationRecap(
            'club@example.test',
            'Riepilogo variazione iscrizione — Trofeo sintetico',
            $message
        );

        self::assertCount(1, $calls);
        self::assertSame('club@example.test', $calls[0]['recipient']);
        self::assertSame(
            'Riepilogo variazione iscrizione — Trofeo sintetico',
            mb_decode_mimeheader($calls[0]['subject'])
        );
        self::assertSame(
            str_replace("\n", "\r\n", $message),
            quoted_printable_decode($calls[0]['message'])
        );
        self::assertSame('text/plain; charset=UTF-8', $calls[0]['headers']['Content-Type']);
    }

    public function testRejectsNonHttpsResetLinksBeforeCallingTransport(): void
    {
        $mailer = new ArubaPhpMailPasswordResetMailer(static function (
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ): bool {
            self::fail('Transport must not be called for an unsafe reset URL.');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTPS');

        $mailer->sendResetLink('club@example.test', 'http://example.test/reset?token=synthetic');
    }

    public function testReportsArubaTransportRejection(): void
    {
        $mailer = new ArubaPhpMailPasswordResetMailer(static fn(
            string $recipient,
            string $subject,
            string $message,
            array $headers
        ): bool => false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aruba');

        $mailer->sendResetLink(
            'club@example.test',
            'https://www.mailer.example.test/club_reset_password.php?token=synthetic'
        );
    }
}
