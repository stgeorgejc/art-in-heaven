<?php
/**
 * Admin Engagement Stats View
 *
 * Tabbed dashboard: Overview, Art Pieces, Bidders, Notifications.
 * Uses Chart.js for visualizations and audit log data for push metrics.
 *
 * @var array  $art_pieces          Art pieces with stats from get_all_with_stats()
 * @var array  $engagement_metrics  Push/engagement data from get_engagement_metrics()
 */

if (!defined('ABSPATH')) {
    exit;
}
if (!isset($art_pieces)) { $art_pieces = array(); }
if (!isset($engagement_metrics)) { $engagement_metrics = array(); }

$funnel           = isset($engagement_metrics['funnel']) ? $engagement_metrics['funnel'] : array();
$bid_attribution  = isset($engagement_metrics['bid_attribution']) ? $engagement_metrics['bid_attribution'] : array();
$push_bidders     = isset($engagement_metrics['push_bidders']) ? $engagement_metrics['push_bidders'] : 0;
$total_bidders_db = isset($engagement_metrics['total_bidders']) ? $engagement_metrics['total_bidders'] : 0;

// Compute overview numbers from art_pieces
$total_bidders   = 0;
$total_bids_sum  = 0;
$active_with_bids = 0;
$total_pieces    = count($art_pieces);
$pieces_with_bids = 0;

foreach ($art_pieces as $piece) {
    $total_bidders += $piece->unique_bidders;
    $total_bids_sum += $piece->total_bids;
    if ($piece->total_bids > 0) {
        $pieces_with_bids++;
        if ($piece->seconds_remaining > 0) {
            $active_with_bids++;
        }
    }
}

$bid_rate_percent = $total_pieces > 0 ? round(($pieces_with_bids / $total_pieces) * 100) : 0;
$push_bids   = isset($bid_attribution['push']) ? $bid_attribution['push'] : 0;
$organic_bids = isset($bid_attribution['organic']) ? $bid_attribution['organic'] : 0;
$total_attributed_bids = $push_bids + $organic_bids;

// Active tab
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
$tabs = array(
    'overview'      => __('Overview', 'art-in-heaven'),
    'art-pieces'    => __('Art Pieces', 'art-in-heaven'),
    'bidders'       => __('Bidders', 'art-in-heaven'),
    'notifications' => __('Notifications', 'art-in-heaven'),
);
?>

