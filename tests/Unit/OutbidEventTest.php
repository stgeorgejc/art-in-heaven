<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Push;
use AIH_Template_Helper;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for outbid event recording and consumption.
 *
 * Verifies that outbid events include the catalog art_id so the frontend
 * can construct correct art piece URLs (fix for notification click navigation).
 */
class OutbidEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset AIH_Push singleton
        $ref = new \ReflectionClass(AIH_Push::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        // Reset VAPID key cache
        $vapid = $ref->getProperty('cached_vapid_keys');
        $vapid->setValue(null, null);

        // Reset AIH_Database cached year
        $dbRef = new \ReflectionClass(AIH_Database::class);
        $year = $dbRef->getProperty('cached_year');
        $year->setValue(null, null);

        Functions\stubs([
            'sanitize_text_field' => function ($v) { return $v; },
            'wp_date'             => fn() => '2026',
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ========== record_outbid_event ==========

    /**
     * Recording an outbid event stores catalog_art_id in the transient.
     */
    public function testRecordOutbidEventIncludesCatalogArtId(): void
    {
        $storedValue = null;

        Functions\stubs(['get_transient' => false]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$storedValue) {
                $storedValue = $value;
                return true;
            });

        AIH_Push::record_outbid_event('bidder-1', 42, 'Sunset Painting', 'ART-2026-001');

        $this->assertIsArray($storedValue);
        $this->assertCount(1, $storedValue);
        $this->assertSame(42, $storedValue[0]['art_piece_id']);
        $this->assertSame('Sunset Painting', $storedValue[0]['title']);
        $this->assertSame('ART-2026-001', $storedValue[0]['catalog_art_id']);
        $this->assertArrayHasKey('time', $storedValue[0]);
    }

    /**
     * Recording an outbid event without catalog_art_id defaults to empty string.
     */
    public function testRecordOutbidEventDefaultsEmptyCatalogArtId(): void
    {
        $storedValue = null;

        Functions\stubs(['get_transient' => false]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$storedValue) {
                $storedValue = $value;
                return true;
            });

        AIH_Push::record_outbid_event('bidder-1', 42, 'Sunset Painting');

        $this->assertIsArray($storedValue);
        $this->assertSame('', $storedValue[0]['catalog_art_id']);
    }

    /**
     * Outbid events are stored under the correct transient key.
     */
    public function testRecordOutbidEventUsesCorrectTransientKey(): void
    {
        $storedKey = null;

        Functions\stubs(['get_transient' => false]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$storedKey) {
                $storedKey = $key;
                return true;
            });

        AIH_Push::record_outbid_event('bidder-99', 10, 'Test Art', 'ART-001');

        $this->assertSame('aih_outbid_bidder-99', $storedKey);
    }

    /**
     * Multiple outbid events append to existing transient.
     */
    public function testRecordOutbidEventAppendsToExisting(): void
    {
        $storedValue = null;
        $existing = [
            ['art_piece_id' => 1, 'title' => 'First', 'catalog_art_id' => 'ART-A', 'time' => 1000],
        ];

        Functions\stubs(['get_transient' => $existing]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$storedValue) {
                $storedValue = $value;
                return true;
            });

        AIH_Push::record_outbid_event('bidder-1', 2, 'Second', 'ART-B');

        $this->assertIsArray($storedValue);
        $this->assertCount(2, $storedValue);
        $this->assertSame('ART-A', $storedValue[0]['catalog_art_id']);
        $this->assertSame('ART-B', $storedValue[1]['catalog_art_id']);
    }

    // ========== consume_outbid_events ==========

    /**
     * Consuming outbid events returns and deletes the transient.
     */
    public function testConsumeOutbidEventsReturnsAndDeletes(): void
    {
        $events = [
            ['art_piece_id' => 42, 'title' => 'Sunset', 'catalog_art_id' => 'ART-001', 'time' => time()],
        ];

        Functions\expect('get_transient')
            ->once()
            ->with('aih_outbid_bidder-1')
            ->andReturn($events);

        Functions\expect('delete_transient')
            ->once()
            ->with('aih_outbid_bidder-1')
            ->andReturn(true);

        $result = AIH_Push::consume_outbid_events('bidder-1');

        $this->assertSame($events, $result);
        $this->assertSame('ART-001', $result[0]['catalog_art_id']);
    }

    /**
     * Consuming when no events exist returns empty array.
     */
    public function testConsumeOutbidEventsReturnsEmptyWhenNone(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->with('aih_outbid_bidder-1')
            ->andReturn(false);

        Functions\expect('delete_transient')->never();

        $result = AIH_Push::consume_outbid_events('bidder-1');

        $this->assertSame([], $result);
    }

    // ========== URL resolution (check_outbid integration) ==========

    /**
     * get_art_url resolves catalog_art_id to a proper URL.
     *
     * This verifies the integration point: check_outbid calls get_art_url
     * with the catalog_art_id from the event, producing a clean URL that
     * uses the catalog ID (not the database row ID).
     */
    public function testGetArtUrlUsesCatalogArtId(): void
    {
        // Reset page URL cache
        $ref = new \ReflectionClass(AIH_Template_Helper::class);
        $cache = $ref->getProperty('page_cache');
        $cache->setValue(null, []);

        Functions\stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_option' => function ($key, $default = false) {
                if ($key === 'aih_gallery_page') {
                    return 10;
                }
                return $default;
            },
            'get_permalink' => 'https://aihgallery.org/live/',
            'trailingslashit' => function ($string) {
                return rtrim($string, '/') . '/';
            },
        ]);

        $url = AIH_Template_Helper::get_art_url('ART-2026-001');

        $this->assertSame('https://aihgallery.org/live/art/ART-2026-001/', $url);
    }

    /**
     * get_art_url encodes special characters in catalog_art_id.
     */
    public function testGetArtUrlEncodesSpecialCharacters(): void
    {
        // Reset page URL cache
        $ref = new \ReflectionClass(AIH_Template_Helper::class);
        $cache = $ref->getProperty('page_cache');
        $cache->setValue(null, []);

        Functions\stubs([
            'get_transient' => false,
            'set_transient' => true,
            'get_option' => function ($key, $default = false) {
                if ($key === 'aih_gallery_page') {
                    return 10;
                }
                return $default;
            },
            'get_permalink' => 'https://aihgallery.org/live/',
            'trailingslashit' => function ($string) {
                return rtrim($string, '/') . '/';
            },
        ]);

        $url = AIH_Template_Helper::get_art_url('ART 001');

        $this->assertSame('https://aihgallery.org/live/art/ART%20001/', $url);
    }
}
