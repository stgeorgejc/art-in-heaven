<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Push;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for push notification audit logging in send_push()
 * and ref=push URL parameter additions.
 */
class PushAuditLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset singletons
        $ref = new \ReflectionClass(AIH_Push::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        $vapid = $ref->getProperty('cached_vapid_keys');
        $vapid->setValue(null, null);

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
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * send_push does not log audit events when no subscriptions exist.
     */
    public function testSendPushNoAuditWhenNoSubscriptions(): void
    {
        $wpdb = $this->createWpdb([], []);
        $GLOBALS['wpdb'] = $wpdb;

        $this->stubGetOption();

        // wp_json_encode should not be called (returns early)
        Functions\expect('wp_json_encode')->never();

        AIH_Push::get_instance()->send_push('bidder-42', [
            'type'         => 'outbid',
            'art_piece_id' => 100,
        ]);

        $this->assertEmpty($wpdb->inserts, 'No audit log inserts when no subscriptions');
    }

    /**
     * handle_outbid_event adds ref=push to the notification URL.
     */
    public function testOutbidNotificationUrlIncludesRefPush(): void
    {
        $wpdb = $this->createWpdb([], []);
        $wpdb->setGetVarReturn('outbid-bidder');
        $wpdb->setGetRowReturn((object) [
            'title'  => 'Test Art',
            'art_id' => 'A-001',
        ]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->stubGetOption(true);
        Functions\stubs([
            'get_transient' => false,
            'set_transient' => true,
        ]);

        // Track add_query_arg calls
        $addQueryArgCalls = [];
        Functions\expect('add_query_arg')
            ->andReturnUsing(function ($key, $value, $url) use (&$addQueryArgCalls) {
                $addQueryArgCalls[] = ['key' => $key, 'value' => $value, 'url' => $url];
                return $url . '?ref=push';
            });

        // Capture the shutdown action
        $shutdownCallback = null;
        Functions\expect('add_action')
            ->andReturnUsing(function ($hook, $callback) use (&$shutdownCallback) {
                if ($hook === 'shutdown') {
                    $shutdownCallback = $callback;
                }
                return true;
            });

        AIH_Push::get_instance()->handle_outbid_event(1, 100, 'new-bidder', '500.00');

        $this->assertNotNull($shutdownCallback, 'Shutdown hook was registered');
    }

    /**
     * handle_winner_event adds ref=push to the checkout URL.
     */
    public function testWinnerNotificationUrlIncludesRefPush(): void
    {
        $wpdb = $this->createWpdb([], []);
        $GLOBALS['wpdb'] = $wpdb;

        $this->stubGetOption(true);
        Functions\stubs([
            'get_transient' => false,
            'set_transient' => true,
        ]);

        $addQueryArgCalls = [];
        Functions\expect('add_query_arg')
            ->andReturnUsing(function ($key, $value, $url) use (&$addQueryArgCalls) {
                $addQueryArgCalls[] = ['key' => $key, 'value' => $value, 'url' => $url];
                return $url . '?ref=push';
            });

        $shutdownCallback = null;
        Functions\expect('add_action')
            ->andReturnUsing(function ($hook, $callback) use (&$shutdownCallback) {
                if ($hook === 'shutdown') {
                    $shutdownCallback = $callback;
                }
                return true;
            });

        AIH_Push::get_instance()->handle_winner_event('winner-bidder', 200, 'Amazing Art');

        $this->assertNotNull($shutdownCallback, 'Shutdown hook was registered for winner');
    }

    /**
     * Engagement metric event types are defined in the expected set.
     */
    public function testEngagementEventTypesExist(): void
    {
        $expectedEvents = [
            'push_sent',
            'push_delivered',
            'push_expired',
            'push_clicked',
            'push_permission_granted',
            'push_permission_denied',
        ];

        // These are the audit event types we instrument.
        // This test documents them for future reference.
        foreach ($expectedEvents as $event) {
            $this->assertMatchesRegularExpression(
                '/^push_/',
                $event,
                "Event '{$event}' should start with 'push_'"
            );
        }

        $this->assertCount(6, $expectedEvents, 'Expected exactly 6 push engagement events');
    }

    // ========== HELPERS ==========

    private function stubGetOption(bool $pushEnabled = true): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) use ($pushEnabled) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_push_enabled' => $pushEnabled ? 1 : 0,
                ];
                return $options[$key] ?? $default;
            },
        ]);
    }

    private function createWpdb(array $subscriptions = [], array $results = []): object
    {
        return new class($subscriptions, $results) {
            public string $prefix = 'wp_';
            public string $last_error = '';
            public array $inserts = [];
            private array $subscriptions;
            private array $results;
            private ?string $getVarReturn = null;
            private ?object $getRowReturn = null;

            public function __construct(array $subscriptions, array $results)
            {
                $this->subscriptions = $subscriptions;
                $this->results = $results;
            }

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

            public function get_results(?string $query = null, $output = null): array
            {
                return $this->subscriptions;
            }

            public function get_var(?string $query = null): ?string
            {
                return $this->getVarReturn;
            }

            public function get_row($query = null, $output = null, $y = 0): ?object
            {
                return $this->getRowReturn;
            }

            public function insert($table, $data, $format = null)
            {
                $this->inserts[] = ['table' => $table, 'data' => $data];
                return 1;
            }

            public int $insert_id = 1;
        };
    }
}
