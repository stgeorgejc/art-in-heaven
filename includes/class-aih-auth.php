<?php
/**
 * Authentication & Bidder Management
 * 
 * Uses two tables:
 * - Registrants: All people from CCB API (pre-loaded)
 * - Bidders: Only people who have logged in (copied from Registrants on first login)
 * 
 * IMPORTANT: bidder_id is the confirmation_code, NOT the email
 * 
 * SESSION HANDLING: Sessions are only started for specific frontend actions
 * and are immediately closed after reading to prevent REST API/loopback issues.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Auth {
    
    private static $instance = null;
    private $session_key = 'aih_bidder_session';
    private $session_data = null; // Cache session data in memory
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Don't auto-start sessions - only start when actually needed
        // This prevents the site health warning about sessions
    }
    
    /**
     * Check if this is a REST API request
     */
    private function is_rest_request() {
        // Check the constant first (most reliable)
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }
        
        // Check for REST API URL patterns
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, '/wp-json/') !== false) {
                return true;
            }
            if (strpos($uri, 'rest_route=') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Close PHP session to prevent locking during long operations
     */
    private function close_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
    
    /**
     * Check if this is a loopback request
     */
    private function is_loopback_request() {
        // WordPress site health checks
        if (isset($_GET['health-check-test-request'])) {
            return true;
        }
        
        // Check user agent for WordPress loopback
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'WordPress') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if we should use sessions at all
     */
    private function should_use_session() {
        // Never use sessions for REST, AJAX heartbeat, cron, or loopback
        if ($this->is_rest_request()) {
            return false;
        }
        
        if ($this->is_loopback_request()) {
            return false;
        }
        
        if (wp_doing_cron()) {
            return false;
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // Allow sessions for our AJAX actions only
            if (isset($_REQUEST['action'])) {
                $our_actions = array(
                    'aih_verify_code', 'aih_logout', 'aih_check_auth',
                    'aih_place_bid', 'aih_toggle_favorite', 'aih_get_gallery',
                    'aih_get_art_details', 'aih_search', 'aih_get_won_items',
                    'aih_create_order', 'aih_get_pushpay_link',
                    'aih_get_order_details', 'aih_get_my_purchases'
                );
                return in_array($_REQUEST['action'], $our_actions);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Read session data - starts session, reads, and immediately closes
     */
    private function read_session() {
        // Return cached data if available
        if ($this->session_data !== null) {
            return $this->session_data;
        }
        
        // Don't use sessions for REST/cron/loopback
        if (!$this->should_use_session()) {
            $this->session_data = array();
            return $this->session_data;
        }
        
        // Can't start session if headers sent
        if (headers_sent()) {
            $this->session_data = array();
            return $this->session_data;
        }
        
        // Start session if not active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_id()) {
                session_start();
            }
        }
        
        // Read our data
        $this->session_data = isset($_SESSION[$this->session_key]) ? $_SESSION[$this->session_key] : array();
        
        // Immediately close the session to prevent blocking
        session_write_close();
        
        return $this->session_data;
    }
    
    /**
     * Write session data - starts session, writes, and immediately closes
     */
    private function write_session($data) {
        // Don't use sessions for REST/cron/loopback
        if (!$this->should_use_session()) {
            return false;
        }
        
        // Can't start session if headers sent
        if (headers_sent()) {
            return false;
        }
        
        // Start session if not active
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_id()) {
                session_start();
            }
        }
        
        // Write our data
        $_SESSION[$this->session_key] = $data;
        $this->session_data = $data;
        
        // Immediately close the session
        session_write_close();
        
        return true;
    }
    
    /**
     * Clear session data
     */
    private function clear_session() {
        if (!$this->should_use_session()) {
            $this->session_data = array();
            return true;
        }
        
        if (headers_sent()) {
            return false;
        }
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!session_id()) {
                session_start();
            }
        }
        
        unset($_SESSION[$this->session_key]);
        $this->session_data = array();
        
        session_write_close();
        
        return true;
    }
    
    // =========================================================================
    // API SYNC - Saves to REGISTRANTS table
    // =========================================================================
    
    /**
     * Sync all registrants from CCB API to Registrants table
     */
    public function sync_bidders_from_api() {
        global $wpdb;
        
        // Close session before making HTTP requests to avoid loopback issues
        $this->close_session();
        
        $api = AIH_CCB_API::get_instance();
        $result = $api->get_form_responses();
        
        if (!$result['success']) {
            return array(
                'success' => false,
                'message' => $result['error'] ?? __('API sync failed.', 'art-in-heaven'),
            );
        }
        
        if (empty($result['data'])) {
            return array(
                'success' => false,
                'message' => __('No registrants found in API response.', 'art-in-heaven'),
                'count' => 0,
            );
        }
        
        $stats = array('inserted' => 0, 'updated' => 0, 'skipped' => 0);
        
        foreach ($result['data'] as $registrant) {
            if (empty($registrant['confirmation_code'])) {
                $stats['skipped']++;
                continue;
            }
            
            $save_result = $this->save_registrant($registrant);
            
            if ($save_result === 'inserted') {
                $stats['inserted']++;
            } elseif ($save_result === 'updated') {
                $stats['updated']++;
            } else {
                $stats['skipped']++;
            }
        }
        
        // Update sync metadata
        update_option('aih_last_bidder_sync', current_time('mysql'));
        update_option('aih_last_sync_count', $result['count']);
        
        return array(
            'success' => true,
            'message' => sprintf(
                __('Sync complete: %d new, %d updated, %d skipped', 'art-in-heaven'),
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped']
            ),
            'total' => $result['count'],
            'inserted' => $stats['inserted'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
        );
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        $this->close_session();
        return AIH_CCB_API::get_instance()->test_connection();
    }
    
    /**
     * Get sync status
     */
    public function get_sync_status() {
        return array(
            'last_sync' => get_option('aih_last_bidder_sync', __('Never', 'art-in-heaven')),
            'last_count' => (int) get_option('aih_last_sync_count', 0),
            'registrant_count' => $this->get_registrant_count(),
            'bidder_count' => $this->get_bidder_count(),
            'current_count' => $this->get_registrant_count(),
        );
    }
    
    // =========================================================================
    // REGISTRANT DATABASE OPERATIONS (from API)
    // =========================================================================
    
    /**
     * Save or update a registrant (from API sync)
     */
    public function save_registrant($data) {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        
        $fields = array(
            'confirmation_code', 'email_primary', 'name_first', 'name_last',
            'phone_mobile', 'phone_home', 'phone_work', 'birthday', 'gender',
            'marital_status', 'mailing_street', 'mailing_city', 'mailing_state',
            'mailing_zip', 'individual_id', 'individual_name', 'api_data'
        );
        
        $existing = null;
        if (!empty($data['confirmation_code'])) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE confirmation_code = %s",
                $data['confirmation_code']
            ));
        }
        
        if ($existing) {
            $update = array('updated_at' => current_time('mysql'));
            
            foreach ($fields as $field) {
                $new_val = isset($data[$field]) ? $data[$field] : '';
                $old_val = isset($existing->$field) ? $existing->$field : '';
                
                if ($field === 'api_data' || (!empty($new_val) && $new_val !== $old_val)) {
                    $update[$field] = $new_val;
                }
            }
            
            $wpdb->update($table, $update, array('id' => $existing->id));
            return 'updated';
        } else {
            $insert = array(
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'has_logged_in' => 0,
                'has_bid' => 0,
            );
            
            foreach ($fields as $field) {
                $insert[$field] = isset($data[$field]) ? $data[$field] : '';
            }
            
            $wpdb->insert($table, $insert);
            return $wpdb->insert_id ? 'inserted' : false;
        }
    }
    
    /**
     * Get registrant by confirmation code
     */
    public function get_registrant_by_confirmation_code($code) {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE confirmation_code = %s",
            trim(strtoupper($code))
        ));
    }
    
    /**
     * Get all registrants
     */
    public function get_all_registrants() {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name_last, name_first ASC");
    }
    
    /**
     * Get registrant count
     */
    public function get_registrant_count() {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    /**
     * Get registrants who haven't logged in
     */
    public function get_registrants_not_logged_in() {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        return $wpdb->get_results("SELECT * FROM $table WHERE has_logged_in = 0 ORDER BY name_last, name_first ASC");
    }
    
    /**
     * Get registrants who logged in but haven't bid
     */
    public function get_registrants_no_bids() {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        return $wpdb->get_results("SELECT * FROM $table WHERE has_logged_in = 1 AND has_bid = 0 ORDER BY name_last, name_first ASC");
    }
    
    // =========================================================================
    // BIDDER DATABASE OPERATIONS (logged-in users)
    // =========================================================================
    
    /**
     * Copy registrant to bidders table (called on first login)
     */
    private function copy_to_bidders($registrant) {
        global $wpdb;
        $bidders_table = AIH_Database::get_table('bidders');
        
        // Check if already in bidders
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $bidders_table WHERE confirmation_code = %s",
            $registrant->confirmation_code
        ));
        
        if ($existing) {
            // Update last login
            $wpdb->update(
                $bidders_table,
                array('last_login' => current_time('mysql')),
                array('id' => $existing->id)
            );
            return $existing->id;
        }
        
        // Copy ALL fields to bidders table
        $insert = array(
            'confirmation_code' => $registrant->confirmation_code,
            'email_primary' => $registrant->email_primary,
            'name_first' => $registrant->name_first,
            'name_last' => $registrant->name_last,
            'phone_mobile' => $registrant->phone_mobile,
            'phone_home' => $registrant->phone_home,
            'phone_work' => $registrant->phone_work,
            'birthday' => $registrant->birthday,
            'gender' => $registrant->gender,
            'marital_status' => $registrant->marital_status,
            'mailing_street' => $registrant->mailing_street,
            'mailing_city' => $registrant->mailing_city,
            'mailing_state' => $registrant->mailing_state,
            'mailing_zip' => $registrant->mailing_zip,
            'individual_id' => $registrant->individual_id,
            'individual_name' => $registrant->individual_name,
            'api_data' => $registrant->api_data,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_login' => current_time('mysql'),
        );
        
        $wpdb->insert($bidders_table, $insert);
        return $wpdb->insert_id ?: false;
    }
    
    /**
     * Mark registrant as having placed a bid (by confirmation_code)
     */
    public function mark_registrant_has_bid($confirmation_code) {
        global $wpdb;
        $table = AIH_Database::get_table('registrants');
        $wpdb->update(
            $table, 
            array('has_bid' => 1), 
            array('confirmation_code' => trim(strtoupper($confirmation_code)))
        );
    }
    
    /**
     * Get bidder by confirmation code
     */
    public function get_bidder_by_confirmation_code($code) {
        global $wpdb;
        $code = trim(strtoupper($code));
        
        $bidders_table = AIH_Database::get_table('bidders');
        $bidder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $bidders_table WHERE confirmation_code = %s",
            $code
        ));
        
        if ($bidder) {
            return $bidder;
        }
        
        return $this->get_registrant_by_confirmation_code($code);
    }
    
    /**
     * Get bidder by email
     */
    public function get_bidder_by_email($email) {
        global $wpdb;
        $table = AIH_Database::get_table('bidders');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE email_primary = %s",
            trim($email)
        ));
    }
    
    /**
     * Get all bidders (only those who have logged in)
     */
    public function get_all_bidders() {
        global $wpdb;
        $table = AIH_Database::get_table('bidders');
        return $wpdb->get_results("SELECT * FROM $table ORDER BY name_last, name_first ASC");
    }
    
    /**
     * Get bidder count
     */
    public function get_bidder_count() {
        global $wpdb;
        $table = AIH_Database::get_table('bidders');
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    // =========================================================================
    // AUTHENTICATION
    // =========================================================================
    
    /**
     * Verify confirmation code and return bidder info
     */
    public function verify_confirmation_code($code) {
        $code = trim(strtoupper($code));
        
        if (empty($code)) {
            return array(
                'success' => false,
                'message' => __('Please enter your confirmation code.', 'art-in-heaven'),
            );
        }
        
        $registrant = $this->get_registrant_by_confirmation_code($code);
        
        if (!$registrant) {
            return array(
                'success' => false,
                'message' => __('Invalid confirmation code. Please check your code or contact support.', 'art-in-heaven'),
            );
        }
        
        return array(
            'success' => true,
            'bidder' => array(
                'confirmation_code' => $registrant->confirmation_code,
                'email' => $registrant->email_primary,
                'first_name' => $registrant->name_first,
                'last_name' => $registrant->name_last,
                'phone' => $registrant->phone_mobile,
            ),
            'registrant' => $registrant,
        );
    }
    
    /**
     * Login a bidder using confirmation code
     * - Copies from Registrants to Bidders table if first login
     * - Updates has_logged_in flag in Registrants
     * 
     * @param string $confirmation_code The bidder's confirmation code
     */
    public function login_bidder($confirmation_code) {
        global $wpdb;
        
        $confirmation_code = trim(strtoupper($confirmation_code));
        
        // Get the registrant
        $registrants_table = AIH_Database::get_table('registrants');
        $registrant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $registrants_table WHERE confirmation_code = %s",
            $confirmation_code
        ));
        
        if ($registrant) {
            // Copy to bidders table (creates or updates)
            $this->copy_to_bidders($registrant);
            
            // Mark as logged in
            $wpdb->update(
                $registrants_table,
                array('has_logged_in' => 1, 'updated_at' => current_time('mysql')),
                array('id' => $registrant->id)
            );
        }
        
        // Store confirmation_code in session (NOT email)
        $this->write_session(array(
            'confirmation_code' => $confirmation_code,
            'logged_in_at' => time(),
        ));
        
        return true;
    }
    
    /**
     * Logout current bidder
     */
    public function logout_bidder() {
        $this->clear_session();
        return true;
    }
    
    /**
     * Check if a bidder is logged in
     */
    public function is_logged_in() {
        $data = $this->read_session();
        return !empty($data['confirmation_code']);
    }
    
    /**
     * Get current bidder's ID (confirmation_code)
     */
    public function get_current_bidder_id() {
        $data = $this->read_session();
        return !empty($data['confirmation_code']) ? $data['confirmation_code'] : null;
    }
    
    /**
     * Get current bidder's full record
     */
    public function get_current_bidder() {
        $confirmation_code = $this->get_current_bidder_id();
        return $confirmation_code ? $this->get_bidder_by_confirmation_code($confirmation_code) : null;
    }
    
    // =========================================================================
    // AUTO SYNC SUPPORT
    // =========================================================================

    /**
     * Schedule auto sync cron job
     *
     * @param string|null $interval Optional interval override. If null, reads from options.
     */
    public static function schedule_auto_sync($interval = null) {
        // Clear any existing schedule first
        wp_clear_scheduled_hook('aih_auto_sync_registrants');

        // Determine interval from parameter or option
        if ($interval === null) {
            $interval = get_option('aih_auto_sync_interval', 'hourly');
        }

        // Validate interval
        $valid_intervals = array('hourly', 'every_thirty_seconds');
        if (!in_array($interval, $valid_intervals)) {
            $interval = 'hourly';
        }

        wp_schedule_event(time(), $interval, 'aih_auto_sync_registrants');
    }

    /**
     * Unschedule auto sync cron job
     */
    public static function unschedule_auto_sync() {
        wp_clear_scheduled_hook('aih_auto_sync_registrants');
    }

    /**
     * Reschedule auto sync with new interval
     *
     * @param string $interval The new interval ('hourly' or 'every_thirty_seconds')
     */
    public static function reschedule_auto_sync($interval) {
        if (get_option('aih_auto_sync_enabled', false)) {
            self::schedule_auto_sync($interval);
        }
    }
    
    /**
     * Run auto sync (called by cron)
     */
    public static function run_auto_sync() {
        $instance = self::get_instance();
        $result = $instance->sync_bidders_from_api();
        
        // Log result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Art in Heaven auto sync: ' . ($result['success'] ? 'Success' : 'Failed') . ' - ' . $result['message']);
        }
        
        return $result;
    }
}

// Register cron action
add_action('aih_auto_sync_registrants', array('AIH_Auth', 'run_auto_sync'));
