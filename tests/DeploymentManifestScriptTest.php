<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentManifestScriptTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/competizionijudo-manifest-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory . '/nested', 0700, true));
        self::assertNotFalse(file_put_contents($this->directory . '/.htaccess', "Deny from all\n"));
        self::assertNotFalse(file_put_contents($this->directory . '/nested/example.php', "<?php echo 'ok';\n"));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/nested/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory . '/nested');
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach (glob($this->directory . '/.*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory);
    }

    public function testGeneratesAndVerifiesACompleteArtifactManifest(): void
    {
        $process = proc_open(
            ['bash', dirname(__DIR__) . '/scripts/generate-deploy-manifest.sh', $this->directory],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__)
        );
        self::assertIsResource($process);

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), $output);
        $manifest = (string) file_get_contents($this->directory . '/DEPLOYMENT_MANIFEST.sha256');
        self::assertStringContainsString('./.htaccess', $manifest);
        self::assertStringContainsString('./nested/example.php', $manifest);
        self::assertStringNotContainsString('DEPLOYMENT_MANIFEST.sha256', $manifest);
    }
}
