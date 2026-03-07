<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the analytics template uses CSS classes for layout
 * instead of inline flex/min-width styles that cause overflow.
 */
class AnalyticsTemplateTest extends TestCase
{
    private string $template;

    protected function setUp(): void
    {
        parent::setUp();
        $path = AIH_PLUGIN_DIR . 'admin/views/analytics.php';
        $this->assertFileExists($path, 'analytics.php must exist');
        $template = file_get_contents($path);
        $this->assertNotFalse($template, 'Failed to read analytics.php');
        $this->template = $template;
    }

    public function testNoInlineFlexDisplayOnChartContainers(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/style="[^"]*display:\s*flex;[^"]*flex-wrap:\s*wrap[^"]*"/',
            $this->template,
            'Chart containers should use .aih-chart-row class instead of inline flex styles'
        );
    }

    public function testChartRowClassIsUsed(): void
    {
        $this->assertStringContainsString(
            'aih-chart-row',
            $this->template,
            'Analytics template must use .aih-chart-row class for chart layouts'
        );
    }

    public function testNoInlineMinWidthOnPostboxes(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/<div\s+class="postbox"[^>]*style="[^"]*min-width:\s*\d+px/',
            $this->template,
            'Postboxes should not have inline min-width styles'
        );
    }

    public function testTopRevenueChartHasYAxisTickConfig(): void
    {
        $this->assertStringContainsString(
            'substring(0,',
            $this->template,
            'Top revenue chart must truncate long Y-axis labels'
        );
    }
}
