<?php
/**
 * Asset Management Class
 *
 * Centralized handler for enqueueing all plugin CSS and JS assets.
 * Replaces scattered direct file includes with proper WordPress asset loading.
 *
 * @package ArtInHeaven
 * @since 0.9.118
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Assets {

    /** @var AIH_Assets|null */
    private static $instance = null;

    /** @var bool Track if elegant theme is enqueued */
    private static $elegant_theme_enqueued = false;

    /**
     * Get single instance
     * @return AIH_Assets
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'), 5);
    }

    /**
     * Register all plugin assets (but don't enqueue yet)
     */
    public function register_assets() {
        // Register Google Fonts
        wp_register_style(
            'aih-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap',
            array(),
            null
        );

        // Register elegant theme CSS
        wp_register_style(
            'aih-elegant-theme',
            AIH_PLUGIN_URL . 'assets/css/elegant-theme.css',
            array('aih-google-fonts'),
            AIH_VERSION
        );

        // Register frontend extras CSS (inline styles extracted from templates)
        wp_register_style(
            'aih-frontend-extras',
            AIH_PLUGIN_URL . 'assets/css/aih-frontend-extras.css',
            array('aih-elegant-theme'),
            AIH_VERSION
        );
    }

    /**
     * Enqueue the elegant theme styles
     * Called from templates that need the theme
     */
    public static function enqueue_elegant_theme() {
        if (self::$elegant_theme_enqueued) {
            return;
        }

        wp_enqueue_style('aih-google-fonts');
        wp_enqueue_style('aih-elegant-theme');

        self::$elegant_theme_enqueued = true;
    }

    /**
     * Enqueue frontend extras (template-specific styles)
     */
    public static function enqueue_frontend_extras() {
        self::enqueue_elegant_theme();
        wp_enqueue_style('aih-frontend-extras');
    }

    /**
     * Check if elegant theme is already enqueued
     * @return bool
     */
    public static function is_elegant_theme_enqueued() {
        return self::$elegant_theme_enqueued;
    }

    /**
     * Get path to elegant theme CSS file
     * @return string
     */
    public static function get_elegant_theme_path() {
        return AIH_PLUGIN_DIR . 'assets/css/elegant-theme.css';
    }

    /**
     * Inline critical CSS for above-the-fold content
     * Used for templates that need immediate styling
     */
    public static function inline_critical_css() {
        if (self::$elegant_theme_enqueued) {
            return;
        }

        // Only inline if not already enqueued via wp_enqueue
        $css_file = self::get_elegant_theme_path();
        if (file_exists($css_file)) {
            echo '<style id="aih-critical-css">';
            // Include only critical above-fold CSS for faster rendering
            echo self::get_critical_css();
            echo '</style>';
        }
    }

    /**
     * Get critical above-fold CSS
     * @return string
     */
    private static function get_critical_css() {
        // Core layout and typography that needs to load immediately
        return '
            :root {
                --color-bg: #faf9f7;
                --color-bg-alt: #f5f3f0;
                --color-surface: #ffffff;
                --color-primary: #1c1c1c;
                --color-secondary: #4a4a4a;
                --color-muted: #8a8a8a;
                --color-border: #e8e6e3;
                --color-accent: #b8956b;
                --font-display: "Cormorant Garamond", Georgia, serif;
                --font-body: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            }
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            .aih-page {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                background: var(--color-bg);
                font-family: var(--font-body);
                font-size: 14px;
                line-height: 1.6;
                color: var(--color-primary);
            }
            .aih-header {
                background: var(--color-surface);
                border-bottom: 1px solid var(--color-border);
                position: sticky;
                top: 0;
                z-index: 100;
            }
            .aih-main {
                flex: 1 1 auto;
                width: 100%;
                padding: 16px 24px;
                background: var(--color-bg);
                max-width: 100%;
                margin: 0 auto;
            }
        ';
    }
}
