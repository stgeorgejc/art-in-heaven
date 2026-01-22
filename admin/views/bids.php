<?php
/**
 * Admin Bids View
 * 
 * Lists all bids with search and delete functionality
 */
if (!defined('ABSPATH')) exit;

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

global $wpdb;
$bids_table = AIH_Database::get_table('bids');
$art_table = AIH_Database::get_table('art_pieces');
$bidders_table = AIH_Database::get_table('bidders');

// Debug: Check if tables exist and have data
$bids_exist = $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table}");

// Search parameter
$search = isset($_GET['search']) ? strtoupper(sanitize_text_field($_GET['search'])) : '';

// Pagination
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Build base query - simplified without joins first
if (!empty($search)) {
    $total_bids = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$bids_table} WHERE bidder_id LIKE %s",
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    $bids = $wpdb->get_results($wpdb->prepare(
        "SELECT b.*, 
                b.bidder_id as confirmation_code,
                bi.name_first, bi.name_last, bi.email_primary,
                a.art_id, a.title, a.artist
         FROM {$bids_table} b 
         LEFT JOIN {$bidders_table} bi ON b.bidder_id = bi.confirmation_code 
         LEFT JOIN {$art_table} a ON b.art_piece_id = a.id 
         WHERE b.bidder_id LIKE %s
         ORDER BY b.bid_time DESC
         LIMIT %d OFFSET %d",
        '%' . $wpdb->esc_like($search) . '%',
        (int) $per_page,
        (int) $offset
    ));
} else {
    $total_bids = $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table}");
    
    // Simple query without prepare for non-search case
    $bids = $wpdb->get_results(
        "SELECT b.*, 
                b.bidder_id as confirmation_code,
                bi.name_first, bi.name_last, bi.email_primary,
                a.art_id, a.title, a.artist
         FROM {$bids_table} b 
         LEFT JOIN {$bidders_table} bi ON b.bidder_id = bi.confirmation_code 
         LEFT JOIN {$art_table} a ON b.art_piece_id = a.id 
         ORDER BY b.bid_time DESC
         LIMIT " . (int) $per_page . " OFFSET " . (int) $offset
    );
}

$total_pages = ceil($total_bids / $per_page);

