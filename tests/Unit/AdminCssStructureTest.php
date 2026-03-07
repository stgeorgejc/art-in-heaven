<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the admin CSS maintains correct structure for layout,
 * padding, overflow, responsive behavior, and variable usage.
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
            '/\.aih-dashboard-section\s+table\.widefat:not\(\.wp-list-table\)\s*\{[^}]*min-width:\s*auto/s',
            $this->css,
            'Dashboard section widefat (non-wp-list-table) tables must have min-width: auto'
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
        preg_match(
            '/@media\s+screen\s+and\s*\(max-width:\s*768px\)\s*\{(.*?)^\}/ms',
            $this->css,
            $mediaBlock
        );
        $this->assertNotEmpty($mediaBlock, '768px max-width media query must exist');
        $this->assertMatchesRegularExpression(
            '/\.aih-chart-row\s*\{[^}]*flex-direction:\s*column/s',
            $mediaBlock[1],
            'Chart row must stack vertically on mobile via flex-direction: column within its rule'
        );
    }

    // ── Global table min-width guard ──

    public function testGlobalTableMinWidthExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-admin-wrap\s+table\s*\{[^}]*min-width:\s*600px/s',
            $this->css,
            'Global table min-width: 600px rule must exist for data tables'
        );
    }

    // ── Badge color variables ──

    #[DataProvider('badgeColorVariableProvider')]
    public function testBadgeColorVariablesDefined(string $variable): void
    {
        $this->assertStringContainsString(
            $variable,
            $this->css,
            "CSS variable $variable must be defined in :root"
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badgeColorVariableProvider(): array
    {
        return [
            'success-bg'   => ['--aih-badge-success-bg'],
            'success-text' => ['--aih-badge-success-text'],
            'warning-bg'   => ['--aih-badge-warning-bg'],
            'warning-text' => ['--aih-badge-warning-text'],
            'error-bg'     => ['--aih-badge-error-bg'],
            'error-text'   => ['--aih-badge-error-text'],
            'info-bg'      => ['--aih-badge-info-bg'],
            'info-text'    => ['--aih-badge-info-text'],
            'neutral-bg'   => ['--aih-badge-neutral-bg'],
            'neutral-text' => ['--aih-badge-neutral-text'],
            'muted-bg'     => ['--aih-badge-muted-bg'],
            'muted-text'   => ['--aih-badge-muted-text'],
        ];
    }

    public function testStatusBadgesUseVariablesNotHardcoded(): void
    {
        // Extract all .aih-status-badge.* rules
        preg_match_all(
            '/\.aih-status-badge\.\w+[^{]*\{([^}]*)\}/s',
            $this->css,
            $matches
        );
        $this->assertNotEmpty($matches[1], 'Status badge rules must exist');

        foreach ($matches[1] as $ruleBody) {
            // Skip rules that don't set background (base rule, etc.)
            if (strpos($ruleBody, 'background:') === false) {
                continue;
            }
            $this->assertStringContainsString(
                'var(--aih-badge-',
                $ruleBody,
                "Status badge colors must use CSS variables, found: $ruleBody"
            );
        }
    }

    public function testSimpleBadgesUseVariablesNotHardcoded(): void
    {
        $badgeClasses = ['aih-badge-success', 'aih-badge-warning', 'aih-badge-error', 'aih-badge-info', 'aih-badge-secondary'];
        foreach ($badgeClasses as $class) {
            preg_match(
                '/\.' . preg_quote($class, '/') . '\s*\{([^}]*)\}/s',
                $this->css,
                $m
            );
            $this->assertNotEmpty($m, ".$class rule must exist");
            $this->assertStringContainsString(
                'var(--aih-badge-',
                $m[1],
                ".$class must use CSS variables for colors"
            );
        }
    }

    // ── Non-existent variable guard ──

    public function testNoNonExistentColorPrimaryVariable(): void
    {
        $this->assertStringNotContainsString(
            '--aih-color-primary',
            $this->css,
            'CSS must not reference non-existent --aih-color-primary variable'
        );
    }

    // ── Progress bar variables ──

    public function testProgressBarUsesVariables(): void
    {
        $this->assertStringContainsString(
            '--aih-progress-track',
            $this->css,
            'Progress bar track color must use a CSS variable'
        );
    }

    public function testImportStatBgUsesVariable(): void
    {
        preg_match(
            '/\.aih-import-stat\s*\{([^}]*)\}/s',
            $this->css,
            $m
        );
        $this->assertNotEmpty($m, '.aih-import-stat rule must exist');
        $this->assertStringContainsString(
            'var(--aih-import-stat-bg)',
            $m[1],
            'Import stat background must use CSS variable'
        );
    }

    // ── Panel modifier ──

    public function testPanelBottomModifierExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-panel--bottom\s*\{[^}]*border-top:\s*none/s',
            $this->css,
            '.aih-panel--bottom modifier must exist with border-top: none'
        );
    }

    // ── Utility classes ──

    public function testHeadingIconClassExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-heading-icon\s*\{[^}]*font-size:/s',
            $this->css,
            '.aih-heading-icon utility class must exist'
        );
    }

    public function testBtnErrorClassExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-btn-error\s*\{[^}]*color:\s*var\(--aih-error\)/s',
            $this->css,
            '.aih-btn-error class must exist with error color'
        );
    }
}
