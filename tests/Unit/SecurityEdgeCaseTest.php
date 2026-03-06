<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Security;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for edge cases in AIH_Security:
 * verify_ajax_nonce, is_admin_request, is_rest_request, log_event.
 */
class SecurityEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'sanitize_text_field' => fn($v) => trim(strip_tags((string) $v)),
            'wp_verify_nonce' => fn($nonce, $action) => $nonce === 'valid_nonce' ? 1 : false,
            'is_admin' => false,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => false,
            'current_time' => fn() => '2026-01-15 10:00:00',
            'get_current_user_id' => fn() => 0,
            'wp_json_encode' => fn($v) => json_encode($v),
            '__' => fn($text) => $text,
            'rest_get_url_prefix' => fn() => 'wp-json',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up $_REQUEST and $_SERVER
        unset($_REQUEST['nonce'], $_REQUEST['custom_key']);
        unset($_SERVER['REQUEST_URI']);
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── verify_ajax_nonce ──

    public function testVerifyAjaxNonceReadsFromRequest(): void
    {
        $_REQUEST['nonce'] = 'valid_nonce';

        $this->assertTrue(AIH_Security::verify_ajax_nonce('test_action'));
    }

    public function testVerifyAjaxNonceFailsWithInvalidNonce(): void
    {
        $_REQUEST['nonce'] = 'bad_nonce';

        $this->assertFalse(AIH_Security::verify_ajax_nonce('test_action'));
    }

    public function testVerifyAjaxNonceFailsWithMissingNonce(): void
    {
        // No $_REQUEST['nonce'] set
        $this->assertFalse(AIH_Security::verify_ajax_nonce('test_action'));
    }

    public function testVerifyAjaxNonceUsesCustomKey(): void
    {
        $_REQUEST['custom_key'] = 'valid_nonce';

        $this->assertTrue(AIH_Security::verify_ajax_nonce('test_action', 'custom_key'));
    }

    // ── is_admin_request ──

    public function testIsAdminRequestReturnsFalseOnFrontend(): void
    {
        $this->assertFalse(AIH_Security::is_admin_request());
    }

    public function testIsAdminRequestReturnsTrueInAdmin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(false);

        $this->assertTrue(AIH_Security::is_admin_request());
    }

    public function testIsAdminRequestReturnsFalseDuringAjax(): void
    {
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);

        $this->assertFalse(AIH_Security::is_admin_request());
    }

    // ── is_rest_request ──

    public function testIsRestRequestReturnsFalseByDefault(): void
    {
        $this->assertFalse(AIH_Security::is_rest_request());
    }

    public function testIsRestRequestDetectsRestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/aih/v1/gallery';

        $this->assertTrue(AIH_Security::is_rest_request());
    }

    public function testIsRestRequestFalseForNonRestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/gallery/';

        $this->assertFalse(AIH_Security::is_rest_request());
    }

    // ── log_event ──

    public function testLogEventWritesToErrorLog(): void
    {
        // WP_DEBUG is true in test bootstrap
        // We can't easily test error_log output, but we can verify it doesn't throw
        AIH_Security::log_event('test_event', ['key' => 'value']);

        // If we get here without exception, the method works
        $this->assertTrue(true);
    }
}
