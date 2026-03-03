<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Template_Helper;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AIH_Template_Helper::get_page_url()
 *
 * Option values are expected to be numeric page IDs (URLs are migrated
 * to IDs by maybe_migrate_page_settings() on init).
 */
class GetPageUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Clear the static page cache between tests
        AIH_Template_Helper::clear_cache();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Option contains a numeric page ID ──

    public function testUsesGetPermalinkWhenOptionIsPageId(): void
    {
        Functions\when('get_option')->justReturn('42');
        Functions\when('get_permalink')->justReturn('https://example.com/gallery/');
        Functions\when('home_url')->justReturn('https://example.com');

        $result = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame('https://example.com/gallery/', $result);
    }

    // ── Option is empty — falls back to shortcode DB search ──

    public function testFallsBackToDbSearchWhenOptionEmpty(): void
    {
        Functions\when('get_option')->justReturn('');
        Functions\when('get_permalink')->justReturn('https://example.com/my-gallery/');
        Functions\when('home_url')->justReturn('https://example.com');

        $wpdb = $this->createWpdbMock(99);

        $result = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame('https://example.com/my-gallery/', $result);
    }

    // ── Everything fails — falls back to home_url() ──

    public function testFallsBackToHomeUrlWhenNothingFound(): void
    {
        Functions\when('get_option')->justReturn('');
        Functions\when('get_permalink')->justReturn(false);
        Functions\when('home_url')->justReturn('https://example.com');

        $wpdb = $this->createWpdbMock(null);

        $result = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame('https://example.com', $result);
    }

    // ── No option_name provided — goes straight to DB search ──

    public function testSkipsOptionCheckWhenNoOptionName(): void
    {
        Functions\when('get_permalink')->justReturn('https://example.com/gallery-page/');
        Functions\when('home_url')->justReturn('https://example.com');

        $wpdb = $this->createWpdbMock(10);

        $result = AIH_Template_Helper::get_page_url('art_in_heaven_gallery');

        $this->assertSame('https://example.com/gallery-page/', $result);
    }

    // ── Caching ──

    public function testCachesResultAndSkipsLookupOnSecondCall(): void
    {
        // get_option should only be called once — the second call hits the cache.
        Functions\expect('get_option')->once()->andReturn('42');
        Functions\when('get_permalink')->justReturn('https://example.com/gallery/');
        Functions\when('home_url')->justReturn('https://example.com');

        $first  = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');
        $second = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame($first, $second);
        $this->assertSame('https://example.com/gallery/', $second);
    }

    /**
     * Create a mock $wpdb object and set it as the global.
     *
     * @param mixed $get_var_return Value for get_var() to return
     * @return object The mock wpdb
     */
    private function createWpdbMock($get_var_return): object
    {
        global $wpdb;

        $wpdb = new class($get_var_return) {
            public string $posts = 'wp_posts';
            private $get_var_return;

            public function __construct($get_var_return)
            {
                $this->get_var_return = $get_var_return;
            }

            public function prepare($query, ...$args)
            {
                return $query;
            }

            public function esc_like($text)
            {
                return $text;
            }

            public function get_var($query = null)
            {
                return $this->get_var_return;
            }
        };

        return $wpdb;
    }
}
