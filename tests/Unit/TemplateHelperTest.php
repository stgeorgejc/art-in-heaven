<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Template_Helper;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class TemplateHelperTest extends TestCase
{
    /** @var mixed */
    private mixed $previousWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Save previous $wpdb so we can restore it in tearDown
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;

        Functions\stubs([
            '__' => function ($text) {
                return $text;
            },
            'esc_url' => function ($url) {
                return $url;
            },
            'esc_attr' => function ($text) {
                return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
            },
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->previousWpdb !== null) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── format_time_remaining() ──

    public function testFormatTimeRemainingEnded(): void
    {
        $this->assertSame('Ended', AIH_Template_Helper::format_time_remaining(0));
        $this->assertSame('Ended', AIH_Template_Helper::format_time_remaining(-100));
    }

    public function testFormatTimeRemainingMinutesOnly(): void
    {
        $this->assertSame('1m', AIH_Template_Helper::format_time_remaining(60));
        $this->assertSame('45m', AIH_Template_Helper::format_time_remaining(2700));
        $this->assertSame('0m', AIH_Template_Helper::format_time_remaining(30)); // less than a minute
    }

    public function testFormatTimeRemainingHoursAndMinutes(): void
    {
        $this->assertSame('1h 0m', AIH_Template_Helper::format_time_remaining(3600));
        $this->assertSame('2h 30m', AIH_Template_Helper::format_time_remaining(9000));
        $this->assertSame('23h 59m', AIH_Template_Helper::format_time_remaining(86340));
    }

    public function testFormatTimeRemainingDaysAndHours(): void
    {
        $this->assertSame('1d 0h', AIH_Template_Helper::format_time_remaining(86400));
        $this->assertSame('3d 12h', AIH_Template_Helper::format_time_remaining(302400));
        $this->assertSame('7d 0h', AIH_Template_Helper::format_time_remaining(604800));
    }

    // ── get_bidder_display_name() ──

    public function testGetBidderDisplayNameNull(): void
    {
        $this->assertSame('', AIH_Template_Helper::get_bidder_display_name(null));
        $this->assertSame('Guest', AIH_Template_Helper::get_bidder_display_name(null, 'Guest'));
    }

    public function testGetBidderDisplayNameFromNameFirst(): void
    {
        $bidder = (object) ['name_first' => 'David', 'individual_name' => 'David Smith'];
        $this->assertSame('David', AIH_Template_Helper::get_bidder_display_name($bidder));
    }

    public function testGetBidderDisplayNameFromIndividualName(): void
    {
        $bidder = (object) ['name_first' => '', 'individual_name' => 'Jane Doe'];
        $this->assertSame('Jane', AIH_Template_Helper::get_bidder_display_name($bidder));
    }

    public function testGetBidderDisplayNameFallback(): void
    {
        $bidder = (object) ['name_first' => '', 'individual_name' => ''];
        $this->assertSame('Anonymous', AIH_Template_Helper::get_bidder_display_name($bidder, 'Anonymous'));
    }

    // ── get_page_url() transient caching ──

    public function testGetPageUrlReturnsTransientCacheHit(): void
    {
        // Clear static cache
        AIH_Template_Helper::clear_cache();

        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $options = 'wp_options';
            /** @var list<string> */
            public array $queries = [];
            public function prepare(string $q, mixed ...$a): string { return $q; }
            public function query(string $q): void { $this->queries[] = $q; }
            public function esc_like(string $t): string { return $t; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        Functions\stubs([
            'get_option' => fn($key, $default = false) => '',
            'get_transient' => fn($key) => 'https://example.com/gallery/',
            'set_transient' => fn() => true,
            'home_url' => fn() => 'https://example.com/',
        ]);

        $url = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame('https://example.com/gallery/', $url);
    }

    public function testGetPageUrlSetsTransientOnCacheMiss(): void
    {
        // Clear static cache
        AIH_Template_Helper::clear_cache();

        $wpdb = new class {
            public string $prefix = 'wp_';
            public string $posts = 'wp_posts';
            public string $options = 'wp_options';
            /** @var list<string> */
            public array $queries = [];
            public function prepare(string $q, mixed ...$a): string { return $q; }
            public function get_var(string $q = ''): ?string { return '42'; }
            public function query(string $q): void { $this->queries[] = $q; }
            public function esc_like(string $t): string { return $t; }
        };
        $GLOBALS['wpdb'] = $wpdb;

        $transient_set = false;
        Functions\stubs([
            'get_option' => fn($key, $default = false) => '',
            'get_transient' => fn($key) => false,
            'set_transient' => function ($key, $value, $expiration) use (&$transient_set) {
                $transient_set = true;
                return true;
            },
            'get_permalink' => fn($id) => 'https://example.com/gallery/',
            'home_url' => fn() => 'https://example.com/',
        ]);

        $url = AIH_Template_Helper::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');

        $this->assertSame('https://example.com/gallery/', $url);
        $this->assertTrue($transient_set, 'set_transient should have been called');
    }

    // ── format_art_piece() ──

    public function testFormatArtPieceReturnsCorrectStructure(): void
    {
        $piece = (object) [
            'id' => 42,
            'art_id' => 'ART-042',
            'title' => 'Sunset Over Mountains',
            'artist' => 'Jane Doe',
            'medium' => 'Oil on Canvas',
            'starting_bid' => '50.00',
            'current_bid' => '125.00',
            'watermarked_url' => 'https://example.com/art/42-wm.jpg',
            'image_url' => 'https://example.com/art/42.jpg',
            'auction_end' => '2025-12-31 23:59:59',
            'seconds_remaining' => 3600,
            'status' => 'active',
            'computed_status' => 'active',
            'is_favorite' => false,
        ];

        $data = AIH_Template_Helper::format_art_piece($piece);

        $this->assertSame(42, $data['id']);
        $this->assertSame('ART-042', $data['art_id']);
        $this->assertSame('Sunset Over Mountains', $data['title']);
        $this->assertSame('Jane Doe', $data['artist']);
        $this->assertSame('Oil on Canvas', $data['medium']);
        $this->assertSame(50.0, $data['starting_bid']);
        $this->assertSame(125.0, $data['current_bid']);
        $this->assertSame('https://example.com/art/42-wm.jpg', $data['image_url']);
        $this->assertSame('active', $data['status']);
        $this->assertFalse($data['auction_ended']);
        $this->assertFalse($data['auction_upcoming']);
        $this->assertFalse($data['is_favorite']);
    }

    public function testFormatArtPieceWithZeroSecondsIsEnded(): void
    {
        $piece = (object) [
            'id' => 1,
            'art_id' => 'A1',
            'title' => 'Test',
            'artist' => 'Artist',
            'medium' => 'Digital',
            'starting_bid' => '10.00',
            'current_bid' => '10.00',
            'watermarked_url' => '',
            'image_url' => 'https://example.com/img.jpg',
            'auction_end' => '2020-01-01 00:00:00',
            'seconds_remaining' => 0,
            'status' => 'ended',
            'computed_status' => 'ended',
            'is_favorite' => false,
        ];

        $data = AIH_Template_Helper::format_art_piece($piece);

        $this->assertTrue($data['auction_ended']);
        // When watermarked_url is empty, falls back to image_url
        $this->assertSame('https://example.com/img.jpg', $data['image_url']);
    }

    // ── picture_tag() ──

    public function testPictureTagEmptyUrlReturnsEmptyString(): void
    {
        $this->assertSame('', AIH_Template_Helper::picture_tag(''));
    }

    public function testPictureTagNonWatermarkedUrlReturnsFallbackImg(): void
    {
        // Non-watermarked URL has no responsive variants, so picture_tag falls back to <img>
        $html = AIH_Template_Helper::picture_tag(
            'https://example.com/uploads/art/image.jpg',
            'Test Alt',
            '80px',
            array('class' => 'aih-thumb')
        );

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="https://example.com/uploads/art/image.jpg"', $html);
        $this->assertStringContainsString('alt="Test Alt"', $html);
        $this->assertStringContainsString('class="aih-thumb"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('<picture>', $html);
    }

    public function testPictureTagEagerLoadingAttribute(): void
    {
        $html = AIH_Template_Helper::picture_tag(
            'https://example.com/image.jpg',
            '',
            '100vw',
            array(),
            'eager',
            'high'
        );

        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }

    public function testPictureTagDefaultSizesAttribute(): void
    {
        // When no sizes specified, defaults to '100vw'
        $html = AIH_Template_Helper::picture_tag('https://example.com/image.jpg');

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $html);
    }

    public function testFormatArtPieceWithTimeString(): void
    {
        $piece = (object) [
            'id' => 1,
            'art_id' => 'A1',
            'title' => 'Test',
            'artist' => 'Artist',
            'medium' => 'Digital',
            'starting_bid' => '10.00',
            'current_bid' => '50.00',
            'watermarked_url' => 'https://example.com/wm.jpg',
            'image_url' => 'https://example.com/img.jpg',
            'auction_end' => '2099-12-31 23:59:59',
            'seconds_remaining' => 9000,
            'status' => 'active',
            'computed_status' => 'active',
            'is_favorite' => true,
        ];

        $data = AIH_Template_Helper::format_art_piece($piece, null, false, true);

        $this->assertArrayHasKey('time_remaining', $data);
        $this->assertSame('2h 30m', $data['time_remaining']);
        $this->assertTrue($data['is_favorite']);
    }

    public function testFormatArtPieceFullDetails(): void
    {
        $piece = (object) [
            'id' => 1,
            'art_id' => 'A1',
            'title' => 'Test',
            'artist' => 'Artist',
            'medium' => 'Oil',
            'starting_bid' => '10.00',
            'current_bid' => '10.00',
            'watermarked_url' => 'https://example.com/wm.jpg',
            'image_url' => 'https://example.com/img.jpg',
            'auction_end' => '2099-12-31 23:59:59',
            'auction_start' => '2025-01-01 00:00:00',
            'seconds_remaining' => 86400,
            'status' => 'active',
            'computed_status' => 'active',
            'is_favorite' => false,
            'dimensions' => '24x36 inches',
            'description' => 'A beautiful landscape.',
        ];

        $data = AIH_Template_Helper::format_art_piece($piece, null, true);

        $this->assertArrayHasKey('dimensions', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertSame('24x36 inches', $data['dimensions']);
        $this->assertSame('A beautiful landscape.', $data['description']);
        $this->assertSame('2025-01-01 00:00:00', $data['auction_start']);
    }
}