// Get summary stats
$total_bid_count = $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table}");
$winning_bid_count = $wpdb->get_var("SELECT COUNT(*) FROM {$bids_table} WHERE is_winning = 1");
$unique_bidders = $wpdb->get_var("SELECT COUNT(DISTINCT bidder_id) FROM {$bids_table}");
$total_bid_value = $wpdb->get_var("SELECT SUM(bid_amount) FROM {$bids_table}");
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Bids Management', 'art-in-heaven'); ?></h1>
    
    <?php if (isset($_GET['debug'])): ?>
    <div class="notice notice-info">
        <p><strong>Debug Info:</strong></p>
        <p>Bids Table: <?php echo esc_html($bids_table); ?></p>
        <p>Art Table: <?php echo esc_html($art_table); ?></p>
        <p>Bidders Table: <?php echo esc_html($bidders_table); ?></p>
        <p>Total bids in table: <?php echo intval($total_bid_count); ?></p>
        <p>Query result count: <?php echo count($bids); ?></p>
        <?php if ($wpdb->last_error): ?>
        <p style="color:red;">Last DB Error: <?php echo esc_html($wpdb->last_error); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Summary Cards -->
    <div class="aih-stats-row">
        <div class="aih-stat-card">
            <div class="aih-stat-number"><?php echo number_format($total_bid_count); ?></div>
            <div class="aih-stat-label"><?php _e('Total Bids', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-number"><?php echo number_format($winning_bid_count); ?></div>
            <div class="aih-stat-label"><?php _e('Winning Bids', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-number"><?php echo number_format($unique_bidders); ?></div>
            <div class="aih-stat-label"><?php _e('Unique Bidders', 'art-in-heaven'); ?></div>
        </div>
        <div class="aih-stat-card">
            <div class="aih-stat-number">$<?php echo number_format($total_bid_value ?: 0); ?></div>
            <div class="aih-stat-label"><?php _e('Total Bid Value', 'art-in-heaven'); ?></div>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="aih-toolbar">
        <form method="get" class="aih-search-form">
            <input type="hidden" name="page" value="art-in-heaven-bids">
            <input type="search" name="search" value="<?php echo esc_attr($search); ?>" 
                   placeholder="<?php esc_attr_e('Enter confirmation code to filter...', 'art-in-heaven'); ?>" 
                   class="aih-search-input" style="text-transform: uppercase;">
            <button type="submit" class="button"><?php _e('Filter', 'art-in-heaven'); ?></button>
            <?php if (!empty($search)): ?>
                <a href="<?php echo admin_url('admin.php?page=art-in-heaven-bids'); ?>" class="button"><?php _e('Show All', 'art-in-heaven'); ?></a>
            <?php endif; ?>
        </form>
        <div class="aih-toolbar-info">
            <?php if (!empty($search)): ?>
                <?php printf(__('Showing %d bids for bidder "%s"', 'art-in-heaven'), $total_bids, esc_html($search)); ?>
            <?php else: ?>
                <?php printf(__('Showing all bids (%d total)', 'art-in-heaven'), $total_bids); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bids Table -->
    <table class="wp-list-table widefat fixed striped aih-bids-table">
        <thead>
            <tr>
                <th class="column-time"><?php _e('Time', 'art-in-heaven'); ?></th>
                <th class="column-bidder"><?php _e('Bidder', 'art-in-heaven'); ?></th>
                <th class="column-art"><?php _e('Art Piece', 'art-in-heaven'); ?></th>
                <th class="column-amount"><?php _e('Amount', 'art-in-heaven'); ?></th>
                <th class="column-status"><?php _e('Status', 'art-in-heaven'); ?></th>
                <th class="column-actions"><?php _e('Actions', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bids)): ?>
                <tr>
                    <td colspan="6" class="aih-no-results">
                        <?php _e('No bids found.', 'art-in-heaven'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bids as $bid): ?>
                    <tr data-bid-id="<?php echo $bid->id; ?>">
                        <td class="column-time">
                            <span class="aih-bid-date"><?php echo date_i18n('M j, Y', strtotime($bid->bid_time)); ?></span>
                            <span class="aih-bid-time"><?php echo date_i18n('g:i:s A', strtotime($bid->bid_time)); ?></span>
                        </td>
                        <td class="column-bidder">
                            <strong><?php echo esc_html(trim($bid->name_first . ' ' . $bid->name_last) ?: 'Unknown'); ?></strong>
                            <div class="aih-bid-meta">
                                <code><?php echo esc_html($bid->confirmation_code ?: 'N/A'); ?></code>
                            </div>
                        </td>
                        <td class="column-art">
                            <strong><?php echo esc_html($bid->title ?: 'Unknown'); ?></strong>
                            <div class="aih-bid-meta">
                                <code><?php echo esc_html($bid->art_id ?: 'N/A'); ?></code>
                                <?php if ($bid->artist): ?>
                                    <span class="aih-artist"><?php echo esc_html($bid->artist); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="column-amount">
                            <strong class="aih-bid-amount">$<?php echo number_format($bid->bid_amount, 0); ?></strong>
                        </td>
                        <td class="column-status">
                            <?php if ($bid->is_winning): ?>
                                <span class="aih-status-badge winning"><?php _e('Winning', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                                <span class="aih-status-badge outbid"><?php _e('Outbid', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-actions">
                            <button type="button" class="button button-small aih-delete-bid" data-bid-id="<?php echo $bid->id; ?>" data-bidder="<?php echo esc_attr($bid->confirmation_code); ?>" data-amount="<?php echo $bid->bid_amount; ?>">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="aih-pagination">
            <?php
            $base_url = admin_url('admin.php?page=art-in-heaven-bids');
            if (!empty($search)) {
                $base_url .= '&search=' . urlencode($search);
            }
            
            if ($current_page > 1): ?>
                <a href="<?php echo $base_url . '&paged=' . ($current_page - 1); ?>" class="button">&laquo; <?php _e('Previous', 'art-in-heaven'); ?></a>
            <?php endif; ?>
            
            <span class="aih-page-info">
                <?php printf(__('Page %d of %d', 'art-in-heaven'), $current_page, $total_pages); ?>
            </span>
            
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo $base_url . '&paged=' . ($current_page + 1); ?>" class="button"><?php _e('Next', 'art-in-heaven'); ?> &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.aih-stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.aih-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    min-width: 150px;
    text-align: center;
}

.aih-stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #1c1c1c;
}

.aih-stat-label {
    font-size: 13px;
    color: #8a8a8a;
    margin-top: 5px;
}

.aih-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 10px;
}

