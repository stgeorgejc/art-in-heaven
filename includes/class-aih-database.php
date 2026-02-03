<?php
/**
 * Database handler for Art in Heaven
 * 
 * Uses year-based table names (e.g., {year}_Bids, {year}_Bidders, {year}_ArtPieces)
 * Includes optimized indexes and prepared statements.
 * 
 * @package ArtInHeaven
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Database {
    
    /**
     * Get current auction year
     * 
     * @return string
     */
    public static function get_auction_year() {
        return get_option('aih_auction_year', date('Y'));
    }
    
    /**
     * Plugin activation - create database tables
     */
    public static function activate() {
        self::create_tables();
    }
    
    /**
     * Create all tables for current year
     * 
     * @param int|null $year Optional year override
     */
    public static function create_tables($year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = self::get_auction_year();
        }
        
        // Validate year
        $year = absint($year);
        if ($year < 2020 || $year > 2100) {
            $year = date('Y');
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Art pieces table - {Year}_ArtPieces
        // NOTE: current_bid removed - calculated from bids table instead
        $art_table = $wpdb->prefix . $year . '_ArtPieces';
        $sql_art = "CREATE TABLE $art_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            art_id varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            artist varchar(255) NOT NULL,
            medium varchar(255) NOT NULL,
            dimensions varchar(100) DEFAULT '',
            description text,
            starting_bid decimal(10,2) NOT NULL DEFAULT 0.00,
            tier varchar(50) DEFAULT '',
            image_id bigint(20) unsigned DEFAULT NULL,
            image_url varchar(500) DEFAULT '',
            watermarked_url varchar(500) DEFAULT '',
            auction_start datetime DEFAULT NULL,
            auction_end datetime DEFAULT NULL,
            show_end_time tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY art_id (art_id),
            KEY status (status),
            KEY auction_end (auction_end),
            KEY auction_start (auction_start),
            KEY status_auction_end (status, auction_end),
            KEY tier (tier),
            KEY artist (artist(50)),
            KEY medium (medium(50)),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Bids table - {Year}_Bids
        $bids_table = $wpdb->prefix . $year . '_Bids';
        $sql_bids = "CREATE TABLE $bids_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            art_piece_id bigint(20) unsigned NOT NULL,
            bidder_id varchar(255) NOT NULL,
            bid_amount decimal(10,2) NOT NULL,
            bid_time datetime DEFAULT CURRENT_TIMESTAMP,
            is_winning tinyint(1) DEFAULT 0,
            bid_status varchar(20) DEFAULT 'valid',
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY (id),
            KEY art_piece_id (art_piece_id),
            KEY bidder_id (bidder_id),
            KEY bid_time (bid_time),
            KEY is_winning (is_winning),
            KEY bid_status (bid_status),
            KEY art_winning (art_piece_id, is_winning),
            KEY bidder_art (bidder_id, art_piece_id)
        ) $charset_collate;";
        
        // Favorites table - {Year}_Favorites
        $favorites_table = $wpdb->prefix . $year . '_Favorites';
        $sql_favorites = "CREATE TABLE $favorites_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bidder_id varchar(255) NOT NULL,
            art_piece_id bigint(20) unsigned NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY bidder_art (bidder_id, art_piece_id),
            KEY bidder_id (bidder_id),
            KEY art_piece_id (art_piece_id)
        ) $charset_collate;";
        
        // Bidders table - {Year}_Bidders
        $bidders_table = $wpdb->prefix . $year . '_Bidders';
        $sql_bidders = "CREATE TABLE $bidders_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            confirmation_code varchar(100) NOT NULL DEFAULT '',
            email_primary varchar(255) DEFAULT '',
            name_first varchar(100) DEFAULT '',
            name_last varchar(100) DEFAULT '',
            phone_mobile varchar(50) DEFAULT '',
            phone_home varchar(50) DEFAULT '',
            phone_work varchar(50) DEFAULT '',
            birthday varchar(20) DEFAULT '',
            gender varchar(10) DEFAULT '',
            marital_status varchar(10) DEFAULT '',
            mailing_street varchar(255) DEFAULT '',
            mailing_city varchar(100) DEFAULT '',
            mailing_state varchar(50) DEFAULT '',
            mailing_zip varchar(20) DEFAULT '',
            individual_id varchar(50) DEFAULT '',
            individual_name varchar(255) DEFAULT '',
            api_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY confirmation_code (confirmation_code),
            KEY email_primary (email_primary),
            KEY name_last (name_last(50)),
            KEY last_login (last_login)
        ) $charset_collate;";
        
        // Orders table - {Year}_Orders
        $orders_table = $wpdb->prefix . $year . '_Orders';
        $sql_orders = "CREATE TABLE $orders_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_number varchar(50) NOT NULL,
            bidder_id varchar(255) NOT NULL,
            subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
            tax decimal(10,2) NOT NULL DEFAULT 0.00,
            total decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_status varchar(20) DEFAULT 'pending',
            payment_method varchar(50) DEFAULT 'pushpay',
            payment_reference varchar(255) DEFAULT '',
            pushpay_payment_id varchar(255) DEFAULT '',
            pushpay_status varchar(50) DEFAULT '',
            payment_date datetime DEFAULT NULL,
            pickup_status varchar(20) DEFAULT 'pending',
            pickup_date datetime DEFAULT NULL,
            pickup_by varchar(255) DEFAULT '',
            pickup_notes text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_number (order_number),
            KEY bidder_id (bidder_id),
            KEY payment_status (payment_status),
            KEY pickup_status (pickup_status),
            KEY pushpay_payment_id (pushpay_payment_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Order items table - {Year}_OrderItems
        $order_items_table = $wpdb->prefix . $year . '_OrderItems';
        $sql_order_items = "CREATE TABLE $order_items_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            art_piece_id bigint(20) unsigned NOT NULL,
            winning_bid decimal(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY art_piece_id (art_piece_id)
        ) $charset_collate;";
        
        // Registrants table - {Year}_Registrants
        $registrants_table = $wpdb->prefix . $year . '_Registrants';
        $sql_registrants = "CREATE TABLE $registrants_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            confirmation_code varchar(100) NOT NULL DEFAULT '',
            email_primary varchar(255) DEFAULT '',
            name_first varchar(100) DEFAULT '',
            name_last varchar(100) DEFAULT '',
            phone_mobile varchar(50) DEFAULT '',
            phone_home varchar(50) DEFAULT '',
            phone_work varchar(50) DEFAULT '',
            birthday varchar(20) DEFAULT '',
            gender varchar(10) DEFAULT '',
            marital_status varchar(10) DEFAULT '',
            mailing_street varchar(255) DEFAULT '',
            mailing_city varchar(100) DEFAULT '',
            mailing_state varchar(50) DEFAULT '',
            mailing_zip varchar(20) DEFAULT '',
            individual_id varchar(50) DEFAULT '',
            individual_name varchar(255) DEFAULT '',
            api_data text,
            has_logged_in tinyint(1) DEFAULT 0,
            has_bid tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY confirmation_code (confirmation_code),
            KEY email_primary (email_primary),
            KEY has_logged_in (has_logged_in),
            KEY has_bid (has_bid),
            KEY login_bid_status (has_logged_in, has_bid)
        ) $charset_collate;";
        
        // Audit log table - {Year}_AuditLog
        $audit_table = $wpdb->prefix . $year . '_AuditLog';
        $sql_audit = "CREATE TABLE $audit_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            object_type varchar(50) DEFAULT '',
            object_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            bidder_id varchar(255) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY object_type (object_type),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Art Images table - {Year}_ArtImages (multiple images per art piece)
        $art_images_table = $wpdb->prefix . $year . '_ArtImages';
        $sql_art_images = "CREATE TABLE $art_images_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            art_piece_id bigint(20) unsigned NOT NULL,
            image_id bigint(20) unsigned DEFAULT NULL,
            image_url varchar(500) NOT NULL,
            watermarked_url varchar(500) DEFAULT '',
            sort_order int(11) DEFAULT 0,
            is_primary tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY art_piece_id (art_piece_id),
            KEY is_primary (is_primary),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Pushpay Transactions table - {Year}_PushpayTransactions
        $pushpay_table = $wpdb->prefix . $year . '_PushpayTransactions';
        $sql_pushpay = "CREATE TABLE $pushpay_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pushpay_id varchar(255) NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) DEFAULT 'USD',
            status varchar(50) DEFAULT '',
            payer_name varchar(255) DEFAULT '',
            payer_email varchar(255) DEFAULT '',
            fund varchar(255) DEFAULT '',
            reference varchar(255) DEFAULT '',
            notes text,
            payment_date datetime DEFAULT NULL,
            raw_data longtext,
            synced_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY pushpay_id (pushpay_id),
            KEY order_id (order_id),
            KEY status (status),
            KEY payer_email (payer_email),
            KEY payment_date (payment_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_art);
        dbDelta($sql_bids);
        dbDelta($sql_favorites);
        dbDelta($sql_bidders);
        dbDelta($sql_orders);
        dbDelta($sql_order_items);
        dbDelta($sql_registrants);
        dbDelta($sql_audit);
        dbDelta($sql_art_images);
        dbDelta($sql_pushpay);
        
        // Migrate: Add bid_status column if it doesn't exist
        self::migrate_bids_table($year);
        
        // Migrate: Add show_end_time column if it doesn't exist
        self::migrate_art_pieces_table($year);
        
        // Clean up old columns from bidders table if they exist
        self::cleanup_bidders_table($year);
        
        // Store database version
        update_option('aih_db_version', AIH_VERSION);
        update_option('aih_auction_year', $year);
        
        // Create upload directory
        $upload_dir = wp_upload_dir();
        $aih_upload_dir = $upload_dir['basedir'] . '/art-in-heaven';
        if (!file_exists($aih_upload_dir)) {
            wp_mkdir_p($aih_upload_dir);
            wp_mkdir_p($aih_upload_dir . '/watermarked');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Migrate bids table - add bid_status column if not exists
     * 
     * @param int|null $year
     */
    public static function migrate_bids_table($year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = self::get_auction_year();
        }
        
        $table = $wpdb->prefix . absint($year) . '_Bids';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return;
        }
        
        // Check if bid_status column exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'bid_status'",
            DB_NAME, $table
        ));
        
        if (!$column_exists) {
            // Add the bid_status column
            $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD COLUMN `bid_status` varchar(20) DEFAULT 'valid' AFTER `is_winning`");
            
            // Add index
            $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD INDEX `bid_status` (`bid_status`)");
        }
    }
    
    /**
     * Migrate art pieces table - add show_end_time column if not exists
     * 
     * @param int|null $year
     */
    public static function migrate_art_pieces_table($year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = self::get_auction_year();
        }
        
        $table = $wpdb->prefix . absint($year) . '_ArtPieces';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return;
        }
        
        // Check if show_end_time column exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'show_end_time'",
            DB_NAME, $table
        ));
        
        if (!$column_exists) {
            // Add the show_end_time column (default 0 = hidden)
            $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD COLUMN `show_end_time` tinyint(1) DEFAULT 0 AFTER `auction_end`");
        }
    }
    
    /**
     * Clean up old columns from bidders table
     * 
     * @param int|null $year
     */
    public static function cleanup_bidders_table($year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = self::get_auction_year();
        }
        
        $table = $wpdb->prefix . absint($year) . '_Bidders';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        if (!$table_exists) {
            return;
        }
        
        // Check which columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `" . esc_sql($table) . "`");
        $existing_columns = array();
        foreach ($columns as $col) {
            $existing_columns[] = $col->Field;
        }
        
        // Old columns to remove
        $old_columns = array('email', 'first_name', 'last_name', 'phone');
        
        foreach ($old_columns as $old_col) {
            if (in_array($old_col, $existing_columns)) {
                // Before dropping, migrate data if new column is empty
                $new_col = self::get_new_column_name($old_col);
                if ($new_col && in_array($new_col, $existing_columns)) {
                    $wpdb->query(
                        "UPDATE `" . esc_sql($table) . "` 
                         SET `" . esc_sql($new_col) . "` = `" . esc_sql($old_col) . "` 
                         WHERE (`" . esc_sql($new_col) . "` IS NULL OR `" . esc_sql($new_col) . "` = '') 
                         AND `" . esc_sql($old_col) . "` IS NOT NULL 
                         AND `" . esc_sql($old_col) . "` != ''"
                    );
                }
                
                // Now drop the old column
                $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` DROP COLUMN `" . esc_sql($old_col) . "`");
            }
        }
    }
    
    /**
     * Map old column names to new ones
     * 
     * @param string $old_col
     * @return string|null
     */
    private static function get_new_column_name($old_col) {
        $map = array(
            'email' => 'email_primary',
            'first_name' => 'name_first',
            'last_name' => 'name_last',
            'phone' => 'phone_mobile'
        );
        return isset($map[$old_col]) ? $map[$old_col] : null;
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_aih_%' 
             OR option_name LIKE '_transient_timeout_aih_%'"
        );
        
        flush_rewrite_rules();
    }
    
    /**
     * Get table name with prefix and year
     * 
     * @param string $table Table identifier
     * @return string Full table name
     */
    public static function get_table($table) {
        global $wpdb;
        $year = self::get_auction_year();
        
        // Map table names to year-based format
        $table_map = array(
            'art_pieces' => $year . '_ArtPieces',
            'bids' => $year . '_Bids',
            'favorites' => $year . '_Favorites',
            'bidders' => $year . '_Bidders',
            'registrants' => $year . '_Registrants',
            'orders' => $year . '_Orders',
            'order_items' => $year . '_OrderItems',
            'audit_log' => $year . '_AuditLog',
            'art_images' => $year . '_ArtImages',
            'pushpay_transactions' => $year . '_PushpayTransactions'
        );
        
        if (isset($table_map[$table])) {
            return $wpdb->prefix . $table_map[$table];
        }
        
        return $wpdb->prefix . $year . '_' . sanitize_key($table);
    }
    
    /**
     * Check if tables exist for a given year
     * 
     * @param int|null $year
     * @return bool
     */
    public static function tables_exist($year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = self::get_auction_year();
        }
        
        $table = $wpdb->prefix . absint($year) . '_ArtPieces';
        $result = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));
        
        return $result === $table;
    }
    
    /**
     * Log an audit event
     * 
     * @param string $event_type Event type
     * @param array  $data       Event data
     * @return int|false Insert ID or false
     */
    public static function log_audit($event_type, $data = array()) {
        global $wpdb;
        $table = self::get_table('audit_log');
        
        $insert = array(
            'event_type' => sanitize_key($event_type),
            'object_type' => isset($data['object_type']) ? sanitize_key($data['object_type']) : '',
            'object_id' => isset($data['object_id']) ? absint($data['object_id']) : null,
            'user_id' => get_current_user_id() ?: null,
            'bidder_id' => isset($data['bidder_id']) ? sanitize_email($data['bidder_id']) : '',
            'ip_address' => class_exists('AIH_Security') ? AIH_Security::get_client_ip() : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 500) : '',
            'details' => isset($data['details']) ? wp_json_encode($data['details']) : '',
            'created_at' => current_time('mysql'),
        );
        
        $result = $wpdb->insert($table, $insert);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get table statistics
     * 
     * @return array
     */
    public static function get_table_stats() {
        global $wpdb;
        $year = self::get_auction_year();
        
        $tables = array('art_pieces', 'bids', 'favorites', 'bidders', 'registrants', 'orders');
        $stats = array();
        
        foreach ($tables as $table) {
            $full_name = self::get_table($table);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `" . esc_sql($full_name) . "`");
            $stats[$table] = array(
                'table' => $full_name,
                'count' => intval($count),
            );
        }
        
        return $stats;
    }
}
