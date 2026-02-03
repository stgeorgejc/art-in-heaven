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
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function add_admin_menus() {
        // Main menu - accessible to anyone with art management capability
        $menu_cap = AIH_Roles::get_menu_capability();
        
        add_menu_page(
            __('Art in Heaven', 'art-in-heaven'),
            __('Art in Heaven', 'art-in-heaven'),
            $menu_cap,
            'art-in-heaven',
            array($this, 'render_dashboard'),
            AIH_PLUGIN_URL . 'assets/images/icon-20.png',
            30
        );
        
        // Dashboard - art managers see limited version, super admins see full stats
        add_submenu_page(
            'art-in-heaven',
            __('Dashboard', 'art-in-heaven'),
            __('Dashboard', 'art-in-heaven'),
            $menu_cap,
            'art-in-heaven',
            array($this, 'render_dashboard')
        );
        
        // Art Pieces - accessible to art managers
        add_submenu_page(
            'art-in-heaven',
            __('Art Pieces', 'art-in-heaven'),
            __('Art Pieces', 'art-in-heaven'),
            AIH_Roles::CAP_MANAGE_ART,
            'art-in-heaven-art',
            array($this, 'render_art_pieces')
        );
        
        // Add New Art - accessible to art managers
        add_submenu_page(
            'art-in-heaven',
            __('Add New Art', 'art-in-heaven'),
            __('Add New', 'art-in-heaven'),
            AIH_Roles::CAP_MANAGE_ART,
            'art-in-heaven-add',
            array($this, 'render_add_art')
        );
        
        // Bids - requires view bids access
        if (AIH_Roles::can_view_bids()) {
            add_submenu_page(
                'art-in-heaven',
                __('Bids', 'art-in-heaven'),
                __('Bids', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_BIDS,
                'art-in-heaven-bids',
                array($this, 'render_bids')
            );
        }
        
        // Orders - requires financial access (super admin only)
        if (AIH_Roles::can_view_financial()) {
            add_submenu_page(
                'art-in-heaven',
                __('Orders & Payments', 'art-in-heaven'),
                __('Orders', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-orders',
                array($this, 'render_orders')
            );
            
            add_submenu_page(
                'art-in-heaven',
                __('Payments', 'art-in-heaven'),
                __('Payments', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-payments',
                array($this, 'render_payments')
            );
            
            // Winners & Sales - requires financial access
            add_submenu_page(
                'art-in-heaven',
                __('Winners & Sales', 'art-in-heaven'),
                __('Winners', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-winners',
                array($this, 'render_winners')
            );
            
            // Pickup - requires financial access
            add_submenu_page(
                'art-in-heaven',
                __('Pickup', 'art-in-heaven'),
                __('Pickup', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_FINANCIAL,
                'art-in-heaven-pickup',
                array($this, 'render_pickup')
            );
        }
        
        // Bidders - requires bidder management access (super admin only)
        if (AIH_Roles::can_manage_bidders()) {
            add_submenu_page(
                'art-in-heaven',
                __('Bidders', 'art-in-heaven'),
                __('Bidders', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_BIDDERS,
                'art-in-heaven-bidders',
                array($this, 'render_bidders')
            );
        }
        
        // Reports - requires reports access (super admin only)
        if (AIH_Roles::can_view_reports()) {
            add_submenu_page(
                'art-in-heaven',
                __('Reports', 'art-in-heaven'),
                __('Reports', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-reports',
                array($this, 'render_reports')
            );
            
            add_submenu_page(
                'art-in-heaven',
                __('Engagement Stats', 'art-in-heaven'),
                __('Engagement Stats', 'art-in-heaven'),
                AIH_Roles::CAP_VIEW_REPORTS,
                'art-in-heaven-stats',
                array($this, 'render_stats')
            );
        }
        
        // Migration - requires full auction management (super admin only)
        if (AIH_Roles::can_manage_auction()) {
            add_submenu_page(
                'art-in-heaven',
                __('Migration', 'art-in-heaven'),
                __('Migration', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_AUCTION,
                'art-in-heaven-migration',
                array($this, 'render_migration')
            );
        }
        
        // Integrations - requires settings access (super admin only)
        if (AIH_Roles::can_manage_settings()) {
            add_submenu_page(
                'art-in-heaven',
                __('Integrations', 'art-in-heaven'),
                __('Integrations', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-integrations',
                array($this, 'render_integrations')
            );
            
            add_submenu_page(
                'art-in-heaven',
                __('Transactions', 'art-in-heaven'),
                __('Transactions', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-transactions',
                array($this, 'render_transactions')
            );
        }
        
        // Settings - requires settings access (super admin only)
        if (AIH_Roles::can_manage_settings()) {
            add_submenu_page(
                'art-in-heaven',
                __('Settings', 'art-in-heaven'),
                __('Settings', 'art-in-heaven'),
                AIH_Roles::CAP_MANAGE_SETTINGS,
                'art-in-heaven-settings',
                array($this, 'render_settings')
            );
        }
    }
    
    public function admin_init() {
        // General settings
        register_setting('aih_settings', 'aih_currency_symbol');
        register_setting('aih_settings', 'aih_bid_increment');
        register_setting('aih_settings', 'aih_watermark_text');
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
        register_setting('aih_settings', 'aih_tax_rate');
        register_setting('aih_settings', 'aih_auction_year');
        register_setting('aih_settings', 'aih_event_date');
        register_setting('aih_settings', 'aih_event_end_date');
        register_setting('aih_settings', 'aih_login_page');
        register_setting('aih_settings', 'aih_gallery_page');
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
        register_setting('aih_integrations', 'aih_api_base_url');
        register_setting('aih_integrations', 'aih_api_form_id');
        register_setting('aih_integrations', 'aih_api_username');
        register_setting('aih_integrations', 'aih_api_password');
        register_setting('aih_integrations', 'aih_auto_sync_enabled', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => function($value) {
                $enabled = (bool) $value;
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
        register_setting('aih_integrations', 'aih_pushpay_merchant_key');
        register_setting('aih_integrations', 'aih_pushpay_merchant_handle');
        register_setting('aih_integrations', 'aih_pushpay_fund');
        register_setting('aih_integrations', 'aih_pushpay_client_id');
        register_setting('aih_integrations', 'aih_pushpay_client_secret');
        register_setting('aih_integrations', 'aih_pushpay_organization_key');

        // Pushpay settings - Sandbox (in aih_integrations group)
        register_setting('aih_integrations', 'aih_pushpay_sandbox_client_id');
        register_setting('aih_integrations', 'aih_pushpay_sandbox_client_secret');
        register_setting('aih_integrations', 'aih_pushpay_sandbox_organization_key');
        register_setting('aih_integrations', 'aih_pushpay_sandbox_merchant_key');
        register_setting('aih_integrations', 'aih_pushpay_sandbox_merchant_handle');
        
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
        $counts = $art_model->get_counts();
        $checkout = AIH_Checkout::get_instance();
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
    
    public function render_bids() {
        if (!AIH_Roles::can_view_bids()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/bids.php';
    }
    
    public function render_orders() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        $checkout = AIH_Checkout::get_instance();
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $orders = $checkout->get_all_orders(array('status' => $status_filter));
        $payment_stats = $checkout->get_payment_stats();
        $single_order = isset($_GET['order_id']) ? $checkout->get_order(intval($_GET['order_id'])) : null;
        
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
        
        include AIH_PLUGIN_DIR . 'admin/views/orders.php';
    }
    
    public function render_winners() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/winners.php';
    }

    public function render_pickup() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/pickup.php';
    }

    public function render_payments() {
        if (!AIH_Roles::can_view_financial()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/payments.php';
    }

    public function render_bidders() {
        if (!AIH_Roles::can_manage_bidders()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/bidders.php';
    }

    public function render_reports() {
        if (!AIH_Roles::can_view_reports()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/reports.php';
    }

    public function render_stats() {
        if (!AIH_Roles::can_view_reports()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        $art_model = new AIH_Art_Piece();
        $art_pieces = $art_model->get_all_with_stats();
        include AIH_PLUGIN_DIR . 'admin/views/stats.php';
    }

    public function render_migration() {
        if (!AIH_Roles::can_manage_auction()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/migration.php';
    }

    public function render_integrations() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/integrations.php';
    }

    public function render_transactions() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/transactions.php';
    }

    public function render_settings() {
        if (!AIH_Roles::can_manage_settings()) {
            wp_die(__('You do not have permission to access this page.', 'art-in-heaven'));
        }
        include AIH_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
