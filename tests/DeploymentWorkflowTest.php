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

    public function testNamedWorkflowActionsHaveReviewedImmutableLockEntries(): void
    {
        $lock = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/config/workflow-action-lock.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($lock);

        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            preg_match_all('/^\s*uses:\s*([^@\s]+@(v\d+(?:\.\d+\.\d+)?))\b/m', $this->workflow($workflowName), $matches);
            foreach ($matches[1] as $reference) {
                self::assertArrayHasKey($reference, $lock);
                self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\z/i', (string) $lock[$reference]);
            }
        }
    }

    public function testDependabotMaintainsComposerAndWorkflowDependencies(): void
    {
        $path = dirname(__DIR__) . '/.github/dependabot.yml';
        self::assertFileExists($path);
        $configuration = (string) file_get_contents($path);

        self::assertStringContainsString('package-ecosystem: "composer"', $configuration);
        self::assertStringContainsString('package-ecosystem: "github-actions"', $configuration);
        self::assertSame(2, substr_count($configuration, 'interval: "weekly"'));
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
