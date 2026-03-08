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

        // Operations / pickup-only users land directly on the pickup page
        $default_slug = 'art-in-heaven';
        $default_render = array($this, 'render_dashboard');
        if (!AIH_Roles::can_manage_art() && !AIH_Roles::can_manage_auction()) {
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

        // Analytics - requires reports access (super admin only)
        if (AIH_Roles::can_view_reports()) {
            add_submenu_page(
                $default_slug,
                __('Analytics', 'art-in-heaven'),
                __('Analytics', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-analytics',
                array($this, 'render_analytics')
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

        // Legacy slug redirects — keeps old bookmarks working.
        if (AIH_Roles::can_view_reports()) {
            add_submenu_page(
                'options.php',
                __('Analytics', 'art-in-heaven'),
                __('Analytics', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-reports',
                array($this, 'render_analytics_redirect')
            );
            add_submenu_page(
                'options.php',
                __('Analytics', 'art-in-heaven'),
                __('Analytics', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-stats',
                array($this, 'render_analytics_redirect')
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

        // QR Code print page (hidden — no menu item, accessed via bulk action)
        add_submenu_page(
            'options.php',
            __('Print QR Codes', 'art-in-heaven'),
            __('Print QR Codes', 'art-in-heaven'),
            AIH_Roles::CAP_MANAGE_ART,
            'art-in-heaven-qr-print',
            array($this, 'render_qr_print')
        );
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
        $status_filter = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';
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
     * Render the consolidated Analytics page (replaces reports + stats).
     *
     * @return void
     */
    /**
     * Get live analytics data for AJAX polling.
     *
     * @param string $tab Active tab (overview, revenue, bidders, notifications).
     * @return array<string, mixed>
     */
    /**
     * Get live analytics data for AJAX polling and page render.
     *
     * @param string $tab The active tab ('overview' or 'revenue').
     * @return array<string, mixed>
     */
    public function get_analytics_live_data($tab = 'overview') {
        global $wpdb;
        $art_model  = new AIH_Art_Piece();
        $art_pieces = $art_model->get_all_with_stats();
        $stats      = $art_model->get_reporting_stats();
        if ( ! $stats ) {
            $stats = new stdClass();
        }

        $checkout      = AIH_Checkout::get_instance();
        $payment_stats = $checkout->get_payment_stats();
        if ( ! $payment_stats ) {
            $payment_stats = new stdClass();
        }

        $bid_model = new AIH_Bid();
        $bid_stats = $bid_model->get_stats();
        if ( ! $bid_stats ) {
            $bid_stats = new stdClass();
        }

        $bids_table = AIH_Database::get_table('bids');
        $art_table  = AIH_Database::get_table('art_pieces');

        // Last bid time.
        /** @var stdClass|null $last_bid_row */
        $last_bid_row  = $wpdb->get_row( "SELECT bid_time FROM $bids_table ORDER BY bid_time DESC LIMIT 1" );
        $last_bid_time = $last_bid_row ? $last_bid_row->bid_time : null;

        // Computed stats.
        $total_pieces      = isset( $stats->total_pieces ) ? (int) $stats->total_pieces : 0;
        $pieces_with_bids  = isset( $stats->pieces_with_bids ) ? (int) $stats->pieces_with_bids : 0;
        $sell_through      = $total_pieces > 0 ? round( ( $pieces_with_bids / $total_pieces ) * 100 ) : 0;
        $total_collected   = isset( $payment_stats->total_collected ) ? floatval( $payment_stats->total_collected ) : 0;
        $total_pending     = isset( $payment_stats->total_pending ) ? floatval( $payment_stats->total_pending ) : 0;
        $total_revenue     = $total_collected + $total_pending;
        $total_starting    = isset( $stats->total_starting_value ) ? floatval( $stats->total_starting_value ) : 0;
        $total_bid_value   = isset( $bid_stats->total_bid_value ) ? floatval( $bid_stats->total_bid_value ) : 0;
        $rev_vs_starting   = $total_starting > 0 ? round( ( $total_bid_value / $total_starting ) * 100 ) : 0;
        $avg_bids          = $pieces_with_bids > 0
            ? number_format( (int) ( $stats->total_bids ?? 0 ) / max( $pieces_with_bids, 1 ), 1 )
            : '0.0';

        // Single-bid pieces.
        $single_bid_count = 0;
        /** @var stdClass $p */
        foreach ( $art_pieces as $p ) {
            if ( $p->total_bids === 1 ) {
                $single_bid_count++;
            }
        }

        // Last bid display.
        $last_bid_display = '&#8212;';
        if ( $last_bid_time ) {
            $bid_dt    = new \DateTime( $last_bid_time, wp_timezone() );
            $now_dt    = new \DateTime( 'now', wp_timezone() );
            $time_diff = $now_dt->getTimestamp() - $bid_dt->getTimestamp();
            if ( $time_diff < 60 ) {
                $last_bid_display = __( 'Just now', 'art-in-heaven' );
            } elseif ( $time_diff < 3600 ) {
                $last_bid_display = sprintf( __( '%d min ago', 'art-in-heaven' ), floor( $time_diff / 60 ) );
            } elseif ( $time_diff < 86400 ) {
                $last_bid_display = sprintf( __( '%d hr ago', 'art-in-heaven' ), floor( $time_diff / 3600 ) );
            } else {
                $last_bid_display = sprintf( __( '%d days ago', 'art-in-heaven' ), floor( $time_diff / 86400 ) );
            }
        }

        // --- Auction Pulse ---
        $audit_table = AIH_Database::get_table( 'audit_log' );
        /** @var stdClass $pulse_row */
        $pulse_row = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) AS bids_5m,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) AS bids_15m,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE) THEN 1 ELSE 0 END) AS bids_60m
             FROM `{$audit_table}`
             WHERE event_type = 'bid_placed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)"
        );
        $bids_5m  = $pulse_row ? (int) $pulse_row->bids_5m : 0;
        $bids_15m = $pulse_row ? (int) $pulse_row->bids_15m : 0;
        $bids_60m = $pulse_row ? (int) $pulse_row->bids_60m : 0;

        if ( $bids_5m >= 3 ) {
            $pulse_status = 'hot';
        } elseif ( $bids_15m >= 3 ) {
            $pulse_status = 'warm';
        } else {
            $pulse_status = 'cooling';
        }

        // --- Urgency Board ---
        /** @var list<array{id: int, title: string, art_id: string, seconds_remaining: int, total_bids: int}> $urgency_items */
        $urgency_items = array();
        /** @var stdClass $p */
        foreach ( $art_pieces as $p ) {
            if ( $p->status === 'active' && $p->seconds_remaining > 0 && $p->seconds_remaining <= 7200 ) {
                $urgency_items[] = array(
                    'id'                => (int) $p->id,
                    'title'             => $p->title,
                    'art_id'            => $p->art_id,
                    'seconds_remaining' => (int) $p->seconds_remaining,
                    'total_bids'        => (int) $p->total_bids,
                );
            }
        }
        usort( $urgency_items, function ( $a, $b ) {
            return $a['seconds_remaining'] - $b['seconds_remaining'];
        } );
        $urgency_items = array_slice( $urgency_items, 0, 10 );

        // --- Needs Attention Alerts ---
        $alerts = array();
        $zero_bid_ending = array_filter( $urgency_items, function ( $item ) {
            return $item['total_bids'] === 0;
        } );
        if ( ! empty( $zero_bid_ending ) ) {
            $alerts[] = array(
                'type'  => 'zero_bids_ending',
                'title' => __( 'Active pieces ending soon with no bids', 'art-in-heaven' ),
                'count' => count( $zero_bid_ending ),
            );
        }

        $orders_table = AIH_Database::get_table( 'orders' );
        $pending_stale = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $orders_table
             WHERE payment_status = 'pending'
               AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        if ( $pending_stale > 0 ) {
            $alerts[] = array(
                'type'  => 'stale_pending',
                'title' => __( 'Pending orders older than 15 minutes', 'art-in-heaven' ),
                'count' => $pending_stale,
            );
        }

        // --- Live Bid Feed ---
        $can_view_financial = AIH_Roles::can_view_financial();
        $amount_select      = $can_view_financial
            ? "JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.amount')) AS amount,"
            : '';
        /** @var list<stdClass> $bid_feed_rows */
        $bid_feed_rows = $wpdb->get_results(
            "SELECT
                al.created_at,
                CONCAT(LEFT(al.bidder_id, 2), '****') AS bidder_masked,
                {$amount_select}
                a.title AS piece_title
             FROM `{$audit_table}` al
             LEFT JOIN $art_table a ON JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.art_piece_id')) = a.id
             WHERE al.event_type = 'bid_placed'
             ORDER BY al.created_at DESC
             LIMIT 20"
        );
        $bid_feed = array();
        $now_ts   = time();
        foreach ( $bid_feed_rows as $row ) {
            $entry_ts = strtotime( $row->created_at );
            $diff     = $now_ts - $entry_ts;
            if ( $diff < 60 ) {
                $time_ago = __( 'Just now', 'art-in-heaven' );
            } elseif ( $diff < 3600 ) {
                $time_ago = sprintf( __( '%d min ago', 'art-in-heaven' ), floor( $diff / 60 ) );
            } else {
                $time_ago = sprintf( __( '%d hr ago', 'art-in-heaven' ), floor( $diff / 3600 ) );
            }
            $entry = array(
                'time_ago'      => $time_ago,
                'bidder_masked' => $row->bidder_masked,
                'piece_title'   => $row->piece_title ?: __( 'Unknown', 'art-in-heaven' ),
            );
            if ( $can_view_financial && isset( $row->amount ) ) {
                $entry['amount'] = $row->amount;
            }
            $bid_feed[] = $entry;
        }

        // --- Repeat Bidder Rate ---
        $engagement_metrics = $this->get_engagement_metrics();
        $bidder_data        = isset( $engagement_metrics['bidder_engagement'] ) ? $engagement_metrics['bidder_engagement'] : array();
        $multi_piece        = 0;
        foreach ( $bidder_data as $b ) {
            if ( isset( $b->pieces_bid_on ) && (int) $b->pieces_bid_on >= 2 ) {
                $multi_piece++;
            }
        }
        $total_bidders       = count( $bidder_data );
        $repeat_bidder_rate  = $total_bidders > 0 ? round( ( $multi_piece / $total_bidders ) * 100 ) : 0;

        // --- Timeline chart data ---
        $bids_by_interval = isset( $engagement_metrics['bids_by_interval'] ) ? $engagement_metrics['bids_by_interval'] : array();
        $interval_data    = array();
        foreach ( $bids_by_interval as $row ) {
            if ( ! isset( $interval_data[ $row->time_bucket ] ) ) {
                $interval_data[ $row->time_bucket ] = array( 'push' => 0, 'organic' => 0 );
            }
            if ( $row->source === 'push' ) {
                $interval_data[ $row->time_bucket ]['push'] = (int) $row->cnt;
            } else {
                $interval_data[ $row->time_bucket ]['organic'] += (int) $row->cnt;
            }
        }
        ksort( $interval_data );
        $timeline_labels  = array();
        $timeline_push    = array();
        $timeline_organic = array();
        foreach ( $interval_data as $bucket => $counts ) {
            $dt                 = \DateTime::createFromFormat( 'Y-m-d H:i', $bucket, wp_timezone() );
            $timeline_labels[]  = $dt ? $dt->format( 'g:i A' ) : $bucket;
            $timeline_push[]    = $counts['push'];
            $timeline_organic[] = $counts['organic'];
        }

        // --- Inventory chart data ---
        $sold_count     = 0;
        $active_bids    = 0;
        $active_no_bids = 0;
        $unsold_count   = 0;
        /** @var stdClass $p */
        foreach ( $art_pieces as $p ) {
            $is_ended = ( $p->seconds_remaining <= 0 && $p->status !== 'draft' );
            $has_bids = ( $p->total_bids > 0 );
            if ( $is_ended && $has_bids ) {
                $sold_count++;
            } elseif ( ! $is_ended && $p->status === 'active' && $has_bids ) {
                $active_bids++;
            } elseif ( ! $is_ended && $p->status === 'active' && ! $has_bids ) {
                $active_no_bids++;
            } elseif ( $is_ended && ! $has_bids ) {
                $unsold_count++;
            }
        }

        // --- Build response ---
        $data = array(
            'overview' => array(
                'sell_through'      => $sell_through,
                'unique_bidders'    => isset( $stats->unique_bidders ) ? (int) $stats->unique_bidders : 0,
                'active_auctions'   => isset( $stats->active_count ) ? (int) $stats->active_count : 0,
                'avg_bids_per_piece' => $avg_bids,
                'rev_vs_starting'   => $rev_vs_starting,
                'single_bid_count'  => $single_bid_count,
                'last_bid_display'  => $last_bid_display,
                'last_bid_time'     => $last_bid_time ? AIH_Status::format_db_date( $last_bid_time, 'M j, g:i a' ) : '',
                'pieces_with_bids'  => $pieces_with_bids,
                'total_pieces'      => $total_pieces,
                'total_bids'        => isset( $stats->total_bids ) ? (int) $stats->total_bids : 0,
                'repeat_bidder_rate' => $repeat_bidder_rate,
                'repeat_bidders'    => $multi_piece,
                'total_bidders'     => $total_bidders,
            ),
            'pulse'    => array(
                'bids_5m'  => $bids_5m,
                'bids_15m' => $bids_15m,
                'bids_60m' => $bids_60m,
                'status'   => $pulse_status,
            ),
            'urgency'  => $urgency_items,
            'alerts'   => $alerts,
            'bid_feed' => $bid_feed,
            'charts'   => array(
                'timeline'  => array(
                    'labels'  => $timeline_labels,
                    'push'    => $timeline_push,
                    'organic' => $timeline_organic,
                ),
                'inventory' => array(
                    'sold'           => $sold_count,
                    'active_bids'    => $active_bids,
                    'active_no_bids' => $active_no_bids,
                    'unsold'         => $unsold_count,
                ),
            ),
        );

        if ( $can_view_financial ) {
            $data['overview']['total_revenue']    = $total_revenue;
            $data['overview']['total_collected']   = $total_collected;
            $data['overview']['total_pending']     = $total_pending;
            $data['overview']['paid_orders']       = isset( $payment_stats->paid_orders ) ? (int) $payment_stats->paid_orders : 0;
            $data['overview']['pending_orders']    = isset( $payment_stats->pending_orders ) ? (int) $payment_stats->pending_orders : 0;
        }

        return $data;
    }

    /**
     * Render the analytics dashboard page.
     *
     * @return void
     */
    public function render_analytics() {
        if (!AIH_Roles::can_view_reports()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }

        // Check if tables exist.
        if (!AIH_Database::tables_exist()) {
            echo '<div class="wrap"><div class="notice notice-warning"><p>' . esc_html__('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
            return;
        }

        $art_model = new AIH_Art_Piece();
        $art_pieces = $art_model->get_all_with_stats();
        $engagement_metrics = $this->get_engagement_metrics();

        $stats = $art_model->get_reporting_stats();
        if (!$stats) {
            $stats = new stdClass();
        }

        $checkout = AIH_Checkout::get_instance();
        $payment_stats = $checkout->get_payment_stats();
        if (!$payment_stats) {
            $payment_stats = new stdClass();
        }

        $bid_model = new AIH_Bid();
        $bid_stats = $bid_model->get_stats();
        if (!$bid_stats) {
            $bid_stats = new stdClass();
        }

        // Last bid time.
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        /** @var stdClass|null $last_bid_row */
        $last_bid_row = $wpdb->get_row("SELECT bid_time FROM $bids_table ORDER BY bid_time DESC LIMIT 1");
        $last_bid_time = $last_bid_row ? $last_bid_row->bid_time : null;

        // Registrant funnel counts for the Bidders tab.
        $registrants_table = AIH_Database::get_table('registrants');
        $registrant_counts = new stdClass();
        $registrant_counts->total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $registrants_table");
        $registrant_counts->logged_in = (int) $wpdb->get_var("SELECT COUNT(*) FROM $registrants_table WHERE has_logged_in = 1");
        $registrant_counts->has_bids = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $registrants_table r WHERE has_logged_in = 1 AND EXISTS (SELECT 1 FROM $bids_table b WHERE b.bidder_id = r.confirmation_code)"
        );

        // Bid distribution by amount ranges.
        $bid_distribution = $wpdb->get_results(
            "SELECT
                CASE
                    WHEN bid_amount < 50 THEN 'Under $50'
                    WHEN bid_amount < 100 THEN '$50–$99'
                    WHEN bid_amount < 250 THEN '$100–$249'
                    WHEN bid_amount < 500 THEN '$250–$499'
                    WHEN bid_amount < 1000 THEN '$500–$999'
                    ELSE '$1,000+'
                END AS bracket,
                COUNT(*) AS cnt
            FROM $bids_table
            GROUP BY bracket
            ORDER BY MIN(bid_amount) ASC"
        );

        // Top 10 pieces by current bid value (revenue proxy).
        $art_table = AIH_Database::get_table('art_pieces');
        $top_by_revenue = $wpdb->get_results(
            "SELECT a.title, a.art_id,
                    MAX(b.bid_amount) AS highest_bid,
                    COUNT(b.id) AS bid_count
             FROM $bids_table b
             INNER JOIN $art_table a ON b.art_piece_id = a.id
             GROUP BY a.id, a.title, a.art_id
             ORDER BY highest_bid DESC
             LIMIT 10"
        );

        // Revenue tab data (only queried for financial users).
        $revenue_by_method  = array();
        $revenue_by_piece   = array();
        $collection_rate    = new stdClass();
        $avg_order_value    = 0.0;
        $projected_revenue  = 0.0;

        if ( AIH_Roles::can_view_financial() ) {
            $orders_table      = AIH_Database::get_table('orders');
            $order_items_table = AIH_Database::get_table('order_items');

            // Revenue by payment method.
            $revenue_by_method = $wpdb->get_results(
                "SELECT payment_method,
                        COUNT(*) AS order_count,
                        SUM(total) AS method_total
                 FROM $orders_table
                 WHERE payment_status = 'paid'
                 GROUP BY payment_method
                 ORDER BY method_total DESC"
            );

            // Revenue by art piece (actual order data, not bid proxy).
            $revenue_by_piece = $wpdb->get_results(
                "SELECT a.title, a.art_id, a.starting_bid,
                        oi.winning_bid AS sold_price,
                        o.payment_status
                 FROM $order_items_table oi
                 JOIN $orders_table o ON oi.order_id = o.id
                 JOIN $art_table a ON oi.art_piece_id = a.id
                 ORDER BY oi.winning_bid DESC"
            );

            // Collection rate: paid vs total won items.
            $collection_rate = $wpdb->get_row(
                "SELECT
                    COUNT(*) AS total_items,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_items,
                    SUM(CASE WHEN o.payment_status = 'pending' THEN 1 ELSE 0 END) AS pending_items
                 FROM $order_items_table oi
                 JOIN $orders_table o ON oi.order_id = o.id"
            );
            if ( ! $collection_rate ) {
                $collection_rate = new stdClass();
            }
            $collection_rate->total_items   = isset( $collection_rate->total_items ) ? (int) $collection_rate->total_items : 0;
            $collection_rate->paid_items    = isset( $collection_rate->paid_items ) ? (int) $collection_rate->paid_items : 0;
            $collection_rate->pending_items = isset( $collection_rate->pending_items ) ? (int) $collection_rate->pending_items : 0;

            // Average order value (paid orders only).
            $avg_order_value = (float) $wpdb->get_var(
                "SELECT AVG(total) FROM $orders_table WHERE payment_status = 'paid' AND total > 0"
            );

            // Projected revenue: sum of highest bids on active items that haven't ended yet.
            $projected_revenue = (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(max_bid), 0)
                 FROM (
                     SELECT MAX(b.bid_amount) AS max_bid
                     FROM $bids_table b
                     JOIN $art_table a ON b.art_piece_id = a.id
                     WHERE a.status = 'active'
                       AND a.auction_end > NOW()
                       AND (b.bid_status = 'valid' OR b.bid_status IS NULL)
                     GROUP BY b.art_piece_id
                 ) active_bids"
            );
        }

        // Live dashboard data (pulse, urgency, alerts, bid feed, repeat rate).
        $live_data = $this->get_analytics_live_data('overview');

        include AIH_PLUGIN_DIR . 'admin/views/analytics.php';
    }

    /**
     * Redirect legacy reports/stats slugs to the new Analytics page.
     *
     * @return void
     */
    public function render_analytics_redirect() {
        wp_safe_redirect(admin_url('admin.php?page=art-in-heaven-analytics'));
        exit;
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

        // Bidding timeline: bids per 5-minute interval
        $bids_by_interval = $wpdb->get_results(
            "SELECT DATE_FORMAT(
                        DATE_SUB(created_at, INTERVAL MOD(MINUTE(created_at), 5) MINUTE),
                        '%Y-%m-%d %H:%i'
                    ) AS time_bucket,
                    COUNT(*) AS cnt,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(details, '$.bid_source')), 'organic') AS source
             FROM `{$audit_table}`
             WHERE event_type = 'bid_placed'
             GROUP BY time_bucket, source
             ORDER BY time_bucket"
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
            'bids_by_interval'      => $bids_by_interval,
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
     * Render the QR code print page (opened in new tab from bulk action).
     *
     * @return void
     */
    public function render_qr_print(): void {
        if (!AIH_Roles::can_manage_art()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/qr-print.php';
    }

    /**
     * Render the opening tag of a stat card grid container.
     *
     * @param string $extra_class Optional additional CSS class(es).
     * @param string $extra_style Optional inline style (kept for backward compatibility).
     * @return void
     */
    public static function open_stat_grid($extra_class = '', $extra_style = '') {
        $classes = 'aih-stats-grid';
        if ($extra_class !== '') {
            $classes .= ' ' . $extra_class;
        }
        // Backward compat: $extra_style is caller-supplied inline CSS, sanitized via esc_attr().
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
     *     value_html?: string,
     *     label: string,
     *     sublabel?: string,
     *     detail?: string,
     *     icon?: string,
     *     icon_color?: string,
     *     icon_bg?: string,
     *     variant?: string,
     *     layout?: string,
     *     link?: string,
     *     stat_key?: string
     * } $args Card configuration.
     * @return void
     */
    public static function render_stat_card($args) {
        $defaults = array(
            'value'      => '',
            'value_html' => '',
            'label'      => '',
            'sublabel'   => '',
            'detail'     => '',
            'icon'       => '',
            'icon_color' => '',
            'icon_bg'    => '',
            'variant'    => '',
            'layout'     => 'vertical',
            'link'       => '',
            'stat_key'   => '',
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

        $has_link = (is_string($args['link']) && $args['link'] !== '');
        if ($has_link) {
            $classes[] = 'aih-stat-card--linked';
            echo '<a href="' . esc_url($args['link']) . '" class="aih-stat-card-link">';
        }

        $stat_attr = $args['stat_key'] !== '' ? ' data-stat="' . esc_attr($args['stat_key']) . '"' : '';
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '"' . $stat_attr . '>';

        // Horizontal layout: icon first, then content wrapper
        if ($args['layout'] === 'horizontal' && $args['icon'] !== '') {
            $icon_style = '';
            $style_parts = array();
            $sanitized_bg = sanitize_hex_color($args['icon_bg']);
            if ($sanitized_bg !== null && $sanitized_bg !== '') {
                $style_parts[] = 'background: ' . $sanitized_bg;
            }
            $sanitized_color = sanitize_hex_color($args['icon_color']);
            if ($sanitized_color !== null && $sanitized_color !== '') {
                $style_parts[] = 'color: ' . $sanitized_color;
            }
            if (!empty($style_parts)) {
                $icon_style = ' style="' . esc_attr(implode('; ', $style_parts)) . ';"';
            }
            echo '<div class="aih-stat-icon"' . $icon_style . '>';
            echo '<span class="dashicons ' . esc_attr($args['icon']) . '"></span>';
            echo '</div>';
            echo '<div class="aih-stat-content">';
        }

        // Value: use value_html (sanitized HTML) if provided, otherwise escape plain text.
        if ($args['value_html'] !== '') {
            echo '<div class="aih-stat-number">' . wp_kses_post($args['value_html']) . '</div>';
        } else {
            echo '<div class="aih-stat-number">' . esc_html($args['value']) . '</div>';
        }

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
            $sanitized_icon_color = sanitize_hex_color($args['icon_color']);
            if ($sanitized_icon_color !== null && $sanitized_icon_color !== '') {
                $icon_style = ' style="color: ' . esc_attr($sanitized_icon_color) . ';"';
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

        if ($has_link) {
            echo '</a>';
        }
    }
}
