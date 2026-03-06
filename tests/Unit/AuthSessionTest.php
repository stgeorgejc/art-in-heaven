<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Auth;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AIH_Auth session management:
 * login_bidder, logout_bidder, is_logged_in, get_current_bidder_id, get_current_bidder.
 */
class AuthSessionTest extends TestCase
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
        $ref = new \ReflectionClass(AIH_Auth::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        $ref2 = new \ReflectionClass(AIH_Database::class);
        $prop2 = $ref2->getProperty('cached_year');
        $prop2->setValue(null, null);

        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

            /** @var list<?object> */
            public array $get_row_queue = [];
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

            public function insert(string $table, array $data, array|null $format = null): int|false
            {
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
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_pushpay_sandbox' => 0,
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
            'current_time' => fn(string $type = 'mysql') => $type === 'timestamp' ? strtotime('2026-01-15 10:00:00') : '2026-01-15 10:00:00',
            'sanitize_text_field' => fn($v) => trim(strip_tags((string) $v)),
            'sanitize_key' => fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
            '__' => fn($text) => $text,
            'wp_doing_cron' => false,
            'wp_doing_ajax' => false,
            'is_admin' => false,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Create a full registrant with all fields copy_to_bidders expects.
     *
     * @param array<string, mixed> $overrides
     */
    private function makeFullRegistrant(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'confirmation_code' => 'BIDDER1',
            'email_primary' => 'test@example.com',
            'name_first' => 'Jane',
            'name_last' => 'Smith',
            'phone_mobile' => '555-0000',
            'phone_home' => '',
            'phone_work' => '',
            'birthday' => '',
            'gender' => '',
            'marital_status' => '',
            'mailing_street' => '',
            'mailing_city' => '',
            'mailing_state' => '',
            'mailing_zip' => '',
            'individual_id' => '',
            'individual_name' => '',
            'api_data' => '',
        ], $overrides);
    }

    /**
     * Inject session data directly via reflection.
     *
     * @param array<string, mixed> $data
     */
    private function setSessionData(array $data): void
    {
        $auth = AIH_Auth::get_instance();
        $ref = new \ReflectionClass($auth);
        $prop = $ref->getProperty('session_data');
        $prop->setValue($auth, $data);
    }

    // ── get_current_bidder_id ──

    public function testGetCurrentBidderIdReturnsCodeFromSession(): void
    {
        $this->setSessionData(['confirmation_code' => 'BIDDER1', 'logged_in_at' => time()]);

        $auth = AIH_Auth::get_instance();
        $this->assertSame('BIDDER1', $auth->get_current_bidder_id());
    }

    public function testGetCurrentBidderIdReturnsNullWhenNotLoggedIn(): void
    {
        $this->setSessionData([]);

        $auth = AIH_Auth::get_instance();
        $this->assertNull($auth->get_current_bidder_id());
    }

    // ── is_logged_in ──

    public function testIsLoggedInReturnsTrueWithValidSession(): void
    {
        $this->setSessionData([
            'confirmation_code' => 'BIDDER1',
            'logged_in_at' => time() - 3600, // 1 hour ago, within 8-hour window
        ]);

        $auth = AIH_Auth::get_instance();
        $this->assertTrue($auth->is_logged_in());
    }

    public function testIsLoggedInReturnsFalseWithNoSession(): void
    {
        $this->setSessionData([]);

        $auth = AIH_Auth::get_instance();
        $this->assertFalse($auth->is_logged_in());
    }

    public function testIsLoggedInReturnsFalseWhenSessionExpired(): void
    {
        // Session logged in 9 hours ago — exceeds 8-hour default
        $this->setSessionData([
            'confirmation_code' => 'BIDDER1',
            'logged_in_at' => time() - (9 * HOUR_IN_SECONDS),
        ]);

        $auth = AIH_Auth::get_instance();
        $this->assertFalse($auth->is_logged_in());
    }

    public function testIsLoggedInUsesSandboxMaxAge(): void
    {
        // In sandbox mode, session lasts 7 days
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_pushpay_sandbox' => 1,
                ];
                return $options[$key] ?? $default;
            },
        ]);

        // Session logged in 3 days ago — within 7-day sandbox window
        $this->setSessionData([
            'confirmation_code' => 'BIDDER1',
            'logged_in_at' => time() - (3 * DAY_IN_SECONDS),
        ]);

        $auth = AIH_Auth::get_instance();
        $this->assertTrue($auth->is_logged_in());
    }

    public function testIsLoggedInSandboxSessionExpires(): void
    {
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                    'aih_pushpay_sandbox' => 1,
                ];
                return $options[$key] ?? $default;
            },
        ]);

        // Session logged in 8 days ago — exceeds 7-day sandbox window
        $this->setSessionData([
            'confirmation_code' => 'BIDDER1',
            'logged_in_at' => time() - (8 * DAY_IN_SECONDS),
        ]);

        $auth = AIH_Auth::get_instance();
        $this->assertFalse($auth->is_logged_in());
    }

    // ── logout_bidder ──

    public function testLogoutBidderReturnsTrue(): void
    {
        $this->setSessionData(['confirmation_code' => 'BIDDER1', 'logged_in_at' => time()]);

        $auth = AIH_Auth::get_instance();
        $this->assertTrue($auth->logout_bidder());
    }

    public function testLogoutBidderClearsSession(): void
    {
        $this->setSessionData(['confirmation_code' => 'BIDDER1', 'logged_in_at' => time()]);

        $auth = AIH_Auth::get_instance();
        $auth->logout_bidder();

        $this->assertNull($auth->get_current_bidder_id());
    }

    // ── login_bidder ──

    public function testLoginBidderSetsSessionAndCopiesToBidders(): void
    {
        // Registrant lookup
        $this->wpdb->get_row_queue[] = $this->makeFullRegistrant([
            'confirmation_code' => 'BIDDER1',
            'name_first' => 'Jane',
            'name_last' => 'Smith',
        ]);
        // copy_to_bidders: check if bidder exists → null (new)
        $this->wpdb->get_row_queue[] = null;

        $auth = AIH_Auth::get_instance();
        $result = $auth->login_bidder('BIDDER1');

        $this->assertTrue($result);

        // Session should now contain the confirmation code
        $this->assertSame('BIDDER1', $auth->get_current_bidder_id());

        // has_logged_in flag should be updated
        $this->assertNotEmpty($this->wpdb->update_log);
        $update = $this->wpdb->update_log[0];
        $this->assertSame(1, $update['data']['has_logged_in']);
    }

    public function testLoginBidderNormalizesCodeToUppercase(): void
    {
        // Registrant lookup
        $this->wpdb->get_row_queue[] = $this->makeFullRegistrant([
            'id' => 2,
            'confirmation_code' => 'LOWER123',
            'email_primary' => 'lower@example.com',
            'name_first' => 'Low',
            'name_last' => 'Case',
        ]);
        // copy_to_bidders check
        $this->wpdb->get_row_queue[] = null;

        $auth = AIH_Auth::get_instance();
        $auth->login_bidder('  lower123  ');

        $this->assertSame('LOWER123', $auth->get_current_bidder_id());
    }

    // ── get_current_bidder ──

    public function testGetCurrentBidderReturnsBidderRecord(): void
    {
        $this->setSessionData(['confirmation_code' => 'BIDDER1', 'logged_in_at' => time()]);

        // get_bidder_by_confirmation_code: bidders table
        $this->wpdb->get_row_queue[] = (object) [
            'id' => 5,
            'confirmation_code' => 'BIDDER1',
            'name_first' => 'John',
            'name_last' => 'Doe',
            'email_primary' => 'john@example.com',
        ];

        $auth = AIH_Auth::get_instance();
        $bidder = $auth->get_current_bidder();

        $this->assertNotNull($bidder);
        $this->assertSame('BIDDER1', $bidder->confirmation_code);
        $this->assertSame('John', $bidder->name_first);
    }

    public function testGetCurrentBidderReturnsNullWhenNotLoggedIn(): void
    {
        $this->setSessionData([]);

        $auth = AIH_Auth::get_instance();
        $this->assertNull($auth->get_current_bidder());
    }
}
