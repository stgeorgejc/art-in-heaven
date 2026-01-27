<?php
/**
 * Admin Art Piece Statistics View
 * 
 * Shows detailed statistics for a single art piece
 */

if (!defined('ABSPATH')) {
    exit;
}

$art_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$art_id) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Invalid art piece ID.', 'art-in-heaven') . '</p></div></div>';
    return;
}

$art_model = new AIH_Art_Piece();
$piece = $art_model->get($art_id);

if (!$piece) {
    echo '<div class="wrap"><div class="notice notice-error"><p>' . __('Art piece not found.', 'art-in-heaven') . '</p></div></div>';
    return;
}

// Get bid statistics
global $wpdb;
$bids_table = AIH_Database::get_table('bids');
$bidders_table = AIH_Database::get_table('bidders');
$registrants_table = AIH_Database::get_table('registrants');
$orders_table = AIH_Database::get_table('orders');
$order_items_table = AIH_Database::get_table('order_items');
$favorites_table = AIH_Database::get_table('favorites');

// Consolidated bid statistics: single query instead of 8 separate ones
$bid_stats = $wpdb->get_row($wpdb->prepare(
    "SELECT
        COUNT(*) AS total_bids,
        SUM(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN 1 ELSE 0 END) AS valid_bids,
        SUM(CASE WHEN bid_status = 'too_low' THEN 1 ELSE 0 END) AS rejected_bids,
        COUNT(DISTINCT bidder_id) AS unique_bidders,
        MAX(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bid_amount END) AS highest_bid,
        MIN(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bid_amount END) AS lowest_bid,
        AVG(CASE WHEN bid_status = 'valid' OR bid_status IS NULL THEN bid_amount END) AS average_bid,
        MAX(bid_time) AS last_bid_time
     FROM $bids_table WHERE art_piece_id = %d",
    $art_id
));

$total_bids    = $bid_stats ? (int) $bid_stats->total_bids : 0;
$valid_bids    = $bid_stats ? (int) $bid_stats->valid_bids : 0;
$rejected_bids = $bid_stats ? (int) $bid_stats->rejected_bids : 0;
$unique_bidders = $bid_stats ? (int) $bid_stats->unique_bidders : 0;
$highest_bid   = $bid_stats ? (float) $bid_stats->highest_bid : 0;
$lowest_bid    = $bid_stats ? (float) $bid_stats->lowest_bid : 0;
$average_bid   = $bid_stats ? (float) $bid_stats->average_bid : 0;
$last_bid_time = $bid_stats ? $bid_stats->last_bid_time : null;
$current_bid   = $highest_bid > 0 ? $highest_bid : $piece->starting_bid;

// Get favorites count
$favorites_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $favorites_table WHERE art_piece_id = %d", $art_id));

// Get all bids for this piece - including unsuccessful bids
$all_bids = $wpdb->get_results($wpdb->prepare(
    "SELECT b.*, 
            COALESCE(bd.name_first, r.name_first, '') as name_first, 
            COALESCE(bd.name_last, r.name_last, '') as name_last, 
            COALESCE(bd.email_primary, r.email_primary, '') as email_primary,
            b.bidder_id as confirmation_code
     FROM $bids_table b
     LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
     LEFT JOIN $registrants_table r ON b.bidder_id = r.confirmation_code
     WHERE b.art_piece_id = %d
     ORDER BY b.bid_amount DESC",
    $art_id
));

// Get winning bidder info
$winning_bid = $wpdb->get_row($wpdb->prepare(
    "SELECT b.*, 
            COALESCE(bd.name_first, r.name_first, '') as name_first, 
            COALESCE(bd.name_last, r.name_last, '') as name_last, 
            COALESCE(bd.email_primary, r.email_primary, '') as bidder_email,
            COALESCE(bd.phone_mobile, r.phone_mobile, '') as bidder_phone,
            b.bidder_id as confirmation_code
     FROM $bids_table b
     LEFT JOIN $bidders_table bd ON b.bidder_id = bd.confirmation_code
     LEFT JOIN $registrants_table r ON b.bidder_id = r.confirmation_code
     WHERE b.art_piece_id = %d AND b.is_winning = 1",
    $art_id
));

