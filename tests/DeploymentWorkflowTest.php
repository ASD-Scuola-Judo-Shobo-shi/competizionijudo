<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentWorkflowTest extends TestCase
{
    public function testQualityAndArtifactChecksRunBeforeDeployableArtifactsAreUploaded(): void
    {
        $workflow = $this->workflow('deploy.yml');
        $uploadPosition = strpos($workflow, '- name: Upload application artifact');
        $requiredGates = [
            'run: composer test:migrations',
            'run: composer check',
            'run: bash scripts/build-deploy.sh',
            'run: bash scripts/test-deploy-artifact.sh build/deploy',
        ];

        self::assertIsInt($uploadPosition);
        foreach ($requiredGates as $requiredGate) {
            $gatePosition = strpos($workflow, $requiredGate);
            self::assertIsInt($gatePosition, 'Missing deployment gate: ' . $requiredGate);
            self::assertLessThan($uploadPosition, $gatePosition, 'Deployment gate runs after artifact upload.');
        }
    }

    public function testDeployJobsRequireSuccessfulBuildAndExactPushBranch(): void
    {
        $workflow = $this->workflow('deploy.yml');

        self::assertDoesNotMatchRegularExpression('/^\s+pull_request:/m', $workflow);
        self::assertSame(2, substr_count($workflow, 'needs: build'));
        self::assertStringContainsString(
            "github.ref == 'refs/heads/main' && needs.build.result == 'success'",
            $workflow
        );
        self::assertStringContainsString(
            "github.ref == 'refs/heads/dev' && needs.build.result == 'success'",
            $workflow
        );
    }

    public function testEveryWorkflowActionUsesAnImmutableCommitSha(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            $workflow = $this->workflow($workflowName);
            preg_match_all('/^\s*uses:\s*[^@\s]+@([^\s#]+)/m', $workflow, $matches);

            self::assertNotEmpty($matches[1], 'No actions found in ' . $workflowName);
            foreach ($matches[1] as $revision) {
                self::assertMatchesRegularExpression('/\A[a-f0-9]{40}\z/', $revision);
            }
        }
    }

    private function workflow(string $name): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/.github/workflows/' . $name);
        self::assertIsString($contents);

        return $contents;
    }
}
