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

    public function testReadReturnsOnlySupportedManagedEventUploads(): void
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
        self::assertIsString($contents);
        file_put_contents($this->publicRoot . '/uploads/events/poster_synthetic.png', $contents);

        $upload = (new EventUploadStorage($this->publicRoot))->read(
            'uploads/events/poster_synthetic.png'
        );

        self::assertNotNull($upload);
        self::assertSame($contents, $upload['contents']);
        self::assertSame('image/png', $upload['mime_type']);
        self::assertSame('poster_synthetic.png', $upload['filename']);
    }

    public function testPosterPathRecognitionCoversCurrentAndLegacyGeneratedNames(): void
    {
        self::assertTrue(EventUploadStorage::isPosterPath('uploads/events/poster_synthetic.png'));
        self::assertTrue(EventUploadStorage::isPosterPath('/uploads/events/poster20260621.pdf'));
        self::assertFalse(EventUploadStorage::isPosterPath('uploads/events/info_synthetic.pdf'));
        self::assertFalse(EventUploadStorage::isPosterPath('uploads/events/../poster_synthetic.png'));
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

    public function testUploadDirectoryPolicyDeniesExecutablesAndSandboxesDocuments(): void
    {
        $policy = file_get_contents(dirname(__DIR__) . '/public/uploads/events/.htaccess');
        self::assertIsString($policy);

        self::assertStringContainsString('php[0-9]?', $policy);
        self::assertStringContainsString('phar', $policy);
        self::assertStringContainsString('Require all denied', $policy);
        self::assertStringContainsString('Content-Security-Policy "default-src \'none\'; sandbox"', $policy);
        self::assertStringContainsString('Content-Disposition "attachment"', $policy);
        self::assertStringContainsString('<FilesMatch "(?i)^poster">', $policy);
        self::assertGreaterThanOrEqual(2, substr_count($policy, 'Require all denied'));

        $frontController = file_get_contents(dirname(__DIR__) . '/public/index.php');
        self::assertIsString($frontController);
        self::assertStringContainsString('EventUploadStorage::isPosterPath', $frontController);
    }
}
