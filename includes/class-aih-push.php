<?php
/**
 * Web Push Notifications for outbid and winner alerts
 *
 * Manages VAPID keys, push subscriptions, and sending notifications
 * when a bidder is outbid or wins an auction. Falls back to transient-based
 * polling for browsers that don't support push or deny permission.
 *
 * @package ArtInHeaven
 * @since   0.9.6
 */

if (!defined('ABSPATH')) {
    exit;
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class AIH_Push {

    /** @var AIH_Push|null */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return AIH_Push
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ========== ENDPOINT VALIDATION ==========

    /**
     * Allowed Web Push service host patterns.
     *
     * @var string[]
     */
    private static $allowed_push_hosts = array(
        'fcm.googleapis.com',
        'updates.push.services.mozilla.com',
        'push.services.mozilla.com',
        '.notify.windows.com',
        'web.push.apple.com',
    );

    /**
     * Validate that a push subscription endpoint uses HTTPS and belongs to a known push service.
     *
     * @param string $endpoint The push subscription endpoint URL.
     * @return bool True if the endpoint is HTTPS and its host matches an allowed push service pattern; false otherwise.
     */
    public static function is_valid_push_endpoint($endpoint) {
        if (empty($endpoint)) {
            return false;
        }

        $parsed = wp_parse_url($endpoint);

        if (!is_array($parsed)) {
            return false;
        }

        // Must be HTTPS
        if (empty($parsed['scheme']) || strtolower($parsed['scheme']) !== 'https') {
            return false;
        }

        if (empty($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);

        foreach (self::$allowed_push_hosts as $allowed) {
            // Wildcard suffix match (e.g. ".notify.windows.com")
            if (strpos($allowed, '.') === 0) {
                if (substr($host, -strlen($allowed)) === $allowed) {
                    return true;
                }
            } elseif ($host === $allowed) {
                return true;
            }
        }

        return false;
    }

    // ========== VAPID KEY MANAGEMENT ==========

    /** @var array<string, string>|null Cached VAPID keys for this request */
    private static $cached_vapid_keys = null;

    /**
     * Get VAPID keys, auto-generating on first call (cached per request)
     *
     * @return array<string, string>
     */
    public static function get_vapid_keys() {
        if (self::$cached_vapid_keys !== null) {
            return self::$cached_vapid_keys;
        }

        $public  = get_option('aih_vapid_public_key');
        $private = get_option('aih_vapid_private_key');
        $subject = get_option('aih_vapid_subject');

        if (empty($public) || empty($private)) {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
            $public  = $keys['publicKey'];
            $private = $keys['privateKey'];
            $subject = 'mailto:' . get_option('admin_email');

            update_option('aih_vapid_public_key', $public);
            update_option('aih_vapid_private_key', $private);
            update_option('aih_vapid_subject', $subject);
        }

        if (empty($subject)) {
            $subject = 'mailto:' . get_option('admin_email');
            update_option('aih_vapid_subject', $subject);
        }

        self::$cached_vapid_keys = array(
            'publicKey'  => $public,
            'privateKey' => $private,
            'subject'    => $subject,
        );

        return self::$cached_vapid_keys;
    }

    // ========== SUBSCRIPTION CRUD ==========

    /**
     * Save (upsert) a push subscription for a bidder
     *
     * @param string $bidder_id
     * @param string $endpoint
     * @param string $p256dh
     * @param string $auth
     * @return bool
     */
    public static function save_subscription($bidder_id, $endpoint, $p256dh, $auth) {
        global $wpdb;
        $table = AIH_Database::get_table('push_subscriptions');

        // Upsert by endpoint
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE endpoint = %s",
            $endpoint
        ));

        if ($existing) {
            return (bool) $wpdb->update(
                $table,
                array(
                    'bidder_id'  => sanitize_text_field($bidder_id),
                    'p256dh'     => sanitize_text_field($p256dh),
                    'auth_key'   => sanitize_text_field($auth),
                ),
                array('id' => $existing),
                array('%s', '%s', '%s'),
                array('%d')
            );
        }

        return (bool) $wpdb->insert(
            $table,
            array(
                'bidder_id'  => sanitize_text_field($bidder_id),
                'endpoint'   => esc_url_raw($endpoint),
                'p256dh'     => sanitize_text_field($p256dh),
                'auth_key'   => sanitize_text_field($auth),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Delete a push subscription by endpoint
     *
     * @param string $endpoint
     * @return bool
     */
    public static function delete_subscription($endpoint) {
        global $wpdb;
        $table = AIH_Database::get_table('push_subscriptions');

        return (bool) $wpdb->delete(
            $table,
            array('endpoint' => $endpoint),
            array('%s')
        );
    }

    /**
     * Get all push subscriptions for a bidder
     *
     * @param string $bidder_id
     * @return array<int, object>
     */
    public static function get_subscriptions($bidder_id) {
        global $wpdb;
        $table = AIH_Database::get_table('push_subscriptions');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE bidder_id = %s ORDER BY created_at DESC LIMIT 5",
            $bidder_id
        ));
    }

    // ========== NOTIFICATION SENDING ==========

    /**
     * Notify the outbid bidder when a new bid is placed.
     * Hooked to `aih_bid_placed` action.
     *
     * Record outbid event for polling fallback (runs synchronously).
     * Called directly from aih_bid_placed hook so the event is available immediately.
     *
     * @param int    $bid_id
     * @param int    $art_piece_id
     * @param string $new_bidder_id  The bidder who just placed the bid
     * @param float  $amount         The new bid amount
     * @return void
     */
    public function handle_outbid_event($bid_id, $art_piece_id, $new_bidder_id, $amount) {
        global $wpdb;

        $bids_table = AIH_Database::get_table('bids');
        $art_table  = AIH_Database::get_table('art_pieces');

        // Find the previous highest bidder (the one who was outbid)
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

        if (empty($outbid_bidder)) {
            return; // No one to notify (first bid)
        }

        // Get art piece title and catalog art_id
        /** @var object{title: string, art_id: string}|null $art_row */
        $art_row = $wpdb->get_row($wpdb->prepare(
            "SELECT title, art_id FROM `{$art_table}` WHERE id = %d",
            $art_piece_id
        ));
        $title = $art_row ? $art_row->title : '';
        $catalog_art_id = $art_row ? $art_row->art_id : '';

        if (empty($title)) {
            $title = 'Art Piece #' . $art_piece_id;
        }

        // Record event immediately for polling fallback (no bid amount shown)
        self::record_outbid_event($outbid_bidder, $art_piece_id, $title);

        // Defer push notification sending to after HTTP response
        if (get_option('aih_push_enabled', 1)) {
            $push_data = compact('outbid_bidder', 'art_piece_id', 'catalog_art_id', 'title');
            add_action('shutdown', function() use ($push_data) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                $url = $push_data['catalog_art_id'] ? AIH_Template_Helper::get_art_url($push_data['catalog_art_id']) : '';
                AIH_Push::get_instance()->send_push($push_data['outbid_bidder'], array(
                    'type'         => 'outbid',
                    'title'        => "You've been outbid!",
                    'body'         => sprintf('Someone outbid you on "%s".', $push_data['title']),
                    'item_title'   => $push_data['title'],
                    'art_piece_id' => $push_data['art_piece_id'],
                    'url'          => $url,
                    'tag'          => 'outbid-' . $push_data['art_piece_id'],
                ));
            });
        }
    }

    /**
     * Send a push notification to a bidder.
     *
     * @param string                $bidder_id The bidder to notify.
     * @param array<string, mixed> $payload   Notification payload (type, title, body, art_piece_id, url, tag).
     * @return void
     */
    public function send_push($bidder_id, array $payload) {
        $subscriptions = self::get_subscriptions($bidder_id);
        if (empty($subscriptions)) {
            return;
        }

        $payload['icon'] = AIH_PLUGIN_URL . 'assets/images/icon-192.png';
        $json_payload = wp_json_encode($payload);

        $vapid = self::get_vapid_keys();

        try {
            $auth = array(
                'VAPID' => array(
                    'subject'    => $vapid['subject'],
                    'publicKey'  => $vapid['publicKey'],
                    'privateKey' => $vapid['privateKey'],
                ),
            );

            $webPush = new WebPush($auth);

            foreach ($subscriptions as $sub) {
                /** @var stdClass $sub */
                if (!self::is_valid_push_endpoint($sub->endpoint)) {
                    self::delete_subscription($sub->endpoint);
                    continue;
                }
                $subscription = Subscription::create(array(
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh,
                    'authToken'       => $sub->auth_key,
                    'contentEncoding' => 'aes128gcm',
                ));
                $webPush->queueNotification($subscription, $json_payload ?: null);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSubscriptionExpired()) {
                    self::delete_subscription($report->getEndpoint());
                }
            }
        } catch (\Exception $e) {
            error_log('AIH Push notification error: ' . $e->getMessage());
        }
    }

    // ========== POLLING FALLBACK ==========

    /**
     * Record an outbid event in a transient for polling fallback
     *
     * @param string $bidder_id
     * @param int    $art_piece_id
     * @param string $title
     * @return void
     */
    // Note: This read-modify-write pattern is non-atomic. Under high concurrency,
    // an outbid event can be lost if two events are recorded simultaneously.
    // SSE/push notifications are the primary channels; this is a polling fallback.
    // For production with 1000+ users, an external object cache (Redis) is recommended.
    public static function record_outbid_event($bidder_id, $art_piece_id, $title) {
        $key    = 'aih_outbid_' . $bidder_id;
        $events = get_transient($key);

        if (!is_array($events)) {
            $events = array();
        }

        $events[] = array(
            'art_piece_id' => $art_piece_id,
            'title'        => $title,
            'time'         => time(),
        );

        set_transient($key, $events, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * Consume (return and delete) pending outbid events for a bidder
     *
     * @param string $bidder_id
     * @return array<int, array<string, mixed>>
     */
    // Note: This read-then-delete pattern is non-atomic. Under high concurrency,
    // an outbid event written between get_transient() and delete_transient() will be lost.
    // SSE/push notifications are the primary channels; this is a polling fallback.
    // For production with 1000+ users, an external object cache (Redis) is recommended.
    public static function consume_outbid_events($bidder_id) {
        $key    = 'aih_outbid_' . $bidder_id;
        $events = get_transient($key);

        if (!is_array($events) || empty($events)) {
            return array();
        }

        delete_transient($key);
        return $events;
    }

    // ========== WINNER NOTIFICATIONS ==========

    /**
     * Notify the winning bidder when an auction ends.
     * Called from poll_status() when it detects a newly ended auction with a winner.
     *
     * @param string $bidder_id     The winning bidder
     * @param int    $art_piece_id  The art piece that was won
     * @param string $title         Art piece title
     * @return void
     */
    public function handle_winner_event($bidder_id, $art_piece_id, $title) {
        // Record event for polling fallback
        self::record_winner_event($bidder_id, $art_piece_id, $title);

        // Send push notification
        if (get_option('aih_push_enabled', 1)) {
            $push_data = compact('bidder_id', 'art_piece_id', 'title');
            add_action('shutdown', function() use ($push_data) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                AIH_Push::get_instance()->send_push($push_data['bidder_id'], array(
                    'type'         => 'winner',
                    'title'        => 'You won!',
                    'body'         => sprintf('Congratulations! You won "%s". Head to checkout to complete your purchase.', $push_data['title']),
                    'item_title'   => $push_data['title'],
                    'art_piece_id' => $push_data['art_piece_id'],
                    'url'          => AIH_Template_Helper::get_checkout_url(),
                    'tag'          => 'winner-' . $push_data['art_piece_id'],
                ));
            });
        }
    }

    /**
     * Record a winner event in a transient for polling fallback
     *
     * @param string $bidder_id
     * @param int    $art_piece_id
     * @param string $title
     * @return void
     */
    public static function record_winner_event($bidder_id, $art_piece_id, $title) {
        $key    = 'aih_won_' . $bidder_id;
        $events = get_transient($key);

        if (!is_array($events)) {
            $events = array();
        }

        $events[] = array(
            'art_piece_id' => $art_piece_id,
            'title'        => $title,
            'time'         => time(),
        );

        set_transient($key, $events, 30 * MINUTE_IN_SECONDS);
    }

    /**
     * Consume (return and delete) pending winner events for a bidder
     *
     * @param string $bidder_id
     * @return array<int, array<string, mixed>>
     */
    public static function consume_winner_events($bidder_id) {
        $key    = 'aih_won_' . $bidder_id;
        $events = get_transient($key);

        if (!is_array($events) || empty($events)) {
            return array();
        }

        delete_transient($key);
        return $events;
    }

    /**
     * Check if a winner notification has already been sent for this bidder+art piece.
     * Uses a transient flag with 24h TTL to prevent duplicate notifications.
     *
     * @param string $bidder_id
     * @param int    $art_piece_id
     * @return bool
     */
    public static function was_winner_notified($bidder_id, $art_piece_id) {
        return (bool) get_transient('aih_won_notified_' . $bidder_id . '_' . $art_piece_id);
    }

    /**
     * Mark a winner notification as sent.
     *
     * @param string $bidder_id
     * @param int    $art_piece_id
     * @return void
     */
    public static function mark_winner_notified($bidder_id, $art_piece_id) {
        set_transient('aih_won_notified_' . $bidder_id . '_' . $art_piece_id, 1, DAY_IN_SECONDS);
    }
}
