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
}
