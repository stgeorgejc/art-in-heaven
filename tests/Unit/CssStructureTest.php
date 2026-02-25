<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the CSS maintains mobile-first responsive structure.
 *
 * Guards against regressions where mobile base styles or responsive
 * breakpoint ordering are accidentally broken.
 */
class CssStructureTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $path = AIH_PLUGIN_DIR . 'assets/css/elegant-theme.css';
        $this->assertFileExists($path, 'elegant-theme.css must exist');
        $this->css = file_get_contents($path);
    }

    // ── Mobile-first base styles ──

    public function testMobileBaseMainPaddingIsTight(): void
    {
        // Mobile base .aih-main padding should be ≤ 12px vertical
        $this->assertMatchesRegularExpression(
            '/\.aih-page\s+\.aih-main\s*\{[^}]*padding:\s*(\d+)px\s+(\d+)px/s',
            $this->css
        );
        preg_match(
            '/\.aih-page\s+\.aih-main\s*\{[^}]*padding:\s*(\d+)px\s+(\d+)px/s',
            $this->css,
            $m
        );
        $this->assertLessThanOrEqual(12, (int) $m[1], 'Mobile main top padding should be ≤ 12px');
    }

    public function testMobileBaseGridGapIsCompact(): void
    {
        // Gallery grid base gap should be ≤ 10px
        preg_match(
            '/\.aih-gallery-grid\s*\{[^}]*gap:\s*(\d+)px/s',
            $this->css,
            $m
        );
        $this->assertNotEmpty($m, 'Gallery grid must define a gap');
        $this->assertLessThanOrEqual(10, (int) $m[1], 'Mobile gallery grid gap should be ≤ 10px');
    }

    public function testMobileHeaderPaddingIsCompact(): void
    {
        preg_match(
            '/\.aih-page\s+\.aih-header-inner\s*\{[^}]*padding:\s*(\d+)px\s+(\d+)px/s',
            $this->css,
            $m
        );
        $this->assertNotEmpty($m, 'Header inner must define padding');
        $this->assertLessThanOrEqual(8, (int) $m[1], 'Mobile header vertical padding should be ≤ 8px');
    }

    // ── Responsive breakpoints exist and scale up ──

    public function testResponsiveBreakpointsExist(): void
    {
        $breakpoints = [380, 400, 428, 600, 768, 900, 1200];
        foreach ($breakpoints as $bp) {
            $this->assertStringContainsString(
                "min-width: {$bp}px",
                $this->css,
                "Breakpoint {$bp}px must exist in CSS"
            );
        }
    }

    public function testTabletBreakpointRestoresGalleryHeaderBorder(): void
    {
        // At 600px+, gallery header should get border-bottom back
        preg_match(
            '/@media\s*\(min-width:\s*600px\)\s*\{(.*?)(?=@media|\z)/s',
            $this->css,
            $mediaBlock
        );
        $this->assertNotEmpty($mediaBlock, '600px media query must exist');
        $this->assertStringContainsString(
            'border-bottom',
            $mediaBlock[1],
            'Tablet breakpoint must restore gallery header border-bottom'
        );
    }

    public function testTabletBreakpointRestoresInputHeight(): void
    {
        // At 600px+, search input should restore to ≥ 32px height
        preg_match(
            '/@media\s*\(min-width:\s*600px\)\s*\{(.*?)(?=@media|\z)/s',
            $this->css,
            $mediaBlock
        );
        $this->assertNotEmpty($mediaBlock);
        // Search input height should be restored at tablet
        preg_match('/\.aih-search-input\s*\{[^}]*height:\s*(\d+)px/s', $mediaBlock[1], $m);
        $this->assertNotEmpty($m, 'Tablet search input must set explicit height');
        $this->assertGreaterThanOrEqual(32, (int) $m[1], 'Tablet search input height should be ≥ 32px');
    }

    // ── Safe area support ──

    public function testSafeAreaInsetsSupported(): void
    {
        $this->assertStringContainsString(
            'env(safe-area-inset-top)',
            $this->css,
            'CSS must support safe-area-inset-top for notched devices'
        );
        $this->assertStringContainsString(
            'env(safe-area-inset-bottom)',
            $this->css,
            'CSS must support safe-area-inset-bottom for notched devices'
        );
    }

    // ── iOS zoom prevention ──

    public function testSearchInputPreventsiOSZoom(): void
    {
        // iOS auto-zooms on inputs with font-size < 16px
        preg_match(
            '/\.aih-search-input\s*\{[^}]*font-size:\s*(\d+)px/s',
            $this->css,
            $m
        );
        $this->assertNotEmpty($m, 'Search input must set font-size');
        $this->assertGreaterThanOrEqual(16, (int) $m[1], 'Search input font-size must be ≥ 16px to prevent iOS zoom');
    }
}
