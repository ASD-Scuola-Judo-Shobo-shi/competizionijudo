<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class LftpUploadScriptTest extends TestCase
{
    public function testUploadScriptFailsExplicitlyForConnectionAndMirrorPhases(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/lftp-upload.sh');

        self::assertStringContainsString('FTPS upload wrapper v4:', $script);
        self::assertStringContainsString('FTP_PASSWORD must not contain line breaks', $script);
        self::assertStringContainsString('set cmd:fail-exit true', $script);
        self::assertStringContainsString('set ftp:ssl-force true', $script);
        self::assertStringContainsString("FTP_SERVER='ftplnx02.aruba.it'", $script);
        self::assertSame(2, substr_count($script, 'mkdir -pf '));
        self::assertStringContainsString(' ${open_command} quote PWD;', $script);
        self::assertStringContainsString('FTPS connection, certificate, or authentication failed', $script);
        self::assertStringContainsString('FTPS preflight succeeded; starting ${operation} upload.', $script);
        self::assertStringContainsString('FTPS upload failed after a successful preflight', $script);
        self::assertStringNotContainsString("\$'\\0'", $script);
    }

    public function testValidInputsReachBothLftpPhases(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-fake-lftp-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $fakeLftp = $directory . '/lftp';
        self::assertNotFalse(file_put_contents($fakeLftp, "#!/usr/bin/env bash\nexit 0\n"));
        self::assertTrue(chmod($fakeLftp, 0700));

        try {
            $process = proc_open(
                ['bash', dirname(__DIR__) . '/scripts/lftp-upload.sh', 'deploy', 'site/prod/'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                [
                    'PATH' => $directory . ':' . (string) getenv('PATH'),
                    'FTP_SERVER' => 'ftp.example.test',
                    'FTP_PORT' => '21',
                    'FTP_USERNAME' => 'synthetic-user',
                    'FTP_PASSWORD' => 'synthetic-password',
                ]
            );
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), $output);
            self::assertStringContainsString('FTPS upload wrapper v4:', $output);
            self::assertStringContainsString('FTPS preflight succeeded; starting deploy upload.', $output);
        } finally {
            unlink($fakeLftp);
            rmdir($directory);
        }
    }
}
