<?php
/**
 * Checkout and Orders Handler with Pushpay Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Checkout {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get_pushpay_settings() {
        return array(
            'merchant_key' => get_option('aih_pushpay_merchant_key', ''),
            'base_url' => get_option('aih_pushpay_base_url', 'https://pushpay.com/pay/'),
            'fund' => get_option('aih_pushpay_fund', 'art-in-heaven')
        );
    }
    
    public function get_pushpay_payment_url($order) {
        // Use the new Pushpay API class
        $pushpay = AIH_Pushpay_API::get_instance();
        return $pushpay->get_payment_url($order);
    }
    
    public function get_won_items($bidder_id) {
        global $wpdb;
        
        $bids_table = AIH_Database::get_table('bids');
        $art_table = AIH_Database::get_table('art_pieces');
        $order_items_table = AIH_Database::get_table('order_items');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, a.id as art_piece_id, b.bid_amount as winning_amount, b.bid_amount as winning_bid, b.bid_time
             FROM $bids_table b
             JOIN $art_table a ON b.art_piece_id = a.id
             LEFT JOIN $order_items_table oi ON oi.art_piece_id = a.id
             WHERE b.bidder_id = %s 
             AND b.is_winning = 1 
             AND (a.auction_end < NOW() OR a.status = 'ended')
             AND oi.id IS NULL
             ORDER BY a.auction_end DESC",
            $bidder_id
        ));
    }
    
    public function calculate_totals($items) {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item->winning_amount);
        }
        
        $tax_rate = floatval(get_option('aih_tax_rate', 0));
        $tax = $subtotal * ($tax_rate / 100);
        
        return array(
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'tax_rate' => $tax_rate,
            'total' => round($subtotal + $tax, 2),
            'item_count' => count($items)
        );
    }
    
    public function create_order($bidder_id, $art_piece_ids = array()) {
        global $wpdb;
        
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        
        $won_items = $this->get_won_items($bidder_id);
        
        if (!empty($art_piece_ids)) {
            $won_items = array_filter($won_items, function($item) use ($art_piece_ids) {
                return in_array($item->id, $art_piece_ids);
            });
        }
        
        if (empty($won_items)) {
            return array('success' => false, 'message' => __('No items to checkout.', 'art-in-heaven'));
        }
        
        $totals = $this->calculate_totals($won_items);
        $order_number = 'AIH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $wpdb->insert($orders_table, array(
                'order_number' => $order_number,
                'bidder_id' => $bidder_id,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'payment_status' => 'pending',
                'payment_method' => 'pushpay',
                'created_at' => current_time('mysql')
            ));
            
            $order_id = $wpdb->insert_id;
            if (!$order_id) throw new Exception('Failed to create order.');
            
            foreach ($won_items as $item) {
                $wpdb->insert($order_items_table, array(
                    'order_id' => $order_id,
                    'art_piece_id' => $item->id,
                    'winning_bid' => $item->winning_amount
                ));
            }
            
            $wpdb->query('COMMIT');
            
            $order = $this->get_order($order_id);
            $pushpay_url = $this->get_pushpay_payment_url($order);

            if (empty($pushpay_url)) {
                return array(
                    'success' => false,
                    'message' => 'Payment URL could not be generated. Please configure the Merchant Handle in Pushpay settings.'
                );
            }

            return array(
                'success' => true,
                'order_id' => $order_id,
                'order_number' => $order_number,
                'totals' => $totals,
                'pushpay_url' => $pushpay_url
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    public function get_order($order_id) {
        global $wpdb;
        
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        $art_table = AIH_Database::get_table('art_pieces');
        $bidders_table = AIH_Database::get_table('bidders');
        $registrants_table = AIH_Database::get_table('registrants');

        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*,
                    COALESCE(bd.name_first, rg.name_first) as name_first,
                    COALESCE(bd.name_last, rg.name_last) as name_last,
                    COALESCE(bd.phone_mobile, rg.phone_mobile) as phone,
                    COALESCE(bd.email_primary, rg.email_primary) as email
             FROM $orders_table o
             LEFT JOIN $bidders_table bd ON o.bidder_id = bd.confirmation_code
             LEFT JOIN $registrants_table rg ON o.bidder_id = rg.confirmation_code
             WHERE o.id = %d",
            $order_id
        ));
        
        if (!$order) return null;
        
        $order->items = $wpdb->get_results($wpdb->prepare(
            "SELECT oi.*, a.title, a.artist, a.art_id, a.watermarked_url, a.image_url
             FROM $order_items_table oi
             JOIN $art_table a ON oi.art_piece_id = a.id
             WHERE oi.order_id = %d",
            $order_id
        ));
        
        return $order;
    }
    
    public function get_order_by_number($order_number) {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $order_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $orders_table WHERE order_number = %s", $order_number));
        return $order_id ? $this->get_order($order_id) : null;
    }
    
    public function update_payment_status($order_id, $status, $method = '', $reference = '', $notes = '') {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        
        $data = array('payment_status' => $status, 'updated_at' => current_time('mysql'));
        if (!empty($method)) $data['payment_method'] = $method;
        if (!empty($reference)) $data['payment_reference'] = $reference;
        if (!empty($notes)) $data['notes'] = $notes;
        if ($status === 'paid') $data['payment_date'] = current_time('mysql');

        $result = $wpdb->update($orders_table, $data, array('id' => $order_id));

        // Invalidate payment stats cache when order status changes
        if ($result !== false && class_exists('AIH_Cache')) {
            AIH_Cache::delete('payment_stats');
        }

        return $result;
    }
    
    public function get_all_orders($args = array()) {
        global $wpdb;

        $defaults = array('status' => '', 'bidder_id' => '', 'orderby' => 'created_at', 'order' => 'DESC');
        $args = wp_parse_args($args, $defaults);

        $orders_table = AIH_Database::get_table('orders');
        $bidders_table = AIH_Database::get_table('bidders');
        $registrants_table = AIH_Database::get_table('registrants');
        $order_items_table = AIH_Database::get_table('order_items');
        $art_table = AIH_Database::get_table('art_pieces');

        // Sanitize orderby to prevent SQL injection
        $allowed_orderby = array('created_at', 'total', 'payment_status', 'order_number', 'updated_at');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'created_at';
        }
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $where = "1=1";
        if (!empty($args['status'])) $where .= $wpdb->prepare(" AND o.payment_status = %s", $args['status']);
        if (!empty($args['bidder_id'])) $where .= $wpdb->prepare(" AND o.bidder_id = %s", $args['bidder_id']);

        // Use derived table for item_count instead of correlated subquery
        // Join both bidders and registrants tables, prefer bidders data but fall back to registrants
        return $wpdb->get_results(
            "SELECT o.*,
                    COALESCE(bd.name_first, rg.name_first) as name_first,
                    COALESCE(bd.name_last, rg.name_last) as name_last,
                    COALESCE(bd.phone_mobile, rg.phone_mobile) as phone,
                    COALESCE(bd.email_primary, rg.email_primary) as email,
                    COALESCE(oic.item_count, 0) as item_count
             FROM $orders_table o
             LEFT JOIN $bidders_table bd ON o.bidder_id = bd.confirmation_code
             LEFT JOIN $registrants_table rg ON o.bidder_id = rg.confirmation_code
             LEFT JOIN (
                 SELECT oi.order_id, COUNT(*) as item_count
                 FROM $order_items_table oi
                 JOIN $art_table a ON oi.art_piece_id = a.id
                 GROUP BY oi.order_id
             ) oic ON o.id = oic.order_id
             WHERE $where
             ORDER BY o.{$args['orderby']} {$args['order']}"
        );
    }
    
    public function get_bidder_orders($bidder_id) {
        return $this->get_all_orders(array('bidder_id' => $bidder_id));
    }
    
    public function get_payment_stats() {
        // Check cache first
        if (class_exists('AIH_Cache')) {
            $cached = AIH_Cache::get('payment_stats');
            if ($cached !== null) {
                return $cached;
            }
        }

        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');

        $stats = $wpdb->get_row(
            "SELECT
                COUNT(*) as total_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_collected,
                SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as total_pending
             FROM $orders_table"
        );

        // Cache for 2 minutes
        if (class_exists('AIH_Cache')) {
            AIH_Cache::set('payment_stats', $stats, 2 * MINUTE_IN_SECONDS, 'orders');
        }

        return $stats;
    }
    
    public function delete_order($order_id) {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');

        $wpdb->delete($order_items_table, array('order_id' => $order_id));
        $result = $wpdb->delete($orders_table, array('id' => $order_id));

        // Invalidate payment stats cache
        if ($result && class_exists('AIH_Cache')) {
            AIH_Cache::delete('payment_stats');
        }

        return $result;
    }
}
