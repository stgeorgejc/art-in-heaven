<?php
/**
 * Cache Class
 *
 * Provides caching functionality using WordPress transients and object cache.
 * Supports cache groups, expiration, and invalidation patterns.
 *
 * Uses a version-counter approach for group invalidation: instead of tracking
 * every key in a group (which causes DB row-lock bottlenecks), each group has
 * an integer version. Cache keys include the version, so incrementing the
 * version makes all old keys unreachable and they naturally expire.
 *
 * @package ArtInHeaven
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Cache {

    /**
     * Cache prefix
     */
    const PREFIX = 'aih_cache_';

    /**
     * Cache group prefix for object cache
     */
    const GROUP = 'art_in_heaven';

    /**
     * Default expiration time in seconds
     */
    const DEFAULT_EXPIRY = HOUR_IN_SECONDS;

    /**
     * Sentinel value to distinguish "not cached" from "cached null"
     */
    const CACHE_MISS = '__AIH_CACHE_MISS__';

    /**
     * In-memory cache of group versions to avoid repeated lookups within a request
     *
     * @var array
     */
    private static $group_versions = array();

    /**
     * Get the current version counter for a cache group.
     *
     * Checks the object cache first (if available), then falls back to wp_options.
     *
     * @param string $group Group name
     * @return int Current version number (starts at 1)
     */
    public static function get_group_version($group) {
        if (isset(self::$group_versions[$group])) {
            return self::$group_versions[$group];
        }

        $option_key = self::PREFIX . 'gv_' . $group;

        if (wp_using_ext_object_cache()) {
            $version = wp_cache_get($option_key, self::GROUP);
            if ($version !== false) {
                self::$group_versions[$group] = (int) $version;
                return self::$group_versions[$group];
            }
        }

        $version = get_option($option_key, 0);
        if (!$version) {
            $version = 1;
            update_option($option_key, $version, false);
        }
        $version = (int) $version;

        // Populate object cache so subsequent reads skip the DB
        if (wp_using_ext_object_cache()) {
            wp_cache_set($option_key, $version, self::GROUP, 0);
        }

        self::$group_versions[$group] = $version;
        return $version;
    }

    /**
     * Build a versioned cache key when a group is provided.
     *
     * @param string $key   Original cache key
     * @param string $group Group name (empty string = no versioning)
     * @return string Effective key
     */
    private static function versioned_key($key, $group) {
        if (empty($group)) {
            return $key;
        }
        $version = self::get_group_version($group);
        return $key . '_v' . $version;
    }

    /**
     * Get a cached value
     *
     * @param string $key     Cache key
     * @param mixed  $default Default value if not found
     * @param string $group   Optional group (needed to resolve versioned key)
     * @return mixed
     */
    public static function get($key, $default = null, $group = '') {
        $effective_key = self::versioned_key($key, $group);
        $full_key = self::PREFIX . $effective_key;

        // Try object cache first (faster)
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($effective_key, self::GROUP);
            if ($value !== false) {
                return $value;
            }
        }

        // Fall back to transients
        $value = get_transient($full_key);

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Set a cached value
     *
     * @param string $key    Cache key
     * @param mixed  $value  Value to cache
     * @param int    $expiry Expiration in seconds
     * @param string $group  Optional group for invalidation
     * @return bool
     */
    public static function set($key, $value, $expiry = null, $group = '') {
        if ($expiry === null) {
            $expiry = self::DEFAULT_EXPIRY;
        }

        $effective_key = self::versioned_key($key, $group);
        $full_key = self::PREFIX . $effective_key;

        // Use object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_set($effective_key, $value, self::GROUP, $expiry);
            // Skip the redundant set_transient() call when an external object
            // cache is active -- transients already delegate to the object cache
            // in that scenario, so writing to both is a double-write.
            return true;
        }

        // Persist via transient when no external object cache
        return set_transient($full_key, $value, $expiry);
    }

    /**
     * Delete a cached value
     *
     * @param string $key   Cache key
     * @param string $group Optional group (needed to resolve versioned key)
     * @return bool
     */
    public static function delete($key, $group = '') {
        $effective_key = self::versioned_key($key, $group);
        $full_key = self::PREFIX . $effective_key;

        // Delete from object cache
        if (wp_using_ext_object_cache()) {
            wp_cache_delete($effective_key, self::GROUP);
        }

        // Delete transient
        return delete_transient($full_key);
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key   Cache key
     * @param string $group Optional group
     * @return bool
     */
    public static function exists($key, $group = '') {
        return self::get($key, '__NOT_FOUND__', $group) !== '__NOT_FOUND__';
    }

    /**
     * Get or set cache value using callback
     *
     * @param string   $key      Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int      $expiry   Expiration in seconds
     * @param string   $group    Optional group
     * @return mixed
     */
    public static function remember($key, $callback, $expiry = null, $group = '') {
        $value = self::get($key, self::CACHE_MISS, $group);

        if ($value !== self::CACHE_MISS) {
            return $value;
        }

        $value = call_user_func($callback);

        self::set($key, $value, $expiry, $group);

        return $value;
    }

    /**
     * Invalidate all keys in a group by incrementing the version counter.
     *
     * Old keys with the previous version naturally expire and become unreachable
     * because new reads will look for the incremented version.
     *
     * @param string $group Group name
     * @return int The new version number
     */
    public static function delete_group($group) {
        $option_key = self::PREFIX . 'gv_' . $group;

        // Increment the version in the database
        $current = (int) get_option($option_key, 0);
        $new_version = $current + 1;
        update_option($option_key, $new_version, false);

        // Update object cache so reads within this request see the new version
        if (wp_using_ext_object_cache()) {
            wp_cache_set($option_key, $new_version, self::GROUP, 0);
        }

        // Update in-memory cache
        self::$group_versions[$group] = $new_version;

        return $new_version;
    }

    /**
     * Flush all plugin caches
     *
     * @return bool
     */
    public static function flush_all() {
        global $wpdb;

        // Delete all transients with our prefix
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . self::PREFIX . '%',
                '_transient_timeout_' . self::PREFIX . '%'
            )
        );

        // Clear object cache group if available
        if (wp_using_ext_object_cache() && function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::GROUP);
        }

        // Clear group version options
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                self::PREFIX . 'gv_%'
            )
        );

        // Reset in-memory version cache
        self::$group_versions = array();

        return true;
    }

    /**
     * Cleanup expired transients
     *
     * @return int Number of expired transients deleted
     */
    public static function cleanup_expired() {
        global $wpdb;

        $time = time();

        // Get expired transient timeouts
        $expired = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                WHERE option_name LIKE %s
                AND option_value < %d",
                '_transient_timeout_' . self::PREFIX . '%',
                $time
            )
        );

        $count = 0;
        foreach ($expired as $timeout_key) {
            // Strip '_transient_timeout_' to get the transient name
            $transient_name = substr($timeout_key, strlen('_transient_timeout_'));
            delete_transient($transient_name);
            $count++;
        }

        return $count;
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;

        // Count transients
        $transient_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::PREFIX . '%'
            )
        );

        // Estimate size
        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::PREFIX . '%'
            )
        );

        return array(
            'count' => intval($transient_count),
            'size_bytes' => intval($size),
            'size_human' => size_format($size),
            'object_cache' => wp_using_ext_object_cache(),
        );
    }

    /**
     * Cache art pieces list
     *
     * @param array $args Query arguments
     * @return array|null
     */
    public static function get_art_pieces($args = array()) {
        $key = 'art_pieces_' . md5(serialize($args));
        return self::get($key, null, 'art_pieces');
    }

    /**
     * Cache art pieces list
     *
     * @param array $args   Query arguments
     * @param array $pieces The art pieces data
     * @return bool
     */
    public static function set_art_pieces($args, $pieces) {
        $key = 'art_pieces_' . md5(serialize($args));
        return self::set($key, $pieces, 5 * MINUTE_IN_SECONDS, 'art_pieces');
    }

    /**
     * Get cached single art piece
     *
     * @param int $id Art piece ID
     * @return object|null
     */
    public static function get_art_piece($id) {
        return self::get('art_piece_' . $id, null, 'art_pieces');
    }

    /**
     * Cache single art piece
     *
     * @param int    $id    Art piece ID
     * @param object $piece Art piece data
     * @return bool
     */
    public static function set_art_piece($id, $piece) {
        return self::set('art_piece_' . $id, $piece, 5 * MINUTE_IN_SECONDS, 'art_pieces');
    }

    /**
     * Get cached bidder
     *
     * @param string $identifier Email or confirmation code
     * @return object|null
     */
    public static function get_bidder($identifier) {
        return self::get('bidder_' . md5($identifier), null, 'bidders');
    }

    /**
     * Cache bidder
     *
     * @param string $identifier Email or confirmation code
     * @param object $bidder     Bidder data
     * @return bool
     */
    public static function set_bidder($identifier, $bidder) {
        return self::set('bidder_' . md5($identifier), $bidder, 10 * MINUTE_IN_SECONDS, 'bidders');
    }

    /**
     * Invalidate bidder cache
     *
     * @param string $identifier Email or confirmation code
     */
    public static function invalidate_bidder($identifier) {
        self::delete('bidder_' . md5($identifier), 'bidders');
    }
}
