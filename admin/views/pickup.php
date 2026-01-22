<?php
/**
 * Admin Pickup View
 * 
 * Manages pickup status for paid orders
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

global $wpdb;
$orders_table = AIH_Database::get_table('orders');
$order_items_table = AIH_Database::get_table('order_items');
$art_table = AIH_Database::get_table('art_pieces');
$bidders_table = AIH_Database::get_table('bidders');

// Handle tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'ready';

// Get counts for tabs - only count orders with at least one item (art piece exists)
$ready_count = $wpdb->get_var(
    "SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o
     INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
     INNER JOIN {$art_table} a ON oi.art_piece_id = a.id
     WHERE o.payment_status = 'paid' AND (o.pickup_status IS NULL OR o.pickup_status = 'pending' OR o.pickup_status = '')"
);
$picked_up_count = $wpdb->get_var(
    "SELECT COUNT(DISTINCT o.id) FROM {$orders_table} o
     INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
     INNER JOIN {$art_table} a ON oi.art_piece_id = a.id
     WHERE o.payment_status = 'paid' AND o.pickup_status = 'picked_up'"
);

// Get orders based on tab - only orders with at least one item
if ($current_tab === 'picked_up') {
    $orders = $wpdb->get_results(
        "SELECT DISTINCT o.*, 
                b.name_first, b.name_last, b.email_primary, b.phone_mobile, b.confirmation_code
         FROM {$orders_table} o
         LEFT JOIN {$bidders_table} b ON o.bidder_id = b.confirmation_code
         INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
         INNER JOIN {$art_table} a ON oi.art_piece_id = a.id
         WHERE o.payment_status = 'paid' AND o.pickup_status = 'picked_up'
         ORDER BY o.pickup_date DESC"
    );
} else {
    $orders = $wpdb->get_results(
        "SELECT DISTINCT o.*, 
                b.name_first, b.name_last, b.email_primary, b.phone_mobile, b.confirmation_code
         FROM {$orders_table} o
         LEFT JOIN {$bidders_table} b ON o.bidder_id = b.confirmation_code
         INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
         INNER JOIN {$art_table} a ON oi.art_piece_id = a.id
         WHERE o.payment_status = 'paid' AND (o.pickup_status IS NULL OR o.pickup_status = 'pending' OR o.pickup_status = '')
         ORDER BY o.payment_date ASC"
    );
}

// Get items for each order (only existing art pieces)
foreach ($orders as $order) {
    $order->items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, a.art_id, a.title, a.artist
         FROM {$order_items_table} oi
         JOIN {$art_table} a ON oi.art_piece_id = a.id
         WHERE oi.order_id = %d",
        $order->id
    ));
}
?>

<div class="wrap aih-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-archive" style="font-size: 28px; margin-right: 10px;"></span>
        <?php _e('Pickup Management', 'art-in-heaven'); ?>
    </h1>
    
    <!-- Stats Cards -->
    <div class="aih-stats-grid" style="margin: 20px 0;">
        <div class="aih-stat-card">
            <div class="aih-stat-icon" style="background: #fef3c7; color: #d97706;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="aih-stat-content">
                <div class="aih-stat-number"><?php echo $ready_count; ?></div>
                <div class="aih-stat-label"><?php _e('Ready for Pickup', 'art-in-heaven'); ?></div>
            </div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-icon" style="background: #d1fae5; color: #4a7c59;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="aih-stat-content">
                <div class="aih-stat-number"><?php echo $picked_up_count; ?></div>
                <div class="aih-stat-label"><?php _e('Picked Up', 'art-in-heaven'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-pickup&tab=ready'); ?>" 
           class="nav-tab <?php echo $current_tab === 'ready' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Ready for Pickup', 'art-in-heaven'); ?> 
            <span class="aih-tab-count"><?php echo $ready_count; ?></span>
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-pickup&tab=picked_up'); ?>" 
           class="nav-tab <?php echo $current_tab === 'picked_up' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Picked Up', 'art-in-heaven'); ?>
            <span class="aih-tab-count"><?php echo $picked_up_count; ?></span>
        </a>
    </nav>
    
    <div class="aih-panel" style="margin-top: 0; border-top: none; border-radius: 0 0 8px 8px;">
        <?php if (empty($orders)): ?>
            <div class="aih-empty-state">
                <?php if ($current_tab === 'ready'): ?>
                    <div class="aih-empty-icon">
                        <span class="dashicons dashicons-smiley"></span>
                    </div>
                    <p class="aih-empty-message"><?php _e('No orders ready for pickup!', 'art-in-heaven'); ?></p>
                    <p class="aih-empty-submessage"><?php _e('Orders will appear here once they are paid.', 'art-in-heaven'); ?></p>
                <?php else: ?>
                    <div class="aih-empty-icon">
                        <span class="dashicons dashicons-archive"></span>
                    </div>
                    <p class="aih-empty-message"><?php _e('No pickups recorded yet.', 'art-in-heaven'); ?></p>
                    <p class="aih-empty-submessage"><?php _e('Completed pickups will appear here.', 'art-in-heaven'); ?></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="aih-pickup-list">
                <?php foreach ($orders as $order): ?>
                    <div class="aih-pickup-card" data-order-id="<?php echo $order->id; ?>">
                        <div class="aih-pickup-header">
                            <div class="aih-pickup-order-info">
                                <span class="aih-order-number"><?php echo esc_html($order->order_number); ?></span>
                                <span class="aih-pickup-status <?php echo $order->pickup_status === 'picked_up' ? 'picked-up' : 'pending'; ?>">
                                    <?php echo $order->pickup_status === 'picked_up' ? __('Picked Up', 'art-in-heaven') : __('Ready', 'art-in-heaven'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="aih-pickup-bidder">
                            <div class="aih-bidder-name">
                                <strong><?php echo esc_html(trim($order->name_first . ' ' . $order->name_last) ?: 'Unknown'); ?></strong>
                                <code class="aih-confirmation-code"><?php echo esc_html($order->confirmation_code ?: $order->bidder_id); ?></code>
                            </div>
                            <div class="aih-bidder-contact">
                                <?php if ($order->email_primary): ?>
                                    <span><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html($order->email_primary); ?></span>
                                <?php endif; ?>
                                <?php if ($order->phone_mobile): ?>
                                    <span><span class="dashicons dashicons-phone"></span> <?php echo esc_html($order->phone_mobile); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="aih-pickup-items">
                            <div class="aih-items-header">
                                <strong><?php printf(_n('%d Item', '%d Items', count($order->items), 'art-in-heaven'), count($order->items)); ?></strong>
                            </div>
                            <div class="aih-items-list">
                                <?php foreach ($order->items as $item): ?>
                                    <div class="aih-pickup-item">
                                        <code class="aih-item-id"><?php echo esc_html($item->art_id); ?></code>
                                        <span class="aih-item-title"><?php echo esc_html($item->title); ?></span>
                                        <span class="aih-item-artist"><?php echo esc_html($item->artist); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="aih-pickup-footer">
                            <div class="aih-pickup-dates">
                                <span title="<?php esc_attr_e('Payment Date', 'art-in-heaven'); ?>">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php echo $order->payment_date ? date_i18n('M j, Y g:i a', strtotime($order->payment_date)) : '—'; ?>
                                </span>
                                <?php if ($order->pickup_status === 'picked_up' && $order->pickup_date): ?>
                                    <span title="<?php esc_attr_e('Pickup Date', 'art-in-heaven'); ?>">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php echo date_i18n('M j, Y g:i a', strtotime($order->pickup_date)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="aih-pickup-actions">
                                <?php if ($order->pickup_status !== 'picked_up'): ?>
                                    <button type="button" class="button button-primary aih-mark-picked-up" data-order-id="<?php echo $order->id; ?>" data-order-number="<?php echo esc_attr($order->order_number); ?>">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Mark as Picked Up', 'art-in-heaven'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button aih-undo-pickup" data-order-id="<?php echo $order->id; ?>">
                                        <span class="dashicons dashicons-undo"></span>
                                        <?php _e('Undo Pickup', 'art-in-heaven'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($order->pickup_status === 'picked_up' && (!empty($order->pickup_by) || !empty($order->pickup_notes))): ?>
                            <div class="aih-pickup-info-footer">
                                <?php if (!empty($order->pickup_by)): ?>
                                    <span class="aih-pickup-by">
                                        <span class="dashicons dashicons-admin-users"></span>
                                        <strong><?php _e('Picked up by:', 'art-in-heaven'); ?></strong> <?php echo esc_html($order->pickup_by); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($order->pickup_notes)): ?>
                                    <span class="aih-pickup-note">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php echo esc_html($order->pickup_notes); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Pickup Modal -->
<div id="aih-pickup-modal" class="aih-modal" style="display: none;">
    <div class="aih-modal-content">
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

<style>
/* Stats Grid */
.aih-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    max-width: 500px;
}

