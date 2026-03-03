<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Auth;
use AIH_Checkout;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class CheckoutTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Load Auth class after Brain Monkey setUp (has file-scope add_action call)
        if (!class_exists('AIH_Auth')) {
            require_once __DIR__ . '/../../includes/class-aih-auth.php';
        }

        // Reset singletons and static caches
        $ref = new \ReflectionClass(AIH_Checkout::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $ref2 = new \ReflectionClass(AIH_Database::class);
        $prop2 = $ref2->getProperty('cached_year');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);

        // Reset AIH_Auth singleton
        $ref3 = new \ReflectionClass(AIH_Auth::class);
        $prop3 = $ref3->getProperty('instance');
        $prop3->setAccessible(true);
        $prop3->setValue(null, null);

        // Mock $wpdb
        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

            /** @var list<list<string>> Queued return values for get_col() */
            public array $get_col_queue = [];

            /** @var list<?object> Queued return values for get_row() */
            public array $get_row_queue = [];

            /** @var list<array{table: string, data: array}> */
            public array $insert_log = [];

            /** @var list<array{table: string, data: array, where: array}> */
            public array $update_log = [];

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
                return array_shift($this->get_row_queue);
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

            public function get_var(string $sql = ''): mixed
            {
                return null;
            }

            /** @return list<string> */
            public function get_col(string $sql = ''): array
            {
                return array_shift($this->get_col_queue) ?? [];
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        // Stub WordPress functions
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_tax_rate' => 0,
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-01-15 10:00:00') : '2026-01-15 10:00:00',
            'sanitize_key' => fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            '__' => fn($text) => $text,
            'wp_generate_password' => fn() => 'ABCD1234',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Set the authenticated bidder in AIH_Auth's session cache.
     */
    private function setAuthBidder(string $confirmationCode): void
    {
        $auth = AIH_Auth::get_instance();
        $ref = new \ReflectionClass($auth);
        $prop = $ref->getProperty('session_data');
        $prop->setAccessible(true);
        $prop->setValue($auth, ['confirmation_code' => $confirmationCode]);
    }

    /**
     * Helper to create a mock won item.
     */
    private function makeItem(float $amount): object
    {
        return (object) ['winning_amount' => $amount];
    }

    // ── calculate_totals ──

    public function testCalculateTotalsBasic(): void
    {
        $items = [
            $this->makeItem(100.00),
            $this->makeItem(250.00),
            $this->makeItem(75.50),
        ];

        $checkout = AIH_Checkout::get_instance();
        $totals = $checkout->calculate_totals($items);

        $this->assertSame(425.50, $totals['subtotal']);
        $this->assertSame(0.0, $totals['tax']);
        $this->assertSame(0.0, $totals['tax_rate']);
        $this->assertSame(425.50, $totals['total']);
        $this->assertSame(3, $totals['item_count']);
    }

    public function testCalculateTotalsWithTax(): void
    {
        // Override tax rate for this test
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_tax_rate' => 8.25,
                ];
                return $options[$key] ?? $default;
            },
        ]);

        $items = [
            $this->makeItem(100.00),
            $this->makeItem(200.00),
        ];

        $checkout = AIH_Checkout::get_instance();
        $totals = $checkout->calculate_totals($items);

        $this->assertSame(300.00, $totals['subtotal']);
        $this->assertSame(8.25, $totals['tax_rate']);
        $this->assertSame(24.75, $totals['tax']); // 300 * 8.25% = 24.75
        $this->assertSame(324.75, $totals['total']); // 300 + 24.75
        $this->assertSame(2, $totals['item_count']);
    }

    public function testCalculateTotalsEmptyItems(): void
    {
        $checkout = AIH_Checkout::get_instance();
        $totals = $checkout->calculate_totals([]);

        $this->assertSame(0.0, $totals['subtotal']);
        $this->assertSame(0.0, $totals['tax']);
        $this->assertSame(0.0, $totals['total']);
        $this->assertSame(0, $totals['item_count']);
    }

    public function testCalculateTotalsSingleItem(): void
    {
        $checkout = AIH_Checkout::get_instance();
        $totals = $checkout->calculate_totals([$this->makeItem(1500.00)]);

        $this->assertSame(1500.00, $totals['subtotal']);
        $this->assertSame(1500.00, $totals['total']);
        $this->assertSame(1, $totals['item_count']);
    }

    public function testCalculateTotalsRoundsToTwoCents(): void
    {
        // Override tax rate to produce a fractional result
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_tax_rate' => 7.75,
                ];
                return $options[$key] ?? $default;
            },
        ]);

        $items = [$this->makeItem(33.33)];

        $checkout = AIH_Checkout::get_instance();
        $totals = $checkout->calculate_totals($items);

        $this->assertSame(33.33, $totals['subtotal']);
        $this->assertSame(2.58, $totals['tax']); // 33.33 * 7.75% = 2.583075, rounded to 2.58
        $this->assertSame(35.91, $totals['total']); // 33.33 + 2.58
    }

    // ── update_payment_status ──

    public function testUpdatePaymentStatusAcceptsValidStatuses(): void
    {
        $checkout = AIH_Checkout::get_instance();

        $valid_statuses = ['pending', 'paid', 'refunded', 'failed', 'cancelled'];
        foreach ($valid_statuses as $status) {
            $result = $checkout->update_payment_status(1, $status);
            $this->assertNotFalse($result, "Status '$status' should be accepted");
        }
    }

    public function testUpdatePaymentStatusRejectsInvalidStatus(): void
    {
        $checkout = AIH_Checkout::get_instance();

        $this->assertFalse($checkout->update_payment_status(1, 'completed'));
        $this->assertFalse($checkout->update_payment_status(1, 'PAID'));
        $this->assertFalse($checkout->update_payment_status(1, ''));
    }

    // ── cancel_pending_orders ──

    public function testCancelPendingOrdersCancelsAndReturnsCount(): void
    {
        $this->setAuthBidder('BIDDER1');
        $this->wpdb->get_col_queue[] = ['10', '20', '30'];

        $checkout = AIH_Checkout::get_instance();
        $count = $checkout->cancel_pending_orders('BIDDER1');

        $this->assertSame(3, $count);
        $this->assertCount(3, $this->wpdb->update_log);
        foreach ($this->wpdb->update_log as $entry) {
            $this->assertSame('cancelled', $entry['data']['payment_status']);
        }
    }

    public function testCancelPendingOrdersReturnsZeroWhenNone(): void
    {
        $this->setAuthBidder('BIDDER2');
        $this->wpdb->get_col_queue[] = [];

        $checkout = AIH_Checkout::get_instance();
        $count = $checkout->cancel_pending_orders('BIDDER2');

        $this->assertSame(0, $count);
        $this->assertCount(0, $this->wpdb->update_log);
    }

    public function testCancelPendingOrdersRejectsUnauthorizedBidder(): void
    {
        $this->setAuthBidder('BIDDER1');

        $checkout = AIH_Checkout::get_instance();
        $count = $checkout->cancel_pending_orders('DIFFERENT_BIDDER');

        $this->assertSame(0, $count);
        $this->assertCount(0, $this->wpdb->update_log);
    }

    // ── mark_manual_payment ──

    public function testMarkManualPaymentUpdatesExistingOrder(): void
    {
        // Queue: existing order found by get_row
        $this->wpdb->get_row_queue[] = (object) ['id' => 42];

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(5, 'paid', 'cash', 'REF-123', 'Paid at event');

        $this->assertTrue($result['success']);
        $this->assertSame('Payment status updated.', $result['message']);

        // update_payment_status should have called $wpdb->update
        $this->assertCount(1, $this->wpdb->update_log);
        $this->assertSame('paid', $this->wpdb->update_log[0]['data']['payment_status']);
        $this->assertSame(['id' => 42], $this->wpdb->update_log[0]['where']);
    }

    public function testMarkManualPaymentCreatesNewOrder(): void
    {
        // Queue: no existing order, then winning bid found
        $this->wpdb->get_row_queue[] = null;
        $this->wpdb->get_row_queue[] = (object) [
            'id' => 10,
            'art_piece_id' => 7,
            'bidder_id' => 'BID99',
            'bid_amount' => 500.00,
            'title' => 'Test Art',
        ];

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(7, 'paid', 'check', 'CHK-456', 'Check received');

        $this->assertTrue($result['success']);
        $this->assertSame('Order created and payment status set.', $result['message']);

        // Should have inserted order + order item = 2 inserts
        $this->assertCount(2, $this->wpdb->insert_log);

        // First insert: the order
        $order_data = $this->wpdb->insert_log[0]['data'];
        $this->assertSame('BID99', $order_data['bidder_id']);
        $this->assertSame(500.00, $order_data['subtotal']);
        $this->assertSame('paid', $order_data['payment_status']);
        $this->assertSame('check', $order_data['payment_method']);
        $this->assertSame('CHK-456', $order_data['payment_reference']);
        $this->assertStringStartsWith('AIH-', $order_data['order_number']);

        // Second insert: the order item
        $item_data = $this->wpdb->insert_log[1]['data'];
        $this->assertSame(7, $item_data['art_piece_id']);
        $this->assertSame(500.00, $item_data['winning_bid']);
    }

    public function testMarkManualPaymentNoWinningBid(): void
    {
        // Queue: no existing order, no winning bid
        $this->wpdb->get_row_queue[] = null;
        $this->wpdb->get_row_queue[] = null;

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(99, 'paid', 'cash');

        $this->assertFalse($result['success']);
        $this->assertSame('No winning bid found for this art piece.', $result['message']);
        $this->assertCount(0, $this->wpdb->insert_log);
    }

    public function testMarkManualPaymentInvalidStatus(): void
    {
        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(1, 'completed');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid payment status.', $result['message']);
        $this->assertCount(0, $this->wpdb->insert_log);
        $this->assertCount(0, $this->wpdb->update_log);
    }

    public function testMarkManualPaymentInvalidMethod(): void
    {
        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(1, 'paid', 'bitcoin');

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid payment method.', $result['message']);
        $this->assertCount(0, $this->wpdb->insert_log);
        $this->assertCount(0, $this->wpdb->update_log);
    }

    public function testMarkManualPaymentAcceptsEmptyMethod(): void
    {
        // Empty method is valid (default parameter)
        $this->wpdb->get_row_queue[] = (object) ['id' => 1];

        $checkout = AIH_Checkout::get_instance();
        $result = $checkout->mark_manual_payment(1, 'paid');

        $this->assertTrue($result['success']);
    }
}
