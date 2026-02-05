<?php
/**
 * Art Images Handler - Multiple images per art piece
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Art_Images {
    
    private $table;
    
    public function __construct() {
        $this->table = AIH_Database::get_table('art_images');
    }
    
    /**
     * Add image to art piece
     */
    public function add_image($art_piece_id, $image_id, $image_url, $watermarked_url = '', $is_primary = false) {
        global $wpdb;
        
        // If this is primary, unset other primaries first
        if ($is_primary) {
            $wpdb->update(
                $this->table,
                array('is_primary' => 0),
                array('art_piece_id' => $art_piece_id),
                array('%d'),
                array('%d')
            );
        }
        
        // Get max sort order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$this->table} WHERE art_piece_id = %d",
            $art_piece_id
        ));
        $sort_order = ($max_order !== null) ? $max_order + 1 : 0;
        
        // If no images exist yet, make this one primary
        $count = $this->get_image_count($art_piece_id);
        if ($count == 0) {
            $is_primary = true;
        }
        
        $result = $wpdb->insert(
            $this->table,
            array(
                'art_piece_id' => $art_piece_id,
                'image_id' => $image_id,
                'image_url' => $image_url,
                'watermarked_url' => $watermarked_url ?: $image_url,
                'sort_order' => $sort_order,
                'is_primary' => $is_primary ? 1 : 0
            ),
            array('%d', '%d', '%s', '%s', '%d', '%d')
        );
        
        if ($result) {
            // Update art piece's main image fields if this is primary
            if ($is_primary) {
                $this->sync_primary_to_art_piece($art_piece_id);
            }
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Remove image from art piece
     */
    public function remove_image($image_record_id) {
        global $wpdb;
        
        error_log('AIH Art Images: remove_image called for record ID: ' . $image_record_id);
        
        // Get the image record first
        $image = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $image_record_id
        ));
        
        if (!$image) {
            error_log('AIH Art Images: No image found with ID: ' . $image_record_id . ' in table: ' . $this->table);
            return false;
        }
        
        $art_piece_id = $image->art_piece_id;
        $was_primary = $image->is_primary;
        $deleted_image_url = $image->image_url;
        $deleted_watermarked_url = $image->watermarked_url;
        
        error_log('AIH Art Images: Found image for art_piece_id: ' . $art_piece_id . ', was_primary: ' . $was_primary);
        
        // Delete the record from art_images table
        $result = $wpdb->delete(
            $this->table,
            array('id' => $image_record_id),
            array('%d')
        );
        
        error_log('AIH Art Images: Delete result: ' . ($result ? 'success' : 'failed') . ', wpdb error: ' . $wpdb->last_error);
        
        if ($result) {
            // Delete watermarked file if it exists
            if (!empty($deleted_watermarked_url)) {
                $upload_dir = wp_upload_dir();
                $watermarked_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $deleted_watermarked_url);
                if (file_exists($watermarked_path)) {
                    @unlink($watermarked_path);
                    error_log('AIH Art Images: Deleted watermarked file: ' . $watermarked_path);
                }
            }
            
            $art_table = AIH_Database::get_table('art_pieces');
            
            // Check if there are remaining images for this art piece
            $remaining = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE art_piece_id = %d ORDER BY is_primary DESC, sort_order ASC",
                $art_piece_id
            ));
            
            error_log('AIH Art Images: Remaining images count: ' . count($remaining));
            
            if (!empty($remaining)) {
                // There are still images left
                $first = $remaining[0];
                
                if ($was_primary) {
                    // The deleted image was primary, so set the first remaining as primary
                    error_log('AIH Art Images: Setting new primary image: ' . $first->id);
                    
                    // Set this image as primary in art_images table
                    $wpdb->update(
                        $this->table,
                        array('is_primary' => 1),
                        array('id' => $first->id),
                        array('%d'),
                        array('%d')
                    );
                    
                    // Update art_pieces table with new primary image
                    $wpdb->update(
                        $art_table,
                        array(
                            'image_id' => $first->image_id,
                            'image_url' => $first->image_url,
                            'watermarked_url' => $first->watermarked_url
                        ),
                        array('id' => $art_piece_id),
                        array('%d', '%s', '%s'),
                        array('%d')
                    );
                    
                    error_log('AIH Art Images: Updated art_pieces with new primary');
                }
            } else {
                // No images left - clear art piece image fields completely
                error_log('AIH Art Images: No images left, clearing art_pieces image fields for ID: ' . $art_piece_id);
                
                $update_result = $wpdb->update(
                    $art_table,
                    array(
                        'image_id' => 0,
                        'image_url' => '',
                        'watermarked_url' => ''
                    ),
                    array('id' => $art_piece_id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );
                
                error_log('AIH Art Images: Clear art_pieces result: ' . ($update_result !== false ? 'success' : 'failed'));
            }
            
            // Clear any caches
            wp_cache_delete('aih_art_piece_' . $art_piece_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Set image as primary
     */
    public function set_primary($image_record_id) {
        global $wpdb;
        
        // Get the image record
        $image = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $image_record_id
        ));
        
        if (!$image) {
            return false;
        }
        
        // Unset all primaries for this art piece
        $wpdb->update(
            $this->table,
            array('is_primary' => 0),
            array('art_piece_id' => $image->art_piece_id),
            array('%d'),
            array('%d')
        );
        
        // Set this one as primary
        $wpdb->update(
            $this->table,
            array('is_primary' => 1),
            array('id' => $image_record_id),
            array('%d'),
            array('%d')
        );
        
        // Sync to art piece table
        $this->sync_primary_to_art_piece($image->art_piece_id);
        
        return true;
    }
    
    /**
     * Sync primary image to art piece's main image fields
     */
    public function sync_primary_to_art_piece($art_piece_id) {
        global $wpdb;
        
        $primary = $this->get_primary_image($art_piece_id);
        $art_table = AIH_Database::get_table('art_pieces');
        
        if ($primary) {
            $wpdb->update(
                $art_table,
                array(
                    'image_id' => $primary->image_id,
                    'image_url' => $primary->image_url,
                    'watermarked_url' => $primary->watermarked_url
                ),
                array('id' => $art_piece_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
        } else {
            // No primary image found - clear the fields
            $wpdb->update(
                $art_table,
                array(
                    'image_id' => 0,
                    'image_url' => '',
                    'watermarked_url' => ''
                ),
                array('id' => $art_piece_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Get all images for an art piece
     */
    public function get_images($art_piece_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE art_piece_id = %d ORDER BY is_primary DESC, sort_order ASC",
            $art_piece_id
        ));
    }
    
    /**
     * Get primary image for an art piece
     */
    public function get_primary_image($art_piece_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE art_piece_id = %d AND is_primary = 1 LIMIT 1",
            $art_piece_id
        ));
    }
    
    /**
     * Get image count for an art piece
     */
    public function get_image_count($art_piece_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE art_piece_id = %d",
            $art_piece_id
        ));
    }
    
    /**
     * Update sort order
     */
    public function update_order($image_ids) {
        global $wpdb;
        
        foreach ($image_ids as $order => $id) {
            $wpdb->update(
                $this->table,
                array('sort_order' => $order),
                array('id' => $id),
                array('%d'),
                array('%d')
            );
        }
        
        return true;
    }
    
    /**
     * Migrate existing single images to new table
     */
    public function migrate_existing_images() {
        global $wpdb;
        
        $art_table = AIH_Database::get_table('art_pieces');
        
        // Get all art pieces with images
        $pieces = $wpdb->get_results(
            "SELECT id, image_id, image_url, watermarked_url FROM {$art_table} WHERE image_url IS NOT NULL AND image_url != ''"
        );
        
        $migrated = 0;
        
        foreach ($pieces as $piece) {
            // Check if already migrated
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE art_piece_id = %d",
                $piece->id
            ));
            
            if ($existing == 0 && !empty($piece->image_url)) {
                $this->add_image(
                    $piece->id,
                    $piece->image_id,
                    $piece->image_url,
                    $piece->watermarked_url,
                    true // Set as primary
                );
                $migrated++;
            }
        }
        
        return $migrated;
    }
}