.aih-stat-card {
    display: flex;
    align-items: center;
    gap: 15px;
    background: #fff;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.aih-stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 10px;
    flex-shrink: 0;
}

.aih-stat-icon .dashicons {
    font-size: 24px;
    width: 24px;
    height: 24px;
    line-height: 1;
}

.aih-stat-content {
    flex: 1;
}

.aih-stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    line-height: 1.2;
}

.aih-stat-label {
    font-size: 13px;
    color: #8a8a8a;
    margin-top: 2px;
}

/* Empty State */
.aih-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.aih-empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: #f3f4f6;
    border-radius: 50%;
}

.aih-empty-icon .dashicons {
    font-size: 40px;
    width: 40px;
    height: 40px;
    color: #9ca3af;
    line-height: 1;
}

.aih-empty-message {
    font-size: 18px;
    font-weight: 600;
    color: #1c1c1c;
    margin: 0 0 8px;
}

.aih-empty-submessage {
    font-size: 14px;
    color: #8a8a8a;
    margin: 0;
}

/* Tab Count */
.aih-tab-count {
    display: inline-block;
    background: #e5e7eb;
    color: #1c1c1c;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    margin-left: 5px;
}

.nav-tab-active .aih-tab-count {
    background: #b8956b;
    color: #fff;
}

