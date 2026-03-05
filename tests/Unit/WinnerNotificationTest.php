<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Push;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for winner notification functionality.
 *
 * Verifies that winner events are correctly recorded, consumed,
 * and that push notifications are gated by the push enabled option.
 */
class WinnerNotificationTest extends TestCase
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

    // ========== record / consume winner events ==========

    /**
     * Recording a winner event stores it in a transient.
     */
    public function testRecordWinnerEventSetsTransient(): void
    {
        $storedKey = null;
        $storedValue = null;

        Functions\stubs(['get_transient' => false]);
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key, $value, $ttl) use (&$storedKey, &$storedValue) {
                $storedKey = $key;
                $storedValue = $value;
                return true;
            });

        $this->stubGetOption(true);

        AIH_Push::record_winner_event('bidder-42', 100, 'Test Artwork');

        $this->assertSame('aih_won_bidder-42', $storedKey);
        $this->assertIsArray($storedValue);
        $this->assertCount(1, $storedValue);
        $this->assertSame(100, $storedValue[0]['art_piece_id']);
        $this->assertSame('Test Artwork', $storedValue[0]['title']);
        $this->assertArrayHasKey('time', $storedValue[0]);
    }

    /**
     * Consuming winner events returns and deletes the transient.
     */
    public function testConsumeWinnerEventsReturnsAndDeletes(): void
    {
        $events = [
            ['art_piece_id' => 100, 'title' => 'Test Artwork', 'time' => time()],
        ];

        Functions\expect('get_transient')
            ->once()
            ->with('aih_won_bidder-42')
            ->andReturn($events);

        Functions\expect('delete_transient')
            ->once()
            ->with('aih_won_bidder-42')
            ->andReturn(true);

        $result = AIH_Push::consume_winner_events('bidder-42');

        $this->assertSame($events, $result);
    }

    /**
     * Consuming when no events exist returns empty array without deleting.
     */
    public function testConsumeWinnerEventsReturnsEmptyWhenNone(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->with('aih_won_bidder-42')
            ->andReturn(false);

        Functions\expect('delete_transient')->never();

        $result = AIH_Push::consume_winner_events('bidder-42');

        $this->assertSame([], $result);
    }

    // ========== notification dedup flags ==========

    /**
     * was_winner_notified returns false when no flag exists.
     */
    public function testWasWinnerNotifiedReturnsFalseInitially(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->with('aih_won_notified_bidder-42_100')
            ->andReturn(false);

        $this->assertFalse(AIH_Push::was_winner_notified('bidder-42', 100));
    }

    /**
     * mark_winner_notified sets a transient flag.
     */
    public function testMarkWinnerNotifiedSetsFlag(): void
    {
        $called = false;
        Functions\expect('set_transient')
            ->once()
            ->with('aih_won_notified_bidder-42_100', 1, DAY_IN_SECONDS)
            ->andReturnUsing(function () use (&$called) {
                $called = true;
                return true;
            });

        AIH_Push::mark_winner_notified('bidder-42', 100);

        $this->assertTrue($called);
    }

    /**
     * was_winner_notified returns true after marking.
     */
    public function testWasWinnerNotifiedReturnsTrueAfterMarking(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->with('aih_won_notified_bidder-42_100')
            ->andReturn(1);

        $this->assertTrue(AIH_Push::was_winner_notified('bidder-42', 100));
    }

    // ========== handle_winner_event ==========

    /**
     * handle_winner_event records polling event and adds shutdown hook when push enabled.
     */
    public function testHandleWinnerEventRecordsAndQueuesPush(): void
    {
        $this->stubGetOption(true);
        Functions\stubs(['get_transient' => false]);

        $transientKey = null;
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key) use (&$transientKey) {
                $transientKey = $key;
                return true;
            });

        AIH_Push::get_instance()->handle_winner_event('bidder-42', 100, 'Test Artwork');

        $this->assertStringStartsWith('aih_won_', $transientKey);
        $this->assertNotFalse(has_action('shutdown'));
    }

    /**
     * handle_winner_event records polling event but skips push when disabled.
     */
    public function testHandleWinnerEventSkipsPushWhenDisabled(): void
    {
        $this->stubGetOption(false);
        Functions\stubs(['get_transient' => false]);

        Functions\expect('set_transient')
            ->once()
            ->andReturn(true);

        AIH_Push::get_instance()->handle_winner_event('bidder-42', 100, 'Test Artwork');

        $this->assertFalse(has_action('shutdown'));
    }

    // ========== HELPERS ==========

    private function stubGetOption(bool $pushEnabled): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) use ($pushEnabled) {
                $options = [
                    'aih_auction_year'  => '2026',
                    'aih_push_enabled'  => $pushEnabled ? 1 : 0,
                ];
                return $options[$key] ?? $default;
            },
        ]);
    }
}
