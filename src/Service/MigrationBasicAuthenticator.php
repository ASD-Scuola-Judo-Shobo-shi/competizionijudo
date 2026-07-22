<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Request;

final class MigrationBasicAuthenticator
{
    public function __construct(private readonly string $administratorUser)
    {
    }

    public function accepts(Request $request): bool
    {
        $providedUser = $request->server('PHP_AUTH_USER');
        $providedPassword = $request->server('PHP_AUTH_PW');

        return $this->administratorUser !== ''
            && is_string($providedUser)
            && is_string($providedPassword)
            && hash_equals($this->administratorUser, $providedUser)
            && hash_equals($this->administratorUser, $providedPassword);
    }
}
