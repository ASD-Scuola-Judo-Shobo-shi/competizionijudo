<?php

declare(strict_types=1);

namespace App\Core;

final readonly class SessionPrincipal
{
    private function __construct(public string $type, public ?int $clubId = null)
    {
    }

    public static function administrator(): self
    {
        return new self('administrator');
    }

    public static function club(int $clubId): self
    {
        if ($clubId < 1) {
            throw new \InvalidArgumentException('A club principal requires a positive identifier.');
        }

        return new self('club', $clubId);
    }
}
