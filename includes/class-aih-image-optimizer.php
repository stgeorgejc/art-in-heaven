<?php
/**
 * Image Optimizer - Generates responsive AVIF/WebP variants using Imagick
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
     * Check if Imagick is available with AVIF + WebP support
     */
    public static function is_available() {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            return false;
        }

        $supported = Imagick::queryFormats();
        return in_array('WEBP', $supported) && in_array('AVIF', $supported);
    }

    /**
     * Check which formats are supported
     */
    public static function supported_formats() {
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
            error_log('AIH Optimizer: Imagick not available or missing AVIF/WebP support');
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

        try {
            $source = new Imagick($watermarked_path);
            $source_width = $source->getImageWidth();
            $source_height = $source->getImageHeight();

            // Strip metadata from source for all variants
            $source->stripImage();

            foreach (self::$widths as $width) {
                // Skip if source is smaller than target width
                if ($source_width <= $width) {
                    // Still generate format conversions at original size for this breakpoint
                    $effective_width = $source_width;
                    $resized = clone $source;
                } else {
                    $resized = clone $source;
                    $resized->thumbnailImage($width, 0);
                    $effective_width = $width;
                }

                foreach ($available_formats as $format) {
                    $quality = self::$formats[$format] ?? 80;
                    $output_file = $responsive_dir . '/' . $basename . '-' . $effective_width . '.' . $format;

                    try {
                        $variant = clone $resized;
                        $variant->setImageFormat($format);
                        $variant->setImageCompressionQuality($quality);

                        if ($format === 'avif') {
                            // AVIF-specific: use YUV420 for photos, set speed
                            $variant->setOption('heic:speed', '6');
                        }

                        $variant->writeImage($output_file);
                        $variant->destroy();
                        $generated[] = $output_file;
                    } catch (ImagickException $e) {
                        error_log('AIH Optimizer: Failed to generate ' . $format . ' at ' . $width . 'w: ' . $e->getMessage());
                    }
                }

                $resized->destroy();
            }

            $source->destroy();

        } catch (ImagickException $e) {
            error_log('AIH Optimizer: Failed to process source image: ' . $e->getMessage());
            return array();
        }

        if (!empty($generated) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('AIH Optimizer: Generated ' . count($generated) . ' variants for ' . basename($watermarked_path));
        }

        return $generated;
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
