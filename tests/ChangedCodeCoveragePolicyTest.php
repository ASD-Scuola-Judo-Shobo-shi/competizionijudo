<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ChangedCodeCoveragePolicyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/competizionijudo-coverage-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        file_put_contents($this->directory . '/coverage.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/synthetic/repository/src/Controller/Synthetic.php">
      <line num="10" type="stmt" count="1"/>
      <line num="11" type="stmt" count="0"/>
      <line num="12" type="stmt" count="2"/>
    </file>
  </project>
</coverage>
XML);
        file_put_contents($this->directory . '/changes.diff', <<<'DIFF'
diff --git a/src/Controller/Synthetic.php b/src/Controller/Synthetic.php
--- a/src/Controller/Synthetic.php
+++ b/src/Controller/Synthetic.php
@@ -1,0 +10,3 @@
+first
+second
+third
DIFF);
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/coverage.xml');
        unlink($this->directory . '/changes.diff');
        rmdir($this->directory);
    }

    public function testChangedCoveragePassesAtOrBelowMeasuredPercentage(): void
    {
        [$status, $output] = $this->runPolicy('60');

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('66.7% (2/3; required 60.0%)', $output);
    }

    public function testChangedCoverageFailsBelowRequiredPercentage(): void
    {
        [$status, $output] = $this->runPolicy('70');

        self::assertSame(1, $status, $output);
        self::assertStringContainsString('66.7% (2/3; required 70.0%)', $output);
    }

    /** @return array{int, string} */
    private function runPolicy(string $minimum): array
    {
        $process = proc_open(
            [
                PHP_BINARY,
                dirname(__DIR__) . '/scripts/check-changed-coverage.php',
                $this->directory . '/coverage.xml',
                'HEAD^',
                $minimum,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
            ['CHANGED_COVERAGE_DIFF_FILE' => $this->directory . '/changes.diff']
        );
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
