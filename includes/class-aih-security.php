<?php
/**
 * Security Class
 * 
 * Provides centralized security functions including:
 * - Input sanitization
 * - Output escaping
 * - Nonce verification
 * - Capability checking
 * - Rate limiting
 * 
 * @package ArtInHeaven
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Security {
    
    /**
     * Rate limit storage key prefix
     */
    const RATE_LIMIT_PREFIX = 'aih_rate_';
    
    /**
     * Default rate limit (requests per minute)
     */
    const DEFAULT_RATE_LIMIT = 60;
    
    /**
     * Sanitize and validate input based on type
     * 
     * @param mixed  $value The value to sanitize
     * @param string $type  The type of sanitization
     * @param array  $args  Additional arguments
     * @return mixed
     */
    public static function sanitize($value, $type = 'text', $args = array()) {
        if ($value === null || $value === '') {
            return $args['default'] ?? '';
        }
        
        switch ($type) {
            case 'int':
            case 'integer':
                $value = intval($value);
                if (isset($args['min']) && $value < $args['min']) {
                    $value = $args['min'];
                }
                if (isset($args['max']) && $value > $args['max']) {
                    $value = $args['max'];
                }
                return $value;
                
            case 'float':
            case 'decimal':
                $value = floatval($value);
                if (isset($args['min']) && $value < $args['min']) {
                    $value = $args['min'];
                }
                if (isset($args['max']) && $value > $args['max']) {
                    $value = $args['max'];
                }
                return round($value, $args['precision'] ?? 2);
                
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            case 'email':
                $value = sanitize_email($value);
                if (!is_email($value)) {
                    return $args['default'] ?? '';
                }
                return $value;
                
            case 'url':
                $value = esc_url_raw($value);
                if (empty($value) && !empty($args['default'])) {
                    return $args['default'];
                }
                return $value;
                
            case 'key':
                return sanitize_key($value);
                
            case 'slug':
                return sanitize_title($value);
                
            case 'filename':
                return sanitize_file_name($value);
                
            case 'html':
                return wp_kses_post($value);
                
            case 'textarea':
                return sanitize_textarea_field($value);
                
            case 'array':
                if (!is_array($value)) {
                    return $args['default'] ?? array();
                }
                $sanitized = array();
                $item_type = $args['item_type'] ?? 'text';
                foreach ($value as $k => $v) {
                    $sanitized[sanitize_key($k)] = self::sanitize($v, $item_type);
                }
                return $sanitized;
                
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }
                return $args['default'] ?? array();
                
            case 'date':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return $args['default'] ?? '';
                }
                return date('Y-m-d', $timestamp);
                
            case 'datetime':
                $timestamp = strtotime($value);
                if ($timestamp === false) {
                    return $args['default'] ?? '';
                }
                return date('Y-m-d H:i:s', $timestamp);
                
            case 'phone':
                // Remove all non-numeric characters except + for international
                $value = preg_replace('/[^0-9+]/', '', $value);
                return sanitize_text_field($value);
                
            case 'currency':
                // Remove currency symbols, keep numbers and decimal
                $value = preg_replace('/[^0-9.]/', '', $value);
                return self::sanitize($value, 'float', array('min' => 0, 'precision' => 2));
                
            case 'confirmation_code':
                // Alphanumeric, uppercase, specific length
                $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value));
                return substr($value, 0, 20);
                
            case 'art_id':
                // Art ID format: letters, numbers, dashes
                $value = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $value));
                return sanitize_text_field($value);
                
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Sanitize multiple fields at once
     * 
     * @param array $data   The data to sanitize
     * @param array $schema Schema defining field types
     * @return array
     */
    public static function sanitize_fields($data, $schema) {
        $sanitized = array();
        
        foreach ($schema as $field => $config) {
            $type = is_array($config) ? ($config['type'] ?? 'text') : $config;
            $args = is_array($config) ? $config : array();
            
            $value = isset($data[$field]) ? $data[$field] : ($args['default'] ?? null);
            
            // Check if required
            if (!empty($args['required']) && ($value === null || $value === '')) {
                continue; // Skip or handle required validation elsewhere
            }
            
            $sanitized[$field] = self::sanitize($value, $type, $args);
        }
        
        return $sanitized;
    }
    
    /**
     * Escape output based on context
     * 
     * @param mixed  $value   The value to escape
     * @param string $context The context (html, attr, url, js, textarea)
     * @return string
     */
    public static function escape($value, $context = 'html') {
        if ($value === null) {
            return '';
        }
        
        switch ($context) {
            case 'attr':
            case 'attribute':
                return esc_attr($value);
                
            case 'url':
                return esc_url($value);
                
            case 'js':
            case 'javascript':
                return esc_js($value);
                
            case 'textarea':
                return esc_textarea($value);
                
            case 'sql':
                global $wpdb;
                return $wpdb->_real_escape($value);
                
            case 'html':
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Verify nonce with specific action
     * 
     * @param string $nonce  The nonce to verify
     * @param string $action The action name
     * @return bool
     */
    public static function verify_nonce($nonce, $action = 'aih_nonce') {
        return wp_verify_nonce($nonce, $action) !== false;
    }
    
    /**
     * Verify AJAX nonce from request
     * 
     * @param string $action The action name
     * @param string $key    The POST/GET key containing the nonce
     * @return bool
     */
    public static function verify_ajax_nonce($action = 'aih_nonce', $key = 'nonce') {
        $nonce = isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : '';
        return self::verify_nonce($nonce, $action);
    }
    
    /**
     * Check AJAX referer and die on failure
     * 
     * @param string $action The action name
     * @param string $key    The POST/GET key containing the nonce
     */
    public static function check_ajax_referer($action = 'aih_nonce', $key = 'nonce') {
        if (!self::verify_ajax_nonce($action, $key)) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh and try again.', 'art-in-heaven'),
                'code' => 'invalid_nonce'
            ), 403);
        }
    }
    
    /**
     * Check if current user has capability
     * 
     * @param string $capability The capability to check
     * @return bool
     */
    public static function can($capability) {
        return current_user_can($capability);
    }
    
    /**
     * Check capability and die on failure
     * 
     * @param string $capability The capability to check
     * @param string $message    Custom error message
     */
    public static function require_capability($capability, $message = null) {
        if (!self::can($capability)) {
            if ($message === null) {
                $message = __('You do not have permission to perform this action.', 'art-in-heaven');
            }
            wp_send_json_error(array(
                'message' => $message,
                'code' => 'insufficient_permissions'
            ), 403);
        }
    }
    
    /**
     * Rate limit check
     * 
     * @param string $identifier Unique identifier (user ID, IP, etc.)
     * @param int    $limit      Maximum requests allowed
     * @param int    $window     Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public static function check_rate_limit($identifier, $limit = null, $window = 60) {
        if ($limit === null) {
            $limit = self::DEFAULT_RATE_LIMIT;
        }
        
        $key = self::RATE_LIMIT_PREFIX . md5($identifier);
        $data = get_transient($key);
        
        if ($data === false) {
            // First request
            set_transient($key, array(
                'count' => 1,
                'start' => time()
            ), $window);
            return true;
        }
        
        // Check if window expired
        if (time() - $data['start'] > $window) {
            set_transient($key, array(
                'count' => 1,
                'start' => time()
            ), $window);
            return true;
        }
        
        // Increment and check
        $data['count']++;
        set_transient($key, $data, $window - (time() - $data['start']));
        
        return $data['count'] <= $limit;
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Generate a secure random token
     * 
     * @param int $length Token length
     * @return string
     */
    public static function generate_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }
        return wp_generate_password($length, false, false);
    }
    
    /**
     * Hash a value securely
     * 
     * @param string $value The value to hash
     * @return string
     */
    public static function hash($value) {
        return wp_hash($value);
    }
    
    /**
     * Validate that a value is in an allowed list
     * 
     * @param mixed $value   The value to check
     * @param array $allowed Allowed values
     * @param mixed $default Default value if not in list
     * @return mixed
     */
    public static function whitelist($value, $allowed, $default = null) {
        if (in_array($value, $allowed, true)) {
            return $value;
        }
        return $default !== null ? $default : (isset($allowed[0]) ? $allowed[0] : null);
    }
    
    /**
     * Sanitize SQL order by clause
     * 
     * @param string $orderby  The column name
     * @param array  $allowed  Allowed column names
     * @param string $default  Default column
     * @return string
     */
    public static function sanitize_orderby($orderby, $allowed, $default = 'id') {
        return self::whitelist(sanitize_key($orderby), $allowed, $default);
    }
    
    /**
     * Sanitize SQL order direction
     * 
     * @param string $order The order direction
     * @return string
     */
    public static function sanitize_order($order) {
        return strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
    }
    
    /**
     * Log security event
     * 
     * @param string $event   Event type
     * @param array  $context Additional context
     */
    public static function log_event($event, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $context['ip'] = self::get_client_ip();
        $context['user_id'] = get_current_user_id();
        $context['time'] = current_time('mysql');
        
        error_log(sprintf(
            '[Art in Heaven Security] %s: %s',
            $event,
            wp_json_encode($context)
        ));
    }
    
    /**
     * Check if request is from admin context
     * 
     * @return bool
     */
    public static function is_admin_request() {
        return is_admin() && !wp_doing_ajax();
    }
    
    /**
     * Check if this is a REST API request
     * 
     * @return bool
     */
    public static function is_rest_request() {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        if (isset($_SERVER['REQUEST_URI'])) {
            $rest_prefix = rest_get_url_prefix();
            return strpos($_SERVER['REQUEST_URI'], '/' . $rest_prefix . '/') !== false;
        }
        
        return false;
    }
    
    /**
     * Prevent caching for sensitive pages
     */
    public static function prevent_caching() {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        
        nocache_headers();
    }
}
