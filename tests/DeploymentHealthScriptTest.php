<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentHealthScriptTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/competizionijudo-health-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory . '/sleep', "#!/usr/bin/env bash\nexit 0\n");
        chmod($this->directory . '/sleep', 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testAcceptsHealthyResponseWithExpectedRevision(): void
    {
        $revision = str_repeat('a', 40);
        $this->writeCurl($revision, 'ok', '200');

        [$status, $output] = $this->runScript($revision);

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('Deployment health verified', $output);
    }

    public function testRejectsWrongRevisionEvenWithHttp200(): void
    {
        $this->writeCurl(str_repeat('b', 40), 'ok', '200');

        [$status, $output] = $this->runScript(str_repeat('a', 40));

        self::assertSame(1, $status, $output);
        self::assertStringContainsString('verification failed', $output);
    }

    public function testRejectsNonHttpsHealthUrlBeforeCurl(): void
    {
        $this->writeCurl(str_repeat('a', 40), 'ok', '200');

        [$status, $output] = $this->runScript(str_repeat('a', 40), 'http://example.test/health');

        self::assertSame(2, $status, $output);
        self::assertStringContainsString('must use HTTPS', $output);
    }

    private function writeCurl(string $revision, string $healthStatus, string $httpStatus): void
    {
        $body = json_encode(['status' => $healthStatus, 'revision' => $revision], JSON_THROW_ON_ERROR);
        $script = <<<'BASH'
#!/usr/bin/env bash
set -euo pipefail
output=''
while (( $# > 0 )); do
  if [[ "$1" == '--output' ]]; then
    output="$2"
    shift 2
  else
    shift
  fi
done
printf '%s' "$FAKE_HEALTH_BODY" > "$output"
printf '%s' "$FAKE_HEALTH_STATUS"
BASH;
        file_put_contents($this->directory . '/curl', $script . PHP_EOL);
        chmod($this->directory . '/curl', 0700);
        file_put_contents($this->directory . '/body', $body);
        file_put_contents($this->directory . '/status', $httpStatus);
    }

    /** @return array{int, string} */
    private function runScript(string $revision, string $url = 'https://example.test/health'): array
    {
        $body = (string) file_get_contents($this->directory . '/body');
        $httpStatus = (string) file_get_contents($this->directory . '/status');
        $process = proc_open(
            [
                'bash',
                dirname(__DIR__) . '/scripts/check-deployment-health.sh',
                $url,
                $revision,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
            [
                'PATH' => $this->directory . ':' . (string) getenv('PATH'),
                'FAKE_HEALTH_BODY' => $body,
                'FAKE_HEALTH_STATUS' => $httpStatus,
            ]
        );
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
