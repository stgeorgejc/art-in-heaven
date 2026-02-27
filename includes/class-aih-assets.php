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
        // Google Fonts loaded async via Art_In_Heaven::add_preconnect_hints()

        // Register elegant theme CSS (prefer .min in production)
        $css_file = 'assets/css/elegant-theme.css';
        if (!(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) && file_exists(AIH_PLUGIN_DIR . 'assets/css/elegant-theme.min.css')) {
            $css_file = 'assets/css/elegant-theme.min.css';
        }
        wp_register_style(
            'aih-elegant-theme',
            AIH_PLUGIN_URL . $css_file,
            array(),
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

        wp_enqueue_style('aih-elegant-theme');

        // Inline critical CSS so above-fold content renders immediately,
        // then make the full stylesheet non-render-blocking.
        // Priority 7 outputs before wp_print_styles (priority 8).
        add_action('wp_head', array(__CLASS__, 'output_critical_css'), 7);
        add_filter('style_loader_tag', array(__CLASS__, 'make_css_async'), 10, 2);

        self::$elegant_theme_enqueued = true;
    }

    /**
     * Enqueue frontend extras (template-specific styles)
     */
    public static function enqueue_frontend_extras() {
        self::enqueue_elegant_theme();
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
        if (!(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)) {
            $min_path = AIH_PLUGIN_DIR . 'assets/css/elegant-theme.min.css';
            if (file_exists($min_path)) {
                return $min_path;
            }
        }
        return AIH_PLUGIN_DIR . 'assets/css/elegant-theme.css';
    }

    /**
     * Output critical CSS inline in <head> for immediate above-fold rendering.
     * Hooked at wp_head priority 7, before wp_print_styles (priority 8).
     */
    public static function output_critical_css() {
        echo '<style id="aih-critical-css">' . self::get_critical_css() . '</style>' . "\n";
    }

    /**
     * Make the elegant theme stylesheet non-render-blocking.
     * Uses media="print" with onload swap — same pattern as Google Fonts.
     * Critical CSS (inlined above) covers above-fold content during load.
     *
     * @param string $tag  The link tag HTML.
     * @param string $handle The stylesheet handle.
     * @return string Modified tag.
     */
    public static function make_css_async($tag, $handle) {
        if ('aih-elegant-theme' !== $handle) {
            return $tag;
        }

        // Swap media="all" to print with onload restore
        $async_tag = str_replace(
            "media='all'",
            "media='print' onload=\"this.media='all'\"",
            $tag
        );

        // WordPress may use double quotes instead
        if ($async_tag === $tag) {
            $async_tag = str_replace(
                'media="all"',
                'media="print" onload="this.media=\'all\'"',
                $tag
            );
        }

        // noscript fallback for users without JS
        $noscript = str_replace(
            array(
                "media='print' onload=\"this.media='all'\"",
                'media="print" onload="this.media=\'all\'"',
            ),
            array("media='all'", 'media="all"'),
            $async_tag
        );

        return $async_tag . '<noscript>' . $noscript . '</noscript>';
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
            .aih-header-inner {
                padding: 6px 10px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 4px;
                max-width: 100%;
                margin: 0 auto;
                width: 100%;
            }
            .aih-logo {
                font-family: var(--font-display);
                font-size: 1rem;
                font-weight: 600;
                color: var(--color-primary);
                text-decoration: none;
                white-space: nowrap;
            }
            .aih-nav { display: flex; gap: 6px; align-items: center; }
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
