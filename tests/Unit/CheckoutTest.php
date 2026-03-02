<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

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

        // Reset singletons and static caches
        $ref = new \ReflectionClass(AIH_Checkout::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $ref2 = new \ReflectionClass(AIH_Database::class);
        $prop2 = $ref2->getProperty('cached_year');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);

        // Mock $wpdb
        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

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
                return null;
            }

            public function update(string $table, array $data, array $where, array|null $format = null, array|null $where_format = null): int|false
            {
                return 1;
            }

            public function get_var(string $sql = ''): mixed
            {
                return null;
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
            'current_time' => fn() => '2026-01-15 10:00:00',
            'sanitize_key' => fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            '__' => fn($text) => $text,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
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
}
