<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Ajax;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CsvImageImportTest extends TestCase
{
    private $ajax;
    private $preprocessMethod;
    private $importMethod;

    public static function setUpBeforeClass(): void
    {
        // Load AIH_Ajax and its dependencies (only once)
        if (!class_exists('AIH_Ajax')) {
            require_once __DIR__ . '/../../includes/class-aih-roles.php';
            require_once __DIR__ . '/../../includes/class-aih-art-images.php';
            require_once __DIR__ . '/../../includes/class-aih-watermark.php';
            require_once __DIR__ . '/../../includes/class-aih-ajax.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Stub all the add_action calls so the constructor doesn't fail
        Functions\stubs([
            'add_action' => null,
            'add_filter' => null,
            '__' => function ($text) { return $text; },
            'wp_parse_url' => function ($url, $component = -1) {
                return parse_url($url, $component);
            },
        ]);

        // Reset singleton before each test
        $ref = new ReflectionClass(AIH_Ajax::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        $this->ajax = AIH_Ajax::get_instance();

        // Access private methods via reflection
        $ref = new ReflectionClass($this->ajax);
        $this->preprocessMethod = $ref->getMethod('preprocess_image_url');
        $this->importMethod = $ref->getMethod('import_image_from_url');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ===== preprocess_image_url tests =====

    public function testGoogleDriveFileLink(): void
    {
        $url = 'https://drive.google.com/file/d/15WchEp3cMaYcTLL0ZFoTfzmAe52ayhvs/view?usp=drive_link';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://drive.google.com/uc?export=download&confirm=t&id=15WchEp3cMaYcTLL0ZFoTfzmAe52ayhvs', $result);
    }

    public function testGoogleDriveFileLinkWithoutView(): void
    {
        $url = 'https://drive.google.com/file/d/10-pDf9sm8doIRDKhzZh6kkJONYZ7E87q';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://drive.google.com/uc?export=download&confirm=t&id=10-pDf9sm8doIRDKhzZh6kkJONYZ7E87q', $result);
    }

    public function testGoogleDriveOpenLink(): void
    {
        $url = 'https://drive.google.com/open?id=ABC123_def';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://drive.google.com/uc?export=download&confirm=t&id=ABC123_def', $result);
    }

    public function testGoogleDriveFolderLinkPassesThrough(): void
    {
        $url = 'https://drive.google.com/drive/u/1/folders/1jpzy5eZx-ZOe3aKyEKy21Hts-n2iH0jC';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame($url, $result, 'Folder links should pass through unchanged — they are not downloadable images');
    }

    public function testGoogleDriveFolderShortLinkPassesThrough(): void
    {
        $url = 'https://drive.google.com/drive/folders/1jpzy5eZx-ZOe3aKyEKy21Hts-n2iH0jC';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame($url, $result, 'Folder links without /u/N/ prefix should also pass through');
    }

    public function testDropboxDl0AsFirstParam(): void
    {
        $url = 'https://www.dropbox.com/s/abc123/photo.jpg?dl=0';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://www.dropbox.com/s/abc123/photo.jpg?dl=1', $result);
    }

    public function testDropboxDl0AsMidQueryParam(): void
    {
        // This was the bug: &dl=0 was replaced with ?dl=1, creating a malformed URL
        $url = 'https://www.dropbox.com/s/abc123/photo.jpg?token=xyz&dl=0';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://www.dropbox.com/s/abc123/photo.jpg?token=xyz&dl=1', $result);
        // Verify no double question marks
        $this->assertSame(1, substr_count($result, '?'), 'URL should have exactly one ? character');
    }

    public function testDropboxNoDlParam(): void
    {
        $url = 'https://www.dropbox.com/s/abc123/photo.jpg';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertStringEndsWith('?dl=1', $result);
    }

    public function testDropboxWithExistingQueryNoDl(): void
    {
        $url = 'https://www.dropbox.com/s/abc123/photo.jpg?rlkey=abc';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertStringEndsWith('&dl=1', $result);
    }

    public function testOneDriveShortLink(): void
    {
        $url = 'https://1drv.ms/i/s!AmBcDeF';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertStringStartsWith('https://api.onedrive.com/v1.0/shares/u!', $result);
        $this->assertStringEndsWith('/root/content', $result);
    }

    public function testImgurSingleImage(): void
    {
        $url = 'https://imgur.com/abc123';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://i.imgur.com/abc123.jpg', $result);
    }

    public function testImgurAlbumNotConverted(): void
    {
        $url = 'https://imgur.com/a/abc123';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        // Album URLs should pass through unchanged
        $this->assertSame($url, $result);
    }

    public function testImgurGalleryNotConverted(): void
    {
        $url = 'https://imgur.com/gallery/abc123';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame($url, $result);
    }

    public function testBoxShareLink(): void
    {
        $url = 'https://app.box.com/s/abc123def';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://app.box.com/shared/static/abc123def.jpg', $result);
    }

    public function testDirectUrlPassesThrough(): void
    {
        $url = 'https://example.com/photos/sunset.jpg';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame($url, $result);
    }

    public function testPostImgConversion(): void
    {
        $url = 'https://postimg.cc/abc123';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://i.postimg.cc/abc123/image.jpg', $result);
    }

    public function testImageBBConversion(): void
    {
        $url = 'https://ibb.co/abc123';
        $result = $this->preprocessMethod->invoke($this->ajax, $url);
        $this->assertSame('https://i.ibb.co/abc123/image.jpg', $result);
    }

    // ===== import_image_from_url validation tests =====

    public function testRejectsInvalidUrl(): void
    {
        $result = $this->importMethod->invoke($this->ajax, 1, 'not-a-url', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('invalid URL', $result);
    }

    public function testRejectsFtpScheme(): void
    {
        $result = $this->importMethod->invoke($this->ajax, 1, 'ftp://example.com/image.jpg', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('only http/https', $result);
    }

    public function testRejectsFileScheme(): void
    {
        $result = $this->importMethod->invoke($this->ajax, 1, 'file:///etc/passwd', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('only http/https', $result);
    }

    public function testRejectsDataScheme(): void
    {
        $result = $this->importMethod->invoke($this->ajax, 1, 'data:image/png;base64,abc', true);
        $this->assertIsString($result);
        // data: URLs fail FILTER_VALIDATE_URL, so caught at invalid URL stage
        $this->assertStringContainsString('invalid URL', $result);
    }
}
