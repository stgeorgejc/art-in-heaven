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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @return void
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

        // Swap media="all" to print — the onload swap is handled by a nonced inline script
        $async_tag = str_replace("media='all'", "media='print' id='aih-elegant-theme-css'", $tag);

        // WordPress may use double quotes instead
        if ($async_tag === $tag) {
            $async_tag = str_replace('media="all"', 'media="print" id="aih-elegant-theme-css"', $tag);
        }

        // If neither replacement matched, return original tag unchanged
        if ($async_tag === $tag) {
            return $tag;
        }

        // Nonced inline script to swap media on load (CSP-compliant)
        $nonce = \AIH_Security::get_csp_nonce();
        $onload_script = '<script nonce="' . esc_attr($nonce) . '">(function(){var l=document.getElementById("aih-elegant-theme-css");if(!l)return;function e(){l.media="all";}l.addEventListener("load",e);if(l.sheet){e();}else{setTimeout(function(){if(l.media!=="all"){e();}},3000);}}());</script>';

        // noscript fallback for users without JS
        $noscript = str_replace(
            array("media='print' id='aih-elegant-theme-css'", 'media="print" id="aih-elegant-theme-css"'),
            array("media='all'", 'media="all"'),
            $async_tag
        );

        return $async_tag . $onload_script . '<noscript>' . $noscript . '</noscript>';
    }

    /**
     * Get critical above-fold CSS
     * @return string
     */
    private static function get_critical_css() {
        // Core layout, dark mode, WP resets, gallery grid, and login card
        // — everything needed to prevent FOUC on first paint
        return '
            :root {
                --color-bg: #faf9f7;
                --color-bg-alt: #f5f3f0;
                --color-surface: #ffffff;
                --color-primary: #1c1c1c;
                --color-secondary: #4a4a4a;
                --color-muted: #6b6b6b;
                --color-border: #e8e6e3;
                --color-accent: #b8956b;
                --shadow-lg: 0 10px 40px rgba(0,0,0,0.08);
                --radius: 4px;
                --font-display: "Cormorant Garamond", Georgia, serif;
                --font-body: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
            }
            .aih-page.dark-mode {
                --color-bg: #121212;
                --color-bg-alt: #1a1a1a;
                --color-surface: #1e1e1e;
                --color-primary: #e8e6e3;
                --color-secondary: #b5b3b0;
                --color-muted: #8a8a8a;
                --color-border: #2a2a2a;
                --color-accent: #d4a574;
                --shadow-lg: 0 10px 40px rgba(0,0,0,0.6);
            }
            .aih-page, .aih-page *, .aih-page *::before, .aih-page *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body.aih-active .site-content,
            body.aih-active .content-area,
            body.aih-active .site-main,
            body.aih-active .entry-content,
            body.aih-active .wp-block-post-content,
            body.aih-active .page-content,
            body.aih-active .is-layout-constrained,
            body.aih-active .is-layout-flow,
            body.aih-active .has-global-padding {
                background: transparent !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            body.aih-active .entry-title,
            body.aih-active .wp-block-post-title,
            body.aih-active .page-title { display: none !important; }
            body.aih-active .wp-site-blocks > header,
            body.aih-active .wp-site-blocks > footer { display: none !important; }
            body.aih-active .wp-site-blocks {
                padding-block-start: 0 !important;
                padding-block-end: 0 !important;
            }
            .aih-page {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                background: var(--color-bg);
                font-family: var(--font-body);
                font-size: 14px;
                line-height: 1.6;
                color: var(--color-primary);
                -webkit-font-smoothing: antialiased;
                overflow-x: hidden;
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
            .aih-page .aih-main-centered,
            .aih-page .aih-main.aih-main-centered {
                display: flex;
                align-items: center;
                justify-content: center;
                max-width: 100%;
            }
            .aih-gallery-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                width: 100%;
            }
            .aih-card {
                background: var(--color-surface);
                border: 1px solid var(--color-border);
                display: flex;
                flex-direction: column;
            }
            .aih-card-image {
                position: relative;
                padding-bottom: 100%;
                height: 0;
                overflow: hidden;
                background: var(--color-bg-alt);
                width: 100%;
            }
            .aih-card-image a {
                display: block;
                width: 100%;
                height: 100%;
            }
            .aih-card-image img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .aih-card-body {
                padding: 6px 8px;
                min-width: 0;
                display: flex;
                flex-direction: column;
            }
            .aih-login-card {
                width: 100%;
                max-width: 400px;
                background: var(--color-surface);
                border: 1px solid var(--color-border);
                box-shadow: var(--shadow-lg);
                margin: 0 auto;
            }
            .aih-login-header {
                text-align: center;
                padding: 32px 24px 24px;
                border-bottom: 1px solid var(--color-border);
            }
            .aih-login-header h1 {
                font-family: var(--font-display);
                font-size: 28px;
                font-weight: 500;
                margin-bottom: 10px;
            }
            .aih-ornament {
                font-size: 16px;
                color: var(--color-accent);
                margin-bottom: 16px;
                letter-spacing: 8px;
            }
            .aih-login-form { padding: 24px; }
            @media (min-width: 480px) {
                .aih-gallery-grid {
                    gap: 12px;
                }
            }
            @media (min-width: 768px) {
                .aih-gallery-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }
            @media (min-width: 1200px) {
                .aih-gallery-grid {
                    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                }
            }
        ';
    }
}
