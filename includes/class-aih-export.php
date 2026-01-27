<?php
/**
 * Export Class
 * 
 * Handles data export functionality including:
 * - GDPR personal data export
 * - GDPR personal data erasure
 * - CSV/Excel exports for admin
 * 
 * @package ArtInHeaven
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Export {
    
    /**
     * Export personal data for GDPR
     * 
     * @param string $email_address The user's email address
     * @param int    $page          Page number
     * @return array
     */
    public static function export_personal_data($email_address, $page = 1) {
        $export_items = array();
        
        global $wpdb;
        
        // Export bidder data
        $bidders_table = AIH_Database::get_table('bidders');
        $bidder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $bidders_table WHERE email_primary = %s",
            $email_address
        ));
        
        if ($bidder) {
            $data = array(
                array('name' => __('Email', 'art-in-heaven'), 'value' => $bidder->email_primary),
                array('name' => __('Name', 'art-in-heaven'), 'value' => trim($bidder->name_first . ' ' . $bidder->name_last)),
                array('name' => __('Phone', 'art-in-heaven'), 'value' => $bidder->phone_mobile),
                array('name' => __('Address', 'art-in-heaven'), 'value' => implode(', ', array_filter(array(
                    $bidder->mailing_street, $bidder->mailing_city, $bidder->mailing_state, $bidder->mailing_zip
                )))),
                array('name' => __('Registration Date', 'art-in-heaven'), 'value' => $bidder->created_at),
            );
            
            $export_items[] = array(
                'group_id' => 'art-in-heaven-bidder',
                'group_label' => __('Auction Bidder Profile', 'art-in-heaven'),
                'item_id' => 'bidder-' . $bidder->id,
                'data' => $data,
            );
        }
        
        // Export bids
        $bids_table = AIH_Database::get_table('bids');
        $art_table = AIH_Database::get_table('art_pieces');
        $bids = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, a.title, a.art_id FROM $bids_table b
             LEFT JOIN $art_table a ON b.art_piece_id = a.id
             WHERE b.bidder_id = %s ORDER BY b.bid_time DESC",
            $email_address
        ));
        
        foreach ($bids as $bid) {
            $export_items[] = array(
                'group_id' => 'art-in-heaven-bids',
                'group_label' => __('Auction Bids', 'art-in-heaven'),
                'item_id' => 'bid-' . $bid->id,
                'data' => array(
                    array('name' => __('Art Piece', 'art-in-heaven'), 'value' => $bid->title . ' (' . $bid->art_id . ')'),
                    array('name' => __('Bid Amount', 'art-in-heaven'), 'value' => '$' . number_format($bid->bid_amount, 2)),
                    array('name' => __('Bid Time', 'art-in-heaven'), 'value' => $bid->bid_time),
                    array('name' => __('Winning Bid', 'art-in-heaven'), 'value' => $bid->is_winning ? __('Yes', 'art-in-heaven') : __('No', 'art-in-heaven')),
                ),
            );
        }
        
        // Export orders
        $orders_table = AIH_Database::get_table('orders');
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $orders_table WHERE bidder_id = %s",
            $email_address
        ));
        
        foreach ($orders as $order) {
            $export_items[] = array(
                'group_id' => 'art-in-heaven-orders',
                'group_label' => __('Auction Orders', 'art-in-heaven'),
                'item_id' => 'order-' . $order->id,
                'data' => array(
                    array('name' => __('Order Number', 'art-in-heaven'), 'value' => $order->order_number),
                    array('name' => __('Total', 'art-in-heaven'), 'value' => '$' . number_format($order->total, 2)),
                    array('name' => __('Status', 'art-in-heaven'), 'value' => ucfirst($order->payment_status)),
                    array('name' => __('Date', 'art-in-heaven'), 'value' => $order->created_at),
                ),
            );
        }
        
        return array(
            'data' => $export_items,
            'done' => true,
        );
    }
    
    /**
     * Erase personal data for GDPR
     * 
     * @param string $email_address The user's email address
     * @param int    $page          Page number
     * @return array
     */
    public static function erase_personal_data($email_address, $page = 1) {
        global $wpdb;
        $items_removed = 0;
        $items_retained = 0;
        $messages = array();
        
        // Anonymize bidder data (don't delete for order integrity)
        $bidders_table = AIH_Database::get_table('bidders');
        $updated = $wpdb->update(
            $bidders_table,
            array(
                'email_primary' => 'anonymized_' . wp_generate_password(8, false),
                'name_first' => 'Anonymized',
                'name_last' => 'User',
                'phone_mobile' => '',
                'phone_home' => '',
                'phone_work' => '',
                'mailing_street' => '',
                'mailing_city' => '',
                'mailing_state' => '',
                'mailing_zip' => '',
                'birthday' => '',
                'api_data' => '',
            ),
            array('email_primary' => $email_address),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );
        
        if ($updated) {
            $items_removed++;
            $messages[] = __('Bidder profile anonymized.', 'art-in-heaven');
        }
        
        // Delete favorites
        $favorites_table = AIH_Database::get_table('favorites');
        $deleted = $wpdb->delete($favorites_table, array('bidder_id' => $email_address), array('%s'));
        if ($deleted) {
            $items_removed += $deleted;
            $messages[] = sprintf(__('%d favorites removed.', 'art-in-heaven'), $deleted);
        }
        
        // Retain bids and orders for business records (anonymized via bidder)
        $bids_table = AIH_Database::get_table('bids');
        $bid_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bids_table WHERE bidder_id = %s",
            $email_address
        ));
        if ($bid_count > 0) {
            $items_retained += $bid_count;
            $messages[] = sprintf(__('%d bids retained for records (anonymized).', 'art-in-heaven'), $bid_count);
        }
        
        return array(
            'items_removed' => $items_removed,
            'items_retained' => $items_retained,
            'messages' => $messages,
            'done' => true,
        );
    }
    
    /**
     * Export data to CSV
     * 
     * @param string $type Export type (art, bids, bidders, orders)
     * @param array  $filters Optional filters
     * @return string CSV content
     */
    public static function to_csv($type, $filters = array()) {
        global $wpdb;
        $data = array();
        $headers = array();
        
        switch ($type) {
            case 'art':
            case 'art_pieces':
                $table = AIH_Database::get_table('art_pieces');
                $data = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC", ARRAY_A);
                $headers = array('ID', 'Art ID', 'Title', 'Artist', 'Medium', 'Dimensions', 'Starting Bid', 'Current Bid', 'Status', 'Auction End', 'Created');
                $fields = array('id', 'art_id', 'title', 'artist', 'medium', 'dimensions', 'starting_bid', 'current_bid', 'status', 'auction_end', 'created_at');
                break;
                
            case 'bids':
                $bids_table = AIH_Database::get_table('bids');
                $art_table = AIH_Database::get_table('art_pieces');
                $data = $wpdb->get_results(
                    "SELECT b.*, a.title, a.art_id as art_code FROM $bids_table b
                     LEFT JOIN $art_table a ON b.art_piece_id = a.id
                     ORDER BY b.bid_time DESC", ARRAY_A
                );
                $headers = array('Bid ID', 'Art ID', 'Art Title', 'Bidder', 'Amount', 'Time', 'Winning');
                $fields = array('id', 'art_code', 'title', 'bidder_id', 'bid_amount', 'bid_time', 'is_winning');
                break;
                
            case 'bidders':
                $table = AIH_Database::get_table('bidders');
                $data = $wpdb->get_results("SELECT * FROM $table ORDER BY name_last, name_first ASC", ARRAY_A);
                $headers = array('ID', 'Email', 'First Name', 'Last Name', 'Phone', 'City', 'State', 'Confirmation Code', 'Last Login', 'Created');
                $fields = array('id', 'email_primary', 'name_first', 'name_last', 'phone_mobile', 'mailing_city', 'mailing_state', 'confirmation_code', 'last_login', 'created_at');
                break;
                
            case 'registrants':
                $table = AIH_Database::get_table('registrants');
                $data = $wpdb->get_results("SELECT * FROM $table ORDER BY name_last, name_first ASC", ARRAY_A);
                $headers = array('ID', 'Email', 'First Name', 'Last Name', 'Phone', 'Confirmation Code', 'Logged In', 'Has Bid', 'Created');
                $fields = array('id', 'email_primary', 'name_first', 'name_last', 'phone_mobile', 'confirmation_code', 'has_logged_in', 'has_bid', 'created_at');
                break;
                
            case 'orders':
                $table = AIH_Database::get_table('orders');
                $data = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A);
                $headers = array('Order #', 'Bidder', 'Subtotal', 'Tax', 'Total', 'Status', 'Method', 'Reference', 'Date');
                $fields = array('order_number', 'bidder_id', 'subtotal', 'tax', 'total', 'payment_status', 'payment_method', 'payment_reference', 'created_at');
                break;
                
            default:
                return '';
        }
        
        // Build CSV
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        // Headers
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($data as $row) {
            $csv_row = array();
            foreach ($fields as $field) {
                $csv_row[] = isset($row[$field]) ? $row[$field] : '';
            }
            fputcsv($output, $csv_row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Generate export file and return download URL
     * 
     * @param string $type Export type
     * @param string $format Format (csv)
     * @return string|WP_Error File URL or error
     */
    /**
     * Get auction statistics for reporting.
     *
     * Results are cached for 5 minutes via AIH_Cache to avoid running
     * 11 separate COUNT/SUM queries on every call.
     *
     * @return array
     */
    public static function get_auction_stats() {
        return AIH_Cache::remember('auction_stats', function () {
            global $wpdb;

            $art_table        = AIH_Database::get_table('art_pieces');
            $bids_table       = AIH_Database::get_table('bids');
            $bidders_table    = AIH_Database::get_table('bidders');
            $registrants_table = AIH_Database::get_table('registrants');
            $orders_table     = AIH_Database::get_table('orders');

            // Consolidate art piece counts into a single query
            $art_counts = $wpdb->get_row(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) AS ended,
                    COALESCE(SUM(CASE WHEN current_bid > 0 THEN current_bid ELSE 0 END), 0) AS bid_value
                 FROM $art_table"
            );

            // Consolidate bid counts into a single query
            $bid_counts = $wpdb->get_row(
                "SELECT COUNT(*) AS total, COUNT(DISTINCT bidder_id) AS unique_bidders
                 FROM $bids_table"
            );

            // Consolidate order counts into a single query
            $order_counts = $wpdb->get_row(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) AS paid,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END), 0) AS revenue
                 FROM $orders_table"
            );

            return array(
                'total_art_pieces'  => $art_counts ? (int) $art_counts->total : 0,
                'active_auctions'   => $art_counts ? (int) $art_counts->active : 0,
                'ended_auctions'    => $art_counts ? (int) $art_counts->ended : 0,
                'total_bid_value'   => $art_counts ? (float) $art_counts->bid_value : 0,
                'total_bids'        => $bid_counts ? (int) $bid_counts->total : 0,
                'unique_bidders'    => $bid_counts ? (int) $bid_counts->unique_bidders : 0,
                'total_registrants' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $registrants_table"),
                'logged_in_bidders' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $bidders_table"),
                'total_orders'      => $order_counts ? (int) $order_counts->total : 0,
                'paid_orders'       => $order_counts ? (int) $order_counts->paid : 0,
                'total_revenue'     => $order_counts ? (float) $order_counts->revenue : 0,
            );
        }, 5 * MINUTE_IN_SECONDS, 'stats');
    }
}