.aih-search-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

.aih-search-input {
    min-width: 200px;
    padding: 8px 12px;
    font-family: inherit;
    font-size: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    background: #fff;
}

.aih-toolbar-info {
    color: #8a8a8a;
    font-size: 13px;
}

.aih-bids-table {
    margin-top: 10px;
}

.aih-bids-table .column-time {
    width: 140px;
}

.aih-bids-table .column-bidder {
    width: 200px;
}

.aih-bids-table .column-art {
    width: auto;
}

.aih-bids-table .column-amount {
    width: 100px;
    text-align: right;
}

.aih-bids-table .column-status {
    width: 100px;
    text-align: center;
}

.aih-bids-table .column-actions {
    width: 80px;
    text-align: center;
}

.aih-bid-date {
    display: block;
    font-weight: 500;
}

.aih-bid-time {
    display: block;
    font-size: 12px;
    color: #8a8a8a;
}

.aih-bid-meta {
    margin-top: 4px;
    font-size: 12px;
}

.aih-bid-meta code {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.aih-bid-meta .aih-artist {
    color: #8a8a8a;
    margin-left: 8px;
}

.aih-bid-amount {
    color: #4a7c59;
    font-size: 16px;
}

.aih-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.aih-status-badge.winning {
    background: #d1fae5;
    color: #065f46;
}

.aih-status-badge.outbid {
    background: #fef3c7;
    color: #92400e;
}

.aih-delete-bid {
    color: #a63d40 !important;
}

.aih-delete-bid:hover {
    background: #fee2e2 !important;
    border-color: #a63d40 !important;
}

.aih-delete-bid .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    line-height: 1;
}

.aih-no-results {
    text-align: center;
    padding: 40px 20px;
    color: #8a8a8a;
}

.aih-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding: 15px;
}

.aih-page-info {
    color: #8a8a8a;
}

@media (max-width: 782px) {
    .aih-stats-row {
        flex-direction: column;
    }
    
    .aih-stat-card {
        width: 100%;
    }
    
    .aih-toolbar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .aih-search-form {
        flex-wrap: wrap;
    }
    
    .aih-search-input {
        min-width: 100%;
        width: 100%;
    }
    
    .aih-bids-table .column-time,
    .aih-bids-table .column-status {
        display: none;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('aih_admin_nonce'); ?>';
    
    // Delete bid
    $('.aih-delete-bid').on('click', function() {
        var $btn = $(this);
        var bidId = $btn.data('bid-id');
        var bidder = $btn.data('bidder');
        var amount = $btn.data('amount');
        
        if (!confirm('<?php echo esc_js(__('Are you sure you want to delete this bid?', 'art-in-heaven')); ?>\n\n' + 
                     '<?php echo esc_js(__('Bidder:', 'art-in-heaven')); ?> ' + bidder + '\n' +
                     '<?php echo esc_js(__('Amount:', 'art-in-heaven')); ?> $' + amount + '\n\n' +
                     '<?php echo esc_js(__('This action cannot be undone.', 'art-in-heaven')); ?>')) {
            return;
        }
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_delete_bid',
                nonce: nonce,
                bid_id: bidId
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error deleting bid.', 'art-in-heaven')); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error deleting bid.', 'art-in-heaven')); ?>');
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
