<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Bid;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class BidTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset AIH_Database cached year
        $ref = new \ReflectionClass(AIH_Database::class);
        $prop = $ref->getProperty('cached_year');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        // Mock $wpdb with a queue-based get_row for flexibility
        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

            /** @var list<object|null> */
            private array $get_row_queue = [];

            public function pushGetRow(?object $value): void
            {
                $this->get_row_queue[] = $value;
            }

            public function query(string $sql): bool
            {
                return true;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_row(string $sql = ''): ?object
            {
                if (empty($this->get_row_queue)) {
                    return null;
                }
                return array_shift($this->get_row_queue);
            }

            public function insert(string $table, array $data, array|null $format = null): int|false
            {
                return 1;
            }

            public function update(string $table, array $data, array $where, array|null $format = null, array|null $where_format = null): int|false
            {
                return 1;
            }

            public function get_var(string $sql = ''): mixed
            {
                return null;
            }

            public function get_results(string $sql = ''): array
            {
                return [];
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        // Set IP address for bid recording
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Stub WordPress functions
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_max_bid' => 50000,
                    'aih_bid_increment' => 1,
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
            'current_time' => fn() => '2026-01-15 10:00:00',
            'sanitize_text_field' => fn($v) => trim(strip_tags((string) $v)),
            'sanitize_key' => fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            '__' => fn($text) => $text,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb'], $_SERVER['REMOTE_ADDR']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper to create a mock art piece object for $wpdb->get_row.
     */
    private function makeArtPiece(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'starting_bid' => 100.00,
            'auction_start' => '2026-01-01 00:00:00',
            'auction_end' => '2026-12-31 23:59:59',
            'status' => 'active',
            'computed_status' => 'active',
            'current_highest' => 0,
        ], $overrides);
    }

    // ── Art piece not found ──

    public function testPlaceBidArtPieceNotFound(): void
    {
        // get_row returns null → art piece not found
        $bid = new AIH_Bid();
        $result = $bid->place_bid(999, 'BIDDER1', 150.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    // ── Auction status checks ──

    public function testPlaceBidAuctionEnded(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'computed_status' => 'ended',
            'status' => 'active',
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 150.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ended', $result['message']);
    }

    public function testPlaceBidAuctionScheduled(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'computed_status' => 'upcoming',
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 150.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not started', $result['message']);
    }

    public function testPlaceBidAuctionDraft(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'computed_status' => 'draft',
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 150.00);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not accepting bids', $result['message']);
    }



    // ── Maximum bid enforcement ──

    public function testPlaceBidExceedsMaximum(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece());

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 50001.00);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['bid_too_high']);
    }

    public function testPlaceBidAtExactMaximumSucceeds(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece());

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 50000.00);

        $this->assertTrue($result['success']);
    }

    // ── Starting bid enforcement ──

    public function testPlaceBidBelowStartingBid(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 0,
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 99.99);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['bid_too_low']);
        $this->assertArrayHasKey('bid_id', $result);
    }

    public function testPlaceBidAtExactStartingBidSucceeds(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 0,
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 100.00);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('bid_id', $result);
    }

    // ── Minimum increment enforcement ──

    public function testPlaceBidBelowMinimumIncrement(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 200.00,
        ]));

        $bid = new AIH_Bid();
        // Current highest is 200, min increment is 1, so need >= 201
        $result = $bid->place_bid(1, 'BIDDER1', 200.50);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['bid_too_low']);
    }

    public function testPlaceBidAtExactMinimumIncrementSucceeds(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 200.00,
        ]));

        $bid = new AIH_Bid();
        // 200 + 1 = 201 minimum
        $result = $bid->place_bid(1, 'BIDDER1', 201.00);

        $this->assertTrue($result['success']);
    }

    // ── Successful bid ──

    public function testPlaceBidSuccessful(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 0,
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 250.00);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('bid_id', $result);
        $this->assertEquals(250.00, $result['current_bid']);
        $this->assertStringContainsString('successfully', $result['message']);
    }

    public function testPlaceBidSuccessfulAboveCurrentHighest(): void
    {
        $this->wpdb->pushGetRow($this->makeArtPiece([
            'starting_bid' => 100.00,
            'current_highest' => 500.00,
        ]));

        $bid = new AIH_Bid();
        $result = $bid->place_bid(1, 'BIDDER1', 600.00);

        $this->assertTrue($result['success']);
        $this->assertEquals(600.00, $result['current_bid']);
    }
}
