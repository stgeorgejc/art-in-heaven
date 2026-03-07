<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_QR_Code;
use AIH_Template_Helper;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the AIH_QR_Code class.
 *
 * Covers QR code generation, logo overlay, and data URI output.
 */
class QRCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->stubWordPressFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Stub the WordPress functions used by the template helper and QR class.
     */
    private function stubWordPressFunctions(): void
    {
        // Pre-populate the template helper's in-memory cache so it never
        // hits $wpdb->get_var() (which requires a full mock).
        $ref = new \ReflectionClass(AIH_Template_Helper::class);
        $prop = $ref->getProperty('page_cache');
        $prop->setValue(null, [
            'art_in_heaven_gallery_aih_gallery_page' => 'https://aihgallery.org/live',
        ]);

        Functions\stubs([
            'get_option' => function (string $key, mixed $default = false): mixed {
                return match ($key) {
                    'aih_watermark_overlay_id' => '',
                    default => $default,
                };
            },
            'get_attached_file'  => fn() => false,
            'trailingslashit'    => function (string $url): string {
                return rtrim($url, '/') . '/';
            },
        ]);
    }

    public function testGetArtUrlReturnsExpectedFormat(): void
    {
        $url = AIH_QR_Code::get_art_url('A050');
        $this->assertStringContainsString('art/A050/', $url);
    }

    public function testGetLogoPathReturnsEmptyWhenNoOverlaySet(): void
    {
        $path = AIH_QR_Code::get_logo_path();
        $this->assertSame('', $path);
    }

    public function testGetLogoPathReturnsEmptyWhenFileNotFound(): void
    {
        Functions\expect('get_option')
            ->with('aih_watermark_overlay_id', '')
            ->andReturn('42');
        Functions\expect('get_attached_file')
            ->with(42)
            ->andReturn('/nonexistent/logo.png');

        $path = AIH_QR_Code::get_logo_path();
        $this->assertSame('', $path);
    }

    public function testGenerateReturnsPngBinary(): void
    {
        $result = AIH_QR_Code::generate('A050');
        $this->assertNotFalse($result);
        $this->assertStringStartsWith("\x89PNG", $result);
    }

    public function testGenerateDataUriReturnsBase64String(): void
    {
        $result = AIH_QR_Code::generate_data_uri('A050');
        $this->assertNotFalse($result);
        $this->assertStringStartsWith('data:image/png;base64,', $result);

        // Decode and verify it's a valid PNG
        $base64 = substr($result, strlen('data:image/png;base64,'));
        $decoded = base64_decode($base64, true);
        $this->assertNotFalse($decoded);
        $this->assertStringStartsWith("\x89PNG", $decoded);
    }

    public function testGenerateWithDifferentScales(): void
    {
        $small = AIH_QR_Code::generate('A050', 5);
        $large = AIH_QR_Code::generate('A050', 30);

        $this->assertNotFalse($small);
        $this->assertNotFalse($large);

        // Larger scale should produce larger image data
        $this->assertGreaterThan(strlen($small), strlen($large));
    }

    public function testGenerateWithLogoOverlay(): void
    {
        // Create a temporary PNG to use as the logo
        $logo_path = tempnam(sys_get_temp_dir(), 'aih_logo_') . '.png';

        $img = imagecreatetruecolor(100, 100);
        $this->assertNotFalse($img);
        $white = imagecolorallocate($img, 255, 255, 255);
        $this->assertNotFalse($white);
        imagefilledrectangle($img, 0, 0, 99, 99, $white);
        imagepng($img, $logo_path);
        unset($img);

        try {
            // Override stubs to return a logo
            Functions\expect('get_option')
                ->with('aih_watermark_overlay_id', '')
                ->andReturn('99');
            Functions\expect('get_attached_file')
                ->with(99)
                ->andReturn($logo_path);

            $result = AIH_QR_Code::generate('A050');

            $this->assertNotFalse($result);
            $this->assertStringStartsWith("\x89PNG", $result);
        } finally {
            // Clean up temp files
            if (file_exists($logo_path)) {
                unlink($logo_path);
            }
            $base_path = substr($logo_path, 0, -4);
            if (file_exists($base_path)) {
                unlink($base_path);
            }
        }
    }

    public function testGenerateProducesSquareQrCode(): void
    {
        $png_data = AIH_QR_Code::generate('B123', 10);
        $this->assertNotFalse($png_data);

        // Verify the image can be loaded by GD (valid PNG)
        $img = imagecreatefromstring($png_data);
        $this->assertNotFalse($img);

        $width = imagesx($img);
        $height = imagesy($img);
        unset($img);

        // QR code should be square
        $this->assertSame($width, $height);

        // At scale=10, even the smallest QR (v1=21 modules + quietzone) is > 200px
        $this->assertGreaterThan(200, $width);
    }

    public function testGenerateWithLogoProducesLargerQrDueToErrorCorrection(): void
    {
        // Create a temporary PNG to use as the logo
        $logo_path = tempnam(sys_get_temp_dir(), 'aih_logo3_') . '.png';
        $img = imagecreatetruecolor(80, 80);
        $this->assertNotFalse($img);
        $red = imagecolorallocate($img, 255, 0, 0);
        $this->assertNotFalse($red);
        imagefilledrectangle($img, 0, 0, 79, 79, $red);
        imagepng($img, $logo_path);
        unset($img);

        try {
            // Override stubs to return a logo
            Functions\expect('get_option')
                ->with('aih_watermark_overlay_id', '')
                ->andReturn('50');
            Functions\expect('get_attached_file')
                ->with(50)
                ->andReturn($logo_path);

            $result = AIH_QR_Code::generate('A050', 10);
            $this->assertNotFalse($result);

            // With logo, ECC is H (30%) → larger QR matrix → larger image
            $img_result = imagecreatefromstring($result);
            $this->assertNotFalse($img_result);
            $width = imagesx($img_result);
            unset($img_result);

            // ECC H at scale 10 for a typical URL gives ~33 modules + quietzone = 370px
            // Without logo (ECC M), it would be smaller (~290px)
            $this->assertGreaterThanOrEqual(300, $width);
        } finally {
            if (file_exists($logo_path)) {
                unlink($logo_path);
            }
            $base_path = substr($logo_path, 0, -4);
            if (file_exists($base_path)) {
                unlink($base_path);
            }
        }
    }
}
