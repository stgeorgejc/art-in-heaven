<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for analytics revenue tab queries and timeline granularity
 * in class-aih-admin.php.
 */
class AnalyticsRevenueTest extends TestCase
{
    private string $adminSource;

    protected function setUp(): void
    {
        parent::setUp();
        $path = AIH_PLUGIN_DIR . 'admin/class-aih-admin.php';
        $this->assertFileExists($path);
        $source = file_get_contents($path);
        $this->assertNotFalse($source);
        $this->adminSource = $source;
    }

    // ========== Timeline Granularity ==========

    public function testTimelineUsesFiveMinuteIntervals(): void
    {
        $this->assertStringContainsString(
            'MOD(MINUTE(created_at), 5)',
            $this->adminSource,
            'Timeline SQL must bucket bids into 5-minute intervals'
        );
    }

    public function testTimelineSqlUsesDateSub(): void
    {
        $this->assertStringContainsString(
            'DATE_SUB(created_at, INTERVAL MOD(MINUTE(created_at), 5) MINUTE)',
            $this->adminSource,
            'Timeline SQL must use DATE_SUB to floor minutes to 5-minute boundaries'
        );
    }

    public function testTimelineSqlFormatsAsDateTimeWithMinutes(): void
    {
        $this->assertStringContainsString(
            "'%Y-%m-%d %H:%i'",
            $this->adminSource,
            'Timeline SQL must format buckets with minutes (not just hours)'
        );
    }

    // ========== Revenue Queries ==========

    public function testRevenueByMethodQueryExists(): void
    {
        $this->assertStringContainsString(
            'GROUP BY payment_method',
            $this->adminSource,
            'Revenue by payment method query must group by payment_method'
        );
    }

    public function testRevenueByMethodFiltersPaidOnly(): void
    {
        $this->assertMatchesRegularExpression(
            '/payment_method.*payment_status\s*=\s*\'paid\'/s',
            $this->adminSource,
            'Revenue by payment method must filter to paid orders only'
        );
    }

    public function testRevenueByPieceJoinsOrderItems(): void
    {
        $this->assertMatchesRegularExpression(
            '/revenue_by_piece.*order_items_table.*JOIN.*orders_table/s',
            $this->adminSource,
            'Revenue by piece must join order_items with orders'
        );
    }

    public function testCollectionRateQueryExists(): void
    {
        $this->assertStringContainsString(
            'paid_items',
            $this->adminSource,
            'Collection rate query must compute paid_items count'
        );
        $this->assertStringContainsString(
            'pending_items',
            $this->adminSource,
            'Collection rate query must compute pending_items count'
        );
    }

    public function testAvgOrderValueQueryFiltersPaidAndPositive(): void
    {
        $this->assertStringContainsString(
            "AVG(total) FROM \$orders_table WHERE payment_status = 'paid' AND total > 0",
            $this->adminSource,
            'Avg order value query must filter to paid orders with positive totals'
        );
    }

    public function testProjectedRevenueQueryFiltersActiveItems(): void
    {
        $this->assertMatchesRegularExpression(
            '/projected_revenue.*status\s*=\s*\'active\'.*auction_end\s*>\s*NOW/s',
            $this->adminSource,
            'Projected revenue must filter to active items that have not ended'
        );
    }

    public function testProjectedRevenueUsesCoalesceForNullSafety(): void
    {
        $this->assertStringContainsString(
            'COALESCE(SUM(max_bid), 0)',
            $this->adminSource,
            'Projected revenue must use COALESCE to handle no active bids'
        );
    }

    // ========== Permission Gating ==========

    public function testRevenueQueriesGatedBehindFinancialPermission(): void
    {
        // The revenue queries must be inside a can_view_financial() block.
        $this->assertMatchesRegularExpression(
            '/can_view_financial\(\).*revenue_by_method\s*=\s*\$wpdb/s',
            $this->adminSource,
            'Revenue queries must only run when can_view_financial() is true'
        );
    }

    public function testRevenueDefaultsSetBeforePermissionCheck(): void
    {
        // Safe defaults must be set before the permission check.
        $defaultsPos = strpos($this->adminSource, '$revenue_by_method  = array()');
        $this->assertNotFalse($defaultsPos, 'Revenue defaults must exist');
        // Find the can_view_financial() call that follows the defaults.
        $permCheckPos = strpos($this->adminSource, 'AIH_Roles::can_view_financial', $defaultsPos);
        $this->assertNotFalse($permCheckPos, 'Financial permission check must exist after defaults');
        $this->assertLessThan(
            $permCheckPos,
            $defaultsPos,
            'Revenue defaults must be set before the permission check'
        );
    }
}