// Check if sold (in an order)
$order_item = $wpdb->get_row($wpdb->prepare(
    "SELECT oi.*, o.order_number, o.payment_status, o.bidder_id, o.total
     FROM $order_items_table oi
     JOIN $orders_table o ON oi.order_id = o.id
     WHERE oi.art_piece_id = %d",
    $art_id
));

$is_sold = !empty($order_item);
$is_paid = $is_sold && $order_item->payment_status === 'paid';

// Determine display status
$computed = isset($piece->computed_status) ? $piece->computed_status : $piece->status;
$auction_ended = strtotime($piece->auction_end) < current_time('timestamp');

// For ended auctions, show Sold/Not Sold based on whether there's a winning bid
if ($computed === 'ended' || ($auction_ended && $piece->status !== 'draft')) {
    if ($winning_bid || $total_bids > 0) {
        $display_status = 'sold';
        $status_label = __('Sold', 'art-in-heaven');
    } else {
        $display_status = 'not_sold';
        $status_label = __('Not Sold', 'art-in-heaven');
    }
} else {
    $display_status = $computed;
    $status_labels = array(
        'active' => __('Active', 'art-in-heaven'),
        'draft' => __('Draft', 'art-in-heaven'),
        'upcoming' => __('Upcoming', 'art-in-heaven'),
    );
    $status_label = $status_labels[$computed] ?? $computed;
}
?>
<div class="wrap aih-admin-wrap">
    <h1>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art'); ?>" class="page-title-action" style="margin-right: 10px;">
            ← <?php _e('Back to Art', 'art-in-heaven'); ?>
        </a>
        <?php printf(__('Statistics: %s', 'art-in-heaven'), esc_html($piece->title)); ?>
    </h1>
    
    <!-- Art Piece Info (No Image) -->
    <div class="aih-settings-section" style="margin-top: 20px;">
        <div class="aih-art-info-grid">
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Title:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><strong><?php echo esc_html($piece->title); ?></strong></span>
            </div>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Artist:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><?php echo esc_html($piece->artist); ?></span>
            </div>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Art ID:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><code><?php echo esc_html($piece->art_id); ?></code></span>
            </div>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Medium:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><?php echo esc_html($piece->medium ?: '—'); ?></span>
            </div>
            <?php if (!empty($piece->tier)): ?>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Tier:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><?php echo esc_html($piece->tier); ?></span>
            </div>
            <?php endif; ?>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Dimensions:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value"><?php echo esc_html($piece->dimensions ?: '—'); ?></span>
            </div>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Status:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value">
                    <span class="aih-status-badge <?php echo esc_attr($display_status); ?>"><?php echo esc_html($status_label); ?></span>
                </span>
            </div>
            <div class="aih-info-row">
                <span class="aih-info-label"><?php _e('Auction Period:', 'art-in-heaven'); ?></span>
                <span class="aih-info-value">
                    <?php echo date_i18n('M j, Y g:i a', strtotime($piece->auction_start)); ?> 
                    – 
                    <?php echo date_i18n('M j, Y g:i a', strtotime($piece->auction_end)); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="aih-stats-grid">
        <div class="aih-stat-card">
            <div class="aih-stat-value">$<?php echo number_format($piece->starting_bid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Starting Bid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card <?php echo $highest_bid > $piece->starting_bid ? 'aih-highlight' : ''; ?>">
            <div class="aih-stat-value">$<?php echo number_format($current_bid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Current Bid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value"><?php echo $total_bids; ?></div>
            <div class="aih-stat-label"><?php _e('Total Bids', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value"><?php echo $unique_bidders; ?></div>
            <div class="aih-stat-label"><?php _e('Unique Bidders', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card" style="<?php echo $favorites_count > 0 ? 'background: #fef3c7; border-color: #f59e0b;' : ''; ?>">
            <div class="aih-stat-value"><?php echo $favorites_count; ?></div>
            <div class="aih-stat-label"><?php _e('Favorites', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value">
                <?php 
                if ($last_bid_time) {
                    echo date_i18n('M j, g:i a', strtotime($last_bid_time));
                } else {
                    _e('No bids yet', 'art-in-heaven');
                }
                ?>
            </div>
            <div class="aih-stat-label"><?php _e('Last Bid', 'art-in-heaven'); ?></div>
        </div>
    </div>
    
    <?php if ($total_bids > 0): ?>
    <div class="aih-stats-grid" style="margin-top: 20px;">
        <div class="aih-stat-card">
            <div class="aih-stat-value">$<?php echo number_format($highest_bid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Highest Bid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value">$<?php echo number_format($lowest_bid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Lowest Valid Bid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value"><?php echo $valid_bids; ?></div>
            <div class="aih-stat-label"><?php _e('Valid Bids', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card" <?php echo $rejected_bids > 0 ? 'style="background: #fef3c7; border-color: #f59e0b;"' : ''; ?>>
            <div class="aih-stat-value"><?php echo $rejected_bids; ?></div>
            <div class="aih-stat-label"><?php _e('Rejected (Too Low)', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value">$<?php echo number_format($average_bid, 2); ?></div>
            <div class="aih-stat-label"><?php _e('Average Bid', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-value">+$<?php echo number_format(max(0, $current_bid - $piece->starting_bid), 2); ?></div>
            <div class="aih-stat-label"><?php _e('Increase from Start', 'art-in-heaven'); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Winning Bidder -->
    <?php if ($winning_bid): ?>
    <div class="aih-winner-box">
        <h3><?php _e('🏆 Winning Bidder', 'art-in-heaven'); ?></h3>
        <p>
            <strong><?php echo esc_html(trim($winning_bid->name_first . ' ' . $winning_bid->name_last) ?: 'Unknown'); ?></strong>
            <code style="margin-left: 10px;"><?php echo esc_html($winning_bid->confirmation_code); ?></code>
        </p>
        <p>
            <?php _e('Email:', 'art-in-heaven'); ?> <?php echo esc_html($winning_bid->bidder_email ?: '—'); ?>
            <?php if (!empty($winning_bid->bidder_phone)): ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <?php _e('Phone:', 'art-in-heaven'); ?> <?php echo esc_html($winning_bid->bidder_phone); ?>
            <?php endif; ?>
        </p>
        <p>
            <strong><?php _e('Winning Amount:', 'art-in-heaven'); ?></strong> 
            $<?php echo number_format($winning_bid->bid_amount, 2); ?>
            &nbsp;&nbsp;|&nbsp;&nbsp;
            <strong><?php _e('Bid Time:', 'art-in-heaven'); ?></strong> 
            <?php echo date_i18n('M j, Y g:i a', strtotime($winning_bid->bid_time)); ?>
        </p>
        
        <?php if ($is_sold): ?>
            <p style="margin-bottom: 0;">
                <strong><?php _e('Order:', 'art-in-heaven'); ?></strong> 
                <a href="<?php echo admin_url('admin.php?page=art-in-heaven-orders&order_id=' . $order_item->order_id); ?>">
                    #<?php echo esc_html($order_item->order_number); ?>
                </a>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong><?php _e('Payment:', 'art-in-heaven'); ?></strong> 
                <?php if ($is_paid): ?>
                    <span style="color: #155724;">✓ <?php _e('Paid', 'art-in-heaven'); ?></span>
                <?php else: ?>
                    <span style="color: #856404;"><?php _e('Pending', 'art-in-heaven'); ?></span>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p style="margin-bottom: 0; color: #856404;">
                <?php _e('⚠ Not yet ordered', 'art-in-heaven'); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- All Bids Table -->
    <h2><?php _e('Bid History', 'art-in-heaven'); ?></h2>
    <?php if (empty($all_bids)): ?>
        <p class="aih-empty-message"><?php _e('No bids have been placed on this item.', 'art-in-heaven'); ?></p>
    <?php else: ?>
        <!-- Search Bar -->
        <div class="aih-filter-bar">
            <input type="text" id="aih-search-bids" class="regular-text" placeholder="<?php _e('Search by name, email, or code...', 'art-in-heaven'); ?>">
            <span class="aih-filter-count"><span id="aih-visible-count"><?php echo count($all_bids); ?></span> <?php _e('bids', 'art-in-heaven'); ?></span>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="aih-bids-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th class="sortable" data-sort="name"><?php _e('Bidder', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th style="width: 140px;"><?php _e('Confirmation Code', 'art-in-heaven'); ?></th>
                    <th class="sortable" data-sort="amount" style="width: 120px;"><?php _e('Bid Amount', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th class="sortable" data-sort="time" style="width: 180px;"><?php _e('Time', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                    <th style="width: 100px;"><?php _e('Status', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php $rank = 1; foreach ($all_bids as $bid): 
                    $name = trim($bid->name_first . ' ' . $bid->name_last);
                ?>
                    <tr <?php echo !empty($bid->is_winning) ? 'class="aih-winner-row"' : ''; ?>
                        data-name="<?php echo esc_attr(strtolower($name)); ?>"
                        data-email="<?php echo esc_attr(strtolower($bid->email_primary)); ?>"
                        data-code="<?php echo esc_attr(strtolower($bid->confirmation_code ?: $bid->bidder_id)); ?>"
                        data-amount="<?php echo esc_attr($bid->bid_amount); ?>"
                        data-time="<?php echo esc_attr(strtotime($bid->bid_time)); ?>">
                        <td><?php echo $rank++; ?></td>
                        <td>
                            <?php echo esc_html($name ?: 'Unknown'); ?>
                            <?php if (!empty($bid->email_primary)): ?>
                                <br><small style="color: #666;"><?php echo esc_html($bid->email_primary); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($bid->confirmation_code ?: $bid->bidder_id); ?></code></td>
                        <td><strong>$<?php echo number_format($bid->bid_amount, 2); ?></strong></td>
                        <td><?php echo date_i18n('M j, Y g:i:s a', strtotime($bid->bid_time)); ?></td>
                        <td>
                            <?php if (!empty($bid->is_winning)): ?>
                                <span class="aih-badge aih-badge-success"><?php _e('Winner', 'art-in-heaven'); ?></span>
                            <?php elseif ($bid->bid_status === 'too_low'): ?>
                                <span class="aih-badge aih-badge-error"><?php _e('Too Low', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                                <span class="aih-badge aih-badge-outbid"><?php _e('Outbid', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="aih-actions-bar">
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-add&edit=' . $piece->id); ?>" class="button button-primary">
            <?php _e('Edit Art Piece', 'art-in-heaven'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art'); ?>" class="button">
            <?php _e('Back to List', 'art-in-heaven'); ?>
        </a>
    </div>
</div>

<style>
/* art-stats minimal overrides - main styles in aih-admin.css */
</style>

<script>
jQuery(document).ready(function($) {
    var $table = $('#aih-bids-table');
    var $tbody = $table.find('tbody');
    var $rows = $tbody.find('tr');
    
    // Search functionality
    $('#aih-search-bids').on('input keyup', function() {
        var search = $(this).val().toLowerCase().trim();
        var visibleCount = 0;
        
        $rows.each(function() {
            var $row = $(this);
            var name = $row.data('name') || '';
            var email = $row.data('email') || '';
            var code = $row.data('code') || '';
            var show = true;
            
            if (search && name.indexOf(search) === -1 && email.indexOf(search) === -1 && code.indexOf(search) === -1) {
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
});
</script>
