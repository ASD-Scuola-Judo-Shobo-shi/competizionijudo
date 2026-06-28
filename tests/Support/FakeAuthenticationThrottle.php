<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Security\AuthenticationThrottle;

final class FakeAuthenticationThrottle implements AuthenticationThrottle
{
    /** @var array<string, int> */
    public array $attempts = [];

    /** @var list<array{scope: string, account: string, network: string}> */
    public array $recorded = [];

    public function __construct(private readonly int $maximumAttempts = 5)
    {
    }

    public function isBlocked(string $scope, string $account, string $networkSignal): bool
    {
        return ($this->attempts[$this->key($scope, $account, $networkSignal)] ?? 0)
            >= $this->maximumAttempts;
    }

    public function recordAttempt(string $scope, string $account, string $networkSignal): void
    {
        $key = $this->key($scope, $account, $networkSignal);
        $this->attempts[$key] = ($this->attempts[$key] ?? 0) + 1;
        $this->recorded[] = [
            'scope' => $scope,
            'account' => $account,
            'network' => $networkSignal,
        ];
    }

    public function clear(string $scope, string $account, string $networkSignal): void
    {
        unset($this->attempts[$this->key($scope, $account, $networkSignal)]);
    }

    private function key(string $scope, string $account, string $networkSignal): string
    {
        return implode("\0", [
            strtolower(trim($scope)),
            strtolower(trim($account)),
            trim($networkSignal),
        ]);
    }
}
