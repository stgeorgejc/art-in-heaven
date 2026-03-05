<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Push;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the admin push toggle (aih_push_enabled option).
 *
 * Verifies that handle_outbid_event correctly gates push sending
 * based on the option while always recording polling fallback events.
 */
class PushToggleTest extends TestCase
{
    private object $wpdb;

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

        // Mock wpdb
        $this->wpdb = $this->createWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        // Default stubs — set_transient intentionally omitted so individual
        // tests can use Functions\expect() for it without conflicts.
        Functions\stubs([
            'sanitize_text_field' => function ($v) { return $v; },
            'get_transient'       => false,
            'wp_date'             => fn() => '2026',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * When push is enabled (default), the shutdown hook should be added.
     */
    public function testShutdownHookAddedWhenPushEnabled(): void
    {
        $this->setUpOutbidScenario();
        $this->stubGetOption(true);
        Functions\stubs(['set_transient' => true]);

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'new-bidder', 500.00);

        $this->assertNotFalse(has_action('shutdown'));
    }

    /**
     * When push is disabled, no shutdown hook should be added.
     */
    public function testShutdownHookSkippedWhenPushDisabled(): void
    {
        $this->setUpOutbidScenario();
        $this->stubGetOption(false);
        Functions\stubs(['set_transient' => true]);

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'new-bidder', 500.00);

        $this->assertFalse(has_action('shutdown'));
    }

    /**
     * Polling fallback event is always recorded, even when push is disabled.
     */
    public function testPollingEventRecordedWhenPushDisabled(): void
    {
        $this->setUpOutbidScenario();
        $this->stubGetOption(false);

        $transientKey = null;
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key) use (&$transientKey) {
                $transientKey = $key;
                return true;
            });

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'new-bidder', 500.00);

        $this->assertStringStartsWith('aih_outbid_', $transientKey);
    }

    /**
     * Polling fallback event is also recorded when push IS enabled.
     */
    public function testPollingEventRecordedWhenPushEnabled(): void
    {
        $this->setUpOutbidScenario();
        $this->stubGetOption(true);

        $transientKey = null;
        Functions\expect('set_transient')
            ->once()
            ->andReturnUsing(function ($key) use (&$transientKey) {
                $transientKey = $key;
                return true;
            });

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'new-bidder', 500.00);

        $this->assertStringStartsWith('aih_outbid_', $transientKey);
    }

    /**
     * When there is no previous bidder (first bid), nothing is recorded or pushed.
     */
    public function testFirstBidSkipsNotification(): void
    {
        $this->wpdb->setGetVarReturn(null); // No outbid bidder
        $this->stubGetOption(true);

        Functions\expect('set_transient')->never();

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'first-bidder', 100.00);

        $this->assertFalse(has_action('shutdown'));
    }

    // ========== HELPERS ==========

    private function setUpOutbidScenario(): void
    {
        $this->wpdb->setGetVarReturn('outbid-bidder');
        $this->wpdb->setGetRowReturn((object) [
            'title'  => 'Test Artwork',
            'art_id' => 'A-001',
        ]);
    }

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

    private function createWpdb(): object
    {
        return new class {
            public string $prefix = 'wp_';
            public string $last_error = '';
            private ?string $getVarReturn = null;
            private ?object $getRowReturn = null;

            public function setGetVarReturn(?string $value): void
            {
                $this->getVarReturn = $value;
            }

            public function setGetRowReturn(?object $value): void
            {
                $this->getRowReturn = $value;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_var(?string $query = null): ?string
            {
                return $this->getVarReturn;
            }

            public function get_row($query = null, $output = null, $y = 0): ?object
            {
                return $this->getRowReturn;
            }
        };
    }
}
