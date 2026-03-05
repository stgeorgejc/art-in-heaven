<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the auction_ended SSE event publish logic.
 *
 * The auction_ended event is published from poll_status() when it detects
 * a newly ended auction. A transient dedup flag prevents re-publishing
 * on every poll request.
 *
 * Since poll_status() is a full AJAX handler, we replicate the relevant
 * control flow in isolation (same pattern as AjaxPushVerifyTest).
 */
class AuctionEndedSseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * First detection of an ended auction publishes the SSE event and sets dedup transient.
     */
    public function testPublishesAuctionEndedOnFirstDetection(): void
    {
        $published = false;
        $transientSet = false;

        Functions\expect('get_transient')
            ->once()
            ->with('aih_ended_sse_42')
            ->andReturn(false);

        Functions\expect('set_transient')
            ->once()
            ->with('aih_ended_sse_42', 1, HOUR_IN_SECONDS)
            ->andReturnUsing(function () use (&$transientSet) {
                $transientSet = true;
                return true;
            });

        $this->runAuctionEndedLogic(42, true, function () use (&$published) {
            $published = true;
            return true;
        });

        $this->assertTrue($transientSet, 'Dedup transient should be set');
        $this->assertTrue($published, 'Mercure event should be published');
    }

    /**
     * Subsequent detection of the same ended auction does NOT re-publish.
     */
    public function testSkipsPublishWhenDedupTransientExists(): void
    {
        $published = false;

        Functions\expect('get_transient')
            ->once()
            ->with('aih_ended_sse_42')
            ->andReturn(1); // Dedup transient exists

        Functions\expect('set_transient')->never();

        $this->runAuctionEndedLogic(42, true, function () use (&$published) {
            $published = true;
            return true;
        });

        $this->assertFalse($published, 'Mercure event should NOT be re-published');
    }

    /**
     * When Mercure is not available, no publish or transient operations occur.
     */
    public function testSkipsWhenMercureNotAvailable(): void
    {
        $published = false;

        Functions\expect('get_transient')->never();
        Functions\expect('set_transient')->never();

        $this->runAuctionEndedLogic(42, false, function () use (&$published) {
            $published = true;
            return true;
        });

        $this->assertFalse($published, 'Should not publish when Mercure is unavailable');
    }

    /**
     * Active auctions (not ended) do not trigger any publish.
     */
    public function testActiveAuctionDoesNotPublish(): void
    {
        $published = false;

        Functions\expect('get_transient')->never();
        Functions\expect('set_transient')->never();

        // Replicate the status check — only 'ended' triggers the logic
        $item = ['status' => 'active'];

        if ($item['status'] === 'ended') {
            $this->runAuctionEndedLogic(42, true, function () use (&$published) {
                $published = true;
            });
        }

        $this->assertFalse($published, 'Active auction should not trigger publish');
    }

    /**
     * Replicate the auction_ended SSE publish logic from poll_status().
     *
     * Mirrors the control flow in class-aih-ajax.php poll_status() method:
     * if ended + Mercure available + no dedup transient → publish + set transient.
     *
     * @param int      $artPieceId       The art piece ID
     * @param bool     $mercureAvailable Whether Mercure is configured and enabled
     * @param callable $publishFn        Called when a Mercure publish would occur
     */
    private function runAuctionEndedLogic(int $artPieceId, bool $mercureAvailable, callable $publishFn): void
    {
        if ($mercureAvailable && !get_transient('aih_ended_sse_' . $artPieceId)) {
            $published = $publishFn();
            if ($published !== false) {
                set_transient('aih_ended_sse_' . $artPieceId, 1, HOUR_IN_SECONDS);
            }
        }
    }
}
