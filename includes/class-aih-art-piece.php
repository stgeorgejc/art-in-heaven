<?php
/**
 * Art Piece Model - Fixed & Enhanced
 * 
 * IMPORTANT: Auction is considered "active" when:
 * - status = 'active'
 * - auction_start <= NOW
 * - auction_end > NOW
 * 
 * An auction that hasn't started yet should show as "not started"
 * An auction that has ended (auction_end < NOW) should show as "ended"
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Art_Piece {
    
    private $table;
    
    public function __construct() {
        $this->table = AIH_Database::get_table('art_pieces');
    }
    
    /**
     * Get all art pieces with optional filtering
     * FIXED: Now properly checks both auction_start AND auction_end
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'orderby' => 'auction_end',
            'order' => 'ASC',
            'search' => '',
            'bidder_id' => '',
            'limit' => -1,
            'offset' => 0,
            'has_bids' => null,
            'tier' => '',
            'artist' => '',
            'medium' => '',
            'min_bid' => null,
            'max_bid' => null,
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Use WordPress current time for timezone-aware comparison
        $now = current_time('mysql');
        
        $where = array("1=1");
        $values = array();
        
        // Handle status filtering - FIXED to properly check start/end times
        // Also handles NULL values: NULL auction_start = already started, NULL auction_end = never ends
        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                // Active = status is active AND auction has started (or no start time) AND hasn't ended (or no end time)
                $where[] = "(a.status = 'active' AND (a.auction_start IS NULL OR a.auction_start <= %s) AND (a.auction_end IS NULL OR a.auction_end > %s))";
                $values[] = $now;
                $values[] = $now;
            } elseif ($args['status'] === 'upcoming') {
                // Upcoming = status is active but start time is in the future
                $where[] = "(a.status = 'active' AND a.auction_start IS NOT NULL AND a.auction_start > %s)";
                $values[] = $now;
            } elseif ($args['status'] === 'ended') {
                // Ended = status is ended OR auction_end has passed (but not if auction_end is NULL)
                $where[] = "(a.status = 'ended' OR (a.status = 'active' AND a.auction_end IS NOT NULL AND a.auction_end <= %s))";
                $values[] = $now;
            } else {
                $where[] = "a.status = %s";
                $values[] = $args['status'];
            }
        }
        
        // Search filter
        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(a.art_id LIKE %s OR a.title LIKE %s OR a.artist LIKE %s OR a.medium LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        // Tier filter
        if (!empty($args['tier'])) {
            $where[] = "a.tier = %s";
            $values[] = $args['tier'];
        }
        
        // Artist filter
        if (!empty($args['artist'])) {
            $where[] = "a.artist = %s";
            $values[] = $args['artist'];
        }
        
        // Medium filter
        if (!empty($args['medium'])) {
            $where[] = "a.medium = %s";
            $values[] = $args['medium'];
        }
        
        // Price range filters
        if ($args['min_bid'] !== null) {
            $where[] = "a.starting_bid >= %f";
            $values[] = floatval($args['min_bid']);
        }
        
        if ($args['max_bid'] !== null) {
            $where[] = "a.starting_bid <= %f";
            $values[] = floatval($args['max_bid']);
        }
        
        $where_clause = implode(' AND ', $where);
        
        $favorites_table = AIH_Database::get_table('favorites');
        $bids_table = AIH_Database::get_table('bids');
        $art_images_table = AIH_Database::get_table('art_images');
        $order_clause = "";
        
        if ($args['bidder_id']) {
            $order_clause = "CASE WHEN f.id IS NOT NULL THEN 0 ELSE 1 END ASC, ";
        }
        
        // Sanitize orderby
        $allowed_orderby = array('id', 'art_id', 'title', 'artist', 'starting_bid', 'auction_start', 'auction_end', 'created_at', 'tier');
        if (!in_array($args['orderby'], $allowed_orderby)) {
            $args['orderby'] = 'auction_end';
        }
        $args['order'] = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $order_clause .= "a.{$args['orderby']} {$args['order']}";
        
        $limit_clause = "";
        if ($args['limit'] > 0) {
            $limit_clause = "LIMIT " . intval($args['limit']);
            if ($args['offset'] > 0) {
                $limit_clause .= " OFFSET " . intval($args['offset']);
            }
        }
        
        $having_clause = "";
        if ($args['has_bids'] === true) {
            $having_clause = "HAVING bid_count > 0";
        } elseif ($args['has_bids'] === false) {
            $having_clause = "HAVING bid_count = 0";
        }
        
        // Build computed auction_status field (handles NULL dates)
        $auction_status_case = "CASE 
            WHEN a.status = 'draft' THEN 'draft'
            WHEN a.status = 'ended' THEN 'ended'
            WHEN a.auction_end IS NOT NULL AND a.auction_end <= %s THEN 'ended'
            WHEN a.auction_start IS NOT NULL AND a.auction_start > %s THEN 'upcoming'
            ELSE 'active'
        END";
        
        if ($args['bidder_id']) {
            $query = "SELECT a.*, 
                      CASE WHEN f.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite,
                      TIMESTAMPDIFF(SECOND, %s, a.auction_end) as seconds_remaining,
                      TIMESTAMPDIFF(SECOND, %s, a.auction_start) as seconds_until_start,
                      COUNT(DISTINCT b.id) as bid_count,
                      ($auction_status_case) as computed_status,
                      COALESCE(NULLIF(a.watermarked_url, ''), img.watermarked_url, NULLIF(a.image_url, ''), img.image_url) as display_image_url
                      FROM {$this->table} a
                      LEFT JOIN $favorites_table f ON a.id = f.art_piece_id AND f.bidder_id = %s
                      LEFT JOIN $bids_table b ON a.id = b.art_piece_id
                      LEFT JOIN (SELECT art_piece_id, image_url, watermarked_url FROM $art_images_table WHERE is_primary = 1 OR sort_order = 0 GROUP BY art_piece_id) img ON a.id = img.art_piece_id
                      WHERE $where_clause
                      GROUP BY a.id
                      $having_clause
                      ORDER BY $order_clause
                      $limit_clause";
            // Add values for computed_status CASE
            array_unshift($values, $args['bidder_id']);
            array_unshift($values, $now); // for auction_start > check
            array_unshift($values, $now); // for auction_end <= check
            array_unshift($values, $now); // seconds_until_start
            array_unshift($values, $now); // seconds_remaining
        } else {
            $query = "SELECT a.*, 
                      0 as is_favorite,
                      TIMESTAMPDIFF(SECOND, %s, a.auction_end) as seconds_remaining,
                      TIMESTAMPDIFF(SECOND, %s, a.auction_start) as seconds_until_start,
                      COUNT(DISTINCT b.id) as bid_count,
                      ($auction_status_case) as computed_status,
                      COALESCE(NULLIF(a.watermarked_url, ''), img.watermarked_url, NULLIF(a.image_url, ''), img.image_url) as display_image_url
                      FROM {$this->table} a
                      LEFT JOIN $bids_table b ON a.id = b.art_piece_id
                      LEFT JOIN (SELECT art_piece_id, image_url, watermarked_url FROM $art_images_table WHERE is_primary = 1 OR sort_order = 0 GROUP BY art_piece_id) img ON a.id = img.art_piece_id
                      WHERE $where_clause
                      GROUP BY a.id
                      $having_clause
                      ORDER BY $order_clause
                      $limit_clause";
            // Add values for computed_status CASE
            array_unshift($values, $now); // for auction_start > check
            array_unshift($values, $now); // for auction_end <= check
            array_unshift($values, $now); // seconds_until_start
            array_unshift($values, $now); // seconds_remaining
        }
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get count for filtered results
     */
    public function get_count($args = array()) {
        global $wpdb;
        
        $now = current_time('mysql');
        $where = array("1=1");
        $values = array();
        
        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                $where[] = "(status = 'active' AND auction_start <= %s AND auction_end > %s)";
                $values[] = $now;
                $values[] = $now;
            } elseif ($args['status'] === 'ended') {
                $where[] = "(status = 'ended' OR (status = 'active' AND auction_end <= %s))";
                $values[] = $now;
            } else {
                $where[] = "status = %s";
                $values[] = $args['status'];
            }
        }
        
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = "(art_id LIKE %s OR title LIKE %s OR artist LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        $where_clause = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE $where_clause";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return (int) $wpdb->get_var($query);
    }
    
    public function get($id) {
        global $wpdb;
        $now = current_time('mysql');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT *, 
             TIMESTAMPDIFF(SECOND, %s, auction_end) as seconds_remaining,
             TIMESTAMPDIFF(SECOND, %s, auction_start) as seconds_until_start,
             CASE 
                WHEN status = 'draft' THEN 'draft'
                WHEN status = 'ended' THEN 'ended'
                WHEN auction_end <= %s THEN 'ended'
                WHEN auction_start > %s THEN 'upcoming'
                ELSE 'active'
             END as computed_status
             FROM {$this->table} WHERE id = %d", 
            $now, $now, $now, $now, $id
        ));
    }
    
    public function get_by_art_id($art_id) {
        global $wpdb;
        $now = current_time('mysql');
        // Use UPPER() to make comparison case-insensitive
        $art_id_upper = strtoupper(trim($art_id));
        return $wpdb->get_row($wpdb->prepare(
            "SELECT *, 
             TIMESTAMPDIFF(SECOND, %s, auction_end) as seconds_remaining,
             TIMESTAMPDIFF(SECOND, %s, auction_start) as seconds_until_start,
             CASE 
                WHEN status = 'draft' THEN 'draft'
                WHEN status = 'ended' THEN 'ended'
                WHEN auction_end <= %s THEN 'ended'
                WHEN auction_start > %s THEN 'upcoming'
                ELSE 'active'
             END as computed_status
             FROM {$this->table} WHERE UPPER(art_id) = %s", 
            $now, $now, $now, $now, $art_id_upper
        ));
    }
    
    public function create($data) {
        global $wpdb;
        
        $defaults = array(
            'art_id' => '',
            'title' => '',
            'artist' => '',
            'medium' => '',
            'dimensions' => '',
            'description' => '',
            'starting_bid' => 0.00,
            'tier' => '',
            'image_id' => null,
            'image_url' => '',
            'watermarked_url' => '',
            'auction_start' => current_time('mysql'),
            'auction_end' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'show_end_time' => 0,
            'status' => 'active'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Art ID is required - don't auto-generate
        if (empty($data['art_id'])) {
            return false;
        }
        
        $art_id = strtoupper(trim($data['art_id']));
        
        // Check if art_id already exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE art_id = %s",
            $art_id
        ));
        
        if ($existing) {
            // Art ID already taken
            return false;
        }
        
        // Data is already sanitized by AJAX handler - just trim strings
        $result = $wpdb->insert($this->table, array(
            'art_id' => $art_id,
            'title' => trim($data['title']),
            'artist' => trim($data['artist']),
            'medium' => trim($data['medium']),
            'dimensions' => trim($data['dimensions']),
            'description' => $data['description'],
            'starting_bid' => floatval($data['starting_bid']),
            'tier' => trim($data['tier']),
            'image_id' => $data['image_id'] ? intval($data['image_id']) : null,
            'image_url' => esc_url_raw($data['image_url']),
            'watermarked_url' => esc_url_raw($data['watermarked_url']),
            'auction_start' => $data['auction_start'],
            'auction_end' => $data['auction_end'],
            'show_end_time' => intval($data['show_end_time']),
            'status' => trim($data['status'])
        ), array(
            '%s', // art_id
            '%s', // title
            '%s', // artist
            '%s', // medium
            '%s', // dimensions
            '%s', // description
            '%f', // starting_bid
            '%s', // tier
            '%d', // image_id
            '%s', // image_url
            '%s', // watermarked_url
            '%s', // auction_start
            '%s', // auction_end
            '%d', // show_end_time
            '%s'  // status
        ));
        
        if ($result) {
            do_action('aih_art_created', $wpdb->insert_id, $data);
        }
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public function update($id, $data) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        // Check if force_status is set (admin override)
        $force_status = isset($data['force_status']) && $data['force_status'];
        unset($data['force_status']); // Don't try to save this to the database
        
        $allowed = array(
            'art_id'=>'%s','title'=>'%s','artist'=>'%s','medium'=>'%s','dimensions'=>'%s','description'=>'%s',
            'starting_bid'=>'%f','tier'=>'%s','image_id'=>'%d','image_url'=>'%s','watermarked_url'=>'%s',
            'auction_start'=>'%s','auction_end'=>'%s','show_end_time'=>'%d','status'=>'%s'
        );
        
        foreach ($allowed as $field => $fmt) {
            if (array_key_exists($field, $data)) {
                // Handle null values - set to NULL in database
                if ($data[$field] === null) {
                    $update_data[$field] = null;
                    $format[] = '%s'; // NULL will be handled properly with %s
                } else {
                    $update_data[$field] = $data[$field];
                    $format[] = $fmt;
                }
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        // If force_status is true, skip all auto-status logic and use the admin's chosen status directly
        if ($force_status) {
            // Validate that the forced status is valid
            if (isset($update_data['status']) && class_exists('AIH_Status')) {
                if (!AIH_Status::is_valid_status($update_data['status'])) {
                    error_log("Art in Heaven: Invalid status value forced: " . $update_data['status']);
                }
            }
        } else {
            // Auto-status management using AIH_Status helper
            $times_changed = isset($update_data['auction_start']) || isset($update_data['auction_end']);
            $status_explicitly_set = isset($update_data['status']);
            
            if ($times_changed || $status_explicitly_set) {
                // Get current piece data
                $current = $wpdb->get_row($wpdb->prepare(
                    "SELECT auction_start, auction_end, status FROM {$this->table} WHERE id = %d",
                    $id
                ));
                
                if ($current) {
                    // Get the final times
                    $final_start = isset($update_data['auction_start']) 
                        ? $update_data['auction_start']
                        : $current->auction_start;
                    
                    $final_end = isset($update_data['auction_end']) 
                        ? $update_data['auction_end']
                        : $current->auction_end;
                    
                    $requested_status = $update_data['status'] ?? $current->status ?? 'active';
                    
                    // Use AIH_Status helper if available, otherwise fallback to basic logic
                    if (class_exists('AIH_Status')) {
                        $calculated_status = AIH_Status::calculate_auto_status($final_start, $final_end, $requested_status, $times_changed);
                        $update_data['status'] = $calculated_status;
                    } else {
                        // Fallback: basic time-based calculation
                        $now_mysql = current_time('mysql');
                        
                        if ($final_start > $now_mysql) {
                            $update_data['status'] = 'draft';
                        } elseif ($final_end <= $now_mysql) {
                            $update_data['status'] = 'ended';
                        } else {
                            $update_data['status'] = 'active';
                        }
                    }
                    
                    // Ensure status is in format array
                    if (!in_array('%s', $format)) {
                        $format[] = '%s';
                    }
                }
            }
        }
        
        $result = $wpdb->update($this->table, $update_data, array('id' => $id), $format, array('%d'));
        
        if ($result !== false) {
            do_action('aih_art_updated', $id, $data);
        }
        
        return $result;
    }
    
    public function delete($id) {
        global $wpdb;
        $wpdb->delete(AIH_Database::get_table('bids'), array('art_piece_id' => $id), array('%d'));
        $wpdb->delete(AIH_Database::get_table('favorites'), array('art_piece_id' => $id), array('%d'));
        return $wpdb->delete($this->table, array('id' => $id), array('%d'));
    }
    
    public function bulk_update_end_times($ids, $new_end_time) {
        global $wpdb;
        if (empty($ids)) return false;
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET auction_end = %s WHERE id IN ($placeholders)", array_merge(array($new_end_time), $ids)));
    }
    
    public function bulk_update_start_times($ids, $new_start_time) {
        global $wpdb;
        if (empty($ids)) return false;
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return $wpdb->query($wpdb->prepare("UPDATE {$this->table} SET auction_start = %s WHERE id IN ($placeholders)", array_merge(array($new_start_time), $ids)));
    }
    
    /**
     * Check if auction has ended
     * FIXED: Now properly checks auction_end time, not start time
     */
    public function is_auction_ended($id) {
        $piece = $this->get($id);
        if (!$piece) return true;
        return $piece->computed_status === 'ended';
    }
    
    /**
     * Check if auction is currently active (started and not ended)
     */
    public function is_auction_active($id) {
        $piece = $this->get($id);
        if (!$piece) return false;
        return $piece->computed_status === 'active';
    }
    
    /**
     * Get all art pieces with stats - FIXED to include ALL statuses
     */
    public function get_all_with_stats($args = array()) {
        global $wpdb;
        
        $defaults = array('status' => '', 'has_bids' => null, 'has_favorites' => null);
        $args = wp_parse_args($args, $defaults);
        
        $bids_table = AIH_Database::get_table('bids');
        $favorites_table = AIH_Database::get_table('favorites');
        $orders_table = AIH_Database::get_table('orders');
        $order_items_table = AIH_Database::get_table('order_items');
        $now = current_time('mysql');
        
        $where = "1=1";
        if (!empty($args['status'])) {
            if ($args['status'] === 'active') {
                $where .= $wpdb->prepare(" AND (a.status = 'active' AND a.auction_start <= %s AND a.auction_end > %s)", $now, $now);
            } elseif ($args['status'] === 'ended') {
                $where .= $wpdb->prepare(" AND (a.status = 'ended' OR (a.status = 'active' AND a.auction_end <= %s))", $now);
            } else {
                $where .= $wpdb->prepare(" AND a.status = %s", $args['status']);
            }
        }
        
        $having = "";
        if ($args['has_bids'] === true) $having = "HAVING total_bids > 0";
        elseif ($args['has_bids'] === false) $having = "HAVING total_bids = 0";
        elseif ($args['has_favorites'] === true) $having = "HAVING favorites_count > 0";
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, 
                    COUNT(DISTINCT b.bidder_id) as unique_bidders,
                    COUNT(b.id) as total_bids,
                    MAX(b.bid_time) as last_bid_time,
                    COALESCE(MAX(b.bid_amount), a.starting_bid) as current_bid,
                    (SELECT COUNT(*) FROM $favorites_table WHERE art_piece_id = a.id) as favorites_count,
                    TIMESTAMPDIFF(SECOND, %s, a.auction_end) as seconds_remaining,
                    TIMESTAMPDIFF(SECOND, %s, a.auction_start) as seconds_until_start,
                    o.payment_status,
                    o.pickup_status,
                    o.pickup_date,
                    CASE 
                        WHEN a.status = 'draft' THEN 'draft'
                        WHEN a.status = 'ended' THEN 'ended'
                        WHEN a.auction_end <= %s THEN 'ended'
                        WHEN a.auction_start > %s THEN 'upcoming'
                        ELSE 'active'
                    END as computed_status
             FROM {$this->table} a
             LEFT JOIN $bids_table b ON a.id = b.art_piece_id
             LEFT JOIN $order_items_table oi ON a.id = oi.art_piece_id
             LEFT JOIN $orders_table o ON oi.order_id = o.id
             WHERE $where
             GROUP BY a.id
             $having
             ORDER BY a.auction_end ASC",
            $now, $now, $now, $now
        ));
    }
    
    public function get_counts() {
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        $now = current_time('mysql');
        
        $counts = new stdClass();
        $counts->total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        
        // Active = started AND not ended (handles NULL dates)
        $counts->active = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'active' AND (auction_start IS NULL OR auction_start <= %s) AND (auction_end IS NULL OR auction_end > %s)", $now, $now
        ));
        
        // Upcoming = not started yet (only if auction_start is set and in future)
        $counts->upcoming = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'active' AND auction_start IS NOT NULL AND auction_start > %s", $now
        ));
        
        $counts->draft = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'draft'");
        
        // Ended = status ended OR auction_end has passed (but not if auction_end is NULL)
        $counts->ended = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'ended' OR (status = 'active' AND auction_end IS NOT NULL AND auction_end <= %s)", $now
        ));
        
        // Active with bids (handles NULL dates)
        $counts->active_with_bids = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT a.id) FROM {$this->table} a 
             INNER JOIN $bids_table b ON a.id = b.art_piece_id 
             WHERE a.status = 'active' AND (a.auction_start IS NULL OR a.auction_start <= %s) AND (a.auction_end IS NULL OR a.auction_end > %s)", $now, $now
        ));
        
        // Active without bids (handles NULL dates)
        $counts->active_no_bids = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT a.id) FROM {$this->table} a 
             LEFT JOIN $bids_table b ON a.id = b.art_piece_id 
             WHERE a.status = 'active' AND (a.auction_start IS NULL OR a.auction_start <= %s) AND (a.auction_end IS NULL OR a.auction_end > %s) AND b.id IS NULL", $now, $now
        ));
        
        // All pieces with bids (regardless of status)
        $counts->pieces_with_bids = $wpdb->get_var(
            "SELECT COUNT(DISTINCT art_piece_id) FROM $bids_table"
        );
        
        // Bid rate percentage (pieces with at least one bid / total pieces)
        $counts->bid_rate_percent = $counts->total > 0 ? round(($counts->pieces_with_bids / $counts->total) * 100) : 0;
        
        // Items with favorites
        $favorites_table = AIH_Database::get_table('favorites');
        $counts->with_favorites = $wpdb->get_var(
            "SELECT COUNT(DISTINCT f.art_piece_id) FROM $favorites_table f
             INNER JOIN {$this->table} a ON f.art_piece_id = a.id"
        );
        
        return $counts;
    }
    
    public function update_expired_auctions() {
        global $wpdb;
        $now = current_time('mysql');
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET status = 'ended' WHERE auction_end < %s AND status = 'active'",
            $now
        ));
    }
    
    /**
     * Auto-activate auctions that have reached their start time
     * Changes status from 'draft' to 'active' when auction_start <= now
     */
    public function auto_activate_auctions() {
        global $wpdb;
        $now = current_time('mysql');
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET status = 'active' WHERE auction_start <= %s AND auction_end > %s AND status = 'draft'",
            $now,
            $now
        ));
    }
    
    /**
     * Auto-draft future auctions
     * Changes status from 'active' to 'draft' when auction_start is in the future
     * This handles cases where admin extends the start time
     */
    public function auto_draft_future_auctions() {
        global $wpdb;
        $now = current_time('mysql');
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET status = 'draft' WHERE auction_start > %s AND status = 'active'",
            $now
        ));
    }
    
    public function get_reporting_stats() {
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        $now = current_time('mysql');
        
        $stats = new stdClass();
        $stats->total_pieces = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $stats->active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'active' AND (auction_start IS NULL OR auction_start <= %s) AND (auction_end IS NULL OR auction_end > %s)", $now, $now
        ));
        $stats->draft_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'draft'");
        $stats->ended_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = 'ended' OR (auction_end IS NOT NULL AND auction_end <= %s)", $now
        ));
        $stats->total_bids = $wpdb->get_var("SELECT COUNT(*) FROM $bids_table");
        $stats->unique_bidders = $wpdb->get_var("SELECT COUNT(DISTINCT bidder_id) FROM $bids_table");
        $stats->pieces_with_bids = $wpdb->get_var("SELECT COUNT(DISTINCT art_piece_id) FROM $bids_table");
        $stats->total_starting_value = $wpdb->get_var("SELECT SUM(starting_bid) FROM {$this->table}");
        $stats->highest_bid = $wpdb->get_var("SELECT MAX(bid_amount) FROM $bids_table");
        $stats->average_bid = $wpdb->get_var("SELECT AVG(bid_amount) FROM $bids_table");
        
        $stats->top_pieces = $wpdb->get_results(
            "SELECT a.*, COUNT(b.id) as bid_count, MAX(b.bid_amount) as highest_bid
             FROM {$this->table} a LEFT JOIN $bids_table b ON a.id = b.art_piece_id
             GROUP BY a.id ORDER BY bid_count DESC LIMIT 10"
        );
        
        return $stats;
    }
}
