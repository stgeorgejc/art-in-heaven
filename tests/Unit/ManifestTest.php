<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Art_In_Heaven::serve_manifest()
 *
 * Validates manifest JSON structure, start_url, icons, cache headers, etc.
 * Runs in separate processes so Brain Monkey can stub WP functions before
 * the plugin file is loaded.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ManifestTest extends TestCase
{
    private object $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

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

        @require_once dirname(__DIR__, 2) . '/art-in-heaven.php';

        $ref = new \ReflectionClass('Art_In_Heaven');
        $this->plugin = $ref->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Call build_manifest_array() on the plugin instance after stubbing
     * the gallery page option so get_page_url() resolves without $wpdb.
     */
    private function getManifestArray(): array
    {
        Functions\when('wp_make_link_relative')->alias(function (string $url): string {
            $parsed = parse_url($url);
            return $parsed['path'] ?? '/';
        });

        // Return a numeric page ID from the option so get_page_url()
        // resolves via get_permalink() without needing $wpdb.
        Functions\when('get_option')->alias(function (string $key, $default = false) {
            if ($key === 'aih_gallery_page') {
                return '42';
            }
            return $default;
        });
        Functions\when('get_permalink')->justReturn('https://example.com/gallery/');

        \AIH_Template_Helper::clear_cache();

        return $this->plugin->build_manifest_array();
    }

    // ── Manifest structure ──

    public function testManifestContainsRequiredFields(): void
    {
        $manifest = $this->getManifestArray();

        $required = ['name', 'short_name', 'start_url', 'display', 'icons', 'id', 'scope'];
        foreach ($required as $field) {
            $this->assertArrayHasKey($field, $manifest, "Manifest missing required field: {$field}");
        }
    }

    public function testManifestDisplayIsStandalone(): void
    {
        $manifest = $this->getManifestArray();

        $this->assertSame('standalone', $manifest['display']);
    }

    public function testManifestHasStableId(): void
    {
        $manifest = $this->getManifestArray();

        $this->assertSame('/art-in-heaven', $manifest['id']);
    }

    public function testManifestScopeIsRoot(): void
    {
        $manifest = $this->getManifestArray();

        $this->assertSame('/', $manifest['scope']);
    }

    // ── Icons ──

    public function testManifestHasFourIcons(): void
    {
        $manifest = $this->getManifestArray();

        $this->assertCount(4, $manifest['icons']);
    }

    public function testManifestHasAnyPurposeIcons(): void
    {
        $manifest = $this->getManifestArray();

        $anyIcons = array_filter($manifest['icons'], fn($i) => $i['purpose'] === 'any');
        $this->assertCount(2, $anyIcons);
    }

    public function testManifestHasMaskableIcons(): void
    {
        $manifest = $this->getManifestArray();

        $maskable = array_filter($manifest['icons'], fn($i) => $i['purpose'] === 'maskable');
        $this->assertCount(2, $maskable);
    }

    public function testManifestIconSizesInclude192And512(): void
    {
        $manifest = $this->getManifestArray();

        $sizes = array_column($manifest['icons'], 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);
    }

    public function testMaskableIconsReferenceCorrectFiles(): void
    {
        $manifest = $this->getManifestArray();

        $maskable = array_values(array_filter($manifest['icons'], fn($i) => $i['purpose'] === 'maskable'));

        $this->assertStringContainsString('icon-maskable-192.png', $maskable[0]['src']);
        $this->assertStringContainsString('icon-maskable-512.png', $maskable[1]['src']);
    }

    // ── Maskable icon files exist ──

    public function testMaskableIconFilesExist(): void
    {
        $this->assertFileExists(AIH_PLUGIN_DIR . 'assets/images/icon-maskable-192.png');
        $this->assertFileExists(AIH_PLUGIN_DIR . 'assets/images/icon-maskable-512.png');
    }

    public function testMaskableIconsAreSquare(): void
    {
        $info192 = getimagesize(AIH_PLUGIN_DIR . 'assets/images/icon-maskable-192.png');
        $info512 = getimagesize(AIH_PLUGIN_DIR . 'assets/images/icon-maskable-512.png');

        $this->assertSame(192, $info192[0], 'maskable-192 width');
        $this->assertSame(192, $info192[1], 'maskable-192 height');
        $this->assertSame(512, $info512[0], 'maskable-512 width');
        $this->assertSame(512, $info512[1], 'maskable-512 height');
    }

    public function testRegularIconsAreSquare(): void
    {
        $info192 = getimagesize(AIH_PLUGIN_DIR . 'assets/images/icon-192.png');
        $info512 = getimagesize(AIH_PLUGIN_DIR . 'assets/images/icon-512.png');

        $this->assertSame(192, $info192[0], 'icon-192 width');
        $this->assertSame(192, $info192[1], 'icon-192 height');
        $this->assertSame(512, $info512[0], 'icon-512 width');
        $this->assertSame(512, $info512[1], 'icon-512 height');
    }
}
