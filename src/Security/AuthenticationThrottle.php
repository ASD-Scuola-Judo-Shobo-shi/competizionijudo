<?php

declare(strict_types=1);

namespace App\Security;

interface AuthenticationThrottle
{
    /**
     * Atomically reserve an authentication attempt.
     *
     * Returns false without reserving an attempt when any applicable limit is
     * already blocked.
     */
    public function consume(string $scope, string $account, string $networkSignal): bool;

    public function clear(string $scope, string $account, string $networkSignal): void;
}
