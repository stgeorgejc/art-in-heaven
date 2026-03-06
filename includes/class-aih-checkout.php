<?php
/**
 * Checkout and Orders Handler with Pushpay Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Checkout {
    
    /** @var self|null */
    private static $instance = null;

    /** @var array{merchant_key: string, base_url: string, fund: string}|null */
    private static $cached_pushpay_settings = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * @return array{merchant_key: string, base_url: string, fund: string}
     */
    public function get_pushpay_settings() {
        if (self::$cached_pushpay_settings !== null) {
            return self::$cached_pushpay_settings;
        }

        self::$cached_pushpay_settings = array(
            'merchant_key' => get_option('aih_pushpay_merchant_key', ''),
            'base_url' => get_option('aih_pushpay_base_url', 'https://pushpay.com/pay/'),
            'fund' => get_option('aih_pushpay_fund', 'art-in-heaven')
        );

        return self::$cached_pushpay_settings;
    }
    
    /**
     * @param object $order Order object.
     * @return string
     */
    public function get_pushpay_payment_url($order) {
        // Use the new Pushpay API class
        $pushpay = AIH_Pushpay_API::get_instance();
        /** @var stdClass $order */
        return $pushpay->get_payment_url($order);
    }
    
    /**
     * @param int|string $bidder_id Bidder confirmation code.
     * @return list<object>
     */
    public function get_won_items($bidder_id) {
        global $wpdb;
        
        $bids_table = AIH_Database::get_table('bids');
        $art_table = AIH_Database::get_table('art_pieces');
        $order_items_table = AIH_Database::get_table('order_items');
        
        $orders_table = AIH_Database::get_table('orders');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, a.id as art_piece_id, b.bid_amount as winning_amount, b.bid_amount as winning_bid, b.bid_time
             FROM $bids_table b
             JOIN $art_table a ON b.art_piece_id = a.id
             LEFT JOIN $order_items_table oi ON oi.art_piece_id = a.id
             LEFT JOIN $orders_table o ON oi.order_id = o.id
             WHERE b.bidder_id = %s
             AND b.is_winning = 1
             AND (a.auction_end < %s OR a.status = 'ended')
             AND (oi.id IS NULL OR o.payment_status IN ('cancelled'))
             ORDER BY a.auction_end DESC",
            $bidder_id,
            current_time('mysql')
        ));
    }

    /**
     * Cancel stale pending orders for a bidder so items return to checkout.
     *
     * Only cancels orders older than 10 minutes to avoid racing with
     * concurrent tabs or in-flight PushPay payments.
     *
     * Callers must verify the bidder is authorized before invoking this method.
     *
     * @param int|string $bidder_id Bidder confirmation code.
     * @return int Number of cancelled orders.
     */
    public function cancel_pending_orders($bidder_id) {
        // Verify the caller's session matches the bidder being cancelled.
        $current_bidder = AIH_Auth::get_instance()->get_current_bidder_id();
        if (empty($current_bidder) || $current_bidder !== $bidder_id) {
            return 0;
        }

        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');

        $pending_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $orders_table WHERE bidder_id = %s AND payment_status = 'pending' AND created_at < %s",
            $bidder_id,
            (new DateTime(current_time('mysql'), wp_timezone()))->modify('-10 minutes')->format('Y-m-d H:i:s')
        ));

        foreach ($pending_ids as $order_id) {
            $this->update_payment_status((int) $order_id, 'cancelled', '', '', 'Auto-cancelled: bidder returned to checkout');
        }

        return count($pending_ids);
    }

    /**
     * Get payment status for all art pieces a bidder has won (keyed by art_piece_id).
     *
     * @param int|string $bidder_id Bidder confirmation code.
     * @return array<int|string, string>
     */
    public function get_bidder_payment_statuses($bidder_id) {
        global $wpdb;

        $bids_table = AIH_Database::get_table('bids');
        $order_items_table = AIH_Database::get_table('order_items');
        $orders_table = AIH_Database::get_table('orders');

        /** @var list<object{art_piece_id: string, payment_status: string}> $results */
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT b.art_piece_id, o.payment_status
             FROM $bids_table b
             JOIN $order_items_table oi ON oi.art_piece_id = b.art_piece_id
             JOIN $orders_table o ON oi.order_id = o.id
             WHERE b.bidder_id = %s AND b.is_winning = 1
             ORDER BY o.id DESC",
            $bidder_id
        ));

        $map = array();
        foreach ($results as $row) {
            if (!isset($map[$row->art_piece_id])) {
                $map[$row->art_piece_id] = $row->payment_status;
            }
        }
        return $map;
    }

    /**
     * @param array<int, object> $items Items with winning_amount property.
     * @return array{subtotal: float, tax: float, tax_rate: float, total: float, item_count: int}
     */
    public function calculate_totals($items) {
        $subtotal = 0;
        foreach ($items as $item) {
            /** @var stdClass $item */
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
    
    /**
     * @param int|string    $bidder_id     Bidder confirmation code.
     * @param array<int, int> $art_piece_ids Art piece IDs to include in the order.
     * @return array{success: bool, message?: string, order_id?: int, order_number?: string, totals?: array<string, mixed>, pushpay_url?: string, idempotent?: bool}
     */
    public function create_order($bidder_id, $art_piece_ids = array()) {
        global $wpdb;

        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        $bids_table = AIH_Database::get_table('bids');

        // --- Idempotency key support ---
        // If the client sends an idempotency_key, return the existing order
        // instead of creating a duplicate.
        $idempotency_key = isset($_POST['idempotency_key']) ? sanitize_text_field($_POST['idempotency_key']) : '';
        if (!empty($idempotency_key)) {
            $transient_key = 'aih_idempotency_' . md5($bidder_id . '_' . $idempotency_key);
            $existing_order_id = get_transient($transient_key);
            if ($existing_order_id) {
                $order = $this->get_order($existing_order_id);
                if ($order) {
                    /** @var stdClass $order */
                    $pushpay_url = $this->get_pushpay_payment_url($order);
                    return array(
                        'success' => true,
                        'order_id' => (int) $existing_order_id,
                        'order_number' => $order->order_number,
                        'totals' => array(
                            'subtotal' => (float) $order->subtotal,
                            'tax' => (float) $order->tax,
                            'total' => (float) $order->total,
                        ),
                        'pushpay_url' => $pushpay_url,
                        'idempotent' => true,
                    );
                }
            }
        }

        $wpdb->query('START TRANSACTION');

        try {
            // Lock the bidder's winning bids inside the transaction to prevent double-orders
            $wpdb->query($wpdb->prepare(
                "SELECT id FROM $bids_table WHERE bidder_id = %s AND is_winning = 1 FOR UPDATE",
                $bidder_id
            ));

            $won_items = $this->get_won_items($bidder_id);

            if (!empty($art_piece_ids)) {
                $won_items = array_filter($won_items, function($item) use ($art_piece_ids) {
                    /** @var stdClass $item */
                    return in_array($item->id, $art_piece_ids);
                });
            }

            if (empty($won_items)) {
                $wpdb->query('ROLLBACK');
                return array('success' => false, 'message' => __('No items to checkout.', 'art-in-heaven'));
            }

            $totals = $this->calculate_totals($won_items);

            // Generate a collision-free order number
            do {
                $order_number = 'AIH-' . strtoupper(wp_generate_password(8, false));
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT 1 FROM $orders_table WHERE order_number = %s",
                    $order_number
                ));
            } while ($exists);

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
                /** @var stdClass $item */
                $wpdb->insert($order_items_table, array(
                    'order_id' => $order_id,
                    'art_piece_id' => $item->id,
                    'winning_bid' => $item->winning_amount
                ));
            }

            $wpdb->query('COMMIT');

            // Store idempotency mapping (10-minute TTL)
            if (!empty($idempotency_key)) {
                $transient_key = 'aih_idempotency_' . md5($bidder_id . '_' . $idempotency_key);
                set_transient($transient_key, $order_id, 10 * MINUTE_IN_SECONDS);
            }

            $order = $this->get_order($order_id);
            if (!$order) {
                $wpdb->query('ROLLBACK');
                return array('success' => false, 'message' => __('Order not found after creation.', 'art-in-heaven'));
            }
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
            error_log('AIH Checkout Error: ' . $e->getMessage());
            return array('success' => false, 'message' => __('An error occurred while creating your order. Please try again.', 'art-in-heaven'));
        }
    }
    
    /**
     * @param int $order_id Order ID.
     * @return object|null
     */
    public function get_order($order_id) {
        global $wpdb;
        
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        $art_table = AIH_Database::get_table('art_pieces');
        $bidders_table = AIH_Database::get_table('bidders');
        $registrants_table = AIH_Database::get_table('registrants');

        /** @var stdClass|null $order */
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
    
    /**
     * @param string $order_number Order number (e.g. AIH-XXXXXXXX).
     * @return object|null
     */
    public function get_order_by_number($order_number) {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $order_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $orders_table WHERE order_number = %s", $order_number));
        return $order_id ? $this->get_order($order_id) : null;
    }
    
    /**
     * @param int    $order_id  Order ID.
     * @param string $status    Payment status.
     * @param string $method    Payment method.
     * @param string $reference Payment reference.
     * @param string $notes     Admin notes.
     * @return int|false Number of rows updated or false on error.
     */
    public function update_payment_status($order_id, $status, $method = '', $reference = '', $notes = '') {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');

        // Validate status against allowlist
        $allowed_statuses = array('pending', 'paid', 'refunded', 'failed', 'cancelled');
        if (!in_array($status, $allowed_statuses, true)) {
            return false;
        }

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
    
    /**
     * Mark a manual payment for a won art piece from the admin panel.
     *
     * If an order already exists for the art piece, updates its payment status.
     * Otherwise, finds the winning bid, creates a new order, and sets payment fields.
     *
     * @param int    $art_piece_id  The art piece ID.
     * @param string $status        Payment status (pending|paid|refunded).
     * @param string $method        Payment method.
     * @param string $reference     Payment reference.
     * @param string $notes         Admin notes.
     * @return array{success: bool, message: string}
     */
    public function mark_manual_payment( $art_piece_id, $status, $method = '', $reference = '', $notes = '' ) {
        global $wpdb;

        $allowed_statuses = array( 'pending', 'paid', 'refunded' );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid payment status.', 'art-in-heaven' ),
            );
        }

        $allowed_methods = array( 'pushpay', 'cash', 'check', 'card', 'other', '' );
        if ( ! in_array( $method, $allowed_methods, true ) ) {
            return array(
                'success' => false,
                'message' => __( 'Invalid payment method.', 'art-in-heaven' ),
            );
        }

        $orders_table      = AIH_Database::get_table( 'orders' );
        $order_items_table = AIH_Database::get_table( 'order_items' );
        $bids_table        = AIH_Database::get_table( 'bids' );
        $art_table         = AIH_Database::get_table( 'art_pieces' );

        // Step 1: Check for an existing order for this art piece.
        /** @var object{id: string, order_number: string, bidder_id: string, subtotal: string, tax: string, total: string, payment_status: string, payment_method: string, payment_reference: string, notes: string, payment_date: string|null, created_at: string, updated_at: string|null}|null $existing_order */
        $existing_order = $wpdb->get_row( $wpdb->prepare(
            "SELECT o.* FROM $orders_table o
             JOIN $order_items_table oi ON o.id = oi.order_id
             WHERE oi.art_piece_id = %d",
            $art_piece_id
        ) );

        if ( $existing_order ) {
            $updated = $this->update_payment_status(
                (int) $existing_order->id,
                $status,
                $method,
                $reference,
                $notes
            );
            if ( $updated === false ) {
                return array(
                    'success' => false,
                    'message' => __( 'Failed to update payment status.', 'art-in-heaven' ),
                );
            }
            return array(
                'success' => true,
                'message' => __( 'Payment status updated.', 'art-in-heaven' ),
            );
        }

        // Step 2: No order exists — find the winning bid.
        /** @var object{id: string, art_piece_id: string, bidder_id: string, bid_amount: string, bid_time: string, is_winning: string, title: string}|null $winning_bid */
        $winning_bid = $wpdb->get_row( $wpdb->prepare(
            "SELECT b.*, a.title FROM $bids_table b
             JOIN $art_table a ON b.art_piece_id = a.id
             WHERE b.art_piece_id = %d AND b.is_winning = 1",
            $art_piece_id
        ) );

        if ( ! $winning_bid ) {
            return array(
                'success' => false,
                'message' => __( 'No winning bid found for this art piece.', 'art-in-heaven' ),
            );
        }

        // Step 3: Create a new order.
        do {
            $order_number = 'AIH-' . strtoupper( wp_generate_password( 8, false ) );
            $exists       = $wpdb->get_var( $wpdb->prepare(
                "SELECT 1 FROM $orders_table WHERE order_number = %s",
                $order_number
            ) );
        } while ( $exists );

        $totals = $this->calculate_totals( array( (object) array( 'winning_amount' => $winning_bid->bid_amount ) ) );

        $wpdb->insert( $orders_table, array(
            'order_number'    => $order_number,
            'bidder_id'       => $winning_bid->bidder_id,
            'subtotal'        => $totals['subtotal'],
            'tax'             => $totals['tax'],
            'total'           => $totals['total'],
            'payment_status'  => $status,
            'payment_method'  => $method,
            'payment_reference' => $reference,
            'notes'           => $notes,
            'payment_date'    => $status === 'paid' ? current_time( 'mysql' ) : null,
            'created_at'      => current_time( 'mysql' ),
        ) );

        $order_id = $wpdb->insert_id;
        if ( ! $order_id ) {
            return array(
                'success' => false,
                'message' => __( 'Failed to create order.', 'art-in-heaven' ),
            );
        }

        $item_inserted = $wpdb->insert( $order_items_table, array(
            'order_id'     => $order_id,
            'art_piece_id' => $art_piece_id,
            'winning_bid'  => $winning_bid->bid_amount,
        ) );
        if ( $item_inserted === false ) {
            return array(
                'success' => false,
                'message' => __( 'Order created but failed to add item.', 'art-in-heaven' ),
            );
        }

        // Invalidate payment stats cache.
        if ( class_exists( 'AIH_Cache' ) ) {
            AIH_Cache::delete( 'payment_stats' );
        }

        return array(
            'success' => true,
            'message' => __( 'Order created and payment status set.', 'art-in-heaven' ),
        );
    }

    /**
     * @param array<string, mixed> $args Query arguments.
     * @return list<object>
     */
    public function get_all_orders($args = array()) {
        global $wpdb;

        $defaults = array('status' => '', 'bidder_id' => '', 'search' => '', 'orderby' => 'created_at', 'order' => 'DESC', 'limit' => 0, 'offset' => 0);
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
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(
                " AND (o.order_number LIKE %s OR o.bidder_id LIKE %s OR COALESCE(bd.name_first, rg.name_first, '') LIKE %s OR COALESCE(bd.name_last, rg.name_last, '') LIKE %s OR CONCAT(COALESCE(bd.name_first, rg.name_first, ''), ' ', COALESCE(bd.name_last, rg.name_last, '')) LIKE %s OR COALESCE(bd.email_primary, rg.email_primary, '') LIKE %s)",
                $like, $like, $like, $like, $like, $like
            );
        }

        $limit = intval($args['limit']);
        $offset = intval($args['offset']);

        // Use derived table for item_count instead of correlated subquery
        // Join both bidders and registrants tables, prefer bidders data but fall back to registrants
        $sql = "SELECT o.*,
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
             ORDER BY o.{$args['orderby']} {$args['order']}";

        if ($limit > 0) {
            $sql = $wpdb->prepare($sql . " LIMIT %d OFFSET %d", $limit, $offset);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * @param array<string, mixed> $args Query arguments.
     * @return int
     */
    public function count_orders($args = array()) {
        global $wpdb;

        $defaults = array('status' => '', 'bidder_id' => '', 'search' => '');
        $args = wp_parse_args($args, $defaults);

        $orders_table = AIH_Database::get_table('orders');
        $bidders_table = AIH_Database::get_table('bidders');
        $registrants_table = AIH_Database::get_table('registrants');

        $where = "1=1";
        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND o.payment_status = %s", $args['status']);
        }
        if (!empty($args['bidder_id'])) {
            $where .= $wpdb->prepare(" AND o.bidder_id = %s", $args['bidder_id']);
        }
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(
                " AND (o.order_number LIKE %s OR o.bidder_id LIKE %s OR COALESCE(bd.name_first, rg.name_first, '') LIKE %s OR COALESCE(bd.name_last, rg.name_last, '') LIKE %s OR CONCAT(COALESCE(bd.name_first, rg.name_first, ''), ' ', COALESCE(bd.name_last, rg.name_last, '')) LIKE %s OR COALESCE(bd.email_primary, rg.email_primary, '') LIKE %s)",
                $like, $like, $like, $like, $like, $like
            );
        }

        $query = "SELECT COUNT(*) FROM $orders_table o
                  LEFT JOIN $bidders_table bd ON o.bidder_id = bd.confirmation_code
                  LEFT JOIN $registrants_table rg ON o.bidder_id = rg.confirmation_code
                  WHERE $where";

        return (int) $wpdb->get_var($query);
    }
    
    /**
     * @param int|string $bidder_id Bidder confirmation code.
     * @return list<object>
     */
    public function get_bidder_orders($bidder_id) {
        return $this->get_all_orders(array('bidder_id' => $bidder_id));
    }
    
    /**
     * @return object|null
     */
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

        /** @var object{total_orders: string, paid_orders: string, pending_orders: string, total_collected: string, total_pending: string}|null $stats */
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
    
    /**
     * @param int $order_id Order ID.
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete_order($order_id) {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');

        $wpdb->query('START TRANSACTION');

        $items_deleted = $wpdb->delete($order_items_table, array('order_id' => $order_id));
        $order_deleted = $wpdb->delete($orders_table, array('id' => $order_id));

        if ($order_deleted === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }

        $wpdb->query('COMMIT');

        // Invalidate payment stats cache
        if (class_exists('AIH_Cache')) {
            AIH_Cache::delete('payment_stats');
        }

        return $order_deleted;
    }
}
