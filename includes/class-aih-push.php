<?php
/**
 * Web Push Notifications for outbid alerts
 *
 * Manages VAPID keys, push subscriptions, and sending notifications
 * when a bidder is outbid. Falls back to transient-based polling for
 * browsers that don't support push or deny permission.
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

    // ========== VAPID KEY MANAGEMENT ==========

    /**
     * Get VAPID keys, auto-generating on first call
     *
     * @return array{publicKey: string, privateKey: string, subject: string}
     */
    public static function get_vapid_keys() {
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

        return array(
            'publicKey'  => $public,
            'privateKey' => $private,
            'subject'    => $subject,
        );
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
     * @return array
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
     * @param int    $bid_id
     * @param int    $art_piece_id
     * @param string $new_bidder_id  The bidder who just placed the bid
     * @param float  $amount         The new bid amount
     */
    /**
     * Record outbid event for polling fallback (runs synchronously).
     * Called directly from aih_bid_placed hook so the event is available immediately.
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

        // Get art piece title
        $title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM `{$art_table}` WHERE id = %d",
            $art_piece_id
        ));

        if (empty($title)) {
            $title = 'Art Piece #' . $art_piece_id;
        }

        // Record event immediately for polling fallback (no bid amount shown)
        self::record_outbid_event($outbid_bidder, $art_piece_id, $title);

        // Defer push notification sending to after HTTP response
        $push_data = compact('outbid_bidder', 'art_piece_id', 'title');
        add_action('shutdown', function() use ($push_data) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            AIH_Push::get_instance()->send_push($push_data['outbid_bidder'], $push_data['art_piece_id'], $push_data['title']);
        });
    }

    /**
     * Send push notification to outbid bidder (runs deferred in shutdown hook).
     */
    public function send_push($outbid_bidder, $art_piece_id, $title) {
        $subscriptions = self::get_subscriptions($outbid_bidder);
        if (empty($subscriptions)) {
            return;
        }

        $vapid = self::get_vapid_keys();

        $gallery_page = get_option('aih_gallery_page');
        $base_url = $gallery_page ? get_permalink($gallery_page) : home_url('/');
        $url = add_query_arg('art_id', $art_piece_id, $base_url);

        $payload = wp_json_encode(array(
            'type'         => 'outbid',
            'title'        => "You've been outbid!",
            'body'         => sprintf('Someone outbid you on "%s".', $title),
            'art_piece_id' => $art_piece_id,
            'url'          => $url,
            'tag'          => 'outbid-' . $art_piece_id,
            'icon'         => AIH_PLUGIN_URL . 'assets/images/icon-192.png',
        ));

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
                $subscription = Subscription::create(array(
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->p256dh,
                    'authToken'       => $sub->auth_key,
                    'contentEncoding' => 'aes128gcm',
                ));
                $webPush->queueNotification($subscription, $payload);
            }

            // Flush and handle responses
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
     * @return array
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
}
