<?php
/**
 * Image Optimizer - Generates responsive AVIF/WebP variants
 *
 * Three-tier format support (per-format priority):
 *   Tier 1: Imagick (fastest, best quality)
 *   Tier 2: GD (built into PHP)
 *   Tier 3: CLI binaries — cwebp for WebP, ffmpeg (libaom-av1) for AVIF
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

    /** @var array|null Cached CLI binary paths keyed by format */
    private static $cli_cache = null;

    /**
     * Check if any image processing library supports at least one modern format
     */
    public static function is_available() {
        return !empty(self::supported_formats());
    }

    /**
     * Check which formats are supported across Imagick, GD, and CLI binaries
     */
    public static function supported_formats() {
        $formats = array();

        // Tier 1: Imagick
        foreach (self::imagick_formats() as $f) {
            if (!in_array($f, $formats)) $formats[] = $f;
        }

        // Tier 2: GD
        foreach (self::gd_formats() as $f) {
            if (!in_array($f, $formats)) $formats[] = $f;
        }

        // Tier 3: CLI binaries (cwebp, ffmpeg)
        foreach (array_keys(self::cli_binaries()) as $f) {
            if (!in_array($f, $formats)) $formats[] = $f;
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
     * Check which formats GD supports natively
     */
    private static function gd_formats() {
        if (!extension_loaded('gd')) {
            return array();
        }
        $formats = array();
        if (function_exists('imageavif')) $formats[] = 'avif';
        if (function_exists('imagewebp')) $formats[] = 'webp';
        return $formats;
    }

    /**
     * Detect available CLI binaries for image encoding
     *
     * @return array Format => binary path, e.g. ['webp' => '/home/user/bin/cwebp']
     */
    private static function cli_binaries() {
        if (self::$cli_cache !== null) {
            return self::$cli_cache;
        }

        self::$cli_cache = array();

        if (!function_exists('exec')) {
            return self::$cli_cache;
        }

        $search_paths = array();
        $home = getenv('HOME');
        if ($home) {
            $search_paths[] = $home . '/bin';
        }
        $search_paths[] = '/usr/local/bin';
        $search_paths[] = '/usr/bin';

        // cwebp for WebP encoding
        foreach ($search_paths as $dir) {
            $path = $dir . '/cwebp';
            if (@is_executable($path)) {
                self::$cli_cache['webp'] = $path;
                break;
            }
        }

        // ffmpeg with libaom-av1 for AVIF encoding
        foreach ($search_paths as $dir) {
            $path = $dir . '/ffmpeg';
            if (@is_executable($path)) {
                $output = array();
                @exec(escapeshellarg($path) . ' -encoders 2>/dev/null', $output);
                if (strpos(implode("\n", $output), 'libaom-av1') !== false) {
                    self::$cli_cache['avif'] = $path;
                }
                break;
            }
        }

        return self::$cli_cache;
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

        // Determine per-tier format support
        $imagick_formats = self::imagick_formats();
        $gd_formats = self::gd_formats();
        $cli_bins = self::cli_binaries();

        // Load source dimensions
        $source_size = @getimagesize($watermarked_path);
        if (!$source_size) {
            error_log('AIH Optimizer: Could not read source image dimensions: ' . $watermarked_path);
            return array();
        }
        $source_width = $source_size[0];

        // Load Imagick source if it supports any format
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

        // Load GD source if it supports any format
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
                $success = false;

                // Tier 1: Imagick
                if (in_array($format, $imagick_formats) && $imagick_source) {
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
                        $success = true;
                    } catch (ImagickException $e) {
                        error_log('AIH Optimizer: Imagick failed ' . $format . ' at ' . $width . 'w: ' . $e->getMessage());
                    }
                }

                // Tier 2: GD
                if (!$success && in_array($format, $gd_formats) && $gd_source) {
                    $result = self::gd_generate_variant($gd_source, $source_width, $effective_width, $format, $quality, $output_file);
                    if ($result) {
                        $generated[] = $output_file;
                        $success = true;
                    }
                }

                // Tier 3: CLI binaries (cwebp / ffmpeg)
                if (!$success && isset($cli_bins[$format])) {
                    $result = self::cli_generate_variant($watermarked_path, $source_width, $effective_width, $format, $quality, $output_file, $cli_bins[$format]);
                    if ($result) {
                        $generated[] = $output_file;
                        $success = true;
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
     * Generate a single variant using a CLI binary (cwebp or ffmpeg)
     */
    private static function cli_generate_variant($source_path, $source_width, $target_width, $format, $quality, $output_file, $cli_bin) {
        $src = escapeshellarg($source_path);
        $out = escapeshellarg($output_file);

        switch ($format) {
            case 'webp':
                // cwebp handles resize internally
                $resize = ($source_width > $target_width) ? '-resize ' . intval($target_width) . ' 0 ' : '';
                $cmd = escapeshellarg($cli_bin) . ' -q ' . intval($quality) . ' ' . $resize . $src . ' -o ' . $out . ' 2>&1';
                break;

            case 'avif':
                // ffmpeg with libaom-av1; map quality 0-100 to CRF 63-0 (quality 50 → CRF 30)
                $scale = ($source_width > $target_width) ? '-vf scale=' . intval($target_width) . ':-1 ' : '';
                $crf = max(0, min(63, intval(63 - ($quality * 0.66))));
                $cmd = escapeshellarg($cli_bin) . ' -y -i ' . $src . ' ' . $scale . '-c:v libaom-av1 -crf ' . $crf . ' -cpu-used 6 -still-picture 1 -frames:v 1 ' . $out . ' 2>&1';
                break;

            default:
                return false;
        }

        $output = array();
        $return_code = 0;
        @exec($cmd, $output, $return_code);

        if ($return_code !== 0 || !file_exists($output_file)) {
            error_log('AIH Optimizer: CLI failed (' . $format . ' at ' . $target_width . 'w): ' . implode(' ', array_slice($output, -3)));
            return false;
        }

        return true;
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
        $responsive_dir = self::get_responsive_dir();
        $responsive_base = self::get_responsive_url();

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