<div class="wrap aih-admin-wrap">
    <h1><?php _e('Engagement Statistics', 'art-in-heaven'); ?></h1>

    <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>"
               class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($active_tab === 'overview'): ?>
    <!-- ========== OVERVIEW TAB ========== -->
    <div class="aih-stats-overview">
        <div class="aih-stat-card aih-stat-bids">
            <span class="aih-stat-number"><?php echo $bid_rate_percent; ?>%</span>
            <span class="aih-stat-label"><?php _e('With Bids', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel"><?php printf(__('%d of %d pieces', 'art-in-heaven'), $pieces_with_bids, $total_pieces); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $total_bidders; ?></span>
            <span class="aih-stat-label"><?php _e('Total Unique Bidders', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $total_bids_sum; ?></span>
            <span class="aih-stat-label"><?php _e('Total Bids Placed', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card aih-stat-active">
            <span class="aih-stat-number"><?php echo $active_with_bids; ?></span>
            <span class="aih-stat-label"><?php _e('Active Auctions with Bids', 'art-in-heaven'); ?></span>
        </div>
    </div>

    <!-- Push vs Organic Summary -->
    <h2 style="margin-top: 30px;"><?php _e('Push Notification Impact', 'art-in-heaven'); ?></h2>
    <p class="description"><?php _e('Compares bidders who enabled push notifications vs those who did not.', 'art-in-heaven'); ?></p>

    <div class="aih-stats-overview">
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $push_bidders; ?> / <?php echo $total_bidders_db; ?></span>
            <span class="aih-stat-label"><?php _e('Push-Enabled Bidders', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel">
                <?php
                $pct = $total_bidders_db > 0 ? round(($push_bidders / $total_bidders_db) * 100) : 0;
                printf(__('%d%% adoption rate', 'art-in-heaven'), $pct);
                ?>
            </span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo $push_bids; ?></span>
            <span class="aih-stat-label"><?php _e('Push-Attributed Bids', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel">
                <?php
                $push_pct = $total_attributed_bids > 0 ? round(($push_bids / $total_attributed_bids) * 100) : 0;
                printf(__('%d%% of total bids', 'art-in-heaven'), $push_pct);
                ?>
            </span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo esc_html($engagement_metrics['push_bidder_breadth'] ?? 0); ?></span>
            <span class="aih-stat-label"><?php _e('Avg Items (Push)', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel"><?php printf(__('vs %s (No Push)', 'art-in-heaven'), esc_html($engagement_metrics['nonpush_bidder_breadth'] ?? 0)); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo esc_html($engagement_metrics['push_bidder_depth'] ?? 0); ?></span>
            <span class="aih-stat-label"><?php _e('Avg Bids (Push)', 'art-in-heaven'); ?></span>
            <span class="aih-stat-sublabel"><?php printf(__('vs %s (No Push)', 'art-in-heaven'), esc_html($engagement_metrics['nonpush_bidder_depth'] ?? 0)); ?></span>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display: flex; flex-wrap: wrap; gap: 24px; margin-top: 24px;">
        <div style="flex: 1; min-width: 300px; max-width: 500px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0;"><?php _e('Notification Funnel', 'art-in-heaven'); ?></h3>
            <canvas id="aih-funnel-chart" height="260"></canvas>
        </div>
        <div style="flex: 1; min-width: 300px; max-width: 500px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0;"><?php _e('Bid Attribution', 'art-in-heaven'); ?></h3>
            <canvas id="aih-attribution-chart" height="260"></canvas>
        </div>
    </div>

    <!-- Bidding Activity Timeline -->
    <div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 24px;">
        <h3 style="margin-top: 0;"><?php _e('Bidding Activity Timeline', 'art-in-heaven'); ?></h3>
        <canvas id="aih-timeline-chart" height="120"></canvas>
    </div>

    <!-- Engagement vs Pressure Interpretation -->
    <div style="background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 24px;">
        <h3 style="margin-top: 0;"><?php _e('Engagement vs Pressure Signals', 'art-in-heaven'); ?></h3>
        <table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th><?php _e('Signal', 'art-in-heaven'); ?></th>
                    <th><?php _e('Push Bidders', 'art-in-heaven'); ?></th>
                    <th><?php _e('Non-Push Bidders', 'art-in-heaven'); ?></th>
                    <th><?php _e('Interpretation', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php _e('Breadth (avg items bid on)', 'art-in-heaven'); ?></strong></td>
                    <td><?php echo esc_html($engagement_metrics['push_bidder_breadth'] ?? 0); ?></td>
                    <td><?php echo esc_html($engagement_metrics['nonpush_bidder_breadth'] ?? 0); ?></td>
                    <td>
                        <?php
                        $pb = (float) ($engagement_metrics['push_bidder_breadth'] ?? 0);
                        $nb = (float) ($engagement_metrics['nonpush_bidder_breadth'] ?? 0);
                        if ($pb > 0 && $nb > 0) {
                            if ($pb > $nb * 1.2) {
                                _e('Push bidders explore more items — healthy engagement signal', 'art-in-heaven');
                            } elseif ($pb < $nb * 0.8) {
                                _e('Push bidders bid on fewer items — may indicate reactive bidding', 'art-in-heaven');
                            } else {
                                _e('Similar breadth — push does not significantly change browsing patterns', 'art-in-heaven');
                            }
                        } else {
                            _e('Insufficient data', 'art-in-heaven');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e('Depth (avg bids per bidder)', 'art-in-heaven'); ?></strong></td>
                    <td><?php echo esc_html($engagement_metrics['push_bidder_depth'] ?? 0); ?></td>
                    <td><?php echo esc_html($engagement_metrics['nonpush_bidder_depth'] ?? 0); ?></td>
                    <td>
                        <?php
                        $pd = (float) ($engagement_metrics['push_bidder_depth'] ?? 0);
                        $nd = (float) ($engagement_metrics['nonpush_bidder_depth'] ?? 0);
                        if ($pd > 0 && $nd > 0) {
                            if ($pd > $nd * 1.5) {
                                _e('Push bidders bid significantly more — monitor for pressure signals', 'art-in-heaven');
                            } elseif ($pd > $nd * 1.2) {
                                _e('Push bidders bid moderately more — likely healthy engagement', 'art-in-heaven');
                            } else {
                                _e('Similar depth — push does not significantly increase bid frequency', 'art-in-heaven');
                            }
                        } else {
                            _e('Insufficient data', 'art-in-heaven');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e('Push Opt-in Rate', 'art-in-heaven'); ?></strong></td>
                    <td colspan="2">
                        <?php
                        $granted = isset($funnel['permission_granted']) ? $funnel['permission_granted'] : 0;
                        $denied  = isset($funnel['permission_denied']) ? $funnel['permission_denied'] : 0;
                        $total_decisions = $granted + $denied;
                        $opt_in_rate = $total_decisions > 0 ? round(($granted / $total_decisions) * 100) : 0;
                        printf(__('%d%% (%d granted / %d total decisions)', 'art-in-heaven'), $opt_in_rate, $granted, $total_decisions);
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($total_decisions > 0) {
                            if ($opt_in_rate >= 70) {
                                _e('High opt-in — users see value in notifications', 'art-in-heaven');
                            } elseif ($opt_in_rate >= 40) {
                                _e('Moderate opt-in — consider timing/messaging', 'art-in-heaven');
                            } else {
                                _e('Low opt-in — users may find notifications intrusive', 'art-in-heaven');
                            }
                        } else {
                            _e('No permission decisions recorded yet', 'art-in-heaven');
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php _e('Notification Click Rate', 'art-in-heaven'); ?></strong></td>
                    <td colspan="2">
                        <?php
                        $delivered = isset($funnel['push_delivered']) ? $funnel['push_delivered'] : 0;
                        $clicked   = isset($funnel['push_clicked']) ? $funnel['push_clicked'] : 0;
                        $click_rate = $delivered > 0 ? round(($clicked / $delivered) * 100) : 0;
                        printf(__('%d%% (%d clicks / %d delivered)', 'art-in-heaven'), $click_rate, $clicked, $delivered);
                        ?>
                    </td>
                    <td>
                        <?php
                        if ($delivered > 0) {
                            if ($click_rate >= 40) {
                                _e('High click rate — notifications are relevant and useful', 'art-in-heaven');
                            } elseif ($click_rate >= 15) {
                                _e('Healthy click rate — above industry average', 'art-in-heaven');
                            } else {
                                _e('Low click rate — notifications may not be compelling', 'art-in-heaven');
                            }
                        } else {
                            _e('No notifications delivered yet', 'art-in-heaven');
                        }
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php elseif ($active_tab === 'art-pieces'): ?>
    <!-- ========== ART PIECES TAB ========== -->
    <h2><?php _e('Statistics by Tier (Active Art Pieces)', 'art-in-heaven'); ?></h2>
    <p class="description"><?php _e('Click column headers to sort. Shows individual art pieces sorted by tier.', 'art-in-heaven'); ?></p>

    <?php
    $active_pieces = array_filter($art_pieces, function($piece) {
        return $piece->status === 'active' && $piece->seconds_remaining > 0;
    });

    $unique_tiers = array();
    foreach ($active_pieces as $piece) {
        $t = !empty($piece->tier) ? $piece->tier : __('No Tier', 'art-in-heaven');
        $unique_tiers[$t] = true;
    }
    ksort($unique_tiers);
    ?>
    <div style="margin: 12px 0;">
        <label for="aih-tier-filter"><strong><?php _e('Filter by Tier:', 'art-in-heaven'); ?></strong></label>
        <select id="aih-tier-filter" style="margin-left: 6px; min-width: 160px;">
            <option value=""><?php _e('All Tiers', 'art-in-heaven'); ?></option>
            <?php foreach (array_keys($unique_tiers) as $tier_option): ?>
                <option value="<?php echo esc_attr($tier_option); ?>"><?php echo esc_html($tier_option); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php
    $sorted_pieces = $active_pieces;
    usort($sorted_pieces, function($a, $b) {
        $tier_a = !empty($a->tier) ? $a->tier : 'ZZZ';
        $tier_b = !empty($b->tier) ? $b->tier : 'ZZZ';
        $cmp = strcmp($tier_a, $tier_b);
        if ($cmp !== 0) return $cmp;
        return strcmp($a->art_id, $b->art_id);
    });

    $active_bids_sum = 0;
    $tier_stats = array();
    foreach ($active_pieces as $piece) {
        $tier = !empty($piece->tier) ? $piece->tier : __('No Tier', 'art-in-heaven');
        if (!isset($tier_stats[$tier])) {
            $tier_stats[$tier] = array('count' => 0, 'bids' => 0, 'bidders' => 0, 'value' => 0, 'with_bids' => 0);
        }
        $tier_stats[$tier]['count']++;
        $tier_stats[$tier]['bids'] += $piece->total_bids;
        $tier_stats[$tier]['bidders'] += $piece->unique_bidders;
        $tier_stats[$tier]['value'] += floatval($piece->current_bid ?: $piece->starting_bid);
        if ($piece->total_bids > 0) $tier_stats[$tier]['with_bids']++;
        $active_bids_sum += $piece->total_bids;
    }
    ?>

    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-tier-table" id="aih-tier-pivot">
        <thead>
            <tr>
                <th class="sortable" data-sort="tier" style="cursor:pointer; width: 100px;"><?php _e('Tier', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th class="sortable" data-sort="art_id" style="cursor:pointer; width: 80px;"><?php _e('Art ID', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th class="sortable" data-sort="title" style="cursor:pointer;"><?php _e('Title', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th style="width: 120px;"><?php _e('Artist', 'art-in-heaven'); ?></th>
                <th class="sortable" data-sort="total_bids" style="cursor:pointer; width: 80px;"><?php _e('Bids', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th style="width: 80px;"><?php _e('With Bids', 'art-in-heaven'); ?></th>
                <th class="sortable" data-sort="bid_rate" style="cursor:pointer; width: 90px;"><?php _e('Bid Rate', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th class="sortable" data-sort="unique_bidders" style="cursor:pointer; width: 90px;"><?php _e('Bidders', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th class="sortable" data-sort="current_bid" style="cursor:pointer; width: 100px;"><?php _e('Current Bid', 'art-in-heaven'); ?> <span class="aih-sort-icon">&#8693;</span></th>
                <th style="width: 80px;"><?php _e('Status', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($sorted_pieces as $piece):
                $tier = !empty($piece->tier) ? $piece->tier : __('No Tier', 'art-in-heaven');
                $piece_bid_rate = $active_bids_sum > 0 ? round(($piece->total_bids / $active_bids_sum) * 100, 1) : 0;
            ?>
            <tr data-tier="<?php echo esc_attr($tier); ?>"
                data-art_id="<?php echo esc_attr($piece->art_id); ?>"
                data-title="<?php echo esc_attr(strtolower($piece->title)); ?>"
                data-total_bids="<?php echo intval($piece->total_bids); ?>"
                data-bid_rate="<?php echo esc_attr($piece_bid_rate); ?>"
                data-unique_bidders="<?php echo intval($piece->unique_bidders); ?>"
                data-current_bid="<?php echo floatval($piece->current_bid ?: $piece->starting_bid); ?>">
                <td><strong style="color: #1c1c1c;"><?php echo esc_html($tier); ?></strong></td>
                <td><code><?php echo esc_html($piece->art_id); ?></code></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&stats=1&id=' . intval($piece->id)); ?>">
                        <?php echo esc_html($piece->title); ?>
                    </a>
                </td>
                <td><small><?php echo esc_html($piece->artist); ?></small></td>
                <td>
                    <span style="font-weight: 600; color: <?php echo $piece->total_bids > 0 ? '#4a7c59' : '#8a8a8a'; ?>;">
                        <?php echo intval($piece->total_bids); ?>
                    </span>
                </td>
                <td>
                    <?php if ($piece->total_bids > 0): ?>
                        <span style="color: #4a7c59; font-weight: 600;"><?php _e('Yes', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                        <span style="color: #a63d40;"><?php _e('No', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($piece_bid_rate > 0): ?>
                        <span style="font-weight: 600;"><?php echo $piece_bid_rate; ?>%</span>
                    <?php else: ?>
                        <span style="color: #9ca3af;">0%</span>
                    <?php endif; ?>
                </td>
                <td><?php echo intval($piece->unique_bidders); ?></td>
                <td><strong>$<?php echo number_format($piece->current_bid ?: $piece->starting_bid, 0); ?></strong></td>
                <td>
                    <?php if ($piece->status === 'active' && $piece->seconds_remaining > 0): ?>
                    <span class="aih-status-badge active"><?php _e('Active', 'art-in-heaven'); ?></span>
                    <?php elseif ($piece->status === 'draft'): ?>
                    <span class="aih-status-badge draft"><?php _e('Draft', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                    <span class="aih-status-badge ended"><?php _e('Ended', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Tier Summary -->
    <h2 style="margin-top: 30px;"><?php _e('Tier Summary', 'art-in-heaven'); ?></h2>
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
        <thead>
            <tr>
                <th><?php _e('Tier', 'art-in-heaven'); ?></th>
                <th><?php _e('Pieces', 'art-in-heaven'); ?></th>
                <th><?php _e('With Bids', 'art-in-heaven'); ?></th>
                <th><?php _e('Total Bids', 'art-in-heaven'); ?></th>
                <th><?php _e('Bid Rate', 'art-in-heaven'); ?></th>
                <th><?php _e('Total Value', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php ksort($tier_stats); foreach ($tier_stats as $tier => $tier_data):
                $tier_bid_rate = $active_bids_sum > 0 ? round(($tier_data['bids'] / $active_bids_sum) * 100, 1) : 0;
            ?>
            <tr>
                <td><strong><?php echo esc_html($tier); ?></strong></td>
                <td><?php echo $tier_data['count']; ?></td>
                <td>
                    <?php if ($tier_data['with_bids'] > 0): ?>
                        <span style="color: #4a7c59; font-weight: 600;"><?php echo $tier_data['with_bids']; ?> <?php _e('Yes', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                        <span style="color: #a63d40;"><?php _e('No', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                    <span style="color: #9ca3af;"> / <?php echo $tier_data['count'] - $tier_data['with_bids']; ?> <?php _e('No', 'art-in-heaven'); ?></span>
                </td>
                <td style="font-weight: 600;"><?php echo $tier_data['bids']; ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1; max-width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo esc_attr($tier_bid_rate); ?>%; height: 100%; background: #b8956b;"></div>
                        </div>
                        <span style="min-width: 45px;"><?php echo esc_html($tier_bid_rate); ?>%</span>
                    </div>
                </td>
                <td>$<?php echo number_format($tier_data['value'], 0); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- All Art Pieces table -->
    <h2 style="margin-top: 30px;"><?php _e('All Art Pieces', 'art-in-heaven'); ?></h2>
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-stats-table">
        <thead>
            <tr>
                <th class="aih-col-title"><?php _e('Art Piece', 'art-in-heaven'); ?></th>
                <th class="aih-col-id"><?php _e('ID', 'art-in-heaven'); ?></th>
                <th><?php _e('Status', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('Unique Bidders', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('Total Bids', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('With Bids', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('Bid Rate', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('Time Since Last Bid', 'art-in-heaven'); ?></th>
                <th class="aih-col-stat"><?php _e('Current Bid', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($art_pieces)): ?>
                <tr><td colspan="9"><?php _e('No art pieces found.', 'art-in-heaven'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($art_pieces as $piece):
                    $piece_bid_rate = $total_bids_sum > 0 ? round(($piece->total_bids / $total_bids_sum) * 100, 1) : 0;
                ?>
                    <tr>
                        <td class="aih-col-title">
                            <strong><?php echo esc_html($piece->title); ?></strong>
                            <br><small><?php echo esc_html($piece->artist); ?></small>
                        </td>
                        <td class="aih-col-id"><code><?php echo esc_html($piece->art_id); ?></code></td>
                        <td>
                            <?php if ($piece->status === 'active' && $piece->seconds_remaining > 0): ?>
                                <span class="aih-status-badge active"><?php _e('Active', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                                <span class="aih-status-badge ended"><?php _e('Ended', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="aih-col-stat">
                            <span class="aih-stat-value <?php echo $piece->unique_bidders > 0 ? 'has-bids' : ''; ?>">
                                <?php echo intval($piece->unique_bidders); ?>
                            </span>
                        </td>
                        <td class="aih-col-stat">
                            <span class="aih-stat-value <?php echo $piece->total_bids > 0 ? 'has-bids' : ''; ?>">
                                <?php echo intval($piece->total_bids); ?>
                            </span>
                        </td>
                        <td class="aih-col-stat">
                            <?php if ($piece->total_bids > 0): ?>
                                <span style="color: #4a7c59; font-weight: 600;"><?php _e('Yes', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                                <span style="color: #a63d40;"><?php _e('No', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="aih-col-stat">
                            <?php if ($piece_bid_rate > 0): ?>
                                <span style="font-weight: 600;"><?php echo esc_html($piece_bid_rate); ?>%</span>
                            <?php else: ?>
                                <span style="color: #9ca3af;">0%</span>
                            <?php endif; ?>
                        </td>
                        <td class="aih-col-stat">
                            <?php
                            if ($piece->last_bid_time) {
                                echo '<span class="aih-time-ago">';
                                $bid_dt = new DateTime($piece->last_bid_time, wp_timezone());
                                $now_dt = new DateTime('now', wp_timezone());
                                echo human_time_diff($bid_dt->getTimestamp(), $now_dt->getTimestamp());
                                echo ' ' . __('ago', 'art-in-heaven');
                                echo '</span>';
                            } else {
                                echo '<span class="aih-no-bids">' . __('No bids yet', 'art-in-heaven') . '</span>';
                            }
                            ?>
                        </td>
                        <td class="aih-col-stat">
                            <span class="aih-bid-amount">$<?php echo number_format($piece->current_bid, 2); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div class="aih-export-section">
        <h2><?php _e('Export Data', 'art-in-heaven'); ?></h2>
        <p><?php _e('Export engagement data for further analysis:', 'art-in-heaven'); ?></p>
        <button type="button" id="aih-export-csv" class="button">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Export to CSV', 'art-in-heaven'); ?>
        </button>
    </div>

    <?php elseif ($active_tab === 'bidders'): ?>
    <!-- ========== BIDDERS TAB ========== -->
    <h2><?php _e('Bidder Engagement Comparison', 'art-in-heaven'); ?></h2>
    <p class="description"><?php _e('Compares engagement patterns between push-enabled and non-push bidders.', 'art-in-heaven'); ?></p>

    <!-- Comparison Chart -->
    <div style="display: flex; flex-wrap: wrap; gap: 24px; margin-top: 16px;">
        <div style="flex: 1; min-width: 300px; max-width: 600px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0;"><?php _e('Push vs Non-Push Comparison', 'art-in-heaven'); ?></h3>
            <canvas id="aih-bidder-comparison-chart" height="200"></canvas>
        </div>
    </div>

    <!-- Bidder Engagement Table -->
    <h2 style="margin-top: 30px;"><?php _e('Top Bidders by Activity', 'art-in-heaven'); ?></h2>
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped" style="max-width: 900px;">
        <thead>
            <tr>
                <th style="width: 60px;">#</th>
                <th><?php _e('Bidder', 'art-in-heaven'); ?></th>
                <th style="width: 100px;"><?php _e('Total Bids', 'art-in-heaven'); ?></th>
                <th style="width: 120px;"><?php _e('Items Bid On', 'art-in-heaven'); ?></th>
                <th style="width: 130px;"><?php _e('Last Bid', 'art-in-heaven'); ?></th>
                <th style="width: 100px;"><?php _e('Push Enabled', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $bidder_data = isset($engagement_metrics['bidder_engagement']) ? $engagement_metrics['bidder_engagement'] : array();
            if (empty($bidder_data)):
            ?>
                <tr><td colspan="6"><?php _e('No bidder data available yet.', 'art-in-heaven'); ?></td></tr>
            <?php else: ?>
                <?php $rank = 0; foreach ($bidder_data as $bidder): $rank++; ?>
                <tr>
                    <td><?php echo $rank; ?></td>
                    <td><code><?php echo esc_html(substr($bidder->bidder_id, 0, 4) . '****'); ?></code></td>
                    <td style="font-weight: 600;"><?php echo intval($bidder->total_bids); ?></td>
                    <td><?php echo intval($bidder->pieces_bid_on); ?></td>
                    <td>
                        <?php
                        if ($bidder->last_bid_time) {
                            echo '<span class="aih-time-ago">';
                            echo human_time_diff(strtotime($bidder->last_bid_time), time());
                            echo ' ' . __('ago', 'art-in-heaven');
                            echo '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($bidder->has_push): ?>
                            <span style="color: #4a7c59; font-weight: 600;">&#10003; <?php _e('Yes', 'art-in-heaven'); ?></span>
                        <?php else: ?>
                            <span style="color: #8a8a8a;"><?php _e('No', 'art-in-heaven'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php elseif ($active_tab === 'notifications'): ?>
    <!-- ========== NOTIFICATIONS TAB ========== -->
    <h2><?php _e('Push Notification Analytics', 'art-in-heaven'); ?></h2>

    <div class="aih-stats-overview">
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo isset($funnel['push_sent']) ? $funnel['push_sent'] : 0; ?></span>
            <span class="aih-stat-label"><?php _e('Notifications Sent', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo isset($funnel['push_delivered']) ? $funnel['push_delivered'] : 0; ?></span>
            <span class="aih-stat-label"><?php _e('Delivered', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo isset($funnel['push_clicked']) ? $funnel['push_clicked'] : 0; ?></span>
            <span class="aih-stat-label"><?php _e('Clicked', 'art-in-heaven'); ?></span>
        </div>
        <div class="aih-stat-card">
            <span class="aih-stat-number"><?php echo isset($funnel['push_expired']) ? $funnel['push_expired'] : 0; ?></span>
            <span class="aih-stat-label"><?php _e('Expired/Failed', 'art-in-heaven'); ?></span>
        </div>
    </div>

    <!-- Notification Funnel Chart -->
    <div style="display: flex; flex-wrap: wrap; gap: 24px; margin-top: 24px;">
        <div style="flex: 1; min-width: 300px; max-width: 600px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0;"><?php _e('Delivery Funnel', 'art-in-heaven'); ?></h3>
            <canvas id="aih-notif-funnel-chart" height="200"></canvas>
        </div>
        <div style="flex: 1; min-width: 300px; max-width: 600px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0;"><?php _e('By Notification Type', 'art-in-heaven'); ?></h3>
            <canvas id="aih-notif-type-chart" height="200"></canvas>
        </div>
    </div>

    <!-- Permission Decision Sources -->
    <h2 style="margin-top: 30px;"><?php _e('Permission Decision Sources', 'art-in-heaven'); ?></h2>
    <p class="description"><?php _e('Where users were prompted to enable/disable notifications.', 'art-in-heaven'); ?></p>
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped" style="max-width: 600px;">
        <thead>
            <tr>
                <th><?php _e('Source', 'art-in-heaven'); ?></th>
                <th><?php _e('Granted', 'art-in-heaven'); ?></th>
                <th><?php _e('Denied', 'art-in-heaven'); ?></th>
                <th><?php _e('Opt-in Rate', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $perm_by_source = array();
            $permission_sources = isset($engagement_metrics['permission_sources']) ? $engagement_metrics['permission_sources'] : array();
            foreach ($permission_sources as $row) {
                $src = $row->source;
                if (!isset($perm_by_source[$src])) {
                    $perm_by_source[$src] = array('granted' => 0, 'denied' => 0);
                }
                if ($row->event_type === 'push_permission_granted') {
                    $perm_by_source[$src]['granted'] = (int) $row->cnt;
                } else {
                    $perm_by_source[$src]['denied'] = (int) $row->cnt;
                }
            }

            $source_labels = array(
                'bell'      => __('Bell Icon', 'art-in-heaven'),
                'after_bid' => __('After Placing Bid', 'art-in-heaven'),
            );

            if (empty($perm_by_source)):
            ?>
                <tr><td colspan="4"><?php _e('No permission decisions recorded yet.', 'art-in-heaven'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($perm_by_source as $src => $counts):
                    $total = $counts['granted'] + $counts['denied'];
                    $rate = $total > 0 ? round(($counts['granted'] / $total) * 100) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html(isset($source_labels[$src]) ? $source_labels[$src] : $src); ?></strong></td>
                    <td style="color: #4a7c59; font-weight: 600;"><?php echo $counts['granted']; ?></td>
                    <td style="color: #a63d40;"><?php echo $counts['denied']; ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; max-width: 80px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo esc_attr($rate); ?>%; height: 100%; background: #4a7c59;"></div>
                            </div>
                            <span><?php echo $rate; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Notification Type Breakdown -->
    <h2 style="margin-top: 30px;"><?php _e('Notification Type Breakdown', 'art-in-heaven'); ?></h2>
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped" style="max-width: 700px;">
        <thead>
            <tr>
                <th><?php _e('Type', 'art-in-heaven'); ?></th>
                <th><?php _e('Sent', 'art-in-heaven'); ?></th>
                <th><?php _e('Delivered', 'art-in-heaven'); ?></th>
                <th><?php _e('Clicked', 'art-in-heaven'); ?></th>
                <th><?php _e('Click Rate', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $notif_breakdown = array();
            $notif_types = isset($engagement_metrics['notif_types']) ? $engagement_metrics['notif_types'] : array();
            foreach ($notif_types as $row) {
                $nt = $row->notif_type;
                if (!isset($notif_breakdown[$nt])) {
                    $notif_breakdown[$nt] = array('sent' => 0, 'delivered' => 0, 'clicked' => 0);
                }
                if ($row->event_type === 'push_sent') {
                    $notif_breakdown[$nt]['sent'] = (int) $row->cnt;
                } elseif ($row->event_type === 'push_delivered') {
                    $notif_breakdown[$nt]['delivered'] = (int) $row->cnt;
                } elseif ($row->event_type === 'push_clicked') {
                    $notif_breakdown[$nt]['clicked'] = (int) $row->cnt;
                }
            }

            $type_labels = array(
                'outbid' => __('Outbid', 'art-in-heaven'),
                'winner' => __('Winner', 'art-in-heaven'),
            );

            if (empty($notif_breakdown)):
            ?>
                <tr><td colspan="5"><?php _e('No notification data yet.', 'art-in-heaven'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($notif_breakdown as $nt => $counts):
                    $cr = $counts['delivered'] > 0 ? round(($counts['clicked'] / $counts['delivered']) * 100) : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html(isset($type_labels[$nt]) ? $type_labels[$nt] : ucfirst($nt)); ?></strong></td>
                    <td><?php echo $counts['sent']; ?></td>
                    <td><?php echo $counts['delivered']; ?></td>
                    <td style="font-weight: 600;"><?php echo $counts['clicked']; ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="flex: 1; max-width: 80px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo esc_attr($cr); ?>%; height: 100%; background: #b8956b;"></div>
                            </div>
                            <span><?php echo $cr; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // ===== Art Pieces tab: tier filter + sorting =====
    $('#aih-tier-filter').on('change', function() {
        var selected = $(this).val();
        $('#aih-tier-pivot tbody tr').each(function() {
            if (!selected || $(this).attr('data-tier') === selected) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    var $tierTable = $('#aih-tier-pivot');
    var $tierTbody = $tierTable.find('tbody');
    var currentSort = { col: null, dir: 'asc' };

    $tierTable.find('th.sortable').on('click', function() {
        var $th = $(this);
        var sortKey = $th.data('sort');
        if (currentSort.col === sortKey) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.col = sortKey;
            currentSort.dir = 'asc';
        }
        $tierTable.find('th.sortable').removeClass('sorted-asc sorted-desc');
        $th.addClass('sorted-' + currentSort.dir);

        var $rows = $tierTbody.find('tr').get();
        $rows.sort(function(a, b) {
            var aVal = $(a).data(sortKey);
            var bVal = $(b).data(sortKey);
            if (sortKey === 'tier' || sortKey === 'art_id' || sortKey === 'title') {
                aVal = String(aVal || '').toLowerCase();
                bVal = String(bVal || '').toLowerCase();
                if (aVal < bVal) return currentSort.dir === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.dir === 'asc' ? 1 : -1;
                return 0;
            } else {
                aVal = parseFloat(aVal) || 0;
                bVal = parseFloat(bVal) || 0;
                return currentSort.dir === 'asc' ? aVal - bVal : bVal - aVal;
            }
        });
        $.each($rows, function(i, row) { $tierTbody.append(row); });
    });

    // ===== CSV Export =====
    $('#aih-export-csv').on('click', function() {
        var data = [];
        data.push(['Art ID', 'Title', 'Artist', 'Tier', 'Status', 'Unique Bidders', 'Total Bids', 'Current Bid']);
        $('#aih-tier-pivot tbody tr').each(function() {
            var $row = $(this);
            data.push([
                $row.data('art_id'),
                $row.find('td:eq(2) a').text().trim(),
                $row.find('td:eq(3)').text().trim(),
                $row.data('tier'),
                $row.find('.aih-status-badge').text().trim(),
                $row.data('unique_bidders'),
                $row.data('total_bids'),
                $row.data('current_bid')
            ]);
        });
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                return '"' + String(cell).replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'art-in-heaven-stats.csv';
        link.click();
    });

    // ===== Chart.js Visualizations =====
    if (typeof Chart === 'undefined') return;

    var chartColors = {
        gold:    '#b8956b',
        green:   '#4a7c59',
        red:     '#a63d40',
        blue:    '#3b82f6',
        gray:    '#6b7280',
        lightGreen: 'rgba(74, 124, 89, 0.2)',
        lightBlue:  'rgba(59, 130, 246, 0.2)',
        lightGold:  'rgba(184, 149, 107, 0.2)'
    };

    // -- Overview tab: Notification Funnel --
    var funnelCanvas = document.getElementById('aih-funnel-chart');
    if (funnelCanvas) {
        new Chart(funnelCanvas, {
            type: 'bar',
            data: {
                labels: ['Granted', 'Sent', 'Delivered', 'Clicked'],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php echo (int) ($funnel['permission_granted'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_sent'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_delivered'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_clicked'] ?? 0); ?>
                    ],
                    backgroundColor: [chartColors.green, chartColors.blue, chartColors.gold, chartColors.green],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // -- Overview tab: Bid Attribution --
    var attrCanvas = document.getElementById('aih-attribution-chart');
    if (attrCanvas) {
        new Chart(attrCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Push-Attributed', 'Organic'],
                datasets: [{
                    data: [<?php echo (int) $push_bids; ?>, <?php echo (int) $organic_bids; ?>],
                    backgroundColor: [chartColors.gold, chartColors.green],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // -- Overview tab: Bidding Timeline --
    var timelineCanvas = document.getElementById('aih-timeline-chart');
    if (timelineCanvas) {
        <?php
        // Build timeline data arrays
        $timeline_hours = array();
        $timeline_push = array();
        $timeline_organic = array();
        $bids_by_hour = isset($engagement_metrics['bids_by_hour']) ? $engagement_metrics['bids_by_hour'] : array();
        $hour_data = array();
        foreach ($bids_by_hour as $row) {
            if (!isset($hour_data[$row->hour_bucket])) {
                $hour_data[$row->hour_bucket] = array('push' => 0, 'organic' => 0);
            }
            if ($row->source === 'push') {
                $hour_data[$row->hour_bucket]['push'] = (int) $row->cnt;
            } else {
                $hour_data[$row->hour_bucket]['organic'] += (int) $row->cnt;
            }
        }
        ksort($hour_data);
        foreach ($hour_data as $hour => $counts) {
            $timeline_hours[] = $hour;
            $timeline_push[] = $counts['push'];
            $timeline_organic[] = $counts['organic'];
        }
        ?>
        new Chart(timelineCanvas, {
            type: 'line',
            data: {
                labels: <?php echo wp_json_encode($timeline_hours); ?>,
                datasets: [
                    {
                        label: 'Organic Bids',
                        data: <?php echo wp_json_encode($timeline_organic); ?>,
                        borderColor: chartColors.green,
                        backgroundColor: chartColors.lightGreen,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Push-Attributed Bids',
                        data: <?php echo wp_json_encode($timeline_push); ?>,
                        borderColor: chartColors.gold,
                        backgroundColor: chartColors.lightGold,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { ticks: { maxTicksLimit: 12 } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    // -- Bidders tab: Push vs Non-Push Comparison --
    var compCanvas = document.getElementById('aih-bidder-comparison-chart');
    if (compCanvas) {
        new Chart(compCanvas, {
            type: 'bar',
            data: {
                labels: ['Avg Items Bid On', 'Avg Total Bids'],
                datasets: [
                    {
                        label: 'Push Bidders',
                        data: [
                            <?php echo (float) ($engagement_metrics['push_bidder_breadth'] ?? 0); ?>,
                            <?php echo (float) ($engagement_metrics['push_bidder_depth'] ?? 0); ?>
                        ],
                        backgroundColor: chartColors.gold,
                        borderRadius: 4
                    },
                    {
                        label: 'Non-Push Bidders',
                        data: [
                            <?php echo (float) ($engagement_metrics['nonpush_bidder_breadth'] ?? 0); ?>,
                            <?php echo (float) ($engagement_metrics['nonpush_bidder_depth'] ?? 0); ?>
                        ],
                        backgroundColor: chartColors.green,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // -- Notifications tab: Delivery Funnel --
    var notifFunnelCanvas = document.getElementById('aih-notif-funnel-chart');
    if (notifFunnelCanvas) {
        new Chart(notifFunnelCanvas, {
            type: 'bar',
            data: {
                labels: ['Sent', 'Delivered', 'Clicked', 'Expired'],
                datasets: [{
                    label: 'Count',
                    data: [
                        <?php echo (int) ($funnel['push_sent'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_delivered'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_clicked'] ?? 0); ?>,
                        <?php echo (int) ($funnel['push_expired'] ?? 0); ?>
                    ],
                    backgroundColor: [chartColors.blue, chartColors.green, chartColors.gold, chartColors.red],
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // -- Notifications tab: By Type --
    var notifTypeCanvas = document.getElementById('aih-notif-type-chart');
    if (notifTypeCanvas) {
        <?php
        $type_chart_data = array();
        foreach ($notif_breakdown as $nt => $counts) {
            $label = isset($type_labels[$nt]) ? $type_labels[$nt] : ucfirst($nt);
            $type_chart_data[] = array('label' => $label, 'sent' => $counts['sent'], 'delivered' => $counts['delivered'], 'clicked' => $counts['clicked']);
        }
        ?>
        var typeData = <?php echo wp_json_encode($type_chart_data); ?>;
        if (typeData.length > 0) {
            new Chart(notifTypeCanvas, {
                type: 'bar',
                data: {
                    labels: typeData.map(function(d) { return d.label; }),
                    datasets: [
                        { label: 'Sent', data: typeData.map(function(d) { return d.sent; }), backgroundColor: chartColors.blue, borderRadius: 4 },
                        { label: 'Delivered', data: typeData.map(function(d) { return d.delivered; }), backgroundColor: chartColors.green, borderRadius: 4 },
                        { label: 'Clicked', data: typeData.map(function(d) { return d.clicked; }), backgroundColor: chartColors.gold, borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }
});
</script>
