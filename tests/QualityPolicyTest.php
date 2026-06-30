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
            'composer audit --locked --abandoned=fail',
            $composer['scripts']['security:audit']
        );
    }

    public function testCiAndDeploymentEnforceChangedSourceCoverage(): void
    {
        foreach (['ci.yml', 'deploy.yml'] as $workflow) {
            $contents = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/' . $workflow);

            self::assertStringContainsString('--coverage-clover build/coverage.xml', $contents);
            self::assertStringContainsString(
                'php scripts/check-changed-coverage.php build/coverage.xml',
                $contents
            );
            self::assertStringContainsString(' 70', $contents);
            self::assertStringContainsString('fetch-depth: 0', $contents);
        }
    }

    public function testPhpcsCoversNonTemplatePhpBoundaries(): void
    {
        $rules = (string) file_get_contents(dirname(__DIR__) . '/phpcs.xml');

        foreach (['src', 'tests', 'public', 'config', 'routes', 'scripts'] as $directory) {
            self::assertStringContainsString('<file>' . $directory . '</file>', $rules);
        }
        self::assertStringNotContainsString('<file>views</file>', $rules);
    }
}
