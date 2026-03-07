<?php
/**
 * QR Code Generator
 *
 * Generates QR codes for art pieces with an optional center logo
 * (the same watermark overlay image from plugin settings).
 *
 * @package ArtInHeaven
 * @since   1.1.0
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;

if (!defined('ABSPATH')) {
    exit;
}

class AIH_QR_Code {

    /** @var int Default QR module scale (pixels per module) */
    private const DEFAULT_SCALE = 20;

    /** @var int Logo takes up roughly 20% of QR width */
    private const LOGO_SIZE_PERCENT = 20;

    /**
     * Generate a QR code PNG for a given art piece.
     *
     * Returns the raw PNG binary string or false on failure.
     *
     * @param string $art_id The catalog art ID (e.g. "A050").
     * @param int    $scale  Pixels per QR module (default 20).
     * @return string|false PNG binary data or false on error.
     */
    public static function generate($art_id, $scale = self::DEFAULT_SCALE) {
        $url = self::get_art_url($art_id);
        if (empty($url)) {
            return false;
        }

        $logo_path = self::get_logo_path();
        $has_logo = ($logo_path !== '' && file_exists($logo_path));

        $options = new QROptions([
            'outputType'       => QROutputInterface::GDIMAGE_PNG,
            'eccLevel'         => $has_logo ? EccLevel::H : EccLevel::M,
            'scale'            => $scale,
            'outputBase64'     => false,
            'addQuietzone'     => true,
            'quietzoneSize'    => 2,
            'addLogoSpace'     => $has_logo,
            'logoSpaceWidth'   => $has_logo ? self::logo_modules($scale) : null,
            'logoSpaceHeight'  => $has_logo ? self::logo_modules($scale) : null,
        ]);

        $qr = new QRCode($options);
        /** @var string $png_data */
        $png_data = $qr->render($url);

        if (!$has_logo) {
            return $png_data;
        }

        return self::overlay_logo($png_data, $logo_path);
    }

    /**
     * Generate a base64-encoded data URI for inline display.
     *
     * @param string $art_id The catalog art ID.
     * @param int    $scale  Pixels per QR module.
     * @return string|false data:image/png;base64,… or false.
     */
    public static function generate_data_uri($art_id, $scale = self::DEFAULT_SCALE) {
        $png = self::generate($art_id, $scale);
        if ($png === false) {
            return false;
        }
        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Get the full URL for a piece of artwork.
     *
     * @param string $art_id The catalog art ID.
     * @return string
     */
    public static function get_art_url($art_id) {
        return AIH_Template_Helper::get_art_url($art_id);
    }

    /**
     * Get the watermark overlay image file path from settings.
     *
     * @return string File path or empty string if not set.
     */
    public static function get_logo_path() {
        $overlay_id = get_option('aih_watermark_overlay_id', '');
        if (empty($overlay_id)) {
            return '';
        }

        $path = get_attached_file((int) $overlay_id);
        if (!$path || !file_exists($path)) {
            return '';
        }

        return $path;
    }

    /**
     * Calculate logo space in QR modules based on LOGO_SIZE_PERCENT.
     *
     * The library needs the logo space specified in module units.
     * We estimate a reasonable value since the actual module count
     * depends on the data length; 7 modules works well for typical URLs.
     *
     * @param int $scale Pixel scale (unused, kept for signature consistency).
     * @return int Number of modules for logo space.
     */
    private static function logo_modules($scale) {
        // For typical art URLs, QR version ~3-5 gives ~29-37 modules.
        // 20% of ~33 modules ≈ 7 modules is a safe logo space.
        return 7;
    }

    /**
     * Overlay the logo image onto the center of the QR code PNG.
     *
     * @param string $png_data Raw PNG binary of the QR code.
     * @param string $logo_path Absolute path to the logo image.
     * @return string|false Modified PNG binary or false on failure.
     */
    private static function overlay_logo($png_data, $logo_path) {
        $qr_image = imagecreatefromstring($png_data);
        if ($qr_image === false) {
            return false;
        }

        $logo_info = getimagesize($logo_path);
        if ($logo_info === false) {
            imagedestroy($qr_image);
            return false;
        }

        $logo_image = self::load_image($logo_path, $logo_info[2]);
        if ($logo_image === false) {
            imagedestroy($qr_image);
            return false;
        }

        $qr_width  = imagesx($qr_image);
        $qr_height = imagesy($qr_image);

        // Logo occupies ~20% of QR code width
        $logo_max = (int) round($qr_width * self::LOGO_SIZE_PERCENT / 100);
        $logo_w   = $logo_info[0];
        $logo_h   = $logo_info[1];

        // Scale logo proportionally to fit within the logo space
        $ratio = min($logo_max / $logo_w, $logo_max / $logo_h);
        $new_w = (int) round($logo_w * $ratio);
        $new_h = (int) round($logo_h * $ratio);

        // Center position
        $x = (int) round(($qr_width - $new_w) / 2);
        $y = (int) round(($qr_height - $new_h) / 2);

        // Draw white background behind logo for contrast
        $white = imagecolorallocate($qr_image, 255, 255, 255);
        if ($white !== false) {
            $padding = (int) round($new_w * 0.1);
            imagefilledrectangle(
                $qr_image,
                $x - $padding,
                $y - $padding,
                $x + $new_w + $padding,
                $y + $new_h + $padding,
                $white
            );
        }

        imagecopyresampled($qr_image, $logo_image, $x, $y, 0, 0, $new_w, $new_h, $logo_w, $logo_h);
        imagedestroy($logo_image);

        ob_start();
        imagepng($qr_image, null, 9);
        imagedestroy($qr_image);

        $result = ob_get_clean();
        return ($result !== false && $result !== '') ? $result : false;
    }

    /**
     * Load an image file into a GD resource based on type.
     *
     * @param string $path Image file path.
     * @param int    $type IMAGETYPE_* constant.
     * @return \GdImage|false
     */
    private static function load_image($path, $type) {
        return match ($type) {
            IMAGETYPE_PNG  => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF  => imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default        => false,
        };
    }
}
