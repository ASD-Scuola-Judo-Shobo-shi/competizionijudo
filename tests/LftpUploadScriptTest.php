<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class LftpUploadScriptTest extends TestCase
{
    public function testUploadScriptFailsExplicitlyForConnectionAndMirrorPhases(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/lftp-upload.sh');

        self::assertStringContainsString('set cmd:fail-exit true', $script);
        self::assertStringContainsString('set ftp:ssl-force true', $script);
        self::assertStringContainsString(' ${open_command} pwd;', $script);
        self::assertStringContainsString('FTPS connection, certificate, or authentication failed', $script);
        self::assertStringContainsString('FTPS preflight succeeded; starting ${operation} upload.', $script);
        self::assertStringContainsString('FTPS upload failed after a successful preflight', $script);
    }
}
