<?php
/**
 * Template Helper Class
 *
 * Consolidates common utility methods used across templates
 * to eliminate code duplication.
 *
 * @package ArtInHeaven
 * @since 0.9.118
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Template_Helper {

    /** @var AIH_Template_Helper|null */
    private static $instance = null;

    /** @var array Cached page URLs */
    private static $page_cache = array();

    /**
     * Get single instance
     * @return AIH_Template_Helper
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get page URL by shortcode pattern
     *
     * Consolidates duplicate page lookup logic used in:
     * - checkout.php, gallery.php, login.php, my-bids.php, single-item.php, winners.php
     *
     * @param string $shortcode The shortcode to search for (e.g., 'art_in_heaven_gallery')
     * @param string $option_name Optional setting name to check first
     * @return string The page URL or home_url() as fallback
     */
    public static function get_page_url($shortcode, $option_name = '') {
        // Check cache first
        $cache_key = $shortcode . '_' . $option_name;
        if (isset(self::$page_cache[$cache_key])) {
            return self::$page_cache[$cache_key];
        }

        global $wpdb;

        // Check option first if provided
        $page_id = '';
        if ($option_name) {
            $page_id = get_option($option_name, '');
        }

        // If no option set, search for page with shortcode
        if (empty($page_id)) {
            $page_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page'
                 AND post_status = 'publish'
                 AND post_content LIKE %s
                 LIMIT 1",
                '%[' . $shortcode . '%'
            ));
        }

        $url = $page_id ? get_permalink($page_id) : home_url();

        // Cache the result
        self::$page_cache[$cache_key] = $url;

        return $url;
    }

    /**
     * Get gallery page URL
     * @return string
     */
    public static function get_gallery_url() {
        return self::get_page_url('art_in_heaven_gallery', 'aih_gallery_page');
    }

    /**
     * Get checkout page URL
     * @return string
     */
    public static function get_checkout_url() {
        return self::get_page_url('art_in_heaven_checkout', 'aih_checkout_page');
    }

    /**
     * Get login page URL
     * @return string
     */
    public static function get_login_url() {
        return self::get_page_url('art_in_heaven_login', 'aih_login_page');
    }

    /**
     * Get my bids page URL
     * @return string
     */
    public static function get_my_bids_url() {
        return self::get_page_url('art_in_heaven_my_bids', 'aih_my_bids_page');
    }

    /**
     * Get winners page URL
     * @return string
     */
    public static function get_winners_url() {
        return self::get_page_url('art_in_heaven_winners', 'aih_winners_page');
    }

    /**
     * Get my wins/collection page URL
     * @return string
     */
    public static function get_my_wins_url() {
        return self::get_page_url('art_in_heaven_my_wins', 'aih_my_wins_page');
    }

    /**
     * Get bidder display name
     *
     * Consolidates duplicate name extraction logic used in:
     * - checkout.php, gallery.php, my-bids.php, single-item.php
     *
     * @param object|null $bidder The bidder object
     * @param string $fallback Fallback value if no name found
     * @return string The display name
     */
    public static function get_bidder_display_name($bidder, $fallback = '') {
        if (!$bidder) {
            return $fallback;
        }

        // Check name_first first
        if (!empty($bidder->name_first)) {
            return $bidder->name_first;
        }

        // Then check individual_name (extract first name)
        if (!empty($bidder->individual_name)) {
            $parts = explode(' ', $bidder->individual_name);
            return $parts[0];
        }

        return $fallback;
    }

    /**
     * Format art piece data for API/AJAX responses
     *
     * Consolidates duplicate format_art_piece methods from:
     * - class-aih-ajax.php
     * - class-aih-rest-api.php
     *
     * @param object $piece The art piece object
     * @param int|null $bidder_id Optional bidder ID for personalized data
     * @param bool $full Whether to include full details
     * @param bool $include_time_string Whether to include formatted time string
     * @return array Formatted art piece data
     */
    public static function format_art_piece($piece, $bidder_id = null, $full = false, $include_time_string = false) {
        $secs = max(0, intval($piece->seconds_remaining ?? 0));

        $data = array(
            'id' => intval($piece->id),
            'art_id' => $piece->art_id,
            'title' => $piece->title,
            'artist' => $piece->artist,
            'medium' => $piece->medium,
            'starting_bid' => floatval($piece->starting_bid),
            'current_bid' => floatval($piece->current_bid),
            'image_url' => $piece->watermarked_url ?: $piece->image_url,
            'auction_end' => $piece->auction_end,
            'seconds_remaining' => $secs,
            'status' => $piece->status,
            'auction_ended' => $secs <= 0,
            'is_favorite' => isset($piece->is_favorite) ? (bool)$piece->is_favorite : false,
        );

        // Add formatted time string if requested (for AJAX responses)
        if ($include_time_string) {
            $data['time_remaining'] = self::format_time_remaining($secs);
        }

        // Add full details if requested
        if ($full) {
            $data['dimensions'] = $piece->dimensions;
            $data['description'] = $piece->description;
            $data['auction_start'] = $piece->auction_start ?? null;
        }

        // Add bidder-specific data
        if ($bidder_id) {
            $bid_model = new AIH_Bid();
            $data['is_winning'] = $bid_model->is_bidder_winning($piece->id, $bidder_id);
        }

        return $data;
    }

    /**
     * Format seconds remaining into human-readable string
     *
     * @param int $seconds Seconds remaining
     * @return string Formatted time string
     */
    public static function format_time_remaining($seconds) {
        if ($seconds <= 0) {
            return __('Ended', 'art-in-heaven');
        }

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get current bidder info
     *
     * Helper to get both bidder ID and name in one call
     *
     * @return array Array with 'id', 'bidder', and 'name' keys
     */
    public static function get_current_bidder_info() {
        $auth = AIH_Auth::get_instance();
        $is_logged_in = $auth->is_logged_in();
        $bidder = $is_logged_in ? $auth->get_current_bidder() : null;
        $bidder_id = $is_logged_in ? $auth->get_current_bidder_id() : null;
        $name = self::get_bidder_display_name($bidder, $bidder_id);

        return array(
            'id' => $bidder_id,
            'bidder' => $bidder,
            'name' => $name,
            'is_logged_in' => $is_logged_in,
        );
    }

    /**
     * Clear page URL cache
     */
    public static function clear_cache() {
        self::$page_cache = array();
    }
}
