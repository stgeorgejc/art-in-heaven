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
                    <p><strong><?php _e('Order Date:', 'art-in-heaven'); ?></strong><br><?php echo AIH_Status::format_db_date($single_order->created_at, get_option('date_format') . ' ' . get_option('time_format')); ?></p>
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
                            <input type="text" name="reference" value="<?php echo esc_attr($single_order->payment_reference ?? ''); ?>">
                        </div>

                        <div class="aih-form-row">
                            <label><?php _e('Notes', 'art-in-heaven'); ?></label>
                            <textarea name="notes" rows="3"><?php echo esc_textarea($single_order->notes ?? ''); ?></textarea>
                        </div>

                        <?php if ($single_order->payment_date): ?>
                        <p><strong><?php _e('Payment Date:', 'art-in-heaven'); ?></strong><br>
                        <?php echo AIH_Status::format_db_date($single_order->payment_date, get_option('date_format') . ' ' . get_option('time_format')); ?></p>
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
                                <span class="aih-badge aih-badge-info aih-badge-lg">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Picked Up', 'art-in-heaven'); ?>
                                </span>
                                <?php if ($pickup_date): ?>
                                    <p style="margin-top: 10px; color: #8a8a8a; font-size: 13px;">
                                        <?php echo AIH_Status::format_db_date($pickup_date, get_option('date_format') . ' ' . get_option('time_format')); ?>
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
                                <span class="aih-badge aih-badge-warning aih-badge-lg">
                                    <span class="dashicons dashicons-clock"></span>
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

        <?php include __DIR__ . '/partials/pickup-modal.php'; ?>

    <?php else: ?>
        <!-- Orders List View -->
        <h1><?php _e('Orders & Payments', 'art-in-heaven'); ?></h1>

        <?php if ($payment_message): ?>
        <div class="notice notice-<?php echo esc_attr($payment_message_type); ?> is-dismissible">
            <p><?php echo esc_html($payment_message); ?></p>
        </div>
        <?php endif; ?>

        <?php AIH_Admin::open_stat_grid(); ?>
            <?php AIH_Admin::render_stat_card(array(
                'value' => (string) intval($payment_stats->total_orders),
                'label' => __('Total Orders', 'art-in-heaven'),
                'icon'  => 'dashicons-cart',
                'variant' => 'info',
            )); ?>
            <?php AIH_Admin::render_stat_card(array(
                'value' => (string) intval($payment_stats->paid_orders),
                'label' => __('Paid Orders', 'art-in-heaven'),
                'icon'  => 'dashicons-yes-alt',
                'variant' => 'success',
            )); ?>
            <?php AIH_Admin::render_stat_card(array(
                'value' => (string) intval($payment_stats->pending_orders),
                'label' => __('Pending Payment', 'art-in-heaven'),
                'icon'  => 'dashicons-clock',
                'variant' => 'warning',
            )); ?>
            <?php AIH_Admin::render_stat_card(array(
                'value' => '$' . number_format($payment_stats->total_collected, 2),
                'label' => __('Total Collected', 'art-in-heaven'),
                'icon'  => 'dashicons-money-alt',
                'variant' => 'money',
            )); ?>
            <?php if ($payment_stats->items_needing_orders > 0): ?>
            <?php AIH_Admin::render_stat_card(array(
                'value' => (string) intval($payment_stats->items_needing_orders),
                'label' => __('Items Needing Orders', 'art-in-heaven'),
                'icon'  => 'dashicons-warning',
                'variant' => 'danger',
            )); ?>
            <?php endif; ?>
        <?php AIH_Admin::close_stat_grid(); ?>

        <!-- Won Items Without Orders -->
        <?php if (!empty($won_without_orders)): ?>
        <div class="aih-card" style="margin: 20px 0;">
            <h2><?php _e('Won Items Without Orders', 'art-in-heaven'); ?></h2>
            <p class="description"><?php _e('These items have winning bids but no order created yet. Create an order and mark payment status.', 'art-in-heaven'); ?></p>

            <table class="wp-list-table widefat fixed striped aih-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Art ID', 'art-in-heaven'); ?></th>
                        <th><?php _e('Title', 'art-in-heaven'); ?></th>
                        <th><?php _e('Winner', 'art-in-heaven'); ?></th>
                        <th><?php _e('Amount', 'art-in-heaven'); ?></th>
                        <th><?php _e('Actions', 'art-in-heaven'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($won_without_orders as $item): ?>
                    <tr>
                        <td data-label="<?php esc_attr_e('Art ID', 'art-in-heaven'); ?>"><code><?php echo esc_html($item->art_id); ?></code></td>
                        <td data-label="<?php esc_attr_e('Title', 'art-in-heaven'); ?>"><strong><?php echo esc_html($item->title); ?></strong></td>
                        <td data-label="<?php esc_attr_e('Winner', 'art-in-heaven'); ?>"><?php echo esc_html(trim($item->winner_first . ' ' . $item->winner_last) ?: $item->bidder_id); ?></td>
                        <td data-label="<?php esc_attr_e('Amount', 'art-in-heaven'); ?>"><strong>$<?php echo number_format($item->winning_amount, 2); ?></strong></td>
                        <td class="aih-col-actions" data-label="">
                            <button type="button" class="button aih-mark-paid-btn"
                                    data-art-id="<?php echo intval($item->id); ?>"
                                    data-title="<?php echo esc_attr($item->title); ?>"
                                    data-amount="<?php echo esc_attr($item->winning_amount); ?>">
                                <?php _e('Mark Payment', 'art-in-heaven'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Tabs -->
        <nav class="nav-tab-wrapper">
            <a href="?page=art-in-heaven-orders" class="nav-tab <?php echo empty($status_filter) ? 'nav-tab-active' : ''; ?>">
                <?php _e('All Orders', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->total_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&tab=pending" class="nav-tab <?php echo $status_filter === 'pending' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Pending', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->pending_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&tab=paid" class="nav-tab <?php echo $status_filter === 'paid' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Paid', 'art-in-heaven'); ?> (<?php echo intval($payment_stats->paid_orders); ?>)
            </a>
            <a href="?page=art-in-heaven-orders&tab=refunded" class="nav-tab <?php echo $status_filter === 'refunded' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Refunded', 'art-in-heaven'); ?>
            </a>
            <a href="?page=art-in-heaven-orders&tab=cancelled" class="nav-tab <?php echo $status_filter === 'cancelled' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Cancelled', 'art-in-heaven'); ?>
            </a>
        </nav>

        <div class="aih-tab-content">
        <!-- Search Bar -->
        <div class="aih-filter-bar">
            <form method="get" class="aih-search-form">
                <input type="hidden" name="page" value="art-in-heaven-orders">
                <?php if ($status_filter): ?>
                    <input type="hidden" name="tab" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by order #, email, or name...', 'art-in-heaven'); ?>">
                <button type="submit" class="button"><?php _e('Search', 'art-in-heaven'); ?></button>
                <?php if (!empty($search)): ?>
                    <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders' . ($status_filter ? '&tab=' . urlencode($status_filter) : '')); ?>" class="button"><?php _e('Show All', 'art-in-heaven'); ?></a>
                <?php endif; ?>
            </form>
            <span class="aih-filter-count">
                <?php if (!empty($search)): ?>
                    <?php printf(__('%d orders matching "%s"', 'art-in-heaven'), intval($total_orders_filtered), esc_html($search)); ?>
                <?php else: ?>
                    <?php echo intval($total_orders_filtered); ?> <?php _e('orders', 'art-in-heaven'); ?>
                <?php endif; ?>
            </span>
        </div>

        <div class="aih-table-wrap">
        <table class="wp-list-table widefat fixed striped aih-admin-table aih-orders-table" id="aih-orders-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="order" aria-sort="none"><button type="button" class="aih-sort-btn"><?php _e('Order #', 'art-in-heaven'); ?> <span class="aih-sort-icon" aria-hidden="true">⇅</span></button></th>
                    <th><?php _e('Bidder', 'art-in-heaven'); ?></th>
                    <th class="sortable" data-sort="items" aria-sort="none"><button type="button" class="aih-sort-btn"><?php _e('Items', 'art-in-heaven'); ?> <span class="aih-sort-icon" aria-hidden="true">⇅</span></button></th>
                    <th class="sortable" data-sort="total" aria-sort="none"><button type="button" class="aih-sort-btn"><?php _e('Total', 'art-in-heaven'); ?> <span class="aih-sort-icon" aria-hidden="true">⇅</span></button></th>
                    <th><?php _e('Status', 'art-in-heaven'); ?></th>
                    <th class="sortable" data-sort="date" aria-sort="none"><button type="button" class="aih-sort-btn"><?php _e('Date', 'art-in-heaven'); ?> <span class="aih-sort-icon" aria-hidden="true">⇅</span></button></th>
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
                        data-date="<?php echo esc_attr($order->created_at); ?>">
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
                        <td data-label="<?php esc_attr_e('Date', 'art-in-heaven'); ?>"><?php echo AIH_Status::format_db_date($order->created_at, get_option('date_format')); ?></td>
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

        <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_orders_filtered, 'art-in-heaven'), number_format($total_orders_filtered)); ?></span>
                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    echo wp_kses_post($page_links);
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /.aih-tab-content -->

        <?php if (current_user_can('manage_options')): ?>
        <p style="margin-top: 30px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-transactions')); ?>" class="button">
                <?php _e('Manage Pushpay Transactions', 'art-in-heaven'); ?>
            </a>
        </p>
        <?php endif; ?>

        <!-- Mark Payment Modal -->
        <div id="aih-payment-modal" class="aih-modal" style="display:none;">
            <div class="aih-modal-content">
                <span class="aih-modal-close">&times;</span>
                <h2 id="aih-modal-title"><?php _e('Mark Payment', 'art-in-heaven'); ?></h2>

                <form method="post" id="aih-mark-payment-form">
                    <?php wp_nonce_field('aih_update_payment', 'aih_payment_nonce'); ?>
                    <input type="hidden" name="aih_update_payment" value="1">
                    <input type="hidden" name="art_piece_id" id="aih-art-piece-id" value="">

                    <table class="form-table">
                        <tr>
                            <th><label for="mark_payment_status"><?php _e('Payment Status', 'art-in-heaven'); ?></label></th>
                            <td>
                                <select name="payment_status" id="mark_payment_status" required>
                                    <option value="pending"><?php _e('Pending', 'art-in-heaven'); ?></option>
                                    <option value="paid"><?php _e('Paid', 'art-in-heaven'); ?></option>
                                    <option value="refunded"><?php _e('Refunded', 'art-in-heaven'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="mark_payment_method"><?php _e('Payment Method', 'art-in-heaven'); ?></label></th>
                            <td>
                                <select name="payment_method" id="mark_payment_method" required>
                                    <option value="cash"><?php _e('Cash', 'art-in-heaven'); ?></option>
                                    <option value="check"><?php _e('Check', 'art-in-heaven'); ?></option>
                                    <option value="card"><?php _e('Credit Card', 'art-in-heaven'); ?></option>
                                    <option value="pushpay"><?php _e('Pushpay', 'art-in-heaven'); ?></option>
                                    <option value="other"><?php _e('Other', 'art-in-heaven'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="mark_payment_reference"><?php _e('Reference #', 'art-in-heaven'); ?></label></th>
                            <td>
                                <input type="text" name="payment_reference" id="mark_payment_reference" class="regular-text" placeholder="<?php esc_attr_e('Check number, transaction ID, etc.', 'art-in-heaven'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="mark_payment_notes"><?php _e('Notes', 'art-in-heaven'); ?></label></th>
                            <td>
                                <textarea name="payment_notes" id="mark_payment_notes" rows="3" class="large-text"></textarea>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Save Payment', 'art-in-heaven'); ?></button>
                        <button type="button" class="button aih-modal-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
                    </p>
                </form>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    var $table = $('#aih-orders-table');
    var $tbody = $table.find('tbody');
    var $rows = $tbody.find('tr[data-order]');

    // Sorting functionality
    $('th.sortable .aih-sort-btn, th.sortable').on('click', function(e) {
        var $th = $(this).closest('th');
        var sortKey = $th.data('sort');
        var isAsc = $th.hasClass('asc');

        $('th.sortable').removeClass('asc desc').attr('aria-sort', 'none');
        $th.addClass(isAsc ? 'desc' : 'asc');
        $th.attr('aria-sort', isAsc ? 'descending' : 'ascending');
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
                    aihModal.alert(response.data.message).then(function() {
                        location.reload();
                    });
                } else {
                    aihModal.alert(response.data.message);
                }
            }
        });
    });

    // Mark payment button (for items without orders)
    $('.aih-mark-paid-btn').on('click', function() {
        var artId = $(this).data('art-id');
        var title = $(this).data('title');
        var amount = $(this).data('amount');

        $('#aih-art-piece-id').val(artId);
        $('#aih-modal-title').text('<?php echo esc_js(__('Mark Payment:', 'art-in-heaven')); ?> ' + title + ' ($' + parseFloat(amount).toFixed(2) + ')');
        $('#mark_payment_status').val('paid');
        $('#aih-payment-modal').show();
    });

    // Close modals (mark payment)
    $('.aih-modal-close, .aih-modal-cancel').on('click', function() {
        $(this).closest('.aih-modal').hide();
    });
    $('.aih-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    // Delete order
    $('.aih-delete-order').on('click', async function() {
        if (!(await aihModal.confirm(aihAdmin.strings.confirmDeleteOrder, { variant: 'danger' }))) return;

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
                    aihModal.alert(response.data.message);
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

        // Set order info (use .text() to prevent XSS)
        $('.aih-modal-order-info').text('<?php echo esc_js(__("Order:", "art-in-heaven")); ?> ' + orderNumber);

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
            aihModal.alert('<?php echo esc_js(__("Please enter your name", "art-in-heaven")); ?>').then(function() {
                $('#pickup-by').focus();
            });
            return;
        }

        $btn.prop('disabled', true).text('<?php echo esc_js(__("Processing...", "art-in-heaven")); ?>');

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
                aihModal.alert(response.data ? response.data.message : '<?php echo esc_js(__("Error updating pickup status", "art-in-heaven")); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span> <?php echo esc_js(__("Confirm Pickup", "art-in-heaven")); ?>');
            }
        }).fail(function() {
            aihModal.alert('<?php echo esc_js(__("Request failed. Check your network connection and try again.", "art-in-heaven")); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span> <?php echo esc_js(__("Confirm Pickup", "art-in-heaven")); ?>');
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
    $('.aih-undo-pickup-btn').on('click', async function() {
        var $btn = $(this);
        var orderId = $btn.data('order-id');

        if (!(await aihModal.confirm('<?php echo esc_js(__("Undo pickup status for this order?", "art-in-heaven")); ?>'))) {
            return;
        }

        $btn.prop('disabled', true).text('<?php echo esc_js(__("Processing...", "art-in-heaven")); ?>');

        $.post(aihAdmin.ajaxurl, {
            action: 'aih_update_pickup_status',
            nonce: aihAdmin.nonce,
            order_id: orderId,
            status: 'pending'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                aihModal.alert(response.data ? response.data.message : '<?php echo esc_js(__("Error updating pickup status", "art-in-heaven")); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="margin-right: 5px;"></span> <?php echo esc_js(__("Undo Pickup", "art-in-heaven")); ?>');
            }
        }).fail(function() {
            aihModal.alert('<?php echo esc_js(__("Request failed. Check your network connection and try again.", "art-in-heaven")); ?>');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-undo" style="margin-right: 5px;"></span> <?php echo esc_js(__("Undo Pickup", "art-in-heaven")); ?>');
        });
    });
});
</script>

