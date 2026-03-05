<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Push;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the unified AIH_Push::send_push() method.
 */
class SendPushTest extends TestCase
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
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * send_push returns early when the bidder has no subscriptions.
     */
    public function testSendPushReturnsEarlyWhenNoSubscriptions(): void
    {
        $wpdb = $this->createWpdb([]);
        $GLOBALS['wpdb'] = $wpdb;

        $this->stubGetOption();

        $payload = [
            'type'         => 'outbid',
            'title'        => "You've been outbid!",
            'body'         => 'Someone outbid you on "Test Art".',
            'art_piece_id' => 100,
            'url'          => 'https://example.com/art/A-001',
            'tag'          => 'outbid-100',
        ];

        // When there are no subscriptions, send_push should return before encoding the payload.
        Functions\expect('wp_json_encode')->never();

        AIH_Push::get_instance()->send_push('bidder-42', $payload);

        // Verify payload was not mutated (icon not appended) since we returned early
        $this->assertArrayNotHasKey('icon', $payload);
    }

    /**
     * send_push has unified (bidder_id, payload) signature and send_winner_push is removed.
     */
    public function testSendPushHasUnifiedSignature(): void
    {
        $ref = new \ReflectionClass(AIH_Push::class);

        $this->assertTrue($ref->hasMethod('send_push'));
        $this->assertFalse($ref->hasMethod('send_winner_push'), 'send_winner_push should be removed');

        $method = $ref->getMethod('send_push');
        $this->assertTrue($method->isPublic());

        $params = $method->getParameters();
        $this->assertCount(2, $params, 'send_push should accept exactly 2 parameters');
        $this->assertSame('bidder_id', $params[0]->getName());
        $this->assertSame('payload', $params[1]->getName());
        $this->assertSame('array', $params[1]->getType()->getName());
    }

    // ========== HELPERS ==========

    private function stubGetOption(): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_push_enabled' => 1,
                ];
                return $options[$key] ?? $default;
            },
        ]);
    }

    private function createWpdb(array $results): object
    {
        return new class($results) {
            public string $prefix = 'wp_';
            public string $last_error = '';
            private array $results;

            public function __construct(array $results)
            {
                $this->results = $results;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_results(?string $query = null, $output = null): array
            {
                return $this->results;
            }
        };
    }
}
