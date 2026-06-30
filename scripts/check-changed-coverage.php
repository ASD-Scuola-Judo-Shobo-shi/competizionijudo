<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/check-changed-coverage.php <clover.xml> [base-ref] [minimum-percent]\n");
    exit(2);
}

$cloverPath = $argv[1];
$baseReference = $argv[2] ?? 'HEAD^';
$minimum = isset($argv[3]) ? (float) $argv[3] : 70.0;

if (!is_file($cloverPath) || $minimum < 0.0 || $minimum > 100.0) {
    fwrite(STDERR, "Coverage input or threshold is invalid.\n");
    exit(2);
}

$diffFile = getenv('CHANGED_COVERAGE_DIFF_FILE');
if (is_string($diffFile) && $diffFile !== '') {
    $diff = file_get_contents($diffFile);
    if (!is_string($diff)) {
        fwrite(STDERR, "Unable to read the supplied coverage diff.\n");
        exit(2);
    }
} else {
    if (preg_match('/\A0+\z/', $baseReference) === 1) {
        $baseReference = 'HEAD^';
    }
    if (preg_match('/\A[0-9A-Za-z._\/@{}^~:+-]+\z/', $baseReference) !== 1) {
        fwrite(STDERR, "Coverage base reference is invalid.\n");
        exit(2);
    }

    $process = proc_open(
        ['git', 'diff', '--unified=0', '--diff-filter=AM', $baseReference . '...HEAD', '--', 'src'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to start git diff for coverage.\n");
        exit(2);
    }
    $diff = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || !is_string($diff)) {
        fwrite(STDERR, "Unable to calculate changed lines: " . trim((string) $error) . "\n");
        exit(2);
    }
}

/** @var array<string, array<int, true>> $changedLines */
$changedLines = [];
$path = null;
foreach (preg_split('/\R/', $diff) ?: [] as $line) {
    if (preg_match('#^\+\+\+ b/(src/.+\.php)$#', $line, $matches) === 1) {
        $path = $matches[1];
        continue;
    }
    if ($path === null) {
        continue;
    }
    if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $line, $matches) !== 1) {
        continue;
    }

    $start = (int) $matches[1];
    $count = isset($matches[2]) ? (int) $matches[2] : 1;
    for ($lineNumber = $start; $lineNumber < $start + $count; $lineNumber++) {
        $changedLines[$path][$lineNumber] = true;
    }
}

$xml = simplexml_load_file($cloverPath);
if ($xml === false) {
    fwrite(STDERR, "Unable to parse Clover coverage.\n");
    exit(2);
}

$root = str_replace('\\', '/', dirname(__DIR__)) . '/';
$total = 0;
$covered = 0;
foreach ($xml->xpath('//file') ?: [] as $file) {
    $coveragePath = str_replace('\\', '/', (string) $file['name']);
    if (str_starts_with($coveragePath, $root)) {
        $coveragePath = substr($coveragePath, strlen($root));
    } elseif (($sourcePosition = strpos($coveragePath, '/src/')) !== false) {
        $coveragePath = substr($coveragePath, $sourcePosition + 1);
    }
    if (!isset($changedLines[$coveragePath])) {
        continue;
    }

    foreach ($file->line as $coverageLine) {
        if ((string) $coverageLine['type'] !== 'stmt') {
            continue;
        }
        $lineNumber = (int) $coverageLine['num'];
        if (!isset($changedLines[$coveragePath][$lineNumber])) {
            continue;
        }
        $total++;
        if ((int) $coverageLine['count'] > 0) {
            $covered++;
        }
    }
}

if ($total === 0) {
    echo "Changed executable line coverage: no changed executable source lines.\n";
    exit(0);
}

$percentage = ($covered / $total) * 100;
echo sprintf(
    "Changed executable line coverage: %.1f%% (%d/%d; required %.1f%%).\n",
    $percentage,
    $covered,
    $total,
    $minimum
);
exit($percentage + 0.00001 >= $minimum ? 0 : 1);
