<?php

declare(strict_types=1);

namespace App\Service;

use App\Validation\EventInputValidator;
use finfo;
use RuntimeException;

final class EventUploadStorage
{
    private const READABLE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

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

        $path = $this->managedFile($relativePath)['path'];
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to purge an event upload.');
        }
    }

    /** @return array{contents:string, mime_type:string, filename:string}|null */
    public function read(string $relativePath): ?array
    {
        $managedFile = $this->managedFile($relativePath);
        $path = $managedFile['path'];
        if (!is_file($path)) {
            return null;
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mimeType) || !in_array($mimeType, self::READABLE_MIME_TYPES, true)) {
            throw new RuntimeException('Refusing to read an unsupported event upload.');
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read an event upload.');
        }

        return [
            'contents' => $contents,
            'mime_type' => $mimeType,
            'filename' => $managedFile['filename'],
        ];
    }

    public static function isPosterPath(string $relativePath): bool
    {
        return preg_match(
            '#\Auploads/events/poster[A-Za-z0-9._-]*\z#Di',
            ltrim($relativePath, '/')
        ) === 1;
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

    /** @return array{filename:string, path:string} */
    private function managedFile(string $relativePath): array
    {
        if (!preg_match('#\Auploads/events/([A-Za-z0-9][A-Za-z0-9._-]*)\z#D', $relativePath, $matches)) {
            throw new RuntimeException('Refusing to access an unmanaged upload path.');
        }

        return [
            'filename' => $matches[1],
            'path' => $this->uploadDirectory() . '/' . $matches[1],
        ];
    }
}
