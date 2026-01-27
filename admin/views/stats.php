<?php
/**
 * Admin Engagement Stats View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<style>
/* stats minimal overrides - main styles in aih-admin.css */
</style>

<div class="wrap aih-admin-wrap">
    <h1><?php _e('Engagement Statistics', 'art-in-heaven'); ?></h1>
    
    <div class="aih-stats-overview">
        <?php
        $total_bidders = 0;
        $total_bids_sum = 0;
        $active_with_bids = 0;
        $total_pieces = count($art_pieces);
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
        ?>
        
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
    
    <!-- Statistics by Tier - Individual Art Pieces -->
    <h2 style="margin-top: 30px;"><?php _e('Statistics by Tier', 'art-in-heaven'); ?></h2>
    <p class="description"><?php _e('Click column headers to sort. Shows individual art pieces sorted by tier.', 'art-in-heaven'); ?></p>
    
    <?php
    // Sort art pieces by tier, then by art_id
    $sorted_pieces = $art_pieces;
    usort($sorted_pieces, function($a, $b) {
        $tier_a = !empty($a->tier) ? $a->tier : 'ZZZ'; // Put empty tiers at end
        $tier_b = !empty($b->tier) ? $b->tier : 'ZZZ';
        $cmp = strcmp($tier_a, $tier_b);
        if ($cmp !== 0) return $cmp;
        return strcmp($a->art_id, $b->art_id);
    });
    
    // Calculate tier totals for summary row
    $tier_stats = array();
    foreach ($art_pieces as $piece) {
        $tier = !empty($piece->tier) ? $piece->tier : __('No Tier', 'art-in-heaven');
        if (!isset($tier_stats[$tier])) {
            $tier_stats[$tier] = array('count' => 0, 'bids' => 0, 'bidders' => 0, 'value' => 0, 'with_bids' => 0);
        }
        $tier_stats[$tier]['count']++;
        $tier_stats[$tier]['bids'] += $piece->total_bids;
        $tier_stats[$tier]['bidders'] += $piece->unique_bidders;
        $tier_stats[$tier]['value'] += floatval($piece->current_bid ?: $piece->starting_bid);
        if ($piece->total_bids > 0) $tier_stats[$tier]['with_bids']++;
    }
    ?>
    
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-tier-table" id="aih-tier-pivot">
        <thead>
            <tr>
                <th class="sortable" data-sort="tier" style="cursor:pointer; width: 100px;"><?php _e('Tier', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable" data-sort="art_id" style="cursor:pointer; width: 80px;"><?php _e('Art ID', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable" data-sort="title" style="cursor:pointer;"><?php _e('Title', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th style="width: 120px;"><?php _e('Artist', 'art-in-heaven'); ?></th>
                <th class="sortable" data-sort="total_bids" style="cursor:pointer; width: 80px;"><?php _e('Bids', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th style="width: 80px;"><?php _e('With Bids', 'art-in-heaven'); ?></th>
                <th class="sortable" data-sort="bid_rate" style="cursor:pointer; width: 90px;"><?php _e('Bid Rate', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable" data-sort="unique_bidders" style="cursor:pointer; width: 90px;"><?php _e('Bidders', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable" data-sort="current_bid" style="cursor:pointer; width: 100px;"><?php _e('Current Bid', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th style="width: 80px;"><?php _e('Status', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $current_tier = null;
            foreach ($sorted_pieces as $piece): 
                $tier = !empty($piece->tier) ? $piece->tier : __('No Tier', 'art-in-heaven');
                $show_tier = ($tier !== $current_tier);
                $current_tier = $tier;
                $piece_bid_rate = $total_bids_sum > 0 ? round(($piece->total_bids / $total_bids_sum) * 100, 1) : 0;
            ?>
            <tr data-tier="<?php echo esc_attr($tier); ?>"
                data-art_id="<?php echo esc_attr($piece->art_id); ?>"
                data-title="<?php echo esc_attr(strtolower($piece->title)); ?>"
                data-total_bids="<?php echo intval($piece->total_bids); ?>"
                data-bid_rate="<?php echo esc_attr($piece_bid_rate); ?>"
                data-unique_bidders="<?php echo intval($piece->unique_bidders); ?>"
                data-current_bid="<?php echo floatval($piece->current_bid ?: $piece->starting_bid); ?>">
                <td>
                    <?php if ($show_tier): ?>
                    <strong style="color: #1c1c1c;"><?php echo esc_html($tier); ?></strong>
                    <?php endif; ?>
                </td>
                <td><code><?php echo esc_html($piece->art_id); ?></code></td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&stats=1&id=' . $piece->id); ?>">
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
            <?php ksort($tier_stats); foreach ($tier_stats as $tier => $stats): 
                $tier_bid_rate = $total_bids_sum > 0 ? round(($stats['bids'] / $total_bids_sum) * 100, 1) : 0;
            ?>
            <tr>
                <td><strong><?php echo esc_html($tier); ?></strong></td>
                <td><?php echo $stats['count']; ?></td>
                <td>
                    <?php if ($stats['with_bids'] > 0): ?>
                        <span style="color: #4a7c59; font-weight: 600;"><?php echo $stats['with_bids']; ?> <?php _e('Yes', 'art-in-heaven'); ?></span>
                    <?php else: ?>
                        <span style="color: #a63d40;"><?php _e('No', 'art-in-heaven'); ?></span>
                    <?php endif; ?>
                    <span style="color: #9ca3af;"> / <?php echo $stats['count'] - $stats['with_bids']; ?> <?php _e('No', 'art-in-heaven'); ?></span>
                </td>
                <td style="font-weight: 600;"><?php echo $stats['bids']; ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1; max-width: 100px; height: 8px; background: #e5e7eb; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo esc_attr($tier_bid_rate); ?>%; height: 100%; background: #b8956b;"></div>
                        </div>
                        <span style="min-width: 45px;"><?php echo esc_html($tier_bid_rate); ?>%</span>
                    </div>
                </td>
                <td>$<?php echo number_format($stats['value'], 0); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <!-- Individual Art Pieces (without image) -->
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
                <tr>
                    <td colspan="9"><?php _e('No art pieces found.', 'art-in-heaven'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($art_pieces as $piece): 
                    $piece_bid_rate = $total_bids_sum > 0 ? round(($piece->total_bids / $total_bids_sum) * 100, 1) : 0;
                ?>
                    <tr>
                        <td class="aih-col-title">
                            <strong><?php echo esc_html($piece->title); ?></strong>
                            <br><small><?php echo esc_html($piece->artist); ?></small>
                        </td>
                        <td class="aih-col-id">
                            <code><?php echo esc_html($piece->art_id); ?></code>
                        </td>
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
                                $diff = current_time('timestamp') - strtotime($piece->last_bid_time);
                                echo '<span class="aih-time-ago">';
                                echo human_time_diff(strtotime($piece->last_bid_time), current_time('timestamp'));
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
</div>


<script type="text/javascript">
jQuery(document).ready(function($) {
    // Tier pivot table sorting
    var $tierTable = $('#aih-tier-pivot');
    var $tierTbody = $tierTable.find('tbody');
    var currentSort = { col: null, dir: 'asc' };
    
    $tierTable.find('th.sortable').on('click', function() {
        var $th = $(this);
        var sortKey = $th.data('sort');
        
        // Toggle direction
        if (currentSort.col === sortKey) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.col = sortKey;
            currentSort.dir = 'asc';
        }
        
        // Update header icons
        $tierTable.find('th.sortable').removeClass('sorted-asc sorted-desc');
        $th.addClass('sorted-' + currentSort.dir);
        
        // Sort rows
        var $rows = $tierTbody.find('tr').get();
        $rows.sort(function(a, b) {
            var aVal = $(a).data(sortKey);
            var bVal = $(b).data(sortKey);
            
            // Handle string vs number
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
        
        $.each($rows, function(i, row) {
            $tierTbody.append(row);
        });
    });
    
    // CSV Export
    $('#aih-export-csv').on('click', function() {
        var data = [];
        data.push(['Art ID', 'Title', 'Artist', 'Tier', 'Status', 'Unique Bidders', 'Total Bids', 'Current Bid']);
        
        $('#aih-tier-pivot tbody tr').each(function() {
            var $row = $(this);
            var row = [];
            row.push($row.data('art_id'));
            row.push($row.find('td:eq(2) a').text().trim());
            row.push($row.find('td:eq(3)').text().trim());
            row.push($row.data('tier'));
            row.push($row.find('.aih-status-badge').text().trim());
            row.push($row.data('unique_bidders'));
            row.push($row.data('total_bids'));
            row.push($row.data('current_bid'));
            data.push(row);
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
});
</script>
