<?php
/**
 * Admin Settings View - Auction Settings
 */
if (!defined('ABSPATH')) exit;

$current_year = AIH_Database::get_auction_year();
$tables_exist = AIH_Database::tables_exist($current_year);
$event_date = get_option('aih_event_date', '');
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Art in Heaven Settings', 'art-in-heaven'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('aih_settings'); ?>
        
        <!-- Event Settings -->
        <div class="aih-settings-section">
            <h2><?php _e('Event Settings', 'art-in-heaven'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_event_date"><?php _e('Event Start Date & Time', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="datetime-local" id="aih_event_date" name="aih_event_date" 
                               value="<?php echo esc_attr($event_date ? date('Y-m-d\TH:i', strtotime($event_date)) : ''); ?>" 
                               class="regular-text">
                        <p class="description"><?php _e('Default start time for new art pieces.', 'art-in-heaven'); ?></p>
                        <?php if ($event_date): ?>
                            <br><button type="button" id="aih-apply-event-date" class="button"><?php _e('Apply to All Active Art Pieces', 'art-in-heaven'); ?></button>
                            <span id="aih-event-date-result"></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_event_end_date"><?php _e('Event End Date & Time', 'art-in-heaven'); ?></label></th>
                    <td>
                        <?php $event_end_date = get_option('aih_event_end_date', ''); ?>
                        <input type="datetime-local" id="aih_event_end_date" name="aih_event_end_date" 
                               value="<?php echo esc_attr($event_end_date ? date('Y-m-d\TH:i', strtotime($event_end_date)) : ''); ?>" 
                               class="regular-text">
                        <p class="description"><?php _e('Default end time for new art pieces.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_gallery_page"><?php _e('Gallery Page URL', 'art-in-heaven'); ?></label></th>
                    <td>
                        <?php $gallery_page = get_option('aih_gallery_page', ''); ?>
                        <input type="url" id="aih_gallery_page" name="aih_gallery_page" 
                               value="<?php echo esc_attr($gallery_page); ?>" 
                               class="regular-text" placeholder="<?php _e('Auto-detected if empty', 'art-in-heaven'); ?>">
                        <p class="description"><?php _e('URL of the page with [art_in_heaven_gallery] shortcode. Leave empty to auto-detect.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Database / Year Settings -->
        <div class="aih-settings-section">
            <h2><?php _e('Auction Year & Database', 'art-in-heaven'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_auction_year"><?php _e('Auction Year', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="number" id="aih_auction_year" name="aih_auction_year" 
                               value="<?php echo esc_attr($current_year); ?>" min="2020" max="2099" style="width: 80px;">
                        <p class="description"><?php printf(__('Tables use this year prefix (e.g., %d_Bidders)', 'art-in-heaven'), $current_year); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Database Tables', 'art-in-heaven'); ?></th>
                    <td>
                        <?php if ($tables_exist): ?>
                            <span class="aih-check-yes">✓ <?php printf(__('Tables exist for %d', 'art-in-heaven'), $current_year); ?></span>
                        <?php else: ?>
                            <span class="aih-check-no">✗ <?php printf(__('Tables not found for %d', 'art-in-heaven'), $current_year); ?></span>
                        <?php endif; ?>
                        <br><br>
                        <button type="button" id="aih-create-tables" class="button"><?php printf(__('Create Tables for %d', 'art-in-heaven'), $current_year); ?></button>
                        <button type="button" id="aih-cleanup-tables" class="button"><?php _e('Cleanup & Migrate Old Columns', 'art-in-heaven'); ?></button>
                        <button type="button" id="aih-purge-data" class="button" style="color:#a00;"><?php _e('Delete All Data', 'art-in-heaven'); ?></button>
                        <span id="aih-tables-result"></span>
                        <p class="description"><?php _e('Cleanup migrates data from old column names (email, first_name, etc.) to new ones (email_primary, name_first, etc.) and removes the old columns.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- General Settings -->
        <div class="aih-settings-section">
            <h2><?php _e('General Settings', 'art-in-heaven'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_currency_symbol"><?php _e('Currency Symbol', 'art-in-heaven'); ?></label></th>
                    <td><input type="text" id="aih_currency_symbol" name="aih_currency_symbol" value="<?php echo esc_attr(get_option('aih_currency_symbol', '$')); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_bid_increment"><?php _e('Min Bid Increment', 'art-in-heaven'); ?></label></th>
                    <td><input type="number" id="aih_bid_increment" name="aih_bid_increment" value="<?php echo esc_attr(get_option('aih_bid_increment', '1')); ?>" min="0.01" step="0.01" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_watermark_text"><?php _e('Watermark Text', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" id="aih_watermark_text" name="aih_watermark_text" value="<?php echo esc_attr(get_option('aih_watermark_text', 'SILENT AUCTION')); ?>" class="regular-text">
                        <p class="description"><?php _e('Text displayed on watermarked images. Year is automatically appended.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Watermark Text Visibility', 'art-in-heaven'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aih_watermark_text_enabled" value="1" <?php checked(get_option('aih_watermark_text_enabled', 1)); ?>>
                            <?php _e('Show text watermark on images', 'art-in-heaven'); ?>
                        </label>
                        <p class="description"><?php _e('Uncheck to disable text watermark. The overlay image (if set) and crosshatch pattern will still be applied.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Crosshatch Pattern', 'art-in-heaven'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aih_watermark_crosshatch" value="1" <?php checked(get_option('aih_watermark_crosshatch', 1)); ?>>
                            <?php _e('Add diagonal crosshatch lines to watermark', 'art-in-heaven'); ?>
                        </label>
                        <p class="description"><?php _e('Adds subtle diagonal lines for extra protection. Disable for cleaner look.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Watermark Overlay Image', 'art-in-heaven'); ?></th>
                    <td>
                        <?php 
                        $overlay_id = get_option('aih_watermark_overlay_id', '');
                        $overlay_url = $overlay_id ? wp_get_attachment_url($overlay_id) : '';
                        ?>
                        <div id="aih-overlay-preview" style="margin-bottom: 10px;">
                            <?php if ($overlay_url): ?>
                                <img src="<?php echo esc_url($overlay_url); ?>" style="max-width: 150px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="aih_watermark_overlay_id" id="aih_watermark_overlay_id" value="<?php echo esc_attr($overlay_id); ?>">
                        <button type="button" class="button" id="aih-select-overlay"><?php _e('Select Image', 'art-in-heaven'); ?></button>
                        <?php if ($overlay_id): ?>
                        <button type="button" class="button" id="aih-remove-overlay"><?php _e('Remove', 'art-in-heaven'); ?></button>
                        <?php endif; ?>
                        <p class="description"><?php _e('Optional logo/image to tile across watermarked images. PNG with transparency recommended. Will be repeated across the image.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Regenerate Watermarks', 'art-in-heaven'); ?></th>
                    <td>
                        <button type="button" class="button" id="aih-regenerate-watermarks"><?php _e('Regenerate All Watermarks', 'art-in-heaven'); ?></button>
                        <span id="aih-regenerate-status" style="margin-left: 10px;"></span>
                        <p class="description"><?php _e('Use this after changing watermark settings to apply new settings to all existing images. This may take a while for many images.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Image Optimization', 'art-in-heaven'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aih_disable_image_sizes" value="1" <?php checked(get_option('aih_disable_image_sizes', 1)); ?>>
                            <?php _e('Disable WordPress thumbnail generation for art images', 'art-in-heaven'); ?>
                        </label>
                        <p class="description"><?php _e('Prevents WordPress from creating 6+ copies of each uploaded image. Saves disk space. Only the original + watermarked version will be kept.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_login_page"><?php _e('Login Page URL', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="url" id="aih_login_page" name="aih_login_page" value="<?php echo esc_attr(get_option('aih_login_page', '')); ?>" class="regular-text">
                        <p class="description"><?php _e('Page with [art_in_heaven_login] shortcode.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Show Sold Items', 'art-in-heaven'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aih_show_sold_items" value="1" <?php checked(get_option('aih_show_sold_items', 1)); ?>>
                            <?php _e('Show sold/ended items in the gallery', 'art-in-heaven'); ?>
                        </label>
                        <p class="description"><?php _e('When enabled, ended auctions will appear in a separate section at the bottom of the gallery.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Color/Theme Settings -->
        <div class="aih-settings-section">
            <h2><?php _e('Colors & Theme', 'art-in-heaven'); ?></h2>
            <p class="description" style="margin-bottom: 20px;"><?php _e('Customize the color scheme to match your website. Changes apply to both the gallery and all frontend pages.', 'art-in-heaven'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_color_primary"><?php _e('Primary/Accent Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_primary" name="aih_color_primary"
                               value="<?php echo esc_attr(get_option('aih_color_primary', '#b8956b')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_primary_text"
                               value="<?php echo esc_attr(get_option('aih_color_primary', '#b8956b')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_primary">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_primary" data-default="#b8956b"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Used for buttons, links, badges, and highlights. Default: Warm Bronze (#b8956b)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_color_secondary"><?php _e('Secondary/Dark Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_secondary" name="aih_color_secondary"
                               value="<?php echo esc_attr(get_option('aih_color_secondary', '#1c1c1c')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_secondary_text"
                               value="<?php echo esc_attr(get_option('aih_color_secondary', '#1c1c1c')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_secondary">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_secondary" data-default="#1c1c1c"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Used for headings, button text, and dark backgrounds. Default: Near Black (#1c1c1c)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_color_success"><?php _e('Success Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_success" name="aih_color_success"
                               value="<?php echo esc_attr(get_option('aih_color_success', '#4a7c59')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_success_text"
                               value="<?php echo esc_attr(get_option('aih_color_success', '#4a7c59')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_success">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_success" data-default="#4a7c59"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Used for winning bids, success messages, and positive status. Default: Forest Green (#4a7c59)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_color_error"><?php _e('Error/Warning Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_error" name="aih_color_error"
                               value="<?php echo esc_attr(get_option('aih_color_error', '#a63d40')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_error_text"
                               value="<?php echo esc_attr(get_option('aih_color_error', '#a63d40')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_error">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_error" data-default="#a63d40"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Used for errors, outbid notices, and urgent countdowns. Default: Muted Red (#a63d40)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_color_text"><?php _e('Text Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_text" name="aih_color_text"
                               value="<?php echo esc_attr(get_option('aih_color_text', '#1c1c1c')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_text_text"
                               value="<?php echo esc_attr(get_option('aih_color_text', '#1c1c1c')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_text">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_text" data-default="#1c1c1c"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Main body text color. Default: Near Black (#1c1c1c)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_color_muted"><?php _e('Muted Text Color', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="color" id="aih_color_muted" name="aih_color_muted"
                               value="<?php echo esc_attr(get_option('aih_color_muted', '#8a8a8a')); ?>"
                               class="aih-color-picker">
                        <input type="text" id="aih_color_muted_text"
                               value="<?php echo esc_attr(get_option('aih_color_muted', '#8a8a8a')); ?>"
                               class="small-text aih-color-text" data-target="aih_color_muted">
                        <button type="button" class="button aih-color-reset" data-target="aih_color_muted" data-default="#8a8a8a"><?php _e('Reset', 'art-in-heaven'); ?></button>
                        <p class="description"><?php _e('Secondary/muted text like descriptions and labels. Default: Medium Gray (#8a8a8a)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div class="aih-color-preview" style="margin-top: 20px; padding: 20px; background: #f9fafb; border-radius: 8px;">
                <h4 style="margin-top: 0;"><?php _e('Preview', 'art-in-heaven'); ?></h4>
                <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <button type="button" class="button" id="aih-preview-btn" style="background: var(--aih-preview-primary, #b8956b); color: var(--aih-preview-secondary, #1c1c1c); border: none; padding: 10px 20px; font-weight: 600;"><?php _e('Place Bid', 'art-in-heaven'); ?></button>
                    <span id="aih-preview-success" style="color: var(--aih-preview-success, #4a7c59); font-weight: 600;"><?php _e('Winning!', 'art-in-heaven'); ?></span>
                    <span id="aih-preview-error" style="color: var(--aih-preview-error, #a63d40); font-weight: 600;"><?php _e('Outbid', 'art-in-heaven'); ?></span>
                    <span id="aih-preview-text" style="color: var(--aih-preview-text, #1c1c1c);"><?php _e('Body text', 'art-in-heaven'); ?></span>
                    <span id="aih-preview-muted" style="color: var(--aih-preview-muted, #8a8a8a);"><?php _e('Muted text', 'art-in-heaven'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Shortcodes -->
        <div class="aih-settings-section">
            <h2><?php _e('Shortcodes', 'art-in-heaven'); ?></h2>
            <table class="form-table">
                <tr><th><?php _e('Gallery', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_gallery]</code></td></tr>
                <tr><th><?php _e('Login Page', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_login]</code></td></tr>
                <tr><th><?php _e('My Bids', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_my_bids]</code></td></tr>
                <tr><th><?php _e('Checkout', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_checkout]</code></td></tr>
                <tr><th><?php _e('My Wins/Collection', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_my_wins]</code></td></tr>
                <tr><th><?php _e('Winners/Sold', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_winners]</code></td></tr>
                <tr><th><?php _e('Single Item', 'art-in-heaven'); ?></th><td><code>[art_in_heaven_item id="123"]</code></td></tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
    
    <!-- Server Info -->
    <div class="aih-settings-section aih-server-info">
        <h2><?php _e('Server Information', 'art-in-heaven'); ?></h2>
        <table class="form-table">
            <tr><th>PHP</th><td><?php echo phpversion(); ?></td></tr>
            <tr><th>WordPress</th><td><?php echo get_bloginfo('version'); ?></td></tr>
            <tr><th>Plugin</th><td><?php echo AIH_VERSION; ?></td></tr>
            <tr><th>GD Library</th><td><?php echo extension_loaded('gd') ? '<span class="aih-check-yes">✓</span>' : '<span class="aih-check-no">✗</span>'; ?></td></tr>
            <tr><th>FreeType (TTF fonts)</th><td><?php echo function_exists('imagettftext') ? '<span class="aih-check-yes">✓</span>' : '<span class="aih-check-no">✗ Watermarks will use low-quality bitmap fonts</span>'; ?></td></tr>
            <tr><th>Watermark Font</th><td><?php
                $font_path = AIH_PLUGIN_DIR . 'assets/fonts/OpenSans-Bold.ttf';
                echo file_exists($font_path) ? '<span class="aih-check-yes">✓ ' . esc_html($font_path) . '</span>' : '<span class="aih-check-no">✗ Font file missing</span>';
            ?></td></tr>
        </table>
    </div>
</div>

<style>
/* settings minimal overrides - main styles in aih-admin.css */
.aih-color-text { width: 90px !important; font-family: monospace; text-align: center; vertical-align: middle; height: 30px; box-sizing: border-box; }
.aih-color-picker { vertical-align: middle; width: 40px; height: 30px; padding: 0; border: 1px solid #8c8f94; cursor: pointer; }
.aih-color-reset { vertical-align: middle; height: 30px; line-height: 28px; }
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('aih_admin_nonce'); ?>';
    
    // Update button text when year changes
    $('#aih_auction_year').on('change input', function() {
        var year = $(this).val();
        $('#aih-create-tables').text('<?php _e('Create Tables for', 'art-in-heaven'); ?> ' + year);
    });
    
    // Create tables
    $('#aih-create-tables').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-tables-result').html('<span style="color:#666;">Creating...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_create_tables', nonce: nonce, year: $('#aih_auction_year').val() },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    // Cleanup tables
    $('#aih-cleanup-tables').on('click', function() {
        if (!confirm('<?php _e('This will migrate data from old columns to new columns and remove old columns. Continue?', 'art-in-heaven'); ?>')) return;
        
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-tables-result').html('<span style="color:#666;">Cleaning up...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_cleanup_tables', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    // Delete all data
    $('#aih-purge-data').on('click', function() {
        if (!confirm('<?php _e('WARNING: This will permanently delete ALL data from every database table (art pieces, bids, orders, bidders, registrants, transactions, etc.). This cannot be undone. Are you sure?', 'art-in-heaven'); ?>')) return;
        if (!confirm('<?php _e('Are you REALLY sure? All auction data will be lost forever.', 'art-in-heaven'); ?>')) return;

        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-tables-result').html('<span style="color:#666;">Deleting all data...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_purge_data', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });

    // Apply event date
    $('#aih-apply-event-date').on('click', function() {
        if (!confirm('<?php _e('This will update the start time for ALL active art pieces. Continue?', 'art-in-heaven'); ?>')) return;
        
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-event-date-result').html('<span style="color:#666;">Updating...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_apply_event_date', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    // Watermark Overlay Image Picker
    var overlayFrame;
    $('#aih-select-overlay').on('click', function(e) {
        e.preventDefault();
        
        if (overlayFrame) {
            overlayFrame.open();
            return;
        }
        
        overlayFrame = wp.media({
            title: '<?php echo esc_js(__('Select Watermark Overlay Image', 'art-in-heaven')); ?>',
            button: { text: '<?php echo esc_js(__('Use this image', 'art-in-heaven')); ?>' },
            multiple: false,
            library: { type: 'image' }
        });
        
        overlayFrame.on('select', function() {
            var attachment = overlayFrame.state().get('selection').first().toJSON();
            $('#aih_watermark_overlay_id').val(attachment.id);
            $('#aih-overlay-preview').html('<img src="' + attachment.url + '" style="max-width: 150px; max-height: 100px; border: 1px solid #ddd; border-radius: 4px;">');
            
            // Show remove button
            if (!$('#aih-remove-overlay').length) {
                $('#aih-select-overlay').after(' <button type="button" class="button" id="aih-remove-overlay"><?php echo esc_js(__('Remove', 'art-in-heaven')); ?></button>');
            }
        });
        
        overlayFrame.open();
    });
    
    $(document).on('click', '#aih-remove-overlay', function() {
        $('#aih_watermark_overlay_id').val('');
        $('#aih-overlay-preview').empty();
        $(this).remove();
    });
    
    // Regenerate Watermarks
    $('#aih-regenerate-watermarks').on('click', function() {
        if (!confirm('<?php echo esc_js(__('This will regenerate watermarks for all art images. This may take several minutes. Continue?', 'art-in-heaven')); ?>')) {
            return;
        }
        
        var $btn = $(this).prop('disabled', true);
        var $status = $('#aih-regenerate-status').html('<span style="color:#666;"><?php echo esc_js(__('Starting...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 300000, // 5 minute timeout
            data: { 
                action: 'aih_admin_regenerate_watermarks', 
                nonce: nonce 
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                } else {
                    $status.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Error') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color:red;">✗ Request failed: ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    // Color picker synchronization
    function updateColorPreview() {
        var primary = $('#aih_color_primary').val();
        var secondary = $('#aih_color_secondary').val();
        var success = $('#aih_color_success').val();
        var error = $('#aih_color_error').val();
        var text = $('#aih_color_text').val();
        var muted = $('#aih_color_muted').val();
        
        // Update preview
        $('#aih-preview-btn').css({'background': primary, 'color': secondary});
        $('#aih-preview-success').css('color', success);
        $('#aih-preview-error').css('color', error);
        $('#aih-preview-text').css('color', text);
        $('#aih-preview-muted').css('color', muted);
    }
    
    // Sync color picker with text input
    $('.aih-color-picker').on('input change', function() {
        var id = $(this).attr('id');
        $('#' + id + '_text').val($(this).val());
        updateColorPreview();
    });
    
    // Sync text input with color picker
    $('.aih-color-text').on('input change', function() {
        var target = $(this).data('target');
        var val = $(this).val();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            $('#' + target).val(val);
            updateColorPreview();
        }
    });
    
    // Reset to default
    $('.aih-color-reset').on('click', function() {
        var target = $(this).data('target');
        var defaultVal = $(this).data('default');
        $('#' + target).val(defaultVal);
        $('#' + target + '_text').val(defaultVal);
        updateColorPreview();
    });
    
    // Initialize preview on page load
    updateColorPreview();
});
</script>
