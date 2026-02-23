<?php
/**
 * Lightweight API Router
 *
 * Routes /api/{action} requests through WordPress with minimal overhead.
 * Strips WordPress fingerprints from responses. Delegates to existing
 * AIH_Ajax handlers to avoid code duplication.
 *
 * Security:
 * - POST-only enforcement (405 for other methods)
 * - Route whitelist (404 for unknown routes)
 * - Origin header validation
 * - Content-Type enforcement
 * - All existing security (nonces, rate limiting, sanitization) inherited from handlers
 *
 * @package ArtInHeaven
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_API_Router {

    /** @var AIH_API_Router|null */
    private static $instance = null;

    /**
     * Map of clean route names to AIH_Ajax method names.
     * Only PUBLIC (bidder-facing) endpoints are listed.
     * Admin endpoints stay on admin-ajax.php.
     */
    const ROUTE_MAP = array(
        // Auth
        'verify-code'      => 'verify_confirmation_code',
        'check-auth'       => 'check_auth',
        'logout'           => 'logout',
        // Browsing
        'gallery'          => 'get_gallery',
        'art-details'      => 'get_art_details',
        'search'           => 'search_art',
        // Bidding
        'bid'              => 'place_bid',
        'favorite'         => 'toggle_favorite',
        // Checkout
        'won-items'        => 'get_won_items',
        'create-order'     => 'create_order',
        'pushpay-link'     => 'get_pushpay_link',
        'order-details'    => 'get_order_details',
        'my-purchases'     => 'get_my_purchases',
        // Real-time (fallback when SSE unavailable)
        'poll-status'      => 'poll_status',
        'check-outbid'     => 'check_outbid',
        // Push notifications
        'push-subscribe'   => 'push_subscribe',
        'push-unsubscribe' => 'push_unsubscribe',
    );

    /**
     * Map of clean route names to WordPress AJAX action names.
     * Used to set $_POST['action'] for session whitelist and nonce verification.
     */
    const ACTION_MAP = array(
        'verify-code'      => 'aih_verify_code',
        'check-auth'       => 'aih_check_auth',
        'logout'           => 'aih_logout',
        'gallery'          => 'aih_get_gallery',
        'art-details'      => 'aih_get_art_details',
        'search'           => 'aih_search',
        'bid'              => 'aih_place_bid',
        'favorite'         => 'aih_toggle_favorite',
        'won-items'        => 'aih_get_won_items',
        'create-order'     => 'aih_create_order',
        'pushpay-link'     => 'aih_get_pushpay_link',
        'order-details'    => 'aih_get_order_details',
        'my-purchases'     => 'aih_get_my_purchases',
        'poll-status'      => 'aih_poll_status',
        'check-outbid'     => 'aih_check_outbid',
        'push-subscribe'   => 'aih_push_subscribe',
        'push-unsubscribe' => 'aih_push_unsubscribe',
    );

    /** API prefix in URL path */
    const API_PREFIX = 'api';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_rewrite_rules'), 5);
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'handle_api_request'), 0);
    }

    /**
     * Register rewrite rules for /api/{action}
     */
    public function register_rewrite_rules() {
        add_rewrite_rule(
            '^' . self::API_PREFIX . '/([a-z0-9\-]+)/?$',
            'index.php?aih_api_action=$matches[1]',
            'top'
        );
    }

    /**
     * Register the custom query var
     */
    public function register_query_vars($vars) {
        $vars[] = 'aih_api_action';
        return $vars;
    }

    /**
     * Intercept API requests before template loading
     */
    public function handle_api_request() {
        $action = get_query_var('aih_api_action');
        if (empty($action)) {
            return;
        }

        // Validate route exists
        if (!isset(self::ROUTE_MAP[$action])) {
            $this->send_error('Not found', 404);
        }

        // POST-only enforcement
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_error('Method not allowed', 405);
        }

        // Origin header validation (defense-in-depth alongside nonces)
        $this->validate_origin();

        // Content-Type enforcement
        $this->validate_content_type();

        // Strip WordPress fingerprint headers
        $this->strip_wp_headers();

        // Set DOING_AJAX so AIH_Auth::should_use_session() allows sessions,
        // and WP functions like check_ajax_referer() work correctly
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }

        // Set the action POST variable so session whitelist and nonce verification work
        $wp_action = self::ACTION_MAP[$action] ?? '';
        $_POST['action'] = $wp_action;
        $_REQUEST['action'] = $wp_action;

        // Delegate to AIH_Ajax handler
        $ajax = AIH_Ajax::get_instance();
        $method = self::ROUTE_MAP[$action];

        if (!method_exists($ajax, $method)) {
            $this->send_error('Handler not found', 500);
        }

        // The handler calls wp_send_json_* which exits
        $ajax->$method();

        // Safety net (should never reach here)
        exit;
    }

    /**
     * Validate Origin header matches site URL
     */
    private function validate_origin() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // No origin header is acceptable (same-origin requests may omit it)
        if (empty($origin)) {
            return;
        }

        $site_host = parse_url(home_url(), PHP_URL_HOST);
        $origin_host = parse_url($origin, PHP_URL_HOST);

        if ($origin_host !== $site_host) {
            $this->send_error('Invalid origin', 403);
        }
    }

    /**
     * Validate Content-Type header
     */
    private function validate_content_type() {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        // Allow empty content type (some jQuery.post calls may omit it)
        if (empty($content_type)) {
            return;
        }

        $allowed = array(
            'application/x-www-form-urlencoded',
            'multipart/form-data',
            'application/json',
        );

        $valid = false;
        foreach ($allowed as $type) {
            if (strpos($content_type, $type) !== false) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            $this->send_error('Unsupported content type', 415);
        }
    }

    /**
     * Remove WordPress-identifying response headers
     */
    private function strip_wp_headers() {
        header_remove('X-Powered-By');
        header_remove('Link');

        // Suppress WordPress REST/XML-RPC discovery headers
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('template_redirect', 'rest_output_link_header', 11);

        // Set clean, secure response headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Send a JSON error and exit
     */
    private function send_error($message, $status = 400) {
        $this->strip_wp_headers();
        http_response_code($status);
        echo wp_json_encode(array(
            'success' => false,
            'data'    => array('message' => $message),
        ));
        exit;
    }

    /**
     * Get the base URL for API calls (for use in wp_localize_script)
     *
     * @return string
     */
    public static function get_api_url() {
        return home_url('/' . self::API_PREFIX . '/');
    }
}
