<?php
/**
 * Admin Reports View
 */
if (!defined('ABSPATH')) exit;

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

$art_model = new AIH_Art_Piece();
$stats = $art_model->get_reporting_stats();
$checkout = AIH_Checkout::get_instance();
$payment_stats = $checkout->get_payment_stats();
$bid_model = new AIH_Bid();
$bid_stats = $bid_model->get_stats();

// Ensure stats has all required properties
if (!$stats) {
    $stats = new stdClass();
}
$stats->total_pieces = isset($stats->total_pieces) ? $stats->total_pieces : 0;
$stats->total_bids = isset($stats->total_bids) ? $stats->total_bids : 0;
$stats->unique_bidders = isset($stats->unique_bidders) ? $stats->unique_bidders : 0;
$stats->active_count = isset($stats->active_count) ? $stats->active_count : 0;
$stats->draft_count = isset($stats->draft_count) ? $stats->draft_count : 0;
$stats->ended_count = isset($stats->ended_count) ? $stats->ended_count : 0;
$stats->pieces_with_bids = isset($stats->pieces_with_bids) ? $stats->pieces_with_bids : 0;
$stats->total_starting_value = isset($stats->total_starting_value) ? $stats->total_starting_value : 0;
$stats->highest_bid = isset($stats->highest_bid) ? $stats->highest_bid : 0;
$stats->average_bid = isset($stats->average_bid) ? $stats->average_bid : 0;
$stats->top_pieces = isset($stats->top_pieces) ? $stats->top_pieces : array();

// Ensure payment_stats has all required properties
if (!$payment_stats) {
    $payment_stats = new stdClass();
}
$payment_stats->total_orders = isset($payment_stats->total_orders) ? $payment_stats->total_orders : 0;
$payment_stats->paid_orders = isset($payment_stats->paid_orders) ? $payment_stats->paid_orders : 0;
$payment_stats->pending_orders = isset($payment_stats->pending_orders) ? $payment_stats->pending_orders : 0;
$payment_stats->total_collected = isset($payment_stats->total_collected) ? $payment_stats->total_collected : 0;
$payment_stats->total_pending = isset($payment_stats->total_pending) ? $payment_stats->total_pending : 0;

// Ensure bid_stats has all required properties
if (!$bid_stats) {
    $bid_stats = new stdClass();
}
$bid_stats->total_bid_value = isset($bid_stats->total_bid_value) ? $bid_stats->total_bid_value : 0;
$bid_stats->total_bids = isset($bid_stats->total_bids) ? $bid_stats->total_bids : 0;
$bid_stats->winning_bids = isset($bid_stats->winning_bids) ? $bid_stats->winning_bids : 0;
$bid_stats->outbid_bids = isset($bid_stats->outbid_bids) ? $bid_stats->outbid_bids : 0;
$bid_stats->rejected_bids = isset($bid_stats->rejected_bids) ? $bid_stats->rejected_bids : 0;
$bid_stats->unique_bidders = isset($bid_stats->unique_bidders) ? $bid_stats->unique_bidders : 0;
$bid_stats->unique_art_pieces = isset($bid_stats->unique_art_pieces) ? $bid_stats->unique_art_pieces : 0;
$bid_stats->highest_bid = isset($bid_stats->highest_bid) ? $bid_stats->highest_bid : 0;
$bid_stats->average_bid = isset($bid_stats->average_bid) ? $bid_stats->average_bid : 0;

