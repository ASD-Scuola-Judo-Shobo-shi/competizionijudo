<?php

declare(strict_types=1);

$lockPath = dirname(__DIR__) . '/config/workflow-action-lock.json';
$lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($lock)) {
    throw new RuntimeException('Workflow action lock must be an object.');
}

foreach ($lock as $reference => $expected) {
    if (!is_string($reference) || !is_string($expected) || preg_match('/\A[a-f0-9]{40}\z/i', $expected) !== 1) {
        throw new RuntimeException('Workflow action lock contains an invalid entry.');
    }
    [$action, $tag] = explode('@', $reference, 2);
    $output = [];
    $status = 0;
    exec('git ls-remote https://github.com/' . escapeshellarg($action) . ' refs/tags/' . escapeshellarg($tag), $output, $status);
    $actual = $status === 0 ? strtok((string) ($output[0] ?? ''), "\t") : false;
    if (!is_string($actual) || !hash_equals(strtolower($expected), strtolower($actual))) {
        throw new RuntimeException(sprintf('Workflow action drift: %s no longer resolves to its locked commit.', $reference));
    }
}

echo "Workflow action lock verified.\n";
