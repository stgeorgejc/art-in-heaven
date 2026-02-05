<?php
/**
 * Elegant Theme CSS Loader
 *
 * This file serves as a backward-compatible wrapper for templates
 * that include CSS directly. It loads the CSS from the dedicated
 * elegant-theme.css file.
 *
 * For proper WordPress asset loading, use:
 * AIH_Assets::enqueue_elegant_theme()
 *
 * @package ArtInHeaven
 * @since 0.9.118
 */

if (!defined('ABSPATH')) {
    exit;
}

// Output the CSS wrapped in style tags
$css_file = dirname(__FILE__) . '/elegant-theme.css';
if (file_exists($css_file)) {
    echo '<style id="aih-elegant-theme">';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS file content
    echo file_get_contents($css_file);
    echo '</style>';
}
