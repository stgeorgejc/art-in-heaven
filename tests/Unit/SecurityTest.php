<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Security;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub WordPress sanitization functions used by AIH_Security
        Functions\stubs([
            'sanitize_text_field' => function ($value) {
                return trim(strip_tags((string) $value));
            },
            'sanitize_email' => function ($value) {
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            },
            'is_email' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            },
            'esc_url_raw' => function ($value) {
                return filter_var($value, FILTER_SANITIZE_URL);
            },
            'sanitize_key' => function ($value) {
                return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
            },
            'sanitize_title' => function ($value) {
                return strtolower(preg_replace('/[^a-z0-9\-]/', '-', strtolower((string) $value)));
            },
            'sanitize_file_name' => function ($value) {
                return preg_replace('/[^a-zA-Z0-9._\-]/', '', (string) $value);
            },
            'sanitize_textarea_field' => function ($value) {
                return trim(strip_tags((string) $value));
            },
            'wp_kses_post' => function ($value) {
                return $value; // simplified for testing
            },
            'wp_date' => function ($format, $timestamp) {
                return date($format, $timestamp);
            },
            'esc_html' => function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            },
            'esc_attr' => function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            },
            'esc_url' => function ($value) {
                return filter_var($value, FILTER_SANITIZE_URL);
            },
            'esc_js' => function ($value) {
                return addslashes((string) $value);
            },
            'esc_textarea' => function ($value) {
                return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            },
            'esc_sql' => function ($value) {
                return addslashes((string) $value);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── sanitize(): integer ──

    public function testSanitizeInteger(): void
    {
        $this->assertSame(42, AIH_Security::sanitize('42', 'int'));
        $this->assertSame(0, AIH_Security::sanitize('abc', 'int'));
        $this->assertSame(-5, AIH_Security::sanitize('-5', 'integer'));
    }

    public function testSanitizeIntegerWithMinMax(): void
    {
        $this->assertSame(10, AIH_Security::sanitize('5', 'int', ['min' => 10]));
        $this->assertSame(100, AIH_Security::sanitize('200', 'int', ['max' => 100]));
        $this->assertSame(50, AIH_Security::sanitize('50', 'int', ['min' => 10, 'max' => 100]));
    }

    // ── sanitize(): float ──

    public function testSanitizeFloat(): void
    {
        $this->assertSame(3.14, AIH_Security::sanitize('3.14159', 'float'));
        $this->assertSame(0.0, AIH_Security::sanitize('abc', 'float'));
    }

    public function testSanitizeFloatPrecision(): void
    {
        $this->assertSame(3.142, AIH_Security::sanitize('3.14159', 'float', ['precision' => 3]));
    }

    public function testSanitizeFloatWithMinMax(): void
    {
        $this->assertSame(5.0, AIH_Security::sanitize('1.5', 'float', ['min' => 5.0]));
        $this->assertSame(10.0, AIH_Security::sanitize('15.5', 'float', ['max' => 10.0]));
    }

    // ── sanitize(): boolean ──

    public function testSanitizeBoolean(): void
    {
        $this->assertTrue(AIH_Security::sanitize('true', 'bool'));
        $this->assertTrue(AIH_Security::sanitize('1', 'boolean'));
        $this->assertTrue(AIH_Security::sanitize('yes', 'bool'));
        $this->assertFalse(AIH_Security::sanitize('false', 'bool'));
        $this->assertFalse(AIH_Security::sanitize('0', 'bool'));
        $this->assertFalse(AIH_Security::sanitize('no', 'bool'));
    }

    // ── sanitize(): email ──

    public function testSanitizeValidEmail(): void
    {
        $this->assertSame('test@example.com', AIH_Security::sanitize('test@example.com', 'email'));
    }

    public function testSanitizeInvalidEmailReturnsDefault(): void
    {
        $this->assertSame('', AIH_Security::sanitize('not-an-email', 'email'));
        $this->assertSame('fallback@test.com', AIH_Security::sanitize('bad', 'email', ['default' => 'fallback@test.com']));
    }

    // ── sanitize(): phone ──

    public function testSanitizePhone(): void
    {
        $this->assertSame('+15551234567', AIH_Security::sanitize('+1 (555) 123-4567', 'phone'));
        $this->assertSame('5551234567', AIH_Security::sanitize('555.123.4567', 'phone'));
    }

    // ── sanitize(): currency ──

    public function testSanitizeCurrency(): void
    {
        $this->assertSame(25.99, AIH_Security::sanitize('$25.99', 'currency'));
        $this->assertSame(1000.0, AIH_Security::sanitize('1,000.00', 'currency'));
        $this->assertSame(5.0, AIH_Security::sanitize('-5.00', 'currency')); // '-' stripped by regex, then min 0 applies
    }

    // ── sanitize(): confirmation_code ──

    public function testSanitizeConfirmationCode(): void
    {
        $this->assertSame('ABC123', AIH_Security::sanitize('abc123', 'confirmation_code'));
        $this->assertSame('CODE', AIH_Security::sanitize('code!@#', 'confirmation_code'));
    }

    public function testSanitizeConfirmationCodeTruncates(): void
    {
        $long_code = str_repeat('A', 30);
        $result = AIH_Security::sanitize($long_code, 'confirmation_code');
        $this->assertSame(20, strlen($result));
    }

    // ── sanitize(): json ──

    public function testSanitizeValidJson(): void
    {
        $this->assertSame(['key' => 'value'], AIH_Security::sanitize('{"key":"value"}', 'json'));
    }

    public function testSanitizeInvalidJsonReturnsDefault(): void
    {
        $this->assertSame([], AIH_Security::sanitize('not json', 'json'));
        $this->assertSame(['fallback'], AIH_Security::sanitize('{bad', 'json', ['default' => ['fallback']]));
    }

    // ── sanitize(): null and empty ──

    public function testSanitizeNullReturnsDefault(): void
    {
        $this->assertSame('', AIH_Security::sanitize(null, 'text'));
        $this->assertSame('default_val', AIH_Security::sanitize(null, 'text', ['default' => 'default_val']));
    }

    public function testSanitizeEmptyStringReturnsDefault(): void
    {
        $this->assertSame('', AIH_Security::sanitize('', 'int'));
        $this->assertSame('fallback', AIH_Security::sanitize('', 'text', ['default' => 'fallback']));
    }

    // ── sanitize(): art_id ──

    public function testSanitizeArtId(): void
    {
        $this->assertSame('ART-001', AIH_Security::sanitize('art-001', 'art_id'));
        $this->assertSame('A1B2', AIH_Security::sanitize('a1!b@2#', 'art_id'));
    }

    // ── sanitize_fields() ──

    public function testSanitizeFields(): void
    {
        $data = [
            'name' => '  John Doe  ',
            'age' => '25',
            'active' => 'yes',
        ];
        $schema = [
            'name' => 'text',
            'age' => ['type' => 'int', 'min' => 0],
            'active' => 'bool',
        ];

        $result = AIH_Security::sanitize_fields($data, $schema);

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame(25, $result['age']);
        $this->assertTrue($result['active']);
    }

    public function testSanitizeFieldsUsesDefaults(): void
    {
        $data = [];
        $schema = [
            'name' => ['type' => 'text', 'default' => 'Unknown'],
        ];

        $result = AIH_Security::sanitize_fields($data, $schema);
        $this->assertSame('Unknown', $result['name']);
    }

    // ── whitelist() ──

    public function testWhitelistReturnsValueWhenAllowed(): void
    {
        $this->assertSame('blue', AIH_Security::whitelist('blue', ['red', 'green', 'blue']));
    }

    public function testWhitelistReturnsDefaultWhenNotAllowed(): void
    {
        $this->assertSame('red', AIH_Security::whitelist('purple', ['red', 'green', 'blue']));
    }

    public function testWhitelistReturnsExplicitDefault(): void
    {
        $this->assertSame('fallback', AIH_Security::whitelist('purple', ['red', 'green'], 'fallback'));
    }

    // ── sanitize_order() ──

    public function testSanitizeOrder(): void
    {
        $this->assertSame('ASC', AIH_Security::sanitize_order('asc'));
        $this->assertSame('DESC', AIH_Security::sanitize_order('DESC'));
        $this->assertSame('ASC', AIH_Security::sanitize_order('invalid'));
    }

    // ── sanitize_orderby() ──

    public function testSanitizeOrderby(): void
    {
        $allowed = ['id', 'name', 'date'];
        $this->assertSame('name', AIH_Security::sanitize_orderby('name', $allowed));
        $this->assertSame('id', AIH_Security::sanitize_orderby('evil_column', $allowed));
        $this->assertSame('date', AIH_Security::sanitize_orderby('DROP TABLE', $allowed, 'date'));
    }

    // ── encrypt() / decrypt() round-trip ──

    public function testEncryptDecryptRoundTrip(): void
    {
        if (!function_exists('openssl_encrypt')) {
            $this->markTestSkipped('OpenSSL extension not available.');
        }

        $plaintext = 'secret-api-key-12345';
        $encrypted = AIH_Security::encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertStringStartsWith('enc2:', $encrypted);

        $decrypted = AIH_Security::decrypt($encrypted);
        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptEmptyValueReturnsEmpty(): void
    {
        $this->assertSame('', AIH_Security::encrypt(''));
        $this->assertNull(AIH_Security::encrypt(null));
    }

    public function testEncryptAlreadyEncryptedValueIsUnchanged(): void
    {
        $already_encrypted = 'enc2:somethingbase64here';
        $this->assertSame($already_encrypted, AIH_Security::encrypt($already_encrypted));

        $legacy = 'enc:legacydata';
        $this->assertSame($legacy, AIH_Security::encrypt($legacy));
    }

    public function testDecryptPlaintextReturnsPlaintext(): void
    {
        $this->assertSame('just plain text', AIH_Security::decrypt('just plain text'));
    }

    public function testDecryptEmptyValueReturnsEmpty(): void
    {
        $this->assertSame('', AIH_Security::decrypt(''));
        $this->assertNull(AIH_Security::decrypt(null));
    }

    // ── sanitize(): date ──

    public function testSanitizeValidDate(): void
    {
        $result = AIH_Security::sanitize('2024-01-15', 'date');
        $this->assertSame('2024-01-15', $result);
    }

    public function testSanitizeInvalidDateReturnsDefault(): void
    {
        $this->assertSame('fallback', AIH_Security::sanitize('not-a-date', 'date', ['default' => 'fallback']));
        $this->assertSame('', AIH_Security::sanitize('not-a-date', 'date'));
    }

    // ── sanitize(): datetime ──

    public function testSanitizeValidDatetime(): void
    {
        $result = AIH_Security::sanitize('2024-01-15 13:45:00', 'datetime');
        $this->assertSame('2024-01-15 13:45:00', $result);
    }

    public function testSanitizeInvalidDatetimeReturnsDefault(): void
    {
        $this->assertSame('', AIH_Security::sanitize('garbage', 'datetime'));
    }

    // ── sanitize(): url ──

    public function testSanitizeUrl(): void
    {
        $result = AIH_Security::sanitize('https://example.com/path?q=1', 'url');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testSanitizeUrlEmptyReturnsDefault(): void
    {
        // esc_url_raw stub returns empty for empty input
        $this->assertSame('https://fallback.com', AIH_Security::sanitize('', 'url', ['default' => 'https://fallback.com']));
    }

    // ── sanitize(): key ──

    public function testSanitizeKey(): void
    {
        $this->assertSame('some-key-123', AIH_Security::sanitize('Some-Key-123!', 'key'));
    }

    // ── sanitize(): slug ──

    public function testSanitizeSlug(): void
    {
        $result = AIH_Security::sanitize('My Post Title', 'slug');
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    // ── sanitize(): filename ──

    public function testSanitizeFilename(): void
    {
        $this->assertSame('....evil.php', AIH_Security::sanitize('../../evil.php', 'filename'));
    }

    // ── sanitize(): html ──

    public function testSanitizeHtml(): void
    {
        $input = '<strong>ok</strong><script>alert(1)</script>';
        $result = AIH_Security::sanitize($input, 'html');
        // wp_kses_post stub passes through (simplified), but the call path is correct
        $this->assertIsString($result);
    }

    // ── sanitize(): textarea ──

    public function testSanitizeTextarea(): void
    {
        $input = "Line 1\n<script>alert(1)</script>\nLine 3";
        $result = AIH_Security::sanitize($input, 'textarea');
        $this->assertIsString($result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    // ── sanitize(): array ──

    public function testSanitizeArrayWithStringItems(): void
    {
        $input = ['  <b>first</b> ', "\tsecond"];
        $result = AIH_Security::sanitize($input, 'array', ['item_type' => 'text']);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        foreach ($result as $value) {
            $this->assertIsString($value);
        }
    }

    public function testSanitizeArrayWithNonArrayInput(): void
    {
        $result = AIH_Security::sanitize('not-an-array', 'array');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSanitizeArrayKeysAreSanitized(): void
    {
        $input = ['UPPER Key!' => 'value'];
        $result = AIH_Security::sanitize($input, 'array', ['item_type' => 'text']);
        $this->assertIsArray($result);
        // sanitize_key lowercases and strips special chars
        $keys = array_keys($result);
        $this->assertSame('upperkey', $keys[0]);
    }

    // ── escape() ──

    public function testEscapeNullReturnsEmptyString(): void
    {
        $this->assertSame('', AIH_Security::escape(null));
        $this->assertSame('', AIH_Security::escape(null, 'attr'));
    }

    public function testEscapeHtmlContext(): void
    {
        $result = AIH_Security::escape('test', 'html');
        $this->assertIsString($result);
    }

    public function testEscapeAttrContext(): void
    {
        $result = AIH_Security::escape('test', 'attr');
        $this->assertIsString($result);
    }

    public function testEscapeAttributeAlias(): void
    {
        $result = AIH_Security::escape('test', 'attribute');
        $this->assertIsString($result);
    }

    public function testEscapeUrlContext(): void
    {
        $result = AIH_Security::escape('https://example.com', 'url');
        $this->assertIsString($result);
    }

    public function testEscapeJsContext(): void
    {
        $result = AIH_Security::escape('alert("hi")', 'js');
        $this->assertIsString($result);
    }

    public function testEscapeJavascriptAlias(): void
    {
        $result = AIH_Security::escape('alert("hi")', 'javascript');
        $this->assertIsString($result);
    }

    public function testEscapeTextareaContext(): void
    {
        $result = AIH_Security::escape('<b>text</b>', 'textarea');
        $this->assertIsString($result);
    }

    public function testEscapeSqlContext(): void
    {
        $result = AIH_Security::escape("O'Reilly", 'sql');
        $this->assertIsString($result);
    }

    public function testEscapeDefaultIsHtml(): void
    {
        // Default context should be html
        $result = AIH_Security::escape('test');
        $this->assertIsString($result);
    }

    // ── verify_nonce() ──

    public function testVerifyNonceValid(): void
    {
        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('good-nonce', 'aih_nonce')
            ->andReturn(1);

        $this->assertTrue(AIH_Security::verify_nonce('good-nonce', 'aih_nonce'));
    }

    public function testVerifyNonceInvalid(): void
    {
        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('bad-nonce', 'aih_nonce')
            ->andReturn(false);

        $this->assertFalse(AIH_Security::verify_nonce('bad-nonce', 'aih_nonce'));
    }

    // ── can() (capability check) ──

    public function testCanReturnsTrueForAllowedCapability(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        $this->assertTrue(AIH_Security::can('manage_options'));
    }

    public function testCanReturnsFalseForDeniedCapability(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        $this->assertFalse(AIH_Security::can('manage_options'));
    }

    // ── check_rate_limit() ──

    public function testCheckRateLimitFirstRequestAllowed(): void
    {
        Functions\expect('wp_using_ext_object_cache')->once()->andReturn(false);
        Functions\expect('get_transient')->once()->andReturn(false);
        Functions\expect('set_transient')->once()->andReturn(true);

        $this->assertTrue(AIH_Security::check_rate_limit('test-user'));
    }

    public function testCheckRateLimitExceeded(): void
    {
        Functions\expect('wp_using_ext_object_cache')->once()->andReturn(false);
        Functions\expect('get_transient')->once()->andReturn([
            'count' => 60,
            'start' => time(),
        ]);
        Functions\expect('set_transient')->once()->andReturn(true);

        $this->assertFalse(AIH_Security::check_rate_limit('test-user', 60));
    }

    public function testCheckRateLimitWindowExpiredResetsCount(): void
    {
        Functions\expect('wp_using_ext_object_cache')->once()->andReturn(false);
        Functions\expect('get_transient')->once()->andReturn([
            'count' => 100,
            'start' => time() - 120, // 2 minutes ago, window is 60s
        ]);
        Functions\expect('set_transient')->once()->andReturn(true);

        $this->assertTrue(AIH_Security::check_rate_limit('test-user', 60, 60));
    }

    // ── generate_token() ──

    public function testGenerateTokenProducesUniqueTokens(): void
    {
        $token1 = AIH_Security::generate_token();
        $token2 = AIH_Security::generate_token();

        $this->assertIsString($token1);
        $this->assertIsString($token2);
        $this->assertSame(32, strlen($token1));
        $this->assertNotSame($token1, $token2);
    }

    public function testGenerateTokenCustomLength(): void
    {
        $token = AIH_Security::generate_token(64);
        $this->assertSame(64, strlen($token));
    }

    // ── hash() ──

    public function testHashIsDeterministic(): void
    {
        Functions\expect('wp_hash')
            ->twice()
            ->with('test-value')
            ->andReturn('hashed-result');

        $hash1 = AIH_Security::hash('test-value');
        $hash2 = AIH_Security::hash('test-value');

        $this->assertSame($hash1, $hash2);
        $this->assertNotSame('test-value', $hash1);
    }

    // ── get_client_ip() ──

    public function testGetClientIpFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_CF_RAY']);

        $ip = AIH_Security::get_client_ip();
        $this->assertSame('192.168.1.100', $ip);
    }

    public function testGetClientIpFromCloudflare(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';
        $_SERVER['HTTP_CF_RAY'] = 'abc123';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $ip = AIH_Security::get_client_ip();
        $this->assertSame('203.0.113.50', $ip);

        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_CF_RAY']);
    }

    public function testGetClientIpFallbackWhenNoAddr(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_CF_RAY']);

        $ip = AIH_Security::get_client_ip();
        $this->assertSame('0.0.0.0', $ip);
    }

    public function testGetClientIpIgnoresCfWithoutCfRay(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';
        unset($_SERVER['HTTP_CF_RAY']);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        $ip = AIH_Security::get_client_ip();
        $this->assertSame('10.0.0.1', $ip);

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    // ── make_sanitize_encrypt() ──

    public function testMakeSanitizeEncryptEncryptsNewValue(): void
    {
        if (!function_exists('openssl_encrypt')) {
            $this->markTestSkipped('OpenSSL extension not available.');
        }

        $callback = AIH_Security::make_sanitize_encrypt('aih_test_option');
        $result = $callback('my-new-secret');

        $this->assertStringStartsWith('enc2:', $result);
        $this->assertSame('my-new-secret', AIH_Security::decrypt($result));
    }

    public function testMakeSanitizeEncryptPreservesExistingWhenEmpty(): void
    {
        $existing_encrypted = 'enc2:existingencrypteddata';

        Functions\expect('get_option')
            ->once()
            ->with('aih_test_option', '')
            ->andReturn($existing_encrypted);

        $callback = AIH_Security::make_sanitize_encrypt('aih_test_option');
        $result = $callback('');

        $this->assertSame($existing_encrypted, $result);
    }

    public function testMakeSanitizeEncryptPreservesExistingWhenWhitespaceOnly(): void
    {
        $existing_encrypted = 'enc2:existingencrypteddata';

        Functions\expect('get_option')
            ->once()
            ->with('aih_test_option', '')
            ->andReturn($existing_encrypted);

        $callback = AIH_Security::make_sanitize_encrypt('aih_test_option');
        $result = $callback('   ');

        $this->assertSame($existing_encrypted, $result);
    }

    public function testMakeSanitizeEncryptReturnsEmptyWhenNothingExists(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('aih_test_option', '')
            ->andReturn('');

        $callback = AIH_Security::make_sanitize_encrypt('aih_test_option');
        $result = $callback('');

        $this->assertSame('', $result);
    }
}
