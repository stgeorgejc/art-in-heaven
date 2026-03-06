<?php
/**
 * Admin Panel Handler
 *
 * Registers the WordPress admin menu structure, settings pages, and
 * render callbacks for all Art in Heaven back-end screens. Each render
 * function performs a secondary capability check for defense-in-depth.
 *
 * @package ArtInHeaven
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Admin {
    
    /** @var self|null */
    private static $instance = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('in_admin_header', array($this, 'render_skip_link'));
    }

    /**
     * Render a skip-to-content link for keyboard navigation on AIH pages.
     *
     * @return void
     */
    public function render_skip_link(): void {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'art-in-heaven') !== false) {
            echo '<a class="aih-skip-link screen-reader-text" href="#wpbody-content">' . esc_html__('Skip to main content', 'art-in-heaven') . '</a>';
        }
    }
    
    /**
     * @return void
     */
    public function add_admin_menus() {
        // Main menu - accessible to anyone with any AIH capability
        $menu_cap = AIH_Roles::get_menu_capability();

        // Pickup-only users land directly on the pickup page
        $default_slug = 'art-in-heaven';
        $default_render = array($this, 'render_dashboard');
        if (AIH_Roles::can_manage_pickup() && !AIH_Roles::can_manage_art() && !AIH_Roles::can_manage_auction()) {
            $default_slug = 'art-in-heaven-pickup';
            $default_render = array($this, 'render_pickup');
            $menu_cap = AIH_Roles::CAP_MANAGE_PICKUP;
        }

        add_menu_page(
            __('Art in Heaven', 'art-in-heaven'),
            __('Art in Heaven', 'art-in-heaven'),
            $menu_cap,
            $default_slug,
            $default_render,
            AIH_PLUGIN_URL . 'assets/images/icon-20.png',
            30
        );

        // Dashboard - art managers see limited version, super admins see full stats
        if (AIH_Roles::can_manage_art() || AIH_Roles::can_manage_auction()) {
            add_submenu_page(
                $default_slug,
                __('Dashboard', 'art-in-heaven'),
                __('Dashboard', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_ART,
                'art-in-heaven',
                array($this, 'render_dashboard')
            );
        }

        // Add New Art - accessible to art managers
        add_submenu_page(
            $default_slug,
            __('Add New Art', 'art-in-heaven'),
            __('Add New', 'art-in-heaven'),
            AIH_Roles::CAP_MANAGE_ART,
            'art-in-heaven-add',
            array($this, 'render_add_art')
        );

        // Art Pieces - accessible to art managers
        add_submenu_page(
            $default_slug,
            __('Art Pieces', 'art-in-heaven'),
            __('Art Pieces', 'art-in-heaven'),
            AIH_Roles::CAP_MANAGE_ART,
            'art-in-heaven-art',
            array($this, 'render_art_pieces')
        );

        // Registrants - requires bidder management access (super admin only)
        if (AIH_Roles::can_manage_bidders()) {
            add_submenu_page(
                $default_slug,
                __('Registrants', 'art-in-heaven'),
                __('Registrants', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_BIDDERS,
                'art-in-heaven-bidders',
                array($this, 'render_bidders')
            );
        }

        // Bids - requires view bids access
        if (AIH_Roles::can_view_bids()) {
            add_submenu_page(
                $default_slug,
                __('Bids', 'art-in-heaven'),
                __('Bids', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_BIDS,
                'art-in-heaven-bids',
                array($this, 'render_bids')
            );
        }

        // Engagement Stats - requires reports access (super admin only)
        if (AIH_Roles::can_view_reports()) {
            add_submenu_page(
                $default_slug,
                __('Engagement Stats', 'art-in-heaven'),
                __('Engagement Stats', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-stats',
                array($this, 'render_stats')
            );
        }

        // Winners, Orders - requires financial access (super admin only)
        if (AIH_Roles::can_view_financial()) {
            add_submenu_page(
                $default_slug,
                __('Winners & Sales', 'art-in-heaven'),
                __('Winners', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-winners',
                array($this, 'render_winners')
            );

            add_submenu_page(
                $default_slug,
                __('Orders & Payments', 'art-in-heaven'),
                __('Orders & Payments', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-orders',
                array($this, 'render_orders')
            );
        }

        // Pickup - requires pickup or financial access
        if (AIH_Roles::can_manage_pickup()) {
            add_submenu_page(
                $default_slug,
                __('Pickup', 'art-in-heaven'),
                __('Pickup', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_PICKUP,
                'art-in-heaven-pickup',
                array($this, 'render_pickup')
            );
        }

        // Transactions & Integrations - requires settings access (super admin only)
        if (AIH_Roles::can_manage_settings()) {
            add_submenu_page(
                $default_slug,
                __('Transactions', 'art-in-heaven'),
                __('Transactions', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-transactions',
                array($this, 'render_transactions')
            );

            add_submenu_page(
                $default_slug,
                __('Integrations', 'art-in-heaven'),
                __('Integrations', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-integrations',
                array($this, 'render_integrations')
            );
        }

        // Reports - requires reports access (super admin only)
        if (AIH_Roles::can_view_reports()) {
            add_submenu_page(
                $default_slug,
                __('Reports', 'art-in-heaven'),
                __('Reports', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-reports',
                array($this, 'render_reports')
            );
        }

        // Migration - requires full auction management (super admin only)
        if (AIH_Roles::can_manage_auction()) {
            add_submenu_page(
                $default_slug,
                __('Migration', 'art-in-heaven'),
                __('Migration', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_AUCTION,
                'art-in-heaven-migration',
                array($this, 'render_migration')
            );
        }

        // Settings & Log Viewer - requires settings access (super admin only)
        if (AIH_Roles::can_manage_settings()) {
            add_submenu_page(
                $default_slug,
                __('Settings', 'art-in-heaven'),
                __('Settings', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-settings',
                array($this, 'render_settings')
            );

            add_submenu_page(
                $default_slug,
                __('Log Viewer', 'art-in-heaven'),
                __('Log Viewer', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-logs',
                array($this, 'render_logs')
            );
        }
    }
    
    /**
     * @return void
     */
    public function admin_init() {
        // Handle dashboard setup form before any output
        if (isset($_POST['aih_action']) && $_POST['aih_action'] === 'create_tables'
            && isset($_POST['aih_create_tables_nonce']) && wp_verify_nonce($_POST['aih_create_tables_nonce'], 'aih_create_tables')) {
            AIH_Database::create_tables();
            if (AIH_Database::tables_exist()) {
                wp_safe_redirect(admin_url('admin.php?page=art-in-heaven&tables_created=1'));
            } else {
                // Tables failed to create - pass error info
                global $wpdb;
                error_log('AIH: create_tables failed. Last DB error: ' . $wpdb->last_error);
                wp_safe_redirect(admin_url('admin.php?page=art-in-heaven&tables_error=1'));
            }
            exit;
        }

        // General settings
        register_setting('aih_settings', 'aih_currency_symbol', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_settings', 'aih_bid_increment', array('sanitize_callback' => 'floatval'));
        register_setting('aih_settings', 'aih_watermark_text', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_settings', 'aih_watermark_text_enabled', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_settings', 'aih_watermark_crosshatch', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_settings', 'aih_tax_rate', array('sanitize_callback' => 'floatval'));
        register_setting('aih_settings', 'aih_auction_year', array('sanitize_callback' => 'intval'));
        register_setting('aih_settings', 'aih_event_date', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_settings', 'aih_event_end_date', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_settings', 'aih_login_page', array('sanitize_callback' => 'absint'));
        register_setting('aih_settings', 'aih_gallery_page', array('sanitize_callback' => 'absint'));
        register_setting('aih_settings', 'aih_show_sold_items', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_settings', 'aih_disable_image_sizes', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_settings', 'aih_watermark_overlay_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));
        
        // Color/Theme settings
        register_setting('aih_settings', 'aih_color_primary', array(
            'type' => 'string',
            'default' => '#b8956b',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('aih_settings', 'aih_color_secondary', array(
            'type' => 'string',
            'default' => '#1c1c1c',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('aih_settings', 'aih_color_success', array(
            'type' => 'string',
            'default' => '#4a7c59',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('aih_settings', 'aih_color_error', array(
            'type' => 'string',
            'default' => '#a63d40',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('aih_settings', 'aih_color_text', array(
            'type' => 'string',
            'default' => '#1c1c1c',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('aih_settings', 'aih_color_muted', array(
            'type' => 'string',
            'default' => '#8a8a8a',
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        
        // API settings - now in aih_integrations group
        register_setting('aih_integrations', 'aih_api_base_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('aih_integrations', 'aih_api_form_id', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_api_username', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_api_username')));
        register_setting('aih_integrations', 'aih_api_password', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_api_password')));
        register_setting('aih_integrations', 'aih_auto_sync_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                $enabled = $value ? 1 : 0;
                // Schedule or unschedule based on setting
                if ($enabled) {
                    AIH_Auth::schedule_auto_sync();
                } else {
                    AIH_Auth::unschedule_auto_sync();
                }
                return $enabled;
            }
        ));
        register_setting('aih_integrations', 'aih_auto_sync_interval', array(
            'type' => 'string',
            'default' => 'hourly',
            'sanitize_callback' => function($value) {
                $valid = array('hourly', 'every_thirty_seconds');
                $interval = in_array($value, $valid) ? $value : 'hourly';
                // Reschedule if auto-sync is enabled
                AIH_Auth::reschedule_auto_sync($interval);
                return $interval;
            }
        ));
        
        // Pushpay settings - Production (in aih_integrations group)
        register_setting('aih_integrations', 'aih_pushpay_merchant_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_merchant_handle', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_fund', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_redirect_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_client_id', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_pushpay_client_id')));
        register_setting('aih_integrations', 'aih_pushpay_client_secret', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_pushpay_client_secret')));
        register_setting('aih_integrations', 'aih_pushpay_organization_key', array('sanitize_callback' => 'sanitize_text_field'));

        // Pushpay settings - Sandbox (in aih_integrations group)
        register_setting('aih_integrations', 'aih_pushpay_sandbox_client_id', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_pushpay_sandbox_client_id')));
        register_setting('aih_integrations', 'aih_pushpay_sandbox_client_secret', array('sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_pushpay_sandbox_client_secret')));
        register_setting('aih_integrations', 'aih_pushpay_sandbox_organization_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_sandbox_merchant_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('aih_integrations', 'aih_pushpay_sandbox_merchant_handle', array('sanitize_callback' => 'sanitize_text_field'));
        
        // Pushpay auto-sync settings
        register_setting('aih_integrations', 'aih_pushpay_auto_sync_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                $enabled = $value ? 1 : 0;
                // Schedule or unschedule based on setting
                if ($enabled) {
                    $interval = isset($_POST['aih_pushpay_auto_sync_interval']) ? sanitize_text_field($_POST['aih_pushpay_auto_sync_interval']) : get_option('aih_pushpay_auto_sync_interval', 'hourly');
                    AIH_Pushpay_API::schedule_auto_sync($interval);
                } else {
                    AIH_Pushpay_API::unschedule_auto_sync();
                }
                return $enabled;
            }
        ));
        register_setting('aih_integrations', 'aih_pushpay_auto_sync_interval', array(
            'type' => 'string',
            'default' => 'hourly',
            'sanitize_callback' => function($value) {
                $valid = array('hourly', 'every_thirty_seconds');
                $interval = in_array($value, $valid) ? $value : 'hourly';
                AIH_Pushpay_API::reschedule_auto_sync($interval);
                return $interval;
            }
        ));

        // Pushpay environment toggle (in aih_integrations group)
        register_setting('aih_integrations', 'aih_pushpay_sandbox', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                // Clear cached token when switching environments
                delete_transient('aih_pushpay_token');
                return $value ? 1 : 0;
            }
        ));

        // Mercure SSE settings (in aih_integrations group)
        register_setting('aih_integrations', 'aih_mercure_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_integrations', 'aih_push_enabled', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => function($value) {
                return $value ? 1 : 0;
            }
        ));
        register_setting('aih_integrations', 'aih_mercure_hub_url', array(
            'type' => 'string',
            'default' => 'http://127.0.0.1:3000/.well-known/mercure',
            'sanitize_callback' => 'esc_url_raw'
        ));
        register_setting('aih_integrations', 'aih_mercure_public_hub_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));
        register_setting('aih_integrations', 'aih_mercure_jwt_secret', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => AIH_Security::make_sanitize_encrypt('aih_mercure_jwt_secret')
        ));
    }
    
    /**
     * Render the main dashboard page.
     *
     * @return void
     */
    public function render_dashboard() {
        if (!AIH_Roles::can_manage_art()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        // Check if database tables exist
        if (!AIH_Database::tables_exist()) {
            include AIH_PLUGIN_DIR . 'admin/views/dashboard-setup.php';
            return;
        }
        
        $art_model = new AIH_Art_Piece();
        $all_art = $art_model->get_all_with_stats();
        /** @var stdClass|null $counts */
        $counts = $art_model->get_counts();
        $checkout = AIH_Checkout::get_instance();
        /** @var stdClass|null $payment_stats */
        $payment_stats = $checkout->get_payment_stats();

        // Ensure counts has all required properties
        if (!$counts) {
            $counts = new stdClass();
        }
        $counts->total = isset($counts->total) ? $counts->total : 0;
        $counts->active = isset($counts->active) ? $counts->active : 0;
        $counts->active_with_bids = isset($counts->active_with_bids) ? $counts->active_with_bids : 0;
        $counts->active_no_bids = isset($counts->active_no_bids) ? $counts->active_no_bids : 0;
        $counts->ended = isset($counts->ended) ? $counts->ended : 0;
        $counts->draft = isset($counts->draft) ? $counts->draft : 0;
        $counts->upcoming = isset($counts->upcoming) ? $counts->upcoming : 0;
        
        // Ensure payment_stats has all required properties
        if (!$payment_stats) {
            $payment_stats = new stdClass();
        }
        $payment_stats->total_orders = isset($payment_stats->total_orders) ? $payment_stats->total_orders : 0;
        $payment_stats->paid_orders = isset($payment_stats->paid_orders) ? $payment_stats->paid_orders : 0;
        $payment_stats->pending_orders = isset($payment_stats->pending_orders) ? $payment_stats->pending_orders : 0;
        $payment_stats->total_collected = isset($payment_stats->total_collected) ? $payment_stats->total_collected : 0;
        $payment_stats->total_pending = isset($payment_stats->total_pending) ? $payment_stats->total_pending : 0;

        $total_bids = 0;
        if ($all_art) {
            foreach ($all_art as $piece) {
                $total_bids += isset($piece->total_bids) ? $piece->total_bids : 0;
            }
        }
        
        include AIH_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render the art pieces list or individual art stats page.
     *
     * @return void
     */
    public function render_art_pieces() {
        if (!AIH_Roles::can_manage_art()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        // Check if viewing individual art stats
        if (isset($_GET['stats']) && $_GET['stats']) {
            include AIH_PLUGIN_DIR . 'admin/views/art-stats.php';
            return;
        }
        include AIH_PLUGIN_DIR . 'admin/views/art-pieces.php';
    }
    
    /**
     * Render the add/edit art piece form.
     *
     * @return void
     */
    public function render_add_art() {
        if (!AIH_Roles::can_manage_art()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        $art_piece = null;
        $page_title = __('Add New Art Piece', 'art-in-heaven');
        
        if (isset($_GET['edit']) && $_GET['edit']) {
            $art_model = new AIH_Art_Piece();
            $art_piece = $art_model->get(intval($_GET['edit']));
            if ($art_piece) $page_title = __('Edit Art Piece', 'art-in-heaven');
        }
        
        include AIH_PLUGIN_DIR . 'admin/views/add-art.php';
    }
    
    /**
     * @return void
     */
    public function render_bids() {
        if (!AIH_Roles::can_view_bids()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/bids.php';
    }
    
    /**
     * @return void
     */
    public function render_orders() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        // Handle manual payment status updates (Mark Payment on won items without orders)
        $payment_message = null;
        $payment_message_type = null;
        if (isset($_POST['aih_update_payment'], $_POST['aih_payment_nonce']) && wp_verify_nonce(wp_unslash($_POST['aih_payment_nonce']), 'aih_update_payment')) {
            $result = AIH_Checkout::get_instance()->mark_manual_payment(
                intval($_POST['art_piece_id']),
                sanitize_text_field(wp_unslash($_POST['payment_status'])),
                sanitize_text_field(wp_unslash($_POST['payment_method'])),
                sanitize_text_field(wp_unslash($_POST['payment_reference'])),
                sanitize_textarea_field(wp_unslash($_POST['payment_notes']))
            );
            $payment_message = $result['message'];
            $payment_message_type = $result['success'] ? 'success' : 'error';
        }

        $checkout = AIH_Checkout::get_instance();
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $single_order = isset($_GET['order_id']) ? $checkout->get_order(intval($_GET['order_id'])) : null;

        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        $orders = $checkout->get_all_orders(array('status' => $status_filter, 'search' => $search, 'limit' => $per_page, 'offset' => $offset));
        $total_orders_filtered = $checkout->count_orders(array('status' => $status_filter, 'search' => $search));
        $total_pages = ceil($total_orders_filtered / $per_page);
        /** @var stdClass|null $payment_stats */
        $payment_stats = $checkout->get_payment_stats();

        // Ensure payment_stats has all required properties
        if (!$payment_stats) {
            $payment_stats = new stdClass();
        }
        $payment_stats->total_orders = isset($payment_stats->total_orders) ? $payment_stats->total_orders : 0;
        $payment_stats->paid_orders = isset($payment_stats->paid_orders) ? $payment_stats->paid_orders : 0;
        $payment_stats->pending_orders = isset($payment_stats->pending_orders) ? $payment_stats->pending_orders : 0;
        $payment_stats->total_collected = isset($payment_stats->total_collected) ? $payment_stats->total_collected : 0;
        $payment_stats->total_pending = isset($payment_stats->total_pending) ? $payment_stats->total_pending : 0;

        // Ensure orders is an array
        if (!$orders) {
            $orders = array();
        }

        // Won items without orders
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        $art_table = AIH_Database::get_table('art_pieces');
        $order_items_table = AIH_Database::get_table('order_items');
        $bidders_table = AIH_Database::get_table('bidders');

        $won_without_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, b.bid_amount as winning_amount, b.bidder_id,
                    COALESCE(bd.name_first, '') as winner_first,
                    COALESCE(bd.name_last, '') as winner_last
             FROM $bids_table b
             JOIN $art_table a ON b.art_piece_id = a.id
             LEFT JOIN $order_items_table oi ON oi.art_piece_id = a.id
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
             WHERE b.is_winning = 1
             AND (a.auction_end < %s OR a.status = 'ended')
             AND oi.id IS NULL
             ORDER BY a.auction_end DESC
             LIMIT 1000",
            current_time('mysql')
        ));
        $payment_stats->items_needing_orders = count($won_without_orders);

        include AIH_PLUGIN_DIR . 'admin/views/orders.php';
    }
    
    /**
     * @return void
     */
    public function render_winners() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/winners.php';
    }

    /**
     * @return void
     */
    public function render_pickup() {
        if (!AIH_Roles::can_manage_pickup()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/pickup.php';
    }

    /**
     * @return void
     */
    public function render_bidders() {
        if (!AIH_Roles::can_manage_bidders()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/bidders.php';
    }

    /**
     * @return void
     */
    public function render_reports() {
        if (!AIH_Roles::can_view_reports()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/reports.php';
    }

    /**
     * @return void
     */
    public function render_stats() {
        if (!AIH_Roles::can_view_reports()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        $art_model = new AIH_Art_Piece();
        $art_pieces = $art_model->get_all_with_stats();
        $engagement_metrics = $this->get_engagement_metrics();
        include AIH_PLUGIN_DIR . 'admin/views/stats.php';
    }

    /**
     * Query engagement metrics from the audit log for the stats dashboard.
     *
     * @return array<string, mixed>
     */
    private function get_engagement_metrics() {
        global $wpdb;
        $audit_table   = AIH_Database::get_table('audit_log');
        $bids_table    = AIH_Database::get_table('bids');

        // Push notification funnel counts
        $push_events = $wpdb->get_results(
            "SELECT event_type, COUNT(*) AS cnt
             FROM `{$audit_table}`
             WHERE event_type IN (
                 'push_permission_granted','push_permission_denied',
                 'push_sent','push_delivered','push_expired','push_clicked'
             )
             GROUP BY event_type",
            OBJECT_K
        );

        $get_count = function ($key) use ($push_events) {
            return isset($push_events[$key]) ? (int) $push_events[$key]->cnt : 0;
        };

        $funnel = array(
            'permission_granted' => $get_count('push_permission_granted'),
            'permission_denied'  => $get_count('push_permission_denied'),
            'push_sent'          => $get_count('push_sent'),
            'push_delivered'     => $get_count('push_delivered'),
            'push_expired'       => $get_count('push_expired'),
            'push_clicked'       => $get_count('push_clicked'),
        );

        // Bid source attribution
        $bid_sources = $wpdb->get_results(
            "SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.bid_source')), 'organic') AS source,
                COUNT(*) AS cnt
             FROM `{$audit_table}`
             WHERE event_type = 'bid_placed'
             GROUP BY source",
            OBJECT_K
        );

        $bid_attribution = array(
            'push'    => isset($bid_sources['push']) ? (int) $bid_sources['push']->cnt : 0,
            'organic' => isset($bid_sources['organic']) ? (int) $bid_sources['organic']->cnt : 0,
        );
        // Capture any other sources
        foreach ($bid_sources as $src => $row) {
            if (!isset($bid_attribution[$src])) {
                $bid_attribution[$src] = (int) $row->cnt;
            }
        }

        // Bidders who granted push vs total bidders
        $push_bidders = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT bidder_id)
             FROM `{$audit_table}`
             WHERE event_type = 'push_permission_granted'"
        );

        $total_bidders_with_bids = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT bidder_id) FROM `{$bids_table}` WHERE bid_status = 'valid'"
        );

        // Bidding breadth: avg distinct art pieces bid on, push vs non-push bidders
        $push_bidder_breadth = $wpdb->get_var(
            "SELECT AVG(piece_count) FROM (
                SELECT b.bidder_id, COUNT(DISTINCT b.art_piece_id) AS piece_count
                FROM `{$bids_table}` b
                INNER JOIN `{$audit_table}` a ON a.bidder_id = b.bidder_id AND a.event_type = 'push_permission_granted'
                WHERE b.bid_status = 'valid'
                GROUP BY b.bidder_id
            ) sub"
        );

        $nonpush_bidder_breadth = $wpdb->get_var(
            "SELECT AVG(piece_count) FROM (
                SELECT b.bidder_id, COUNT(DISTINCT b.art_piece_id) AS piece_count
                FROM `{$bids_table}` b
                WHERE b.bid_status = 'valid'
                  AND b.bidder_id NOT IN (
                      SELECT DISTINCT bidder_id FROM `{$audit_table}` WHERE event_type = 'push_permission_granted'
                  )
                GROUP BY b.bidder_id
            ) sub"
        );

        // Bidding depth: avg bids per bidder, push vs non-push
        $push_bidder_depth = $wpdb->get_var(
            "SELECT AVG(bid_count) FROM (
                SELECT b.bidder_id, COUNT(*) AS bid_count
                FROM `{$bids_table}` b
                INNER JOIN `{$audit_table}` a ON a.bidder_id = b.bidder_id AND a.event_type = 'push_permission_granted'
                WHERE b.bid_status = 'valid'
                GROUP BY b.bidder_id
            ) sub"
        );

        $nonpush_bidder_depth = $wpdb->get_var(
            "SELECT AVG(bid_count) FROM (
                SELECT b.bidder_id, COUNT(*) AS bid_count
                FROM `{$bids_table}` b
                WHERE b.bid_status = 'valid'
                  AND b.bidder_id NOT IN (
                      SELECT DISTINCT bidder_id FROM `{$audit_table}` WHERE event_type = 'push_permission_granted'
                  )
                GROUP BY b.bidder_id
            ) sub"
        );

        // Bidding timeline: bids per hour
        $bids_by_hour = $wpdb->get_results(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS hour_bucket,
                    COUNT(*) AS cnt,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.bid_source')), 'organic') AS source
             FROM `{$audit_table}`
             WHERE event_type = 'bid_placed'
             GROUP BY hour_bucket, source
             ORDER BY hour_bucket"
        );

        // Push permission source breakdown
        $permission_sources = $wpdb->get_results(
            "SELECT
                event_type,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.source')), 'bell') AS source,
                COUNT(*) AS cnt
             FROM `{$audit_table}`
             WHERE event_type IN ('push_permission_granted', 'push_permission_denied')
             GROUP BY event_type, source"
        );

        // Per-bidder engagement summary (top 20 most active)
        $bidder_engagement = $wpdb->get_results(
            "SELECT
                b.bidder_id,
                COUNT(*) AS total_bids,
                COUNT(DISTINCT b.art_piece_id) AS pieces_bid_on,
                MAX(b.bid_time) AS last_bid_time,
                CASE WHEN a.bidder_id IS NOT NULL THEN 1 ELSE 0 END AS has_push
             FROM `{$bids_table}` b
             LEFT JOIN (
                SELECT DISTINCT bidder_id FROM `{$audit_table}` WHERE event_type = 'push_permission_granted'
             ) a ON a.bidder_id = b.bidder_id
             WHERE b.bid_status = 'valid'
             GROUP BY b.bidder_id, has_push
             ORDER BY total_bids DESC
             LIMIT 50"
        );

        // Notification type breakdown (outbid vs winner)
        $notif_types = $wpdb->get_results(
            "SELECT
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.notification_type')), 'unknown') AS notif_type,
                event_type,
                COUNT(*) AS cnt
             FROM `{$audit_table}`
             WHERE event_type IN ('push_sent', 'push_delivered', 'push_clicked')
             GROUP BY notif_type, event_type"
        );

        return array(
            'funnel'                => $funnel,
            'bid_attribution'       => $bid_attribution,
            'push_bidders'          => $push_bidders,
            'total_bidders'         => $total_bidders_with_bids,
            'push_bidder_breadth'   => $push_bidder_breadth ? round((float) $push_bidder_breadth, 1) : 0,
            'nonpush_bidder_breadth'=> $nonpush_bidder_breadth ? round((float) $nonpush_bidder_breadth, 1) : 0,
            'push_bidder_depth'     => $push_bidder_depth ? round((float) $push_bidder_depth, 1) : 0,
            'nonpush_bidder_depth'  => $nonpush_bidder_depth ? round((float) $nonpush_bidder_depth, 1) : 0,
            'bids_by_hour'          => $bids_by_hour,
            'permission_sources'    => $permission_sources,
            'bidder_engagement'     => $bidder_engagement,
            'notif_types'           => $notif_types,
        );
    }

    /**
     * @return void
     */
    public function render_migration() {
        if (!AIH_Roles::can_manage_auction()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/migration.php';
    }

    /**
     * @return void
     */
    public function render_integrations() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/integrations.php';
    }

    /**
     * @return void
     */
    public function render_transactions() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/transactions.php';
    }

    /**
     * @return void
     */
    public function render_settings() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * @return void
     */
    public function render_logs() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Render the opening tag of a stat card grid container.
     *
     * @param string $extra_class Optional additional CSS class(es).
     * @param string $extra_style Optional inline style.
     * @return void
     */
    public static function open_stat_grid($extra_class = '', $extra_style = '') {
        $classes = 'aih-stats-grid';
        if ($extra_class !== '') {
            $classes .= ' ' . $extra_class;
        }
        $style_attr = $extra_style !== '' ? ' style="' . esc_attr($extra_style) . '"' : '';
        echo '<div class="' . esc_attr($classes) . '"' . $style_attr . '>';
    }

    /**
     * Render the closing tag of a stat card grid container.
     *
     * @return void
     */
    public static function close_stat_grid() {
        echo '</div>';
    }

    /**
     * Render a single stat card with unified markup.
     *
     * @param array{
     *     value: string,
     *     label: string,
     *     sublabel?: string,
     *     detail?: string,
     *     icon?: string,
     *     icon_color?: string,
     *     icon_bg?: string,
     *     variant?: string,
     *     layout?: string
     * } $args Card configuration.
     * @return void
     */
    public static function render_stat_card($args) {
        $defaults = array(
            'value'      => '',
            'label'      => '',
            'sublabel'   => '',
            'detail'     => '',
            'icon'       => '',
            'icon_color' => '',
            'icon_bg'    => '',
            'variant'    => '',
            'layout'     => 'vertical',
        );
        $args = wp_parse_args($args, $defaults);

        // Build card CSS classes
        $classes = array('aih-stat-card');

        if ($args['layout'] === 'horizontal') {
            $classes[] = 'aih-stat-card--horizontal';
        }

        // Map variant to CSS class
        $variant_map = array(
            'success'  => 'aih-card-success',
            'warning'  => 'aih-card-warning',
            'danger'   => 'aih-card-danger',
            'info'     => 'aih-card-info',
            'active'   => 'aih-stat-active',
            'bids'     => 'aih-stat-bids',
            'nobids'   => 'aih-stat-nobids',
            'money'    => 'aih-stat-money',
            'highlight' => 'aih-stat-highlight',
            'favorites' => 'aih-stat-favorites',
            'last-bid' => 'aih-last-bid-card',
        );
        if ($args['variant'] !== '' && isset($variant_map[$args['variant']])) {
            $classes[] = $variant_map[$args['variant']];
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';

        // Horizontal layout: icon first, then content wrapper
        if ($args['layout'] === 'horizontal' && $args['icon'] !== '') {
            $icon_style = '';
            $style_parts = array();
            if ($args['icon_bg'] !== '') {
                $style_parts[] = 'background: ' . $args['icon_bg'];
            }
            if ($args['icon_color'] !== '') {
                $style_parts[] = 'color: ' . $args['icon_color'];
            }
            if (!empty($style_parts)) {
                $icon_style = ' style="' . esc_attr(implode('; ', $style_parts)) . ';"';
            }
            echo '<div class="aih-stat-icon"' . $icon_style . '>';
            echo '<span class="dashicons ' . esc_attr($args['icon']) . '"></span>';
            echo '</div>';
            echo '<div class="aih-stat-content">';
        }

        // Value
        echo '<div class="aih-stat-number">' . $args['value'] . '</div>';

        // Label
        $label_class = 'aih-stat-label';
        if ($args['layout'] === 'horizontal') {
            $label_class .= ' aih-stat-label--plain';
        }
        echo '<div class="' . esc_attr($label_class) . '">' . esc_html($args['label']) . '</div>';

        // Sublabel (optional)
        if ($args['sublabel'] !== '') {
            echo '<span class="aih-stat-sublabel">' . esc_html($args['sublabel']) . '</span>';
        }

        // Detail (optional)
        if ($args['detail'] !== '') {
            echo '<span class="aih-stat-detail">' . esc_html($args['detail']) . '</span>';
        }

        // Vertical layout icon (after label)
        if ($args['layout'] !== 'horizontal' && $args['icon'] !== '') {
            $icon_style = '';
            if ($args['icon_color'] !== '') {
                $icon_style = ' style="color: ' . esc_attr($args['icon_color']) . ';"';
            }
            echo '<div class="aih-stat-icon">';
            echo '<span class="dashicons ' . esc_attr($args['icon']) . '"' . $icon_style . ' aria-hidden="true"></span>';
            echo '</div>';
        }

        // Close content wrapper for horizontal layout
        if ($args['layout'] === 'horizontal' && $args['icon'] !== '') {
            echo '</div>'; // .aih-stat-content
        }

        echo '</div>'; // .aih-stat-card
    }
}
