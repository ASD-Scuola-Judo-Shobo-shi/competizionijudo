<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ApacheArtifactRoutingTest extends TestCase
{
    public function testArtifactRoutesMigrationAndPublicRequestsWithoutTheRootRouter(): void
    {
        $apache = '/usr/sbin/apache2';
        $modules = '/usr/lib/apache2/modules';
        if (!is_executable($apache) || !is_file($modules . '/mod_mpm_event.so')) {
            self::markTestSkipped('Apache 2 and its event MPM are required for the rewrite regression test.');
        }
        $directory = sys_get_temp_dir() . '/competizionijudo-apache-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $process = null;
        try {
            $root = $directory . '/www';
            self::assertTrue(mkdir($root));
            foreach (['prod', 'dev'] as $environment) {
                $this->createArtifactFixture($root . '/' . $environment);
            }
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            self::assertIsString($address);
            fclose($socket);
            $configuration = <<<APACHE
ServerRoot "{$directory}"
DefaultRuntimeDir "{$directory}"
PidFile "{$directory}/httpd.pid"
Listen {$address}
ServerName localhost
LoadModule mpm_event_module {$modules}/mod_mpm_event.so
LoadModule authz_core_module {$modules}/mod_authz_core.so
LoadModule rewrite_module {$modules}/mod_rewrite.so
LoadModule dir_module {$modules}/mod_dir.so
ErrorLog "{$directory}/error.log"
DocumentRoot "{$root}"
DirectoryIndex index.php
<Directory "{$root}">
    AllowOverride All
    Require all granted
    Options +FollowSymLinks -Indexes
</Directory>
APACHE;
            file_put_contents($directory . '/httpd.conf', $configuration);
            $process = proc_open(
                [$apache, '-f', $directory . '/httpd.conf', '-DFOREGROUND'],
                [1 => ['file', $directory . '/process.log', 'w'], 2 => ['file', $directory . '/process.log', 'a']],
                $pipes
            );
            self::assertIsResource($process);
            for ($attempt = 0; $attempt < 50; $attempt++) {
                $connection = @stream_socket_client('tcp://' . $address, timeout: 0.1);
                if (is_resource($connection)) {
                    fclose($connection);
                    break;
                }
                usleep(100_000);
            }
            foreach (['/prod', '/dev', ''] as $prefix) {
                if ($prefix === '') {
                    $this->createArtifactFixture($root);
                }
                foreach (['/migrations/', '/health', '/public/index.php'] as $path) {
                    $this->assertResponse($address, $prefix . $path, 200, 'synthetic front controller');
                }
                $this->assertResponse($address, $prefix . '/migrations', 301);
                $this->assertResponse($address, $prefix . '/assets/synthetic.txt', 200, 'synthetic asset');
                $this->assertResponse($address, $prefix . '/migrations/synthetic.sql', 403);
                $this->assertResponse($address, $prefix . '/src/synthetic.php', 403);
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($directory);
        }
    }

    private function createArtifactFixture(string $directory): void
    {
        mkdir($directory . '/public/assets', 0755, true);
        mkdir($directory . '/migrations');
        mkdir($directory . '/src');
        copy(dirname(__DIR__) . '/.htaccess', $directory . '/.htaccess');
        copy(dirname(__DIR__) . '/public/.htaccess', $directory . '/public/.htaccess');
        file_put_contents($directory . '/public/index.php', 'synthetic front controller');
        file_put_contents($directory . '/public/assets/synthetic.txt', 'synthetic asset');
        file_put_contents($directory . '/migrations/synthetic.sql', 'synthetic private SQL');
        file_put_contents($directory . '/src/synthetic.php', 'synthetic private source');
    }

    private function assertResponse(string $address, string $path, int $status, ?string $body = null): void
    {
        $response = file_get_contents('http://' . $address . $path, false, stream_context_create([
            'http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5],
        ]));
        self::assertSame($status, (int) substr($http_response_header[0] ?? '', 9, 3), $path);
        if ($body !== null) {
            self::assertSame($body, $response, $path);
        }
    }
}
