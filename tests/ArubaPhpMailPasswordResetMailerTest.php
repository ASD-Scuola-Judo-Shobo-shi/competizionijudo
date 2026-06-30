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
        $_ENV['MAIL_FROM_ADDRESS'] = 'postmaster@competizionijudo.it';
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
        $url = 'https://www.competizionijudo.it/club_reset_password.php?token=synthetic-token';

        $mailer->sendResetLink('club@example.test', $url);

        self::assertCount(1, $calls);
        self::assertSame('club@example.test', $calls[0]['recipient']);
        self::assertSame(__('club.reset_email.subject'), $calls[0]['subject']);
        self::assertStringContainsString($url, $calls[0]['message']);
        self::assertSame('postmaster@competizionijudo.it', $calls[0]['headers']['From']);
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
            'https://www.competizionijudo.it/club_reset_password.php?token=synthetic'
        );
    }
}
