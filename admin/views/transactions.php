<?php
/**
 * Admin Transactions View - PushPay Transactions
 */

if (!defined('ABSPATH')) {
    exit;
}

$pushpay = AIH_Pushpay_API::get_instance();
$is_configured = $pushpay->is_configured();
$settings = $pushpay->get_settings();

// Get filter values
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_matched = isset($_GET['matched']) ? sanitize_text_field($_GET['matched']) : '';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get transactions
global $wpdb;
$transactions_table = AIH_Database::get_table('pushpay_transactions');
$orders_table = AIH_Database::get_table('orders');

// Build query
$where = "1=1";
$where_values = array();

if ($filter_status) {
    $where .= " AND t.status = %s";
    $where_values[] = $filter_status;
}
if ($filter_matched === 'yes') {
    $where .= " AND t.order_id IS NOT NULL";
} elseif ($filter_matched === 'no') {
    $where .= " AND t.order_id IS NULL";
}
if ($search) {
    $where .= " AND (t.payer_name LIKE %s OR t.payer_email LIKE %s OR t.pushpay_id LIKE %s OR t.reference LIKE %s OR o.order_number LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $where_values = array_merge($where_values, array($search_like, $search_like, $search_like, $search_like, $search_like));
}

// Count total
$count_query = "SELECT COUNT(*) FROM {$transactions_table} t LEFT JOIN {$orders_table} o ON t.order_id = o.id WHERE {$where}";
if (!empty($where_values)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
} else {
    $total_items = $wpdb->get_var($count_query);
}
$total_pages = ceil($total_items / $per_page);

// Get transactions
$query = "SELECT t.*, o.order_number, o.bidder_id 
          FROM {$transactions_table} t 
          LEFT JOIN {$orders_table} o ON t.order_id = o.id 
          WHERE {$where} 
          ORDER BY t.payment_date DESC 
          LIMIT %d OFFSET %d";
$query_values = array_merge($where_values, array($per_page, $offset));
$transactions = $wpdb->get_results($wpdb->prepare($query, $query_values));

// Get status counts for filters
$status_counts = $wpdb->get_results(
    "SELECT status, COUNT(*) as count FROM {$transactions_table} GROUP BY status",
    OBJECT_K
);

// Get matched/unmatched counts
$matched_count = $wpdb->get_var("SELECT COUNT(*) FROM {$transactions_table} WHERE order_id IS NOT NULL");
$unmatched_count = $wpdb->get_var("SELECT COUNT(*) FROM {$transactions_table} WHERE order_id IS NULL");

// Get last sync info
$last_sync = get_option('aih_pushpay_last_sync', '');
$last_sync_count = get_option('aih_pushpay_last_sync_count', 0);

// Get all orders for manual matching (not just pending - someone else may have paid)
$matchable_orders = $wpdb->get_results(
    "SELECT o.id, o.order_number, o.total, o.payment_status, o.created_at
     FROM {$orders_table} o
     ORDER BY o.created_at DESC
     LIMIT 200"
);
?>

