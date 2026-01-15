<?php
/**
 * Image Watermark Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Watermark {
    
    private $opacity = 50;
    private $font_size = 24;
    
    /**
     * Check if GD library is available
     */
    public function is_available() {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }
    
    /**
     * Get watermark text - uses logo text + current year
     */
    private function get_watermark_text() {
        $custom_text = get_option('aih_watermark_text', '');
        if (!empty($custom_text)) {
            return $custom_text . ' ' . date('Y');
        }
        return 'Art in Heaven ' . date('Y');
    }
    
    /**
     * Apply watermark to an image
     */
    public function apply_watermark($image_path, $output_path = null) {
        // Check if GD is available
        if (!$this->is_available()) {
            error_log('Art in Heaven: GD library not available for watermarking');
            return false;
        }
        
        if (!file_exists($image_path)) {
            error_log('Art in Heaven: Image file not found: ' . $image_path);
            return false;
        }
        
        // Check file is readable
        if (!is_readable($image_path)) {
            error_log('Art in Heaven: Image file not readable: ' . $image_path);
            return false;
        }
        
        // Get image info
        $image_info = @getimagesize($image_path);
        if (!$image_info) {
            error_log('Art in Heaven: Could not get image info: ' . $image_path);
            return false;
        }
        
        $mime_type = $image_info['mime'];
        $width = $image_info[0];
        $height = $image_info[1];
        
        // Skip very large images to prevent memory issues
        if ($width * $height > 25000000) { // ~25 megapixels
            error_log('Art in Heaven: Image too large to watermark: ' . $image_path . ' (' . $width . 'x' . $height . ')');
            return false;
        }
        
        // Create image resource based on type
        $image = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($image_path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($image_path);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($image_path);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($image_path);
                }
                break;
            default:
                error_log('Art in Heaven: Unsupported image type: ' . $mime_type);
                return false;
        }
        
        if (!$image) {
            error_log('Art in Heaven: Failed to create image resource: ' . $image_path);
            return false;
        }
        
        // Apply watermarks
        $this->add_diagonal_watermarks($image, $width, $height);
        
        // Determine output path
        if (!$output_path) {
            $path_info = pathinfo($image_path);
            $output_path = $path_info['dirname'] . '/watermarked/' . $path_info['basename'];
        }
        
        // Create directory if needed
        $output_dir = dirname($output_path);
        if (!file_exists($output_dir)) {
            if (!wp_mkdir_p($output_dir)) {
                error_log('Art in Heaven: Could not create output directory: ' . $output_dir);
                imagedestroy($image);
                return false;
            }
        }
        
        // Check directory is writable
        if (!is_writable($output_dir)) {
            error_log('Art in Heaven: Output directory not writable: ' . $output_dir);
            imagedestroy($image);
            return false;
        }
        
        // Save watermarked image
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = @imagejpeg($image, $output_path, 90);
                break;
            case 'image/png':
                imagesavealpha($image, true);
                $result = @imagepng($image, $output_path, 6);
                break;
            case 'image/gif':
                $result = @imagegif($image, $output_path);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    $result = @imagewebp($image, $output_path, 90);
                }
                break;
        }
        
        imagedestroy($image);
        
        if (!$result) {
            error_log('Art in Heaven: Failed to save watermarked image: ' . $output_path);
            return false;
        }
        
        return $output_path;
    }
    
    /**
     * Add diagonal watermarks across the image
     */
    private function add_diagonal_watermarks($image, $width, $height) {
        // Get watermark overlay image if set
        $overlay_id = get_option('aih_watermark_overlay_id', '');
        $overlay_path = '';
        if ($overlay_id) {
            $overlay_path = get_attached_file($overlay_id);
        }
        
        // Apply overlay image if available
        if ($overlay_path && file_exists($overlay_path)) {
            $this->apply_overlay_pattern($image, $overlay_path, $width, $height);
        }
        
        // Check if text watermark is enabled
        $text_enabled = get_option('aih_watermark_text_enabled', 1);
        
        if ($text_enabled) {
            // Get watermark text
            $text = $this->get_watermark_text();
            
            // Check for TTF font
            $font_file = AIH_PLUGIN_DIR . 'assets/fonts/OpenSans-Bold.ttf';
            $use_ttf = file_exists($font_file) && function_exists('imagettftext');
            
            if ($use_ttf) {
                // Calculate font size based on image dimensions
                $base_size = min($width, $height) / 4;
                $font_size = max(40, min(150, $base_size));
                
                // Calculate spacing
                $spacing_x = $width / 1.5;
                $spacing_y = $height / 2;
                
                // Add watermarks in a grid pattern
                for ($y_pos = 0; $y_pos < $height * 1.5; $y_pos += $spacing_y) {
                    for ($x_pos = -$width * 0.5; $x_pos < $width * 1.5; $x_pos += $spacing_x) {
                        // Shadow
                        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
                        @imagettftext($image, $font_size, 30, $x_pos + 3, $y_pos + 3, $shadow, $font_file, $text);
                        
                        // White text
                        $white = imagecolorallocatealpha($image, 255, 255, 255, 50);
                        @imagettftext($image, $font_size, 30, $x_pos, $y_pos, $white, $font_file, $text);
                    }
                }
                
                // Add center watermark
                $this->add_center_watermark($image, $width, $height, $text, $font_file);
            } else {
                // Fallback: use built-in GD fonts
                $this->add_builtin_font_watermark($image, $width, $height, $text);
            }
        }
        
        // Cross-hatch is now disabled (diagonal lines removed)
    }
    
    /**
     * Add watermark using built-in GD fonts (no TTF required)
     */
    private function add_builtin_font_watermark($image, $width, $height, $text) {
        $font = 5; // Largest built-in font
        
        // Colors
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 60);
        $white = imagecolorallocatealpha($image, 255, 255, 255, 50);
        
        // Calculate spacing
        $text_width = strlen($text) * imagefontwidth($font);
        $spacing_x = max($text_width + 50, $width / 3);
        $spacing_y = $height / 4;
        
        // Grid of watermarks
        for ($y = 0; $y < $height; $y += $spacing_y) {
            for ($x = -$text_width; $x < $width + $text_width; $x += $spacing_x) {
                // Shadow
                imagestring($image, $font, $x + 2, $y + 2, $text, $shadow);
                // Text
                imagestring($image, $font, $x, $y, $text, $white);
            }
        }
        
        // Center watermark
        $center_x = ($width - $text_width) / 2;
        $center_y = ($height - imagefontheight($font)) / 2;
        
        // Larger center text - draw multiple times
        for ($i = 0; $i < 3; $i++) {
            imagestring($image, $font, $center_x + 2 + $i, $center_y + 2, $text, $shadow);
        }
        imagestring($image, $font, $center_x, $center_y, $text, $white);
    }
    
    /**
     * Apply an overlay image pattern across the entire image
     */
    private function apply_overlay_pattern($image, $overlay_path, $width, $height) {
        $overlay_info = @getimagesize($overlay_path);
        if (!$overlay_info) {
            error_log('AIH Watermark: Could not get overlay image info from: ' . $overlay_path);
            return;
        }
        
        $overlay_width = $overlay_info[0];
        $overlay_height = $overlay_info[1];
        $is_png = ($overlay_info['mime'] === 'image/png');
        
        // Load overlay image based on type
        $overlay = null;
        switch ($overlay_info['mime']) {
            case 'image/png':
                $overlay = @imagecreatefrompng($overlay_path);
                break;
            case 'image/jpeg':
                $overlay = @imagecreatefromjpeg($overlay_path);
                break;
            case 'image/gif':
                $overlay = @imagecreatefromgif($overlay_path);
                break;
            default:
                error_log('AIH Watermark: Unsupported overlay format: ' . $overlay_info['mime']);
                return;
        }
        
        if (!$overlay) {
            error_log('AIH Watermark: Could not load overlay image');
            return;
        }
        
        // Preserve alpha channel on source
        if ($is_png) {
            imagealphablending($overlay, false);
            imagesavealpha($overlay, true);
        }
        
        // Scale overlay to about 1/4 of image width
        $target_width = max(100, $width / 4);
        $scale = $target_width / $overlay_width;
        $new_width = intval($overlay_width * $scale);
        $new_height = intval($overlay_height * $scale);
        
        // Create a scaled version of the overlay with full alpha support
        $scaled_overlay = imagecreatetruecolor($new_width, $new_height);
        
        // Critical: Set up alpha channel properly for scaled image
        imagealphablending($scaled_overlay, false);
        imagesavealpha($scaled_overlay, true);
        
        // Fill with fully transparent background
        $transparent = imagecolorallocatealpha($scaled_overlay, 0, 0, 0, 127);
        imagefilledrectangle($scaled_overlay, 0, 0, $new_width - 1, $new_height - 1, $transparent);
        
        // Scale the overlay (preserves alpha when alphablending is false)
        imagecopyresampled(
            $scaled_overlay, $overlay,
            0, 0, 0, 0,
            $new_width, $new_height,
            $overlay_width, $overlay_height
        );
        
        // Now apply opacity to the scaled overlay by adjusting alpha values
        $opacity = 50; // 0-100, percentage of opacity to apply
        if ($is_png) {
            $this->apply_opacity_to_image($scaled_overlay, $new_width, $new_height, $opacity);
        }
        
        // Enable alpha blending on main image for proper compositing
        imagealphablending($image, true);
        
        // Tile the overlay across the image
        $spacing_x = intval($new_width * 1.8);
        $spacing_y = intval($new_height * 1.8);
        
        // First pass - main grid
        for ($y_pos = 0; $y_pos < $height; $y_pos += $spacing_y) {
            for ($x_pos = 0; $x_pos < $width; $x_pos += $spacing_x) {
                if ($is_png) {
                    // Use imagecopy which respects alpha channel when alphablending is true
                    imagecopy($image, $scaled_overlay, $x_pos, $y_pos, 0, 0, $new_width, $new_height);
                } else {
                    imagecopymerge($image, $scaled_overlay, $x_pos, $y_pos, 0, 0, $new_width, $new_height, $opacity);
                }
            }
        }
        
        // Second pass - offset grid for denser coverage
        $offset_x = intval($spacing_x / 2);
        $offset_y = intval($spacing_y / 2);
        for ($y_pos = $offset_y; $y_pos < $height; $y_pos += $spacing_y) {
            for ($x_pos = $offset_x; $x_pos < $width; $x_pos += $spacing_x) {
                if ($is_png) {
                    imagecopy($image, $scaled_overlay, $x_pos, $y_pos, 0, 0, $new_width, $new_height);
                } else {
                    imagecopymerge($image, $scaled_overlay, $x_pos, $y_pos, 0, 0, $new_width, $new_height, $opacity);
                }
            }
        }
        
        imagedestroy($overlay);
        imagedestroy($scaled_overlay);
    }
    
    /**
     * Apply opacity to an image by adjusting alpha values of each pixel
     */
    private function apply_opacity_to_image($image, $width, $height, $opacity_percent) {
        // Disable alpha blending to directly set pixel values
        imagealphablending($image, false);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                
                $alpha = ($rgba >> 24) & 0x7F;  // 0 = opaque, 127 = transparent
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                
                // Only process non-fully-transparent pixels
                if ($alpha < 127) {
                    // Calculate new alpha based on opacity percentage
                    // Current opacity = (127 - alpha) / 127
                    // New opacity = current_opacity * (opacity_percent / 100)
                    $current_opacity = (127 - $alpha) / 127.0;
                    $new_opacity = $current_opacity * ($opacity_percent / 100.0);
                    $new_alpha = intval(127 - ($new_opacity * 127));
                    $new_alpha = max(0, min(127, $new_alpha));
                    
                    $new_color = imagecolorallocatealpha($image, $r, $g, $b, $new_alpha);
                    imagesetpixel($image, $x, $y, $new_color);
                }
            }
        }
        
        // Re-enable alpha blending
        imagealphablending($image, true);
    }
    
    /**
     * Add cross-hatch pattern for extra protection (DISABLED - removed diagonal lines)
     */
    private function add_crosshatch_pattern($image, $width, $height) {
        // Diagonal lines removed per user request
        // This function intentionally left empty
        return;
    }
    
    /**
     * Add prominent center watermark
     */
    private function add_center_watermark($image, $width, $height, $text, $font_file = null) {
        $center_x = $width / 2;
        $center_y = $height / 2;
        
        // Large center text
        $font_size = min($width, $height) / 5;
        $font_size = max(40, min(120, $font_size));
        
        if ($font_file && function_exists('imagettftext')) {
            // Calculate text bounding box for centering
            $bbox = imagettfbbox($font_size, 0, $font_file, $text);
            $text_width = abs($bbox[4] - $bbox[0]);
            $text_height = abs($bbox[5] - $bbox[1]);
            
            $x = $center_x - ($text_width / 2);
            $y = $center_y + ($text_height / 2);
            
            // Strong shadow
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 30);
            for ($s = 6; $s >= 1; $s--) {
                imagettftext($image, $font_size, 0, $x + $s, $y + $s, $shadow, $font_file, $text);
            }
            
            // White text
            $white = imagecolorallocatealpha($image, 255, 255, 255, 15);
            imagettftext($image, $font_size, 0, $x, $y, $white, $font_file, $text);
        } else {
            // Fallback with built-in fonts
            $font = 5;
            $char_width = imagefontwidth($font);
            $text_width = strlen($text) * $char_width;
            $x = $center_x - ($text_width / 2);
            $y = $center_y - 5;
            
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 40);
            $white = imagecolorallocatealpha($image, 255, 255, 255, 30);
            
            // Draw multiple times for bold effect
            for ($i = 0; $i < 3; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    imagestring($image, $font, $x + $i, $y + $j, $text, $shadow);
                }
            }
            imagestring($image, $font, $x, $y, $text, $white);
        }
    }
    
    /**
     * Draw large watermark text without TTF fonts
     */
    private function draw_large_watermark($image, $text, $x, $y, $img_width, $img_height) {
        // Use largest built-in font (5)
        $font = 5;
        $char_width = imagefontwidth($font);
        $char_height = imagefontheight($font);
        
        // Colors - MORE VISIBLE
        $white = imagecolorallocatealpha($image, 255, 255, 255, 25); // More opaque
        $shadow = imagecolorallocatealpha($image, 0, 0, 0, 35); // More opaque
        
        // Draw text block multiple times for visibility
        $lines = array($text, $text, $text);
        
        $line_y = $y;
        foreach ($lines as $line) {
            // Heavy shadow
            for ($s = 3; $s >= 1; $s--) {
                imagestring($image, $font, $x + $s, $line_y + $s, $line, $shadow);
            }
            // Main text
            imagestring($image, $font, $x, $line_y, $line, $white);
            $line_y += $char_height + 8;
        }
        
        // Add X pattern through this block
        $line_color = imagecolorallocatealpha($image, 255, 255, 255, 50);
        imageline($image, $x, $y, $x + 150, $y + 80, $line_color);
        imageline($image, $x + 150, $y, $x, $y + 80, $line_color);
    }
    
    /**
     * Process uploaded image and create watermarked version
     */
    public function process_upload($attachment_id) {
        if (!$this->is_available()) {
            error_log('Art in Heaven: GD library not available - cannot watermark image');
            return false;
        }
        
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            error_log('Art in Heaven: Could not get file path for attachment ID ' . $attachment_id);
            return false;
        }
        
        if (!file_exists($file_path)) {
            error_log('Art in Heaven: File does not exist: ' . $file_path);
            return false;
        }
        
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $aih_upload_dir = $upload_dir['basedir'] . '/art-in-heaven/watermarked';
        
        if (!file_exists($aih_upload_dir)) {
            $created = wp_mkdir_p($aih_upload_dir);
            if (!$created) {
                error_log('Art in Heaven: Could not create watermark directory: ' . $aih_upload_dir);
                return false;
            }
        }
        
        // Check if directory is writable
        if (!is_writable($aih_upload_dir)) {
            error_log('Art in Heaven: Watermark directory is not writable: ' . $aih_upload_dir);
            return false;
        }
        
        // Generate output filename
        $filename = basename($file_path);
        $output_path = $aih_upload_dir . '/' . $filename;
        
        // Apply watermark
        $result = $this->apply_watermark($file_path, $output_path);
        
        if ($result) {
            // Return URL to watermarked image
            return $upload_dir['baseurl'] . '/art-in-heaven/watermarked/' . $filename;
        }
        
        error_log('Art in Heaven: Watermark creation failed for: ' . $file_path);
        return false;
    }
    
    /**
     * Get watermarked URL for an attachment
     */
    public function get_watermarked_url($attachment_id) {
        $upload_dir = wp_upload_dir();
        $file_path = get_attached_file($attachment_id);
        $filename = basename($file_path);
        
        $watermarked_path = $upload_dir['basedir'] . '/art-in-heaven/watermarked/' . $filename;
        
        if (file_exists($watermarked_path)) {
            return $upload_dir['baseurl'] . '/art-in-heaven/watermarked/' . $filename;
        }
        
        // Create watermarked version if it doesn't exist
        return $this->process_upload($attachment_id);
    }
    
    /**
     * Delete watermarked version
     */
    public function delete_watermarked($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $filename = basename($file_path);
        
        $upload_dir = wp_upload_dir();
        $watermarked_path = $upload_dir['basedir'] . '/art-in-heaven/watermarked/' . $filename;
        
        if (file_exists($watermarked_path)) {
            return unlink($watermarked_path);
        }
        
        return true;
    }
}
