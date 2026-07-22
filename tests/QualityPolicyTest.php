<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class QualityPolicyTest extends TestCase
{
    public function testComposerCiRunsTheCompleteGateThroughParallelQualityAndArtifactLanes(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('bash scripts/run-ci.sh', $composer['scripts']['ci']);
        self::assertSame(
            [
                '@dependencies:verify',
                '@workflow:check',
                '@test:migrations',
                '@quality',
                '@test:coverage:changed',
            ],
            $composer['scripts']['ci:quality']
        );
        self::assertSame(['@quality', '@test'], $composer['scripts']['check']);
        self::assertSame(
            ['@metadata', '@syntax', '@cs', '@analyse', '@security:audit'],
            $composer['scripts']['quality']
        );
        self::assertSame('bash scripts/run-changed-coverage.sh', $composer['scripts']['test:coverage:changed']);
        self::assertSame(
            'git config core.hooksPath scripts/git-hooks',
            $composer['scripts']['hooks:install']
        );
        self::assertSame(
            'composer audit --locked --abandoned=fail',
            $composer['scripts']['security:audit']
        );
        self::assertSame(
            'composer install --dry-run --prefer-dist --no-interaction --no-progress',
            $composer['scripts']['dependencies:verify']
        );
        self::assertSame('bash scripts/deploy-preflight.sh', $composer['scripts']['deploy:preflight']);
    }

    public function testCiEnforcesChangedSourceCoverageAndDeployReusesCi(): void
    {
        $ci = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/ci.yml');
        $composer = (string) file_get_contents(dirname(__DIR__) . '/composer.json');

        $coverage = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-changed-coverage.sh');
        $localCi = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-ci.sh');

        self::assertStringContainsString('"@test:coverage:changed"', $composer);
        self::assertStringContainsString('--coverage-clover build/coverage.xml', $coverage);
        self::assertStringContainsString('php scripts/check-changed-coverage.php build/coverage.xml', $coverage);
        self::assertStringContainsString('composer ci:quality', $localCi);
        self::assertStringContainsString('composer deploy:preflight', $localCi);
        self::assertStringContainsString('run: composer ci:quality', $ci);
        self::assertStringContainsString('fetch-depth: 0', $ci);
        self::assertStringContainsString(
            'COMPOSER_CACHE_DIR: ${{ github.workspace }}/.cache/composer',
            $ci
        );
        self::assertSame(2, substr_count($ci, 'path: ${{ env.COMPOSER_CACHE_DIR }}/files'));
        self::assertStringNotContainsString('composer install --no-dev --', $ci);
        self::assertStringContainsString('run: bash scripts/deploy-preflight.sh', $ci);
        self::assertStringNotContainsString('needs: quality', $ci);

        $deploy = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy.yml');
        self::assertStringContainsString('uses: ./.github/workflows/ci.yml', $deploy);
        self::assertSame(1, substr_count($deploy, 'run: bash scripts/stage-root-router.sh build/root-router'));
    }

    public function testCiRunsOnlyThePhp84QualityMatrixEntry(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/ci.yml');

        self::assertStringContainsString('php-version: "8.4"', $workflow);
        self::assertStringNotContainsString('matrix:', $workflow);
        self::assertStringContainsString(
            "name: Quality checks\n    runs-on: ubuntu-latest",
            $workflow
        );
    }

    public function testPhpcsCoversApplicationPhpBoundaries(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__) . '/phpcs.xml');

        foreach (['src', 'tests', 'public', 'config', 'routes', 'scripts', 'views', 'lang'] as $directory) {
            self::assertStringContainsString('<file>' . $directory . '</file>', $rules);
        }
    }

    public function testPhpstanAndSyntaxCoverEveryApplicationPhpBoundary(): void
    {
        $phpstan = (string) file_get_contents(dirname(__DIR__) . '/phpstan.neon');
        foreach (
            [
                'src/Core',
                'src/Controller',
                'src/Model',
                'src/Service',
                'src/Security',
                'src/Presentation',
                'src/Validation',
                'src/bootstrap.php',
                'src/helpers.php',
                'public',
                'config',
                'routes',
                'scripts',
                'views',
                'lang',
            ] as $path
        ) {
            self::assertStringContainsString('- ' . $path, $phpstan);
        }

        $composer = (string) file_get_contents(dirname(__DIR__) . '/composer.json');
        self::assertStringContainsString(
            "find config lang public routes scripts src tests views -name '*.php'",
            $composer
        );
    }

    public function testCiPinsAndLintsWorkflowDefinitions(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/ci.yml');
        $workflowCheck = (string) file_get_contents(dirname(__DIR__) . '/scripts/check-workflows.sh');

        self::assertStringContainsString(
            'uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5 # v4',
            $workflow
        );
        self::assertStringContainsString(
            'uses: shivammathur/setup-php@b604ade2a87db23f8871b7182e69ec5e75effb45 # v2',
            $workflow
        );
        self::assertStringContainsString('ACTIONLINT_VERSION="1.7.7"', $workflowCheck);
        self::assertStringContainsString('go install', $workflowCheck);
        self::assertStringContainsString('"$actionlint_path"', $workflowCheck);
    }

    public function testPrePushDelegatesToTheExecutableGithubActionsQualityGate(): void
    {
        $hook = (string) file_get_contents(dirname(__DIR__) . '/scripts/git-hooks/pre-push');

        self::assertStringContainsString('git rev-parse --show-toplevel', $hook);
        self::assertStringContainsString('vendor/autoload.php', $hook);
        self::assertStringContainsString('composer ci', $hook);
        self::assertStringNotContainsString('composer install --', $hook);
    }

    public function testPreCommitChecksOnlyRelevantStagedFilesAcrossQualityBoundaries(): void
    {
        $hook = (string) file_get_contents(dirname(__DIR__) . '/scripts/git-hooks/pre-commit');

        self::assertStringContainsString('git rev-parse --show-toplevel', $hook);
        self::assertStringContainsString("grep -Fx 'composer.json'", $hook);
        self::assertStringContainsString(
            '^(config|lang|public|routes|scripts|src|tests|views)/.*\\.php$',
            $hook
        );
        self::assertStringContainsString('if [ -z "$staged_files" ]; then', $hook);
    }

    public function testTemplatesContainNoEditorInstructionArtifacts(): void
    {
        $templates = '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__) . '/views')
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $templates .= (string) file_get_contents($file->getPathname());
            }
        }

        self::assertStringNotContainsString('</parameter>', $templates);
        self::assertStringNotContainsString('</write_to_file>', $templates);
    }

    public function testDeployArtifactSmokeScriptRetriesPortSelectionAndStopsEachServer(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/test-deploy-artifact.sh');

        self::assertStringContainsString('stop_server()', $script);
        self::assertStringContainsString('for _attempt in {1..10}; do', $script);
        self::assertStringContainsString(
            'grep -Fq "Failed to listen on 127.0.0.1:${port}"',
            $script
        );
        self::assertStringContainsString('port="$((port + 1))"', $script);
        self::assertSame(2, substr_count($script, 'stop_server "$server_pid"'));
    }

    public function testMigrationSmokeScriptRetriesDatabaseConnectionsForServiceStartupRaces(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/test-migrations.php');

        self::assertStringContainsString('$server = connectWithRetry(', $script);
        self::assertStringContainsString('return connectWithRetry(', $script);
        self::assertStringContainsString('function connectWithRetry(', $script);
        self::assertStringContainsString(
            "getenv('MIGRATION_TEST_CONNECT_ATTEMPTS') ?: 10",
            $script
        );
        self::assertStringContainsString(
            "getenv('MIGRATION_TEST_CONNECT_DELAY_MICROS') ?: 500000",
            $script
        );
        self::assertStringContainsString('usleep($sleepMicros);', $script);
    }

    public function testBuildArtifactScriptCreatesRuntimeDirectoriesAndLogPlaceholder(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__) . '/scripts/build-deploy.sh');

        self::assertStringContainsString('mkdir -p', $script);
        self::assertStringContainsString('"${BUILD_DIR}/public/uploads/events"', $script);
        self::assertStringContainsString('"${BUILD_DIR}/var/log"', $script);
        self::assertStringContainsString('touch', $script);
        self::assertStringContainsString('"${BUILD_DIR}/var/log/.gitkeep"', $script);
        self::assertStringContainsString('DEPLOYMENT_TRANSFER_PROTOCOL', $script);
        self::assertStringContainsString('scripts/generate-deploy-manifest.sh', $script);
    }

    public function testSharedDeploymentPreflightCoversTheArtifactAndRootRouter(): void
    {
        $preflight = (string) file_get_contents(dirname(__DIR__) . '/scripts/deploy-preflight.sh');
        $router = (string) file_get_contents(dirname(__DIR__) . '/scripts/stage-root-router.sh');

        self::assertStringContainsString('scripts/build-deploy.sh', $preflight);
        self::assertStringContainsString('scripts/test-deploy-artifact.sh', $preflight);
        self::assertStringContainsString('scripts/stage-root-router.sh', $preflight);
        self::assertStringContainsString('rm router.sha256', $router);
        self::assertStringContainsString('Root router staging directory must contain only', $router);
    }
}
