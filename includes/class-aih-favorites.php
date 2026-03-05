<?php
/**
 * Favorites Model - Uses bidder_id (email)
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Favorites {
    
    private $table;
    
    public function __construct() {
        $this->table = AIH_Database::get_table('favorites');
    }
    
    /**
     * Add to favorites
     * @param mixed $bidder_id
     * @param mixed $art_piece_id
     * @return mixed
     */
    public function add($bidder_id, $art_piece_id) {
        global $wpdb;
        
        // Check if already exists
        if ($this->is_favorite($bidder_id, $art_piece_id)) {
            return true;
        }
        
        $result = $wpdb->insert(
            $this->table,
            array(
                'bidder_id' => $bidder_id,
                'art_piece_id' => $art_piece_id,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Remove from favorites
     * @param mixed $bidder_id
     * @param mixed $art_piece_id
     * @return mixed
     */
    public function remove($bidder_id, $art_piece_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table,
            array(
                'bidder_id' => $bidder_id,
                'art_piece_id' => $art_piece_id
            ),
            array('%s', '%d')
        );
    }
    
    /**
     * Toggle favorite
     * @param mixed $bidder_id
     * @param mixed $art_piece_id
     * @return array
     */
    public function toggle($bidder_id, $art_piece_id) {
        // Debounce: prevent rapid duplicate toggles with a 2-second transient lock
        $lock_key = 'aih_fav_lock_' . md5($bidder_id . '_' . $art_piece_id);
        if (get_transient($lock_key)) {
            // Return current state without toggling
            $is_fav = $this->is_favorite($bidder_id, $art_piece_id);
            return array(
                'action' => 'debounced',
                'is_favorite' => $is_fav
            );
        }
        set_transient($lock_key, 1, 2);

        if ($this->is_favorite($bidder_id, $art_piece_id)) {
            $this->remove($bidder_id, $art_piece_id);
            return array(
                'action' => 'removed',
                'is_favorite' => false
            );
        } else {
            $this->add($bidder_id, $art_piece_id);
            return array(
                'action' => 'added',
                'is_favorite' => true
            );
        }
    }
    
    /**
     * Check if art piece is favorite
     * @param mixed $bidder_id
     * @param mixed $art_piece_id
     * @return mixed
     */
    public function is_favorite($bidder_id, $art_piece_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE bidder_id = %s AND art_piece_id = %d",
            $bidder_id,
            $art_piece_id
        ));
        
        return $result !== null;
    }
    
    /**
     * Get all favorites for bidder
     * @param mixed $bidder_id
     * @return mixed
     */
    public function get_bidder_favorites($bidder_id) {
        global $wpdb;
        
        $art_table = AIH_Database::get_table('art_pieces');
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, f.created_at as favorited_at
             FROM {$this->table} f
             JOIN $art_table a ON f.art_piece_id = a.id
             WHERE f.bidder_id = %s
             ORDER BY f.created_at DESC",
            $bidder_id
        ));
    }
    
    /**
     * Get favorite art piece IDs for bidder
     * @param mixed $bidder_id
     * @return mixed
     */
    public function get_bidder_favorite_ids($bidder_id) {
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT art_piece_id FROM {$this->table} WHERE bidder_id = %s",
            $bidder_id
        ));
    }
    
    /**
     * Get favorite count for art piece
     * @param mixed $art_piece_id
     * @return mixed
     */
    public function get_favorite_count($art_piece_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE art_piece_id = %d",
            $art_piece_id
        ));
    }
}
