<?php

declare(strict_types=1);

namespace App\Model;

final class Competition
{
    public function __construct(
        public readonly string $name,
        public readonly string $location,
        public readonly string $date
    ) {
    }

    /** @return list<self> */
    public static function upcoming(): array
    {
        return [
            new self('Trofeo Primavera', 'Milano', '2026-04-18'),
            new self('Coppa Regionale', 'Torino', '2026-05-09'),
            new self('Open Nazionale', 'Bologna', '2026-06-14'),
        ];
    }
}
