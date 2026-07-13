<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentWorkflowTest extends TestCase
{
    /**
     * Safety guardrail: external marketplace actions use stable major versions.
     */
    public function testEveryExternalWorkflowActionUsesSafeVersionTags(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            $workflow = $this->workflow($workflowName);

            // Extracts the version tag following the '@' symbol for external actions.
            preg_match_all('/^\s*uses:\s*[^@\s]+@([^\s#]+)/m', $workflow, $matches);

            if ($matches[1] !== []) {
                foreach ($matches[1] as $tag) {
                    self::assertMatchesRegularExpression(
                        '/\Av\d+(?:\.\d+\.\d+)?\z/',
                        $tag,
                        "Workflow '{$workflowName}' contains an unpinned or unstable action tag: '@{$tag}'."
                    );
                }
            }
        }
    }

    private function workflow(string $name): string
    {
        $path = dirname(__DIR__) . '/.github/workflows/' . $name;
        self::assertFileExists($path, "Workflow file '.github/workflows/{$name}' is missing.");

        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
