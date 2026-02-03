<?php
/**
 * Admin Orders View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap aih-admin-wrap">
    <?php if ($single_order): ?>
        <!-- Single Order View -->
        <h1>
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders'); ?>" class="page-title-action">
                &larr; <?php _e('Back to Orders', 'art-in-heaven'); ?>
            </a>
            <?php printf(__('Order %s', 'art-in-heaven'), esc_html($single_order->order_number)); ?>
        </h1>
        
        <div class="aih-order-detail-grid">
            <div class="aih-order-main">
                <div class="aih-form-section">
                    <h2><?php _e('Order Items', 'art-in-heaven'); ?></h2>
                    <div class="aih-table-wrap">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Image', 'art-in-heaven'); ?></th>
                                <th><?php _e('Art Piece', 'art-in-heaven'); ?></th>
                                <th><?php _e('Art ID', 'art-in-heaven'); ?></th>
                                <th><?php _e('Winning Bid', 'art-in-heaven'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $art_images = new AIH_Art_Images();
                            foreach ($single_order->items as $item):
                                $images = $art_images->get_images($item->art_piece_id);
                                $thumb = !empty($images) ? ($images[0]->watermarked_url ?: $images[0]->image_url) : ($item->watermarked_url ?: $item->image_url);
                            ?>
                            <tr>
                                <td>
                                    <?php if ($thumb): ?>
                                        <img src="<?php echo esc_url($thumb); ?>" class="aih-thumb" alt="">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($item->title); ?></strong>
                                    <br><small><?php echo esc_html($item->artist); ?></small>
                                </td>
                                <td><code><?php echo esc_html($item->art_id); ?></code></td>
                                <td><strong>$<?php echo number_format($item->winning_bid, 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong><?php _e('Subtotal:', 'art-in-heaven'); ?></strong></td>
                                <td><strong>$<?php echo number_format($single_order->subtotal, 2); ?></strong></td>
                            </tr>
                            <?php if ($single_order->tax > 0): ?>
                            <tr>
                                <td colspan="3" style="text-align: right;"><?php _e('Tax:', 'art-in-heaven'); ?></td>
                                <td>$<?php echo number_format($single_order->tax, 2); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="3" style="text-align: right;"><strong><?php _e('Total:', 'art-in-heaven'); ?></strong></td>
                                <td><strong style="font-size: 18px;">$<?php echo number_format($single_order->total, 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div><!-- /.aih-table-wrap -->
                </div>
            </div>
            
            <div class="aih-order-sidebar">
                <div class="aih-form-section">
                    <h2><?php _e('Bidder Information', 'art-in-heaven'); ?></h2>
                    <p><strong><?php _e('Name:', 'art-in-heaven'); ?></strong><br><?php echo esc_html(trim(($single_order->name_first ?? '') . ' ' . ($single_order->name_last ?? '')) ?: 'N/A'); ?></p>
                    <p><strong><?php _e('Email:', 'art-in-heaven'); ?></strong><br><?php echo esc_html($single_order->email ?: $single_order->bidder_id); ?></p>
                    <p><strong><?php _e('Phone:', 'art-in-heaven'); ?></strong><br><?php echo esc_html($single_order->phone ?: 'N/A'); ?></p>
                    <p><strong><?php _e('Order Date:', 'art-in-heaven'); ?></strong><br><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($single_order->created_at)); ?></p>
                </div>
                
                <div class="aih-form-section">
                    <h2><?php _e('Payment Status', 'art-in-heaven'); ?></h2>
                    <form id="aih-payment-form">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr($single_order->id); ?>">
                        
                        <div class="aih-form-row">
                            <label><?php _e('Status', 'art-in-heaven'); ?></label>
                            <select name="status" id="payment-status">
                                <option value="pending" <?php selected($single_order->payment_status, 'pending'); ?>><?php _e('Pending', 'art-in-heaven'); ?></option>
                                <option value="paid" <?php selected($single_order->payment_status, 'paid'); ?>><?php _e('Paid', 'art-in-heaven'); ?></option>
                                <option value="refunded" <?php selected($single_order->payment_status, 'refunded'); ?>><?php _e('Refunded', 'art-in-heaven'); ?></option>
                                <option value="cancelled" <?php selected($single_order->payment_status, 'cancelled'); ?>><?php _e('Cancelled', 'art-in-heaven'); ?></option>
                            </select>
                        </div>
                        
                        <div class="aih-form-row">
                            <label><?php _e('Payment Method', 'art-in-heaven'); ?></label>
                            <select name="method">
                                <option value="" <?php selected($single_order->payment_method, ''); ?>><?php _e('Select...', 'art-in-heaven'); ?></option>
                                <option value="pushpay" <?php selected($single_order->payment_method, 'pushpay'); ?>><?php _e('Pushpay', 'art-in-heaven'); ?></option>
                                <option value="cash" <?php selected($single_order->payment_method, 'cash'); ?>><?php _e('Cash', 'art-in-heaven'); ?></option>
                                <option value="check" <?php selected($single_order->payment_method, 'check'); ?>><?php _e('Check', 'art-in-heaven'); ?></option>
                                <option value="card" <?php selected($single_order->payment_method, 'card'); ?>><?php _e('Credit Card', 'art-in-heaven'); ?></option>
                                <option value="other" <?php selected($single_order->payment_method, 'other'); ?>><?php _e('Other', 'art-in-heaven'); ?></option>
                            </select>
                        </div>
                        
                        <div class="aih-form-row">
                            <label><?php _e('Reference/Check #', 'art-in-heaven'); ?></label>
                            <input type="text" name="reference" value="<?php echo esc_attr($single_order->payment_reference); ?>">
                        </div>
                        
                        <div class="aih-form-row">
                            <label><?php _e('Notes', 'art-in-heaven'); ?></label>
                            <textarea name="notes" rows="3"><?php echo esc_textarea($single_order->notes); ?></textarea>
                        </div>
                        
                        <?php if ($single_order->payment_date): ?>
                        <p><strong><?php _e('Payment Date:', 'art-in-heaven'); ?></strong><br>
                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($single_order->payment_date)); ?></p>
                        <?php endif; ?>
                        
                        <button type="submit" class="button button-primary"><?php _e('Update Payment', 'art-in-heaven'); ?></button>
                    </form>
                </div>
                
                <!-- Pickup Status Section -->
                <div class="aih-form-section">
                    <h2><?php _e('Pickup Status', 'art-in-heaven'); ?></h2>
                    <?php 
                    $pickup_status = $single_order->pickup_status ?? 'pending';
                    $pickup_date = $single_order->pickup_date ?? null;
                    $pickup_by = $single_order->pickup_by ?? '';
                    $pickup_notes = $single_order->pickup_notes ?? '';
                    $is_paid = ($single_order->payment_status === 'paid');
                    ?>
                    
                    <?php if (!$is_paid): ?>
                        <p class="description" style="color: #9ca3af; font-style: italic;">
                            <?php _e('Order must be paid before pickup can be processed.', 'art-in-heaven'); ?>
                        </p>
                    <?php else: ?>
                        <div class="aih-pickup-status-display" style="margin-bottom: 15px;">
                            <?php if ($pickup_status === 'picked_up'): ?>
                                <span class="aih-badge aih-badge-info" style="font-size: 14px; padding: 8px 16px;">
                                    <span class="dashicons dashicons-yes-alt" style="margin-right: 5px;"></span>
                                    <?php _e('Picked Up', 'art-in-heaven'); ?>
                                </span>
                                <?php if ($pickup_date): ?>
                                    <p style="margin-top: 10px; color: #8a8a8a; font-size: 13px;">
                                        <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($pickup_date)); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($pickup_by): ?>
                                    <p style="margin-top: 5px; color: #166534; font-size: 13px;">
                                        <strong><?php _e('By:', 'art-in-heaven'); ?></strong> <?php echo esc_html($pickup_by); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($pickup_notes): ?>
                                    <p style="margin-top: 5px; color: #8a8a8a; font-size: 13px; font-style: italic;">
                                        <?php echo esc_html($pickup_notes); ?>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="aih-badge aih-badge-warning" style="font-size: 14px; padding: 8px 16px;">
                                    <span class="dashicons dashicons-clock" style="margin-right: 5px;"></span>
                                    <?php _e('Ready for Pickup', 'art-in-heaven'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($pickup_status === 'picked_up'): ?>
                            <button type="button" class="button aih-undo-pickup-btn" data-order-id="<?php echo $single_order->id; ?>">
                                <span class="dashicons dashicons-undo" style="margin-right: 5px;"></span>
                                <?php _e('Undo Pickup', 'art-in-heaven'); ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="button button-primary aih-mark-pickup-btn" data-order-id="<?php echo $single_order->id; ?>" data-order-number="<?php echo esc_attr($single_order->order_number); ?>">
                                <span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>
                                <?php _e('Mark as Picked Up', 'art-in-heaven'); ?>
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
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
                            <label for="pickup-by"><?php _e('Your Name', 'art-in-heaven'); ?> <span style="color: #a63d40;">*</span></label>
                            <input type="text" id="pickup-by" name="pickup_by" required placeholder="<?php esc_attr_e('Enter your name', 'art-in-heaven'); ?>" style="width: 100%; padding: 8px 10px;">
                        </div>
                        <div class="aih-form-row">
                            <label for="pickup-notes"><?php _e('Notes', 'art-in-heaven'); ?> <span style="color: #9ca3af; font-weight: normal;">(<?php _e('optional', 'art-in-heaven'); ?>)</span></label>
                            <textarea id="pickup-notes" name="pickup_notes" rows="3" placeholder="<?php esc_attr_e('Any notes about the pickup...', 'art-in-heaven'); ?>" style="width: 100%; padding: 8px 10px;"></textarea>
                        </div>
                    </form>
                </div>
                <div class="aih-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 15px 20px; border-top: 1px solid #e5e7eb; background: #f9fafb;">
                    <button type="button" class="button aih-modal-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
                    <button type="button" class="button button-primary" id="aih-confirm-pickup">
                        <span class="dashicons dashicons-yes" style="margin-right: 5px;"></span>
                        <?php _e('Confirm Pickup', 'art-in-heaven'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
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
        }
        .aih-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .aih-modal-header h3 { margin: 0; font-size: 18px; }
        .aih-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #8a8a8a;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .aih-modal-close:hover { color: #111827; }
        .aih-modal-body { padding: 20px; }
        .aih-modal-order-info {
            background: #f9fafb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .aih-form-row { margin-bottom: 15px; }
        .aih-form-row label { display: block; margin-bottom: 6px; font-weight: 500; }
        </style>
        
    <?php else: ?>
        <!-- Orders List View -->
        <h1><?php _e('Orders & Payments', 'art-in-heaven'); ?></h1>
        
        <div class="aih-dashboard-stats">
            <div class="aih-stat-card">
                <div class="aih-stat-icon total"><span class="dashicons dashicons-cart"></span></div>
                <div class="aih-stat-content">
                    <span class="aih-stat-number"><?php echo intval($payment_stats->total_orders); ?></span>
                    <span class="aih-stat-label"><?php _e('Total Orders', 'art-in-heaven'); ?></span>
                </div>
            </div>
            <div class="aih-stat-card">
                <div class="aih-stat-icon active"><span class="dashicons dashicons-yes-alt"></span></div>
                <div class="aih-stat-content">
                    <span class="aih-stat-number"><?php echo intval($payment_stats->paid_orders); ?></span>
                    <span class="aih-stat-label"><?php _e('Paid Orders', 'art-in-heaven'); ?></span>
                </div>
            </div>
            <div class="aih-stat-card">
                <div class="aih-stat-icon ended"><span class="dashicons dashicons-clock"></span></div>
                <div class="aih-stat-content">
                    <span class="aih-stat-number"><?php echo intval($payment_stats->pending_orders); ?></span>
                    <span class="aih-stat-label"><?php _e('Pending Payment', 'art-in-heaven'); ?></span>
                </div>
            </div>
            <div class="aih-stat-card">
                <div class="aih-stat-icon bids"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="aih-stat-content">
                    <span class="aih-stat-number">$<?php echo number_format($payment_stats->total_collected, 2); ?></span>
                    <span class="aih-stat-label"><?php _e('Total Collected', 'art-in-heaven'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <nav class="nav-tab-wrapper">
            <a href="?page=art-in-heaven-orders" class="nav-tab <?php echo empty($status_filter) ? 'nav-tab-active' : ''; ?>">
                <?php _e('All Orders', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->total_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&status=pending" class="nav-tab <?php echo $status_filter === 'pending' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Pending', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->pending_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&status=paid" class="nav-tab <?php echo $status_filter === 'paid' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Paid', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->paid_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&status=refunded" class="nav-tab <?php echo $status_filter === 'refunded' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Refunded', 'art-in-heaven'); ?>
            </a>
            <a href="?page=art-in-heaven-orders&status=cancelled" class="nav-tab <?php echo $status_filter === 'cancelled' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Cancelled', 'art-in-heaven'); ?>
            </a>
        </nav>
        
        <div class="aih-tab-content">
        <!-- Search Bar -->
        <div class="aih-filter-bar">
            <input type="text" id="aih-search-orders" class="regular-text" placeholder="<?php _e('Search by order #, email, or name...', 'art-in-heaven'); ?>">
            <span class="aih-filter-count"><span id="aih-visible-count"><?php echo count($orders); ?></span> <?php _e('orders', 'art-in-heaven'); ?></span>
        </div>
        
        <div class="aih-table-wrap">
        <table class="wp-list-table widefat fixed striped aih-admin-table aih-orders-table" id="aih-orders-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="order"><?php _e('Order #', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th><?php _e('Bidder', 'art-in-heaven'); ?></th>
                    <th class="sortable" data-sort="items"><?php _e('Items', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th class="sortable" data-sort="total"><?php _e('Total', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th><?php _e('Status', 'art-in-heaven'); ?></th>
                    <th class="sortable" data-sort="date"><?php _e('Date', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th><?php _e('Actions', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7"><?php _e('No orders found.', 'art-in-heaven'); ?></td>
                </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr data-order="<?php echo esc_attr(strtolower($order->order_number)); ?>"
                        data-bidder="<?php echo esc_attr(strtolower($order->bidder_id . ' ' . ($order->name_first ?? '') . ' ' . ($order->name_last ?? ''))); ?>"
                        data-items="<?php echo esc_attr($order->item_count); ?>"
                        data-total="<?php echo esc_attr($order->total); ?>"
                        data-date="<?php echo esc_attr(strtotime($order->created_at)); ?>">
                        <td data-label="<?php esc_attr_e('Order #', 'art-in-heaven'); ?>"><strong><a href="?page=art-in-heaven-orders&order_id=<?php echo $order->id; ?>"><?php echo esc_html($order->order_number); ?></a></strong></td>
                        <td data-label="<?php esc_attr_e('Bidder', 'art-in-heaven'); ?>">
                            <?php
                                $bidder_name = trim(($order->name_first ?? '') . ' ' . ($order->name_last ?? ''));
                                if ($bidder_name) {
                                    echo esc_html($bidder_name);
                                    echo '<br><small>' . esc_html($order->bidder_id) . '</small>';
                                } else {
                                    echo esc_html($order->bidder_id);
                                }
                            ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Items', 'art-in-heaven'); ?>"><?php echo intval($order->item_count); ?></td>
                        <td data-label="<?php esc_attr_e('Total', 'art-in-heaven'); ?>"><strong>$<?php echo number_format($order->total, 2); ?></strong></td>
                        <td data-label="<?php esc_attr_e('Status', 'art-in-heaven'); ?>">
                            <?php
                            $status_class = 'pending';
                            if ($order->payment_status === 'paid') $status_class = 'active';
                            if ($order->payment_status === 'refunded' || $order->payment_status === 'cancelled') $status_class = 'ended';
                            ?>
                            <span class="aih-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst($order->payment_status)); ?></span>
                            <?php if ($order->payment_status === 'paid' && isset($order->pickup_status) && $order->pickup_status === 'picked_up'): ?>
                                <span class="aih-badge aih-badge-info" style="margin-left: 5px;"><?php _e('Picked Up', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e('Date', 'art-in-heaven'); ?>"><?php echo date_i18n(get_option('date_format'), strtotime($order->created_at)); ?></td>
                        <td class="aih-col-actions" data-label="">
                            <a href="?page=art-in-heaven-orders&order_id=<?php echo intval($order->id); ?>" class="button button-small"><?php _e('View', 'art-in-heaven'); ?></a>
                            <button type="button" class="button button-small aih-delete-order" data-id="<?php echo intval($order->id); ?>"><?php _e('Delete', 'art-in-heaven'); ?></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div><!-- /.aih-table-wrap -->
        </div><!-- /.aih-tab-content -->
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    var $table = $('#aih-orders-table');
    var $tbody = $table.find('tbody');
    var $rows = $tbody.find('tr[data-order]');
    
    // Search/Filter functionality
    $('#aih-search-orders').on('input keyup', function() {
        var search = $(this).val().toLowerCase().trim();
        var visibleCount = 0;
        
        $rows.each(function() {
            var $row = $(this);
            var order = $row.data('order') || '';
            var bidder = $row.data('bidder') || '';
            var show = true;
            
            if (search && order.indexOf(search) === -1 && bidder.indexOf(search) === -1) {
                show = false;
            }
            
            if (show) {
                $row.removeClass('aih-hidden');
                visibleCount++;
            } else {
                $row.addClass('aih-hidden');
            }
        });
        
        $('#aih-visible-count').text(visibleCount);
    });
    
    // Sorting functionality
    $('th.sortable').on('click', function() {
        var $th = $(this);
        var sortKey = $th.data('sort');
        var isAsc = $th.hasClass('asc');
        
        $('th.sortable').removeClass('asc desc');
        $th.addClass(isAsc ? 'desc' : 'asc');
        var sortDir = isAsc ? -1 : 1;
        
        var rows = $rows.get();
        rows.sort(function(a, b) {
            var aVal = $(a).data(sortKey);
            var bVal = $(b).data(sortKey);
            
            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return (aVal - bVal) * sortDir;
            }
            
            aVal = String(aVal || '');
            bVal = String(bVal || '');
            return aVal.localeCompare(bVal) * sortDir;
        });
        
        $.each(rows, function(i, row) {
            $tbody.append(row);
        });
    });
    
    // Update payment
    $('#aih-payment-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_update_payment',
                nonce: aihAdmin.nonce,
                order_id: $form.find('[name="order_id"]').val(),
                status: $form.find('[name="status"]').val(),
                method: $form.find('[name="method"]').val(),
                reference: $form.find('[name="reference"]').val(),
                notes: $form.find('[name="notes"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Delete order
    $('.aih-delete-order').on('click', function() {
        if (!confirm(aihAdmin.strings.confirmDeleteOrder)) return;
        
        var $btn = $(this);
        var $row = $btn.closest('tr');
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_delete_order',
                nonce: aihAdmin.nonce,
                order_id: $btn.data('id')
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(response.data.message);
                }
            }
        });
    });
    
    // Pickup Modal handling
    var $modal = $('#aih-pickup-modal');
    var currentOrderId = null;
    
    // Open modal when clicking Mark as Picked Up
    $('.aih-mark-pickup-btn').on('click', function() {
        var $btn = $(this);
        currentOrderId = $btn.data('order-id');
        var orderNumber = $btn.data('order-number');
        
        // Reset form
        $('#pickup-order-id').val(currentOrderId);
        $('#pickup-by').val('');
        $('#pickup-notes').val('');
        
        // Set order info
        $('.aih-modal-order-info').html('<?php _e("Order:", "art-in-heaven"); ?> <strong>' + orderNumber + '</strong>');
        
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
            alert('<?php _e("Please enter your name", "art-in-heaven"); ?>');
            $('#pickup-by').focus();
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e("Processing...", "art-in-heaven"); ?>');
        
        $.post(aihAdmin.ajaxurl, {
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
                alert(response.data ? response.data.message : '<?php _e("Error updating pickup status", "art-in-heaven"); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span> <?php _e("Confirm Pickup", "art-in-heaven"); ?>');
            }
        }).fail(function() {
            alert('<?php _e("Request failed", "art-in-heaven"); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span> <?php _e("Confirm Pickup", "art-in-heaven"); ?>');
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
    $('.aih-undo-pickup-btn').on('click', function() {
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        
        if (!confirm('<?php _e("Undo pickup status for this order?", "art-in-heaven"); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true).text('<?php _e("Processing...", "art-in-heaven"); ?>');
        
        $.post(aihAdmin.ajaxurl, {
            action: 'aih_update_pickup_status',
            nonce: aihAdmin.nonce,
            order_id: orderId,
            status: 'pending'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data ? response.data.message : '<?php _e("Error updating pickup status", "art-in-heaven"); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="margin-right: 5px;"></span> <?php _e("Undo Pickup", "art-in-heaven"); ?>');
            }
        }).fail(function() {
            alert('<?php _e("Request failed", "art-in-heaven"); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="margin-right: 5px;"></span> <?php _e("Undo Pickup", "art-in-heaven"); ?>');
        });
    });
});
</script>

<style>
/* orders minimal overrides - main styles in aih-admin.css */
</style>
