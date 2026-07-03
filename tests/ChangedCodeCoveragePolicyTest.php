<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class ChangedCodeCoveragePolicyTest extends TestCase
{
    private string $directory;
    private string $gitDirectory;

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

        $this->gitDirectory = sys_get_temp_dir() . '/competizionijudo-coverage-git-' . bin2hex(random_bytes(8));
        mkdir($this->gitDirectory . '/src/Controller', 0700, true);
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/coverage.xml');
        unlink($this->directory . '/changes.diff');
        rmdir($this->directory);

        if (is_dir($this->gitDirectory)) {
            $this->removeDirectory($this->gitDirectory);
        }
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

    public function testChangedCoverageFallsBackWhenBaseReferenceIsUnavailable(): void
    {
        $this->initialiseGitFixture();

        [$status, $output] = $this->runGitPolicy(str_repeat('a', 40), '70');

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

    /** @return array{int, string} */
    private function runGitPolicy(string $baseReference, string $minimum): array
    {
        $process = proc_open(
            [
                PHP_BINARY,
                dirname(__DIR__) . '/scripts/check-changed-coverage.php',
                $this->gitDirectory . '/coverage.xml',
                $baseReference,
                $minimum,
            ],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->gitDirectory
        );
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }

    private function initialiseGitFixture(): void
    {
        $file = $this->gitDirectory . '/src/Controller/Synthetic.php';
        file_put_contents($file, <<<'PHP'
<?php

function synthetic(): void
{
    echo 'before';
}
PHP);
        $this->runGit(['git', 'init']);
        $this->runGit(['git', 'config', 'user.name', 'Synthetic Tester']);
        $this->runGit(['git', 'config', 'user.email', 'synthetic@example.test']);
        $this->runGit(['git', 'config', 'commit.gpgsign', 'false']);
        $this->runGit(['git', 'add', 'src/Controller/Synthetic.php']);
        $this->runGit(['git', 'commit', '-m', 'baseline']);

        file_put_contents($file, <<<'PHP'
<?php

function synthetic(): void
{
    echo 'first';
    echo 'second';
    echo 'third';
}
PHP);
        $this->runGit(['git', 'add', 'src/Controller/Synthetic.php']);
        $this->runGit(['git', 'commit', '-m', 'changes']);

        file_put_contents($this->gitDirectory . '/coverage.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/synthetic/repository/src/Controller/Synthetic.php">
      <line num="5" type="stmt" count="1"/>
      <line num="6" type="stmt" count="0"/>
      <line num="7" type="stmt" count="2"/>
    </file>
  </project>
</coverage>
XML);
    }

    /** @param list<string> $command */
    private function runGit(array $command): void
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->gitDirectory);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $output);
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        self::assertIsArray($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
