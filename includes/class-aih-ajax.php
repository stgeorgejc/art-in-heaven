<?php
/**
 * AJAX Handler
 *
 * Registers and handles all WordPress AJAX actions for both the admin
 * panel and the public-facing frontend. Every handler verifies a nonce
 * via check_ajax_referer() and, where applicable, checks user
 * capabilities through AIH_Roles before processing the request.
 *
 * @package ArtInHeaven
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Auth
        add_action('wp_ajax_aih_verify_code', array($this, 'verify_confirmation_code'));
        add_action('wp_ajax_nopriv_aih_verify_code', array($this, 'verify_confirmation_code'));
        add_action('wp_ajax_aih_logout', array($this, 'logout'));
        add_action('wp_ajax_nopriv_aih_logout', array($this, 'logout'));
        add_action('wp_ajax_aih_check_auth', array($this, 'check_auth'));
        add_action('wp_ajax_nopriv_aih_check_auth', array($this, 'check_auth'));
        
        // Bidding
        add_action('wp_ajax_aih_place_bid', array($this, 'place_bid'));
        add_action('wp_ajax_nopriv_aih_place_bid', array($this, 'place_bid'));
        add_action('wp_ajax_aih_toggle_favorite', array($this, 'toggle_favorite'));
        add_action('wp_ajax_nopriv_aih_toggle_favorite', array($this, 'toggle_favorite'));
        
        // Gallery
        add_action('wp_ajax_aih_get_gallery', array($this, 'get_gallery'));
        add_action('wp_ajax_nopriv_aih_get_gallery', array($this, 'get_gallery'));
        add_action('wp_ajax_aih_get_art_details', array($this, 'get_art_details'));
        add_action('wp_ajax_nopriv_aih_get_art_details', array($this, 'get_art_details'));
        add_action('wp_ajax_aih_search', array($this, 'search_art'));
        add_action('wp_ajax_nopriv_aih_search', array($this, 'search_art'));
        
        // Checkout
        add_action('wp_ajax_aih_get_won_items', array($this, 'get_won_items'));
        add_action('wp_ajax_nopriv_aih_get_won_items', array($this, 'get_won_items'));
        add_action('wp_ajax_aih_create_order', array($this, 'create_order'));
        add_action('wp_ajax_nopriv_aih_create_order', array($this, 'create_order'));
        add_action('wp_ajax_aih_get_pushpay_link', array($this, 'get_pushpay_link'));
        add_action('wp_ajax_nopriv_aih_get_pushpay_link', array($this, 'get_pushpay_link'));
        add_action('wp_ajax_aih_get_order_details', array($this, 'get_order_details'));
        add_action('wp_ajax_nopriv_aih_get_order_details', array($this, 'get_order_details'));
        add_action('wp_ajax_aih_get_my_purchases', array($this, 'get_my_purchases'));
        add_action('wp_ajax_nopriv_aih_get_my_purchases', array($this, 'get_my_purchases'));
        
        // Admin
        add_action('wp_ajax_aih_admin_save_art', array($this, 'admin_save_art'));
        add_action('wp_ajax_aih_admin_delete_art', array($this, 'admin_delete_art'));
        add_action('wp_ajax_aih_admin_bulk_update_times', array($this, 'admin_bulk_update_times'));
        add_action('wp_ajax_aih_admin_bulk_update_start_times', array($this, 'admin_bulk_update_start_times'));
        add_action('wp_ajax_aih_admin_bulk_show_end_time', array($this, 'admin_bulk_show_end_time'));
        add_action('wp_ajax_aih_admin_toggle_end_time', array($this, 'admin_toggle_end_time'));
        add_action('wp_ajax_aih_admin_inline_edit', array($this, 'admin_inline_edit'));
        add_action('wp_ajax_aih_admin_apply_event_date', array($this, 'admin_apply_event_date'));
        add_action('wp_ajax_aih_admin_process_watermark', array($this, 'admin_process_watermark'));
        add_action('wp_ajax_aih_admin_get_stats', array($this, 'admin_get_stats'));
        add_action('wp_ajax_aih_admin_update_payment', array($this, 'admin_update_payment'));
        add_action('wp_ajax_aih_admin_delete_order', array($this, 'admin_delete_order'));
        add_action('wp_ajax_aih_admin_delete_bid', array($this, 'admin_delete_bid'));
        add_action('wp_ajax_aih_update_pickup_status', array($this, 'admin_update_pickup_status'));
        add_action('wp_ajax_aih_admin_test_api', array($this, 'admin_test_api'));
        add_action('wp_ajax_aih_admin_create_tables', array($this, 'admin_create_tables'));
        add_action('wp_ajax_aih_admin_export_data', array($this, 'admin_export_data'));
        add_action('wp_ajax_aih_admin_sync_bidders', array($this, 'admin_sync_bidders'));
        add_action('wp_ajax_aih_admin_cleanup_tables', array($this, 'admin_cleanup_tables'));
        add_action('wp_ajax_aih_admin_purge_data', array($this, 'admin_purge_data'));
        add_action('wp_ajax_aih_admin_regenerate_watermarks', array($this, 'admin_regenerate_watermarks'));
        add_action('wp_ajax_aih_admin_import_csv', array($this, 'admin_import_csv'));

        // Pushpay API
        add_action('wp_ajax_aih_admin_test_pushpay', array($this, 'admin_test_pushpay'));
        add_action('wp_ajax_aih_admin_sync_pushpay', array($this, 'admin_sync_pushpay'));
        add_action('wp_ajax_aih_admin_match_transaction', array($this, 'admin_match_transaction'));
        add_action('wp_ajax_aih_admin_discover_pushpay_keys', array($this, 'admin_discover_pushpay_keys'));
        // Aliases for transactions page
        add_action('wp_ajax_aih_test_pushpay_connection', array($this, 'admin_test_pushpay'));
        add_action('wp_ajax_aih_sync_pushpay_transactions', array($this, 'admin_sync_pushpay'));
        add_action('wp_ajax_aih_match_transaction_to_order', array($this, 'admin_match_transaction'));
        add_action('wp_ajax_aih_get_transaction_details', array($this, 'admin_get_transaction_details'));
        
        // Multiple images
        add_action('wp_ajax_aih_admin_add_image', array($this, 'admin_add_image'));
        add_action('wp_ajax_aih_admin_remove_image', array($this, 'admin_remove_image'));
        add_action('wp_ajax_aih_admin_set_primary_image', array($this, 'admin_set_primary_image'));
        add_action('wp_ajax_aih_admin_reorder_images', array($this, 'admin_reorder_images'));
        add_action('wp_ajax_aih_admin_get_images', array($this, 'admin_get_images'));
        
        // Image upload flag (to disable intermediate sizes)
        add_action('wp_ajax_aih_set_upload_flag', array($this, 'set_upload_flag'));

        // Log Viewer
        add_action('wp_ajax_aih_admin_get_logs',   array($this, 'admin_get_logs'));
        add_action('wp_ajax_aih_admin_clear_logs', array($this, 'admin_clear_logs'));

        // Push notifications
        add_action('wp_ajax_aih_push_subscribe', array($this, 'push_subscribe'));
        add_action('wp_ajax_nopriv_aih_push_subscribe', array($this, 'push_subscribe'));
        add_action('wp_ajax_aih_push_unsubscribe', array($this, 'push_unsubscribe'));
        add_action('wp_ajax_nopriv_aih_push_unsubscribe', array($this, 'push_unsubscribe'));
        add_action('wp_ajax_aih_check_outbid', array($this, 'check_outbid'));
        add_action('wp_ajax_nopriv_aih_check_outbid', array($this, 'check_outbid'));

        // Status polling
        add_action('wp_ajax_aih_poll_status', array($this, 'poll_status'));
        add_action('wp_ajax_nopriv_aih_poll_status', array($this, 'poll_status'));
    }
    
    // ========== AUTH ==========
    
    public function verify_confirmation_code() {
        check_ajax_referer('aih_nonce', 'nonce');

        // Rate limiting: 5 attempts per 60 seconds
        $ip = AIH_Security::get_client_ip();
        if (!AIH_Security::check_rate_limit('ajax_auth_' . $ip, 5, 60)) {
            wp_send_json_error(array('message' => __('Too many attempts. Please wait.', 'art-in-heaven')));
        }

        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        if (empty($code)) wp_send_json_error(array('message' => __('Please enter your confirmation code.', 'art-in-heaven')));
        
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code($code);
        
        if ($result['success']) {
            // Login using confirmation_code as bidder ID (NOT email)
            $auth->login_bidder($result['bidder']['confirmation_code']);
            AIH_Database::log_audit('login', array(
                'bidder_id' => $result['bidder']['confirmation_code'],
                'details'   => array('ip' => AIH_Security::get_client_ip()),
            ));
            wp_send_json_success(array(
                'message' => sprintf(__('Welcome, %s!', 'art-in-heaven'), $result['bidder']['first_name']),
                'bidder' => $result['bidder']
            ));
        }
        wp_send_json_error(array('message' => $result['message']));
    }
    
    /**
     * Log the current bidder out and destroy their session.
     *
     * @return void Sends JSON response and exits.
     */
    public function logout() {
        check_ajax_referer('aih_nonce', 'nonce');
        AIH_Auth::get_instance()->logout_bidder();
        wp_send_json_success(array('message' => __('Logged out.', 'art-in-heaven')));
    }

    /**
     * Check the current bidder's authentication status.
     *
     * @return void Sends JSON response and exits.
     */
    public function check_auth() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        wp_send_json_success(array('logged_in' => $auth->is_logged_in(), 'bidder' => $auth->get_current_bidder()));
    }
    
    // ========== BIDDING ==========
    
    public function place_bid() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('message' => __('Please sign in.', 'art-in-heaven'), 'login_required' => true));

        // Rate limiting: 10 bids per 60 seconds
        if (!AIH_Security::check_rate_limit('ajax_bid_' . $auth->get_current_bidder_id(), 10, 60)) {
            wp_send_json_error(array('message' => __('Too many bid attempts. Please wait.', 'art-in-heaven')));
        }

        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        $bid_amount = floatval($_POST['bid_amount'] ?? 0);
        if (!$art_piece_id || $bid_amount <= 0) wp_send_json_error(array('message' => __('Invalid bid.', 'art-in-heaven')));
        
        // Ensure whole dollar amounts only
        $bid_amount = floor($bid_amount);
        if ($bid_amount < 1) wp_send_json_error(array('message' => __('Bid must be at least $1.', 'art-in-heaven')));
        
        $result = (new AIH_Bid())->place_bid($art_piece_id, $auth->get_current_bidder_id(), $bid_amount);
        if ($result['success']) {
            $auth->mark_registrant_has_bid($auth->get_current_bidder_id());
            AIH_Database::log_audit('bid_placed', array(
                'object_type' => 'bid',
                'object_id'   => $result['bid_id'] ?? 0,
                'bidder_id'   => $auth->get_current_bidder_id(),
                'details'     => array('art_piece_id' => $art_piece_id, 'amount' => $bid_amount),
            ));
            wp_send_json_success($result);
        }
        wp_send_json_error($result);
    }
    
    public function toggle_favorite() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        if (!$art_piece_id) wp_send_json_error(array('message' => __('Invalid.', 'art-in-heaven')));
        
        wp_send_json_success((new AIH_Favorites())->toggle($auth->get_current_bidder_id(), $art_piece_id));
    }
    
    // ========== GALLERY ==========
    
    /**
     * Return all active gallery art pieces as JSON.
     *
     * @return void Sends JSON response and exits.
     */
    public function get_gallery() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        $pieces = (new AIH_Art_Piece())->get_all(array('status' => 'active', 'bidder_id' => $bidder_id));

        // Batch-fetch winning IDs to avoid N+1 queries
        $batch_data = null;
        if ($bidder_id && !empty($pieces)) {
            $piece_ids = array_map(function($p) { return intval($p->id); }, $pieces);
            $bid_model = new AIH_Bid();
            $batch_data = array('winning_ids' => $bid_model->get_winning_ids_batch($piece_ids, $bidder_id));
        }

        $data = array();
        foreach ($pieces as $p) $data[] = $this->format_art_piece($p, $bidder_id, false, $batch_data);
        wp_send_json_success($data);
    }

    /**
     * Return details for a single art piece, including user bids and favorites.
     *
     * @return void Sends JSON response and exits.
     */
    public function get_art_details() {
        check_ajax_referer('aih_nonce', 'nonce');
        $art_id = intval($_POST['art_id'] ?? 0);
        if (!$art_id) wp_send_json_error(array('message' => __('Invalid.', 'art-in-heaven')));

        $piece = (new AIH_Art_Piece())->get($art_id);
        if (!$piece) wp_send_json_error(array('message' => __('Not found.', 'art-in-heaven')));

        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        $data = $this->format_art_piece($piece, $bidder_id, true);

        if ($bidder_id) {
            $bids = (new AIH_Bid())->get_bidder_bids_for_art_piece($art_id, $bidder_id);
            $data['user_bids'] = array();
            foreach ($bids as $b) {
                $data['user_bids'][] = array('amount' => number_format($b->bid_amount, 2), 'time' => date_i18n('M j, g:i a', strtotime($b->bid_time)), 'is_winning' => (bool)$b->is_winning);
            }
            $data['is_favorite'] = (new AIH_Favorites())->is_favorite($bidder_id, $art_id);
        }
        wp_send_json_success($data);
    }

    /**
     * Search active art pieces by keyword and return matching results.
     *
     * @return void Sends JSON response and exits.
     */
    public function search_art() {
        check_ajax_referer('aih_nonce', 'nonce');
        $search = sanitize_text_field($_POST['search'] ?? '');
        if (strlen($search) < 2) wp_send_json_success(array());

        $auth = AIH_Auth::get_instance();
        $bidder_id = $auth->get_current_bidder_id();
        $results = (new AIH_Art_Piece())->get_all(array('search' => $search, 'status' => 'active', 'bidder_id' => $bidder_id, 'limit' => 20));

        // Batch-fetch winning IDs to avoid N+1 queries
        $batch_data = null;
        if ($bidder_id && !empty($results)) {
            $piece_ids = array_map(function($p) { return intval($p->id); }, $results);
            $bid_model = new AIH_Bid();
            $batch_data = array('winning_ids' => $bid_model->get_winning_ids_batch($piece_ids, $bidder_id));
        }

        $data = array();
        foreach ($results as $p) $data[] = $this->format_art_piece($p, $bidder_id, false, $batch_data);
        wp_send_json_success($data);
    }

    // ========== CHECKOUT ==========

    /**
     * Return items won by the current bidder for checkout.
     *
     * @return void Sends JSON response and exits.
     */
    public function get_won_items() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));
        
        $checkout = AIH_Checkout::get_instance();
        $items = $checkout->get_won_items($auth->get_current_bidder_id());
        $data = array();
        foreach ($items as $item) {
            $data[] = array('id' => $item->id, 'art_id' => $item->art_id, 'title' => $item->title, 'artist' => $item->artist, 'image_url' => $item->watermarked_url ?: $item->image_url, 'winning_amount' => number_format($item->winning_amount, 2));
        }
        wp_send_json_success(array('items' => $data, 'totals' => $checkout->calculate_totals($items)));
    }
    
    public function create_order() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));

        // Rate limiting: 5 orders per 60 seconds
        if (!AIH_Security::check_rate_limit('ajax_order_' . $auth->get_current_bidder_id(), 5, 60)) {
            wp_send_json_error(array('message' => __('Too many order attempts. Please wait.', 'art-in-heaven')));
        }

        $art_piece_ids = isset($_POST['art_piece_ids']) ? array_map('intval', (array) $_POST['art_piece_ids']) : array();
        if (empty($art_piece_ids)) {
            wp_send_json_error(array('message' => __('Please select at least one item to pay for.', 'art-in-heaven')));
        }
        $result = AIH_Checkout::get_instance()->create_order($auth->get_current_bidder_id(), $art_piece_ids);
        if ($result['success']) {
            AIH_Database::log_audit('order_created', array(
                'object_type' => 'order',
                'object_id'   => $result['order_id'] ?? 0,
                'bidder_id'   => $auth->get_current_bidder_id(),
                'details'     => array('order_number' => $result['order_number'] ?? ''),
            ));
            wp_send_json_success($result);
        }
        wp_send_json_error($result);
    }
    
    public function get_pushpay_link() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number(sanitize_text_field($_POST['order_number'] ?? ''));
        if (!$order) wp_send_json_error(array('message' => __('Order not found.', 'art-in-heaven')));

        // Verify this order belongs to the current bidder
        $current_bidder = $auth->get_current_bidder_id();
        if ($order->bidder_id != $current_bidder) {
            wp_send_json_error(array('message' => __('Order does not belong to this account.', 'art-in-heaven')));
        }

        wp_send_json_success(array('pushpay_url' => $checkout->get_pushpay_payment_url($order)));
    }

    /**
     * Get order details for frontend display
     */
    public function get_order_details() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('message' => __('Session expired. Please refresh the page and sign in again.', 'art-in-heaven')));

        $order_number = sanitize_text_field($_POST['order_number'] ?? '');
        if (empty($order_number)) wp_send_json_error(array('message' => __('Order number required.', 'art-in-heaven')));

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number($order_number);

        if (!$order) wp_send_json_error(array('message' => __('Order not found.', 'art-in-heaven')));

        // Verify this order belongs to the current bidder
        $current_bidder = $auth->get_current_bidder_id();
        if ($order->bidder_id != $current_bidder) {
            wp_send_json_error(array('message' => __('Order does not belong to this account.', 'art-in-heaven')));
        }

        // Format items for response
        $items = array();
        if (!empty($order->items)) {
            foreach ($order->items as $item) {
                $items[] = array(
                    'art_id' => $item->art_id ?? '',
                    'title' => $item->title ?? '',
                    'artist' => $item->artist ?? '',
                    'image_url' => $item->watermarked_url ?: ($item->image_url ?? ''),
                    'winning_bid' => floatval($item->winning_bid)
                );
            }
        }

        wp_send_json_success(array(
            'order_number' => $order->order_number,
            'created_at' => date_i18n('M j, Y g:i a', strtotime($order->created_at)),
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method ?? '',
            'payment_reference' => $order->payment_reference ?? '',
            'subtotal' => floatval($order->subtotal),
            'tax' => floatval($order->tax),
            'total' => floatval($order->total),
            'pickup_status' => $order->pickup_status ?? 'pending',
            'items' => $items
        ));
    }

    /**
     * Get all purchased items for My Wins page
     */
    public function get_my_purchases() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));

        $bidder_id = $auth->get_current_bidder_id();
        $checkout = AIH_Checkout::get_instance();

        // Get all paid orders for this bidder
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $items_table = AIH_Database::get_table('order_items');
        $art_table = AIH_Database::get_table('art_pieces');
        $images_table = AIH_Database::get_table('art_images');

        $purchases = $wpdb->get_results($wpdb->prepare(
            "SELECT
                oi.id as item_id,
                oi.winning_bid,
                o.order_number,
                o.payment_status,
                o.pickup_status,
                o.created_at as order_date,
                ap.id as art_piece_id,
                ap.art_id,
                ap.title,
                ap.artist,
                ap.medium,
                ap.dimensions,
                ap.description,
                COALESCE(ai.watermarked_url, ap.watermarked_url, ap.image_url) as image_url
            FROM {$items_table} oi
            JOIN {$orders_table} o ON oi.order_id = o.id
            JOIN {$art_table} ap ON oi.art_piece_id = ap.id
            LEFT JOIN {$images_table} ai ON ap.id = ai.art_piece_id AND ai.is_primary = 1
            WHERE o.bidder_id = %s
            AND o.payment_status = 'paid'
            ORDER BY o.created_at DESC",
            $bidder_id
        ));

        $items = array();
        foreach ($purchases as $p) {
            $items[] = array(
                'item_id' => $p->item_id,
                'art_piece_id' => $p->art_piece_id,
                'art_id' => $p->art_id,
                'title' => $p->title,
                'artist' => $p->artist,
                'medium' => $p->medium,
                'dimensions' => $p->dimensions,
                'description' => $p->description,
                'image_url' => $p->image_url,
                'winning_bid' => floatval($p->winning_bid),
                'order_number' => $p->order_number,
                'order_date' => date_i18n('M j, Y', strtotime($p->order_date)),
                'pickup_status' => $p->pickup_status
            );
        }

        wp_send_json_success(array('items' => $items));
    }

    // ========== ADMIN ==========
    
    public function admin_save_art() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        $art_model = new AIH_Art_Piece();
        $watermark = new AIH_Watermark();
        
        // Get default times from settings
        $event_date = get_option('aih_event_date', '');
        $event_end_date = get_option('aih_event_end_date', '');
        $default_start = $event_date ? wp_date('Y-m-d H:i:s', strtotime($event_date)) : current_time('mysql');
        $default_end = $event_end_date ? wp_date('Y-m-d H:i:s', strtotime($event_end_date)) : '';
        
        // Art ID is required for new pieces
        $record_id = intval($_POST['id'] ?? 0);
        $custom_art_id = trim(sanitize_text_field($_POST['art_id'] ?? ''));
        
        if (!$record_id && empty($custom_art_id)) {
            wp_send_json_error(array('message' => __('Art ID is required.', 'art-in-heaven')));
        }
        
        // Tier is required
        $tier = sanitize_text_field($_POST['tier'] ?? '');
        if (empty($tier)) {
            wp_send_json_error(array('message' => __('Tier is required. Please select a tier (1-4).', 'art-in-heaven')));
        }
        
        // Default status is 'active' - will show as 'upcoming' if start time is in future
        // Only use 'draft' if explicitly selected by the user
        $default_status = 'active';
        
        // Convert datetime-local format (YYYY-MM-DDTHH:MM) to MySQL format (YYYY-MM-DD HH:MM:SS)
        $auction_start = sanitize_text_field($_POST['auction_start'] ?? $default_start);
        $auction_end = sanitize_text_field($_POST['auction_end'] ?? $default_end);
        
        // Convert 'T' separator to space and add seconds if not present
        if (!empty($auction_start)) {
            $auction_start = str_replace('T', ' ', $auction_start);
            if (strlen($auction_start) === 16) {
                $auction_start .= ':00'; // Add seconds
            }
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $auction_start);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $auction_start) {
                wp_send_json_error(array('message' => __('Invalid auction start date format.', 'art-in-heaven')));
            }
        }
        if (!empty($auction_end)) {
            $auction_end = str_replace('T', ' ', $auction_end);
            if (strlen($auction_end) === 16) {
                $auction_end .= ':00'; // Add seconds
            }
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $auction_end);
            if (!$dt || $dt->format('Y-m-d H:i:s') !== $auction_end) {
                wp_send_json_error(array('message' => __('Invalid auction end date format.', 'art-in-heaven')));
            }
        }
        
        // Check if admin wants to force the status (bypass auto-status logic)
        $force_status = isset($_POST['force_status']) && $_POST['force_status'] === '1';
        $requested_status = sanitize_text_field($_POST['status'] ?? $default_status);
        
        // Custom sanitize function that preserves quotes
        $sanitize_with_quotes = function($str) {
            $str = stripslashes($str); // Remove WordPress magic quotes
            $str = wp_strip_all_tags($str); // Remove HTML tags
            $str = preg_replace('/[\r\n\t]+/', ' ', $str); // Replace newlines/tabs with space
            $str = trim($str);
            return $str;
        };
        
        $data = array(
            'title' => $sanitize_with_quotes($_POST['title'] ?? ''),
            'artist' => $sanitize_with_quotes($_POST['artist'] ?? ''),
            'medium' => $sanitize_with_quotes($_POST['medium'] ?? ''),
            'dimensions' => $sanitize_with_quotes($_POST['dimensions'] ?? ''),
            'description' => wp_kses_post(stripslashes($_POST['description'] ?? '')),
            'tier' => $tier,
            'auction_start' => $auction_start,
            'auction_end' => $auction_end,
            'show_end_time' => isset($_POST['show_end_time']) && $_POST['show_end_time'] === '1' ? 1 : 0,
            'status' => $requested_status,
            'force_status' => $force_status  // Pass this to the model
        );
        
        // Handle Art ID
        if (!empty($custom_art_id)) {
            $data['art_id'] = strtoupper($custom_art_id);
        }
        
        // Only allow starting_bid changes if user can view bids
        if (AIH_Roles::can_view_bids()) {
            $data['starting_bid'] = floatval($_POST['starting_bid'] ?? 0);
        } elseif (isset($_POST['starting_bid'])) {
            // Art managers: preserve existing value or use submitted hidden value
            $data['starting_bid'] = floatval($_POST['starting_bid']);
        }
        
        // Handle image - check if image_id is set in POST
        if (isset($_POST['image_id'])) {
            $image_id = intval($_POST['image_id']);
            if ($image_id > 0) {
                // New image uploaded
                $data['image_id'] = $image_id;
                $data['image_url'] = wp_get_attachment_url($image_id);
                
                // Process watermark
                $watermarked = $watermark->process_upload($image_id);
                if ($watermarked) {
                    $data['watermarked_url'] = $watermarked;
                } else {
                    // Watermark failed - use original image
                    $data['watermarked_url'] = $data['image_url'];
                    error_log('Art in Heaven: Watermark failed for attachment ID ' . $image_id);
                }
            } else {
                // Image removed - clear all image fields
                $data['image_id'] = null;
                $data['image_url'] = null;
                $data['watermarked_url'] = null;
            }
        }
        
        if ($record_id) {
            // Check if changing art_id to one that already exists (excluding this record)
            if (!empty($custom_art_id)) {
                $existing = $art_model->get_by_art_id($custom_art_id);
                if ($existing && (int)$existing->id !== $record_id) {
                    wp_send_json_error(array('message' => sprintf(__('Art ID "%s" is already in use by another piece.', 'art-in-heaven'), $custom_art_id)));
                }
            }
            
            // Pass expected updated_at for optimistic locking (concurrent edit detection)
            if (!empty($_POST['updated_at'])) {
                $data['_expected_updated_at'] = sanitize_text_field($_POST['updated_at']);
            }

            $result = $art_model->update($record_id, $data);

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }

            $piece = $art_model->get($record_id);
            AIH_Database::log_audit('art_updated', array(
                'object_type' => 'art_piece',
                'object_id'   => $record_id,
                'details'     => array('art_id' => $piece->art_id, 'title' => $piece->title),
            ));
            wp_send_json_success(array('message' => __('Updated successfully!', 'art-in-heaven'), 'id' => $record_id, 'art_id' => $piece->art_id, 'final_status' => $piece->status, 'updated_at' => $piece->updated_at));
        } else {
            // For new records, check if art_id already exists
            if (!empty($custom_art_id)) {
                $existing = $art_model->get_by_art_id($custom_art_id);
                if ($existing) {
                    wp_send_json_error(array('message' => sprintf(__('Art ID "%s" is already in use. Please choose a different Art ID.', 'art-in-heaven'), $custom_art_id)));
                }
            }
            
            $new_id = $art_model->create($data);
            if (!$new_id) {
                global $wpdb;
                if ($wpdb->last_error) {
                    error_log('[AIH] create_art DB error: ' . $wpdb->last_error);
                }
                wp_send_json_error(array('message' => __('Failed to create art piece. Please try again.', 'art-in-heaven')));
            }
            
            // Also add the image to art_images table for multi-image support
            if (isset($_POST['image_id']) && intval($_POST['image_id']) > 0) {
                $image_id = intval($_POST['image_id']);
                $images_handler = new AIH_Art_Images();
                $images_handler->add_image(
                    $new_id,
                    $image_id,
                    wp_get_attachment_url($image_id),
                    $data['watermarked_url'] ?? wp_get_attachment_url($image_id),
                    true // is_primary
                );
            }
            
            $piece = $art_model->get($new_id);
            AIH_Database::log_audit('art_created', array(
                'object_type' => 'art_piece',
                'object_id'   => $new_id,
                'details'     => array('art_id' => $piece->art_id, 'title' => $piece->title),
            ));
            wp_send_json_success(array('message' => __('Created successfully!', 'art-in-heaven'), 'id' => $new_id, 'art_id' => $piece->art_id, 'final_status' => $piece->status));
        }
    }
    
    public function admin_delete_art() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $art_id = intval($_POST['id'] ?? 0);
        if (!$art_id) wp_send_json_error(array('message' => __('Invalid.', 'art-in-heaven')));
        (new AIH_Art_Piece())->delete($art_id);
        AIH_Database::log_audit('art_deleted', array(
            'object_type' => 'art_piece',
            'object_id'   => $art_id,
        ));
        wp_send_json_success(array('message' => __('Deleted.', 'art-in-heaven')));
    }
    
    public function admin_bulk_update_times() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $new_end_time = sanitize_text_field($_POST['new_end_time'] ?? '');
        if (empty($ids) || empty($new_end_time)) wp_send_json_error(array('message' => __('Missing data.', 'art-in-heaven')));
        (new AIH_Art_Piece())->bulk_update_end_times($ids, $new_end_time);
        wp_send_json_success(array('message' => count($ids) . ' items updated.'));
    }
    
    public function admin_bulk_update_start_times() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $new_start_time = sanitize_text_field($_POST['new_start_time'] ?? '');
        if (empty($ids) || empty($new_start_time)) wp_send_json_error(array('message' => __('Missing data.', 'art-in-heaven')));
        (new AIH_Art_Piece())->bulk_update_start_times($ids, $new_start_time);
        wp_send_json_success(array('message' => count($ids) . ' items updated.'));
    }
    
    public function admin_bulk_show_end_time() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $show = isset($_POST['show']) && $_POST['show'] == '1' ? 1 : 0;
        if (empty($ids)) wp_send_json_error(array('message' => __('No items selected.', 'art-in-heaven')));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET show_end_time = %d WHERE id IN ($placeholders)",
            array_merge(array($show), $ids)
        ));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Database error updating end time visibility.', 'art-in-heaven')));
        }

        $action = $show ? __('revealed', 'art-in-heaven') : __('hidden', 'art-in-heaven');
        wp_send_json_success(array('message' => sprintf(__('End times %s for %d items.', 'art-in-heaven'), $action, count($ids))));
    }
    
    public function admin_toggle_end_time() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        $id = intval($_POST['id'] ?? 0);
        $show = isset($_POST['show']) && $_POST['show'] == '1' ? 1 : 0;
        
        if (!$id) wp_send_json_error(array('message' => __('Invalid item.', 'art-in-heaven')));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');

        $result = $wpdb->update($table, array('show_end_time' => $show), array('id' => $id), array('%d'), array('%d'));
        
        if ($result === false) {
            error_log('[AIH] admin_toggle_end_time DB error: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Database error occurred.', 'art-in-heaven')));
        }

        wp_send_json_success(array(
            'message' => $show ? __('End time revealed', 'art-in-heaven') : __('End time hidden', 'art-in-heaven'),
            'show' => $show
        ));
    }
    
    /**
     * Inline edit a single field for an art piece
     */
    public function admin_inline_edit() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        $id = intval($_POST['id'] ?? 0);
        $field = sanitize_key($_POST['field'] ?? '');
        $raw_value = $_POST['value'] ?? '';
        // Default sanitization applied immediately; field-specific sanitization below
        $value = sanitize_text_field($raw_value);
        
        if (!$id || !$field) {
            wp_send_json_error(array('message' => __('Invalid request.', 'art-in-heaven')));
        }
        
        // Allowed fields for inline editing
        $allowed_fields = array(
            'art_id' => '%s',
            'title' => '%s',
            'artist' => '%s',
            'medium' => '%s',
            'tier' => '%s',
            'starting_bid' => '%f',
            'auction_start' => '%s',
            'auction_end' => '%s',
            'show_end_time' => '%d'
        );
        
        if (!isset($allowed_fields[$field])) {
            wp_send_json_error(array('message' => __('Field not editable.', 'art-in-heaven')));
        }
        
        // Apply field-specific sanitization (overrides default sanitize_text_field above)
        switch ($field) {
            case 'starting_bid':
                $value = floatval(preg_replace('/[^0-9.]/', '', $raw_value));
                break;
            case 'auction_start':
            case 'auction_end':
                // Convert datetime-local format to MySQL format
                $value = str_replace('T', ' ', $value);
                if (strlen($value) === 16) $value .= ':00'; // Add seconds if missing
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $value);
                if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
                    wp_send_json_error(array('message' => __('Invalid date format.', 'art-in-heaven')));
                }
                break;
            case 'show_end_time':
                $value = intval($value);
                break;
            // 'tier' and default already sanitized via sanitize_text_field() above
        }
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');
        $art_model = new AIH_Art_Piece();

        // Use the model's update() for date fields so auto-status logic runs
        if (in_array($field, array('auction_start', 'auction_end'))) {
            $result = $art_model->update($id, array($field => $value));
        } else {
            $result = $wpdb->update(
                $table,
                array($field => $value),
                array('id' => $id),
                array($allowed_fields[$field]),
                array('%d')
            );
        }

        if ($result === false) {
            error_log('[AIH] admin_inline_edit DB error: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Database error occurred.', 'art-in-heaven')));
        }

        // Format the display value for return
        $display_value = $value;
        switch ($field) {
            case 'starting_bid':
                $display_value = '$' . number_format($value, 2);
                break;
            case 'auction_start':
            case 'auction_end':
                $display_value = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($value));
                break;
        }

        // Compute new status HTML if date fields were changed
        $status_html = '';
        if (in_array($field, array('auction_start', 'auction_end'))) {
            $bids_table = AIH_Database::get_table('bids');
            $orders_table = AIH_Database::get_table('orders');
            $order_items_table = AIH_Database::get_table('order_items');
            $now = current_time('mysql');

            $piece = $wpdb->get_row($wpdb->prepare(
                "SELECT a.status, a.auction_start, a.auction_end,
                        COUNT(DISTINCT b.id) as total_bids,
                        o.payment_status, o.pickup_status,
                        CASE
                            WHEN a.status = 'draft' THEN 'draft'
                            WHEN a.auction_end IS NOT NULL AND a.auction_end <= %s THEN 'ended'
                            WHEN a.status = 'ended' AND (a.auction_end IS NULL OR a.auction_end > %s) AND (a.auction_start IS NULL OR a.auction_start <= %s) THEN 'active'
                            WHEN a.status = 'ended' THEN 'ended'
                            WHEN a.auction_start IS NOT NULL AND a.auction_start > %s THEN 'upcoming'
                            ELSE 'active'
                        END as computed_status
                 FROM $table a
                 LEFT JOIN $bids_table b ON a.id = b.art_piece_id
                 LEFT JOIN $order_items_table oi ON a.id = oi.art_piece_id
                 LEFT JOIN $orders_table o ON oi.order_id = o.id
                 WHERE a.id = %d
                 GROUP BY a.id",
                $now, $now, $now, $now, $id
            ));

            if ($piece) {
                if ($piece->status === 'draft') {
                    $status_html = '<span class="aih-status-badge draft">' . __('Draft', 'art-in-heaven') . '</span>';
                } elseif ($piece->computed_status === 'upcoming') {
                    $status_html = '<span class="aih-status-badge upcoming">' . __('Upcoming', 'art-in-heaven') . '</span>';
                } elseif ($piece->computed_status === 'active') {
                    $status_html = '<span class="aih-status-badge active">' . __('Active', 'art-in-heaven') . '</span>';
                } elseif ($piece->computed_status === 'ended') {
                    if ($piece->total_bids > 0) {
                        if (!empty($piece->pickup_status) && $piece->pickup_status === 'picked_up') {
                            $status_html = '<span class="aih-status-badge" style="background: #dbeafe; color: #1e40af;">' . __('Picked Up', 'art-in-heaven') . '</span>';
                        } elseif (!empty($piece->payment_status) && $piece->payment_status === 'paid') {
                            $status_html = '<span class="aih-status-badge paid">' . __('Paid', 'art-in-heaven') . '</span>';
                        } else {
                            $status_html = '<span class="aih-status-badge sold">' . __('Sold', 'art-in-heaven') . '</span>';
                        }
                    } else {
                        $status_html = '<span class="aih-status-badge not_sold">' . __('Unsold', 'art-in-heaven') . '</span>';
                    }
                }
            }
        }

        wp_send_json_success(array(
            'message' => __('Updated successfully', 'art-in-heaven'),
            'value' => $value,
            'display_value' => $display_value,
            'status_html' => $status_html
        ));
    }
    
    public function admin_apply_event_date() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $event_date = get_option('aih_event_date', '');
        if (empty($event_date)) wp_send_json_error(array('message' => __('Event date not set.', 'art-in-heaven')));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');
        $updated = $wpdb->query($wpdb->prepare("UPDATE $table SET auction_start = %s WHERE status = 'active'", wp_date('Y-m-d H:i:s', strtotime($event_date))));
        if ($updated === false) {
            wp_send_json_error(array('message' => __('Database error applying event date.', 'art-in-heaven')));
        }
        wp_send_json_success(array('message' => sprintf('%d art pieces updated.', $updated)));
    }
    
    public function admin_process_watermark() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $image_id = intval($_POST['image_id'] ?? 0);
        if (!$image_id) wp_send_json_error(array('message' => __('Invalid.', 'art-in-heaven')));
        $url = (new AIH_Watermark())->process_upload($image_id);
        if ($url) wp_send_json_success(array('watermarked_url' => $url));
        wp_send_json_error(array('message' => __('Failed.', 'art-in-heaven')));
    }
    
    public function admin_get_stats() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        wp_send_json_success((new AIH_Art_Piece())->get_all_with_stats());
    }
    
    public function admin_update_payment() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_financial()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        if (!$order_id || !$status) wp_send_json_error(array('message' => __('Missing data.', 'art-in-heaven')));
        AIH_Checkout::get_instance()->update_payment_status($order_id, $status, sanitize_text_field($_POST['method'] ?? ''), sanitize_text_field($_POST['reference'] ?? ''), sanitize_textarea_field($_POST['notes'] ?? ''));

        // Audit log
        AIH_Database::log_audit('payment_updated', array(
            'object_type' => 'order',
            'object_id' => $order_id,
            'details' => array(
                'new_status' => $status,
                'method' => sanitize_text_field($_POST['method'] ?? ''),
            ),
        ));

        wp_send_json_success(array('message' => 'Updated.'));
    }
    
    public function admin_delete_order() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_financial()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) wp_send_json_error(array('message' => __('Invalid.', 'art-in-heaven')));
        AIH_Checkout::get_instance()->delete_order($order_id);
        AIH_Database::log_audit('order_deleted', array(
            'object_type' => 'order',
            'object_id'   => $order_id,
        ));
        wp_send_json_success(array('message' => __('Deleted.', 'art-in-heaven')));
    }
    
    /**
     * Delete a bid (admin only)
     */
    public function admin_delete_bid() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_auction()) {
            wp_send_json_error(array('message' => __('Permission denied. Only super admins can delete bids.', 'art-in-heaven')));
        }
        
        $bid_id = intval($_POST['bid_id'] ?? 0);
        if (!$bid_id) {
            wp_send_json_error(array('message' => __('Invalid bid ID.', 'art-in-heaven')));
        }
        
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        $registrants_table = AIH_Database::get_table('registrants');
        
        // Get the bid first to check if it's a winning bid
        $bid = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$bids_table} WHERE id = %d",
            $bid_id
        ));
        
        if (!$bid) {
            wp_send_json_error(array('message' => __('Bid not found.', 'art-in-heaven')));
        }
        
        // Store bidder_id for later check
        $bidder_id = $bid->bidder_id;
        
        // If this is the winning bid, we need to recalculate
        $was_winning = $bid->is_winning == 1;
        $art_piece_id = $bid->art_piece_id;
        
        // Delete the bid
        $deleted = $wpdb->delete($bids_table, array('id' => $bid_id), array('%d'));
        
        if (!$deleted) {
            wp_send_json_error(array('message' => __('Failed to delete bid.', 'art-in-heaven')));
        }
        
        // If this was the winning bid, set the next highest bid as winning
        if ($was_winning) {
            $next_highest = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bids_table} WHERE art_piece_id = %d AND bid_status = 'valid' ORDER BY bid_amount DESC LIMIT 1",
                $art_piece_id
            ));
            
            if ($next_highest) {
                $update_result = $wpdb->update(
                    $bids_table,
                    array('is_winning' => 1),
                    array('id' => $next_highest->id),
                    array('%d'),
                    array('%d')
                );
                if ($update_result === false) {
                    wp_send_json_error(array('message' => __('Failed to update winning bid status.', 'art-in-heaven')));
                }
            }
        }

        // Check if bidder still has any bids, update has_bid flag accordingly
        $remaining_bids = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$bids_table} WHERE bidder_id = %s",
            $bidder_id
        ));
        
        if ($remaining_bids == 0) {
            // No more bids, set has_bid to 0
            $wpdb->update(
                $registrants_table,
                array('has_bid' => 0),
                array('confirmation_code' => $bidder_id),
                array('%d'),
                array('%s')
            );
        }
        
        // Audit log
        AIH_Database::log_audit('bid_deleted', array(
            'object_type' => 'bid',
            'object_id' => $bid_id,
            'bidder_id' => $bidder_id,
            'details' => array(
                'art_piece_id' => $art_piece_id,
                'bid_amount' => $bid->bid_amount,
                'was_winning' => $was_winning,
            ),
        ));

        // Invalidate cache
        if (class_exists('AIH_Cache')) {
            AIH_Cache::delete_group('bids');
        }

        wp_send_json_success(array('message' => 'Bid deleted successfully.'));
    }
    
    /**
     * Update pickup status for an order
     */
    public function admin_update_pickup_status() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_pickup()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }

        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $pickup_by = sanitize_text_field($_POST['pickup_by'] ?? '');
        $pickup_notes = sanitize_textarea_field($_POST['pickup_notes'] ?? '');
        
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID.', 'art-in-heaven')));
        }
        
        if (!in_array($status, array('pending', 'picked_up'))) {
            wp_send_json_error(array('message' => __('Invalid status.', 'art-in-heaven')));
        }
        
        // Require name when marking as picked up
        if ($status === 'picked_up' && empty($pickup_by)) {
            wp_send_json_error(array('message' => __('Name is required when marking as picked up.', 'art-in-heaven')));
        }
        
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        
        // Check order exists and is paid
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found.', 'art-in-heaven')));
        }
        
        if ($order->payment_status !== 'paid') {
            wp_send_json_error(array('message' => __('Order must be paid before marking as picked up.', 'art-in-heaven')));
        }
        
        // Update pickup status
        $update_data = array(
            'pickup_status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($status === 'picked_up') {
            $update_data['pickup_date'] = current_time('mysql');
            $update_data['pickup_by'] = $pickup_by;
            $update_data['pickup_notes'] = $pickup_notes;
        } else {
            $update_data['pickup_date'] = null;
            $update_data['pickup_by'] = '';
            $update_data['pickup_notes'] = '';
        }
        
        $result = $wpdb->update(
            $orders_table,
            $update_data,
            array('id' => $order_id)
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Database error.', 'art-in-heaven')));
        }
        
        // Audit log
        AIH_Database::log_audit('pickup_updated', array(
            'object_type' => 'order',
            'object_id' => $order_id,
            'details' => array(
                'new_status' => $status,
                'pickup_by' => $pickup_by,
            ),
        ));

        wp_send_json_success(array(
            'message' => $status === 'picked_up' ? 'Marked as picked up.' : 'Pickup status reset.',
            'status' => $status
        ));
    }
    
    public function admin_test_api() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_settings()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $result = AIH_Auth::get_instance()->test_api_connection();
        if ($result['success']) wp_send_json_success($result);
        wp_send_json_error($result);
    }
    
    public function admin_create_tables() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        $year = intval($_POST['year'] ?? wp_date('Y'));
        AIH_Database::create_tables($year);
        wp_send_json_success(array('message' => sprintf('Tables created for %d.', $year)));
    }
    
    public function admin_export_data() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_reports()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        $type = sanitize_text_field($_POST['type'] ?? 'art');
        $art_model = new AIH_Art_Piece();
        
        switch ($type) {
            case 'bids':
                global $wpdb;
                $data = $wpdb->get_results("SELECT * FROM " . AIH_Database::get_table('bids'));
                break;
            case 'bidders':
                $data = AIH_Auth::get_instance()->get_all_bidders();
                break;
            case 'orders':
                $data = AIH_Checkout::get_instance()->get_all_orders();
                break;
            default:
                $data = $art_model->get_all_with_stats();
        }
        
        wp_send_json_success(array('data' => $data));
    }

    public function admin_import_csv() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }

        // Check file upload
        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('No file uploaded or upload error.', 'art-in-heaven')));
        }

        $file = $_FILES['csv_file'];

        // Validate MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = array('text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel');
        if (!in_array($mime, $allowed)) {
            wp_send_json_error(array('message' => __('Invalid file type. Please upload a CSV file.', 'art-in-heaven')));
        }

        // Validate size (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('File exceeds 2MB limit.', 'art-in-heaven')));
        }

        $update_existing = !empty($_POST['update_existing']) && $_POST['update_existing'] === '1';

        // Read file contents, strip BOM, then delete temp file
        $contents = file_get_contents($file['tmp_name']);
        @unlink($file['tmp_name']);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $lines = preg_split('/\r\n|\r|\n/', $contents);
        $lines = array_filter($lines, function($line) { return trim($line) !== ''; });
        $lines = array_values($lines);

        if (count($lines) < 2) {
            wp_send_json_error(array('message' => __('CSV file must have a header row and at least one data row.', 'art-in-heaven')));
        }

        // Parse header row
        $headers = str_getcsv($lines[0], ',', '"', '');
        $headers = array_map(function($h) { return strtolower(trim($h)); }, $headers);
        $col_map = array_flip($headers);

        // Validate required headers
        $required_headers = array('art_id', 'title', 'artist', 'medium', 'tier');
        $missing = array();
        foreach ($required_headers as $rh) {
            if (!isset($col_map[$rh])) {
                $missing[] = $rh;
            }
        }
        if (!empty($missing)) {
            wp_send_json_error(array('message' => sprintf(__('Missing required columns: %s', 'art-in-heaven'), implode(', ', $missing))));
        }

        // Get auction dates from form inputs, falling back to settings
        $settings = get_option('aih_settings', array());
        $auction_start = !empty($_POST['auction_start']) ? sanitize_text_field($_POST['auction_start']) : (isset($settings['event_date']) ? $settings['event_date'] : '');
        $auction_end = !empty($_POST['auction_end']) ? sanitize_text_field($_POST['auction_end']) : (isset($settings['event_end_date']) ? $settings['event_end_date'] : '');

        $art_model = new AIH_Art_Piece();
        $summary = array('created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0);
        $row_results = array();
        $max_rows = 500;
        $data_lines = array_slice($lines, 1, $max_rows);

        foreach ($data_lines as $i => $line) {
            $row_num = $i + 2; // 1-indexed, accounting for header
            $summary['total']++;
            $fields = str_getcsv($line, ',', '"', '');

            // Helper to get column value
            $get = function($col) use ($fields, $col_map) {
                if (!isset($col_map[$col])) return '';
                $idx = $col_map[$col];
                return isset($fields[$idx]) ? trim($fields[$idx]) : '';
            };

            $art_id = strtoupper($get('art_id'));
            $title = $get('title');
            $artist = $get('artist');
            $medium = $get('medium');
            $tier = $get('tier');

            // Validate required fields
            $errors = array();
            if ($art_id === '') $errors[] = 'art_id is empty';
            if ($title === '') $errors[] = 'title is empty';
            if ($artist === '') $errors[] = 'artist is empty';
            if ($medium === '') $errors[] = 'medium is empty';
            if ($tier === '') {
                $errors[] = 'tier is empty';
            } elseif (!in_array($tier, array('1', '2', '3', '4'))) {
                $errors[] = 'tier must be 1-4';
            }

            // Validate optional fields
            $starting_bid = $get('starting_bid');
            if ($starting_bid !== '' && (!is_numeric($starting_bid) || floatval($starting_bid) < 0)) {
                $errors[] = 'starting_bid must be a non-negative number';
            }

            if (!empty($errors)) {
                $summary['errors']++;
                $row_results[] = array(
                    'row' => $row_num,
                    'art_id' => $art_id,
                    'status' => 'error',
                    'message' => implode('; ', $errors),
                );
                continue;
            }

            // Build data array with defaults
            $data = array(
                'art_id'        => $art_id,
                'title'         => $title,
                'artist'        => $artist,
                'medium'        => $medium,
                'dimensions'    => $get('dimensions'),
                'description'   => $get('description'),
                'starting_bid'  => $starting_bid !== '' ? floatval($starting_bid) : 0.00,
                'tier'          => intval($tier),
                'auction_start' => $auction_start,
                'auction_end'   => $auction_end,
                'show_end_time' => 0,
                'status'        => 'draft',
                'force_status'  => true,
            );

            // Check for existing piece
            $existing = $art_model->get_by_art_id($art_id);

            if ($existing) {
                if ($update_existing) {
                    unset($data['art_id']); // Don't update art_id itself
                    $result = $art_model->update($existing->id, $data);
                    if ($result !== false) {
                        $summary['updated']++;
                        $row_results[] = array(
                            'row' => $row_num,
                            'art_id' => $art_id,
                            'status' => 'updated',
                            'message' => __('Updated existing piece.', 'art-in-heaven'),
                        );
                    } else {
                        $summary['errors']++;
                        $row_results[] = array(
                            'row' => $row_num,
                            'art_id' => $art_id,
                            'status' => 'error',
                            'message' => __('Failed to update piece.', 'art-in-heaven'),
                        );
                    }
                } else {
                    $summary['skipped']++;
                    $row_results[] = array(
                        'row' => $row_num,
                        'art_id' => $art_id,
                        'status' => 'skipped',
                        'message' => __('Art ID already exists.', 'art-in-heaven'),
                    );
                }
            } else {
                $result = $art_model->create($data);
                if ($result) {
                    $summary['created']++;
                    $row_results[] = array(
                        'row' => $row_num,
                        'art_id' => $art_id,
                        'status' => 'created',
                        'message' => __('Created successfully.', 'art-in-heaven'),
                    );
                } else {
                    $summary['errors']++;
                    $row_results[] = array(
                        'row' => $row_num,
                        'art_id' => $art_id,
                        'status' => 'error',
                        'message' => __('Failed to create piece.', 'art-in-heaven'),
                    );
                }
            }
        }

        wp_send_json_success(array(
            'summary' => $summary,
            'rows'    => $row_results,
        ));
    }

    public function admin_sync_bidders() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_bidders()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        $auth = AIH_Auth::get_instance();
        $result = $auth->sync_bidders_from_api();
        
        if ($result['success']) {
            wp_send_json_success($result);
        }
        wp_send_json_error($result);
    }
    
    public function admin_cleanup_tables() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        
        global $wpdb;
        $year = AIH_Database::get_auction_year();
        $table = $wpdb->prefix . $year . '_Bidders';
        
        $messages = array();

        // Allowlist of column names that may appear in SQL statements
        $allowed_columns = array('email', 'first_name', 'last_name', 'phone', 'email_primary', 'name_first', 'name_last', 'phone_mobile');

        // Check which columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
        $existing_columns = array();
        foreach ($columns as $col) {
            // Only track columns that are in our allowlist
            if (in_array($col->Field, $allowed_columns, true)) {
                $existing_columns[] = $col->Field;
            }
        }

        // Map old columns to new columns
        $column_map = array(
            'email' => 'email_primary',
            'first_name' => 'name_first',
            'last_name' => 'name_last',
            'phone' => 'phone_mobile'
        );

        // Migrate data from old to new columns
        foreach ($column_map as $old_col => $new_col) {
            // Validate both column names are in the allowlist
            if (!in_array($old_col, $allowed_columns, true) || !in_array($new_col, $allowed_columns, true)) {
                continue;
            }
            if (in_array($old_col, $existing_columns) && in_array($new_col, $existing_columns)) {
                $migrated = $wpdb->query("UPDATE `{$table}` SET `{$new_col}` = `{$old_col}` WHERE (`{$new_col}` IS NULL OR `{$new_col}` = '') AND `{$old_col}` IS NOT NULL AND `{$old_col}` != ''");
                if ($migrated === false) {
                    error_log('[AIH] cleanup_tables migration error: ' . $wpdb->last_error);
                    $messages[] = sprintf(__('Error migrating %s.', 'art-in-heaven'), $old_col);
                    continue;
                }
                if ($migrated > 0) {
                    $messages[] = "Migrated $migrated records from $old_col to $new_col";
                }
            }
        }

        // Drop old columns
        foreach (array_keys($column_map) as $old_col) {
            // Validate column name is in the allowlist before using in SQL
            if (!in_array($old_col, $allowed_columns, true)) {
                continue;
            }
            if (in_array($old_col, $existing_columns)) {
                $wpdb->query("ALTER TABLE `{$table}` DROP COLUMN `{$old_col}`");
                $messages[] = "Dropped old column: $old_col";
            }
        }
        
        if (empty($messages)) {
            $messages[] = "No cleanup needed - table is already up to date";
        }
        
        wp_send_json_success(array('message' => implode('. ', $messages)));
    }
    
    /**
     * Delete all data from all database tables
     */
    public function admin_purge_data() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));

        global $wpdb;

        $tables = array(
            'art_pieces', 'bids', 'favorites', 'bidders', 'registrants',
            'orders', 'order_items', 'audit_log', 'art_images', 'pushpay_transactions'
        );

        // Audit log before purge (since audit_log table itself will be truncated)
        AIH_Database::log_audit('data_purged', array(
            'object_type' => 'system',
            'details' => array('tables' => $tables),
        ));

        $cleared = array();
        foreach ($tables as $key) {
            $table = AIH_Database::get_table($key);
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            if ($count > 0) {
                $truncate_result = $wpdb->query("TRUNCATE TABLE $table");
                if ($truncate_result === false) {
                    error_log('[AIH] clear_tables error on ' . $key . ': ' . $wpdb->last_error);
                    $cleared[] = "$key (" . __('error', 'art-in-heaven') . ")";
                } else {
                    $cleared[] = "$key ($count rows)";
                }
            }
        }

        if (empty($cleared)) {
            wp_send_json_success(array('message' => 'All tables were already empty.'));
        } else {
            wp_send_json_success(array('message' => 'Deleted data from: ' . implode(', ', $cleared)));
        }
    }

    // ========== HELPERS ==========

    /**
     * Format art piece for AJAX response
     * Uses consolidated AIH_Template_Helper::format_art_piece()
     */
    private function format_art_piece($piece, $bidder_id = null, $full = false, $batch_data = null) {
        return AIH_Template_Helper::format_art_piece($piece, $bidder_id, $full, true, $batch_data);
    }
    
    // ========== MULTIPLE IMAGES ==========
    
    /**
     * Add image to art piece
     */
    public function admin_add_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$art_piece_id || !$image_id) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'art-in-heaven') . ' Art ID: ' . $art_piece_id . ', Image ID: ' . $image_id));
        }
        
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            wp_send_json_error(array('message' => __('Invalid image attachment.', 'art-in-heaven')));
        }
        
        // Check if table exists, if not create it
        global $wpdb;
        $table = AIH_Database::get_table('art_images');
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            // Try to create the table
            AIH_Database::create_tables();
            // Check again
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                wp_send_json_error(array('message' => __('Database table not found. Please go to Settings and click "Recreate Tables".', 'art-in-heaven')));
            }
        }
        
        // Process watermark
        $watermark = new AIH_Watermark();
        $watermarked_url = $watermark->process_upload($image_id);
        if (!$watermarked_url) {
            $watermarked_url = $image_url; // Fallback to original
        }
        
        $images_handler = new AIH_Art_Images();
        $is_primary = isset($_POST['is_primary']) && $_POST['is_primary'] === '1';
        
        $result = $images_handler->add_image($art_piece_id, $image_id, $image_url, $watermarked_url, $is_primary);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Image added successfully.',
                'image_record_id' => $result,
                'image_url' => $image_url,
                'watermarked_url' => $watermarked_url
            ));
        } else {
            global $wpdb;
            error_log('[AIH] admin_add_image DB error: ' . $wpdb->last_error);
            wp_send_json_error(array('message' => __('Failed to add image. Please try again.', 'art-in-heaven')));
        }
    }
    
    /**
     * Remove image from art piece
     */
    public function admin_remove_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $image_record_id = intval($_POST['image_record_id'] ?? 0);
        
        if (!$image_record_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AIH Remove Image: Missing image_record_id');
            }
            wp_send_json_error(array('message' => __('Missing image ID.', 'art-in-heaven')));
        }

        // Get art_piece_id before removal for returning updated data
        global $wpdb;
        $images_table = AIH_Database::get_table('art_images');
        $image_record = $wpdb->get_row($wpdb->prepare(
            "SELECT art_piece_id FROM {$images_table} WHERE id = %d",
            $image_record_id
        ));
        
        $art_piece_id = $image_record ? $image_record->art_piece_id : 0;
        
        $images_handler = new AIH_Art_Images();
        $result = $images_handler->remove_image($image_record_id);
        
        if ($result) {
            // Get remaining images for this art piece
            $remaining_images = $images_handler->get_images($art_piece_id);
            
            // Get updated art piece data
            $art_model = new AIH_Art_Piece();
            $updated_art = $art_model->get($art_piece_id);
            
            wp_send_json_success(array(
                'message' => 'Image removed successfully.',
                'remaining_count' => count($remaining_images),
                'remaining_images' => $remaining_images,
                'art_piece' => array(
                    'image_url' => $updated_art ? $updated_art->image_url : '',
                    'watermarked_url' => $updated_art ? $updated_art->watermarked_url : ''
                ),
                'reload' => count($remaining_images) == 0 // Suggest reload if no images left
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to remove image. It may have already been deleted.', 'art-in-heaven')));
        }
    }
    
    /**
     * Set image as primary
     */
    public function admin_set_primary_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $image_record_id = intval($_POST['image_record_id'] ?? 0);
        
        if (!$image_record_id) {
            wp_send_json_error(array('message' => __('Missing image ID.', 'art-in-heaven')));
        }
        
        $images_handler = new AIH_Art_Images();
        $result = $images_handler->set_primary($image_record_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Primary image updated.'));
        } else {
            wp_send_json_error(array('message' => __('Failed to update primary image.', 'art-in-heaven')));
        }
    }
    
    /**
     * Reorder images
     */
    public function admin_reorder_images() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $image_ids = $_POST['image_ids'] ?? array();
        
        if (empty($image_ids) || !is_array($image_ids)) {
            wp_send_json_error(array('message' => __('Missing image IDs.', 'art-in-heaven')));
        }
        
        $images_handler = new AIH_Art_Images();
        $result = $images_handler->update_order(array_map('intval', $image_ids));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Order updated.'));
        } else {
            wp_send_json_error(array('message' => __('Failed to update order.', 'art-in-heaven')));
        }
    }
    
    /**
     * Get images for art piece
     */
    public function admin_get_images() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        
        if (!$art_piece_id) {
            wp_send_json_error(array('message' => __('Missing art piece ID.', 'art-in-heaven')));
        }
        
        $images_handler = new AIH_Art_Images();
        $images = $images_handler->get_images($art_piece_id);
        
        wp_send_json_success(array('images' => $images));
    }
    
    /**
     * Set/clear the upload flag to disable intermediate image sizes
     */
    public function set_upload_flag() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $action = sanitize_text_field($_POST['flag_action'] ?? 'set');
        
        if ($action === 'set') {
            Art_In_Heaven::before_aih_upload();
            wp_send_json_success(array('message' => 'Upload flag set.'));
        } else {
            Art_In_Heaven::after_aih_upload();
            wp_send_json_success(array('message' => 'Upload flag cleared.'));
        }
    }
    
    /**
     * Test Pushpay API connection
     */
    public function admin_test_pushpay() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_settings()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $pushpay = AIH_Pushpay_API::get_instance();
        $result = $pushpay->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Discover Pushpay organization and merchant keys
     */
    public function admin_discover_pushpay_keys() {
        check_ajax_referer('aih_admin_nonce', 'nonce');

        if (!AIH_Roles::can_manage_settings()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }

        $pushpay = AIH_Pushpay_API::get_instance();
        $result = $pushpay->discover_keys();

        if ($result['success']) {
            $is_sandbox = get_option('aih_pushpay_sandbox', 0);
            $prefix = $is_sandbox ? 'aih_pushpay_sandbox_' : 'aih_pushpay_';

            // If a specific org index was selected, apply that one
            if (isset($_POST['selected_org_index']) && !empty($result['organizations'])) {
                $idx = intval($_POST['selected_org_index']);
                if (isset($result['organizations'][$idx])) {
                    $org = $result['organizations'][$idx];
                    update_option($prefix . 'organization_key', $org['key']);
                    $result['applied_org'] = $org['key'];

                    // Apply selected merchant if specified, otherwise first merchant
                    $merchant_idx = isset($_POST['selected_merchant_index']) ? intval($_POST['selected_merchant_index']) : 0;
                    if (!empty($org['merchants']) && isset($org['merchants'][$merchant_idx])) {
                        update_option($prefix . 'merchant_key', $org['merchants'][$merchant_idx]['key']);
                        $result['applied_merchant'] = $org['merchants'][$merchant_idx]['key'];
                        if (!empty($org['merchants'][$merchant_idx]['handle'])) {
                            update_option($prefix . 'merchant_handle', $org['merchants'][$merchant_idx]['handle']);
                        }
                    }

                    delete_transient('aih_pushpay_token');
                }
            }
            // Auto-apply only if exactly one org found
            elseif (isset($_POST['auto_apply']) && $_POST['auto_apply'] === '1' && !empty($result['organizations']) && count($result['organizations']) === 1) {
                $org = $result['organizations'][0];
                update_option($prefix . 'organization_key', $org['key']);
                $result['applied_org'] = $org['key'];

                if (!empty($org['merchants']) && count($org['merchants']) === 1) {
                    update_option($prefix . 'merchant_key', $org['merchants'][0]['key']);
                    $result['applied_merchant'] = $org['merchants'][0]['key'];
                    if (!empty($org['merchants'][0]['handle'])) {
                        update_option($prefix . 'merchant_handle', $org['merchants'][0]['handle']);
                    }
                }

                delete_transient('aih_pushpay_token');
            }

            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Sync Pushpay transactions
     */
    public function admin_sync_pushpay() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_view_financial()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        // Increase execution time
        @set_time_limit(180);
        
        $pushpay = AIH_Pushpay_API::get_instance();
        $result = $pushpay->sync_payments(30); // Last 30 days
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Manually match a transaction to an order
     */
    public function admin_match_transaction() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_view_financial()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$transaction_id || !$order_id) {
            wp_send_json_error(array('message' => __('Missing transaction or order ID.', 'art-in-heaven')));
        }
        
        $pushpay = AIH_Pushpay_API::get_instance();
        $result = $pushpay->match_transaction_to_order($transaction_id, $order_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get transaction details
     */
    public function admin_get_transaction_details() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_view_financial()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => __('Invalid transaction ID.', 'art-in-heaven')));
        }
        
        global $wpdb;
        $transactions_table = AIH_Database::get_table('pushpay_transactions');
        $orders_table = AIH_Database::get_table('orders');
        
        $txn = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, o.order_number, o.bidder_id 
             FROM {$transactions_table} t 
             LEFT JOIN {$orders_table} o ON t.order_id = o.id 
             WHERE t.id = %d",
            $id
        ));
        
        if (!$txn) {
            wp_send_json_error(array('message' => __('Transaction not found.', 'art-in-heaven')));
        }
        
        // Build HTML for modal
        $html = '<div class="aih-txn-details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('PushPay ID', 'art-in-heaven') . '</label><div class="value"><code style="font-size: 11px; word-break: break-all;">' . esc_html($txn->pushpay_id) . '</code></div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Status', 'art-in-heaven') . '</label><div class="value"><span class="aih-status-badge ' . strtolower($txn->status) . '">' . esc_html($txn->status) . '</span></div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Amount', 'art-in-heaven') . '</label><div class="value"><strong style="font-size: 18px; color: #4a7c59;">$' . number_format($txn->amount, 2) . '</strong> ' . esc_html($txn->currency) . '</div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Payment Date', 'art-in-heaven') . '</label><div class="value">' . ($txn->payment_date ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($txn->payment_date)) : '—') . '</div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Payer Name', 'art-in-heaven') . '</label><div class="value">' . esc_html($txn->payer_name ?: '—') . '</div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Payer Email', 'art-in-heaven') . '</label><div class="value">' . esc_html($txn->payer_email ?: '—') . '</div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Fund', 'art-in-heaven') . '</label><div class="value">' . esc_html($txn->fund ?: '—') . '</div></div>';
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Reference', 'art-in-heaven') . '</label><div class="value">' . esc_html($txn->reference ?: '—') . '</div></div>';
        
        if ($txn->order_number) {
            $html .= '<div class="aih-txn-detail"><label>' . __('Matched Order', 'art-in-heaven') . '</label><div class="value"><a href="' . admin_url('admin.php?page=art-in-heaven-orders&search=' . urlencode($txn->order_number)) . '" style="color: #b8956b; font-weight: 600;">' . esc_html($txn->order_number) . '</a></div></div>';
        }
        
        $html .= '<div class="aih-txn-detail"><label>' . __('Synced At', 'art-in-heaven') . '</label><div class="value">' . ($txn->synced_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($txn->synced_at)) : '—') . '</div></div>';
        
        $html .= '</div>';
        
        // Notes
        if ($txn->notes) {
            $html .= '<div class="aih-txn-detail" style="margin-top: 20px;"><label>' . __('Notes', 'art-in-heaven') . '</label><div class="value">' . esc_html($txn->notes) . '</div></div>';
        }
        
        // Raw data (collapsed by default)
        if ($txn->raw_data) {
            $raw = json_decode($txn->raw_data, true);
            $html .= '<div style="margin-top: 20px;">';
            $html .= '<details><summary style="cursor: pointer; font-weight: 600; margin-bottom: 10px;">' . __('Raw API Data', 'art-in-heaven') . '</summary>';
            $html .= '<div class="aih-txn-raw">' . esc_html(json_encode($raw, JSON_PRETTY_PRINT)) . '</div>';
            $html .= '</details></div>';
        }
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Regenerate all watermarks
     */
    public function admin_regenerate_watermarks() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }
        
        // Increase execution time and memory
        if (function_exists('set_time_limit')) {
            set_time_limit(600);
        }
        @ini_set('memory_limit', '512M');
        
        global $wpdb;
        $images_table = AIH_Database::get_table('art_images');
        $art_table = AIH_Database::get_table('art_pieces');
        
        // Get all images from art_images table (bounded to prevent runaway queries)
        $images = $wpdb->get_results("SELECT * FROM {$images_table} LIMIT 5000");

        // Also get images directly from art_pieces that might not be in art_images (bounded)
        $art_pieces = $wpdb->get_results("SELECT id, image_id, image_url, watermarked_url FROM {$art_table} WHERE image_id > 0 LIMIT 5000");
        
        $watermark = new AIH_Watermark();
        if (!$watermark->is_available()) {
            wp_send_json_error(array('message' => __('GD library not available for watermarking.', 'art-in-heaven')));
        }
        
        $success_count = 0;
        $error_count = 0;
        $skipped_count = 0;
        $error_details = array();
        $upload_dir = wp_upload_dir();

        // Collect batch updates to reduce individual DB writes
        $batch_images_updates = array(); // id => watermarked_url
        $batch_art_updates = array();    // art_piece_id => watermarked_url

        // Process art_images table
        if ($images) {
            foreach ($images as $image) {
                // Clear any previous image from memory
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                $file_path = get_attached_file($image->image_id);

                if (!$file_path || !file_exists($file_path)) {
                    $error_count++;
                    $error_details[] = "Image ID {$image->image_id}: File not found";
                    continue;
                }

                // Check file size - skip very large files (over 10MB)
                $file_size = @filesize($file_path);
                if ($file_size > 10 * 1024 * 1024) {
                    $skipped_count++;
                    $error_details[] = "Image ID {$image->image_id}: File too large (" . round($file_size / 1024 / 1024, 1) . "MB)";
                    continue;
                }

                // Delete existing watermarked file and responsive variants
                if (!empty($image->watermarked_url)) {
                    AIH_Image_Optimizer::cleanup_variants($image->watermarked_url);
                    $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image->watermarked_url);
                    if (file_exists($watermarked_path)) {
                        @unlink($watermarked_path);
                    }
                }

                // Regenerate watermark (also generates responsive variants)
                try {
                    $new_watermark_url = $watermark->process_upload($image->image_id);

                    if ($new_watermark_url) {
                        $batch_images_updates[$image->id] = $new_watermark_url;

                        // If primary, queue art_pieces update too
                        if ($image->is_primary) {
                            $batch_art_updates[$image->art_piece_id] = $new_watermark_url;
                        }

                        $success_count++;
                    } else {
                        $error_count++;
                        $error_details[] = "Image ID {$image->image_id}: Watermark generation failed";
                    }
                } catch (Exception $e) {
                    $error_count++;
                    $error_details[] = "Image ID {$image->image_id}: " . $e->getMessage();
                }
            }
        }

        // Build lookup of already-processed art pieces from art_images
        $processed_art_ids = array();
        if ($images) {
            foreach ($images as $img) {
                if ($img->is_primary) {
                    $processed_art_ids[$img->art_piece_id] = true;
                }
            }
        }

        // Also process art_pieces directly (for any not in art_images)
        if ($art_pieces) {
            foreach ($art_pieces as $piece) {
                // Clear memory
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }

                // Check if already processed via art_images (O(1) lookup instead of nested loop)
                if (isset($processed_art_ids[$piece->id])) {
                    continue;
                }

                $file_path = get_attached_file($piece->image_id);

                if (!$file_path || !file_exists($file_path)) {
                    continue; // Skip silently for art_pieces direct
                }

                // Check file size
                $file_size = @filesize($file_path);
                if ($file_size > 10 * 1024 * 1024) {
                    $skipped_count++;
                    continue;
                }

                // Delete existing watermarked file and responsive variants
                if (!empty($piece->watermarked_url)) {
                    AIH_Image_Optimizer::cleanup_variants($piece->watermarked_url);
                    $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $piece->watermarked_url);
                    if (file_exists($watermarked_path)) {
                        @unlink($watermarked_path);
                    }
                }

                // Regenerate watermark (also generates responsive variants)
                try {
                    $new_watermark_url = $watermark->process_upload($piece->image_id);

                    if ($new_watermark_url) {
                        $batch_art_updates[$piece->id] = $new_watermark_url;
                        $success_count++;
                    }
                } catch (Exception $e) {
                    // Skip silently
                }
            }
        }

        // Batch write all art_images updates using CASE statement
        if (!empty($batch_images_updates)) {
            $case_sql = "UPDATE {$images_table} SET watermarked_url = CASE id";
            $ids = array();
            foreach ($batch_images_updates as $id => $url) {
                $case_sql .= $wpdb->prepare(" WHEN %d THEN %s", $id, $url);
                $ids[] = intval($id);
            }
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $case_sql .= " END WHERE id IN ($placeholders)";
            $batch_result = $wpdb->query($wpdb->prepare($case_sql, $ids));
            if ($batch_result === false) {
                error_log('AIH: Batch image watermark update failed: ' . $wpdb->last_error);
            }
        }

        // Batch write all art_pieces updates using CASE statement
        if (!empty($batch_art_updates)) {
            $case_sql = "UPDATE {$art_table} SET watermarked_url = CASE id";
            $ids = array();
            foreach ($batch_art_updates as $id => $url) {
                $case_sql .= $wpdb->prepare(" WHEN %d THEN %s", $id, $url);
                $ids[] = intval($id);
            }
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $case_sql .= " END WHERE id IN ($placeholders)";
            $batch_result = $wpdb->query($wpdb->prepare($case_sql, $ids));
            if ($batch_result === false) {
                error_log('AIH: Batch art piece watermark update failed: ' . $wpdb->last_error);
            }
        }

        if ($success_count == 0 && $error_count == 0 && $skipped_count == 0) {
            wp_send_json_error(array('message' => __('No images found to process.', 'art-in-heaven')));
        }
        
        $message = sprintf(__('Regenerated %d watermarks successfully.', 'art-in-heaven'), $success_count);
        
        if ($skipped_count > 0) {
            $message .= ' ' . sprintf(__('%d skipped (too large).', 'art-in-heaven'), $skipped_count);
        }
        
        if ($error_count > 0) {
            $message .= ' ' . sprintf(__('%d failed.', 'art-in-heaven'), $error_count);
            error_log('AIH Watermark Errors: ' . implode('; ', array_slice($error_details, 0, 10)));
        }
        
        wp_send_json_success(array('message' => $message, 'success' => $success_count, 'errors' => $error_count, 'skipped' => $skipped_count));
    }

    // ========== LOG VIEWER ==========

    /**
     * Resolve the active PHP/WordPress error log file path.
     *
     * Checks, in order:
     *  1. WP_DEBUG_LOG constant (when set to a string path)
     *  2. wp-content/debug.log (WP default when WP_DEBUG_LOG === true)
     *  3. php.ini error_log directive
     *
     * @return string|false  Absolute path to a readable log file, or false.
     */
    private function resolve_log_file() {
        // 1. WP_DEBUG_LOG set to a custom path
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '' && WP_DEBUG_LOG !== '1') {
            if (file_exists(WP_DEBUG_LOG) && is_readable(WP_DEBUG_LOG)) {
                return WP_DEBUG_LOG;
            }
        }

        // 2. Default WP location
        $wp_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($wp_log) && is_readable($wp_log)) {
            return $wp_log;
        }

        // 3. php.ini error_log directive
        $php_log = ini_get('error_log');
        if ($php_log && file_exists($php_log) && is_readable($php_log)) {
            return $php_log;
        }

        return false;
    }

    public function admin_get_logs() {
        check_ajax_referer('aih_admin_nonce', 'nonce');

        if (!AIH_Roles::can_manage_settings()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }

        $log_file = $this->resolve_log_file();

        if (!$log_file) {
            // Build a helpful diagnostic message
            $hints = array();
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                $hints[] = 'WP_DEBUG is not enabled';
            }
            if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                $hints[] = 'WP_DEBUG_LOG is not enabled';
            }
            $default_path = WP_CONTENT_DIR . '/debug.log';
            if (!file_exists($default_path)) {
                $hints[] = $default_path . ' does not exist';
            } elseif (!is_readable($default_path)) {
                $hints[] = $default_path . ' exists but is not readable';
            }
            $php_log = ini_get('error_log');
            if ($php_log && file_exists($php_log) && !is_readable($php_log)) {
                $hints[] = $php_log . ' exists but is not readable';
            }

            wp_send_json_success(array(
                'entries'   => array(),
                'total'     => 0,
                'file_size' => '0 B',
                'log_path'  => '',
                'hints'     => $hints,
            ));
        }

        $lines  = isset($_POST['lines']) ? absint($_POST['lines']) : 200;
        $lines  = min($lines, 1000);
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'aih';

        $file_size = filesize($log_file);
        $size_label = size_format($file_size);

        // Read last N lines efficiently
        $all_lines = array();
        $fp = fopen($log_file, 'r');
        if ($fp) {
            if ($file_size < 2 * 1024 * 1024) {
                $content = fread($fp, $file_size);
                $all_lines = $content !== false && $content !== '' ? explode("\n", $content) : array();
            } else {
                $chunk_size = 8192;
                $buffer = '';
                fseek($fp, 0, SEEK_END);
                $pos = ftell($fp);

                while ($pos > 0 && count($all_lines) < $lines + 100) {
                    $read_size = min($chunk_size, $pos);
                    $pos -= $read_size;
                    fseek($fp, $pos);
                    $buffer = fread($fp, $read_size) . $buffer;
                    $all_lines = explode("\n", $buffer);
                }
            }
            fclose($fp);
        }

        // Remove empty trailing line
        if (!empty($all_lines) && $all_lines[count($all_lines) - 1] === '') {
            array_pop($all_lines);
        }

        $total = count($all_lines);

        // Filter if requested
        if ($filter === 'aih') {
            $all_lines = array_values(array_filter($all_lines, function($line) {
                $lower = strtolower($line);
                return strpos($lower, 'aih') !== false || strpos($lower, 'art in heaven') !== false;
            }));
        }

        // Take last N lines and reverse (newest first)
        $all_lines = array_slice($all_lines, -$lines);
        $all_lines = array_reverse($all_lines);

        wp_send_json_success(array(
            'entries'   => $all_lines,
            'total'     => $total,
            'file_size' => $size_label,
            'log_path'  => basename($log_file),
            'hints'     => array(),
        ));
    }

    public function admin_clear_logs() {
        check_ajax_referer('aih_admin_nonce', 'nonce');

        if (!AIH_Roles::can_manage_settings()) {
            wp_send_json_error(array('message' => __('Permission denied.', 'art-in-heaven')));
        }

        $log_file = $this->resolve_log_file();

        if (!$log_file) {
            wp_send_json_error(array('message' => __('No log file found.', 'art-in-heaven')));
        }

        $fp = fopen($log_file, 'w');
        if ($fp) {
            fclose($fp);
        } else {
            wp_send_json_error(array('message' => __('Could not clear log file.', 'art-in-heaven')));
        }

        wp_send_json_success(array('message' => __('Log file cleared.', 'art-in-heaven')));
    }

    // ========== PUSH NOTIFICATIONS ==========

    /**
     * Save a push subscription for the current bidder
     */
    public function push_subscribe() {
        check_ajax_referer('aih_nonce', 'nonce');

        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $bidder_id = $auth->get_current_bidder_id();
        $endpoint  = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';
        $p256dh    = isset($_POST['p256dh']) ? sanitize_text_field($_POST['p256dh']) : '';
        $auth_key  = isset($_POST['auth']) ? sanitize_text_field($_POST['auth']) : '';

        if (empty($endpoint) || empty($p256dh) || empty($auth_key)) {
            wp_send_json_error(array('message' => 'Missing subscription data'));
        }

        $result = AIH_Push::save_subscription($bidder_id, $endpoint, $p256dh, $auth_key);
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to save subscription'));
        }
    }

    /**
     * Remove a push subscription by endpoint
     */
    public function push_unsubscribe() {
        check_ajax_referer('aih_nonce', 'nonce');

        $endpoint = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';
        if (empty($endpoint)) {
            wp_send_json_error(array('message' => 'Missing endpoint'));
        }

        AIH_Push::delete_subscription($endpoint);
        wp_send_json_success();
    }

    /**
     * Return and clear pending outbid events for the current bidder (polling fallback)
     */
    public function check_outbid() {
        check_ajax_referer('aih_nonce', 'nonce');

        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $bidder_id = $auth->get_current_bidder_id();
        $events    = AIH_Push::consume_outbid_events($bidder_id);

        wp_send_json_success($events);
    }

    /**
     * Lightweight polling endpoint for live bid status updates.
     * Returns winning status, min bid, has_bids flag, and auction status
     * for each requested art piece.
     *
     * Results are cached per-bidder for 3 seconds to reduce DB load
     * when many users are polling simultaneously.
     */
    public function poll_status() {
        check_ajax_referer('aih_nonce', 'nonce');

        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $bidder_id = $auth->get_current_bidder_id();

        $ids = isset($_POST['art_piece_ids']) ? array_map('intval', (array) $_POST['art_piece_ids']) : array();
        $ids = array_filter($ids);
        if (empty($ids)) {
            wp_send_json_error(array('message' => 'No art piece IDs provided'));
        }

        // Cap to prevent abuse
        $ids = array_slice($ids, 0, 200);
        sort($ids);

        // Check cache first (3 second TTL per bidder + ID set)
        $cache_key = 'poll_' . md5($bidder_id . '_' . implode(',', $ids));
        $cached = wp_cache_get($cache_key, 'aih_poll');
        if ($cached !== false) {
            wp_send_json_success($cached);
        }

        global $wpdb;
        $now = current_time('mysql');
        $art_table = AIH_Database::get_table('art_pieces');
        $bids_table = AIH_Database::get_table('bids');

        // Batch fetch art piece data
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $art_pieces = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, auction_end FROM $art_table WHERE id IN ($placeholders)",
            ...$ids
        ), OBJECT_K);

        // Batch fetch highest bids per piece
        $highest_bids = $wpdb->get_results($wpdb->prepare(
            "SELECT art_piece_id, MAX(bid_amount) as highest
             FROM $bids_table
             WHERE art_piece_id IN ($placeholders) AND (bid_status = 'valid' OR bid_status IS NULL)
             GROUP BY art_piece_id",
            ...$ids
        ), OBJECT_K);

        // Batch fetch winning status for this bidder
        $winning_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT art_piece_id FROM $bids_table
             WHERE art_piece_id IN ($placeholders) AND bidder_id = %s AND is_winning = 1",
            ...array_merge($ids, array($bidder_id))
        ));
        $winning_ids = array();
        foreach ($winning_rows as $row) {
            $winning_ids[$row->art_piece_id] = true;
        }

        // Batch fetch which pieces this bidder has bid on
        $bid_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT art_piece_id FROM $bids_table
             WHERE art_piece_id IN ($placeholders) AND bidder_id = %s AND bid_status = 'valid'",
            ...array_merge($ids, array($bidder_id))
        ));
        $has_bid_ids = array();
        foreach ($bid_rows as $row) {
            $has_bid_ids[$row->art_piece_id] = true;
        }

        $items = array();
        foreach ($ids as $id) {
            $piece = isset($art_pieces[$id]) ? $art_pieces[$id] : null;
            $highest = isset($highest_bids[$id]) ? floatval($highest_bids[$id]->highest) : 0;
            $has_bids = $highest > 0;
            $is_winning = isset($winning_ids[$id]);

            $status = 'active';
            if ($piece) {
                if ($piece->status === 'ended' || (!empty($piece->auction_end) && $piece->auction_end <= $now)) {
                    $status = 'ended';
                }
            }

            $items[$id] = array(
                'is_winning' => $is_winning,
                'has_bids'   => $has_bids,
                'status'     => $status,
                'has_bid'    => isset($has_bid_ids[$id]),
            );
        }

        $result = array(
            'items'      => $items,
            'cart_count'  => 0,
        );

        // Cache for 3 seconds
        wp_cache_set($cache_key, $result, 'aih_poll', 3);

        wp_send_json_success($result);
    }
}
