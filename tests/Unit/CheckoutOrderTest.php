<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Auth;
use AIH_Checkout;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AIH_Checkout: get_won_items, create_order, get_order,
 * get_order_by_number, get_bidder_payment_statuses, delete_order.
 */
class CheckoutOrderTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!class_exists('AIH_Auth')) {
            require_once __DIR__ . '/../../includes/class-aih-auth.php';
        }

        // Reset singletons
        $ref = new \ReflectionClass(AIH_Checkout::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        $ref2 = new \ReflectionClass(AIH_Database::class);
        $prop2 = $ref2->getProperty('cached_year');
        $prop2->setValue(null, null);

        $ref3 = new \ReflectionClass(AIH_Auth::class);
        $prop3 = $ref3->getProperty('instance');
        $prop3->setValue(null, null);

        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

            /** @var list<list<string>> */
            public array $get_col_queue = [];
            /** @var list<?object> */
            public array $get_row_queue = [];
            /** @var list<mixed> */
            public array $get_var_queue = [];
            /** @var list<list<object>> */
            public array $get_results_queue = [];
            /** @var list<string> */
            public array $queries = [];
            /** @var list<array{table: string, data: array}> */
            public array $insert_log = [];
            /** @var list<array{table: string, data: array, where: array}> */
            public array $update_log = [];
            /** @var list<array{table: string, where: array}> */
            public array $delete_log = [];

            public function query(string $sql): bool
            {
                $this->queries[] = $sql;
                return true;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_row(string $sql = ''): ?object
            {
                $this->queries[] = $sql;
                return array_shift($this->get_row_queue);
            }

            public function get_var(string $sql = ''): mixed
            {
                $this->queries[] = $sql;
                return array_shift($this->get_var_queue);
            }

            /** @return list<string> */
            public function get_col(string $sql = ''): array
            {
                $this->queries[] = $sql;
                return array_shift($this->get_col_queue) ?? [];
            }

            /** @return list<object> */
            public function get_results(string $sql = ''): array
            {
                $this->queries[] = $sql;
                return array_shift($this->get_results_queue) ?? [];
            }

            public function insert(string $table, array $data, array|string|null $format = null): int|false
            {
                $this->insert_log[] = ['table' => $table, 'data' => $data];
                return 1;
            }

            public function update(string $table, array $data, array $where, array|null $format = null, array|null $where_format = null): int|false
            {
                $this->update_log[] = ['table' => $table, 'data' => $data, 'where' => $where];
                return 1;
            }

            public function delete(string $table, array $where, array|null $where_format = null): int|false
            {
                $this->delete_log[] = ['table' => $table, 'where' => $where];
                return 1;
            }

            public function esc_like(string $text): string
            {
                return addcslashes($text, '_%\\');
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_tax_rate' => 0,
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
            'wp_timezone' => fn() => new \DateTimeZone('America/New_York'),
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-01-15 10:00:00') : '2026-01-15 10:00:00',
            'sanitize_key' => fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            '__' => fn($text) => $text,
            'wp_generate_password' => fn(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false) => 'ABCD1234',
            'wp_parse_args' => function ($args, $defaults) {
                return array_merge($defaults, $args);
            },
            'sanitize_text_field' => fn($v) => trim(strip_tags((string) $v)),
            'get_transient' => false,
            'set_transient' => true,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    private function setAuthBidder(string $confirmationCode): void
    {
        $auth = AIH_Auth::get_instance();
        $ref = new \ReflectionClass($auth);
        $prop = $ref->getProperty('session_data');
        $prop->setValue($auth, ['confirmation_code' => $confirmationCode]);
    }

    // ── get_won_items ──

    public function testGetWonItemsReturnsWinningBidsWithoutExistingOrder(): void
    {
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => 1, 'art_piece_id' => 1, 'winning_amount' => 100.00, 'title' => 'Piece A'],
            (object) ['id' => 2, 'art_piece_id' => 2, 'winning_amount' => 250.00, 'title' => 'Piece B'],
        ];

        $checkout = AIH_Checkout::get_instance();
        $items = $checkout->get_won_items('BIDDER1');

        $this->assertCount(2, $items);
        $this->assertSame(100.00, $items[0]->winning_amount);
        $this->assertSame(250.00, $items[1]->winning_amount);
    }

    public function testGetWonItemsReturnsEmptyWhenNoneWon(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $checkout = AIH_Checkout::get_instance();
        $items = $checkout->get_won_items('NOBODY');

        $this->assertIsArray($items);
        $this->assertCount(0, $items);
    }

    public function testGetWonItemsQueryIncludesCorrectFilters(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $checkout = AIH_Checkout::get_instance();
        $checkout->get_won_items('BIDDER1');

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('is_winning = 1', $sql);
        $this->assertStringContainsString('cancelled', $sql);
    }

    // ── get_bidder_payment_statuses ──

    public function testGetBidderPaymentStatusesReturnsMap(): void
    {
        $this->wpdb->get_results_queue[] = [
            (object) ['art_piece_id' => '10', 'payment_status' => 'paid'],
            (object) ['art_piece_id' => '20', 'payment_status' => 'pending'],
            (object) ['art_piece_id' => '10', 'payment_status' => 'cancelled'], // duplicate, should keep first
        ];

        $checkout = AIH_Checkout::get_instance();
        $map = $checkout->get_bidder_payment_statuses('BIDDER1');

        $this->assertSame('paid', $map['10']);
        $this->assertSame('pending', $map['20']);
        $this->assertCount(2, $map); // deduped
    }

    public function testGetBidderPaymentStatusesEmptyWhenNone(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $checkout = AIH_Checkout::get_instance();
        $map = $checkout->get_bidder_payment_statuses('NOBODY');

        $this->assertIsArray($map);
        $this->assertCount(0, $map);
    }

    // ── get_order ──

    public function testGetOrderReturnsOrderWithItems(): void
    {
        $this->wpdb->get_row_queue[] = (object) [
            'id' => 42,
            'order_number' => 'AIH-TEST1234',
            'bidder_id' => 'BIDDER1',
            'subtotal' => 500.00,
            'tax' => 0.00,
            'total' => 500.00,
            'name_first' => 'John',
            'name_last' => 'Doe',
            'email' => 'john@example.com',
        ];
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => 1, 'art_piece_id' => 10, 'winning_bid' => 300.00, 'title' => 'Art A'],
            (object) ['id' => 2, 'art_piece_id' => 20, 'winning_bid' => 200.00, 'title' => 'Art B'],
        ];

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order(42);

        $this->assertNotNull($order);
        $this->assertSame('AIH-TEST1234', $order->order_number);
        $this->assertSame('BIDDER1', $order->bidder_id);
        $this->assertCount(2, $order->items);
    }

    public function testGetOrderReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_row_queue[] = null;

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order(999);

        $this->assertNull($order);
    }

    // ── get_order_by_number ──

    public function testGetOrderByNumberFindsOrder(): void
    {
        // get_var returns order ID
        $this->wpdb->get_var_queue[] = '42';
        // get_order internals
        $this->wpdb->get_row_queue[] = (object) [
            'id' => 42,
            'order_number' => 'AIH-ABCD1234',
            'bidder_id' => 'BIDDER1',
            'name_first' => 'Jane',
            'name_last' => 'Doe',
        ];
        $this->wpdb->get_results_queue[] = [];

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number('AIH-ABCD1234');

        $this->assertNotNull($order);
        $this->assertSame('AIH-ABCD1234', $order->order_number);
    }

    public function testGetOrderByNumberReturnsNullWhenNotFound(): void
    {
        $this->wpdb->get_var_queue[] = null;

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number('AIH-NOTEXIST');

        $this->assertNull($order);
    }

    // ── create_order ──

    public function testCreateOrderSucceeds(): void
    {
        $this->setAuthBidder('BIDDER1');

        // get_won_items returns items
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => 10, 'art_piece_id' => 10, 'winning_amount' => 300.00, 'winning_bid' => 300.00],
        ];
        // order number collision check → null (no collision)
        $this->wpdb->get_var_queue[] = null;
        // get_order after insert: order row
        $this->wpdb->get_row_queue[] = (object) [
            'id' => 1,
            'order_number' => 'AIH-ABCD1234',
            'bidder_id' => 'BIDDER1',
            'subtotal' => 300.00,
            'tax' => 0.00,
            'total' => 300.00,
            'name_first' => 'Test',
            'name_last' => 'User',
        ];
        // get_order items
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => 1, 'art_piece_id' => 10, 'winning_bid' => 300.00],
        ];

        // Mock Pushpay to return a URL
        if (!class_exists('AIH_Pushpay_API')) {
            require_once __DIR__ . '/../../includes/class-aih-pushpay.php';
        }
        $ref = new \ReflectionClass(\AIH_Pushpay_API::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_tax_rate' => 0,
                    'aih_pushpay_merchant_handle' => 'test-merchant',
                    'aih_pushpay_merchant_key' => 'test-key',
                    'aih_pushpay_base_url' => 'https://pushpay.com/pay/',
                    'aih_pushpay_fund' => 'art-in-heaven',
                ];
                return $options[$key] ?? $default;
            },
        ]);

        // Clear $_POST to avoid idempotency key path
        $_POST = [];

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->create_order('BIDDER1');

        $this->assertTrue($result['success']);
        $this->assertSame('AIH-ABCD1234', $result['order_number']);
        $this->assertArrayHasKey('pushpay_url', $result);
        $this->assertArrayHasKey('totals', $result);

        // Verify order was inserted
        $this->assertNotEmpty($this->wpdb->insert_log);
        $order_insert = $this->wpdb->insert_log[0]['data'];
        $this->assertSame('BIDDER1', $order_insert['bidder_id']);
        $this->assertSame('pending', $order_insert['payment_status']);
    }

    public function testCreateOrderFailsWhenNoItems(): void
    {
        $this->setAuthBidder('BIDDER1');
        $this->wpdb->get_results_queue[] = []; // no won items

        $_POST = [];

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->create_order('BIDDER1');

        $this->assertFalse($result['success']);
        $this->assertSame('No items to checkout.', $result['message']);
    }

    // ── delete_order ──

    public function testDeleteOrderSucceeds(): void
    {
        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->delete_order(42);

        $this->assertSame(1, $result);
        $this->assertCount(2, $this->wpdb->delete_log); // items + order
    }

    public function testDeleteOrderRollbackOnFailure(): void
    {
        // Override delete to return false for order deletion
        $this->wpdb = new class extends \stdClass {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';
            /** @var list<string> */
            public array $queries = [];
            private int $deleteCount = 0;

            public function query(string $sql): bool
            {
                $this->queries[] = $sql;
                return true;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function delete(string $table, array $where, array|null $where_format = null): int|false
            {
                $this->deleteCount++;
                // First delete (items) succeeds, second (order) fails
                return $this->deleteCount === 1 ? 1 : false;
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        // Reset singleton to pick up new wpdb
        $ref = new \ReflectionClass(AIH_Checkout::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->delete_order(42);

        $this->assertFalse($result);
        $this->assertContains('ROLLBACK', $this->wpdb->queries);
    }
}
