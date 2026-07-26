<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Request;

final class MigrationTokenAuthenticator
{
    public function __construct(private readonly string $token)
    {
    }

    public function accepts(Request $request): bool
    {
        $provided = $request->server('HTTP_X_MIGRATION_TOKEN');

        return $this->token !== ''
            && is_string($provided)
            && hash_equals($this->token, $provided);
    }
}
