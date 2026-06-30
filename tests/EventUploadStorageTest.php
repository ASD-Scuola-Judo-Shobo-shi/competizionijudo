<?php

declare(strict_types=1);

namespace Tests;

use App\Service\EventUploadStorage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventUploadStorageTest extends TestCase
{
    private string $publicRoot;

    protected function setUp(): void
    {
        $this->publicRoot = sys_get_temp_dir() . '/competizionijudo-uploads-' . bin2hex(random_bytes(8));
        mkdir($this->publicRoot . '/uploads/events', 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->publicRoot . '/uploads/events/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->publicRoot . '/uploads/events');
        rmdir($this->publicRoot . '/uploads');
        rmdir($this->publicRoot);
    }

    public function testPurgeDeletesOnlyManagedEventUploads(): void
    {
        $upload = $this->publicRoot . '/uploads/events/poster_synthetic.pdf';
        file_put_contents($upload, 'synthetic document');

        (new EventUploadStorage($this->publicRoot))->purge('uploads/events/poster_synthetic.pdf');

        self::assertFileDoesNotExist($upload);
    }

    public function testPurgeRejectsTraversalAndAccessControlFile(): void
    {
        $storage = new EventUploadStorage($this->publicRoot);

        foreach (['uploads/events/../secret', 'uploads/events/.htaccess'] as $path) {
            try {
                $storage->purge($path);
                self::fail('Unsafe upload path was accepted.');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
    }

    public function testPurgeManyAttemptsEveryFileBeforeReportingFailure(): void
    {
        $upload = $this->publicRoot . '/uploads/events/info_synthetic.pdf';
        file_put_contents($upload, 'synthetic document');

        try {
            (new EventUploadStorage($this->publicRoot))->purgeMany([
                'uploads/events/../secret',
                'uploads/events/info_synthetic.pdf',
            ]);
            self::fail('Unsafe upload path was accepted.');
        } catch (RuntimeException) {
            self::assertFileDoesNotExist($upload);
        }
    }
}
