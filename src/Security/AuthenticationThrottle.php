<?php

declare(strict_types=1);

namespace App\Security;

interface AuthenticationThrottle
{
    public function isBlocked(string $scope, string $account, string $networkSignal): bool;

    public function recordAttempt(string $scope, string $account, string $networkSignal): void;

    public function clear(string $scope, string $account, string $networkSignal): void;
}
