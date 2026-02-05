<?php
/**
 * Admin Winners & Sales View
 * 
 * Shows who won what art piece, payment status, and amounts owed
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

// Get bid model
$bid_model = new AIH_Bid();
$winning_bids = $bid_model->get_all_winning_bids();

// Ensure winning_bids is an array
if (!$winning_bids) {
    $winning_bids = array();
}

// Calculate totals
$total_won = 0;
$total_paid = 0;
$total_unpaid = 0;
$total_pending = 0;

foreach ($winning_bids as $bid) {
    $total_won += floatval($bid->bid_amount);
    
    if ($bid->payment_status === 'paid') {
        $total_paid += floatval($bid->bid_amount);
    } elseif ($bid->is_in_order) {
        $total_pending += floatval($bid->bid_amount);
    } else {
        $total_unpaid += floatval($bid->bid_amount);
    }
}

// Group by bidder for owing summary
$bidder_totals = array();
foreach ($winning_bids as $bid) {
    $bidder_key = $bid->confirmation_code ?: $bid->bidder_id;
    if (!isset($bidder_totals[$bidder_key])) {
        $bidder_totals[$bidder_key] = array(
            'confirmation_code' => $bid->confirmation_code,
            'name' => trim($bid->name_first . ' ' . $bid->name_last),
            'email' => $bid->email_primary,
            'phone' => $bid->phone_mobile,
            'items' => array(),
            'total_won' => 0,
            'total_paid' => 0,
            'total_owed' => 0,
        );
    }
    
    $bidder_totals[$bidder_key]['items'][] = $bid;
    $bidder_totals[$bidder_key]['total_won'] += floatval($bid->bid_amount);
    
    if ($bid->payment_status === 'paid') {
        $bidder_totals[$bidder_key]['total_paid'] += floatval($bid->bid_amount);
    } else {
        $bidder_totals[$bidder_key]['total_owed'] += floatval($bid->bid_amount);
    }
}

// Sort by amount owed descending
uasort($bidder_totals, function($a, $b) {
    return $b['total_owed'] - $a['total_owed'];
});

$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'items';
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Winners & Sales', 'art-in-heaven'); ?></h1>
    
    <!-- Summary Cards -->
    <div class="aih-stats-grid">
        <div class="aih-stat-card">
            <div class="aih-stat-value">$<?php echo number_format($total_won, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Total Won', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card aih-card-success">
            <div class="aih-stat-value">$<?php echo number_format($total_paid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Paid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card aih-card-warning">
            <div class="aih-stat-value">$<?php echo number_format($total_pending, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Pending Orders', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card aih-card-danger">
            <div class="aih-stat-value">$<?php echo number_format($total_unpaid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Not Yet Ordered', 'art-in-heaven'); ?></div>
        </div>
    </div>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="?page=art-in-heaven-winners&tab=items" 
           class="nav-tab <?php echo $current_tab === 'items' ? 'nav-tab-active' : ''; ?>">
            <?php _e('By Art Piece', 'art-in-heaven'); ?> (<?php echo count($winning_bids); ?>)
        </a>
        <a href="?page=art-in-heaven-winners&tab=bidders" 
           class="nav-tab <?php echo $current_tab === 'bidders' ? 'nav-tab-active' : ''; ?>">
            <?php _e('By Bidder', 'art-in-heaven'); ?> (<?php echo count($bidder_totals); ?>)
        </a>
        <a href="?page=art-in-heaven-winners&tab=unpaid" 
           class="nav-tab <?php echo $current_tab === 'unpaid' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Amounts Owed', 'art-in-heaven'); ?>
        </a>
    </nav>
    
    <div class="aih-tab-content">
        
        <?php if ($current_tab === 'items'): ?>
            <!-- BY ART PIECE -->
            <div class="aih-table-wrap">
            <table class="wp-list-table widefat fixed striped aih-admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;"><?php _e('Art ID', 'art-in-heaven'); ?></th>
                        <th><?php _e('Title / Artist', 'art-in-heaven'); ?></th>
                        <th><?php _e('Winner', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Winning Bid', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Starting Bid', 'art-in-heaven'); ?></th>
                        <th style="width: 120px;"><?php _e('Payment Status', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Order #', 'art-in-heaven'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($winning_bids)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No winning bids yet.', 'art-in-heaven'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($winning_bids as $bid): ?>
                            <tr>
                                <td data-label="<?php esc_attr_e('Art ID', 'art-in-heaven'); ?>"><code><?php echo esc_html($bid->art_id); ?></code></td>
                                <td data-label="<?php esc_attr_e('Title', 'art-in-heaven'); ?>">
                                    <strong><?php echo esc_html($bid->title); ?></strong><br>
                                    <span style="color: #666;"><?php echo esc_html($bid->artist); ?></span>
                                </td>
                                <td data-label="<?php esc_attr_e('Winner', 'art-in-heaven'); ?>">
                                    <?php 
                                    $name = trim($bid->name_first . ' ' . $bid->name_last);
                                    echo esc_html($name ?: $bid->bidder_id); 
                                    ?>
                                    <?php if ($bid->confirmation_code): ?>
                                        <br><code style="font-size: 11px;"><?php echo esc_html($bid->confirmation_code); ?></code>
                                    <?php endif; ?>
                                    <?php if ($bid->email_primary): ?>
                                        <br><small><?php echo esc_html($bid->email_primary); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php esc_attr_e('Winning Bid', 'art-in-heaven'); ?>"><strong>$<?php echo number_format($bid->bid_amount, 2); ?></strong></td>
                                <td data-label="<?php esc_attr_e('Starting', 'art-in-heaven'); ?>">$<?php echo number_format($bid->starting_bid, 2); ?></td>
                                <td data-label="<?php esc_attr_e('Status', 'art-in-heaven'); ?>">
                                    <?php if ($bid->payment_status === 'paid'): ?>
                                        <?php if ($bid->pickup_status === 'picked_up'): ?>
                                            <span class="aih-badge aih-badge-info"><?php _e('Picked Up', 'art-in-heaven'); ?></span>
                                        <?php else: ?>
                                            <span class="aih-badge aih-badge-success"><?php _e('Paid', 'art-in-heaven'); ?></span>
                                        <?php endif; ?>
                                    <?php elseif ($bid->is_in_order): ?>
                                        <span class="aih-badge aih-badge-warning"><?php _e('Pending', 'art-in-heaven'); ?></span>
                                    <?php else: ?>
                                        <span class="aih-badge aih-badge-error"><?php _e('Not Ordered', 'art-in-heaven'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="<?php esc_attr_e('Order #', 'art-in-heaven'); ?>">
                                    <?php if ($bid->order_number): ?>
                                        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders&order=' . $bid->order_number); ?>">
                                            <?php echo esc_html($bid->order_number); ?>
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div><!-- /.aih-table-wrap -->
            
        <?php elseif ($current_tab === 'bidders'): ?>
            <!-- BY BIDDER -->
            <?php foreach ($bidder_totals as $bidder): ?>
                <div style="margin-bottom: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="padding: 15px; background: #f5f5f5; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="font-size: 16px;"><?php echo esc_html($bidder['name'] ?: 'Unknown'); ?></strong>
                            <?php if ($bidder['confirmation_code']): ?>
                                <code style="margin-left: 10px;"><?php echo esc_html($bidder['confirmation_code']); ?></code>
                            <?php endif; ?>
                            <br>
                            <span style="color: #666;">
                                <?php echo esc_html($bidder['email']); ?>
                                <?php if ($bidder['phone']): ?>
                                    • <?php echo esc_html($bidder['phone']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <div>
                                <?php _e('Total Won:', 'art-in-heaven'); ?> 
                                <strong>$<?php echo number_format($bidder['total_won'], 2); ?></strong>
                            </div>
                            <?php if ($bidder['total_owed'] > 0): ?>
                                <div style="color: #e63946;">
                                    <?php _e('Owed:', 'art-in-heaven'); ?> 
                                    <strong>$<?php echo number_format($bidder['total_owed'], 2); ?></strong>
                                </div>
                            <?php else: ?>
                                <div style="color: #2d6a4f;">
                                    <strong><?php _e('Fully Paid', 'art-in-heaven'); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <table class="wp-list-table widefat">
                        <tbody>
                            <?php foreach ($bidder['items'] as $item): ?>
                                <tr>
                                    <td style="width: 80px;"><code><?php echo esc_html($item->art_id); ?></code></td>
                                    <td><?php echo esc_html($item->title); ?></td>
                                    <td style="width: 100px;">$<?php echo number_format($item->bid_amount, 2); ?></td>
                                    <td style="width: 120px;">
                                        <?php if ($item->payment_status === 'paid'): ?>
                                            <?php if ($item->pickup_status === 'picked_up'): ?>
                                                <span class="aih-badge aih-badge-info"><?php _e('Picked Up', 'art-in-heaven'); ?></span>
                                            <?php else: ?>
                                                <span class="aih-badge aih-badge-success"><?php _e('Paid', 'art-in-heaven'); ?></span>
                                            <?php endif; ?>
                                        <?php elseif ($item->is_in_order): ?>
                                            <span class="aih-badge aih-badge-warning"><?php _e('Pending', 'art-in-heaven'); ?></span>
                                        <?php else: ?>
                                            <span class="aih-badge aih-badge-error"><?php _e('Not Ordered', 'art-in-heaven'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
        <?php elseif ($current_tab === 'unpaid'): ?>
            <!-- AMOUNTS OWED TABLE -->
            <p><?php _e('Bidders with outstanding amounts:', 'art-in-heaven'); ?></p>
            <div class="aih-table-wrap">
            <table class="wp-list-table widefat fixed striped aih-admin-table">
                <thead>
                    <tr>
                        <th><?php _e('Bidder', 'art-in-heaven'); ?></th>
                        <th style="width: 150px;"><?php _e('Confirmation Code', 'art-in-heaven'); ?></th>
                        <th><?php _e('Contact', 'art-in-heaven'); ?></th>
                        <th style="width: 80px;"><?php _e('Items Won', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Total Won', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Paid', 'art-in-heaven'); ?></th>
                        <th style="width: 100px;"><?php _e('Owed', 'art-in-heaven'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_owed = false;
                    foreach ($bidder_totals as $bidder): 
                        if ($bidder['total_owed'] <= 0) continue;
                        $has_owed = true;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($bidder['name'] ?: 'Unknown'); ?></strong></td>
                            <td><code><?php echo esc_html($bidder['confirmation_code']); ?></code></td>
                            <td>
                                <?php echo esc_html($bidder['email']); ?>
                                <?php if ($bidder['phone']): ?>
                                    <br><?php echo esc_html($bidder['phone']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo count($bidder['items']); ?></td>
                            <td>$<?php echo number_format($bidder['total_won'], 2); ?></td>
                            <td style="color: #2d6a4f;">$<?php echo number_format($bidder['total_paid'], 2); ?></td>
                            <td style="color: #e63946;"><strong>$<?php echo number_format($bidder['total_owed'], 2); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$has_owed): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #2d6a4f;">
                                <strong><?php _e('All winners have paid!', 'art-in-heaven'); ?></strong>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($has_owed): ?>
                <tfoot>
                    <tr>
                        <th colspan="4"></th>
                        <th>$<?php echo number_format($total_won, 2); ?></th>
                        <th>$<?php echo number_format($total_paid, 2); ?></th>
                        <th style="color: #e63946;">$<?php echo number_format($total_unpaid + $total_pending, 2); ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            </div><!-- /.aih-table-wrap -->
        <?php endif; ?>
        
    </div>
    
    <!-- Export Button -->
    <div style="margin-top: 20px;">
        <button type="button" class="button" id="aih-export-winners">
            <?php _e('Export to CSV', 'art-in-heaven'); ?>
        </button>
    </div>
</div>

<style>
/* winners minimal overrides - main styles in aih-admin.css */
</style>

<script>
jQuery(document).ready(function($) {
    $('#aih-export-winners').on('click', function() {
        window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=aih_admin_export_data&type=winners&nonce=<?php echo wp_create_nonce('aih_admin_nonce'); ?>';
    });
});
</script>
