<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Database;
use Closure;
use RuntimeException;

final class DeploymentHealth
{
    /** @var Closure(): bool */
    private readonly Closure $databaseProbe;
    private readonly string $revisionPath;

    /** @param (Closure(): bool)|null $databaseProbe */
    public function __construct(?Closure $databaseProbe = null, ?string $revisionPath = null)
    {
        $this->databaseProbe = $databaseProbe ?? static function (): bool {
            $statement = Database::connection()->query('SELECT 1');

            return $statement !== false && (int) $statement->fetchColumn() === 1;
        };
        $this->revisionPath = $revisionPath ?? base_path('REVISION');
    }

    public function verify(): string
    {
        $revision = is_file($this->revisionPath)
            ? strtolower(trim((string) file_get_contents($this->revisionPath)))
            : '';
        if (preg_match('/\A[a-f0-9]{40}\z/', $revision) !== 1) {
            throw new RuntimeException('Deployment revision is missing or invalid.');
        }
        if (!(($this->databaseProbe)())) {
            throw new RuntimeException('Deployment database probe failed.');
        }

        return $revision;
    }
}
