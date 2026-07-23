<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class LftpUploadScriptTest extends TestCase
{
    public function testUploadScriptFailsExplicitlyForConnectionAndMirrorPhases(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/lftp-upload.sh');

        self::assertStringContainsString('FTPS upload wrapper v5:', $script);
        self::assertStringContainsString('FTP_PASSWORD must not contain line breaks', $script);
        self::assertStringContainsString('set cmd:fail-exit true', $script);
        self::assertStringContainsString('set xfer:use-temp-file true', $script);
        self::assertStringNotContainsString('set xfer:use-temp-file false', $script);
        self::assertStringContainsString('set xfer:temp-file-name .deploying-*', $script);
        self::assertStringContainsString('set ftp:use-mode-z false', $script);
        self::assertStringContainsString('set ftp:ssl-force true', $script);
        self::assertStringContainsString("FTP_SERVER='ftplnx02.aruba.it'", $script);
        self::assertGreaterThanOrEqual(3, substr_count($script, 'mkdir -pf '));
        self::assertStringContainsString('--transfer-all --no-perms --verbose --max-errors=1', $script);
        self::assertStringContainsString('DEPLOYMENT_MANIFEST.sha256', $script);
        self::assertStringContainsString('DEPLOYMENT_TRANSFER_PROTOCOL', $script);
        self::assertStringContainsString('FTPS upload: ${changed} changed and ${removed} removed artifact files', $script);
        self::assertStringContainsString('"$operation" == \'deploy-full\'', $script);
        self::assertStringNotContainsString('--exclude-glob .git*', $script);
        self::assertStringContainsString('verify_remote_files()', $script);
        self::assertStringContainsString('sha256sum --check --status --strict', $script);
        self::assertStringNotContainsString('rm -rf vendor/', $script);
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
        $artifactDirectory = $directory . '/artifact';
        self::assertTrue(mkdir($artifactDirectory, 0700));
        self::assertNotFalse(file_put_contents($artifactDirectory . '/DEPLOYMENT_TRANSFER_PROTOCOL', "1\n"));
        self::assertNotFalse(file_put_contents($artifactDirectory . '/index.php', "<?php\n"));
        $this->generateManifest($artifactDirectory);
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
                    'DEPLOY_ARTIFACT_DIR' => $artifactDirectory,
                ]
            );
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), $output);
            self::assertStringContainsString('FTPS upload wrapper v5:', $output);
            self::assertStringContainsString('transferring the complete artifact.', $output);
        } finally {
            unlink($fakeLftp);
            foreach (glob($artifactDirectory . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($artifactDirectory);
            rmdir($directory);
        }
    }

    public function testVerificationFailsWhenARemoteFileDoesNotMatchTheManifest(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-lftp-verify-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory . '/nested', 0700, true));
        self::assertNotFalse(file_put_contents($directory . '/.htaccess', "Deny from all\n"));
        self::assertNotFalse(file_put_contents($directory . '/nested/example.php', "<?php echo 'ok';\n"));

        $manifestProcess = proc_open(
            ['bash', dirname(__DIR__) . '/scripts/generate-deploy-manifest.sh', $directory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $manifestPipes,
            dirname(__DIR__)
        );
        self::assertIsResource($manifestProcess);
        stream_get_contents($manifestPipes[1]);
        stream_get_contents($manifestPipes[2]);
        fclose($manifestPipes[1]);
        fclose($manifestPipes[2]);
        self::assertSame(0, proc_close($manifestProcess));

        $fakeDirectory = sys_get_temp_dir() . '/competizionijudo-fake-lftp-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($fakeDirectory, 0700));
        $fakeLftp = $fakeDirectory . '/lftp';
        self::assertNotFalse(file_put_contents($fakeLftp, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
command="$2"
if [[ "$command" == *'quote PWD'* ]]; then
  exit 0
fi
if [[ "$command" != *'get "DEPLOYMENT_MANIFEST.sha256" -o '* ]]; then
  exit 1
fi
if [[ -n "${FAKE_COMMAND_LOG:-}" ]]; then
  printf '%s' "$command" > "$FAKE_COMMAND_LOG"
fi
while IFS= read -r download_arguments; do
  eval "set -- ${download_arguments}"
  remote_path="$1"
  [[ "$2" == '-o' ]]
  destination="$3"
  cp "$FAKE_REMOTE_ROOT/$remote_path" "$destination"
  if [[ "${FAKE_CORRUPT_PATH:-}" == "$remote_path" ]]; then
    printf 'corrupted' > "$destination"
  fi
done < <(printf '%s' "$command" | tr ';' '\n' | sed -n 's/.*get //p')
BASH
        ));
        self::assertTrue(chmod($fakeLftp, 0700));

        try {
            [$status, $output] = $this->runVerification($directory, $fakeDirectory, '', true);
            self::assertSame(0, $status, $output);

            [$status, $output] = $this->runVerification(
                $directory,
                $fakeDirectory,
                'nested/example.php',
                false,
                $directory . '/mismatches.txt'
            );
            self::assertSame(1, $status, $output);
            self::assertStringContainsString('Remote file checksum verification failed.', $output);
            self::assertSame("./nested/example.php\n", file_get_contents($directory . '/mismatches.txt'));

            [$status, $output] = $this->runVerification(
                $directory,
                $fakeDirectory,
                '',
                false,
                $directory . '/mismatches.txt',
                $directory . '/commands.txt'
            );
            self::assertSame(0, $status, $output);
            self::assertStringContainsString('FTPS targeted verification succeeded', $output);
            $commands = (string) file_get_contents($directory . '/commands.txt');
            self::assertStringContainsString('get "nested/example.php"', $commands);
            self::assertStringNotContainsString('get ".htaccess"', $commands);
            self::assertSame('', file_get_contents($directory . '/mismatches.txt'));
        } finally {
            unlink($fakeLftp);
            rmdir($fakeDirectory);
            unlink($directory . '/nested/example.php');
            rmdir($directory . '/nested');
            unlink($directory . '/.htaccess');
            unlink($directory . '/DEPLOYMENT_MANIFEST.sha256');
            unlink($directory . '/mismatches.txt');
            unlink($directory . '/commands.txt');
            rmdir($directory);
        }
    }

    public function testTargetedRepairUploadsOnlyTheReportedFiles(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-lftp-repair-' . bin2hex(random_bytes(8));
        $artifact = $directory . '/artifact';
        $fakeDirectory = $directory . '/fake';
        self::assertTrue(mkdir($artifact, 0700, true));
        self::assertTrue(mkdir($fakeDirectory, 0700));
        self::assertNotFalse(file_put_contents($artifact . '/changed.php', "<?php echo 'changed';\n"));
        self::assertNotFalse(file_put_contents($artifact . '/unchanged.php', "<?php echo 'unchanged';\n"));
        $this->generateManifest($artifact);
        $mismatchFile = $directory . '/mismatches.txt';
        self::assertNotFalse(file_put_contents($mismatchFile, "./changed.php\n"));

        $fakeLftp = $fakeDirectory . '/lftp';
        $commandLog = $fakeDirectory . '/command.log';
        self::assertNotFalse(file_put_contents($fakeLftp, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
command="$2"
if [[ "$command" == *'quote PWD'* ]]; then
  exit 0
fi
printf '%s' "$command" > "$FAKE_COMMAND_LOG"
BASH
        ));
        self::assertTrue(chmod($fakeLftp, 0700));

        try {
            $process = proc_open(
                ['bash', dirname(__DIR__) . '/scripts/lftp-upload.sh', 'repair', 'site/prod/', $artifact, $mismatchFile],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                [
                    'PATH' => $fakeDirectory . ':' . (string) getenv('PATH'),
                    'FTP_SERVER' => 'ftp.example.test',
                    'FTP_PORT' => '21',
                    'FTP_USERNAME' => 'synthetic-user',
                    'FTP_PASSWORD' => 'synthetic-password',
                    'FAKE_COMMAND_LOG' => $commandLog,
                ]
            );
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), $output);
            self::assertStringContainsString('retransferring 1 mismatched artifact files', $output);
            $commands = (string) file_get_contents($commandLog);
            self::assertStringContainsString(
                'put "' . $artifact . '/changed.php" -o "site/prod/changed.php"',
                $commands
            );
            self::assertStringNotContainsString('put "' . $artifact . '/unchanged.php"', $commands);
            self::assertStringContainsString(
                'put "' . $artifact . '/DEPLOYMENT_MANIFEST.sha256" -o "site/prod/DEPLOYMENT_MANIFEST.sha256"',
                $commands
            );
            self::assertStringNotContainsString('.repair-', $commands);
        } finally {
            unlink($fakeLftp);
            unlink($commandLog);
            rmdir($fakeDirectory);
            foreach (glob($artifact . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($artifact);
            unlink($mismatchFile);
            rmdir($directory);
        }
    }

    public function testDeploymentUploadsOnlyManifestDifferencesAfterTheInitialFullTransfer(): void
    {
        $directory = sys_get_temp_dir() . '/competizionijudo-lftp-diff-' . bin2hex(random_bytes(8));
        $localArtifact = $directory . '/local';
        $remoteArtifact = $directory . '/remote';
        $fakeDirectory = $directory . '/fake';
        self::assertTrue(mkdir($localArtifact, 0700, true));
        self::assertTrue(mkdir($remoteArtifact, 0700));
        self::assertTrue(mkdir($fakeDirectory, 0700));

        foreach ([$localArtifact, $remoteArtifact] as $artifact) {
            self::assertNotFalse(file_put_contents($artifact . '/DEPLOYMENT_TRANSFER_PROTOCOL', "1\n"));
            self::assertNotFalse(file_put_contents($artifact . '/unchanged.php', "<?php echo 'same';\n"));
        }
        self::assertNotFalse(file_put_contents($localArtifact . '/.htaccess', "Deny from all\n"));
        self::assertNotFalse(file_put_contents($localArtifact . '/added.php', "<?php echo 'added';\n"));
        self::assertNotFalse(file_put_contents($remoteArtifact . '/.htaccess', "Allow from all\n"));
        self::assertNotFalse(file_put_contents($remoteArtifact . '/removed.php', "<?php echo 'removed';\n"));
        $this->generateManifest($localArtifact);
        $this->generateManifest($remoteArtifact);

        $fakeLftp = $fakeDirectory . '/lftp';
        $commandLog = $fakeDirectory . '/command.log';
        self::assertNotFalse(file_put_contents($fakeLftp, <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
command="$2"
if [[ "$command" == *'quote PWD'* ]]; then
  exit 0
fi
if [[ "$command" == *'get "DEPLOYMENT_MANIFEST.sha256" -o '* ]]; then
  destination="$(printf '%s' "$command" | sed -n 's/.*get "DEPLOYMENT_MANIFEST\.sha256" -o "\([^"]*\)".*/\1/p')"
  cp "$FAKE_REMOTE_MANIFEST" "$destination"
  exit 0
fi
printf '%s' "$command" > "$FAKE_COMMAND_LOG"
BASH
        ));
        self::assertTrue(chmod($fakeLftp, 0700));

        try {
            $process = proc_open(
                ['bash', dirname(__DIR__) . '/scripts/lftp-upload.sh', 'deploy', 'site/prod/'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                [
                    'PATH' => $fakeDirectory . ':' . (string) getenv('PATH'),
                    'FTP_SERVER' => 'ftp.example.test',
                    'FTP_PORT' => '21',
                    'FTP_USERNAME' => 'synthetic-user',
                    'FTP_PASSWORD' => 'synthetic-password',
                    'DEPLOY_ARTIFACT_DIR' => $localArtifact,
                    'FAKE_REMOTE_MANIFEST' => $remoteArtifact . '/DEPLOYMENT_MANIFEST.sha256',
                    'FAKE_COMMAND_LOG' => $commandLog,
                ]
            );
            self::assertIsResource($process);

            $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            self::assertSame(0, proc_close($process), $output);
            self::assertStringContainsString('2 changed and 1 removed artifact files', $output);
            $commands = (string) file_get_contents($commandLog);
            self::assertStringContainsString('put "' . $localArtifact . '/.htaccess" -o "site/prod/.htaccess"', $commands);
            self::assertStringContainsString('put "' . $localArtifact . '/added.php" -o "site/prod/added.php"', $commands);
            self::assertStringContainsString('rm -f "site/prod/removed.php"', $commands);
            self::assertStringNotContainsString('put "' . $localArtifact . '/unchanged.php"', $commands);
            self::assertStringContainsString(
                'put "' . $localArtifact . '/DEPLOYMENT_MANIFEST.sha256" -o "site/prod/DEPLOYMENT_MANIFEST.sha256"',
                $commands
            );
        } finally {
            unlink($fakeLftp);
            foreach ([$localArtifact, $remoteArtifact] as $artifact) {
                foreach (glob($artifact . '/*') ?: [] as $file) {
                    unlink($file);
                }
                foreach (glob($artifact . '/.*') ?: [] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($artifact);
            }
            unlink($commandLog);
            rmdir($fakeDirectory);
            rmdir($directory);
        }
    }

    private function generateManifest(string $directory): void
    {
        $process = proc_open(
            ['bash', dirname(__DIR__) . '/scripts/generate-deploy-manifest.sh', $directory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__)
        );
        self::assertIsResource($process);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
    }

    /** @return array{int, string} */
    private function runVerification(
        string $directory,
        string $fakeDirectory,
        string $corruptPath = '',
        bool $useRelativeSourceDirectory = false,
        string $mismatchFile = '',
        string $commandLog = ''
    ): array {
        $sourceDirectory = $useRelativeSourceDirectory ? '.' : $directory;
        $process = proc_open(
            ['bash', dirname(__DIR__) . '/scripts/lftp-upload.sh', 'verify', 'site/prod/', $sourceDirectory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $useRelativeSourceDirectory ? $directory : dirname(__DIR__),
            [
                'PATH' => $fakeDirectory . ':' . (string) getenv('PATH'),
                'FTP_SERVER' => 'ftp.example.test',
                'FTP_PORT' => '21',
                'FTP_USERNAME' => 'synthetic-user',
                'FTP_PASSWORD' => 'synthetic-password',
                'FAKE_REMOTE_ROOT' => $directory,
                'FAKE_CORRUPT_PATH' => $corruptPath,
                'FTP_MISMATCH_FILE' => $mismatchFile,
                'FAKE_COMMAND_LOG' => $commandLog,
            ]
        );
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
