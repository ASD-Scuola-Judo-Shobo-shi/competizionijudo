<?php

declare(strict_types=1);

namespace App\Service;

use App\Validation\EventInputValidator;
use RuntimeException;

final class EventUploadStorage
{
    private readonly \Closure $moveUploadedFile;

    public function __construct(private readonly ?string $publicRoot = null, ?\Closure $moveUploadedFile = null)
    {
        $this->moveUploadedFile = $moveUploadedFile
            ?? static fn(string $source, string $destination): bool => move_uploaded_file($source, $destination);
    }

    /** @param array<string, mixed> $upload */
    public function store(array $upload, string $prefix): string
    {
        $extension = EventInputValidator::extension($upload);
        if ($extension === null) {
            throw new RuntimeException('Validated event upload has no supported extension.');
        }

        $directory = $this->uploadDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create event upload directory.');
        }

        $filename = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (!(($this->moveUploadedFile)((string) $upload['tmp_name'], $directory . '/' . $filename))) {
            throw new RuntimeException('Unable to store event upload.');
        }

        return 'uploads/events/' . $filename;
    }

    public function purge(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (!preg_match('#\Auploads/events/([A-Za-z0-9][A-Za-z0-9._-]*)\z#D', $relativePath, $matches)) {
            throw new RuntimeException('Refusing to purge an unmanaged upload path.');
        }

        $filename = $matches[1];
        if ($filename === '.htaccess') {
            throw new RuntimeException('Refusing to purge the upload access-control file.');
        }

        $path = $this->uploadDirectory() . '/' . $filename;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to purge an event upload.');
        }
    }

    /** @param list<string|null> $relativePaths */
    public function purgeMany(array $relativePaths): void
    {
        $failure = null;
        foreach (array_unique($relativePaths) as $relativePath) {
            try {
                $this->purge($relativePath);
            } catch (RuntimeException $exception) {
                $failure ??= $exception;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function uploadDirectory(): string
    {
        return rtrim($this->publicRoot ?? base_path('public'), '/') . '/uploads/events';
    }
}
