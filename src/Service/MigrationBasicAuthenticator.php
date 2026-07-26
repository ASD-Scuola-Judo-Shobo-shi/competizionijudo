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
        [$providedUser, $providedPassword] = $this->credentials($request);

        return $this->administratorUser !== ''
            && is_string($providedUser)
            && is_string($providedPassword)
            && hash_equals($this->administratorUser, $providedUser)
            && hash_equals($this->administratorUser, $providedPassword);
    }

    /** @return array{string|null, string|null} */
    private function credentials(Request $request): array
    {
        $providedUser = $request->server('PHP_AUTH_USER');
        $providedPassword = $request->server('PHP_AUTH_PW');
        if (is_string($providedUser) && is_string($providedPassword)) {
            return [$providedUser, $providedPassword];
        }

        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $header) {
            $authorization = $request->server($header);
            if (!is_string($authorization)) {
                continue;
            }

            if (preg_match('/\ABasic[ \t]+([A-Za-z0-9+\/]+=*)\z/i', trim($authorization), $matches) !== 1) {
                continue;
            }

            $decoded = base64_decode($matches[1], true);
            if (!is_string($decoded) || !str_contains($decoded, ':')) {
                continue;
            }

            [$user, $password] = explode(':', $decoded, 2);

            return [$user, $password];
        }

        return [null, null];
    }
}
