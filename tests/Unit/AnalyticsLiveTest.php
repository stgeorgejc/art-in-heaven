<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the live analytics polling infrastructure:
 * AJAX handler registration, permission gating, data queries,
 * and stat_key rendering in render_stat_card().
 */
class AnalyticsLiveTest extends TestCase
{
    private string $ajaxSource;
    private string $adminSource;
    private string $template;

    protected function setUp(): void
    {
        parent::setUp();

        $ajaxPath = AIH_PLUGIN_DIR . 'includes/class-aih-ajax.php';
        $this->assertFileExists($ajaxPath);
        $ajax = file_get_contents($ajaxPath);
        $this->assertNotFalse($ajax);
        $this->ajaxSource = $ajax;

        $adminPath = AIH_PLUGIN_DIR . 'admin/class-aih-admin.php';
        $this->assertFileExists($adminPath);
        $admin = file_get_contents($adminPath);
        $this->assertNotFalse($admin);
        $this->adminSource = $admin;

        $tplPath = AIH_PLUGIN_DIR . 'admin/views/analytics.php';
        $this->assertFileExists($tplPath);
        $tpl = file_get_contents($tplPath);
        $this->assertNotFalse($tpl);
        $this->template = $tpl;
    }

    // ========== AJAX Registration ==========

    public function testAjaxHandlerRegistered(): void
    {
        $this->assertStringContainsString(
            'aih_admin_analytics_live',
            $this->ajaxSource,
            'AJAX handler for admin_analytics_live must be registered'
        );
    }

    public function testAjaxHandlerChecksReportPermission(): void
    {
        $this->assertMatchesRegularExpression(
            '/admin_analytics_live.*can_view_reports/s',
            $this->ajaxSource,
            'admin_analytics_live handler must check can_view_reports()'
        );
    }

    public function testAjaxHandlerChecksAdminNonce(): void
    {
        $this->assertMatchesRegularExpression(
            '/admin_analytics_live.*aih_admin_nonce/s',
            $this->ajaxSource,
            'admin_analytics_live handler must verify aih_admin_nonce'
        );
    }

    // ========== Pulse Query ==========

    public function testPulseQueryUsesFiveMinuteInterval(): void
    {
        $this->assertStringContainsString(
            'INTERVAL 5 MINUTE',
            $this->adminSource,
            'Auction Pulse query must use 5-minute interval'
        );
    }

    public function testPulseQueryUsesFifteenMinuteInterval(): void
    {
        $this->assertStringContainsString(
            'INTERVAL 15 MINUTE',
            $this->adminSource,
            'Auction Pulse query must use 15-minute interval'
        );
    }

    public function testPulseQueryUsesSixtyMinuteInterval(): void
    {
        $this->assertStringContainsString(
            'INTERVAL 60 MINUTE',
            $this->adminSource,
            'Auction Pulse query must use 60-minute interval'
        );
    }

    // ========== Bid Feed Query ==========

    public function testBidFeedQueryUsesDescLimit(): void
    {
        $this->assertMatchesRegularExpression(
            '/bid_placed.*ORDER BY.*created_at DESC.*LIMIT 20/s',
            $this->adminSource,
            'Bid feed query must fetch last 20 bid_placed events'
        );
    }

    public function testBidFeedMasksBidderId(): void
    {
        $this->assertStringContainsString(
            "CONCAT(LEFT(al.bidder_id, 2), '****')",
            $this->adminSource,
            'Bid feed must mask bidder IDs'
        );
    }

    // ========== Repeat Bidder Rate ==========

    public function testRepeatBidderRateUsePiecesBidOn(): void
    {
        $this->assertStringContainsString(
            'pieces_bid_on',
            $this->adminSource,
            'Repeat bidder rate must use pieces_bid_on from engagement data'
        );
    }

    // ========== stat_key Rendering ==========

    public function testStatKeyRendersDataAttribute(): void
    {
        $this->assertStringContainsString(
            'data-stat',
            $this->adminSource,
            'render_stat_card must render data-stat attribute when stat_key is provided'
        );
    }

    // ========== Template Widgets ==========

    public function testTemplateContainsAuctionPulse(): void
    {
        $this->assertStringContainsString(
            'aih-auction-pulse',
            $this->template,
            'Analytics template must contain Auction Pulse widget'
        );
    }

    public function testTemplateContainsUrgencyBoard(): void
    {
        $this->assertStringContainsString(
            'aih-urgency-board',
            $this->template,
            'Analytics template must contain Urgency Board'
        );
    }

    public function testTemplateContainsBidFeed(): void
    {
        $this->assertStringContainsString(
            'aih-bid-feed',
            $this->template,
            'Analytics template must contain Live Bid Feed'
        );
    }

    public function testTemplateContainsAlertsPanel(): void
    {
        $this->assertStringContainsString(
            'aih-alerts-panel',
            $this->template,
            'Analytics template must contain Needs Attention alerts panel'
        );
    }

    public function testTemplateContainsStatKeyAttributes(): void
    {
        $this->assertStringContainsString(
            "'stat_key'",
            $this->template,
            'Analytics template must pass stat_key to render_stat_card()'
        );
    }

    public function testTemplateContainsWindowAihCharts(): void
    {
        $this->assertStringContainsString(
            'window.aihCharts',
            $this->template,
            'Analytics template must store Chart.js instances on window.aihCharts'
        );
    }

    // ========== JS Polling ==========

    public function testAdminJsContainsPollingCode(): void
    {
        $jsPath = AIH_PLUGIN_DIR . 'assets/js/aih-admin.js';
        $this->assertFileExists($jsPath);
        $js = file_get_contents($jsPath);
        $this->assertNotFalse($js);
        $this->assertStringContainsString(
            'aih_admin_analytics_live',
            $js,
            'Admin JS must contain the analytics live polling AJAX action'
        );
    }

    public function testAdminJsUsesVisibilityApi(): void
    {
        $jsPath = AIH_PLUGIN_DIR . 'assets/js/aih-admin.js';
        $js = file_get_contents($jsPath);
        $this->assertNotFalse($js);
        $this->assertStringContainsString(
            'visibilitychange',
            $js,
            'Admin JS must use Visibility API to pause/resume polling'
        );
    }
}
