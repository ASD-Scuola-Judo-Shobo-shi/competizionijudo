<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class FileLogger implements Logger
{
    private const REDACTED = '[redacted]';
    private const MAX_CONTEXT_LENGTH = 200;
    private const SAFE_CONTEXT_KEYS = [
        'method',
        'path',
        'status',
    ];

    public function __construct(private readonly string $path)
    {
    }

    public static function application(): self
    {
        return new self(base_path('var/log/application.log'));
    }

    /** @param array<string, mixed> $context */
    public function error(
        string $event,
        Throwable $exception,
        string $correlationId,
        array $context = []
    ): void {
        $record = json_encode([
            'timestamp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'level' => 'error',
            'correlation_id' => $this->safeCorrelationId($correlationId),
            'event' => $this->safeEvent($event),
            'exception' => [
                'type' => $exception::class,
                'code' => $exception->getCode(),
                'message' => self::REDACTED,
            ],
            'context' => $this->safeContext($context),
        ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        if (!is_string($record)) {
            return;
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            @error_log($record);

            return;
        }

        if (@file_put_contents($this->path, $record . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            @error_log($record);
        }
    }

    private function safeCorrelationId(string $correlationId): string
    {
        return preg_match('/\A[a-f0-9]{16,64}\z/i', $correlationId) === 1
            ? strtolower($correlationId)
            : self::REDACTED;
    }

    private function safeEvent(string $event): string
    {
        return preg_match('/\A[a-z0-9._-]{1,100}\z/i', $event) === 1
            ? strtolower($event)
            : self::REDACTED;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, bool|int|float|string|null>
     */
    private function safeContext(array $context): array
    {
        $safe = [];
        $redacted = 0;
        foreach ($context as $key => $value) {
            if (!in_array($key, self::SAFE_CONTEXT_KEYS, true)) {
                $redacted++;
                $safe['redacted_' . $redacted] = self::REDACTED;

                continue;
            }

            if (is_string($value)) {
                $safe[$key] = mb_substr($value, 0, self::MAX_CONTEXT_LENGTH);
            } elseif (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $safe[$key] = $value;
            } else {
                $safe[$key] = self::REDACTED;
            }
        }

        return $safe;
    }
}
