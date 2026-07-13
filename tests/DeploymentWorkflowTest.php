<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentWorkflowTest extends TestCase
{
    /**
     * Safety guardrail: external marketplace actions must use immutable commits.
     */
    public function testEveryExternalWorkflowActionUsesImmutableCommitPins(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            $workflow = $this->workflow($workflowName);

            // Extracts the immutable revision following the '@' symbol for external actions.
            preg_match_all('/^\s*uses:\s*[^@\s]+@([^\s#]+)/m', $workflow, $matches);

            if ($matches[1] !== []) {
                foreach ($matches[1] as $revision) {
                    self::assertMatchesRegularExpression(
                        '/\A[a-f0-9]{40}\z/i',
                        $revision,
                        "Workflow '{$workflowName}' contains a mutable action reference: '@{$revision}'."
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
