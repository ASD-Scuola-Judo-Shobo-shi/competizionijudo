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

    public function testDeploymentsEmbedAndVerifyTheExactSelectedRevision(): void
    {
        $workflow = $this->workflow('deploy.yml');

        self::assertStringContainsString('deployment_ref:', $workflow);
        self::assertStringContainsString('ref: ${{ inputs.deployment_ref || github.sha }}', $workflow);
        self::assertStringContainsString('BUILD_REVISION: ${{ steps.revision.outputs.sha }}', $workflow);
        self::assertSame(2, substr_count($workflow, 'bash scripts/check-deployment-health.sh'));
        self::assertSame(4, substr_count($workflow, '${{ needs.build.outputs.revision }}'));
        self::assertStringContainsString('https://www.competizionijudo.it/health', $workflow);
        self::assertStringContainsString('https://dev.competizionijudo.it/health', $workflow);
    }

    public function testStatefulStaleFileRetirementPreservesServerOwnedPaths(): void
    {
        $workflow = $this->workflow('deploy.yml');

        self::assertStringContainsString('state-name: .deploy-state-production.json', $workflow);
        self::assertStringContainsString('state-name: .deploy-state-development.json', $workflow);
        self::assertSame(3, substr_count($workflow, 'dangerous-clean-slate: false'));
        self::assertStringNotContainsString('dangerous-clean-slate: true', $workflow);
        self::assertSame(2, substr_count($workflow, '**/.env' . PHP_EOL));
        self::assertSame(2, substr_count($workflow, '**/.env.dev'));
        self::assertSame(2, substr_count($workflow, 'legacy/**'));
    }

    private function workflow(string $name): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/.github/workflows/' . $name);
        self::assertIsString($contents);

        return $contents;
    }
}
