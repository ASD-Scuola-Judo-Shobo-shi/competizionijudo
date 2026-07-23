<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class DeploymentWorkflowTest extends TestCase
{
    public function testEveryExternalWorkflowActionIsPinnedToAnImmutableCommit(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            $workflow = $this->workflow($workflowName);

            preg_match_all('/^\s*uses:\s*([^@\s]+)@([^\s#]+)(?:\s+#\s*(\S+))?\s*$/m', $workflow, $matches);

            foreach ($matches[1] as $index => $action) {
                if (str_starts_with($action, './')) {
                    continue;
                }

                self::assertMatchesRegularExpression(
                    '/\A[a-f0-9]{40}\z/i',
                    $matches[2][$index],
                    "Workflow '{$workflowName}' action '{$action}' is not pinned to a full commit SHA."
                );
                self::assertNotSame(
                    '',
                    $matches[3][$index],
                    "Workflow '{$workflowName}' action '{$action}' must retain its reviewed version comment."
                );
            }
        }
    }

    public function testNamedWorkflowActionsRetainTheirReviewedVersionComments(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflowName) {
            preg_match_all('/^\s*uses:\s*([^@\s]+)@[a-f0-9]{40}\s+#\s*(v\d+)\s*$/mi', $this->workflow($workflowName), $matches);
            self::assertNotEmpty($matches[1]);

            foreach ($matches[2] as $version) {
                self::assertMatchesRegularExpression('/\Av\d+\z/', $version);
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

    public function testDeploymentsInvokeBasicAuthServerLocalMigrationsAfterUpload(): void
    {
        $workflow = $this->workflow('deploy.yml');

        self::assertSame(2, substr_count(
            $workflow,
            'run: bash scripts/trigger-server-migrations.sh'
        ));
        self::assertSame(2, substr_count(
            $workflow,
            'MIGRATION_BASIC_AUTH_USER: ${{ vars.ADMIN_USER }}'
        ));
        self::assertStringNotContainsString('MIGRATION_WEBHOOK_SECRET', $workflow);
        self::assertStringContainsString('vars.PRODUCTION_MIGRATION_URL', $workflow);
        self::assertStringContainsString('vars.DEVELOPMENT_MIGRATION_URL', $workflow);
        self::assertStringNotContainsString('migrate_production:', $workflow);
        self::assertStringNotContainsString('migrate_development:', $workflow);
        self::assertStringNotContainsString('MIGRATION_DB_HOST', $workflow);
        self::assertSame(2, substr_count($workflow, 'needs: ci'));
        self::assertStringContainsString('cancel-in-progress: false', $workflow);
    }

    public function testDeploymentsVerifyEveryUploadedArtifactBeforeRunningMigrations(): void
    {
        $workflow = $this->workflow('deploy.yml');

        self::assertSame(5, substr_count($workflow, 'scripts/lftp-upload.sh verify'));
        self::assertSame(2, substr_count($workflow, 'scripts/lftp-upload.sh repair'));
        self::assertSame(2, substr_count($workflow, 'FTP_VERIFY_RETRIES: ${{ vars.FTP_VERIFY_RETRIES || 4 }}'));
        self::assertSame(2, substr_count($workflow, 'FTP_VERIFY_RETRIES must be an integer of at least 4.'));
        self::assertSame(2, substr_count($workflow, 'for attempt in $(seq 0 "$FTP_VERIFY_RETRIES")'));
        self::assertSame(2, substr_count($workflow, 'Verification did not identify files for targeted repair.'));
        self::assertSame(2, substr_count($workflow, 'rm -f "$FTP_MISMATCH_FILE"'));
        self::assertStringContainsString('Verify production application artifact over FTPS', $workflow);
        self::assertStringContainsString('Verify production runtime env over FTPS', $workflow);
        self::assertStringContainsString('Verify root router over FTPS', $workflow);
        self::assertStringContainsString('Verify development application artifact over FTPS', $workflow);
        self::assertStringContainsString('Verify development runtime env over FTPS', $workflow);
    }

    public function testRuntimeArtifactOmitsComposerLockAfterInstallingDependencies(): void
    {
        $root = dirname(__DIR__);
        $builder = (string) file_get_contents($root . '/scripts/build-deploy.sh');
        $verifier = (string) file_get_contents($root . '/scripts/verify-deploy-artifact.sh');

        $installPosition = strpos($builder, 'composer install');
        $removePosition = strpos($builder, 'rm -f "${BUILD_DIR}/composer.lock"');
        self::assertIsInt($installPosition);
        self::assertIsInt($removePosition);
        self::assertGreaterThan($installPosition, $removePosition);
        self::assertStringContainsString("  composer.lock \\\n  dev.env", $verifier);
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
