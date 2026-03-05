<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Push;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class PushEndpointValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset AIH_Push singleton
        $ref = new \ReflectionClass(AIH_Push::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        Functions\stubs([
            'wp_parse_url' => function ($url, $component = -1) {
                return parse_url($url, $component);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ========== Valid endpoints ==========

    public function testAcceptsFcmEndpoint(): void
    {
        $this->assertTrue(
            AIH_Push::is_valid_push_endpoint('https://fcm.googleapis.com/fcm/send/abc123')
        );
    }

    public function testAcceptsMozillaUpdatesEndpoint(): void
    {
        $this->assertTrue(
            AIH_Push::is_valid_push_endpoint('https://updates.push.services.mozilla.com/wpush/v2/abc')
        );
    }

    public function testAcceptsMozillaPushEndpoint(): void
    {
        $this->assertTrue(
            AIH_Push::is_valid_push_endpoint('https://push.services.mozilla.com/wpush/v1/abc')
        );
    }

    public function testAcceptsWindowsNotifyEndpoint(): void
    {
        $this->assertTrue(
            AIH_Push::is_valid_push_endpoint('https://wns2-par02p.notify.windows.com/w/?token=abc')
        );
    }

    public function testAcceptsApplePushEndpoint(): void
    {
        $this->assertTrue(
            AIH_Push::is_valid_push_endpoint('https://web.push.apple.com/QGuoy123')
        );
    }

    // ========== Invalid endpoints ==========

    public function testRejectsEmptyEndpoint(): void
    {
        $this->assertFalse(AIH_Push::is_valid_push_endpoint(''));
    }

    public function testRejectsHttpEndpoint(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('http://fcm.googleapis.com/fcm/send/abc123')
        );
    }

    public function testRejectsInternalUrl(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('https://localhost/callback')
        );
    }

    public function testRejectsArbitraryExternalUrl(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('https://evil.example.com/collect')
        );
    }

    public function testRejectsInternalIpAddress(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('https://192.168.1.1/admin')
        );
    }

    public function testRejectsMetadataEndpoint(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('https://169.254.169.254/latest/meta-data/')
        );
    }

    public function testRejectsSpoofedSubdomain(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('https://fcm.googleapis.com.evil.com/push')
        );
    }

    public function testRejectsNonUrl(): void
    {
        $this->assertFalse(
            AIH_Push::is_valid_push_endpoint('not-a-url')
        );
    }
}
