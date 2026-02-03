<?php
/**
 * Bid Model - Uses bidder_id (confirmation_code) instead of email
 * 
 * IMPORTANT: All bids are stored in the database, not just winning bids.
 * This allows for bid history, analytics, and audit trails.
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Bid {
    
    private $table;
    private $art_piece_model;
    
    public function __construct() {
        $this->table = AIH_Database::get_table('bids');
        $this->art_piece_model = new AIH_Art_Piece();
    }
    
    /**
     * Place a bid
     * 
     * @param int    $art_piece_id Art piece ID
     * @param string $bidder_id    Bidder's confirmation code
     * @param float  $amount       Bid amount
     * @return array Result with success, message, bid_id
     */
    public function place_bid($art_piece_id, $bidder_id, $amount) {
        global $wpdb;
        
        $art_table = AIH_Database::get_table('art_pieces');
        $now = current_time('mysql');
        
        // Start transaction to prevent race conditions
        $wpdb->query('START TRANSACTION');
        
        try {
            // Lock the art piece row and get current highest bid atomically
            $art_piece = $wpdb->get_row($wpdb->prepare(
                "SELECT a.id, a.starting_bid, a.auction_start, a.auction_end, a.status,
                        CASE
                            WHEN a.status = 'draft' AND a.auction_start IS NOT NULL AND a.auction_start <= %s AND (a.auction_end IS NULL OR a.auction_end > %s) THEN 'active'
                            WHEN a.status = 'draft' THEN 'draft'
                            WHEN a.status = 'ended' THEN 'ended'
                            WHEN a.auction_end <= %s THEN 'ended'
                            WHEN a.auction_start > %s THEN 'upcoming'
                            ELSE 'active'
                        END as computed_status,
                        (SELECT MAX(bid_amount) FROM {$this->table} WHERE art_piece_id = a.id AND bid_status = 'valid') as current_highest
                 FROM $art_table a WHERE a.id = %d FOR UPDATE",
                $now, $now, $now, $now, $art_piece_id
            ));
            
            if (!$art_piece) {
                $wpdb->query('ROLLBACK');
                return array('success' => false, 'message' => __('Art piece not found.', 'art-in-heaven'));
            }
            
            if ($art_piece->computed_status === 'ended') {
                if ($art_piece->status === 'active') {
                    $wpdb->update($art_table, array('status' => 'ended'), array('id' => $art_piece_id));
                }
                $wpdb->query('COMMIT');
                return array('success' => false, 'message' => __('This auction has ended.', 'art-in-heaven'));
            }
            
            if ($art_piece->computed_status === 'upcoming') {
                $wpdb->query('ROLLBACK');
                return array('success' => false, 'message' => __('This auction has not started yet.', 'art-in-heaven'));
            }
            
            $current_highest = floatval($art_piece->current_highest);
            $ip_address = !empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '';
            $bid_amount = floatval($amount);
            
            // Check if bid is too low
            if ($current_highest > 0) {
                $is_too_low = $bid_amount <= $current_highest;
            } else {
                $is_too_low = $bid_amount < floatval($art_piece->starting_bid);
            }
            
            // Insert bid
            $wpdb->insert(
                $this->table,
                array(
                    'art_piece_id' => $art_piece_id,
                    'bidder_id' => $bidder_id,
                    'bid_amount' => $bid_amount,
                    'bid_time' => $now,
                    'is_winning' => $is_too_low ? 0 : 1,
                    'bid_status' => $is_too_low ? 'too_low' : 'valid',
                    'ip_address' => $ip_address,
                ),
                array('%d', '%s', '%f', '%s', '%d', '%s', '%s')
            );
            
            $bid_id = $wpdb->insert_id;
            
            if ($is_too_low) {
                $wpdb->query('COMMIT');
                return array(
                    'success' => false,
                    'message' => __('Your Bid is too Low.', 'art-in-heaven'),
                    'bid_too_low' => true,
                    'bid_id' => $bid_id
                );
            }
            
            // Update previous winning bids
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table} SET is_winning = 0 WHERE art_piece_id = %d AND id != %d AND is_winning = 1",
                $art_piece_id, $bid_id
            ));
            
            $wpdb->query('COMMIT');
            
            // Fire action (notifications hook into this) - after commit
            do_action('aih_bid_placed', $bid_id, $art_piece_id, $bidder_id, $amount);
            
            return array(
                'success' => true,
                'message' => __('Your bid has been placed successfully!', 'art-in-heaven'),
                'bid_id' => $bid_id,
                'current_bid' => $amount,
            );
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return array('success' => false, 'message' => __('An error occurred. Please try again.', 'art-in-heaven'));
        }
    }
    
    /**
     * Get highest bid amount for an art piece (only valid bids)
     */
    public function get_highest_bid_amount($art_piece_id) {
        global $wpdb;
        $amount = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(bid_amount) FROM {$this->table} WHERE art_piece_id = %d AND (bid_status = 'valid' OR bid_status IS NULL)",
            $art_piece_id
        ));
        return floatval($amount);
    }
    
    /**
     * Get all bids for an art piece (includes bidder info, only valid bids)
     */
    public function get_bids_for_art_piece($art_piece_id, $limit = null) {
        global $wpdb;
        
        $bidders_table = AIH_Database::get_table('bidders');
        
        $limit_clause = $limit ? $wpdb->prepare("LIMIT %d", $limit) : "";
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                    CONCAT(bd.name_first, ' ', bd.name_last) as bidder_name, 
                    bd.email_primary as bidder_email,
                    bd.phone_mobile as bidder_phone,
                    bd.confirmation_code
             FROM {$this->table} b
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
             WHERE b.art_piece_id = %d
               AND (b.bid_status = 'valid' OR b.bid_status IS NULL)
             ORDER BY b.bid_amount DESC
             $limit_clause",
            $art_piece_id
        ));
    }
    
    /**
     * Get bids by bidder for a specific art piece
     */
    public function get_bidder_bids_for_art_piece($art_piece_id, $bidder_id, $successful_only = false) {
        global $wpdb;

        // Get the current highest bid to determine which bids were "successful" (higher than previous)
        if ($successful_only) {
            // Get bids that were winning at some point (is_winning = 1) OR are currently the highest for this bidder
            // A successful bid is one that was the highest bid at the time it was placed
            // Only include valid bids (exclude 'too_low' bids)
            return $wpdb->get_results($wpdb->prepare(
                "SELECT b.* FROM {$this->table} b
                 WHERE b.art_piece_id = %d AND b.bidder_id = %s
                 AND b.bid_status = 'valid'
                 AND (b.is_winning = 1 OR b.bid_amount = (
                     SELECT MAX(b2.bid_amount) FROM {$this->table} b2
                     WHERE b2.art_piece_id = b.art_piece_id AND b2.bidder_id = b.bidder_id AND b2.bid_status = 'valid'
                 ))
                 ORDER BY b.bid_time DESC",
                $art_piece_id,
                $bidder_id
            ));
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE art_piece_id = %d AND bidder_id = %s
             ORDER BY bid_time DESC",
            $art_piece_id,
            $bidder_id
        ));
    }
    
    /**
     * Get only successful (winning) bids for display - excludes "too low" bids
     */
    public function get_successful_bids_for_art_piece($art_piece_id, $bidder_id) {
        global $wpdb;

        // Only return valid bids - those that were accepted when placed
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE art_piece_id = %d AND bidder_id = %s AND bid_status = 'valid'
             ORDER BY bid_time DESC",
            $art_piece_id,
            $bidder_id
        ));
    }
    
    /**
     * Get all bids by bidder (confirmation_code)
     * Returns only the bidder's highest bid per art piece to avoid duplicates
     */
    public function get_bidder_bids($bidder_id) {
        global $wpdb;

        $art_table = AIH_Database::get_table('art_pieces');
        $now = current_time('mysql');

        // Get the bidder's highest bid per art piece, avoiding duplicates
        // Uses a subquery to find the max bid amount per art piece for this bidder,
        // then joins to get full details of that specific bid
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, a.title, a.title as art_title, a.artist, a.art_id, a.auction_end, a.status as auction_status,
                    a.watermarked_url, a.watermarked_url as image_url, a.image_url as original_image_url,
                    a.starting_bid,
                    CASE
                        WHEN a.status = 'ended' THEN 'ended'
                        WHEN a.auction_end IS NOT NULL AND a.auction_end <= %s THEN 'ended'
                        WHEN a.auction_start IS NOT NULL AND a.auction_start > %s THEN 'upcoming'
                        ELSE 'active'
                    END as computed_status
             FROM {$this->table} b
             JOIN $art_table a ON b.art_piece_id = a.id
             INNER JOIN (
                 SELECT art_piece_id, MAX(bid_amount) as max_bid
                 FROM {$this->table}
                 WHERE bidder_id = %s AND bid_status = 'valid'
                 GROUP BY art_piece_id
             ) max_bids ON b.art_piece_id = max_bids.art_piece_id
                       AND b.bid_amount = max_bids.max_bid
                       AND b.bidder_id = %s
             WHERE b.bidder_id = %s AND b.bid_status = 'valid'
             GROUP BY b.art_piece_id
             ORDER BY b.bid_time DESC",
            $now, $now, $bidder_id, $bidder_id, $bidder_id
        ));
    }
    
    /**
     * Check if bidder is winning an art piece
     */
    public function is_bidder_winning($art_piece_id, $bidder_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT is_winning FROM {$this->table}
             WHERE art_piece_id = %d AND bidder_id = %s AND is_winning = 1",
            $art_piece_id,
            $bidder_id
        ));
        
        return $result == 1;
    }
    
    /**
     * Get winning bid for an art piece
     */
    public function get_winning_bid($art_piece_id) {
        global $wpdb;
        
        $bidders_table = AIH_Database::get_table('bidders');
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, 
                    CONCAT(bd.name_first, ' ', bd.name_last) as bidder_name, 
                    bd.email_primary as bidder_email, 
                    bd.phone_mobile as bidder_phone,
                    bd.confirmation_code
             FROM {$this->table} b
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
             WHERE b.art_piece_id = %d AND b.is_winning = 1",
            $art_piece_id
        ));
    }
    
    /**
     * Get bid count for art piece (only valid bids)
     */
    public function get_bid_count($art_piece_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE art_piece_id = %d AND (bid_status = 'valid' OR bid_status IS NULL)",
            $art_piece_id
        ));
    }
    
    /**
     * Get unique bidder count for art piece (only valid bids)
     */
    public function get_unique_bidder_count($art_piece_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT bidder_id) FROM {$this->table} WHERE art_piece_id = %d AND (bid_status = 'valid' OR bid_status IS NULL)",
            $art_piece_id
        ));
    }
    
    /**
     * Delete bid
     */
    public function delete($bid_id) {
        global $wpdb;
        
        // Get bid info first
        $bid = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $bid_id
        ));
        
        if (!$bid) {
            return false;
        }
        
        // Delete the bid
        $result = $wpdb->delete($this->table, array('id' => $bid_id), array('%d'));
        
        // If this was the winning bid, update the winning status
        if ($bid->is_winning) {
            // Find the next highest bid
            $next_highest = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table}
                 WHERE art_piece_id = %d
                 ORDER BY bid_amount DESC
                 LIMIT 1",
                $bid->art_piece_id
            ));
            
            if ($next_highest) {
                $wpdb->update(
                    $this->table,
                    array('is_winning' => 1),
                    array('id' => $next_highest->id),
                    array('%d'),
                    array('%d')
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Get all winning bids (for winners report)
     */
    public function get_all_winning_bids() {
        global $wpdb;
        
        $art_table = AIH_Database::get_table('art_pieces');
        $bidders_table = AIH_Database::get_table('bidders');
        $registrants_table = AIH_Database::get_table('registrants');
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        $now = current_time('mysql');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*,
                    a.art_id, a.title, a.artist, a.starting_bid,
                    a.auction_end, a.status as art_status,
                    COALESCE(bd.name_first, rg.name_first) as name_first,
                    COALESCE(bd.name_last, rg.name_last) as name_last,
                    COALESCE(bd.email_primary, rg.email_primary) as email_primary,
                    COALESCE(bd.phone_mobile, rg.phone_mobile) as phone_mobile,
                    COALESCE(bd.confirmation_code, rg.confirmation_code) as confirmation_code,
                    (SELECT oi2.id FROM $order_items_table oi2 WHERE oi2.art_piece_id = a.id LIMIT 1) IS NOT NULL as is_in_order,
                    (SELECT o2.order_number FROM $order_items_table oi2 JOIN $orders_table o2 ON oi2.order_id = o2.id WHERE oi2.art_piece_id = a.id ORDER BY o2.id DESC LIMIT 1) as order_number,
                    (SELECT o2.payment_status FROM $order_items_table oi2 JOIN $orders_table o2 ON oi2.order_id = o2.id WHERE oi2.art_piece_id = a.id ORDER BY o2.id DESC LIMIT 1) as payment_status,
                    (SELECT o2.pickup_status FROM $order_items_table oi2 JOIN $orders_table o2 ON oi2.order_id = o2.id WHERE oi2.art_piece_id = a.id ORDER BY o2.id DESC LIMIT 1) as pickup_status,
                    (SELECT o2.pickup_date FROM $order_items_table oi2 JOIN $orders_table o2 ON oi2.order_id = o2.id WHERE oi2.art_piece_id = a.id ORDER BY o2.id DESC LIMIT 1) as pickup_date,
                    CASE
                        WHEN a.status = 'ended' THEN 'ended'
                        WHEN a.auction_end <= %s THEN 'ended'
                        ELSE 'active'
                    END as auction_computed_status
             FROM {$this->table} b
             JOIN $art_table a ON b.art_piece_id = a.id
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
             LEFT JOIN $registrants_table rg ON b.bidder_id = rg.confirmation_code
             WHERE b.is_winning = 1
             ORDER BY a.auction_end DESC",
            $now
        ));
    }
    
    /**
     * Get bid statistics
     * Consolidated into a single query with conditional aggregation + caching
     */
    public function get_stats() {
        global $wpdb;

        // Check cache first
        if (class_exists('AIH_Cache')) {
            $cached = AIH_Cache::get('bid_stats');
            if ($cached !== null) {
                return $cached;
            }
        }

        // Single query with conditional aggregation (replaces 9 separate queries)
        $row = $wpdb->get_row(
            "SELECT
                COUNT(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN 1 END) AS total_bids,
                COUNT(CASE WHEN is_winning = 1 THEN 1 END) AS winning_bids,
                COUNT(CASE WHEN is_winning = 0 AND (bid_status = 'valid' OR bid_status IS NULL) THEN 1 END) AS outbid_bids,
                COUNT(CASE WHEN bid_status = 'too_low' THEN 1 END) AS rejected_bids,
                COUNT(DISTINCT CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bidder_id END) AS unique_bidders,
                COUNT(DISTINCT CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN art_piece_id END) AS unique_art_pieces,
                COALESCE(SUM(CASE WHEN is_winning = 1 THEN bid_amount ELSE 0 END), 0) AS total_bid_value,
                MAX(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bid_amount END) AS highest_bid,
                AVG(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bid_amount END) AS average_bid
            FROM {$this->table}"
        );

        $stats = new stdClass();
        $stats->total_bids = (int) ($row->total_bids ?? 0);
        $stats->winning_bids = (int) ($row->winning_bids ?? 0);
        $stats->outbid_bids = (int) ($row->outbid_bids ?? 0);
        $stats->rejected_bids = (int) ($row->rejected_bids ?? 0);
        $stats->unique_bidders = (int) ($row->unique_bidders ?? 0);
        $stats->unique_art_pieces = (int) ($row->unique_art_pieces ?? 0);
        $stats->total_bid_value = (float) ($row->total_bid_value ?? 0);
        $stats->highest_bid = (float) ($row->highest_bid ?? 0);
        $stats->average_bid = (float) ($row->average_bid ?? 0);

        // Cache for 2 minutes
        if (class_exists('AIH_Cache')) {
            AIH_Cache::set('bid_stats', $stats, 2 * MINUTE_IN_SECONDS, 'bids');
        }

        return $stats;
    }
}
