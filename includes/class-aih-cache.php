<?php
/**
 * Cache Class
 * 
 * Provides caching functionality using WordPress transients and object cache.
 * Supports cache groups, expiration, and invalidation patterns.
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
     * Track cache keys by group for invalidation
     * 
     * @var array
     */
    private static $group_keys = array();
    
    /**
     * Get a cached value
     * 
     * @param string $key     Cache key
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        $full_key = self::PREFIX . $key;
        
        // Try object cache first (faster)
        if (wp_using_ext_object_cache()) {
            $value = wp_cache_get($key, self::GROUP);
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
        
        $full_key = self::PREFIX . $key;
        
        // Track key in group for invalidation
        if (!empty($group)) {
            self::add_to_group($group, $key);
        }
        
        // Use object cache if available
        if (wp_using_ext_object_cache()) {
            wp_cache_set($key, $value, self::GROUP, $expiry);
        }
        
        // Also set transient for persistence
        return set_transient($full_key, $value, $expiry);
    }
    
    /**
     * Delete a cached value
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function delete($key) {
        $full_key = self::PREFIX . $key;
        
        // Delete from object cache
        if (wp_using_ext_object_cache()) {
            wp_cache_delete($key, self::GROUP);
        }
        
        // Delete transient
        return delete_transient($full_key);
    }
    
    /**
     * Check if a key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function exists($key) {
        return self::get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
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
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = call_user_func($callback);
        
        if ($value !== null) {
            self::set($key, $value, $expiry, $group);
        }
        
        return $value;
    }
    
    /**
     * Add a key to a group for batch invalidation
     * 
     * @param string $group Group name
     * @param string $key   Cache key
     */
    private static function add_to_group($group, $key) {
        $group_key = self::PREFIX . 'group_' . $group;
        $keys = get_option($group_key, array());
        
        if (!in_array($key, $keys)) {
            $keys[] = $key;
            update_option($group_key, $keys, false);
        }
    }
    
    /**
     * Delete all keys in a group
     * 
     * @param string $group Group name
     * @return int Number of keys deleted
     */
    public static function delete_group($group) {
        $group_key = self::PREFIX . 'group_' . $group;
        $keys = get_option($group_key, array());
        $count = 0;
        
        foreach ($keys as $key) {
            if (self::delete($key)) {
                $count++;
            }
        }
        
        // Clear the group tracking
        delete_option($group_key);
        
        return $count;
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
        if (wp_using_ext_object_cache()) {
            wp_cache_flush_group(self::GROUP);
        }
        
        // Clear group tracking options
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                self::PREFIX . 'group_%'
            )
        );
        
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
            $transient_key = str_replace('_transient_timeout_', '', $timeout_key);
            delete_transient(str_replace(self::PREFIX, '', $transient_key));
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
        return self::get($key);
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
        return self::get('art_piece_' . $id);
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
        return self::get('bidder_' . md5($identifier));
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
        self::delete('bidder_' . md5($identifier));
    }
}
