<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Auth;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
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
        $ref = new \ReflectionClass(AIH_Auth::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $ref2 = new \ReflectionClass(AIH_Database::class);
        $prop2 = $ref2->getProperty('cached_year');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);

        // Mock $wpdb with queue-based get_row
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
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        // Stub WordPress functions
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
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
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Helper to create a mock registrant object.
     */
    private function makeRegistrant(array $overrides = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'confirmation_code' => '65199753',
            'email_primary' => 'john@example.com',
            'name_first' => 'John',
            'name_last' => 'Doe',
            'phone_mobile' => '555-1234',
            'api_data' => '',
        ], $overrides);
    }

    // ── verify_confirmation_code ──

    public function testVerifyEmptyCodeFails(): void
    {
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('enter your confirmation code', $result['message']);
    }

    public function testVerifyWhitespaceOnlyCodeFails(): void
    {
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('   ');

        $this->assertFalse($result['success']);
    }

    public function testVerifyInvalidCodeFails(): void
    {
        // get_row returns null → code not found in registrants
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('INVALID999');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid confirmation code', $result['message']);
    }

    public function testVerifyValidCodeSucceeds(): void
    {
        $registrant = $this->makeRegistrant();
        $this->wpdb->pushGetRow($registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('65199753');

        $this->assertTrue($result['success']);
        $this->assertSame('65199753', $result['bidder']['confirmation_code']);
        $this->assertSame('John', $result['bidder']['first_name']);
        $this->assertSame('Doe', $result['bidder']['last_name']);
        $this->assertSame('john@example.com', $result['bidder']['email']);
    }

    public function testVerifyCodeIsCaseInsensitive(): void
    {
        $registrant = $this->makeRegistrant(['confirmation_code' => 'ABC123']);
        $this->wpdb->pushGetRow($registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('abc123');

        $this->assertTrue($result['success']);
        $this->assertSame('ABC123', $result['bidder']['confirmation_code']);
    }

    public function testVerifyCodeTrimsWhitespace(): void
    {
        $registrant = $this->makeRegistrant();
        $this->wpdb->pushGetRow($registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('  65199753  ');

        $this->assertTrue($result['success']);
    }

    // ── Test code bypass ──

    public function testVerifyTestCodeBypassExistingRegistrant(): void
    {
        // AIH_TEST_CODE_PREFIX is 'AIHTEST' and WP_DEBUG is true in bootstrap
        $registrant = $this->makeRegistrant([
            'confirmation_code' => 'AIHTEST001',
            'email_primary' => 'test001@test.aihgallery.org',
            'name_first' => 'Test',
            'name_last' => 'Bidder 001',
        ]);
        // get_or_create_test_registrant queries for existing → found
        $this->wpdb->pushGetRow($registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('AIHTEST001');

        $this->assertTrue($result['success']);
        $this->assertSame('AIHTEST001', $result['bidder']['confirmation_code']);
        $this->assertSame('Test', $result['bidder']['first_name']);
    }

    public function testVerifyTestCodeBypassCreatesNewRegistrant(): void
    {
        $new_registrant = $this->makeRegistrant([
            'confirmation_code' => 'AIHTEST002',
            'email_primary' => 'test002@test.aihgallery.org',
            'name_first' => 'Test',
            'name_last' => 'Bidder 002',
        ]);
        // get_or_create_test_registrant: first get_row → null (not found), then insert, then get_row → new registrant
        $this->wpdb->pushGetRow(null);
        $this->wpdb->pushGetRow($new_registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('AIHTEST002');

        $this->assertTrue($result['success']);
        $this->assertSame('AIHTEST002', $result['bidder']['confirmation_code']);
    }

    public function testVerifyTestCodeBypassFailsWithoutPrefix(): void
    {
        // 'NOTTEST001' does not start with 'AIHTEST', should fall through to normal lookup
        // get_row returns null → invalid code
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('NOTTEST001');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid confirmation code', $result['message']);
    }

    // ── Registrant data decryption ──

    public function testVerifyCodeDecryptsApiData(): void
    {
        $encrypted = \AIH_Security::encrypt('{"some":"data"}');
        $registrant = $this->makeRegistrant(['api_data' => $encrypted]);
        $this->wpdb->pushGetRow($registrant);

        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code('65199753');

        $this->assertTrue($result['success']);
        // The registrant's api_data should have been decrypted
        $this->assertSame('{"some":"data"}', $result['registrant']->api_data);
    }
}
