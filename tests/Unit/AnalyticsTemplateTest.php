<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the analytics template uses CSS classes for layout
 * instead of inline flex/min-width styles that cause overflow,
 * and that the Revenue tab and 5-minute timeline are present.
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

    public function testTierTableHasEndClosingTimeColumn(): void
    {
        $this->assertStringContainsString(
            'data-sort="end_closing"',
            $this->template,
            'Tier statistics table must have a sortable End Closing Time column'
        );

        $this->assertStringContainsString(
            'data-end_closing=',
            $this->template,
            'Tier table rows must include data-end_closing attribute for sorting'
        );
    }

    public function testCsvExportIncludesEndClosingTime(): void
    {
        $this->assertStringContainsString(
            "'End Closing Time'",
            $this->template,
            'CSV export header must include End Closing Time'
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

    // ========== Revenue Tab ==========

    public function testRevenueTabExists(): void
    {
        $this->assertStringContainsString(
            "'revenue'",
            $this->template,
            'Analytics template must define a revenue tab key'
        );
    }

    public function testRevenueTabIsConditionalOnFinancialPermission(): void
    {
        $this->assertMatchesRegularExpression(
            '/can_view_financial.*revenue/s',
            $this->template,
            'Revenue tab must be gated behind can_view_financial()'
        );
    }

    public function testRevenueTabContainsProjectedRevenue(): void
    {
        $this->assertStringContainsString(
            'projected_revenue',
            $this->template,
            'Revenue tab must show projected revenue for active items'
        );
    }

    public function testRevenueTabContainsAvgOrderValue(): void
    {
        $this->assertStringContainsString(
            'avg_order_value',
            $this->template,
            'Revenue tab must show average order value'
        );
    }

    public function testRevenueTabContainsCollectionRate(): void
    {
        $this->assertStringContainsString(
            'collection_rate',
            $this->template,
            'Revenue tab must show collection rate'
        );
    }

    public function testRevenueTabContainsPaymentMethodChart(): void
    {
        $this->assertStringContainsString(
            'aih-revenue-method-chart',
            $this->template,
            'Revenue tab must include a payment method chart canvas'
        );
    }

    public function testRevenueTabContainsRevenueByPieceTable(): void
    {
        $this->assertStringContainsString(
            'revenue_by_piece',
            $this->template,
            'Revenue tab must include revenue-by-piece data'
        );
    }

    public function testRevenueTabShowsUpliftPercentage(): void
    {
        $this->assertStringContainsString(
            'avg_uplift',
            $this->template,
            'Revenue tab must calculate uplift from starting bid'
        );
    }

    // ========== Timeline Granularity ==========

    public function testTimelineUsesIntervalDataVariable(): void
    {
        $this->assertStringContainsString(
            'bids_by_interval',
            $this->template,
            'Timeline data processing must use interval-based variable name'
        );
    }

    public function testTimelineFormatsLabelsAsShortTime(): void
    {
        $this->assertStringContainsString(
            "->format( 'g:i A' )",
            $this->template,
            'Timeline labels must be formatted as short times (e.g. 2:15 PM)'
        );
    }

    // ========== Safe Defaults ==========

    public function testRevenueVariablesHaveSafeDefaults(): void
    {
        $vars = ['revenue_by_method', 'revenue_by_piece', 'collection_rate', 'avg_order_value', 'projected_revenue'];
        foreach ($vars as $var) {
            $this->assertStringContainsString(
                "! isset( \$$var )",
                $this->template,
                "Revenue variable \$$var must have a safe default check"
            );
        }
    }

    // ========== Live Widgets ==========

    public function testLiveDataHasSafeDefault(): void
    {
        $this->assertStringContainsString(
            '! isset( $live_data )',
            $this->template,
            'Template must have safe default for $live_data'
        );
    }

    public function testTemplateContainsRepeatBidders(): void
    {
        $this->assertStringContainsString(
            'repeat_bidder_rate',
            $this->template,
            'Template must display repeat bidder rate stat card'
        );
    }

    public function testTemplateContainsPulseDot(): void
    {
        $this->assertStringContainsString(
            'aih-pulse-dot',
            $this->template,
            'Template must contain pulse dot indicator'
        );
    }

    // ========== Server Load Tab ==========

    public function testServerLoadTabExists(): void
    {
        $this->assertStringContainsString(
            "'server-load'",
            $this->template,
            'Analytics template must define a server-load tab key'
        );
    }

    public function testServerLoadTabHasTimelineChart(): void
    {
        $this->assertStringContainsString(
            'aih-server-load-timeline-chart',
            $this->template,
            'Server Load tab must include timeline chart canvas'
        );
    }

    public function testServerLoadTabHasConnectionTypeChart(): void
    {
        $this->assertStringContainsString(
            'aih-conn-type-chart',
            $this->template,
            'Server Load tab must include connection type chart canvas'
        );
    }

    public function testServerLoadTabHasPushSegmentChart(): void
    {
        $this->assertStringContainsString(
            'aih-push-segment-chart',
            $this->template,
            'Server Load tab must include push segment chart canvas'
        );
    }

    public function testServerLoadTabUsesEngagementMetrics(): void
    {
        $this->assertStringContainsString(
            'server_load_by_segment',
            $this->template,
            'Server Load tab must reference server_load_by_segment data'
        );
    }

    public function testServerLoadTabShowsCostPerSegmentTable(): void
    {
        $this->assertStringContainsString(
            'Polls/Min',
            $this->template,
            'Server Load tab must show polls per minute rate in cost table'
        );
    }

    public function testServerLoadTabShowsKeyInsight(): void
    {
        $this->assertStringContainsString(
            'Key Insight',
            $this->template,
            'Server Load tab must include key insight callout'
        );
    }
}
