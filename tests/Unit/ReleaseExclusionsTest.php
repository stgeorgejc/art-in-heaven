<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies that dev-only files and directories are excluded from
 * the release zip archives and rsync deploy in release.yml.
 *
 * Catches drift between the three deployment targets (source zip,
 * pre-packaged zip, rsync) so exclusions stay in sync.
 */
class ReleaseExclusionsTest extends TestCase
{
    private string $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        $path = AIH_PLUGIN_DIR . '.github/workflows/release.yml';
        $this->assertFileExists($path, 'release.yml must exist');
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'Failed to read release.yml');
        $this->workflow = $contents;
    }

    /**
     * Paths that must NEVER ship in any release artifact or deploy.
     * Each entry is the exclusion pattern as it appears in zip -x or rsync --exclude.
     */
    private function devOnlyPaths(): array
    {
        return [
            'tests/*'          => 'tests/',
            'load-tests/*'     => 'load-tests/',
            '.github/*'        => '.github/',
            '.claude/*'        => '.claude/',
            '.githooks/*'      => '.githooks/',
            'phpunit.xml'      => 'phpunit.xml',
            'phpstan.neon'     => 'phpstan.neon',
            'composer.json'    => 'composer.json',
            'composer.lock'    => 'composer.lock',
            '.mcp.json'        => '.mcp.json',
            '.playwright-mcp/*' => '.playwright-mcp/',
        ];
    }

    /**
     * Extract the zip -x exclusion block for a given step name.
     */
    private function extractZipExclusions(string $stepName): string
    {
        $pattern = '/name:\s*' . preg_quote($stepName, '/') . '.*?zip\s+-r\s+\S+\.zip\s+\.\s+(.*?)(?=\n\n|\n\s+-\s+name:|\z)/s';
        preg_match($pattern, $this->workflow, $m);
        return $m[1] ?? '';
    }

    /**
     * Extract the rsync --exclude lines from the deploy step.
     */
    private function extractRsyncExclusions(): string
    {
        preg_match('/name:\s*Deploy via rsync.*?rsync(.*?)(?=\n\n|\n\s+-\s+name:|\z)/s', $this->workflow, $m);
        return $m[1] ?? '';
    }

    // ── Source zip excludes dev-only paths ──

    public function testSourceZipExcludesDevPaths(): void
    {
        $block = $this->extractZipExclusions('Build source release');
        $this->assertNotEmpty($block, 'Source zip exclusion block must exist');

        foreach ($this->devOnlyPaths() as $zipPattern => $_) {
            $this->assertStringContainsString(
                "'{$zipPattern}'",
                $block,
                "Source zip must exclude {$zipPattern}"
            );
        }
    }

    public function testSourceZipExcludesVendor(): void
    {
        $block = $this->extractZipExclusions('Build source release');
        $this->assertStringContainsString("'vendor/*'", $block, 'Source zip must exclude vendor');
    }

    // ── Pre-packaged zip excludes dev-only paths ──

    public function testPackagedZipExcludesDevPaths(): void
    {
        $block = $this->extractZipExclusions('Build pre-packaged release');
        $this->assertNotEmpty($block, 'Packaged zip exclusion block must exist');

        foreach ($this->devOnlyPaths() as $zipPattern => $_) {
            $this->assertStringContainsString(
                "'{$zipPattern}'",
                $block,
                "Packaged zip must exclude {$zipPattern}"
            );
        }
    }

    public function testPackagedZipIncludesVendor(): void
    {
        $block = $this->extractZipExclusions('Build pre-packaged release');
        $this->assertStringNotContainsString("'vendor/*'", $block, 'Packaged zip must NOT exclude vendor');
    }

    // ── Rsync deploy excludes dev-only paths ──

    public function testRsyncExcludesDevPaths(): void
    {
        $block = $this->extractRsyncExclusions();
        $this->assertNotEmpty($block, 'Rsync exclusion block must exist');

        foreach ($this->devOnlyPaths() as $_ => $rsyncPattern) {
            $this->assertStringContainsString(
                $rsyncPattern,
                $block,
                "Rsync deploy must exclude {$rsyncPattern}"
            );
        }
    }

    // ── Production files are NOT excluded ──

    public function testProductionFilesNotExcluded(): void
    {
        $productionPaths = [
            'includes/',
            'templates/',
            'assets/',
            'admin/',
            'art-in-heaven.php',
            'uninstall.php',
        ];

        $sourceBlock = $this->extractZipExclusions('Build source release');
        $packagedBlock = $this->extractZipExclusions('Build pre-packaged release');

        foreach ($productionPaths as $path) {
            $this->assertStringNotContainsString(
                $path,
                $sourceBlock,
                "Source zip must NOT exclude production path: {$path}"
            );
            $this->assertStringNotContainsString(
                $path,
                $packagedBlock,
                "Packaged zip must NOT exclude production path: {$path}"
            );
        }
    }
}