.aih-pickup-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.aih-pickup-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    transition: box-shadow 0.2s;
}

.aih-pickup-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.aih-pickup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.aih-pickup-order-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.aih-order-number {
    font-weight: 700;
    font-size: 16px;
    color: #111827;
}

.aih-pickup-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.aih-pickup-status.pending {
    background: #fef3c7;
    color: #d97706;
}

.aih-pickup-status.picked-up {
    background: #d1fae5;
    color: #4a7c59;
}

.aih-pickup-total {
    font-size: 18px;
    color: #4a7c59;
}

.aih-pickup-bidder {
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
}

.aih-bidder-name {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.aih-bidder-name strong {
    font-size: 15px;
    color: #111827;
}

.aih-confirmation-code {
    font-size: 12px;
    background: #f3f4f6;
    padding: 2px 8px;
    border-radius: 4px;
}

.aih-bidder-contact {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    font-size: 13px;
    color: #8a8a8a;
}

.aih-bidder-contact > span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.aih-bidder-contact .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    line-height: 1;
    flex-shrink: 0;
}

.aih-pickup-items {
    padding: 15px 20px;
}

.aih-items-header {
    font-size: 13px;
    color: #8a8a8a;
    margin-bottom: 10px;
}

.aih-items-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.aih-pickup-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #f9fafb;
    border-radius: 6px;
    font-size: 13px;
}

.aih-item-id {
    font-size: 11px;
    background: #e0e7ff;
    color: #4f46e5;
    padding: 2px 6px;
    border-radius: 3px;
    flex-shrink: 0;
}

.aih-item-title {
    flex: 1;
    font-weight: 500;
    color: #111827;
}

.aih-item-artist {
    color: #8a8a8a;
    font-style: italic;
}

.aih-pickup-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
}

.aih-pickup-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 12px;
    color: #8a8a8a;
}

.aih-pickup-dates > span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.aih-pickup-dates .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    line-height: 1;
    flex-shrink: 0;
}

.aih-pickup-actions .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.aih-pickup-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.aih-pickup-info-footer {
    padding: 12px 20px;
    background: #f0fdf4;
    border-top: 1px solid #bbf7d0;
    font-size: 13px;
    color: #166534;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.aih-pickup-info-footer .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    margin-right: 6px;
    vertical-align: middle;
}

.aih-pickup-by strong {
    margin-right: 5px;
}

/* Modal Styles */
.aih-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aih-modal-content {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    max-width: 450px;
    width: 90%;
    max-height: 90vh;
    overflow: auto;
}

.aih-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.aih-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #111827;
}

