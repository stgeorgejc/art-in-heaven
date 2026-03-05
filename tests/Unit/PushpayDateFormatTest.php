<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Pushpay_API;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Pushpay API date parameters use UTC format with Z suffix.
 *
 * The Pushpay API requires from/to parameters as "a date/time (UTC)".
 * Local timezone offsets (e.g. date('c') producing 2026-03-01T00:00:00-05:00)
 * are silently ignored by the API.
 *
 * @see https://pushpay.io/docs/operations/payments
 */
class PushpayDateFormatTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset singletons
        $ref = new \ReflectionClass(AIH_Pushpay_API::class);
        $ref->getProperty('instance')->setValue(null, null);
        $ref->getProperty('cached_settings')->setValue(null, null);

        // Reset AIH_Database cached year
        (new \ReflectionClass(AIH_Database::class))
            ->getProperty('cached_year')->setValue(null, null);

        // Mock wpdb
        $this->wpdb = $this->createWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\stubs([
            'sanitize_text_field' => function ($v) { return $v; },
            'wp_date'            => fn() => '2026',
            'current_time'       => fn() => '2026-03-05 12:00:00',
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Verify sync_payments passes UTC Z-suffix dates to get_payments.
     */
    public function testSyncPaymentsUsesUtcDateFormat(): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                return match ($key) {
                    'aih_auction_year'    => '2026',
                    'aih_event_date'      => '2026-03-01',
                    'aih_pushpay_fund'    => 'art-in-heaven',
                    'aih_pushpay_sandbox' => 0,
                    'aih_pushpay_client_id'          => 'test-id',
                    'aih_pushpay_client_secret'      => 'test-secret',
                    'aih_pushpay_organization_key'   => 'test-org',
                    'aih_pushpay_merchant_key'       => 'test-merchant',
                    'aih_pushpay_merchant_handle'    => 'test-handle',
                    'aih_pushpay_fund'               => 'test-fund',
                    default => $default,
                };
            },
            'update_option' => true,
        ]);

        // Capture the params passed to get_payments
        $capturedParams = null;

        $pushpay = $this->getMockBuilder(AIH_Pushpay_API::class)
            ->onlyMethods(['get_payments'])
            ->getMock();

        $pushpay->method('get_payments')
            ->willReturnCallback(function ($params) use (&$capturedParams) {
                $capturedParams = $params;
                // Return empty result to stop pagination
                return ['items' => [], 'page' => 0, 'totalPages' => 1];
            });

        $pushpay->sync_payments();

        $this->assertNotNull($capturedParams, 'get_payments should have been called');

        // Verify 'from' uses UTC Z suffix format
        $this->assertArrayHasKey('from', $capturedParams);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $capturedParams['from'],
            'from parameter must use UTC format with Z suffix (e.g. 2026-03-01T00:00:00Z)'
        );

        // Verify 'to' uses UTC Z suffix format
        $this->assertArrayHasKey('to', $capturedParams);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $capturedParams['to'],
            'to parameter must use UTC format with Z suffix (e.g. 2026-03-05T12:00:00Z)'
        );
    }

    /**
     * Verify from parameter is null when no event date is set.
     */
    public function testSyncPaymentsOmitsFromWhenNoEventDate(): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                return match ($key) {
                    'aih_auction_year'    => '2026',
                    'aih_event_date'      => '',
                    'aih_pushpay_fund'    => 'art-in-heaven',
                    'aih_pushpay_sandbox' => 0,
                    'aih_pushpay_client_id'          => 'test-id',
                    'aih_pushpay_client_secret'      => 'test-secret',
                    'aih_pushpay_organization_key'   => 'test-org',
                    'aih_pushpay_merchant_key'       => 'test-merchant',
                    'aih_pushpay_merchant_handle'    => 'test-handle',
                    'aih_pushpay_fund'               => 'test-fund',
                    default => $default,
                };
            },
            'update_option' => true,
        ]);

        $capturedParams = null;

        $pushpay = $this->getMockBuilder(AIH_Pushpay_API::class)
            ->onlyMethods(['get_payments'])
            ->getMock();

        $pushpay->method('get_payments')
            ->willReturnCallback(function ($params) use (&$capturedParams) {
                $capturedParams = $params;
                return ['items' => [], 'page' => 0, 'totalPages' => 1];
            });

        $pushpay->sync_payments();

        $this->assertNotNull($capturedParams, 'get_payments should have been called');
        $this->assertArrayNotHasKey('from', $capturedParams, 'from should be omitted when no event date is set');

        // 'to' should still be present in UTC format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $capturedParams['to']
        );
    }

    private function createWpdb(): object
    {
        return new class {
            public string $prefix = 'wp_';
            public string $last_error = '';

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
        };
    }
}
