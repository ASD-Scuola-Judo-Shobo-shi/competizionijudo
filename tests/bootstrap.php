<?php

declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/.phpunit.cache/sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    throw new RuntimeException(sprintf('Unable to create PHPUnit session directory: %s', $sessionPath));
}

if (!is_writable($sessionPath)) {
    throw new RuntimeException(sprintf('PHPUnit session directory is not writable: %s', $sessionPath));
}

if (ini_set('session.save_path', $sessionPath) === false) {
    throw new RuntimeException('Unable to configure PHPUnit session storage.');
}

define('PHPUNIT_RUNNING', true);

require dirname(__DIR__) . '/src/bootstrap.php';
