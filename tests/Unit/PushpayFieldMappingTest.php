<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Pushpay_API;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests that sync_payments reads order numbers and transaction data
 * from the correct Pushpay API response fields (notes, sourceReference)
 * rather than the old fields (payerNote, paymentMethodDetails.reference).
 */
class PushpayFieldMappingTest extends TestCase
{
    private object $wpdb;

    /** @var list<array{table: string, data: array<string, mixed>}> */
    private array $inserts = [];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset singletons
        $ref = new \ReflectionClass(AIH_Pushpay_API::class);
        $ref->getProperty('instance')->setValue(null, null);
        $ref->getProperty('cached_settings')->setValue(null, null);

        (new \ReflectionClass(AIH_Database::class))
            ->getProperty('cached_year')->setValue(null, null);

        $this->inserts = [];
        $this->wpdb = $this->createWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\stubs([
            'sanitize_text_field' => function ($v) { return $v; },
            'wp_date'            => fn() => '2026-03-05 12:00:00',
            'current_time'       => fn() => '2026-03-05 12:00:00',
            'get_option' => function ($key, $default = false) {
                return match ($key) {
                    'aih_auction_year'               => '2026',
                    'aih_event_date'                 => '2026-03-01',
                    'aih_pushpay_fund'               => 'Art In Heaven',
                    'aih_pushpay_sandbox'            => 0,
                    'aih_pushpay_client_id'          => 'test-id',
                    'aih_pushpay_client_secret'      => 'test-secret',
                    'aih_pushpay_organization_key'   => 'test-org',
                    'aih_pushpay_merchant_key'       => 'test-merchant',
                    'aih_pushpay_merchant_handle'    => 'test-handle',
                    default => $default,
                };
            },
            'update_option' => true,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Order number in `notes` field should be extracted for matching.
     */
    public function testExtractsOrderNumberFromNotesField(): void
    {
        $payment = $this->makePayment('token-1', [
            'notes' => 'Payment for AIH-ABCD1234',
        ]);

        $inserts = $this->runSyncWithPayments([$payment]);

        $this->assertCount(1, $inserts);
        $this->assertSame('Payment for AIH-ABCD1234', $inserts[0]['data']['notes']);
    }

    /**
     * Order number in `sourceReference` field should be extracted for matching.
     */
    public function testExtractsOrderNumberFromSourceReferenceField(): void
    {
        $payment = $this->makePayment('token-2', [
            'sourceReference' => 'AIH-WXYZ5678',
        ]);

        $inserts = $this->runSyncWithPayments([$payment]);

        $this->assertCount(1, $inserts);
        $this->assertSame('AIH-WXYZ5678', $inserts[0]['data']['reference']);
    }

    /**
     * Regression guard: old field names must NOT be used for matching.
     * A payment with order numbers only in payerNote / paymentMethodDetails.reference
     * should result in empty notes/reference in the transaction data.
     */
    public function testIgnoresOldFieldNames(): void
    {
        $payment = $this->makePayment('token-3', [
            'payerNote' => 'Payment for AIH-OLD00001',
            'paymentMethodDetails' => ['reference' => 'AIH-OLD00002'],
        ]);

        $inserts = $this->runSyncWithPayments([$payment]);

        $this->assertCount(1, $inserts);
        // Old fields should NOT populate transaction data
        $this->assertSame('', $inserts[0]['data']['notes']);
        $this->assertSame('', $inserts[0]['data']['reference']);
    }

    /**
     * Transaction data should be populated from `notes` and `sourceReference`,
     * not from `payerNote` and `paymentMethodDetails.reference`.
     */
    public function testTransactionDataUsesCorrectFields(): void
    {
        $payment = $this->makePayment('token-4', [
            'notes'            => 'Correct notes value',
            'sourceReference'  => 'correct-ref-123',
            'payerNote'        => 'Wrong notes value',
            'paymentMethodDetails' => ['reference' => 'wrong-ref-456'],
        ]);

        $inserts = $this->runSyncWithPayments([$payment]);

        $this->assertCount(1, $inserts);
        $this->assertSame('Correct notes value', $inserts[0]['data']['notes']);
        $this->assertSame('correct-ref-123', $inserts[0]['data']['reference']);
    }

    /**
     * Build a minimal Pushpay payment array with required fields.
     *
     * @param string $token
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makePayment(string $token, array $overrides = []): array
    {
        return array_merge([
            'paymentToken' => $token,
            'amount'       => ['amount' => '100.00', 'currency' => 'USD'],
            'status'       => 'Success',
            'createdOn'    => '2026-03-05T12:00:00Z',
            'fund'         => ['name' => 'Art In Heaven'],
            'payer'        => ['fullName' => 'Test User', 'emailAddress' => 'test@example.com'],
        ], $overrides);
    }

    /**
     * Run sync_payments with the given mock payments and return captured inserts.
     *
     * @param list<array<string, mixed>> $payments
     * @return list<array{table: string, data: array<string, mixed>}>
     */
    private function runSyncWithPayments(array $payments): array
    {
        $pushpay = $this->createPartialMock(AIH_Pushpay_API::class, ['get_payments']);

        $pushpay->expects($this->once())
            ->method('get_payments')
            ->willReturn([
                'items'      => $payments,
                'page'       => 0,
                'totalPages' => 1,
            ]);

        $pushpay->sync_payments();

        return $this->inserts;
    }

    private function createWpdb(): object
    {
        $test = $this;
        return new class($test) {
            public string $prefix = 'wp_';
            public string $last_error = '';
            /** @var PushpayFieldMappingTest */
            private $test;

            public function __construct(PushpayFieldMappingTest $test)
            {
                $this->test = $test;
            }

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function query(string $query): int
            {
                return 0;
            }

            public function get_var(?string $query = null): ?string
            {
                return null;
            }

            /**
             * @return list<object>
             */
            public function get_results(string $query): array
            {
                return [];
            }

            /**
             * @param string $table
             * @param array<string, mixed> $data
             * @return int|false
             */
            public function insert(string $table, array $data): int|false
            {
                $this->test->captureInsert($table, $data);
                return 1;
            }

            /**
             * @param string $table
             * @param array<string, mixed> $data
             * @param array<string, mixed> $where
             * @return int|false
             */
            public function update(string $table, array $data, array $where): int|false
            {
                return 1;
            }
        };
    }

    /**
     * Called by the mock wpdb to capture insert calls.
     *
     * @param string $table
     * @param array<string, mixed> $data
     */
    public function captureInsert(string $table, array $data): void
    {
        $this->inserts[] = ['table' => $table, 'data' => $data];
    }
}
