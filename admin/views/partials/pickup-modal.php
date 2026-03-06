<?php
/**
 * Pickup Modal Partial
 *
 * Shared modal for marking orders as picked up.
 * Used by both orders.php and pickup.php admin views.
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="aih-pickup-modal" class="aih-modal" style="display: none;">
    <div class="aih-modal-content aih-modal-content--sm">
        <div class="aih-modal-header">
            <h3><?php _e('Mark as Picked Up', 'art-in-heaven'); ?></h3>
            <button type="button" class="aih-modal-close">&times;</button>
        </div>
        <div class="aih-modal-body">
            <p class="aih-modal-order-info"></p>
            <form id="aih-pickup-form">
                <input type="hidden" name="order_id" id="pickup-order-id" value="">
                <div class="aih-form-row">
                    <label for="pickup-by"><?php _e('Your Name', 'art-in-heaven'); ?> <span class="required">*</span></label>
                    <input type="text" id="pickup-by" name="pickup_by" required placeholder="<?php esc_attr_e('Enter your name', 'art-in-heaven'); ?>">
                </div>
                <div class="aih-form-row">
                    <label for="pickup-notes"><?php _e('Notes', 'art-in-heaven'); ?> <span class="optional">(<?php _e('optional', 'art-in-heaven'); ?>)</span></label>
                    <textarea id="pickup-notes" name="pickup_notes" rows="3" placeholder="<?php esc_attr_e('Any notes about the pickup...', 'art-in-heaven'); ?>"></textarea>
                </div>
            </form>
        </div>
        <div class="aih-modal-footer">
            <button type="button" class="button aih-modal-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
            <button type="button" class="button button-primary" id="aih-confirm-pickup">
                <span class="dashicons dashicons-yes"></span>
                <?php _e('Confirm Pickup', 'art-in-heaven'); ?>
            </button>
        </div>
    </div>
</div>
