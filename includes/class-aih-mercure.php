<?php
/**
 * Mercure SSE Integration
 *
 * Manages JWT generation for Mercure hub authentication,
 * publishes real-time events (bid updates, outbid notifications,
 * auction ended), and provides subscriber JWT tokens for the frontend.
 *
 * Gracefully degrades: if Mercure is disabled or unreachable,
 * the existing polling mechanism continues to function.
 *
 * Security:
 * - Publisher JWT is server-side only, never exposed to browsers
 * - Subscriber JWT is per-bidder with scoped topic claims
 * - JWT secret stored encrypted via AIH_Security::encrypt()
 * - Private topics enforce subscriber authorization
 * - Non-blocking publish prevents Mercure failures from blocking bids
 *
 * @package ArtInHeaven
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Mercure {

    /** @var AIH_Mercure|null */
    private static $instance = null;

    /** Default hub URL (internal, for PHP → Mercure) */
    const DEFAULT_HUB_URL = 'http://127.0.0.1:3000/.well-known/mercure';

    /** Cached publisher JWT to avoid regenerating per request */
    private $publisher_jwt = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!self::is_enabled()) {
            return;
        }

        // Publish events when bids are placed (after push notifications at priority 20)
        add_action('aih_bid_placed', array($this, 'on_bid_placed'), 25, 4);

        // Publish auction ended events
        add_action('aih_auction_ended', array($this, 'on_auction_ended'), 10, 1);
    }

    // =========================================================================
    // CONFIGURATION
    // =========================================================================

    /**
     * Check if Mercure is configured (hub URL and JWT secret are set).
     *
     * @return bool
     */
    public function is_configured() {
        $hub_url = self::get_hub_url();
        $secret  = self::get_jwt_secret();
        return !empty($hub_url) && !empty($secret);
    }

    /**
     * Verify the Mercure hub is reachable.
     *
     * @return array{status: string, error?: string, code?: int}
     */
    public function health_check() {
        if (!$this->is_configured()) {
            return array('status' => 'not_configured');
        }

        $response = wp_remote_get(self::get_hub_url(), array('timeout' => 5));

        if (is_wp_error($response)) {
            return array('status' => 'unreachable', 'error' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        return array(
            'status' => $code < 500 ? 'ok' : 'error',
            'code'   => $code,
        );
    }

    /**
     * Check if Mercure integration is enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        if (!get_option('aih_mercure_enabled', false)) {
            return false;
        }

        // Must have a JWT secret configured
        $secret = self::get_jwt_secret();
        return !empty($secret);
    }

    /**
     * Get the internal Mercure hub URL (PHP publishes here)
     *
     * @return string
     */
    public static function get_hub_url() {
        return get_option('aih_mercure_hub_url', self::DEFAULT_HUB_URL);
    }

    /**
     * Get the public hub URL (browsers connect here, proxied via nginx)
     *
     * @return string
     */
    public static function get_public_hub_url() {
        $public = get_option('aih_mercure_public_hub_url', '');
        if (!empty($public)) {
            return $public;
        }
        return home_url('/.well-known/mercure');
    }

    /**
     * Get the JWT secret (shared between PHP and Mercure hub)
     * Stored encrypted in wp_options.
     *
     * @return string Plaintext secret
     */
    private static function get_jwt_secret() {
        $encrypted = get_option('aih_mercure_jwt_secret', '');
        if (empty($encrypted)) {
            return '';
        }

        // If it looks encrypted, decrypt it
        if (strpos($encrypted, 'enc:') === 0 || strpos($encrypted, 'enc2:') === 0) {
            return AIH_Security::decrypt($encrypted);
        }

        return $encrypted;
    }

    /**
     * Get the site's topic prefix
     *
     * @return string
     */
    public static function get_topic_prefix() {
        return rtrim(home_url(), '/');
    }

    // =========================================================================
    // JWT GENERATION (HMAC-SHA256)
    // =========================================================================

    /**
     * Generate a publisher JWT (server-side only, never sent to browser)
     *
     * @return string JWT
     */
    private function generate_publisher_jwt() {
        if ($this->publisher_jwt !== null) {
            return $this->publisher_jwt;
        }

        $payload = array(
            'mercure' => array(
                'publish' => array('*'),
            ),
            'iat' => time(),
            'exp' => time() + 3600,
        );

        $this->publisher_jwt = self::encode_jwt($payload);
        return $this->publisher_jwt;
    }

    /**
     * Generate a subscriber JWT for a specific bidder
     *
     * @param string|null $bidder_id If null, only public topics
     * @return string JWT
     */
    public static function generate_subscriber_jwt($bidder_id = null) {
        $prefix = self::get_topic_prefix();

        // Public topics: all auction updates
        $subscribe = array(
            $prefix . '/auction/{id}',
        );

        // Private topic: personal outbid notifications
        if ($bidder_id) {
            $subscribe[] = $prefix . '/bidder/' . $bidder_id;
        }

        $payload = array(
            'mercure' => array(
                'subscribe' => $subscribe,
            ),
            'iat' => time(),
            'exp' => time() + 3600,
        );

        return self::encode_jwt($payload);
    }

    /**
     * Encode a JWT using HMAC-SHA256
     *
     * @param array $payload JWT claims
     * @return string Encoded JWT
     */
    private static function encode_jwt($payload) {
        $secret = self::get_jwt_secret();
        if (empty($secret)) {
            return '';
        }

        $header = self::base64url_encode(wp_json_encode(array(
            'alg' => 'HS256',
            'typ' => 'JWT',
        )));

        $payload_encoded = self::base64url_encode(wp_json_encode($payload));

        $signature = self::base64url_encode(
            hash_hmac('sha256', $header . '.' . $payload_encoded, $secret, true)
        );

        return $header . '.' . $payload_encoded . '.' . $signature;
    }

    /**
     * Base64URL encode (no padding, URL-safe characters)
     *
     * @param string $data Raw data
     * @return string Encoded string
     */
    private static function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // =========================================================================
    // PUBLISHING
    // =========================================================================

    /**
     * Publish an event to the Mercure hub
     *
     * @param string $topic   Full topic URL
     * @param array  $data    Event data (will be JSON-encoded)
     * @param bool   $private Whether this is a private (authorized) topic
     * @return bool Success
     */
    public function publish($topic, $data, $private = false) {
        if (!self::is_enabled()) {
            return false;
        }

        $jwt = $this->generate_publisher_jwt();
        if (empty($jwt)) {
            return false;
        }

        $body = array(
            'topic' => $topic,
            'data'  => wp_json_encode($data),
        );

        if ($private) {
            $body['private'] = 'on';
        }

        $response = wp_remote_post(self::get_hub_url(), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $jwt,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'     => http_build_query($body),
            'timeout'  => 2,
            'blocking' => false,
        ));

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIH Mercure publish error: ' . $response->get_error_message());
            }
            return false;
        }

        return true;
    }

    // =========================================================================
    // EVENT HOOKS
    // =========================================================================

    /**
     * Handle bid placed event
     *
     * Publishes:
     * 1. Public bid_update on the art piece topic
     * 2. Private outbid notification to the outbid bidder
     *
     * @param int    $bid_id
     * @param int    $art_piece_id
     * @param string $new_bidder_id
     * @param float  $amount
     */
    public function on_bid_placed($bid_id, $art_piece_id, $new_bidder_id, $amount) {
        global $wpdb;
        $prefix = self::get_topic_prefix();

        // 1. Public bid update (no bid amounts — silent auction)
        $this->publish(
            $prefix . '/auction/' . intval($art_piece_id),
            array(
                'type'         => 'bid_update',
                'art_piece_id' => intval($art_piece_id),
                'has_bids'     => true,
                'status'       => 'active',
            )
        );

        // 2. Find the outbid bidder and send private notification
        $bids_table = AIH_Database::get_table('bids');
        $art_table  = AIH_Database::get_table('art_pieces');

        $outbid_bidder = $wpdb->get_var($wpdb->prepare(
            "SELECT bidder_id FROM `{$bids_table}`
             WHERE art_piece_id = %d
               AND bidder_id != %s
               AND bid_status = 'valid'
             ORDER BY bid_amount DESC, bid_time DESC
             LIMIT 1",
            $art_piece_id,
            $new_bidder_id
        ));

        if (!empty($outbid_bidder)) {
            $title = $wpdb->get_var($wpdb->prepare(
                "SELECT title FROM `{$art_table}` WHERE id = %d",
                $art_piece_id
            ));

            $this->publish(
                $prefix . '/bidder/' . $outbid_bidder,
                array(
                    'type'         => 'outbid',
                    'art_piece_id' => intval($art_piece_id),
                    'title'        => $title ?: 'Art Piece #' . $art_piece_id,
                ),
                true // Private topic
            );
        }
    }

    /**
     * Handle auction ended event
     *
     * @param int $art_piece_id
     */
    public function on_auction_ended($art_piece_id) {
        $prefix = self::get_topic_prefix();

        $this->publish(
            $prefix . '/auction/' . intval($art_piece_id),
            array(
                'type'         => 'auction_ended',
                'art_piece_id' => intval($art_piece_id),
            )
        );
    }
}
