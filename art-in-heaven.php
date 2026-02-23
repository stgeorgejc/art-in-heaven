<?php
/**
 * Plugin Name: Art in Heaven
 * Plugin URI: https://example.com/art-in-heaven
 * Description: A comprehensive silent auction system for art pieces with bid management, favorites, and admin controls
 * Version: 0.9.7
 * Author: Art in Heaven Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: art-in-heaven
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * 
 * @package ArtInHeaven
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIH_VERSION', '0.9.7');
define('AIH_DB_VERSION', '0.9.6');
define('AIH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('AIH_CACHE_GROUP', 'art_in_heaven');
define('AIH_CACHE_EXPIRY', HOUR_IN_SECONDS);

/**
 * Main Art in Heaven Class
 * 
 * @since 1.0.0
 */
class Art_In_Heaven {
    
    /** @var Art_In_Heaven|null */
    private static $instance = null;
    
    const MIN_PHP_VERSION = '7.4';
    const MIN_WP_VERSION = '5.8';
    
    /**
     * Get single instance
     * @return Art_In_Heaven
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        if (!$this->check_requirements()) {
            return;
        }
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Check minimum requirements
     */
    private function check_requirements() {
        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        return true;
    }
    
    public function php_version_notice() {
        $message = sprintf(
            esc_html__('Art in Heaven requires PHP %2$s or higher. You have %1$s.', 'art-in-heaven'),
            PHP_VERSION, self::MIN_PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Core
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-database.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-cache.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-security.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-template-helper.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-assets.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-roles.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-status.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-ccb-api.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-auth.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-art-piece.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-art-images.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-bid.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-favorites.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-watermark.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-checkout.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-pushpay.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-ajax.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-shortcodes.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-cron-scheduler.php';

        // Composer autoloader (web-push library)
        require_once AIH_PLUGIN_DIR . 'vendor/autoload.php';
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-push.php';

        // Defer loading of classes only needed in specific contexts
        // REST API class loaded on rest_api_init (see init_rest_api())
        // Export class loaded on demand (referenced by class name in callbacks)
        require_once AIH_PLUGIN_DIR . 'includes/class-aih-export.php';

        if (is_admin()) {
            require_once AIH_PLUGIN_DIR . 'admin/class-aih-admin.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'maybe_update_db'), 0);
        add_action('init', array('AIH_Auth', 'maybe_encrypt_api_data'), 1);
        add_action('init', array($this, 'init'), 0);
        add_action('rest_api_init', array($this, 'init_rest_api'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_filter('body_class', array($this, 'add_body_class'));
        add_action('wp_head', array($this, 'add_preconnect_hints'), 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Privacy/GDPR
        add_action('admin_init', array($this, 'add_privacy_policy_content'));
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_data_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_data_eraser'));
        
        // Custom cron schedule
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        
        // Cron
        add_action('aih_hourly_cleanup', array($this, 'run_hourly_cleanup'));
        add_action('aih_check_expired_auctions', array($this, 'check_expired_auctions'));
        add_action('aih_auto_sync_pushpay', array('AIH_Pushpay_API', 'run_auto_sync'));
        
        // Cache invalidation
        add_action('aih_art_created', array($this, 'invalidate_art_cache'));
        add_action('aih_art_updated', array($this, 'invalidate_art_cache'));
        add_action('aih_bid_placed', array($this, 'invalidate_bid_cache'));

        // Outbid alerts: record event synchronously (fast), defer push sending (slow)
        add_action('aih_bid_placed', array(AIH_Push::get_instance(), 'handle_outbid_event'), 10, 4);

        // Serve service worker from site root scope
        add_action('template_redirect', array($this, 'serve_service_worker'));

        // Disable intermediate image sizes for AIH uploads
        add_filter('intermediate_image_sizes_advanced', array($this, 'disable_intermediate_sizes'), 10, 2);
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        // Add 5-minute schedule for auction status checks
        $schedules['every_five_minutes'] = array(
            'interval' => 300, // 5 minutes in seconds
            'display'  => __('Every 5 Minutes', 'art-in-heaven')
        );
        // Add 30-second schedule for frequent CCB sync during live events
        $schedules['every_thirty_seconds'] = array(
            'interval' => 30, // 30 seconds
            'display'  => __('Every 30 Seconds', 'art-in-heaven')
        );
        return $schedules;
    }
    
    /**
     * Auto-run database migrations when plugin version changes
     */
    public function maybe_update_db() {
        $installed_version = get_option('aih_db_version', '0');
        if (version_compare($installed_version, AIH_DB_VERSION, '<')) {
            AIH_Database::activate();
        }
    }

    /**
     * Disable WordPress intermediate image sizes for AIH uploads
     * This prevents WordPress from creating 6+ copies of every uploaded image
     */
    public function disable_intermediate_sizes($sizes, $metadata) {
        // Check if we're in an AIH upload context
        if (get_transient('aih_uploading_image')) {
            // Disable extra sizes - only keep original + watermarked
            if (get_option('aih_disable_image_sizes', 1)) {
                return array(); // Return empty - no intermediate sizes
            }
        }
        return $sizes;
    }
    
    /**
     * Set flag before AIH image upload
     */
    public static function before_aih_upload() {
        set_transient('aih_uploading_image', true, 120); // 2 minute timeout
    }
    
    /**
     * Clear flag after AIH image upload  
     */
    public static function after_aih_upload() {
        delete_transient('aih_uploading_image');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        if (!current_user_can('activate_plugins')) return;

        // Suppress PHP warnings/deprecations during activation to prevent
        // WordPress from reporting "unexpected output" (especially with Xdebug
        // installed, which generates verbose HTML for each PHP notice/deprecation)
        $prev_error_reporting = error_reporting(0);
        $prev_display_errors  = @ini_get('display_errors');
        @ini_set('display_errors', '0');

        AIH_Database::activate();
        AIH_Roles::install();
        
        // Schedule cron
        if (!wp_next_scheduled('aih_hourly_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'aih_hourly_cleanup');
        }

        // Schedule auction status check every 5 minutes for timely draft->active transitions
        // Clear any existing hourly schedule and reschedule at 5-minute interval
        wp_clear_scheduled_hook('aih_check_expired_auctions');
        if (!wp_next_scheduled('aih_check_expired_auctions')) {
            wp_schedule_event(time(), 'every_five_minutes', 'aih_check_expired_auctions');
        }
        
        // Clean up deprecated five-minute check (removed in v0.9.89)
        wp_clear_scheduled_hook('aih_five_minute_check');
        
        // Schedule auto-sync for registrants (if enabled)
        if (get_option('aih_auto_sync_enabled', false)) {
            AIH_Auth::schedule_auto_sync();
        }

        // Schedule auto-sync for Pushpay transactions (if enabled)
        if (get_option('aih_pushpay_auto_sync_enabled', false)) {
            AIH_Pushpay_API::schedule_auto_sync();
        }

        // Schedule precise cron events for all existing art pieces
        if (class_exists('AIH_Cron_Scheduler')) {
            AIH_Cron_Scheduler::schedule_all_existing_pieces();
        }

        $this->create_upload_directories();
        $this->set_default_options();
        flush_rewrite_rules();

        if (class_exists('AIH_Cache')) AIH_Cache::flush_all();

        // Restore previous error reporting settings
        error_reporting($prev_error_reporting);
        @ini_set('display_errors', $prev_display_errors);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('aih_hourly_cleanup');
        wp_clear_scheduled_hook('aih_check_expired_auctions');
        wp_clear_scheduled_hook('aih_five_minute_check');
        AIH_Auth::unschedule_auto_sync();
        AIH_Pushpay_API::unschedule_auto_sync();

        // Clear all scheduled piece-specific cron events
        if (class_exists('AIH_Cron_Scheduler')) {
            AIH_Cron_Scheduler::clear_all_scheduled_events();
        }

        flush_rewrite_rules();
        if (class_exists('AIH_Cache')) AIH_Cache::flush_all();
        AIH_Database::deactivate();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('art-in-heaven', false, dirname(AIH_PLUGIN_BASENAME) . '/languages');
        
        // One-time cleanup of deprecated cron (v0.9.89)
        if (wp_next_scheduled('aih_five_minute_check')) {
            wp_clear_scheduled_hook('aih_five_minute_check');
        }
        
        AIH_Roles::get_instance();
        AIH_Auth::get_instance();
        AIH_Ajax::get_instance();
        AIH_Shortcodes::get_instance();
        AIH_Checkout::get_instance();
        AIH_Assets::get_instance();
        if (class_exists('AIH_Cron_Scheduler')) AIH_Cron_Scheduler::get_instance();
        
        if (is_admin()) {
            AIH_Admin::get_instance();
        }
        
        // Auto-manage auction statuses based on start/end times
        $this->throttled_expired_check();
    }
    
    public function init_rest_api() {
        // Load REST API class only when REST requests are made
        $rest_file = AIH_PLUGIN_DIR . 'includes/class-aih-rest-api.php';
        if (file_exists($rest_file)) {
            require_once $rest_file;
        }
        if (class_exists('AIH_REST_API')) {
            $rest_api = new AIH_REST_API();
            $rest_api->register_routes();
        }
    }

    /**
     * Serve the push notification service worker from site root scope.
     * Intercepted via /?aih-sw=1 so the SW gets "/" scope.
     */
    public function serve_service_worker() {
        if (!isset($_GET['aih-sw']) || $_GET['aih-sw'] !== '1') {
            return;
        }

        $sw_file = AIH_PLUGIN_DIR . 'assets/js/aih-sw.js';
        if (!file_exists($sw_file)) {
            return;
        }

        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
        readfile($sw_file);
        exit;
    }

    private function throttled_expired_check() {
        // Run status check every 30 seconds max
        $last_check = get_transient('aih_last_expired_check');
        if ($last_check === false) {
            $this->check_expired_auctions();
            set_transient('aih_last_expired_check', time(), 30); // 30 seconds
        }
    }
    
    public function check_expired_auctions() {
        // Wrap in try-catch to prevent cron failures
        try {
            global $wpdb;

            // Single table existence check (removed redundant SHOW TABLES queries)
            if (!class_exists('AIH_Database') || !AIH_Database::tables_exist()) {
                return;
            }

            $table = AIH_Database::get_table('art_pieces');
            if (!$table) return;

            $now = current_time('mysql');
            
            // Activate draft auctions whose start time has passed
            $activated = $wpdb->query($wpdb->prepare(
                "UPDATE $table
                 SET status = 'active'
                 WHERE status = 'draft'
                 AND auction_start IS NOT NULL
                 AND auction_start <= %s
                 AND (auction_end IS NULL OR auction_end > %s)",
                $now,
                $now
            ));

            // Mark active auctions as ended if their end time has passed
            $ended = $wpdb->query($wpdb->prepare(
                "UPDATE $table
                 SET status = 'ended'
                 WHERE status = 'active'
                 AND auction_end IS NOT NULL
                 AND auction_end <= %s",
                $now
            ));

            // Clear targeted caches if any records were updated
            if (($activated > 0 || $ended > 0) && class_exists('AIH_Cache')) {
                $this->invalidate_art_cache();
                if ($ended > 0) {
                    $this->invalidate_bid_cache();
                }
            }
        } catch (Exception $e) {
            // Log error but don't let cron fail
            error_log('AIH check_expired_auctions error: ' . $e->getMessage());
        }
    }
    
    public function run_hourly_cleanup() {
        try {
            if (class_exists('AIH_Cache')) AIH_Cache::cleanup_expired();
            $this->check_expired_auctions();
        } catch (Exception $e) {
            error_log('AIH run_hourly_cleanup error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if the current page contains an AIH shortcode.
     */
    private function is_aih_page() {
        global $post;
        if (!$post || empty($post->post_content)) return false;

        return has_shortcode($post->post_content, 'art_in_heaven_gallery')
            || has_shortcode($post->post_content, 'art_in_heaven_item')
            || has_shortcode($post->post_content, 'art_in_heaven_my_bids')
            || has_shortcode($post->post_content, 'art_in_heaven_checkout')
            || has_shortcode($post->post_content, 'art_in_heaven_login')
            || has_shortcode($post->post_content, 'art_in_heaven_winners')
            || has_shortcode($post->post_content, 'art_in_heaven_my_wins');
    }

    /**
     * Add body class on plugin pages for CSS targeting
     */
    public function add_body_class($classes) {
        if ($this->is_aih_page()) {
            $classes[] = 'aih-active';
        }

        return $classes;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (!$this->is_aih_page()) return;

        // Google Fonts
        wp_enqueue_style(
            'aih-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap',
            array(),
            null
        );

        // Elegant theme CSS (loaded in <head> to prevent FOUC)
        wp_enqueue_style('aih-elegant-theme', AIH_PLUGIN_URL . 'assets/css/elegant-theme.css', array('aih-google-fonts'), AIH_VERSION);

        wp_enqueue_script('aih-frontend', AIH_PLUGIN_URL . 'assets/js/aih-frontend.js', array('jquery'), AIH_VERSION, true);
        wp_enqueue_script('aih-push', AIH_PLUGIN_URL . 'assets/js/aih-push.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);

        // Add custom color CSS
        $custom_css = $this->get_custom_color_css();
        if ($custom_css) {
            wp_add_inline_style('aih-elegant-theme', $custom_css);
        }
        
        $auth = AIH_Auth::get_instance();
        $vapid = AIH_Push::get_vapid_keys();
        wp_localize_script('aih-frontend', 'aihAjax', array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'resturl'        => rest_url('art-in-heaven/v1/'),
            'nonce'          => wp_create_nonce('aih_nonce'),
            'restNonce'      => wp_create_nonce('wp_rest'),
            'isLoggedIn'     => $auth->is_logged_in(),
            'bidderId'       => $auth->get_current_bidder_id(),
            'vapidPublicKey' => $vapid['publicKey'],
            'swUrl'          => home_url('/?aih-sw=1'),
            'checkoutUrl'    => AIH_Template_Helper::get_checkout_url(),
            'bidIncrement'   => floatval(get_option('aih_bid_increment', 1)),
            'strings'        => array(
                'bidTooLow'      => __('Your Bid is too Low.', 'art-in-heaven'),
                'bidSuccess'     => __('Bid placed successfully!', 'art-in-heaven'),
                'bidError'       => __('Error placing bid.', 'art-in-heaven'),
                'favoriteAdded'  => __('Added to favorites!', 'art-in-heaven'),
                'favoriteRemoved'=> __('Removed from favorites.', 'art-in-heaven'),
                'auctionEnded'   => __('This auction has ended.', 'art-in-heaven'),
                'loginRequired'  => __('Please log in.', 'art-in-heaven'),
                'invalidCode'    => __('Invalid code.', 'art-in-heaven'),
                'loginSuccess'   => __('Login successful!', 'art-in-heaven'),
                'checkoutSuccess'=> __('Order created!', 'art-in-heaven'),
                'loading'        => __('Loading...', 'art-in-heaven'),
                'error'          => __('An error occurred.', 'art-in-heaven'),
                'enterCode'      => __('Please enter your code', 'art-in-heaven'),
                'enterValidBid'  => __('Please enter a valid bid amount', 'art-in-heaven'),
                'bidPlaced'      => __('Your bid has been placed!', 'art-in-heaven'),
                'connectionError'=> __('Connection error. Please try again.', 'art-in-heaven'),
                'networkError'   => __('Network error. Please try again.', 'art-in-heaven'),
            )
        ));

        // Conditionally enqueue page-specific JS
        global $post;
        if ($post) {
            $content = $post->post_content;
            if (has_shortcode($content, 'art_in_heaven_gallery')) {
                // Gallery shortcode also serves single-item (?art_id=) and my-bids (?my_bids=1)
                if (isset($_GET['art_id']) && !empty($_GET['art_id'])) {
                    wp_enqueue_script('aih-single-item', AIH_PLUGIN_URL . 'assets/js/aih-single-item.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);
                } elseif (isset($_GET['my_bids']) && $_GET['my_bids'] == '1') {
                    wp_enqueue_script('aih-my-bids', AIH_PLUGIN_URL . 'assets/js/aih-my-bids.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);
                } else {
                    wp_enqueue_script('aih-gallery', AIH_PLUGIN_URL . 'assets/js/aih-gallery.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);
                }
            }
            if (has_shortcode($content, 'art_in_heaven_item')) {
                wp_enqueue_script('aih-single-item', AIH_PLUGIN_URL . 'assets/js/aih-single-item.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);
            }
            if (has_shortcode($content, 'art_in_heaven_my_bids')) {
                wp_enqueue_script('aih-my-bids', AIH_PLUGIN_URL . 'assets/js/aih-my-bids.js', array('jquery', 'aih-frontend'), AIH_VERSION, true);
            }
        }
    }
    
    /**
     * Add preconnect hints for Google Fonts
     */
    public function add_preconnect_hints() {
        if (!$this->is_aih_page()) return;
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    /**
     * Generate custom color CSS from settings
     */
    public function get_custom_color_css() {
        $color_defaults = array(
            'primary' => '#b8956b', 'secondary' => '#1c1c1c', 'success' => '#4a7c59',
            'error' => '#a63d40', 'text' => '#1c1c1c', 'muted' => '#8a8a8a',
        );
        $colors = array();
        foreach ($color_defaults as $key => $default) {
            $val = get_option('aih_color_' . $key, $default);
            // Validate hex color format
            $colors[$key] = preg_match('/^#[0-9A-Fa-f]{6}$/', $val) ? $val : $default;
        }
        $primary = $colors['primary'];
        $secondary = $colors['secondary'];
        $success = $colors['success'];
        $error = $colors['error'];
        $text = $colors['text'];
        $muted = $colors['muted'];

        // Only generate if colors differ from defaults
        $defaults = array('#b8956b', '#1c1c1c', '#4a7c59', '#a63d40', '#1c1c1c', '#8a8a8a');
        $current = array($primary, $secondary, $success, $error, $text, $muted);
        
        if ($defaults === $current) {
            return '';
        }
        
        // Generate CSS with color overrides
        $css = ":root {
            --aih-primary: {$primary};
            --aih-secondary: {$secondary};
            --aih-success: {$success};
            --aih-error: {$error};
            --aih-text: {$text};
            --aih-muted: {$muted};
        }
        /* Primary color overrides */
        .aih-gallery-wrap .aih-art-id,
        .aih-winner-art-id,
        .aih-back-link,
        .aih-back-btn { color: {$primary}; }
        
        .aih-gallery-wrap .aih-title:hover,
        .aih-gallery-wrap .aih-artist:hover { color: {$primary}; }
        
        .aih-gallery-wrap .aih-art-id,
        .aih-winner-art-id { background: " . $this->hex_to_rgba($primary, 0.1) . "; }
        
        .aih-gallery-wrap .aih-bid-btn,
        .aih-bid-btn,
        .aih-btn-primary,
        .aih-btn-sm { background: {$primary}; color: {$secondary}; }
        
        .aih-gallery-wrap .aih-bid-btn:hover,
        .aih-bid-btn:hover { background: " . $this->adjust_brightness($primary, 15) . "; }
        
        /* Secondary color overrides */
        .aih-gallery-wrap .aih-title,
        .aih-winner-title,
        .aih-winners-header h1 { color: {$secondary}; }
        
        /* Success color overrides */
        .aih-gallery-wrap .aih-winning,
        .aih-winning-ribbon { background: {$success}; }
        
        .aih-gallery-wrap .aih-bid-msg.ok { background: " . $this->hex_to_rgba($success, 0.15) . "; color: {$success}; }
        
        /* Error color overrides */
        .aih-gallery-wrap .aih-fav-btn:hover,
        .aih-gallery-wrap .aih-fav-btn.active,
        .aih-single-image .aih-fav-btn:hover,
        .aih-single-image .aih-fav-btn.active { color: {$error}; }
        
        .aih-gallery-wrap .aih-time.urgent { background: {$error}; }
        
        .aih-gallery-wrap .aih-bid-msg.err { background: " . $this->hex_to_rgba($error, 0.15) . "; color: {$error}; }
        
        /* Text color overrides */
        .aih-gallery-wrap,
        .aih-winners-wrap { color: {$text}; }
        
        /* Muted color overrides */
        .aih-gallery-wrap .aih-artist,
        .aih-winner-artist,
        .aih-winners-subtitle,
        .aih-price-label,
        .aih-winner-label { color: {$muted}; }
        ";
        
        return $css;
    }
    
    /**
     * Convert hex color to rgba
     */
    private function hex_to_rgba($hex, $alpha = 1) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
    
    /**
     * Adjust brightness of hex color
     */
    private function adjust_brightness($hex, $percent) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = min(255, max(0, $r + ($r * $percent / 100)));
        $g = min(255, max(0, $g + ($g * $percent / 100)));
        $b = min(255, max(0, $b + ($b * $percent / 100)));
        
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'art-in-heaven') === false) return;
        
        // Add viewport meta for proper mobile scaling
        add_action('admin_head', function() {
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">';
        });
        
        wp_enqueue_media();
        $css_ver = AIH_VERSION . '.' . filemtime(AIH_PLUGIN_DIR . 'assets/css/aih-admin.css');
        wp_enqueue_style('aih-admin', AIH_PLUGIN_URL . 'assets/css/aih-admin.css', array(), $css_ver);
        wp_enqueue_script('aih-admin', AIH_PLUGIN_URL . 'assets/js/aih-admin.js', array('jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable'), AIH_VERSION, true);
        
        wp_localize_script('aih-admin', 'aihAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('aih_admin_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure?', 'art-in-heaven'),
                'confirmDeleteOrder' => __('Delete this order?', 'art-in-heaven'),
                'saveSuccess' => __('Saved!', 'art-in-heaven'),
                'saveError' => __('Error saving.', 'art-in-heaven'),
                'selectImage' => __('Select Image', 'art-in-heaven'),
                'useImage' => __('Use this image', 'art-in-heaven'),
            )
        ));
    }
    
    private function create_upload_directories() {
        $upload_dir = wp_upload_dir();
        $base = $upload_dir['basedir'] . '/art-in-heaven';
        $dirs = array($base, $base . '/watermarked', $base . '/exports', $base . '/temp');
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    private function set_default_options() {
        $defaults = array(
            'aih_auction_year' => wp_date('Y'),
            'aih_tax_rate' => 0,
            'aih_currency' => 'USD',
            'aih_enable_favorites' => 1,
            'aih_watermark_enabled' => 1,
            'aih_watermark_text' => 'Art in Heaven',
            'aih_min_bid_increment' => 5,
            'aih_disable_image_sizes' => 1, // Disable WordPress thumbnail generation by default
        );
        foreach ($defaults as $k => $v) {
            if (get_option($k) === false) add_option($k, $v);
        }
    }
    
    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) return;
        $content = '<h2>' . __('Art in Heaven Auction Plugin', 'art-in-heaven') . '</h2>';
        $content .= '<p>' . __('This plugin collects bidder information for auction participation.', 'art-in-heaven') . '</p>';
        wp_add_privacy_policy_content('Art in Heaven', wp_kses_post($content));
    }
    
    public function register_data_exporter($exporters) {
        $exporters['art-in-heaven'] = array(
            'exporter_friendly_name' => __('Art in Heaven Data', 'art-in-heaven'),
            'callback' => array('AIH_Export', 'export_personal_data'),
        );
        return $exporters;
    }
    
    public function register_data_eraser($erasers) {
        $erasers['art-in-heaven'] = array(
            'eraser_friendly_name' => __('Art in Heaven Data', 'art-in-heaven'),
            'callback' => array('AIH_Export', 'erase_personal_data'),
        );
        return $erasers;
    }
    
    public function invalidate_art_cache($art_id = null) {
        if (class_exists('AIH_Cache')) {
            AIH_Cache::delete_group('art_pieces');
            if ($art_id) AIH_Cache::delete('art_piece_' . $art_id);
        }
    }
    
    public function invalidate_bid_cache($bid_id = null) {
        if (class_exists('AIH_Cache')) {
            AIH_Cache::delete_group('bids');
            // Also clear art piece counts since they include bid-related stats
            AIH_Cache::delete('art_piece_counts');
        }
    }
}

function art_in_heaven() {
    return Art_In_Heaven::get_instance();
}
art_in_heaven();
