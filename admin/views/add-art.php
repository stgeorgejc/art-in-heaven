<?php
/**
 * Admin Add/Edit Art View
 * 
 * Uses AIH_Status helper for centralized, robust status computation.
 * Handles null values, timezone issues, and data inconsistencies gracefully.
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = false;
$can_view_bids = AIH_Roles::can_view_bids();

// Validate art_piece if provided
$validation = array('valid' => true, 'errors' => array());
$status_info = null;
$warnings = array();

if (isset($art_piece)) {
    $validation = AIH_Status::validate_art_piece($art_piece);
    if ($validation['valid']) {
        $is_edit = true;
        $status_info = AIH_Status::compute_status($art_piece);
        $warnings = $status_info['warnings'];
    } else {
        // Invalid art piece data - show error but allow viewing
        $warnings = $validation['errors'];
    }
}

// Get current time info - using WordPress's configured timezone
$now = AIH_Status::get_now();
$wp_timezone = wp_timezone();
$timezone_name = $wp_timezone->getName();
// Get a friendlier timezone display (e.g., "EST" instead of "America/New_York")
$now_display = AIH_Status::format_date($now, 'M j, Y g:i A');
$timezone_abbr = $now->format('T'); // Gets timezone abbreviation like EST, PST

// Safe getters for art piece properties
function aih_get_prop($obj, $prop, $default = '') {
    if (!is_object($obj)) return $default;
    if (!property_exists($obj, $prop)) return $default;
    return $obj->$prop ?? $default;
}

function aih_get_date_for_input($obj, $prop) {
    if (!is_object($obj)) return current_time('Y-m-d\TH:i');
    $value = aih_get_prop($obj, $prop, '');
    if (empty($value)) return current_time('Y-m-d\TH:i');
    $dt = AIH_Status::parse_date($value);
    if ($dt === null) return current_time('Y-m-d\TH:i');
    return $dt->format('Y-m-d\TH:i');
}

// Get status options from helper
$status_options = AIH_Status::get_status_options();
?>
<div class="wrap aih-admin-wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    
    <?php if (!empty($warnings)): ?>
    <div class="notice notice-warning" style="margin-bottom: 20px;">
        <p><strong><?php _e('Data Warnings:', 'art-in-heaven'); ?></strong></p>
        <ul style="margin: 0 0 0 20px; list-style: disc;">
            <?php foreach ($warnings as $warning): ?>
                <li><?php echo esc_html($warning); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if ($is_edit && $status_info): ?>
    <div class="aih-status-info" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 16px; margin-bottom: 20px;">
        <table style="border-collapse: collapse;">
            <tr>
                <td style="padding: 2px 15px 2px 0; font-weight: 600;"><?php _e('Current Time:', 'art-in-heaven'); ?></td>
                <td>
                    <?php echo esc_html($now_display); ?> 
                    <span style="color: #666;">(<?php echo esc_html($timezone_abbr); ?> - <?php echo esc_html($timezone_name); ?>)</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 2px 15px 2px 0; font-weight: 600;"><?php _e('Database Status:', 'art-in-heaven'); ?></td>
                <td><code><?php echo esc_html(aih_get_prop($art_piece, 'status', 'not set')); ?></code></td>
            </tr>
            <tr>
                <td style="padding: 2px 15px 2px 0; font-weight: 600;"><?php _e('Computed Status:', 'art-in-heaven'); ?></td>
                <td>
                    <strong><?php echo esc_html($status_info['display_status']); ?></strong>
                    <span style="color: #666; margin-left: 10px;">(<?php echo esc_html($status_info['reason']); ?>)</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 2px 15px 2px 0; font-weight: 600;"><?php _e('Bidding Allowed:', 'art-in-heaven'); ?></td>
                <td>
                    <?php if ($status_info['can_bid']): ?>
                        <span style="color: #00a32a;">✓ <?php _e('Yes', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                        <span style="color: #d63638;">✗ <?php _e('No', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    
    <form id="aih-art-form" class="aih-art-form">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr(aih_get_prop($art_piece, 'id', '')); ?>">
        <?php endif; ?>
        
        <div class="aih-form-grid">
            <div class="aih-form-main">
                <div class="aih-form-section">
                    <h2><?php _e('Basic Information', 'art-in-heaven'); ?></h2>
                    
                    <div class="aih-form-row">
                        <label for="title"><?php _e('Title', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'title', '')); ?>">
                    </div>
                    
                    <div class="aih-form-row">
                        <label for="artist"><?php _e('Artist', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="text" id="artist" name="artist" required
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'artist', '')); ?>">
                    </div>
                    
                    <div class="aih-form-row">
                        <label for="medium"><?php _e('Medium', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="text" id="medium" name="medium" required
                               placeholder="<?php esc_attr_e('e.g., Oil on Canvas, Watercolor, Bronze', 'art-in-heaven'); ?>"
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'medium', '')); ?>">
                    </div>
                    
                    <div class="aih-form-row">
                        <label for="dimensions"><?php _e('Dimensions', 'art-in-heaven'); ?></label>
                        <input type="text" id="dimensions" name="dimensions" 
                               placeholder="<?php esc_attr_e('e.g., 24" x 36"', 'art-in-heaven'); ?>"
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'dimensions', '')); ?>">
                    </div>
                    
                    <div class="aih-form-row">
                        <label for="description"><?php _e('Description', 'art-in-heaven'); ?></label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="<?php esc_attr_e('Add a descriptive sentence about this piece...', 'art-in-heaven'); ?>"><?php echo esc_textarea(aih_get_prop($art_piece, 'description', '')); ?></textarea>
                    </div>
                </div>
                
                <div class="aih-form-section">
                    <h2><?php _e('Auction Settings', 'art-in-heaven'); ?></h2>
                    
                    <?php if ($can_view_bids): ?>
                    <div class="aih-form-row">
                        <label for="starting_bid"><?php _e('Starting Bid ($)', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="number" id="starting_bid" name="starting_bid" 
                               min="0" step="0.01" required
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'starting_bid', '100')); ?>">
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="starting_bid" value="<?php echo esc_attr(aih_get_prop($art_piece, 'starting_bid', '100')); ?>">
                    <?php endif; ?>
                    
                    <div class="aih-form-row">
                        <label for="auction_start"><?php _e('Auction Start', 'art-in-heaven'); ?></label>
                        <input type="datetime-local" id="auction_start" name="auction_start"
                               value="<?php echo esc_attr($is_edit ? aih_get_date_for_input($art_piece, 'auction_start') : current_time('Y-m-d\TH:i')); ?>">
                        <p class="description"><?php _e('When bidding opens. Leave as-is for immediate start.', 'art-in-heaven'); ?></p>
                    </div>
                    
                    <div class="aih-form-row">
                        <label for="auction_end"><?php _e('Auction End', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="datetime-local" id="auction_end" name="auction_end" required
                               value="<?php echo esc_attr($is_edit ? aih_get_date_for_input($art_piece, 'auction_end') : date('Y-m-d\TH:i', strtotime('+7 days', current_time('timestamp')))); ?>">
                        <p class="description aih-time-feedback" id="end-time-feedback"></p>
                    </div>
                    
                    <div class="aih-form-row" style="background: #e8f4fd; padding: 12px 15px; border-radius: 4px; border-left: 4px solid #0073aa;">
                        <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin: 0;">
                            <input type="checkbox" id="show_end_time" name="show_end_time" value="1" 
                                   <?php checked(aih_get_prop($art_piece, 'show_end_time', 0), 1); ?>
                                   style="margin-top: 3px;">
                            <span>
                                <strong><?php _e('Show end time to bidders', 'art-in-heaven'); ?></strong>
                                <br>
                                <span class="description" style="font-weight: normal;">
                                    <?php _e('When unchecked, bidders will see "Closing time TBD" instead of the actual end time. Reveal it when you\'re ready.', 'art-in-heaven'); ?>
                                </span>
                            </span>
                        </label>
                    </div>
                    
                    <div class="aih-form-row" style="background: #fff8e5; padding: 15px; border-radius: 4px; margin-top: 15px;">
                        <label for="status" style="font-weight: 600;"><?php _e('Status', 'art-in-heaven'); ?></label>
                        <select id="status" name="status" style="margin-bottom: 10px;">
                            <?php 
                            $current_status = aih_get_prop($art_piece, 'status', 'active');
                            foreach ($status_options as $value => $label): 
                            ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div style="margin-top: 10px;">
                            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="force_status" name="force_status" value="1" style="margin-top: 3px;">
                                <span>
                                    <strong><?php _e('Override auto-status', 'art-in-heaven'); ?></strong>
                                    <br>
                                    <span class="description" style="font-weight: normal;">
                                        <?php _e('Use the selected status exactly as-is, ignoring time-based rules. Check this to reactivate an ended auction or set any status manually.', 'art-in-heaven'); ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                        
                        <div id="status-preview" style="margin-top: 15px; padding: 10px; background: #f6f7f7; border-radius: 4px; display: none;">
                            <strong><?php _e('Preview:', 'art-in-heaven'); ?></strong>
                            <span id="status-preview-text"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="aih-form-sidebar">
                <div class="aih-form-section">
                    <h2><?php _e('Art Images', 'art-in-heaven'); ?></h2>
                    
                    <div class="aih-images-container">
                        <?php 
                        // Get all images for this art piece
                        $images = array();
                        if ($is_edit) {
                            $images_handler = new AIH_Art_Images();
                            $images = $images_handler->get_images(aih_get_prop($art_piece, 'id', 0));
                        }
                        ?>
                        
                        <?php if ($is_edit): ?>
                        <!-- Edit mode: Multiple images with add/remove -->
                        <div id="aih-images-list" class="aih-images-list" <?php echo empty($images) ? 'style="display:none;"' : ''; ?>>
                            <?php foreach ($images as $img): ?>
                            <div class="aih-image-item" data-id="<?php echo esc_attr($img->id); ?>">
                                <img src="<?php echo esc_url($img->watermarked_url ?: $img->image_url); ?>" alt="">
                                <div class="aih-image-actions">
                                    <button type="button" class="aih-set-primary <?php echo $img->is_primary ? 'is-primary' : ''; ?>" 
                                            data-id="<?php echo esc_attr($img->id); ?>" title="<?php esc_attr_e('Set as primary', 'art-in-heaven'); ?>">
                                        ★
                                    </button>
                                    <button type="button" class="aih-remove-image" data-id="<?php echo esc_attr($img->id); ?>" 
                                            title="<?php esc_attr_e('Remove image', 'art-in-heaven'); ?>">
                                        ×
                                    </button>
                                </div>
                                <?php if ($img->is_primary): ?>
                                <span class="aih-primary-badge"><?php _e('Primary', 'art-in-heaven'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="aih-no-images" class="aih-no-images" <?php echo !empty($images) ? 'style="display:none;"' : ''; ?>>
                            <span class="dashicons dashicons-format-image"></span>
                            <p><?php _e('No images added', 'art-in-heaven'); ?></p>
                        </div>
                        
                        <div class="aih-image-buttons">
                            <button type="button" id="aih-add-image-btn" class="button">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php _e('Add Image', 'art-in-heaven'); ?>
                            </button>
                        </div>
                        
                        <p class="description">
                            <?php _e('Add multiple images. Star = primary image. Images are automatically watermarked.', 'art-in-heaven'); ?>
                        </p>
                        
                        <?php else: ?>
                        <!-- New piece mode: Single image upload -->
                        <div id="aih-image-preview" class="aih-image-preview">
                            <span class="dashicons dashicons-format-image"></span>
                            <p><?php _e('No image selected', 'art-in-heaven'); ?></p>
                        </div>
                        
                        <div class="aih-image-buttons">
                            <button type="button" id="aih-upload-btn" class="button">
                                <?php _e('Select Image', 'art-in-heaven'); ?>
                            </button>
                            <button type="button" id="aih-remove-btn" class="button" style="display:none;">
                                <?php _e('Remove', 'art-in-heaven'); ?>
                            </button>
                        </div>
                        
                        <p class="description">
                            <?php _e('Select an image. You can add more images after saving. Images are automatically watermarked.', 'art-in-heaven'); ?>
                        </p>
                        <?php endif; ?>
                        
                        <!-- Hidden field for legacy single image support -->
                        <input type="hidden" id="image_id" name="image_id" 
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'image_id', '')); ?>">
                    </div>
                </div>
                
                <div class="aih-form-section">
                    <h2><?php _e('Art ID', 'art-in-heaven'); ?></h2>
                    <div class="aih-form-row">
                        <label for="art_id"><?php _e('Art ID', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <input type="text" id="art_id" name="art_id" required
                               placeholder="<?php esc_attr_e('e.g., AIH-001', 'art-in-heaven'); ?>"
                               value="<?php echo esc_attr(aih_get_prop($art_piece, 'art_id', '')); ?>"
                               style="font-family: monospace; text-transform: uppercase;">
                        <p class="description">
                            <?php _e('Required. Use a consistent format like AIH-001, AIH-002, etc.', 'art-in-heaven'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="aih-form-section">
                    <h2><?php _e('Tier / Category', 'art-in-heaven'); ?></h2>
                    <div class="aih-form-row">
                        <label for="tier"><?php _e('Tier', 'art-in-heaven'); ?> <span class="required">*</span></label>
                        <select id="tier" name="tier" required>
                            <option value=""><?php _e('— Select Tier —', 'art-in-heaven'); ?></option>
                            <?php
                            $tiers = array('1', '2', '3', '4');
                            $current_tier = aih_get_prop($art_piece, 'tier', '');
                            foreach ($tiers as $tier):
                            ?>
                            <option value="<?php echo esc_attr($tier); ?>" <?php selected($current_tier, $tier); ?>>
                                <?php printf(__('Tier %s', 'art-in-heaven'), $tier); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Required. Categorize art pieces by tier (1-4).', 'art-in-heaven'); ?>
                        </p>
                    </div>
                </div>
                
                <div class="aih-form-section aih-submit-section">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo $is_edit ? __('Update Art Piece', 'art-in-heaven') : __('Add Art Piece', 'art-in-heaven'); ?>
                    </button>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-art')); ?>" class="button button-large">
                        <?php _e('Cancel', 'art-in-heaven'); ?>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
/* Add/Edit Art minimal overrides - main styles in aih-admin.css */
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var mediaUploader;
    
    /**
     * Update time feedback based on current values
     */
    function updateTimeFeedback() {
        var startVal = $('#auction_start').val();
        var endVal = $('#auction_end').val();
        var $feedback = $('#end-time-feedback');
        var $preview = $('#status-preview');
        var $previewText = $('#status-preview-text');
        var forceStatus = $('#force_status').is(':checked');
        var selectedStatus = $('#status').val();
        
        if (!endVal) {
            $feedback.html('').hide();
            $preview.hide();
            return;
        }
        
        var now = new Date();
        var start = startVal ? new Date(startVal) : null;
        var end = new Date(endVal);
        
        var messages = [];
        var previewStatus = selectedStatus;
        
        // Check for invalid date range
        if (start && end && start > end) {
            messages.push('<span style="color: #d63638;">⚠️ <?php echo esc_js(__('Start time is after end time - invalid range!', 'art-in-heaven')); ?></span>');
        }
        
        // Check end time
        if (end <= now) {
            messages.push('<span style="color: #d63638;">⚠️ <?php echo esc_js(__('End time is in the past.', 'art-in-heaven')); ?></span>');
            if (!forceStatus) {
                previewStatus = 'ended';
            }
        } else {
            var diff = end - now;
            var hours = Math.floor(diff / (1000 * 60 * 60));
            var days = Math.floor(hours / 24);
            
            if (days > 0) {
                messages.push('<span style="color: #00a32a;"><?php echo esc_js(__('Ends in', 'art-in-heaven')); ?> ' + days + ' <?php echo esc_js(__('days', 'art-in-heaven')); ?></span>');
            } else if (hours > 0) {
                messages.push('<span style="color: #dba617;"><?php echo esc_js(__('Ends in', 'art-in-heaven')); ?> ' + hours + ' <?php echo esc_js(__('hours', 'art-in-heaven')); ?></span>');
            } else {
                messages.push('<span style="color: #d63638;"><?php echo esc_js(__('Ends in less than 1 hour!', 'art-in-heaven')); ?></span>');
            }
        }
        
        // Check start time
        if (start && start > now) {
            var startDiff = start - now;
            var startHours = Math.floor(startDiff / (1000 * 60 * 60));
            messages.push('<span style="color: #2271b1;"><?php echo esc_js(__('Starts in', 'art-in-heaven')); ?> ' + startHours + ' <?php echo esc_js(__('hours', 'art-in-heaven')); ?></span>');
            if (!forceStatus && selectedStatus === 'active') {
                previewStatus = 'draft';
            }
        }
        
        $feedback.html(messages.join(' &bull; ')).show();
        
        // Show status preview
        if (forceStatus) {
            $previewText.html('<?php echo esc_js(__('Will be saved as:', 'art-in-heaven')); ?> <strong>' + selectedStatus + '</strong> (<?php echo esc_js(__('forced', 'art-in-heaven')); ?>)');
        } else if (previewStatus !== selectedStatus) {
            $previewText.html('<?php echo esc_js(__('Will be auto-adjusted to:', 'art-in-heaven')); ?> <strong>' + previewStatus + '</strong> <?php echo esc_js(__('based on times', 'art-in-heaven')); ?>');
        } else {
            $previewText.html('<?php echo esc_js(__('Will be saved as:', 'art-in-heaven')); ?> <strong>' + previewStatus + '</strong>');
        }
        $preview.show();
    }
    
    // Run on load and on changes
    updateTimeFeedback();
    $('#auction_start, #auction_end, #status, #force_status').on('change', updateTimeFeedback);
    
    // Image handling
    var artPieceId = <?php echo $is_edit ? intval(aih_get_prop($art_piece, 'id', 0)) : '0'; ?>;
    var mediaUploader;
    var newPieceUploader;
    
    <?php if ($is_edit): ?>
    // EDIT MODE: Multiple images with AJAX
    
    // Add image (edit mode)
    $('#aih-add-image-btn').on('click', function(e) {
        e.preventDefault();
        
        // Set flag to disable intermediate image sizes
        $.post(aihAdmin.ajaxurl, {
            action: 'aih_set_upload_flag',
            nonce: aihAdmin.nonce,
            flag_action: 'set'
        });
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: '<?php echo esc_js(__('Add Images', 'art-in-heaven')); ?>',
            button: { text: '<?php echo esc_js(__('Add Selected Images', 'art-in-heaven')); ?>' },
            multiple: true
        });
        
        // Clear flag when modal closes
        mediaUploader.on('close', function() {
            $.post(aihAdmin.ajaxurl, {
                action: 'aih_set_upload_flag',
                nonce: aihAdmin.nonce,
                flag_action: 'clear'
            });
        });
        
        mediaUploader.on('select', function() {
            var attachments = mediaUploader.state().get('selection').toJSON();
            var $list = $('#aih-images-list');
            var $noImages = $('#aih-no-images');
            
            // Check if list is empty BEFORE any uploads (for primary determination)
            var listWasEmpty = $list.find('.aih-image-item').length === 0;
            var uploadCount = 0;
            
            attachments.forEach(function(attachment, index) {
                // Only mark as primary if list was empty and this is the first image
                var setAsPrimary = listWasEmpty && index === 0;
                
                // Add image via AJAX
                $.ajax({
                    url: aihAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aih_admin_add_image',
                        nonce: aihAdmin.nonce,
                        art_piece_id: artPieceId,
                        image_id: attachment.id,
                        is_primary: setAsPrimary ? '1' : '0'
                    },
                    success: function(response) {
                        uploadCount++;
                        if (response.success) {
                            var html = '<div class="aih-image-item" data-id="' + response.data.image_record_id + '">' +
                                '<img src="' + response.data.watermarked_url + '" alt="">' +
                                '<div class="aih-image-actions">' +
                                    '<button type="button" class="aih-set-primary ' + (setAsPrimary ? 'is-primary' : '') + '" data-id="' + response.data.image_record_id + '" title="<?php echo esc_js(__('Set as primary', 'art-in-heaven')); ?>">★</button>' +
                                    '<button type="button" class="aih-remove-image" data-id="' + response.data.image_record_id + '" title="<?php echo esc_js(__('Remove image', 'art-in-heaven')); ?>">×</button>' +
                                '</div>' +
                                (setAsPrimary ? '<span class="aih-primary-badge"><?php echo esc_js(__('Primary', 'art-in-heaven')); ?></span>' : '') +
                            '</div>';
                            $list.append(html);
                            $list.show();
                            $noImages.hide();
                        } else {
                            alert(response.data.message || 'Error adding image');
                        }
                    },
                    error: function(xhr, status, error) {
                        uploadCount++;
                        alert('Error: ' + error);
                    }
                });
            });
        });
        
        mediaUploader.open();
    });
    
    // Remove image (edit mode)
    $(document).on('click', '.aih-remove-image', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $item = $btn.closest('.aih-image-item');
        var imageRecordId = $btn.data('id');
        
        console.log('AIH: Removing image record ID:', imageRecordId);
        
        if (!imageRecordId) {
            alert('Error: No image ID found');
            return;
        }
        
        if (!confirm('<?php echo esc_js(__('Remove this image?', 'art-in-heaven')); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true).text('...');
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_remove_image',
                nonce: aihAdmin.nonce,
                image_record_id: imageRecordId
            },
            success: function(response) {
                console.log('AIH: Remove image response:', response);
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        var remainingItems = $('#aih-images-list .aih-image-item').length;
                        console.log('AIH: Remaining images:', remainingItems);
                        
                        if (remainingItems === 0) {
                            $('#aih-images-list').hide();
                            $('#aih-no-images').show();
                            
                            // Reload page to ensure clean state
                            if (response.data.reload) {
                                setTimeout(function() {
                                    location.reload();
                                }, 500);
                            }
                        }
                    });
                } else {
                    alert(response.data.message || 'Error removing image');
                    $btn.prop('disabled', false).text('×');
                }
            },
            error: function(xhr, status, error) {
                console.error('AIH: Remove image error:', error, xhr.responseText);
                alert('Error: ' + error);
                $btn.prop('disabled', false).text('×');
            }
        });
    });
    
    // Set primary image (edit mode)
    $(document).on('click', '.aih-set-primary', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var imageRecordId = $btn.data('id');
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_set_primary_image',
                nonce: aihAdmin.nonce,
                image_record_id: imageRecordId
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    $('.aih-set-primary').removeClass('is-primary');
                    $('.aih-primary-badge').remove();
                    $btn.addClass('is-primary');
                    $btn.closest('.aih-image-item').append('<span class="aih-primary-badge"><?php echo esc_js(__('Primary', 'art-in-heaven')); ?></span>');
                }
            }
        });
    });
    
    <?php else: ?>
    // NEW PIECE MODE: Single image upload (saved with form)
    
    $('#aih-upload-btn').on('click', function(e) {
        e.preventDefault();
        
        // Set flag to disable intermediate image sizes
        $.post(aihAdmin.ajaxurl, {
            action: 'aih_set_upload_flag',
            nonce: aihAdmin.nonce,
            flag_action: 'set'
        });
        
        if (newPieceUploader) {
            newPieceUploader.open();
            return;
        }
        
        newPieceUploader = wp.media({
            title: '<?php echo esc_js(__('Select Image', 'art-in-heaven')); ?>',
            button: { text: '<?php echo esc_js(__('Use this image', 'art-in-heaven')); ?>' },
            multiple: false
        });
        
        // Clear flag when modal closes
        newPieceUploader.on('close', function() {
            $.post(aihAdmin.ajaxurl, {
                action: 'aih_set_upload_flag',
                nonce: aihAdmin.nonce,
                flag_action: 'clear'
            });
        });
        
        newPieceUploader.on('select', function() {
            var attachment = newPieceUploader.state().get('selection').first().toJSON();
            $('#image_id').val(attachment.id);
            $('#aih-image-preview').html('<img src="' + attachment.url + '" alt="" style="max-width:100%; height:auto; border-radius:8px;">');
            $('#aih-remove-btn').show();
        });
        
        newPieceUploader.open();
    });
    
    $('#aih-remove-btn').on('click', function(e) {
        e.preventDefault();
        $('#image_id').val('');
        $('#aih-image-preview').html('<span class="dashicons dashicons-format-image"></span><p><?php echo esc_js(__('No image selected', 'art-in-heaven')); ?></p>');
        $(this).hide();
    });
    <?php endif; ?>
    
    // Form submit
    $('#aih-art-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'aih_admin_save_art',
            nonce: aihAdmin.nonce
        };
        
        $(this).serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });
        
        // Handle checkbox
        formData['force_status'] = $('#force_status').is(':checked') ? '1' : '0';
        formData['show_end_time'] = $('#show_end_time').is(':checked') ? '1' : '0';
        
        // Convert datetime-local to MySQL format
        if (formData.auction_start) {
            formData.auction_start = formData.auction_start.replace('T', ' ') + ':00';
        }
        if (formData.auction_end) {
            formData.auction_end = formData.auction_end.replace('T', ' ') + ':00';
        }
        
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'art-in-heaven')); ?>');
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    var msg = response.data.message;
                    if (response.data.final_status) {
                        msg += '\n<?php echo esc_js(__('Final status:', 'art-in-heaven')); ?> ' + response.data.final_status;
                    }
                    if (response.data.art_id) {
                        msg += '\n<?php echo esc_js(__('Art ID:', 'art-in-heaven')); ?> ' + response.data.art_id;
                    }
                    
                    // If this was a new piece, reload to show image uploader
                    if (!artPieceId && response.data.id) {
                        alert(msg + '\n\n<?php echo esc_js(__('You can now add images.', 'art-in-heaven')); ?>');
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-art&action=edit&id=')); ?>' + response.data.id;
                    } else {
                        alert(msg);
                        window.location.href = '<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-art')); ?>';
                    }
                } else {
                    alert(response.data.message || aihAdmin.strings.saveError || 'Error saving.');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                alert(aihAdmin.strings.saveError || 'Error saving: ' + error);
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
</script>
