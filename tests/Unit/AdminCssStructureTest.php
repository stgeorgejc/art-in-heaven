<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the admin CSS maintains correct structure for layout,
 * padding, overflow, and responsive behavior.
 */
class AdminCssStructureTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $path = AIH_PLUGIN_DIR . 'assets/css/aih-admin.css';
        $this->assertFileExists($path, 'aih-admin.css must exist');
        $css = file_get_contents($path);
        $this->assertNotFalse($css, 'Failed to read aih-admin.css');
        $this->css = $css;
    }

    // ── Postbox padding ──

    public function testPostboxHndleHasPadding(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-admin-wrap\s+\.postbox\s+\.hndle\s*\{[^}]*padding:/s',
            $this->css,
            'Postbox .hndle must have explicit padding in admin CSS'
        );
    }

    public function testPostboxInsideHasPadding(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-admin-wrap\s+\.postbox\s+\.inside\s*\{[^}]*padding:/s',
            $this->css,
            'Postbox .inside must have explicit padding in admin CSS'
        );
    }

    // ── Table overflow prevention ──

    public function testReportSectionTableHasMinWidthAuto(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-report-section\s+table\s*\{[^}]*min-width:\s*auto/s',
            $this->css,
            'Report section tables must have min-width: auto to prevent overflow'
        );
    }

    public function testDashboardSectionWidefatHasMinWidthAuto(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-dashboard-section\s+table\.widefat\s*\{[^}]*min-width:\s*auto/s',
            $this->css,
            'Dashboard section widefat tables must have min-width: auto to prevent overflow'
        );
    }

    // ── Chart row layout ──

    public function testChartRowUsesFlexWrap(): void
    {
        preg_match(
            '/\.aih-chart-row\s*\{([^}]*)\}/s',
            $this->css,
            $m
        );
        $this->assertNotEmpty($m, '.aih-chart-row rule must exist');
        $this->assertStringContainsString('display: flex', $m[1]);
        $this->assertStringContainsString('flex-wrap: wrap', $m[1]);
    }

    public function testChartRowPostboxHasMinWidth(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-chart-row\s+\.postbox\s*\{[^}]*min-width:/s',
            $this->css,
            '.aih-chart-row .postbox must define a min-width'
        );
    }

    // ── Mobile responsive ──

    public function testMobileChartRowStacksVertically(): void
    {
        // Find the 768px mobile breakpoint block
        preg_match(
            '/@media\s+screen\s+and\s*\(max-width:\s*768px\)\s*\{(.*?)^\}/ms',
            $this->css,
            $mediaBlock
        );
        $this->assertNotEmpty($mediaBlock, '768px max-width media query must exist');
        $this->assertStringContainsString(
            'flex-direction: column',
            $mediaBlock[1],
            'Chart row must stack vertically on mobile'
        );
    }

    // ── Global table min-width guard ──

    public function testGlobalTableMinWidthExists(): void
    {
        // The global min-width: 600px rule should still exist for data tables
        $this->assertMatchesRegularExpression(
            '/\.aih-admin-wrap\s+table\s*\{[^}]*min-width:\s*600px/s',
            $this->css,
            'Global table min-width: 600px rule must exist for data tables'
        );
    }
}
