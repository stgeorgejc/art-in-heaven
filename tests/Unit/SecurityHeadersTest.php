<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Art_In_Heaven::add_security_headers()
 *
 * Runs in separate processes so Brain Monkey can create WP function stubs
 * before the plugin file is loaded — avoids Patchwork "DefinedTooEarly" issues.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class SecurityHeadersTest extends TestCase
{
    private object $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub ALL WordPress functions needed for loading the plugin.
        // Brain Monkey creates these via Patchwork, making them overridable.
        Functions\stubs([
            'add_action' => null,
            'add_filter' => null,
            'remove_action' => null,
            'register_activation_hook' => null,
            'register_deactivation_hook' => null,
            'do_action' => null,
            'has_action' => false,
            'plugin_dir_path' => AIH_PLUGIN_DIR,
            'plugin_dir_url' => AIH_PLUGIN_URL,
            'plugin_basename' => AIH_PLUGIN_BASENAME,
            'get_option' => false,
            'update_option' => true,
            'current_time' => '2026-01-01 00:00:00',
            'wp_schedule_event' => null,
            'wp_clear_scheduled_hook' => null,
            'wp_next_scheduled' => false,
            'home_url' => 'https://example.com',
            'admin_url' => 'https://example.com/wp-admin/',
            'rest_url' => 'https://example.com/wp-json/',
            'wp_create_nonce' => 'test_nonce',
            'wp_register_script' => null,
            'wp_register_style' => null,
            'wp_enqueue_script' => null,
            'wp_enqueue_style' => null,
            'wp_localize_script' => null,
            'wp_add_inline_script' => null,
            'wp_style_is' => false,
            'wp_script_is' => false,
            'load_plugin_textdomain' => null,
            'add_shortcode' => null,
            '__' => function (string $text): string { return $text; },
            'esc_html__' => function (string $text): string { return $text; },
            '_e' => null,
            'esc_url' => function (string $url): string { return $url; },
            'esc_attr' => function (string $text): string { return $text; },
            'esc_html' => function (string $text): string { return $text; },
            'sanitize_text_field' => function (string $str): string { return trim(strip_tags($str)); },
            'wp_date' => function (string $f): string { return date($f); },
            'trailingslashit' => function (string $s): string { return rtrim($s, '/\\') . '/'; },
            'absint' => function ($n): int { return abs((int) $n); },
            'shortcode_atts' => function (array $d, $a): array { return array_merge($d, (array) $a); },
            'apply_filters' => function (): mixed { $a = func_get_args(); return $a[1] ?? null; },
            'check_ajax_referer' => true,
            'get_permalink' => 'https://example.com/?p=1',
            'wp_parse_args' => function ($a, array $d = []): array { return array_merge($d, (array) $a); },
            'wp_cache_get' => false,
            'wp_cache_set' => true,
            'wp_cache_delete' => true,
            'current_user_can' => false,
            'wp_generate_password' => 'test123',
            'sanitize_email' => function (string $e): string { return $e; },
            'sanitize_key' => function (string $k): string { return strtolower($k); },
            'is_email' => true,
            'esc_url_raw' => function (string $u): string { return $u; },
            'is_wp_error' => false,
            'wp_validate_redirect' => function (string $u): string { return $u; },
            'wp_safe_redirect' => null,
            'is_admin' => false,
            'wp_doing_ajax' => false,
            'wp_doing_cron' => false,
        ]);

        // Load the main plugin file (fresh in each separate process)
        @require_once dirname(__DIR__, 2) . '/art-in-heaven.php';

        // Create instance via reflection (bypasses constructor)
        $ref = new \ReflectionClass('Art_In_Heaven');
        $this->plugin = $ref->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Default headers on frontend pages ──
    // (is_admin, wp_doing_ajax, wp_doing_cron all return false from stubs)

    public function testAddsAllSecurityHeadersWhenNoneExist(): void
    {
        $result = $this->plugin->add_security_headers([]);

        $this->assertSame('nosniff', $result['X-Content-Type-Options']);
        $this->assertSame('SAMEORIGIN', $result['X-Frame-Options']);
        $this->assertSame('strict-origin-when-cross-origin', $result['Referrer-Policy']);
        $this->assertSame('camera=(), microphone=(), geolocation=()', $result['Permissions-Policy']);
    }

    public function testReturnsFourSecurityHeaders(): void
    {
        $result = $this->plugin->add_security_headers([]);

        $this->assertCount(4, $result);
    }

    // ── Adaptive: never override existing headers ──

    public function testDoesNotOverrideExistingXFrameOptions(): void
    {
        $existing = ['X-Frame-Options' => 'DENY'];
        $result = $this->plugin->add_security_headers($existing);

        $this->assertSame('DENY', $result['X-Frame-Options']);
    }

    public function testDoesNotOverrideExistingReferrerPolicy(): void
    {
        $existing = ['Referrer-Policy' => 'no-referrer'];
        $result = $this->plugin->add_security_headers($existing);

        $this->assertSame('no-referrer', $result['Referrer-Policy']);
        $this->assertSame('nosniff', $result['X-Content-Type-Options']);
    }

    public function testDoesNotOverrideAnyPreexistingHeaders(): void
    {
        $existing = [
            'X-Content-Type-Options' => 'custom',
            'X-Frame-Options'        => 'DENY',
            'Referrer-Policy'        => 'no-referrer',
            'Permissions-Policy'     => 'fullscreen=(self)',
        ];
        $result = $this->plugin->add_security_headers($existing);

        $this->assertSame($existing, $result);
    }

    // ── Preserves unrelated headers ──

    public function testPreservesExistingNonSecurityHeaders(): void
    {
        $existing = ['X-Powered-By' => 'WordPress', 'Cache-Control' => 'no-cache'];
        $result = $this->plugin->add_security_headers($existing);

        $this->assertSame('WordPress', $result['X-Powered-By']);
        $this->assertSame('no-cache', $result['Cache-Control']);
        $this->assertArrayHasKey('X-Content-Type-Options', $result);
    }

    // ── Skips admin, AJAX, and cron contexts ──

    public function testSkipsHeadersInAdmin(): void
    {
        Functions\when('is_admin')->justReturn(true);

        $result = $this->plugin->add_security_headers([]);

        $this->assertEmpty($result);
    }

    public function testSkipsHeadersDuringAjax(): void
    {
        Functions\when('wp_doing_ajax')->justReturn(true);

        $result = $this->plugin->add_security_headers([]);

        $this->assertEmpty($result);
    }

    public function testSkipsHeadersDuringCron(): void
    {
        Functions\when('wp_doing_cron')->justReturn(true);

        $result = $this->plugin->add_security_headers([]);

        $this->assertEmpty($result);
    }
}