.aih-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #8a8a8a;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.aih-modal-close:hover {
    color: #111827;
}

.aih-modal-body {
    padding: 20px;
}

.aih-modal-order-info {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #1c1c1c;
}

.aih-form-row {
    margin-bottom: 15px;
}

.aih-form-row label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #1c1c1c;
}

.aih-form-row label .required {
    color: #a63d40;
}

.aih-form-row label .optional {
    color: #9ca3af;
    font-weight: normal;
}

.aih-form-row input[type="text"],
.aih-form-row textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.aih-form-row input[type="text"]:focus,
.aih-form-row textarea:focus {
    outline: none;
    border-color: #b8956b;
    box-shadow: 0 0 0 3px rgba(201, 162, 39, 0.1);
}

.aih-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 15px 20px;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
    border-radius: 0 0 12px 12px;
}

.aih-modal-footer .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.aih-modal-footer .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .aih-pickup-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .aih-pickup-item {
        flex-wrap: wrap;
    }
    
    .aih-item-title {
        width: 100%;
        order: -1;
    }
    
    .aih-pickup-footer {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .aih-pickup-actions {
        display: flex;
    }
    
    .aih-pickup-actions .button {
        flex: 1;
        justify-content: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var $modal = $('#aih-pickup-modal');
    var currentOrderId = null;
    
    // Open modal when clicking Mark as Picked Up
    $('.aih-mark-picked-up').on('click', function() {
        var $btn = $(this);
        currentOrderId = $btn.data('order-id');
        var orderNumber = $btn.data('order-number');
        
        // Reset form
        $('#pickup-order-id').val(currentOrderId);
        $('#pickup-by').val('');
        $('#pickup-notes').val('');
        
        // Set order info
        $('.aih-modal-order-info').html('<?php _e('Order:', 'art-in-heaven'); ?> <strong>' + orderNumber + '</strong>');
        
        // Show modal
        $modal.fadeIn(200);
        $('#pickup-by').focus();
    });
    
    // Close modal
    $('.aih-modal-close, .aih-modal-cancel').on('click', function() {
        $modal.fadeOut(200);
        currentOrderId = null;
    });
    
    // Close on backdrop click
    $modal.on('click', function(e) {
        if (e.target === this) {
            $modal.fadeOut(200);
            currentOrderId = null;
        }
    });
    
    // Close on escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            $modal.fadeOut(200);
            currentOrderId = null;
        }
    });
    
    // Confirm pickup
    $('#aih-confirm-pickup').on('click', function() {
        var $btn = $(this);
        var pickupBy = $('#pickup-by').val().trim();
        var pickupNotes = $('#pickup-notes').val().trim();
        
        if (!pickupBy) {
            alert('<?php _e('Please enter your name', 'art-in-heaven'); ?>');
            $('#pickup-by').focus();
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Processing...', 'art-in-heaven'); ?>');
        
        $.post(ajaxurl, {
            action: 'aih_update_pickup_status',
            nonce: aihAdmin.nonce,
            order_id: currentOrderId,
            status: 'picked_up',
            pickup_by: pickupBy,
            pickup_notes: pickupNotes
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data ? response.data.message : '<?php _e('Error updating pickup status', 'art-in-heaven'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <?php _e('Confirm Pickup', 'art-in-heaven'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed', 'art-in-heaven'); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <?php _e('Confirm Pickup', 'art-in-heaven'); ?>');
        });
    });
    
    // Submit form on enter in name field
    $('#pickup-by').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#aih-confirm-pickup').click();
        }
    });
    
    // Undo pickup
    $('.aih-undo-pickup').on('click', function() {
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        
        if (!confirm('<?php _e('Undo pickup status for this order?', 'art-in-heaven'); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e('Processing...', 'art-in-heaven'); ?>');
        
        $.post(ajaxurl, {
            action: 'aih_update_pickup_status',
            nonce: aihAdmin.nonce,
            order_id: orderId,
            status: 'pending'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data ? response.data.message : '<?php _e('Error updating pickup status', 'art-in-heaven'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> <?php _e('Undo Pickup', 'art-in-heaven'); ?>');
            }
        }).fail(function() {
            alert('<?php _e('Request failed', 'art-in-heaven'); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> <?php _e('Undo Pickup', 'art-in-heaven'); ?>');
        });
    });
});
</script>