// Get last bid time
global $wpdb;
$bids_table = AIH_Database::get_table('bids');
$last_bid = $wpdb->get_row("SELECT bid_time, bid_amount, bidder_id FROM $bids_table ORDER BY bid_time DESC LIMIT 1");
$last_bid_time = $last_bid ? $last_bid->bid_time : null;
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Auction Reports', 'art-in-heaven'); ?></h1>
    
    <!-- Quick Stats -->
    <?php
    // Calculate last bid display text
    $last_bid_display = '—';
    if ($last_bid_time) {
        $bid_dt = new DateTime($last_bid_time, wp_timezone());
        $now_dt = new DateTime('now', wp_timezone());
        $time_diff = $now_dt->getTimestamp() - $bid_dt->getTimestamp();
        if ($time_diff < 60) {
            $last_bid_display = __('Just now', 'art-in-heaven');
        } elseif ($time_diff < 3600) {
            $last_bid_display = sprintf(__('%d min ago', 'art-in-heaven'), floor($time_diff / 60));
        } elseif ($time_diff < 86400) {
            $last_bid_display = sprintf(__('%d hr ago', 'art-in-heaven'), floor($time_diff / 3600));
        } else {
            $last_bid_display = sprintf(__('%d days ago', 'art-in-heaven'), floor($time_diff / 86400));
        }
    }

    AIH_Admin::open_stat_grid();
    AIH_Admin::render_stat_card(array(
        'value' => number_format($stats->total_pieces),
        'label' => __('Total Art Pieces', 'art-in-heaven'),
    ));
    AIH_Admin::render_stat_card(array(
        'value' => number_format($stats->total_bids),
        'label' => __('Total Bids', 'art-in-heaven'),
    ));
    AIH_Admin::render_stat_card(array(
        'value' => number_format($stats->unique_bidders),
        'label' => __('Unique Bidders', 'art-in-heaven'),
    ));
    AIH_Admin::render_stat_card(array(
        'value' => '$' . number_format($bid_stats->total_bid_value ?: 0, 2),
        'label' => __('Total Winning Bid Value', 'art-in-heaven'),
    ));
    AIH_Admin::render_stat_card(array(
        'value'   => $last_bid_display,
        'label'   => __('Last Bid', 'art-in-heaven'),
        'detail'  => $last_bid_time ? AIH_Status::format_db_date($last_bid_time, 'M j, g:i a') : '',
        'variant' => 'last-bid',
    ));
    AIH_Admin::close_stat_grid();
    ?>
    
    <!-- Detailed Stats -->
    <div class="aih-report-sections">
        <div class="aih-report-section">
            <h2><?php _e('Art Piece Statistics', 'art-in-heaven'); ?></h2>
            <table class="widefat">
                <tr><th><?php _e('Active Pieces', 'art-in-heaven'); ?></th><td><?php echo $stats->active_count; ?></td></tr>
                <tr><th><?php _e('Draft Pieces', 'art-in-heaven'); ?></th><td><?php echo $stats->draft_count; ?></td></tr>
                <tr><th><?php _e('Ended Pieces', 'art-in-heaven'); ?></th><td><?php echo $stats->ended_count; ?></td></tr>
                <tr><th><?php _e('Pieces with Bids', 'art-in-heaven'); ?></th><td><?php echo $stats->pieces_with_bids; ?></td></tr>
                <tr><th><?php _e('Total Starting Value', 'art-in-heaven'); ?></th><td>$<?php echo number_format($stats->total_starting_value ?: 0, 2); ?></td></tr>
                <tr><th><?php _e('Highest Bid', 'art-in-heaven'); ?></th><td>$<?php echo number_format($stats->highest_bid ?: 0, 2); ?></td></tr>
                <tr><th><?php _e('Average Bid', 'art-in-heaven'); ?></th><td>$<?php echo number_format($stats->average_bid ?: 0, 2); ?></td></tr>
            </table>
        </div>
        <div class="aih-report-section">
            <h2><?php _e('Payment Statistics', 'art-in-heaven'); ?></h2>
            <table class="widefat">
                <tr><th><?php _e('Total Orders', 'art-in-heaven'); ?></th><td><?php echo intval($payment_stats->total_orders ?: 0); ?></td></tr>
                <tr><th><?php _e('Paid Orders', 'art-in-heaven'); ?></th><td><?php echo intval($payment_stats->paid_orders ?: 0); ?></td></tr>
                <tr><th><?php _e('Pending Orders', 'art-in-heaven'); ?></th><td><?php echo intval($payment_stats->pending_orders ?: 0); ?></td></tr>
                <tr><th><?php _e('Total Collected', 'art-in-heaven'); ?></th><td>$<?php echo number_format($payment_stats->total_collected ?: 0, 2); ?></td></tr>
                <tr><th><?php _e('Total Pending', 'art-in-heaven'); ?></th><td>$<?php echo number_format($payment_stats->total_pending ?: 0, 2); ?></td></tr>
            </table>
        </div>
    </div>
    
    <!-- Top Pieces -->
    <div class="aih-report-section">
        <h2><?php _e('Top 10 Art Pieces by Bids', 'art-in-heaven'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Title', 'art-in-heaven'); ?></th>
                    <th><?php _e('Artist', 'art-in-heaven'); ?></th>
                    <th><?php _e('Bids', 'art-in-heaven'); ?></th>
                    <th><?php _e('Highest Bid', 'art-in-heaven'); ?></th>
                    <th><?php _e('Starting Bid', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stats->top_pieces)): foreach ($stats->top_pieces as $piece): ?>
                <tr>
                    <td><a href="<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-add&edit=' . intval($piece->id))); ?>"><?php echo esc_html($piece->title); ?></a></td>
                    <td><?php echo esc_html($piece->artist); ?></td>
                    <td><?php echo intval($piece->bid_count); ?></td>
                    <td>$<?php echo number_format($piece->highest_bid ?: 0, 2); ?></td>
                    <td>$<?php echo number_format($piece->starting_bid, 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5"><?php _e('No art pieces with bids yet.', 'art-in-heaven'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Export Buttons -->
    <div class="aih-report-section">
        <h2><?php _e('Export Data', 'art-in-heaven'); ?></h2>
        <p><?php _e('Download auction data as JSON files for backup or analysis.', 'art-in-heaven'); ?></p>
        <button type="button" class="button aih-export-btn" data-type="art"><?php _e('Export Art Pieces', 'art-in-heaven'); ?></button>
        <button type="button" class="button aih-export-btn" data-type="bids"><?php _e('Export Bids', 'art-in-heaven'); ?></button>
        <button type="button" class="button aih-export-btn" data-type="bidders"><?php _e('Export Bidders', 'art-in-heaven'); ?></button>
        <button type="button" class="button aih-export-btn" data-type="orders"><?php _e('Export Orders', 'art-in-heaven'); ?></button>
    </div>
</div>

<style>
/* reports minimal overrides - main styles in aih-admin.css */
</style>

<script>
jQuery(document).ready(function($) {
    $('.aih-export-btn').on('click', function() {
        var type = $(this).data('type');
        var $btn = $(this).prop('disabled', true).text('Exporting...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_export_data', nonce: '<?php echo esc_js(wp_create_nonce('aih_admin_nonce')); ?>', type: type },
            success: function(response) {
                if (response.success) {
                    var blob = new Blob([JSON.stringify(response.data.data, null, 2)], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'art-in-heaven-' + type + '-' + new Date().toISOString().slice(0,10) + '.json';
                    a.click();
                }
            },
            complete: function() { $btn.prop('disabled', false).text('Export ' + type.charAt(0).toUpperCase() + type.slice(1)); }
        });
    });
});
</script>
