<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class QualityPolicyTest extends TestCase
{
    public function testComposerCiIncludesSchemaQualityArtifactAndBootGates(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame(
            [
                '@test:migrations',
                '@check',
                'bash scripts/build-deploy.sh',
                'bash scripts/test-deploy-artifact.sh build/deploy',
            ],
            $composer['scripts']['ci']
        );
        self::assertSame(
            'git config core.hooksPath scripts/git-hooks',
            $composer['scripts']['hooks:install']
        );
        self::assertSame(
            'composer audit --locked --abandoned=fail',
            $composer['scripts']['security:audit']
        );
    }

    public function testCiEnforcesChangedSourceCoverageAndDeployReusesCi(): void
    {
        $ci = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/ci.yml');
        self::assertStringContainsString('--coverage-clover build/coverage.xml', $ci);
        self::assertStringContainsString(
            'php scripts/check-changed-coverage.php build/coverage.xml',
            $ci
        );
        self::assertStringContainsString(' 70', $ci);
        self::assertStringContainsString('fetch-depth: 0', $ci);

        $deploy = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/deploy.yml');
        self::assertStringContainsString('uses: ./.github/workflows/ci.yml', $deploy);
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

    public function testPhpcsCoversNonTemplatePhpBoundaries(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__) . '/phpcs.xml');

        foreach (['src', 'tests', 'public', 'config', 'routes', 'scripts'] as $directory) {
            self::assertStringContainsString('<file>' . $directory . '</file>', $rules);
        }
        self::assertStringNotContainsString('<file>views</file>', $rules);
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
    }
}
