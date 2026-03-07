<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for bulk image upload improvements:
 * - Admin CSS progress bar classes exist
 * - admin_add_image AJAX handler returns descriptive error messages
 * - JS upload queue template contains retry and progress logic
 */
class BulkUploadTest extends TestCase
{
    private string $css;
    private string $addArtTemplate;
    private string $ajaxSource;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            '__' => function ($text) { return $text; },
        ]);

        $cssPath = AIH_PLUGIN_DIR . 'assets/css/aih-admin.css';
        $this->assertFileExists($cssPath);
        $css = file_get_contents($cssPath);
        $this->assertNotFalse($css);
        $this->css = $css;

        $templatePath = AIH_PLUGIN_DIR . 'admin/views/add-art.php';
        $this->assertFileExists($templatePath);
        $tpl = file_get_contents($templatePath);
        $this->assertNotFalse($tpl);
        $this->addArtTemplate = $tpl;

        $ajaxPath = AIH_PLUGIN_DIR . 'includes/class-aih-ajax.php';
        $this->assertFileExists($ajaxPath);
        $ajax = file_get_contents($ajaxPath);
        $this->assertNotFalse($ajax);
        $this->ajaxSource = $ajax;
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── CSS: Upload progress bar classes ──

    public function testUploadProgressContainerExists(): void
    {
        $this->assertStringContainsString(
            '.aih-upload-progress',
            $this->css,
            'Upload progress container class must exist in admin CSS'
        );
    }

    public function testProgressBarTrackExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-progress-bar-track\s*\{[^}]*height:/s',
            $this->css,
            'Progress bar track must have a height defined'
        );
    }

    public function testProgressBarFillHasTransition(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-progress-bar-fill\s*\{[^}]*transition:/s',
            $this->css,
            'Progress bar fill must have a CSS transition for smooth animation'
        );
    }

    public function testProgressFailedClassExists(): void
    {
        $this->assertStringContainsString(
            '.aih-progress-failed',
            $this->css,
            'Failed status class must exist for red error text'
        );
    }

    public function testProgressDoneClassExists(): void
    {
        $this->assertStringContainsString(
            '.aih-progress-done',
            $this->css,
            'Done status class must exist for success messaging'
        );
    }

    public function testFailedTableClassExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.aih-failed-table\s*\{[^}]*border-collapse:/s',
            $this->css,
            'Failed table must have border-collapse for clean layout'
        );
    }

    public function testRetryButtonClassExists(): void
    {
        $this->assertStringContainsString(
            '.aih-retry-failed-btn',
            $this->css,
            'Retry button class must exist in admin CSS'
        );
    }

    // ── JS: Sequential queue and retry logic ──

    public function testTemplateContainsSequentialQueueFunction(): void
    {
        $this->assertStringContainsString(
            'function aihBulkUpload(',
            $this->addArtTemplate,
            'Bulk upload must use a dedicated sequential queue function'
        );
    }

    public function testTemplateContainsProcessQueue(): void
    {
        $this->assertStringContainsString(
            'function processQueue(queue, index)',
            $this->addArtTemplate,
            'Upload must process images sequentially via processQueue'
        );
    }

    public function testTemplateContainsRetryLogic(): void
    {
        $this->assertStringContainsString(
            'function tryUpload()',
            $this->addArtTemplate,
            'Upload must have a tryUpload function for retry logic'
        );
        $this->assertStringContainsString(
            'maxRetries',
            $this->addArtTemplate,
            'Upload must define a maxRetries limit'
        );
    }

    public function testTemplateContainsProgressBar(): void
    {
        $this->assertStringContainsString(
            'aih-progress-bar-fill',
            $this->addArtTemplate,
            'Upload UI must render a progress bar fill element'
        );
    }

    public function testTemplateContainsRetryFailedButton(): void
    {
        $this->assertStringContainsString(
            'aih-retry-failed-btn',
            $this->addArtTemplate,
            'Upload UI must include a Retry Failed button for resuming'
        );
    }

    public function testTemplateHasExtendedTimeout(): void
    {
        $this->assertMatchesRegularExpression(
            '/timeout:\s*120000/',
            $this->addArtTemplate,
            'AJAX requests must have a 120s timeout for large image processing'
        );
    }

    public function testTemplateDistinguishesHttpErrorCodes(): void
    {
        $this->assertStringContainsString(
            "xhr.status === 413",
            $this->addArtTemplate,
            'Error handler must detect 413 (file too large) responses'
        );
        $this->assertStringContainsString(
            "xhr.status === 500",
            $this->addArtTemplate,
            'Error handler must detect 500 (server error) responses'
        );
        $this->assertStringContainsString(
            "xhr.status === 502",
            $this->addArtTemplate,
            'Error handler must detect 502 (gateway timeout) responses'
        );
        $this->assertStringContainsString(
            "'timeout'",
            $this->addArtTemplate,
            'Error handler must detect jQuery timeout status'
        );
    }

    public function testTemplateShowsErrorReasonInFailedTable(): void
    {
        $this->assertStringContainsString(
            'aih-failed-table',
            $this->addArtTemplate,
            'Failed uploads must be shown in a structured table'
        );
    }

    public function testTemplateDoesNotFireParallelUploads(): void
    {
        // Ensure the old parallel forEach pattern is gone
        $this->assertDoesNotMatchRegularExpression(
            '/attachments\.forEach\s*\(\s*function\s*\(\s*attachment/',
            $this->addArtTemplate,
            'Must NOT use parallel forEach for uploading — use sequential queue instead'
        );
    }

    // ── PHP: admin_add_image descriptive error messages ──

    public function testAdminAddImageLogsFileInfo(): void
    {
        $this->assertStringContainsString(
            'admin_add_image: Processing',
            $this->ajaxSource,
            'admin_add_image must log file name and size for debugging'
        );
    }

    public function testAdminAddImageLogsMemoryUsage(): void
    {
        $this->assertStringContainsString(
            'memory_get_usage',
            $this->ajaxSource,
            'admin_add_image must log memory usage for diagnosing OOM failures'
        );
    }

    public function testAdminAddImageReportsWatermarkWarning(): void
    {
        $this->assertStringContainsString(
            "watermark_failed",
            $this->ajaxSource,
            'admin_add_image must track watermark failures and report as warning'
        );
        $this->assertStringContainsString(
            "'warning'",
            $this->ajaxSource,
            'Successful response must include a warning field when watermark fails'
        );
    }

    public function testAdminAddImageReportsDbErrorDetail(): void
    {
        $this->assertStringContainsString(
            'Failed to save "%1$s" to database: %2$s',
            $this->ajaxSource,
            'DB insert failure must include the filename and database error in the response'
        );
    }

    public function testAdminAddImageReportsInvalidAttachmentId(): void
    {
        $this->assertMatchesRegularExpression(
            '/Invalid image attachment.*ID:/',
            $this->ajaxSource,
            'Invalid attachment error must include the attachment ID for debugging'
        );
    }
}
