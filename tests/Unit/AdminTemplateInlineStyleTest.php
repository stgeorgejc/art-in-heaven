<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies admin templates use CSS classes instead of inline styles
 * for patterns that have dedicated CSS rules.
 */
class AdminTemplateInlineStyleTest extends TestCase
{
    public function testArtPiecesDeleteButtonUsesCssClass(): void
    {
        $path = AIH_PLUGIN_DIR . 'admin/views/art-pieces.php';
        $this->assertFileExists($path);
        $template = (string) file_get_contents($path);

        // Bulk delete button should use .aih-btn-error, not inline color
        $this->assertDoesNotMatchRegularExpression(
            '/id="aih-bulk-delete-btn"[^>]*style="[^"]*color:\s*#d63638/',
            $template,
            'Bulk delete button should use .aih-btn-error class instead of inline color'
        );
        $this->assertStringContainsString(
            'aih-btn-error',
            $template,
            'Bulk delete button must use .aih-btn-error class'
        );
    }

    public function testPickupHeadingIconUsesCssClass(): void
    {
        $path = AIH_PLUGIN_DIR . 'admin/views/pickup.php';
        $this->assertFileExists($path);
        $template = (string) file_get_contents($path);

        // Heading icon should use .aih-heading-icon, not inline font-size
        $this->assertDoesNotMatchRegularExpression(
            '/dashicons-archive[^>]*style="[^"]*font-size:\s*28px/',
            $template,
            'Pickup heading icon should use .aih-heading-icon class instead of inline sizing'
        );
        $this->assertStringContainsString(
            'aih-heading-icon',
            $template,
            'Pickup heading icon must use .aih-heading-icon class'
        );
    }

    public function testPickupPanelUsesBottomModifier(): void
    {
        $path = AIH_PLUGIN_DIR . 'admin/views/pickup.php';
        $this->assertFileExists($path);
        $template = (string) file_get_contents($path);

        // Panel should use .aih-panel--bottom, not inline border-radius
        $this->assertDoesNotMatchRegularExpression(
            '/class="aih-panel"[^>]*style="[^"]*border-radius:\s*0\s+0/',
            $template,
            'Pickup panel should use .aih-panel--bottom class instead of inline border-radius'
        );
        $this->assertStringContainsString(
            'aih-panel--bottom',
            $template,
            'Pickup panel must use .aih-panel--bottom modifier'
        );
    }

    public function testPickupSearchFormNoInlineFlex(): void
    {
        $path = AIH_PLUGIN_DIR . 'admin/views/pickup.php';
        $this->assertFileExists($path);
        $template = (string) file_get_contents($path);

        $this->assertDoesNotMatchRegularExpression(
            '/class="aih-search-form"[^>]*style="[^"]*flex:\s*1/',
            $template,
            'Search form should not have inline flex: 1 (handled by CSS)'
        );
    }
}