<div class="wrap aih-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('PushPay Transactions', 'art-in-heaven'); ?></h1>
    
    <?php if (!$is_configured): ?>
    <div class="notice notice-warning">
        <p><?php _e('PushPay API is not configured. Please configure it in the Integrations page.', 'art-in-heaven'); ?>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-integrations'); ?>" class="button button-small"><?php _e('Configure', 'art-in-heaven'); ?></a></p>
    </div>
    <?php else: ?>
    
    <!-- Sync Status & Actions -->
    <div class="aih-transactions-header">
        <div class="aih-sync-status">
            <span class="aih-mode-badge <?php echo $settings['sandbox_mode'] ? 'sandbox' : 'production'; ?>">
                <?php echo $settings['sandbox_mode'] ? __('Sandbox Mode', 'art-in-heaven') : __('Production', 'art-in-heaven'); ?>
            </span>
            <?php if ($last_sync): ?>
            <span class="aih-last-sync">
                <?php printf(__('Last sync: %s (%d transactions)', 'art-in-heaven'), 
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync)),
                    $last_sync_count
                ); ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="aih-sync-actions">
            <button type="button" id="aih-sync-transactions" class="button button-primary">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Sync from PushPay', 'art-in-heaven'); ?>
            </button>
            <button type="button" id="aih-test-connection" class="button">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('Test Connection', 'art-in-heaven'); ?>
            </button>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="aih-stats-cards">
        <div class="aih-stat-card">
            <div class="aih-stat-number"><?php echo number_format($total_items); ?></div>
            <div class="aih-stat-label"><?php _e('Total Transactions', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card success">
            <div class="aih-stat-number"><?php echo number_format($matched_count); ?></div>
            <div class="aih-stat-label"><?php _e('Matched to Orders', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card warning">
            <div class="aih-stat-number"><?php echo number_format($unmatched_count); ?></div>
            <div class="aih-stat-label"><?php _e('Unmatched', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-number">$<?php 
                $total_amount = $wpdb->get_var("SELECT SUM(amount) FROM {$transactions_table} WHERE status = 'Success'");
                echo number_format($total_amount ?: 0, 2);
            ?></div>
            <div class="aih-stat-label"><?php _e('Total Amount', 'art-in-heaven'); ?></div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="aih-filters-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="art-in-heaven-transactions">
            
            <select name="status">
                <option value=""><?php _e('All Statuses', 'art-in-heaven'); ?></option>
                <option value="Success" <?php selected($filter_status, 'Success'); ?>><?php _e('Success', 'art-in-heaven'); ?> (<?php echo isset($status_counts['Success']) ? $status_counts['Success']->count : 0; ?>)</option>
                <option value="Processing" <?php selected($filter_status, 'Processing'); ?>><?php _e('Processing', 'art-in-heaven'); ?> (<?php echo isset($status_counts['Processing']) ? $status_counts['Processing']->count : 0; ?>)</option>
                <option value="Failed" <?php selected($filter_status, 'Failed'); ?>><?php _e('Failed', 'art-in-heaven'); ?> (<?php echo isset($status_counts['Failed']) ? $status_counts['Failed']->count : 0; ?>)</option>
            </select>
            
            <select name="matched">
                <option value=""><?php _e('All Transactions', 'art-in-heaven'); ?></option>
                <option value="yes" <?php selected($filter_matched, 'yes'); ?>><?php _e('Matched', 'art-in-heaven'); ?> (<?php echo $matched_count; ?>)</option>
                <option value="no" <?php selected($filter_matched, 'no'); ?>><?php _e('Unmatched', 'art-in-heaven'); ?> (<?php echo $unmatched_count; ?>)</option>
            </select>
            
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search transactions...', 'art-in-heaven'); ?>">
            
            <button type="submit" class="button"><?php _e('Filter', 'art-in-heaven'); ?></button>
            
            <?php if ($filter_status || $filter_matched || $search): ?>
            <a href="<?php echo admin_url('admin.php?page=art-in-heaven-transactions'); ?>" class="button"><?php _e('Clear', 'art-in-heaven'); ?></a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Transactions Table -->
    <div class="aih-table-wrap">
        <table class="wp-list-table widefat fixed striped aih-transactions-table">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php _e('ID', 'art-in-heaven'); ?></th>
                    <th style="width: 180px;"><?php _e('PushPay ID', 'art-in-heaven'); ?></th>
                    <th style="width: 140px;"><?php _e('Date', 'art-in-heaven'); ?></th>
                    <th><?php _e('Payer', 'art-in-heaven'); ?></th>
                    <th style="width: 100px;"><?php _e('Amount', 'art-in-heaven'); ?></th>
                    <th style="width: 100px;"><?php _e('Status', 'art-in-heaven'); ?></th>
                    <th style="width: 120px;"><?php _e('Fund', 'art-in-heaven'); ?></th>
                    <th style="width: 120px;"><?php _e('Order', 'art-in-heaven'); ?></th>
                    <th style="width: 100px;"><?php _e('Actions', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <?php if ($total_items == 0 && !$filter_status && !$filter_matched && !$search): ?>
                            <p><?php _e('No transactions found. Click "Sync from PushPay" to fetch transactions.', 'art-in-heaven'); ?></p>
                        <?php else: ?>
                            <p><?php _e('No transactions match your filters.', 'art-in-heaven'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $txn): ?>
                <tr data-id="<?php echo $txn->id; ?>">
                    <td><?php echo $txn->id; ?></td>
                    <td>
                        <code class="aih-pushpay-id" title="<?php echo esc_attr($txn->pushpay_id); ?>">
                            <?php echo esc_html(substr($txn->pushpay_id, 0, 20)); ?>...
                        </code>
                    </td>
                    <td>
                        <?php if ($txn->payment_date): ?>
                            <?php echo date_i18n('M j, Y', strtotime($txn->payment_date)); ?><br>
                            <small class="aih-time"><?php echo date_i18n('g:i a', strtotime($txn->payment_date)); ?></small>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo esc_html($txn->payer_name ?: '—'); ?></strong>
                        <?php if ($txn->payer_email): ?>
                        <br><small><?php echo esc_html($txn->payer_email); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="aih-amount">$<?php echo number_format($txn->amount, 2); ?></strong>
                        <?php if ($txn->currency && $txn->currency !== 'USD'): ?>
                        <small><?php echo esc_html($txn->currency); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="aih-status-badge <?php echo strtolower($txn->status); ?>">
                            <?php echo esc_html($txn->status ?: 'Unknown'); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo esc_html($txn->fund ?: '—'); ?>
                        <?php if ($txn->reference): ?>
                        <br><small title="Reference"><?php echo esc_html($txn->reference); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($txn->order_number): ?>
                        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders&search=' . urlencode($txn->order_number)); ?>" class="aih-order-link">
                            <?php echo esc_html($txn->order_number); ?>
                        </a>
                        <span class="aih-matched-badge">✓</span>
                        <?php else: ?>
                        <span class="aih-unmatched">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="aih-row-actions">
                            <button type="button" class="button button-small aih-view-details" data-id="<?php echo $txn->id; ?>" title="<?php esc_attr_e('View Details', 'art-in-heaven'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                            <?php if (!$txn->order_id): ?>
                            <button type="button" class="button button-small aih-match-order" data-id="<?php echo $txn->id; ?>" data-amount="<?php echo $txn->amount; ?>" title="<?php esc_attr_e('Match to Order', 'art-in-heaven'); ?>">
                                <span class="dashicons dashicons-admin-links"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php printf(_n('%s item', '%s items', $total_items, 'art-in-heaven'), number_format($total_items)); ?></span>
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
                echo $page_links;
                ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; // is_configured ?>
</div>

<!-- Transaction Details Modal -->
<div id="aih-transaction-modal" class="aih-modal" style="display:none;">
    <div class="aih-modal-content aih-modal-lg">
        <div class="aih-modal-header">
            <h2><?php _e('Transaction Details', 'art-in-heaven'); ?></h2>
            <button type="button" class="aih-modal-close">&times;</button>
        </div>
        <div class="aih-modal-body" id="aih-transaction-details">
            <div class="aih-loading"><?php _e('Loading...', 'art-in-heaven'); ?></div>
        </div>
    </div>
</div>

<!-- Match Order Modal -->
<div id="aih-match-modal" class="aih-modal" style="display:none;">
    <div class="aih-modal-content">
        <div class="aih-modal-header">
            <h2><?php _e('Match to Order', 'art-in-heaven'); ?></h2>
            <button type="button" class="aih-modal-close">&times;</button>
        </div>
        <div class="aih-modal-body">
            <p><?php _e('Select an order to match this transaction:', 'art-in-heaven'); ?></p>
            <input type="hidden" id="aih-match-txn-id" value="">
            <p class="aih-match-amount"></p>
            <select id="aih-match-order-select" style="width: 100%; max-width: 400px;">
                <option value=""><?php _e('Select an order...', 'art-in-heaven'); ?></option>
                <?php foreach ($matchable_orders as $order): ?>
                <option value="<?php echo $order->id; ?>" data-amount="<?php echo $order->total; ?>">
                    <?php echo esc_html($order->order_number); ?> - $<?php echo number_format($order->total, 2); ?> - <?php echo esc_html(ucfirst($order->payment_status)); ?> (<?php echo date_i18n('M j', strtotime($order->created_at)); ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="aih-match-warning" style="display:none; color: #d63638;">
                <?php _e('Warning: Order amount does not match transaction amount.', 'art-in-heaven'); ?>
            </p>
        </div>
        <div class="aih-modal-footer">
            <button type="button" class="button aih-modal-close"><?php _e('Cancel', 'art-in-heaven'); ?></button>
            <button type="button" class="button button-primary" id="aih-confirm-match"><?php _e('Match', 'art-in-heaven'); ?></button>
        </div>
    </div>
</div>

<style>
.aih-transactions-header { display: flex; justify-content: space-between; align-items: center; margin: 20px 0; padding: 15px 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.aih-sync-status { display: flex; align-items: center; gap: 15px; }
.aih-mode-badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
.aih-mode-badge.sandbox { background: #fef3c7; color: #92400e; }
.aih-mode-badge.production { background: #d1fae5; color: #065f46; }
.aih-last-sync { color: #8a8a8a; font-size: 13px; }
.aih-sync-actions { display: flex; gap: 10px; }
.aih-sync-actions .dashicons { margin-right: 5px; line-height: 1.4; }

.aih-stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
.aih-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
.aih-stat-card.success { border-left: 4px solid #10b981; }
.aih-stat-card.warning { border-left: 4px solid #f59e0b; }
.aih-stat-number { font-size: 28px; font-weight: 700; color: #1c1c1c; margin-bottom: 5px; }
.aih-stat-label { font-size: 13px; color: #8a8a8a; }

.aih-filters-bar { background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
.aih-filters-bar form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.aih-filters-bar select, .aih-filters-bar input[type="search"] { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px; }
.aih-filters-bar input[type="search"] { min-width: 200px; }

.aih-transactions-table .aih-pushpay-id { font-size: 11px; background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
.aih-transactions-table .aih-time { color: #8a8a8a; }
.aih-transactions-table .aih-amount { color: #4a7c59; font-size: 15px; }
.aih-transactions-table .aih-status-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.aih-status-badge.success { background: #d1fae5; color: #065f46; }
.aih-status-badge.processing { background: #dbeafe; color: #1e40af; }
.aih-status-badge.failed { background: #fee2e2; color: #991b1b; }
.aih-transactions-table .aih-order-link { font-weight: 600; color: #b8956b; text-decoration: none; }
.aih-transactions-table .aih-order-link:hover { text-decoration: underline; }
.aih-matched-badge { color: #10b981; margin-left: 5px; }
.aih-unmatched { color: #9ca3af; }
.aih-row-actions { display: flex; gap: 5px; }
.aih-row-actions .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 1.3; }

/* Modal */
.aih-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; display: flex; align-items: center; justify-content: center; }
.aih-modal-content { background: #fff; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow: auto; }
.aih-modal-lg { max-width: 700px; }
.aih-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e5e7eb; }
.aih-modal-header h2 { margin: 0; font-size: 18px; }
.aih-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #8a8a8a; line-height: 1; }
.aih-modal-body { padding: 20px; }
.aih-modal-footer { padding: 15px 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; }

/* Transaction details */
.aih-txn-detail { margin-bottom: 15px; }
.aih-txn-detail label { display: block; font-size: 11px; font-weight: 600; color: #8a8a8a; text-transform: uppercase; margin-bottom: 3px; }
.aih-txn-detail .value { font-size: 14px; color: #1c1c1c; }
.aih-txn-raw { background: #f9fafb; padding: 15px; border-radius: 6px; max-height: 200px; overflow: auto; font-family: monospace; font-size: 11px; white-space: pre-wrap; word-break: break-all; }

.aih-loading { text-align: center; padding: 40px; color: #8a8a8a; }
</style>

<script>
jQuery(document).ready(function($) {
    
    // Sync transactions
    $('#aih-sync-transactions').on('click', function() {
        var $btn = $(this);
        var $icon = $btn.prop('disabled', true).find('.dashicons');
        $icon.removeClass('dashicons-update').addClass('dashicons-update-alt aih-spin');

        $.post(ajaxurl, {
            action: 'aih_sync_pushpay_transactions',
            nonce: aihAdmin.nonce
        }, function(response) {
            $icon.removeClass('dashicons-update-alt aih-spin').addClass('dashicons-update');
            $btn.prop('disabled', false);
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Error: ' + (response.data ? response.data.message : 'Sync failed'));
            }
        }).fail(function() {
            $icon.removeClass('dashicons-update-alt aih-spin').addClass('dashicons-update');
            $btn.prop('disabled', false);
            alert('Request failed');
        });
    });
    
    // Test connection
    $('#aih-test-connection').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'aih_test_pushpay_connection',
            nonce: aihAdmin.nonce
        }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                alert('✓ ' + response.data.message);
            } else {
                alert('✗ ' + (response.data ? response.data.message : 'Connection failed'));
            }
        }).fail(function() {
            $btn.prop('disabled', false);
            alert('Request failed');
        });
    });
    
    // View details
    $('.aih-view-details').on('click', function() {
        var id = $(this).data('id');
        var $modal = $('#aih-transaction-modal');
        var $body = $('#aih-transaction-details');
        
        $body.html('<div class="aih-loading"><?php _e('Loading...', 'art-in-heaven'); ?></div>');
        $modal.show();
        
        $.post(ajaxurl, {
            action: 'aih_get_transaction_details',
            nonce: aihAdmin.nonce,
            id: id
        }, function(response) {
            if (response.success) {
                $body.html(response.data.html);
            } else {
                $body.html('<p class="error">' + (response.data ? response.data.message : 'Error loading details') + '</p>');
            }
        });
    });
    
    // Match order - open modal
    $('.aih-match-order').on('click', function() {
        var id = $(this).data('id');
        var amount = $(this).data('amount');
        
        $('#aih-match-txn-id').val(id);
        $('.aih-match-amount').html('<strong><?php _e('Transaction Amount:', 'art-in-heaven'); ?></strong> $' + parseFloat(amount).toFixed(2));
        $('#aih-match-order-select').val('');
        $('.aih-match-warning').hide();
        $('#aih-match-modal').show();
    });
    
    // Check amount match
    $('#aih-match-order-select').on('change', function() {
        var $selected = $(this).find(':selected');
        var orderAmount = parseFloat($selected.data('amount')) || 0;
        var txnAmount = parseFloat($('.aih-match-amount').text().replace(/[^0-9.]/g, '')) || 0;
        
        if (orderAmount > 0 && Math.abs(orderAmount - txnAmount) > 0.01) {
            $('.aih-match-warning').show();
        } else {
            $('.aih-match-warning').hide();
        }
    });
    
    // Confirm match
    $('#aih-confirm-match').on('click', function() {
        var txnId = $('#aih-match-txn-id').val();
        var orderId = $('#aih-match-order-select').val();
        
        if (!orderId) {
            alert('<?php _e('Please select an order', 'art-in-heaven'); ?>');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('Matching...', 'art-in-heaven'); ?>');
        
        $.post(ajaxurl, {
            action: 'aih_match_transaction_to_order',
            nonce: aihAdmin.nonce,
            transaction_id: txnId,
            order_id: orderId
        }, function(response) {
            $btn.prop('disabled', false).text('<?php _e('Match', 'art-in-heaven'); ?>');
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('Error: ' + (response.data ? response.data.message : 'Failed to match'));
            }
        });
    });
    
    // Close modals
    $('.aih-modal-close').on('click', function() {
        $(this).closest('.aih-modal').hide();
    });
    
    $(document).on('click', '.aih-modal', function(e) {
        if ($(e.target).hasClass('aih-modal')) {
            $(this).hide();
        }
    });
});

// Spin animation
var style = document.createElement('style');
style.textContent = '@keyframes aih-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } .aih-spin { animation: aih-spin 1s linear infinite; }';
document.head.appendChild(style);
</script>
