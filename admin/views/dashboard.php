<?php
/**
 * Admin Dashboard View - Role-aware
 */
if (!defined('ABSPATH')) exit;

$can_view_financial = AIH_Roles::can_view_financial();
$can_view_bids = AIH_Roles::can_view_bids();
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Art in Heaven Dashboard', 'art-in-heaven'); ?></h1>
    
    <div class="aih-dashboard-stats">
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $counts->total; ?></span>
            <span class="aih-stat-label"><?php _e('Total Art Pieces', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card aih-stat-active">
            <span class="aih-stat-number"><?php echo $counts->active; ?></span>
            <span class="aih-stat-label"><?php _e('Active Auctions', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card aih-stat-bids">
            <span class="aih-stat-number"><?php echo $counts->bid_rate_percent; ?>%</span>
            <span class="aih-stat-label"><?php _e('With Bids', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel"><?php printf(__('%d of %d pieces', 'art-in-heaven'), $counts->pieces_with_bids, $counts->total); ?></span>
        </div>
        <div class="aih-stat-card aih-stat-nobids">
            <span class="aih-stat-number"><?php echo $counts->active_no_bids; ?></span>
            <span class="aih-stat-label"><?php _e('No Bids Yet', 'art-in-heaven'); ?></span>
        </div>
        <?php if ($can_view_bids): ?>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $total_bids; ?></span>
            <span class="aih-stat-label"><?php _e('Total Bids', 'art-in-heaven'); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($can_view_financial): ?>
        <div class="aih-stat-card aih-stat-money">
            <span class="aih-stat-number">$<?php echo number_format($payment_stats->total_collected ?: 0, 2); ?></span>
            <span class="aih-stat-label"><?php _e('Collected', 'art-in-heaven'); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="aih-dashboard-grid">
        <div class="aih-dashboard-section">
            <h2><?php _e('Quick Links', 'art-in-heaven'); ?></h2>
            <ul class="aih-quick-links">
                <li><a href="<?php echo admin_url('admin.php?page=art-in-heaven-add'); ?>" class="button button-primary"><?php _e('Add New Art Piece', 'art-in-heaven'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=art-in-heaven-art'); ?>" class="button"><?php _e('View All Art Pieces', 'art-in-heaven'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=active_no_bids'); ?>" class="button"><?php _e('Pieces Without Bids', 'art-in-heaven'); ?></a></li>
                <?php if ($can_view_financial): ?>
                <li><a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders'); ?>" class="button"><?php _e('View Orders', 'art-in-heaven'); ?></a></li>
                <?php endif; ?>
                <?php if (AIH_Roles::can_view_reports()): ?>
                <li><a href="<?php echo admin_url('admin.php?page=art-in-heaven-reports'); ?>" class="button"><?php _e('View Reports', 'art-in-heaven'); ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <?php if ($can_view_financial): ?>
        <div class="aih-dashboard-section">
            <h2><?php _e('Payment Summary', 'art-in-heaven'); ?></h2>
            <table class="widefat">
                <tr><td><?php _e('Total Orders', 'art-in-heaven'); ?></td><td><strong><?php echo $payment_stats->total_orders ?: 0; ?></strong></td></tr>
                <tr><td><?php _e('Paid', 'art-in-heaven'); ?></td><td><strong style="color: #065f46;"><?php echo $payment_stats->paid_orders ?: 0; ?></strong></td></tr>
                <tr><td><?php _e('Pending', 'art-in-heaven'); ?></td><td><strong style="color: #d97706;"><?php echo $payment_stats->pending_orders ?: 0; ?></strong></td></tr>
                <tr><td><?php _e('Total Pending', 'art-in-heaven'); ?></td><td><strong>$<?php echo number_format($payment_stats->total_pending ?: 0, 2); ?></strong></td></tr>
            </table>
        </div>
        <?php else: ?>
        <div class="aih-dashboard-section">
            <h2><?php _e('Your Role', 'art-in-heaven'); ?></h2>
            <p><?php _e('You have access to manage art pieces. Financial data and other admin features are restricted.', 'art-in-heaven'); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="aih-dashboard-section">
        <h2><?php _e('Recent Activity', 'art-in-heaven'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Art Piece', 'art-in-heaven'); ?></th>
                    <th><?php _e('Artist', 'art-in-heaven'); ?></th>
                    <th><?php _e('Bids', 'art-in-heaven'); ?></th>
                    <?php if ($can_view_bids): ?>
                    <th><?php _e('Current Bid', 'art-in-heaven'); ?></th>
                    <?php endif; ?>
                    <th><?php _e('Status', 'art-in-heaven'); ?></th>
                    <th><?php _e('Time Left', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recent = array_slice($all_art, 0, 10);
                foreach ($recent as $piece): 
                ?>
                <tr>
                    <td><a href="<?php echo admin_url('admin.php?page=art-in-heaven-add&edit=' . $piece->id); ?>"><?php echo esc_html($piece->title); ?></a></td>
                    <td><?php echo esc_html($piece->artist); ?></td>
                    <td><?php echo $piece->total_bids; ?></td>
                    <?php if ($can_view_bids): ?>
                    <td>$<?php echo number_format($piece->current_bid, 2); ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($piece->status === 'draft'): ?>
                            <span class="aih-status-badge draft"><?php _e('Draft', 'art-in-heaven'); ?></span>
                        <?php elseif ($piece->status === 'active' && $piece->seconds_remaining > 0): ?>
                            <span class="aih-status-badge active"><?php _e('Active', 'art-in-heaven'); ?></span>
                        <?php else: ?>
                            <span class="aih-status-badge ended"><?php _e('Ended', 'art-in-heaven'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($piece->seconds_remaining > 0) {
                            $days = floor($piece->seconds_remaining / 86400);
                            $hours = floor(($piece->seconds_remaining % 86400) / 3600);
                            echo $days > 0 ? "{$days}d {$hours}h" : "{$hours}h";
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
/* Dashboard-specific minimal overrides */
.aih-dashboard-section .wp-list-table { margin: 0; }
.aih-dashboard-section .widefat td { padding: 10px 12px; }
.aih-dashboard-section .widefat tr:nth-child(odd) td { background: #f9fafb; }
</style>
