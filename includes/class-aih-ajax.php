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
    }
    
    // ========== AUTH ==========
    
    public function verify_confirmation_code() {
        check_ajax_referer('aih_nonce', 'nonce');
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        if (empty($code)) wp_send_json_error(array('message' => __('Please enter your confirmation code.', 'art-in-heaven')));
        
        $auth = AIH_Auth::get_instance();
        $result = $auth->verify_confirmation_code($code);
        
        if ($result['success']) {
            // Login using confirmation_code as bidder ID (NOT email)
            $auth->login_bidder($result['bidder']['confirmation_code']);
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
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        $bid_amount = floatval($_POST['bid_amount'] ?? 0);
        if (!$art_piece_id || $bid_amount <= 0) wp_send_json_error(array('message' => __('Invalid bid.', 'art-in-heaven')));
        
        // Ensure whole dollar amounts only
        $bid_amount = floor($bid_amount);
        if ($bid_amount < 1) wp_send_json_error(array('message' => __('Bid must be at least $1.', 'art-in-heaven')));
        
        $result = (new AIH_Bid())->place_bid($art_piece_id, $auth->get_current_bidder_id(), $bid_amount);
        if ($result['success']) wp_send_json_success($result);
        wp_send_json_error($result);
    }
    
    public function toggle_favorite() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        if (!$art_piece_id) wp_send_json_error(array('message' => 'Invalid.'));
        
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
        $pieces = (new AIH_Art_Piece())->get_all(array('status' => 'active', 'bidder_id' => $auth->get_current_bidder_id()));
        $data = array();
        foreach ($pieces as $p) $data[] = $this->format_art_piece($p, $auth->get_current_bidder_id());
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
        if (!$art_id) wp_send_json_error(array('message' => 'Invalid.'));

        $piece = (new AIH_Art_Piece())->get($art_id);
        if (!$piece) wp_send_json_error(array('message' => 'Not found.'));

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
        $results = (new AIH_Art_Piece())->get_all(array('search' => $search, 'status' => 'active', 'bidder_id' => $auth->get_current_bidder_id(), 'limit' => 20));
        $data = array();
        foreach ($results as $p) $data[] = $this->format_art_piece($p, $auth->get_current_bidder_id());
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
        
        $result = AIH_Checkout::get_instance()->create_order($auth->get_current_bidder_id());
        if ($result['success']) wp_send_json_success($result);
        wp_send_json_error($result);
    }
    
    public function get_pushpay_link() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('login_required' => true));

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number(sanitize_text_field($_POST['order_number'] ?? ''));
        if (!$order) wp_send_json_error(array('message' => 'Order not found.'));
        wp_send_json_success(array('pushpay_url' => $checkout->get_pushpay_payment_url($order)));
    }

    /**
     * Get order details for frontend display
     */
    public function get_order_details() {
        check_ajax_referer('aih_nonce', 'nonce');
        $auth = AIH_Auth::get_instance();
        if (!$auth->is_logged_in()) wp_send_json_error(array('message' => 'Session expired. Please refresh the page and sign in again.'));

        $order_number = sanitize_text_field($_POST['order_number'] ?? '');
        if (empty($order_number)) wp_send_json_error(array('message' => 'Order number required.'));

        $checkout = AIH_Checkout::get_instance();
        $order = $checkout->get_order_by_number($order_number);

        if (!$order) wp_send_json_error(array('message' => 'Order not found.'));

        // Verify this order belongs to the current bidder
        $current_bidder = $auth->get_current_bidder_id();
        if ($order->bidder_id != $current_bidder) {
            wp_send_json_error(array('message' => 'Order does not belong to this account.'));
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
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        
        $art_model = new AIH_Art_Piece();
        $watermark = new AIH_Watermark();
        
        // Get default times from settings
        $event_date = get_option('aih_event_date', '');
        $event_end_date = get_option('aih_event_end_date', '');
        $default_start = $event_date ? date('Y-m-d H:i:s', strtotime($event_date)) : current_time('mysql');
        $default_end = $event_end_date ? date('Y-m-d H:i:s', strtotime($event_end_date)) : '';
        
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
        }
        if (!empty($auction_end)) {
            $auction_end = str_replace('T', ' ', $auction_end);
            if (strlen($auction_end) === 16) {
                $auction_end .= ':00'; // Add seconds
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
            
            $result = $art_model->update($record_id, $data);
            $piece = $art_model->get($record_id);
            wp_send_json_success(array('message' => __('Updated successfully!', 'art-in-heaven'), 'id' => $record_id, 'art_id' => $piece->art_id, 'final_status' => $piece->status));
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
                $db_error = $wpdb->last_error;
                wp_send_json_error(array('message' => __('Failed to create art piece.', 'art-in-heaven') . ($db_error ? ' DB Error: ' . $db_error : '')));
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
            wp_send_json_success(array('message' => __('Created successfully!', 'art-in-heaven'), 'id' => $new_id, 'art_id' => $piece->art_id, 'final_status' => $piece->status));
        }
    }
    
    public function admin_delete_art() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        $art_id = intval($_POST['id'] ?? 0);
        if (!$art_id) wp_send_json_error(array('message' => 'Invalid.'));
        (new AIH_Art_Piece())->delete($art_id);
        wp_send_json_success(array('message' => 'Deleted.'));
    }
    
    public function admin_bulk_update_times() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $new_end_time = sanitize_text_field($_POST['new_end_time'] ?? '');
        if (empty($ids) || empty($new_end_time)) wp_send_json_error(array('message' => 'Missing data.'));
        (new AIH_Art_Piece())->bulk_update_end_times($ids, $new_end_time);
        wp_send_json_success(array('message' => count($ids) . ' items updated.'));
    }
    
    public function admin_bulk_update_start_times() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $new_start_time = sanitize_text_field($_POST['new_start_time'] ?? '');
        if (empty($ids) || empty($new_start_time)) wp_send_json_error(array('message' => 'Missing data.'));
        (new AIH_Art_Piece())->bulk_update_start_times($ids, $new_start_time);
        wp_send_json_success(array('message' => count($ids) . ' items updated.'));
    }
    
    public function admin_bulk_show_end_time() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        $ids = array_map('intval', $_POST['ids'] ?? array());
        $show = isset($_POST['show']) && $_POST['show'] == '1' ? 1 : 0;
        if (empty($ids)) wp_send_json_error(array('message' => 'No items selected.'));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');
        
        // Check if column exists, if not add it
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'show_end_time'",
            DB_NAME, $table
        ));
        
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD COLUMN `show_end_time` tinyint(1) DEFAULT 0 AFTER `auction_end`");
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET show_end_time = %d WHERE id IN ($placeholders)",
            array_merge(array($show), $ids)
        ));
        
        $action = $show ? __('revealed', 'art-in-heaven') : __('hidden', 'art-in-heaven');
        wp_send_json_success(array('message' => sprintf(__('End times %s for %d items.', 'art-in-heaven'), $action, count($ids))));
    }
    
    public function admin_toggle_end_time() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        
        $id = intval($_POST['id'] ?? 0);
        $show = isset($_POST['show']) && $_POST['show'] == '1' ? 1 : 0;
        
        if (!$id) wp_send_json_error(array('message' => 'Invalid item.'));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');
        
        // Check if column exists, if not run migration
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'show_end_time'",
            DB_NAME, $table
        ));
        
        if (!$column_exists) {
            // Add the column
            $wpdb->query("ALTER TABLE `" . esc_sql($table) . "` ADD COLUMN `show_end_time` tinyint(1) DEFAULT 0 AFTER `auction_end`");
        }
        
        $result = $wpdb->update($table, array('show_end_time' => $show), array('id' => $id), array('%d'), array('%d'));
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
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
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        
        $id = intval($_POST['id'] ?? 0);
        $field = sanitize_key($_POST['field'] ?? '');
        $raw_value = $_POST['value'] ?? '';
        // Default sanitization applied immediately; field-specific sanitization below
        $value = sanitize_text_field($raw_value);
        
        if (!$id || !$field) {
            wp_send_json_error(array('message' => 'Invalid request.'));
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
            wp_send_json_error(array('message' => 'Field not editable.'));
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
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
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
                            WHEN a.status = 'ended' THEN 'ended'
                            WHEN a.auction_end IS NOT NULL AND a.auction_end <= %s THEN 'ended'
                            WHEN a.auction_start IS NOT NULL AND a.auction_start > %s THEN 'upcoming'
                            ELSE 'active'
                        END as computed_status
                 FROM $table a
                 LEFT JOIN $bids_table b ON a.id = b.art_piece_id
                 LEFT JOIN $order_items_table oi ON a.id = oi.art_piece_id
                 LEFT JOIN $orders_table o ON oi.order_id = o.id
                 WHERE a.id = %d
                 GROUP BY a.id",
                $now, $now, $id
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
        if (!AIH_Roles::can_manage_settings()) wp_send_json_error(array('message' => 'Permission denied.'));
        $event_date = get_option('aih_event_date', '');
        if (empty($event_date)) wp_send_json_error(array('message' => 'Event date not set.'));
        
        global $wpdb;
        $table = AIH_Database::get_table('art_pieces');
        $updated = $wpdb->query($wpdb->prepare("UPDATE $table SET auction_start = %s WHERE status = 'active'", date('Y-m-d H:i:s', strtotime($event_date))));
        wp_send_json_success(array('message' => sprintf('%d art pieces updated.', $updated)));
    }
    
    public function admin_process_watermark() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        $image_id = intval($_POST['image_id'] ?? 0);
        if (!$image_id) wp_send_json_error(array('message' => 'Invalid.'));
        $url = (new AIH_Watermark())->process_upload($image_id);
        if ($url) wp_send_json_success(array('watermarked_url' => $url));
        wp_send_json_error(array('message' => 'Failed.'));
    }
    
    public function admin_get_stats() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) wp_send_json_error(array('message' => 'Permission denied.'));
        wp_send_json_success((new AIH_Art_Piece())->get_all_with_stats());
    }
    
    public function admin_update_payment() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_financial()) wp_send_json_error(array('message' => 'Permission denied.'));
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        if (!$order_id || !$status) wp_send_json_error(array('message' => 'Missing data.'));
        AIH_Checkout::get_instance()->update_payment_status($order_id, $status, sanitize_text_field($_POST['method'] ?? ''), sanitize_text_field($_POST['reference'] ?? ''), sanitize_textarea_field($_POST['notes'] ?? ''));
        wp_send_json_success(array('message' => 'Updated.'));
    }
    
    public function admin_delete_order() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_financial()) wp_send_json_error(array('message' => 'Permission denied.'));
        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) wp_send_json_error(array('message' => 'Invalid.'));
        AIH_Checkout::get_instance()->delete_order($order_id);
        wp_send_json_success(array('message' => 'Deleted.'));
    }
    
    /**
     * Delete a bid (admin only)
     */
    public function admin_delete_bid() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        
        if (!AIH_Roles::can_manage_auction()) {
            wp_send_json_error(array('message' => 'Permission denied. Only super admins can delete bids.'));
        }
        
        $bid_id = intval($_POST['bid_id'] ?? 0);
        if (!$bid_id) {
            wp_send_json_error(array('message' => 'Invalid bid ID.'));
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
            wp_send_json_error(array('message' => 'Bid not found.'));
        }
        
        // Store bidder_id for later check
        $bidder_id = $bid->bidder_id;
        
        // If this is the winning bid, we need to recalculate
        $was_winning = $bid->is_winning == 1;
        $art_piece_id = $bid->art_piece_id;
        
        // Delete the bid
        $deleted = $wpdb->delete($bids_table, array('id' => $bid_id), array('%d'));
        
        if (!$deleted) {
            wp_send_json_error(array('message' => 'Failed to delete bid.'));
        }
        
        // If this was the winning bid, set the next highest bid as winning
        if ($was_winning) {
            $next_highest = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$bids_table} WHERE art_piece_id = %d ORDER BY bid_amount DESC LIMIT 1",
                $art_piece_id
            ));
            
            if ($next_highest) {
                $wpdb->update(
                    $bids_table,
                    array('is_winning' => 1),
                    array('id' => $next_highest->id),
                    array('%d'),
                    array('%d')
                );
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
        
        if (!AIH_Roles::can_view_financial()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');
        $pickup_by = sanitize_text_field($_POST['pickup_by'] ?? '');
        $pickup_notes = sanitize_textarea_field($_POST['pickup_notes'] ?? '');
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID.'));
        }
        
        if (!in_array($status, array('pending', 'picked_up'))) {
            wp_send_json_error(array('message' => 'Invalid status.'));
        }
        
        // Require name when marking as picked up
        if ($status === 'picked_up' && empty($pickup_by)) {
            wp_send_json_error(array('message' => 'Name is required when marking as picked up.'));
        }
        
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        
        // Check order exists and is paid
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found.'));
        }
        
        if ($order->payment_status !== 'paid') {
            wp_send_json_error(array('message' => 'Order must be paid before marking as picked up.'));
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
            wp_send_json_error(array('message' => 'Database error.'));
        }
        
        wp_send_json_success(array(
            'message' => $status === 'picked_up' ? 'Marked as picked up.' : 'Pickup status reset.',
            'status' => $status
        ));
    }
    
    public function admin_test_api() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_settings()) wp_send_json_error(array('message' => 'Permission denied.'));
        $result = AIH_Auth::get_instance()->test_api_connection();
        if ($result['success']) wp_send_json_success($result);
        wp_send_json_error($result);
    }
    
    public function admin_create_tables() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => 'Permission denied.'));
        $year = intval($_POST['year'] ?? date('Y'));
        AIH_Database::create_tables($year);
        wp_send_json_success(array('message' => sprintf('Tables created for %d.', $year)));
    }
    
    public function admin_export_data() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_view_reports()) wp_send_json_error(array('message' => 'Permission denied.'));
        
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
    
    public function admin_sync_bidders() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_bidders()) wp_send_json_error(array('message' => 'Permission denied.'));
        
        $auth = AIH_Auth::get_instance();
        $result = $auth->sync_bidders_from_api();
        
        if ($result['success']) {
            wp_send_json_success($result);
        }
        wp_send_json_error($result);
    }
    
    public function admin_cleanup_tables() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => 'Permission denied.'));
        
        global $wpdb;
        $year = AIH_Database::get_auction_year();
        $table = $wpdb->prefix . $year . '_Bidders';
        
        $messages = array();
        
        // Check which columns exist
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
        $existing_columns = array();
        foreach ($columns as $col) {
            $existing_columns[] = $col->Field;
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
            if (in_array($old_col, $existing_columns) && in_array($new_col, $existing_columns)) {
                $migrated = $wpdb->query("UPDATE $table SET $new_col = $old_col WHERE ($new_col IS NULL OR $new_col = '') AND $old_col IS NOT NULL AND $old_col != ''");
                if ($migrated > 0) {
                    $messages[] = "Migrated $migrated records from $old_col to $new_col";
                }
            }
        }
        
        // Drop old columns
        foreach (array_keys($column_map) as $old_col) {
            if (in_array($old_col, $existing_columns)) {
                $wpdb->query("ALTER TABLE $table DROP COLUMN $old_col");
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
        if (!AIH_Roles::can_manage_auction()) wp_send_json_error(array('message' => 'Permission denied.'));

        global $wpdb;

        $tables = array(
            'art_pieces', 'bids', 'favorites', 'bidders', 'registrants',
            'orders', 'order_items', 'audit_log', 'art_images', 'pushpay_transactions'
        );

        $cleared = array();
        foreach ($tables as $key) {
            $table = AIH_Database::get_table($key);
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
            if ($count > 0) {
                $wpdb->query("TRUNCATE TABLE $table");
                $cleared[] = "$key ($count rows)";
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
    private function format_art_piece($piece, $bidder_id = null, $full = false) {
        return AIH_Template_Helper::format_art_piece($piece, $bidder_id, $full, true);
    }
    
    // ========== MULTIPLE IMAGES ==========
    
    /**
     * Add image to art piece
     */
    public function admin_add_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);
        
        if (!$art_piece_id || !$image_id) {
            wp_send_json_error(array('message' => 'Missing required fields. Art ID: ' . $art_piece_id . ', Image ID: ' . $image_id));
        }
        
        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            wp_send_json_error(array('message' => 'Invalid image attachment.'));
        }
        
        // Check if table exists, if not create it
        global $wpdb;
        $table = AIH_Database::get_table('art_images');
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$table_exists) {
            // Try to create the table
            AIH_Database::create_tables();
            // Check again
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$table_exists) {
                wp_send_json_error(array('message' => 'Database table not found. Please go to Settings and click "Recreate Tables".'));
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
            wp_send_json_error(array('message' => 'Failed to add image. DB Error: ' . $wpdb->last_error));
        }
    }
    
    /**
     * Remove image from art piece
     */
    public function admin_remove_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $image_record_id = intval($_POST['image_record_id'] ?? 0);
        
        if (!$image_record_id) {
            error_log('AIH Remove Image: Missing image_record_id. POST data: ' . print_r($_POST, true));
            wp_send_json_error(array('message' => 'Missing image ID.'));
        }
        
        error_log('AIH Remove Image: Attempting to remove image record ID: ' . $image_record_id);
        
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
            error_log('AIH Remove Image: Successfully removed image record ID: ' . $image_record_id);
            
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
            error_log('AIH Remove Image: Failed to remove image record ID: ' . $image_record_id);
            wp_send_json_error(array('message' => 'Failed to remove image. It may have already been deleted.'));
        }
    }
    
    /**
     * Set image as primary
     */
    public function admin_set_primary_image() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $image_record_id = intval($_POST['image_record_id'] ?? 0);
        
        if (!$image_record_id) {
            wp_send_json_error(array('message' => 'Missing image ID.'));
        }
        
        $images_handler = new AIH_Art_Images();
        $result = $images_handler->set_primary($image_record_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Primary image updated.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update primary image.'));
        }
    }
    
    /**
     * Reorder images
     */
    public function admin_reorder_images() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $image_ids = $_POST['image_ids'] ?? array();
        
        if (empty($image_ids) || !is_array($image_ids)) {
            wp_send_json_error(array('message' => 'Missing image IDs.'));
        }
        
        $images_handler = new AIH_Art_Images();
        $result = $images_handler->update_order(array_map('intval', $image_ids));
        
        if ($result) {
            wp_send_json_success(array('message' => 'Order updated.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update order.'));
        }
    }
    
    /**
     * Get images for art piece
     */
    public function admin_get_images() {
        check_ajax_referer('aih_admin_nonce', 'nonce');
        if (!AIH_Roles::can_manage_art()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $art_piece_id = intval($_POST['art_piece_id'] ?? 0);
        
        if (!$art_piece_id) {
            wp_send_json_error(array('message' => 'Missing art piece ID.'));
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
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        $order_id = intval($_POST['order_id'] ?? 0);
        
        if (!$transaction_id || !$order_id) {
            wp_send_json_error(array('message' => 'Missing transaction or order ID.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(array('message' => 'Invalid transaction ID.'));
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
            wp_send_json_error(array('message' => 'Transaction not found.'));
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
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        // Increase execution time and memory
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        global $wpdb;
        $images_table = AIH_Database::get_table('art_images');
        $art_table = AIH_Database::get_table('art_pieces');
        
        // Get all images from art_images table
        $images = $wpdb->get_results("SELECT * FROM {$images_table}");
        
        // Also get images directly from art_pieces that might not be in art_images
        $art_pieces = $wpdb->get_results("SELECT id, image_id, image_url, watermarked_url FROM {$art_table} WHERE image_id > 0");
        
        $watermark = new AIH_Watermark();
        if (!$watermark->is_available()) {
            wp_send_json_error(array('message' => 'GD library not available for watermarking.'));
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

                // Delete existing watermarked file
                if (!empty($image->watermarked_url)) {
                    $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image->watermarked_url);
                    if (file_exists($watermarked_path)) {
                        @unlink($watermarked_path);
                    }
                }

                // Regenerate watermark
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

                // Delete existing watermarked file
                if (!empty($piece->watermarked_url)) {
                    $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $piece->watermarked_url);
                    if (file_exists($watermarked_path)) {
                        @unlink($watermarked_path);
                    }
                }

                // Regenerate watermark
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
            $wpdb->query($wpdb->prepare($case_sql, $ids));
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
            $wpdb->query($wpdb->prepare($case_sql, $ids));
        }
        
        if ($success_count == 0 && $error_count == 0 && $skipped_count == 0) {
            wp_send_json_error(array('message' => 'No images found to process.'));
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
}
