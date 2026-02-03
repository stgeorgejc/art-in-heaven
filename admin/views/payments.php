<?php
/**
 * Admin Payments & Transactions View
 * 
 * Shows payment transactions and allows manual payment marking
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$orders_table = AIH_Database::get_table('orders');
$order_items_table = AIH_Database::get_table('order_items');
$art_table = AIH_Database::get_table('art_pieces');
$bidders_table = AIH_Database::get_table('bidders');
$bids_table = AIH_Database::get_table('bids');

// Handle manual payment status updates
if (isset($_POST['aih_update_payment']) && wp_verify_nonce($_POST['aih_payment_nonce'], 'aih_update_payment')) {
    $art_piece_id = intval($_POST['art_piece_id']);
    $payment_status = sanitize_text_field($_POST['payment_status']);
    $payment_method = sanitize_text_field($_POST['payment_method']);
    $payment_reference = sanitize_text_field($_POST['payment_reference']);
    $payment_notes = sanitize_textarea_field($_POST['payment_notes']);
    
    // Check if this art piece already has an order
    $existing_order = $wpdb->get_row($wpdb->prepare(
        "SELECT o.* FROM $orders_table o
         JOIN $order_items_table oi ON o.id = oi.order_id
         WHERE oi.art_piece_id = %d",
        $art_piece_id
    ));
    
    if ($existing_order) {
        // Update existing order
        $wpdb->update(
            $orders_table,
            array(
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'payment_reference' => $payment_reference,
                'notes' => $payment_notes,
                'payment_date' => $payment_status === 'paid' ? current_time('mysql') : null,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $existing_order->id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        $message = __('Payment status updated.', 'art-in-heaven');
        $message_type = 'success';
    } else {
        // Get winning bid info
        $winning_bid = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, a.title FROM $bids_table b
             JOIN $art_table a ON b.art_piece_id = a.id
             WHERE b.art_piece_id = %d AND b.is_winning = 1",
            $art_piece_id
        ));
        
        if ($winning_bid) {
            // Create new order
            $order_number = 'AIH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $tax_rate = floatval(get_option('aih_tax_rate', 0));
            $tax = $winning_bid->bid_amount * ($tax_rate / 100);
            $total = $winning_bid->bid_amount + $tax;
            
            $wpdb->insert($orders_table, array(
                'order_number' => $order_number,
                'bidder_id' => $winning_bid->bidder_id,
                'subtotal' => $winning_bid->bid_amount,
                'tax' => $tax,
                'total' => $total,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'payment_reference' => $payment_reference,
                'notes' => $payment_notes,
                'payment_date' => $payment_status === 'paid' ? current_time('mysql') : null,
                'created_at' => current_time('mysql')
            ));
            
            $order_id = $wpdb->insert_id;
            
            $wpdb->insert($order_items_table, array(
                'order_id' => $order_id,
                'art_piece_id' => $art_piece_id,
                'winning_bid' => $winning_bid->bid_amount
            ));
            
            $message = __('Order created and payment status set.', 'art-in-heaven');
            $message_type = 'success';
        } else {
            $message = __('No winning bid found for this art piece.', 'art-in-heaven');
            $message_type = 'error';
        }
    }
}

// Get filter
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_method = isset($_GET['method']) ? sanitize_text_field($_GET['method']) : '';

// Get all orders with items
$where = "1=1";
if ($filter_status) {
    $where .= $wpdb->prepare(" AND o.payment_status = %s", $filter_status);
}
if ($filter_method) {
    $where .= $wpdb->prepare(" AND o.payment_method = %s", $filter_method);
}

$orders = $wpdb->get_results(
    "SELECT o.*, 
            COALESCE(bd.name_first, '') as name_first, 
            COALESCE(bd.name_last, '') as name_last,
            COALESCE(bd.email_primary, '') as email,
            (SELECT COUNT(*) FROM $order_items_table oi JOIN $art_table a ON oi.art_piece_id = a.id WHERE oi.order_id = o.id) as item_count
     FROM $orders_table o
     LEFT JOIN $bidders_table bd ON o.bidder_id = bd.confirmation_code
     WHERE $where
     ORDER BY o.created_at DESC"
);

// Get payment stats
$stats = $wpdb->get_row(
    "SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_collected,
        SUM(CASE WHEN payment_status = 'pending' THEN total ELSE 0 END) as total_pending
     FROM $orders_table"
);

// Get won items without orders (for manual payment)
$won_without_orders = $wpdb->get_results(
    "SELECT a.*, b.bid_amount as winning_amount, b.bidder_id,
            COALESCE(bd.name_first, '') as winner_first,
            COALESCE(bd.name_last, '') as winner_last
     FROM $bids_table b
     JOIN $art_table a ON b.art_piece_id = a.id
     LEFT JOIN $order_items_table oi ON oi.art_piece_id = a.id
     LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
     WHERE b.is_winning = 1 
     AND (a.auction_end < NOW() OR a.status = 'ended')
     AND oi.id IS NULL
     ORDER BY a.auction_end DESC"
);
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Payments & Transactions', 'art-in-heaven'); ?></h1>
    
    <?php if (isset($message)): ?>
    <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Payment Stats -->
    <div class="aih-stats-grid">
        <div class="aih-stat-card aih-card-success">
            <div class="aih-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
            <div class="aih-stat-content">
                <span class="aih-stat-number">$<?php echo number_format($stats->total_collected ?? 0, 2); ?></span>
                <span class="aih-stat-label"><?php _e('Total Collected', 'art-in-heaven'); ?></span>
            </div>
        </div>
        <div class="aih-stat-card aih-card-warning">
            <div class="aih-stat-icon"><span class="dashicons dashicons-clock"></span></div>
            <div class="aih-stat-content">
                <span class="aih-stat-number">$<?php echo number_format($stats->total_pending ?? 0, 2); ?></span>
                <span class="aih-stat-label"><?php _e('Pending Payment', 'art-in-heaven'); ?></span>
            </div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-icon"><span class="dashicons dashicons-cart"></span></div>
            <div class="aih-stat-content">
                <span class="aih-stat-number"><?php echo intval($stats->paid_orders ?? 0); ?>/<?php echo intval($stats->total_orders ?? 0); ?></span>
                <span class="aih-stat-label"><?php _e('Orders Paid', 'art-in-heaven'); ?></span>
            </div>
        </div>
        <div class="aih-stat-card aih-card-danger">
            <div class="aih-stat-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="aih-stat-content">
                <span class="aih-stat-number"><?php echo count($won_without_orders); ?></span>
                <span class="aih-stat-label"><?php _e('Won Items Needing Orders', 'art-in-heaven'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Items Needing Manual Payment -->
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
                                data-art-id="<?php echo $item->id; ?>"
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
    
    <!-- Filter Bar -->
    <div class="aih-filter-bar">
        <form method="get" action="" class="aih-filter-form">
            <input type="hidden" name="page" value="art-in-heaven-payments">
            
            <select name="status">
                <option value=""><?php _e('All Statuses', 'art-in-heaven'); ?></option>
                <option value="paid" <?php selected($filter_status, 'paid'); ?>><?php _e('Paid', 'art-in-heaven'); ?></option>
                <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php _e('Pending', 'art-in-heaven'); ?></option>
                <option value="refunded" <?php selected($filter_status, 'refunded'); ?>><?php _e('Refunded', 'art-in-heaven'); ?></option>
            </select>
            
            <select name="method">
                <option value=""><?php _e('All Methods', 'art-in-heaven'); ?></option>
                <option value="pushpay" <?php selected($filter_method, 'pushpay'); ?>><?php _e('Pushpay', 'art-in-heaven'); ?></option>
                <option value="cash" <?php selected($filter_method, 'cash'); ?>><?php _e('Cash', 'art-in-heaven'); ?></option>
                <option value="check" <?php selected($filter_method, 'check'); ?>><?php _e('Check', 'art-in-heaven'); ?></option>
                <option value="card" <?php selected($filter_method, 'card'); ?>><?php _e('Credit Card', 'art-in-heaven'); ?></option>
                <option value="other" <?php selected($filter_method, 'other'); ?>><?php _e('Other', 'art-in-heaven'); ?></option>
            </select>
            
            <button type="submit" class="button"><?php _e('Filter', 'art-in-heaven'); ?></button>
            <?php if ($filter_status || $filter_method): ?>
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-payments'); ?>" class="button"><?php _e('Clear', 'art-in-heaven'); ?></a>
            <?php endif; ?>
        </form>
        
        <span class="aih-filter-count"><?php echo count($orders); ?> <?php _e('orders', 'art-in-heaven'); ?></span>
    </div>
    
    <!-- Orders Table -->
    <h2><?php _e('All Orders', 'art-in-heaven'); ?></h2>
    
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-admin-table">
        <thead>
            <tr>
                <th style="width: 120px;"><?php _e('Order #', 'art-in-heaven'); ?></th>
                <th><?php _e('Bidder', 'art-in-heaven'); ?></th>
                <th style="width: 80px;"><?php _e('Items', 'art-in-heaven'); ?></th>
                <th style="width: 100px;"><?php _e('Total', 'art-in-heaven'); ?></th>
                <th style="width: 100px;"><?php _e('Method', 'art-in-heaven'); ?></th>
                <th style="width: 100px;"><?php _e('Status', 'art-in-heaven'); ?></th>
                <th style="width: 150px;"><?php _e('Date', 'art-in-heaven'); ?></th>
                <th style="width: 120px;"><?php _e('Actions', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
            <tr>
                <td colspan="8"><?php _e('No orders found.', 'art-in-heaven'); ?></td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td data-label="<?php esc_attr_e('Order #', 'art-in-heaven'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders&order_id=' . $order->id); ?>">
                        <strong><?php echo esc_html($order->order_number); ?></strong>
                    </a>
                </td>
                <td data-label="<?php esc_attr_e('Bidder', 'art-in-heaven'); ?>">
                    <?php echo esc_html(trim($order->name_first . ' ' . $order->name_last) ?: $order->bidder_id); ?>
                    <?php if ($order->email): ?>
                    <br><small><?php echo esc_html($order->email); ?></small>
                    <?php endif; ?>
                </td>
                <td data-label="<?php esc_attr_e('Items', 'art-in-heaven'); ?>"><?php echo intval($order->item_count); ?></td>
                <td data-label="<?php esc_attr_e('Total', 'art-in-heaven'); ?>"><strong>$<?php echo number_format($order->total, 2); ?></strong></td>
                <td data-label="<?php esc_attr_e('Method', 'art-in-heaven'); ?>">
                    <?php 
                    $method_labels = array(
                        'pushpay' => 'Pushpay',
                        'cash' => 'Cash',
                        'check' => 'Check',
                        'card' => 'Card',
                        'other' => 'Other'
                    );
                    echo esc_html($method_labels[$order->payment_method] ?? ucfirst($order->payment_method));
                    ?>
                </td>
                <td data-label="<?php esc_attr_e('Status', 'art-in-heaven'); ?>">
                    <?php if ($order->payment_status === 'paid'): ?>
                    <span class="aih-status-badge active"><?php _e('Paid', 'art-in-heaven'); ?></span>
                    <?php elseif ($order->payment_status === 'refunded'): ?>
                    <span class="aih-status-badge ended"><?php _e('Refunded', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                    <span class="aih-status-badge upcoming"><?php _e('Pending', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                </td>
                <td data-label="<?php esc_attr_e('Date', 'art-in-heaven'); ?>">
                    <?php echo date_i18n('M j, Y g:i a', strtotime($order->created_at)); ?>
                    <?php if ($order->payment_date && $order->payment_status === 'paid'): ?>
                    <br><small style="color: #4a7c59;"><?php _e('Paid:', 'art-in-heaven'); ?> <?php echo date_i18n('M j', strtotime($order->payment_date)); ?></small>
                    <?php endif; ?>
                </td>
                <td class="aih-col-actions" data-label="">
                    <button type="button" class="button button-small aih-update-order-btn"
                            data-order-id="<?php echo $order->id; ?>"
                            data-order-number="<?php echo esc_attr($order->order_number); ?>"
                            data-status="<?php echo esc_attr($order->payment_status); ?>"
                            data-method="<?php echo esc_attr($order->payment_method); ?>"
                            data-reference="<?php echo esc_attr($order->payment_reference ?? ''); ?>"
                            data-notes="<?php echo esc_attr($order->notes ?? ''); ?>">
                        <?php _e('Update', 'art-in-heaven'); ?>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div><!-- /.aih-table-wrap -->
    
    <!-- Pushpay Transactions Section -->
    <?php
    $pushpay = AIH_Pushpay_API::get_instance();
    $transactions_table = AIH_Database::get_table('pushpay_transactions');
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$transactions_table}'") === $transactions_table;
    
    if ($pushpay->is_configured() && $table_exists):
        $filter_matched = isset($_GET['matched']) ? sanitize_text_field($_GET['matched']) : '';
        $transactions = $pushpay->get_synced_transactions(array(
            'matched' => $filter_matched,
            'limit' => 100
        ));
        $last_sync = get_option('aih_pushpay_last_sync', '');
    ?>
    <div style="margin-top: 40px;">
        <h2><?php _e('Pushpay Transactions', 'art-in-heaven'); ?></h2>
        <p class="description">
            <?php _e('Transactions synced from Pushpay.', 'art-in-heaven'); ?>
            <?php if ($last_sync): ?>
            <strong><?php _e('Last sync:', 'art-in-heaven'); ?></strong> <?php echo date_i18n('M j, Y g:i a', strtotime($last_sync)); ?>
            <?php endif; ?>
            <button type="button" id="aih-sync-transactions-btn" class="button button-small" style="margin-left: 10px;">
                <?php _e('Sync Now', 'art-in-heaven'); ?>
            </button>
            <span id="aih-sync-status"></span>
        </p>
        
        <!-- Transaction Filters -->
        <div class="aih-filter-bar" style="margin: 15px 0;">
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-payments'); ?>" 
               class="button <?php echo !$filter_matched ? 'button-primary' : ''; ?>">
                <?php _e('All', 'art-in-heaven'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-payments&matched=yes'); ?>" 
               class="button <?php echo $filter_matched === 'yes' ? 'button-primary' : ''; ?>">
                <?php _e('Matched', 'art-in-heaven'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-payments&matched=no'); ?>" 
               class="button <?php echo $filter_matched === 'no' ? 'button-primary' : ''; ?>">
                <?php _e('Unmatched', 'art-in-heaven'); ?>
            </a>
        </div>
        
        <div class="aih-table-wrap">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Date', 'art-in-heaven'); ?></th>
                    <th><?php _e('Payer', 'art-in-heaven'); ?></th>
                    <th style="width: 100px;"><?php _e('Amount', 'art-in-heaven'); ?></th>
                    <th style="width: 120px;"><?php _e('Fund', 'art-in-heaven'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'art-in-heaven'); ?></th>
                    <th style="width: 120px;"><?php _e('Matched Order', 'art-in-heaven'); ?></th>
                    <th style="width: 150px;"><?php _e('Actions', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="7"><?php _e('No transactions found. Click "Sync Now" to fetch from Pushpay.', 'art-in-heaven'); ?></td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                <tr>
                    <td><?php echo date_i18n('M j, Y g:i a', strtotime($txn->payment_date)); ?></td>
                    <td>
                        <strong><?php echo esc_html($txn->payer_name ?: __('Unknown', 'art-in-heaven')); ?></strong>
                        <?php if ($txn->payer_email): ?>
                        <br><small><?php echo esc_html($txn->payer_email); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><strong>$<?php echo number_format($txn->amount, 2); ?></strong></td>
                    <td><?php echo esc_html($txn->fund ?: '—'); ?></td>
                    <td>
                        <?php if ($txn->status === 'Success'): ?>
                        <span class="aih-status-badge active"><?php echo esc_html($txn->status); ?></span>
                        <?php else: ?>
                        <span class="aih-status-badge"><?php echo esc_html($txn->status); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($txn->order_number): ?>
                        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders&order_id=' . $txn->order_id); ?>">
                            <?php echo esc_html($txn->order_number); ?>
                        </a>
                        <?php else: ?>
                        <span style="color: #999;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$txn->order_id): ?>
                        <button type="button" class="button button-small aih-match-txn-btn"
                                data-txn-id="<?php echo $txn->id; ?>"
                                data-amount="<?php echo esc_attr($txn->amount); ?>"
                                data-payer="<?php echo esc_attr($txn->payer_name); ?>">
                            <?php _e('Match to Order', 'art-in-heaven'); ?>
                        </button>
                        <?php else: ?>
                        <span style="color: #4a7c59;">✓ <?php _e('Matched', 'art-in-heaven'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div><!-- /.aih-table-wrap -->
    </div>
    <?php elseif (!$pushpay->is_configured()): ?>
    <div class="aih-card" style="margin-top: 40px; background: #fef3c7; border-left: 4px solid #f59e0b;">
        <h3><?php _e('Pushpay API Not Configured', 'art-in-heaven'); ?></h3>
        <p><?php _e('To sync transactions from Pushpay, configure your API credentials in', 'art-in-heaven'); ?> 
           <a href="<?php echo admin_url('admin.php?page=art-in-heaven-settings'); ?>"><?php _e('Settings', 'art-in-heaven'); ?></a>.
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- Match Transaction Modal -->
<div id="aih-match-modal" class="aih-modal" style="display:none;">
    <div class="aih-modal-content">
        <span class="aih-modal-close">&times;</span>
        <h2><?php _e('Match Transaction to Order', 'art-in-heaven'); ?></h2>
        <p id="aih-match-info"></p>
        
        <input type="hidden" id="aih-match-txn-id" value="">
        
        <table class="form-table">
            <tr>
                <th><label for="aih-match-order"><?php _e('Select Order', 'art-in-heaven'); ?></label></th>
                <td>
                    <select id="aih-match-order" class="regular-text" style="width: 100%;">
                        <option value=""><?php _e('— Select an order —', 'art-in-heaven'); ?></option>
                        <?php 
                        // Get pending orders for matching
                        $pending_orders = $wpdb->get_results(
                            "SELECT o.*, COALESCE(bd.name_first, '') as name_first, COALESCE(bd.name_last, '') as name_last
                             FROM {$orders_table} o
                             LEFT JOIN {$bidders_table} bd ON o.bidder_id = bd.confirmation_code
                             WHERE o.payment_status = 'pending'
                             ORDER BY o.created_at DESC"
                        );
                        foreach ($pending_orders as $po): 
                            $name = trim($po->name_first . ' ' . $po->name_last);
                        ?>
                        <option value="<?php echo $po->id; ?>" data-amount="<?php echo $po->total; ?>">
                            <?php echo esc_html($po->order_number); ?> - $<?php echo number_format($po->total, 2); ?> 
                            <?php if ($name): ?>(<?php echo esc_html($name); ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Only pending orders are shown.', 'art-in-heaven'); ?></p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="button" class="button button-primary" id="aih-confirm-match"><?php _e('Match & Mark Paid', 'art-in-heaven'); ?></button>
            <button type="button" class="button aih-modal-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
        </p>
    </div>
</div>

<!-- Mark Payment Modal -->
<div id="aih-payment-modal" class="aih-modal" style="display:none;">
    <div class="aih-modal-content">
        <span class="aih-modal-close">&times;</span>
        <h2 id="aih-modal-title"><?php _e('Mark Payment', 'art-in-heaven'); ?></h2>
        
        <form method="post" id="aih-payment-form">
            <?php wp_nonce_field('aih_update_payment', 'aih_payment_nonce'); ?>
            <input type="hidden" name="aih_update_payment" value="1">
            <input type="hidden" name="art_piece_id" id="aih-art-piece-id" value="">
            
            <table class="form-table">
                <tr>
                    <th><label for="payment_status"><?php _e('Payment Status', 'art-in-heaven'); ?></label></th>
                    <td>
                        <select name="payment_status" id="payment_status" required>
                            <option value="pending"><?php _e('Pending', 'art-in-heaven'); ?></option>
                            <option value="paid"><?php _e('Paid', 'art-in-heaven'); ?></option>
                            <option value="refunded"><?php _e('Refunded', 'art-in-heaven'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="payment_method"><?php _e('Payment Method', 'art-in-heaven'); ?></label></th>
                    <td>
                        <select name="payment_method" id="payment_method" required>
                            <option value="cash"><?php _e('Cash', 'art-in-heaven'); ?></option>
                            <option value="check"><?php _e('Check', 'art-in-heaven'); ?></option>
                            <option value="card"><?php _e('Credit Card', 'art-in-heaven'); ?></option>
                            <option value="pushpay"><?php _e('Pushpay', 'art-in-heaven'); ?></option>
                            <option value="other"><?php _e('Other', 'art-in-heaven'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="payment_reference"><?php _e('Reference #', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" name="payment_reference" id="payment_reference" class="regular-text" placeholder="<?php esc_attr_e('Check number, transaction ID, etc.', 'art-in-heaven'); ?>">
                    </td>
                </tr>
                <tr>
                    <th><label for="payment_notes"><?php _e('Notes', 'art-in-heaven'); ?></label></th>
                    <td>
                        <textarea name="payment_notes" id="payment_notes" rows="3" class="large-text"></textarea>
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

<!-- Update Order Modal -->
<div id="aih-order-modal" class="aih-modal" style="display:none;">
    <div class="aih-modal-content">
        <span class="aih-modal-close">&times;</span>
        <h2><?php _e('Update Order Payment', 'art-in-heaven'); ?></h2>
        <p id="aih-order-modal-subtitle"></p>
        
        <form method="post" id="aih-order-form" action="">
            <input type="hidden" name="order_id" id="aih-order-id" value="">
            
            <table class="form-table">
                <tr>
                    <th><label for="order_payment_status"><?php _e('Payment Status', 'art-in-heaven'); ?></label></th>
                    <td>
                        <select name="status" id="order_payment_status" required>
                            <option value="pending"><?php _e('Pending', 'art-in-heaven'); ?></option>
                            <option value="paid"><?php _e('Paid', 'art-in-heaven'); ?></option>
                            <option value="refunded"><?php _e('Refunded', 'art-in-heaven'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="order_payment_method"><?php _e('Payment Method', 'art-in-heaven'); ?></label></th>
                    <td>
                        <select name="method" id="order_payment_method" required>
                            <option value="cash"><?php _e('Cash', 'art-in-heaven'); ?></option>
                            <option value="check"><?php _e('Check', 'art-in-heaven'); ?></option>
                            <option value="card"><?php _e('Credit Card', 'art-in-heaven'); ?></option>
                            <option value="pushpay"><?php _e('Pushpay', 'art-in-heaven'); ?></option>
                            <option value="other"><?php _e('Other', 'art-in-heaven'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="order_payment_reference"><?php _e('Reference #', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" name="reference" id="order_payment_reference" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="order_payment_notes"><?php _e('Notes', 'art-in-heaven'); ?></label></th>
                    <td>
                        <textarea name="notes" id="order_payment_notes" rows="3" class="large-text"></textarea>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" class="button button-primary" id="aih-save-order-btn"><?php _e('Save', 'art-in-heaven'); ?></button>
                <button type="button" class="button aih-modal-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
            </p>
        </form>
    </div>
</div>

<style>
.aih-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}
.aih-modal-content {
    background: #fff;
    padding: 20px 30px;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    position: relative;
}
.aih-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 28px;
    cursor: pointer;
    color: #666;
}
.aih-modal-close:hover {
    color: #000;
}
.aih-modal h2 {
    margin-top: 0;
    padding-right: 30px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('aih_admin_nonce'); ?>';
    
    // Mark payment button (for items without orders)
    $('.aih-mark-paid-btn').on('click', function() {
        var artId = $(this).data('art-id');
        var title = $(this).data('title');
        var amount = $(this).data('amount');
        
        $('#aih-art-piece-id').val(artId);
        $('#aih-modal-title').text('Mark Payment: ' + title + ' ($' + parseFloat(amount).toFixed(2) + ')');
        $('#payment_status').val('paid');
        $('#aih-payment-modal').show();
    });
    
    // Update order button
    $('.aih-update-order-btn').on('click', function() {
        var orderId = $(this).data('order-id');
        var orderNumber = $(this).data('order-number');
        var status = $(this).data('status');
        var method = $(this).data('method');
        var reference = $(this).data('reference');
        var notes = $(this).data('notes');
        
        $('#aih-order-id').val(orderId);
        $('#aih-order-modal-subtitle').text('Order: ' + orderNumber);
        $('#order_payment_status').val(status);
        $('#order_payment_method').val(method || 'pushpay');
        $('#order_payment_reference').val(reference);
        $('#order_payment_notes').val(notes);
        $('#aih-order-modal').show();
    });
    
    // Save order update via AJAX
    $('#aih-save-order-btn').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'art-in-heaven')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_update_payment',
                nonce: nonce,
                order_id: $('#aih-order-id').val(),
                status: $('#order_payment_status').val(),
                method: $('#order_payment_method').val(),
                reference: $('#order_payment_reference').val(),
                notes: $('#order_payment_notes').val()
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error updating order');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Save', 'art-in-heaven')); ?>');
                }
            },
            error: function() {
                alert('Error updating order');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Save', 'art-in-heaven')); ?>');
            }
        });
    });
    
    // Close modals
    $('.aih-modal-close, .aih-modal-cancel').on('click', function() {
        $('.aih-modal').hide();
    });
    
    // Close modal on backdrop click
    $('.aih-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Sync Pushpay transactions
    $('#aih-sync-transactions-btn').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'art-in-heaven')); ?>');
        var $status = $('#aih-sync-status').html('<span style="color:#666;"><?php echo esc_js(__('Fetching transactions from Pushpay...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 180000,
            data: { action: 'aih_admin_sync_pushpay', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $status.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Sync failed') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color:red;">✗ ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Sync Now', 'art-in-heaven')); ?>');
            }
        });
    });
    
    // Match transaction to order
    $('.aih-match-txn-btn').on('click', function() {
        var txnId = $(this).data('txn-id');
        var amount = $(this).data('amount');
        var payer = $(this).data('payer');
        
        $('#aih-match-txn-id').val(txnId);
        $('#aih-match-info').html('<?php echo esc_js(__('Transaction:', 'art-in-heaven')); ?> <strong>$' + parseFloat(amount).toFixed(2) + '</strong> <?php echo esc_js(__('from', 'art-in-heaven')); ?> <strong>' + payer + '</strong>');
        $('#aih-match-order').val('');
        $('#aih-match-modal').show();
    });
    
    // Confirm match
    $('#aih-confirm-match').on('click', function() {
        var txnId = $('#aih-match-txn-id').val();
        var orderId = $('#aih-match-order').val();
        
        if (!orderId) {
            alert('<?php echo esc_js(__('Please select an order.', 'art-in-heaven')); ?>');
            return;
        }
        
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Matching...', 'art-in-heaven')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_match_transaction',
                nonce: nonce,
                transaction_id: txnId,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error matching transaction');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Match & Mark Paid', 'art-in-heaven')); ?>');
                }
            },
            error: function() {
                alert('Error matching transaction');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Match & Mark Paid', 'art-in-heaven')); ?>');
            }
        });
    });
});
</script>
