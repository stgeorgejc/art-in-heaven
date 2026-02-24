<?php
/**
 * Image Optimizer - Generates responsive AVIF/WebP variants
 *
 * Uses Imagick when available, falls back to GD for formats Imagick lacks.
 *
 * @package ArtInHeaven
 * @since 0.9.7
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Image_Optimizer {

    /** @var int[] Responsive widths to generate */
    private static $widths = array(400, 800, 1200);

    /** @var array Format => quality mapping */
    private static $formats = array(
        'avif' => 50,  // AVIF quality 50 ≈ JPEG 85 visually
        'webp' => 80,
    );

    /**
     * Check if any image processing library supports at least one modern format
     */
    public static function is_available() {
        return !empty(self::supported_formats());
    }

    /**
     * Check which formats are supported across Imagick and GD
     */
    public static function supported_formats() {
        $formats = array();

        // Check Imagick
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $imagick_formats = Imagick::queryFormats();
            if (in_array('AVIF', $imagick_formats)) $formats[] = 'avif';
            if (in_array('WEBP', $imagick_formats)) $formats[] = 'webp';
        }

        // Check GD as fallback for formats Imagick doesn't support
        if (extension_loaded('gd')) {
            if (!in_array('avif', $formats) && function_exists('imageavif')) $formats[] = 'avif';
            if (!in_array('webp', $formats) && function_exists('imagewebp')) $formats[] = 'webp';
        }

        return $formats;
    }

    /**
     * Check which formats Imagick supports natively
     */
    private static function imagick_formats() {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            return array();
        }
        $supported = Imagick::queryFormats();
        $formats = array();
        if (in_array('AVIF', $supported)) $formats[] = 'avif';
        if (in_array('WEBP', $supported)) $formats[] = 'webp';
        return $formats;
    }

    /**
     * Get the responsive variants directory path
     */
    public static function get_responsive_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/art-in-heaven/responsive';
    }

    /**
     * Get the responsive variants directory URL
     */
    public static function get_responsive_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/art-in-heaven/responsive';
    }

    /**
     * Generate responsive AVIF/WebP variants from a watermarked image
     *
     * @param string $watermarked_path Absolute path to watermarked image
     * @return array List of generated file paths, or empty on failure
     */
    public static function generate_variants($watermarked_path) {
        $available_formats = self::supported_formats();
        if (empty($available_formats)) {
            error_log('AIH Optimizer: No image library supports AVIF or WebP');
            return array();
        }

        if (!file_exists($watermarked_path) || !is_readable($watermarked_path)) {
            error_log('AIH Optimizer: Source file not found or not readable: ' . $watermarked_path);
            return array();
        }

        $responsive_dir = self::get_responsive_dir();
        if (!file_exists($responsive_dir)) {
            if (!wp_mkdir_p($responsive_dir)) {
                error_log('AIH Optimizer: Could not create responsive directory: ' . $responsive_dir);
                return array();
            }
        }

        $basename = pathinfo($watermarked_path, PATHINFO_FILENAME);
        $generated = array();
        $imagick_formats = self::imagick_formats();

        // Load source dimensions via GD (always available for dimension check)
        $source_size = @getimagesize($watermarked_path);
        if (!$source_size) {
            error_log('AIH Optimizer: Could not read source image dimensions: ' . $watermarked_path);
            return array();
        }
        $source_width = $source_size[0];

        // Try Imagick for formats it supports
        $imagick_source = null;
        if (!empty($imagick_formats) && extension_loaded('imagick')) {
            try {
                $imagick_source = new Imagick($watermarked_path);
                $imagick_source->stripImage();
            } catch (ImagickException $e) {
                error_log('AIH Optimizer: Imagick failed to load source: ' . $e->getMessage());
                $imagick_source = null;
            }
        }

        // Load GD source for formats that need it
        $gd_formats = array_diff($available_formats, $imagick_formats);
        $gd_source = null;
        if (!empty($gd_formats)) {
            $gd_source = self::gd_load_image($watermarked_path);
            if (!$gd_source) {
                error_log('AIH Optimizer: GD failed to load source: ' . $watermarked_path);
            }
        }

        foreach (self::$widths as $width) {
            $effective_width = ($source_width <= $width) ? $source_width : $width;

            foreach ($available_formats as $format) {
                $quality = self::$formats[$format] ?? 80;
                $output_file = $responsive_dir . '/' . $basename . '-' . $effective_width . '.' . $format;

                if (in_array($format, $imagick_formats) && $imagick_source) {
                    // Use Imagick
                    try {
                        $resized = clone $imagick_source;
                        if ($source_width > $width) {
                            $resized->thumbnailImage($width, 0);
                        }
                        $resized->setImageFormat($format);
                        $resized->setImageCompressionQuality($quality);
                        if ($format === 'avif') {
                            $resized->setOption('heic:speed', '6');
                        }
                        $resized->writeImage($output_file);
                        $resized->destroy();
                        $generated[] = $output_file;
                    } catch (ImagickException $e) {
                        error_log('AIH Optimizer: Imagick failed ' . $format . ' at ' . $width . 'w: ' . $e->getMessage());
                    }
                } elseif ($gd_source) {
                    // Use GD fallback
                    $result = self::gd_generate_variant($gd_source, $source_width, $effective_width, $format, $quality, $output_file);
                    if ($result) {
                        $generated[] = $output_file;
                    }
                }
            }
        }

        if ($imagick_source) {
            $imagick_source->destroy();
        }
        if ($gd_source) {
            imagedestroy($gd_source);
        }

        if (!empty($generated) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIH Optimizer: Generated ' . count($generated) . ' variants for ' . basename($watermarked_path));
        }

        return $generated;
    }

    /**
     * Load an image into a GD resource
     */
    private static function gd_load_image($path) {
        $info = @getimagesize($path);
        if (!$info) return null;

        switch ($info[2]) {
            case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:  return @imagecreatefrompng($path);
            case IMAGETYPE_WEBP: return @imagecreatefromwebp($path);
            case IMAGETYPE_GIF:  return @imagecreatefromgif($path);
            default: return null;
        }
    }

    /**
     * Generate a single variant using GD
     */
    private static function gd_generate_variant($source, $source_width, $target_width, $format, $quality, $output_file) {
        $source_height = imagesy($source);

        if ($source_width > $target_width) {
            $target_height = (int) round($source_height * ($target_width / $source_width));
            $resized = imagecreatetruecolor($target_width, $target_height);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $target_width, $target_height, $source_width, $source_height);
        } else {
            $resized = $source;
        }

        $success = false;
        switch ($format) {
            case 'webp':
                $success = @imagewebp($resized, $output_file, $quality);
                break;
            case 'avif':
                $success = @imageavif($resized, $output_file, $quality);
                break;
        }

        if ($resized !== $source) {
            imagedestroy($resized);
        }

        if (!$success) {
            error_log('AIH Optimizer: GD failed to generate ' . $format . ' at ' . $target_width . 'w');
        }

        return $success;
    }

    /**
     * Derive responsive variant URLs from a watermarked URL
     *
     * @param string $watermarked_url URL to the watermarked image
     * @return array ['avif' => [400 => url, 800 => url, ...], 'webp' => [...]]
     */
    public static function get_variant_urls($watermarked_url) {
        if (empty($watermarked_url) || strpos($watermarked_url, '/watermarked/') === false) {
            return array();
        }

        $basename = pathinfo($watermarked_url, PATHINFO_FILENAME);
        $responsive_base = str_replace('/watermarked/', '/responsive/', dirname($watermarked_url));

        $upload_dir = wp_upload_dir();
        $responsive_dir = self::get_responsive_dir();

        $variants = array();

        foreach (array_keys(self::$formats) as $format) {
            $variants[$format] = array();
            foreach (self::$widths as $width) {
                $filename = $basename . '-' . $width . '.' . $format;
                $file_path = $responsive_dir . '/' . $filename;

                // Only include variants that actually exist on disk
                if (file_exists($file_path)) {
                    $variants[$format][$width] = $responsive_base . '/' . $filename;
                }
            }

            // Remove format key if no variants exist
            if (empty($variants[$format])) {
                unset($variants[$format]);
            }
        }

        return $variants;
    }

    /**
     * Delete all responsive variants for a watermarked image
     *
     * @param string $watermarked_url URL to the watermarked image
     */
    public static function cleanup_variants($watermarked_url) {
        if (empty($watermarked_url) || strpos($watermarked_url, '/watermarked/') === false) {
            return;
        }

        $basename = pathinfo($watermarked_url, PATHINFO_FILENAME);
        $responsive_dir = self::get_responsive_dir();

        $deleted = 0;
        foreach (array_keys(self::$formats) as $format) {
            foreach (self::$widths as $width) {
                $file_path = $responsive_dir . '/' . $basename . '-' . $width . '.' . $format;
                if (file_exists($file_path)) {
                    @unlink($file_path);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIH Optimizer: Cleaned up ' . $deleted . ' variants for ' . $basename);
        }
    }
}
