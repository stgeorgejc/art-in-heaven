<?php
/**
 * Admin Art Pieces List View - With Tabs & Role-based access
 */
if (!defined('ABSPATH')) exit;

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

$art_model = new AIH_Art_Piece();
$counts = $art_model->get_counts();
$can_view_bids = AIH_Roles::can_view_bids();

// Ensure counts has all required properties
if (!$counts) {
    $counts = new stdClass();
}
$counts->total = isset($counts->total) ? $counts->total : 0;
$counts->active = isset($counts->active) ? $counts->active : 0;
$counts->active_with_bids = isset($counts->active_with_bids) ? $counts->active_with_bids : 0;
$counts->active_no_bids = isset($counts->active_no_bids) ? $counts->active_no_bids : 0;
$counts->ended = isset($counts->ended) ? $counts->ended : 0;
$counts->draft = isset($counts->draft) ? $counts->draft : 0;
$counts->with_favorites = isset($counts->with_favorites) ? $counts->with_favorites : 0;

// Get current tab
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';

// Filter by tab
$filter_args = array();
switch ($current_tab) {
    case 'active_bids':
        $filter_args = array('status' => 'active', 'has_bids' => true);
        break;
    case 'active_no_bids':
        $filter_args = array('status' => 'active', 'has_bids' => false);
        break;
    case 'draft':
        $filter_args = array('status' => 'draft');
        break;
    case 'ended':
        $filter_args = array('status' => 'ended');
        break;
    default:
        $filter_args = array(); // All
}

$art_pieces = $art_model->get_all_with_stats($filter_args);
?>
<div class="wrap aih-admin-wrap">
    <h1 class="wp-heading-inline"><?php _e('Art Pieces', 'art-in-heaven'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=art-in-heaven-add'); ?>" class="page-title-action"><?php _e('Add New', 'art-in-heaven'); ?></a>
    <a href="#" class="page-title-action" id="aih-import-csv-btn"><?php _e('Import Art Pieces', 'art-in-heaven'); ?></a>
    <hr class="wp-header-end">
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=all'); ?>" class="nav-tab <?php echo $current_tab === 'all' ? 'nav-tab-active' : ''; ?>">
            <?php _e('All', 'art-in-heaven'); ?> (<?php echo $counts->total; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=active_bids'); ?>" class="nav-tab <?php echo $current_tab === 'active_bids' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Active with Bids', 'art-in-heaven'); ?> (<?php echo $counts->active_with_bids; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=active_no_bids'); ?>" class="nav-tab <?php echo $current_tab === 'active_no_bids' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Active - No Bids', 'art-in-heaven'); ?> (<?php echo $counts->active_no_bids; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=draft'); ?>" class="nav-tab <?php echo $current_tab === 'draft' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Draft', 'art-in-heaven'); ?> (<?php echo $counts->draft; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&tab=ended'); ?>" class="nav-tab <?php echo $current_tab === 'ended' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Ended', 'art-in-heaven'); ?> (<?php echo $counts->ended; ?>)
        </a>
    </nav>
    
    <div class="aih-tab-content">
    <!-- Combined Toolbar: Select All, Search, Filter, Sort, and Bulk Actions -->
    <div class="aih-toolbar">
        <div class="aih-toolbar-row">
            <label class="aih-select-all-label"><input type="checkbox" id="aih-select-all"> <?php _e('All', 'art-in-heaven'); ?></label>
            <input type="text" id="aih-search-table" placeholder="<?php _e('Search...', 'art-in-heaven'); ?>">
            <select id="aih-filter-artist-admin">
                <option value=""><?php _e('All Artists', 'art-in-heaven'); ?></option>
                <?php 
                $artists = array_unique(array_column($art_pieces, 'artist'));
                sort($artists);
                foreach ($artists as $artist): ?>
                    <option value="<?php echo esc_attr($artist); ?>"><?php echo esc_html($artist); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="aih-sort-table">
                <option value=""><?php _e('Sort...', 'art-in-heaven'); ?></option>
                <option value="title-asc"><?php _e('Title A-Z', 'art-in-heaven'); ?></option>
                <option value="title-desc"><?php _e('Title Z-A', 'art-in-heaven'); ?></option>
                <option value="artist-asc"><?php _e('Artist A-Z', 'art-in-heaven'); ?></option>
                <option value="artist-desc"><?php _e('Artist Z-A', 'art-in-heaven'); ?></option>
                <option value="current-desc"><?php _e('Highest Bid', 'art-in-heaven'); ?></option>
                <option value="current-asc"><?php _e('Lowest Bid', 'art-in-heaven'); ?></option>
                <option value="bids-desc"><?php _e('Most Bids', 'art-in-heaven'); ?></option>
                <option value="bids-asc"><?php _e('Fewest Bids', 'art-in-heaven'); ?></option>
                <option value="endtime-asc"><?php _e('Ending Soon', 'art-in-heaven'); ?></option>
                <option value="endtime-desc"><?php _e('Ending Last', 'art-in-heaven'); ?></option>
            </select>
            <div class="aih-bulk-actions">
                <button type="button" class="button" id="aih-bulk-start-btn" disabled>
                    <?php _e('Set Start Time', 'art-in-heaven'); ?>
                </button>
                <button type="button" class="button" id="aih-bulk-time-btn" disabled>
                    <?php _e('Set End Time', 'art-in-heaven'); ?>
                </button>
                <button type="button" class="button aih-btn-reveal" id="aih-bulk-show-end-btn" disabled>
                    <?php _e('Show Timer', 'art-in-heaven'); ?>
                </button>
                <button type="button" class="button" id="aih-bulk-hide-end-btn" disabled>
                    <?php _e('Hide Timer', 'art-in-heaven'); ?>
                </button>
                <button type="button" class="button" id="aih-bulk-delete-btn" disabled style="color: #d63638; border-color: #d63638;">
                    <?php _e('Delete', 'art-in-heaven'); ?>
                </button>
            </div>
            <span class="aih-toolbar-counts"><span id="aih-selected-num">0</span> <?php _e('sel', 'art-in-heaven'); ?> / <span id="aih-visible-count"><?php echo count($art_pieces); ?></span> <?php _e('items', 'art-in-heaven'); ?></span>
        </div>
    </div>
    
    <!-- Bulk End Time Modal -->
    <div id="aih-bulk-time-modal" class="aih-modal" style="display:none;">
        <div class="aih-modal-content">
            <span class="aih-modal-close">&times;</span>
            <h2><?php _e('Change Auction End Time', 'art-in-heaven'); ?></h2>
            <div class="aih-form-row">
                <label for="aih-bulk-datetime"><?php _e('New End Date/Time', 'art-in-heaven'); ?></label>
                <input type="datetime-local" id="aih-bulk-datetime" class="aih-datetime-input">
            </div>
            <div class="aih-modal-actions">
                <button type="button" class="button button-primary" id="aih-apply-bulk-time"><?php _e('Apply', 'art-in-heaven'); ?></button>
                <button type="button" class="button" id="aih-cancel-bulk-time"><?php _e('Cancel', 'art-in-heaven'); ?></button>
            </div>
        </div>
    </div>
    
    <!-- Bulk Start Time Modal -->
    <div id="aih-bulk-start-modal" class="aih-modal" style="display:none;">
        <div class="aih-modal-content">
            <span class="aih-modal-close">&times;</span>
            <h2><?php _e('Set Event Start Time', 'art-in-heaven'); ?></h2>
            <p class="description"><?php _e('Set the auction start time for all selected items (useful for setting the event date).', 'art-in-heaven'); ?></p>
            <div class="aih-form-row">
                <label for="aih-bulk-start-datetime"><?php _e('Event Start Date/Time', 'art-in-heaven'); ?></label>
                <input type="datetime-local" id="aih-bulk-start-datetime" class="aih-datetime-input">
            </div>
            <div class="aih-modal-actions">
                <button type="button" class="button button-primary" id="aih-apply-bulk-start"><?php _e('Apply', 'art-in-heaven'); ?></button>
                <button type="button" class="button" id="aih-cancel-bulk-start"><?php _e('Cancel', 'art-in-heaven'); ?></button>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div id="aih-import-csv-modal" class="aih-modal" style="display:none;">
        <div class="aih-modal-content aih-import-modal-content">
            <span class="aih-modal-close">&times;</span>

            <!-- Upload View -->
            <div id="aih-import-upload-view">
                <h2><?php _e('Import Art Pieces from CSV', 'art-in-heaven'); ?></h2>
                <p class="description"><?php _e('Upload a CSV file to bulk-import art pieces. Download the template to see the required format.', 'art-in-heaven'); ?></p>

                <div class="aih-import-actions-row">
                    <button type="button" class="button" id="aih-download-template"><?php _e('Download Template', 'art-in-heaven'); ?></button>
                </div>

                <div class="aih-import-file-area">
                    <label for="aih-import-file" class="aih-import-file-label">
                        <span class="dashicons dashicons-upload"></span>
                        <span id="aih-import-file-text"><?php _e('Choose CSV file (max 2MB)', 'art-in-heaven'); ?></span>
                    </label>
                    <input type="file" id="aih-import-file" accept=".csv" style="display:none;">
                </div>

                <div class="aih-import-dates">
                    <div class="aih-form-row">
                        <label for="aih-import-start"><?php _e('Auction Start', 'art-in-heaven'); ?></label>
                        <input type="datetime-local" id="aih-import-start" class="aih-datetime-input">
                    </div>
                    <div class="aih-form-row">
                        <label for="aih-import-end"><?php _e('Auction End', 'art-in-heaven'); ?></label>
                        <input type="datetime-local" id="aih-import-end" class="aih-datetime-input">
                    </div>
                </div>

                <div class="aih-import-options">
                    <label>
                        <input type="checkbox" id="aih-import-update-existing">
                        <?php _e('Update existing pieces if Art ID matches', 'art-in-heaven'); ?>
                    </label>
                </div>

                <div class="aih-modal-actions">
                    <button type="button" class="button button-primary" id="aih-import-submit" disabled><?php _e('Import', 'art-in-heaven'); ?></button>
                    <button type="button" class="button" id="aih-import-cancel"><?php _e('Cancel', 'art-in-heaven'); ?></button>
                </div>
            </div>

            <!-- Progress View -->
            <div id="aih-import-progress-view" style="display:none;">
                <h2><?php _e('Importing...', 'art-in-heaven'); ?></h2>
                <div class="aih-import-progress-bar">
                    <div class="aih-import-progress-fill" style="width:0%"></div>
                </div>
                <p class="aih-import-progress-text"><?php _e('Uploading file...', 'art-in-heaven'); ?></p>
            </div>

            <!-- Results View -->
            <div id="aih-import-results-view" style="display:none;">
                <h2><?php _e('Import Complete', 'art-in-heaven'); ?></h2>
                <div class="aih-import-summary"></div>
                <div class="aih-import-row-details"></div>
                <div class="aih-modal-actions">
                    <button type="button" class="button button-primary" id="aih-import-done"><?php _e('Done', 'art-in-heaven'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-art-table aih-inline-editable" id="aih-sortable-table">
        <thead>
            <tr>
                <th class="aih-check-column" style="width: 40px;"><input type="checkbox" id="aih-select-all-top"></th>
                <th class="aih-col-image" style="width: 60px;"><?php _e('Image', 'art-in-heaven'); ?></th>
                <th class="sortable aih-editable-col" data-sort="artid" data-field="art_id" style="width: 80px;"><?php _e('Art ID', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable aih-editable-col" data-sort="title" data-field="title"><?php _e('Title', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable aih-editable-col" data-sort="artist" data-field="artist" style="width: 120px;"><?php _e('Artist', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="aih-editable-col" data-field="medium" style="width: 100px;"><?php _e('Medium', 'art-in-heaven'); ?></th>
                <th class="aih-editable-col" data-field="tier" style="width: 60px;"><?php _e('Tier', 'art-in-heaven'); ?></th>
                <?php if ($can_view_bids): ?>
                <th class="sortable aih-editable-col" data-sort="starting" data-field="starting_bid" style="width: 90px;"><?php _e('Starting', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th class="sortable" data-sort="bids" style="width: 70px;"><?php _e('Bids', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <?php endif; ?>
                <th class="aih-editable-col" data-field="auction_start" style="width: 140px;"><?php _e('Start Time', 'art-in-heaven'); ?></th>
                <th class="sortable aih-editable-col" data-sort="endtime" data-field="auction_end" style="width: 140px;"><?php _e('End Time', 'art-in-heaven'); ?> <span class="aih-sort-icon">⇅</span></th>
                <th style="width: 80px;"><?php _e('Status', 'art-in-heaven'); ?></th>
                <th class="aih-col-actions" style="width: 120px;"><?php _e('Actions', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($art_pieces)): ?>
                <tr><td colspan="<?php echo $can_view_bids ? '13' : '11'; ?>"><?php _e('No art pieces found.', 'art-in-heaven'); ?> <a href="<?php echo admin_url('admin.php?page=art-in-heaven-add'); ?>"><?php _e('Add your first art piece', 'art-in-heaven'); ?></a></td></tr>
            <?php else: ?>
                <?php
                // Batch load bid data and images to avoid N+1 queries
                global $wpdb;
                $bids_table = AIH_Database::get_table('bids');
                $art_images_table = AIH_Database::get_table('art_images');
                $piece_ids = array_map(function($p) { return (int) $p->id; }, $art_pieces);

                // Batch: highest bids per piece
                $bid_data = array();
                if (!empty($piece_ids)) {
                    $bid_placeholders = implode(',', array_fill(0, count($piece_ids), '%d'));
                    $bid_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT art_piece_id, MAX(bid_amount) as highest_bid, COUNT(DISTINCT bidder_id) as unique_bidders
                         FROM $bids_table WHERE art_piece_id IN ($bid_placeholders) GROUP BY art_piece_id",
                        $piece_ids
                    ));
                    foreach ($bid_rows as $row) {
                        $bid_data[$row->art_piece_id] = $row;
                    }
                }

                // Batch: primary images per piece
                $image_data = array();
                if (!empty($piece_ids)) {
                    $img_placeholders = implode(',', array_fill(0, count($piece_ids), '%d'));
                    $image_rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT ai.art_piece_id, ai.watermarked_url, ai.image_url
                         FROM $art_images_table ai
                         INNER JOIN (
                             SELECT art_piece_id, MIN(CASE WHEN is_primary = 1 THEN sort_order ELSE sort_order + 10000 END) as min_order
                             FROM $art_images_table WHERE art_piece_id IN ($img_placeholders) GROUP BY art_piece_id
                         ) best ON ai.art_piece_id = best.art_piece_id
                         WHERE ai.art_piece_id IN ($img_placeholders)
                         ORDER BY ai.is_primary DESC, ai.sort_order ASC",
                        array_merge($piece_ids, $piece_ids)
                    ));
                    foreach ($image_rows as $row) {
                        if (!isset($image_data[$row->art_piece_id])) {
                            $image_data[$row->art_piece_id] = $row;
                        }
                    }
                }
                ?>
                <?php foreach ($art_pieces as $piece):
                    // Use batch-loaded data instead of per-row queries
                    $highest_bid = isset($bid_data[$piece->id]) ? $bid_data[$piece->id]->highest_bid : null;
                    $current_bid = $highest_bid ?: $piece->starting_bid;
                    $unique_bidders = isset($bid_data[$piece->id]) ? $bid_data[$piece->id]->unique_bidders : 0;

                    // Use batch-loaded image or fall back to piece properties
                    $image_url = $piece->watermarked_url ?: $piece->image_url;
                    if (empty($image_url) && isset($image_data[$piece->id])) {
                        $img = $image_data[$piece->id];
                        $image_url = $img->watermarked_url ?: $img->image_url;
                    }

                    // Format dates for datetime-local input
                    $auction_start_value = !empty($piece->auction_start) ? wp_date('Y-m-d\TH:i', strtotime($piece->auction_start)) : '';
                    $auction_end_value = !empty($piece->auction_end) ? wp_date('Y-m-d\TH:i', strtotime($piece->auction_end)) : '';
                ?>
                    <tr data-id="<?php echo esc_attr($piece->id); ?>"
                        data-title="<?php echo esc_attr(strtolower($piece->title)); ?>"
                        data-artist="<?php echo esc_attr(strtolower($piece->artist)); ?>"
                        data-artid="<?php echo esc_attr(strtolower($piece->art_id)); ?>"
                        data-starting="<?php echo esc_attr($piece->starting_bid); ?>"
                        data-current="<?php echo esc_attr($current_bid); ?>"
                        data-bids="<?php echo esc_attr($piece->total_bids); ?>"
                        data-endtime="<?php echo esc_attr(strtotime($piece->auction_end)); ?>">
                        
                        <td class="aih-check-column" data-label=""><input type="checkbox" class="aih-art-checkbox" value="<?php echo esc_attr($piece->id); ?>"></td>
                        
                        <td class="aih-col-image" data-label="">
                            <?php if ($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" class="aih-thumb" alt="">
                            <?php else: ?>
                                <span class="aih-no-image"><?php _e('No img', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="aih-editable" data-field="art_id" data-value="<?php echo esc_attr($piece->art_id); ?>" data-label="<?php esc_attr_e('Art ID', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo esc_html($piece->art_id); ?></span>
                        </td>
                        
                        <td class="aih-editable" data-field="title" data-value="<?php echo esc_attr($piece->title); ?>" data-label="<?php esc_attr_e('Title', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><a href="<?php echo admin_url('admin.php?page=art-in-heaven-add&edit=' . $piece->id); ?>"><?php echo esc_html($piece->title); ?></a></span>
                        </td>
                        
                        <td class="aih-editable" data-field="artist" data-value="<?php echo esc_attr($piece->artist); ?>" data-label="<?php esc_attr_e('Artist', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo esc_html($piece->artist); ?></span>
                        </td>
                        
                        <td class="aih-editable" data-field="medium" data-value="<?php echo esc_attr($piece->medium); ?>" data-label="<?php esc_attr_e('Medium', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo esc_html($piece->medium ?: '—'); ?></span>
                        </td>
                        
                        <td class="aih-editable" data-field="tier" data-value="<?php echo esc_attr($piece->tier); ?>" data-label="<?php esc_attr_e('Tier', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo esc_html($piece->tier ?: '—'); ?></span>
                        </td>
                        
                        <?php if ($can_view_bids): ?>
                        <td class="aih-editable" data-field="starting_bid" data-value="<?php echo esc_attr($piece->starting_bid); ?>" data-label="<?php esc_attr_e('Starting', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value">$<?php echo number_format($piece->starting_bid, 2); ?></span>
                        </td>
                        
                        <td data-label="<?php esc_attr_e('Bids', 'art-in-heaven'); ?>">
                            <?php if ($piece->total_bids > 0): ?>
                                <span class="aih-bid-count has-bids"><?php echo $piece->total_bids; ?>/<?php echo $unique_bidders; ?></span>
                            <?php else: ?>
                                <span class="aih-bid-count no-bids">0</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        
                        <td class="aih-editable aih-editable-datetime" data-field="auction_start" data-value="<?php echo esc_attr($auction_start_value); ?>" data-label="<?php esc_attr_e('Start', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo !empty($piece->auction_start) ? date_i18n('M j, g:ia', strtotime($piece->auction_start)) : '—'; ?></span>
                        </td>
                        
                        <td class="aih-editable aih-editable-datetime" data-field="auction_end" data-value="<?php echo esc_attr($auction_end_value); ?>" data-label="<?php esc_attr_e('End', 'art-in-heaven'); ?>">
                            <span class="aih-cell-value"><?php echo !empty($piece->auction_end) ? date_i18n('M j, g:ia', strtotime($piece->auction_end)) : '—'; ?></span>
                            <?php 
                            $end_visible = !empty($piece->show_end_time);
                            if ($end_visible): ?>
                                <span class="aih-vis-icon" title="<?php esc_attr_e('Visible', 'art-in-heaven'); ?>">👁</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="aih-status-cell" data-label="<?php esc_attr_e('Status', 'art-in-heaven'); ?>">
                            <?php 
                            $computed_status = isset($piece->computed_status) ? $piece->computed_status : $piece->status;
                            $auction_ended = !empty($piece->auction_end) && strtotime($piece->auction_end) && strtotime($piece->auction_end) < current_time('timestamp');
                            
                            if ($piece->status === 'draft'): ?>
                                <span class="aih-status-badge draft"><?php _e('Draft', 'art-in-heaven'); ?></span>
                            <?php elseif ($computed_status === 'upcoming'): ?>
                                <span class="aih-status-badge upcoming"><?php _e('Upcoming', 'art-in-heaven'); ?></span>
                            <?php elseif ($computed_status === 'active'): ?>
                                <span class="aih-status-badge active"><?php _e('Active', 'art-in-heaven'); ?></span>
                            <?php elseif ($computed_status === 'ended' || $auction_ended): ?>
                                <?php if ($piece->total_bids > 0): ?>
                                    <?php if (!empty($piece->pickup_status) && $piece->pickup_status === 'picked_up'): ?>
                                        <span class="aih-status-badge" style="background: #dbeafe; color: #1e40af;"><?php _e('Picked Up', 'art-in-heaven'); ?></span>
                                    <?php elseif (!empty($piece->payment_status) && $piece->payment_status === 'paid'): ?>
                                        <span class="aih-status-badge paid"><?php _e('Paid', 'art-in-heaven'); ?></span>
                                    <?php else: ?>
                                        <span class="aih-status-badge sold"><?php _e('Sold', 'art-in-heaven'); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="aih-status-badge not_sold"><?php _e('Unsold', 'art-in-heaven'); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        
                        <td class="aih-col-actions" data-label="">
                            <div class="aih-actions">
                                <a href="<?php echo admin_url('admin.php?page=art-in-heaven-add&edit=' . $piece->id); ?>" class="button button-small aih-action-btn" title="<?php esc_attr_e('Edit', 'art-in-heaven'); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                                <?php if ($can_view_bids): ?>
                                <a href="<?php echo admin_url('admin.php?page=art-in-heaven-art&stats=1&id=' . $piece->id); ?>" class="button button-small aih-action-btn" title="<?php esc_attr_e('Stats', 'art-in-heaven'); ?>">
                                    <span class="dashicons dashicons-chart-bar"></span>
                                </a>
                                <?php endif; ?>
                                <button type="button" class="button button-small aih-action-btn aih-toggle-end-time <?php echo $end_visible ? 'is-visible' : ''; ?>" 
                                        data-id="<?php echo esc_attr($piece->id); ?>" 
                                        data-visible="<?php echo $end_visible ? '1' : '0'; ?>"
                                        title="<?php echo $end_visible ? esc_attr__('Hide end time', 'art-in-heaven') : esc_attr__('Show end time', 'art-in-heaven'); ?>">
                                    <span class="dashicons <?php echo $end_visible ? 'dashicons-visibility' : 'dashicons-hidden'; ?>"></span>
                                </button>
                                <button type="button" class="button button-small aih-action-btn aih-delete-art" data-id="<?php echo esc_attr($piece->id); ?>" title="<?php esc_attr_e('Delete', 'art-in-heaven'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div><!-- /.aih-table-wrap -->
    </div><!-- /.aih-tab-content -->
</div>

<style>
/* Art Pieces - Inline Edit Styles */
.aih-inline-editable .aih-editable {
    cursor: pointer;
    position: relative;
}
.aih-inline-editable .aih-editable:hover {
    background: #f0f7ff;
}
.aih-inline-editable .aih-editable.editing {
    background: transparent;
    overflow: visible;
}
.aih-inline-editable .aih-editable .aih-cell-value {
    display: block;
}
.aih-inline-editable .aih-editable.editing .aih-cell-value {
    visibility: hidden;
}
/* Input wrapper - overlaps cell content */
.aih-inline-editable .aih-edit-wrapper {
    display: none;
    position: absolute;
    top: 50%;
    left: -4px;
    right: -4px;
    transform: translateY(-50%);
    z-index: 100;
    background: #fff;
    border: 2px solid #b8956b;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2), 0 2px 8px rgba(0,0,0,0.1);
    padding: 8px;
    min-width: 180px;
}
.aih-inline-editable .aih-editable.editing .aih-edit-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}
.aih-inline-editable .aih-edit-input {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    min-width: 0;
    background: #fafafa;
    transition: all 0.2s;
}
.aih-inline-editable .aih-edit-input:focus {
    outline: none;
    border-color: #b8956b;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(184, 149, 107, 0.15);
}
.aih-edit-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}
.aih-edit-actions button {
    width: 32px !important;
    height: 32px !important;
    min-height: 32px !important;
    padding: 0 !important;
    border-radius: 6px !important;
    border: none !important;
    cursor: pointer;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    line-height: 1 !important;
    transition: all 0.15s !important;
}
.aih-edit-confirm {
    background: #10b981 !important;
    color: #fff !important;
}
.aih-edit-confirm:hover {
    background: #4a7c59 !important;
    transform: scale(1.05);
}
.aih-edit-cancel {
    background: #ef4444 !important;
    color: #fff !important;
}
.aih-edit-cancel:hover {
    background: #a63d40 !important;
    transform: scale(1.05);
}
.aih-vis-icon {
    font-size: 10px;
    margin-left: 4px;
}
.aih-editable-datetime .aih-edit-wrapper {
    min-width: 280px;
}
}
/* Hint text */
.aih-inline-editable th.aih-editable-col::after {
    content: '✎';
    margin-left: 4px;
    opacity: 0.4;
    font-size: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    var $table = $('#aih-sortable-table');
    var $tbody = $table.find('tbody');
    var $rows = $tbody.find('tr[data-id]');
    
    // ========== INLINE EDITING ==========
    
    // Double-click to edit
    $table.on('dblclick', '.aih-editable', function(e) {
        e.preventDefault();
        var $cell = $(this);
        
        // Don't edit if already editing
        if ($cell.hasClass('editing')) return;
        
        // Cancel any other editing cells
        cancelAllEditing();
        
        var field = $cell.data('field');
        var value = $cell.data('value');
        var isDatetime = $cell.hasClass('aih-editable-datetime');
        
        // Create input
        var inputType = isDatetime ? 'datetime-local' : (field === 'starting_bid' ? 'number' : 'text');
        var inputHtml = '<input type="' + inputType + '" class="aih-edit-input" value="' + escapeHtml(value) + '"';
        if (field === 'starting_bid') {
            inputHtml += ' step="0.01" min="0"';
        }
        inputHtml += '>';
        
        // Create wrapper with input and action buttons
        var wrapperHtml = '<div class="aih-edit-wrapper">' + inputHtml +
            '<div class="aih-edit-actions">' +
            '<button type="button" class="aih-edit-confirm" title="<?php echo esc_js(__('Save', 'art-in-heaven')); ?>">✓</button>' +
            '<button type="button" class="aih-edit-cancel" title="<?php echo esc_js(__('Cancel', 'art-in-heaven')); ?>">✕</button>' +
            '</div></div>';
        
        $cell.addClass('editing').append(wrapperHtml);
        $cell.find('.aih-edit-input').focus().select();
    });
    
    // Confirm edit
    $table.on('click', '.aih-edit-confirm', function(e) {
        e.stopPropagation();
        var $cell = $(this).closest('.aih-editable');
        saveEdit($cell);
    });
    
    // Cancel edit
    $table.on('click', '.aih-edit-cancel', function(e) {
        e.stopPropagation();
        var $cell = $(this).closest('.aih-editable');
        cancelEdit($cell);
    });
    
    // Enter to save, Escape to cancel
    $table.on('keydown', '.aih-edit-input', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveEdit($(this).closest('.aih-editable'));
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEdit($(this).closest('.aih-editable'));
        }
    });
    
    // Click outside to cancel
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.aih-editable').length) {
            cancelAllEditing();
        }
    });
    
    function saveEdit($cell) {
        var $row = $cell.closest('tr');
        var id = $row.data('id');
        var field = $cell.data('field');
        var newValue = $cell.find('.aih-edit-input').val();
        var oldValue = $cell.data('value');
        
        if (newValue === oldValue) {
            cancelEdit($cell);
            return;
        }
        
        // Show loading
        $cell.find('.aih-edit-confirm').text('…').prop('disabled', true);
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_inline_edit',
                nonce: aihAdmin.nonce,
                id: id,
                field: field,
                value: newValue
            },
            success: function(r) {
                if (r.success) {
                    // Update cell value
                    $cell.data('value', r.data.value);
                    $cell.find('.aih-cell-value').html(r.data.display_value);
                    
                    // Update row data attributes if needed
                    if (field === 'title') $row.data('title', newValue.toLowerCase());
                    if (field === 'artist') $row.data('artist', newValue.toLowerCase());
                    if (field === 'art_id') $row.data('artid', newValue.toLowerCase());
                    if (field === 'starting_bid') $row.data('starting', parseFloat(newValue));
                    
                    // Update status cell if date fields were changed
                    if (r.data.status_html && (field === 'auction_start' || field === 'auction_end')) {
                        $row.find('.aih-status-cell').html(r.data.status_html);
                    }
                    
                    cancelEdit($cell);
                    
                    // Brief success flash
                    $cell.css('background', '#d1fae5');
                    setTimeout(function() { $cell.css('background', ''); }, 500);
                } else {
                    alert(r.data ? r.data.message : 'Error saving');
                    $cell.find('.aih-edit-confirm').text('✓').prop('disabled', false);
                }
            },
            error: function() {
                alert('Request failed');
                $cell.find('.aih-edit-confirm').text('✓').prop('disabled', false);
            }
        });
    }
    
    function cancelEdit($cell) {
        $cell.removeClass('editing');
        $cell.find('.aih-edit-wrapper').remove();
    }
    
    function cancelAllEditing() {
        $table.find('.aih-editable.editing').each(function() {
            cancelEdit($(this));
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    
    // ========== SEARCH/FILTER ==========
    
    function applyFilters() {
        var search = $('#aih-search-table').val().toLowerCase().trim();
        var artist = $('#aih-filter-artist-admin').val().toLowerCase();
        var visibleCount = 0;
        
        $rows.each(function() {
            var $row = $(this);
            var title = $row.data('title') || '';
            var rowArtist = $row.data('artist') || '';
            var artid = $row.data('artid') || '';
            var show = true;
            
            if (search && title.indexOf(search) === -1 && rowArtist.indexOf(search) === -1 && artid.indexOf(search) === -1) {
                show = false;
            }
            if (artist && rowArtist !== artist) {
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
    }
    
    $('#aih-search-table').on('input keyup', applyFilters);
    $('#aih-filter-artist-admin').on('change', applyFilters);
    
    // ========== SORTING ==========
    
    function sortTable(sortKey, sortDir) {
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
    }
    
    $('#aih-sort-table').on('change', function() {
        var val = $(this).val();
        if (!val) return;
        
        var parts = val.split('-');
        var sortKey = parts[0];
        var sortDir = parts[1] === 'asc' ? 1 : -1;
        
        $('th.sortable').removeClass('asc desc');
        var $th = $('th.sortable[data-sort="' + sortKey + '"]');
        if ($th.length) {
            $th.addClass(sortDir === 1 ? 'asc' : 'desc');
        }
        
        sortTable(sortKey, sortDir);
    });
    
    $('th.sortable').on('click', function() {
        var $th = $(this);
        var sortKey = $th.data('sort');
        var isAsc = $th.hasClass('asc');
        
        $('th.sortable').removeClass('asc desc');
        $th.addClass(isAsc ? 'desc' : 'asc');
        var sortDir = isAsc ? -1 : 1;
        
        var dropdownVal = sortKey + '-' + (sortDir === 1 ? 'asc' : 'desc');
        $('#aih-sort-table').val(dropdownVal);
        
        sortTable(sortKey, sortDir);
    });
    
    // ========== CHECKBOX SELECTION ==========
    
    $('#aih-select-all, #aih-select-all-top').on('change', function() {
        $('.aih-art-checkbox').prop('checked', this.checked);
        updateSelectedCount();
    });
    
    $('.aih-art-checkbox').on('change', updateSelectedCount);
    
    function updateSelectedCount() {
        var count = $('.aih-art-checkbox:checked').length;
        $('#aih-selected-num').text(count);
        $('#aih-bulk-time-btn, #aih-bulk-start-btn, #aih-bulk-show-end-btn, #aih-bulk-hide-end-btn, #aih-bulk-delete-btn').prop('disabled', count === 0);
    }
    
    // ========== MODALS ==========
    
    $('#aih-bulk-time-btn').on('click', function() { $('#aih-bulk-time-modal').fadeIn(200); });
    $('#aih-bulk-start-btn').on('click', function() { $('#aih-bulk-start-modal').fadeIn(200); });
    $('.aih-modal-close, #aih-cancel-bulk-time, #aih-cancel-bulk-start').on('click', function() { $(this).closest('.aih-modal').fadeOut(200); });
    
    $('#aih-apply-bulk-time').on('click', function() {
        var $btn = $(this);
        if ($btn.prop('disabled')) return;
        
        var ids = $('.aih-art-checkbox:checked').map(function() { return $(this).val(); }).get();
        var newTime = $('#aih-bulk-datetime').val();
        if (!newTime) { alert('Select a date/time.'); return; }
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'art-in-heaven')); ?>');
        
        $.post(aihAdmin.ajaxurl, { action: 'aih_admin_bulk_update_times', nonce: aihAdmin.nonce, ids: ids, new_end_time: newTime.replace('T', ' ') + ':00' }, function(r) {
            if (r.success) { 
                $('#aih-bulk-time-modal').hide();
                alert(r.data.message); 
                location.reload(); 
            } else { 
                alert(r.data ? r.data.message : 'Error');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'art-in-heaven')); ?>');
            }
        }).fail(function() {
            alert('Request failed');
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'art-in-heaven')); ?>');
        });
    });
    
    $('#aih-apply-bulk-start').on('click', function() {
        var $btn = $(this);
        if ($btn.prop('disabled')) return;
        
        var ids = $('.aih-art-checkbox:checked').map(function() { return $(this).val(); }).get();
        var newTime = $('#aih-bulk-start-datetime').val();
        if (!newTime) { alert('Select a date/time.'); return; }
        
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'art-in-heaven')); ?>');
        
        $.post(aihAdmin.ajaxurl, { action: 'aih_admin_bulk_update_start_times', nonce: aihAdmin.nonce, ids: ids, new_start_time: newTime.replace('T', ' ') + ':00' }, function(r) {
            if (r.success) { 
                $('#aih-bulk-start-modal').hide();
                alert(r.data.message); 
                location.reload(); 
            } else { 
                alert(r.data ? r.data.message : 'Error');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'art-in-heaven')); ?>');
            }
        }).fail(function() {
            alert('Request failed');
            $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'art-in-heaven')); ?>');
        });
    });
    
    // ========== BULK VISIBILITY ==========
    
    $('#aih-bulk-show-end-btn').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Reveal end times for selected items?', 'art-in-heaven')); ?>')) return;
        var ids = $('.aih-art-checkbox:checked').map(function() { return $(this).val(); }).get();
        $.post(aihAdmin.ajaxurl, { action: 'aih_admin_bulk_show_end_time', nonce: aihAdmin.nonce, ids: ids, show: '1' }, function(r) {
            if (r.success) { alert(r.data.message); location.reload(); }
            else { alert(r.data ? r.data.message : 'Error'); }
        });
    });
    
    $('#aih-bulk-hide-end-btn').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Hide end times for selected items?', 'art-in-heaven')); ?>')) return;
        var ids = $('.aih-art-checkbox:checked').map(function() { return $(this).val(); }).get();
        $.post(aihAdmin.ajaxurl, { action: 'aih_admin_bulk_show_end_time', nonce: aihAdmin.nonce, ids: ids, show: '0' }, function(r) {
            if (r.success) { alert(r.data.message); location.reload(); }
            else { alert(r.data ? r.data.message : 'Error'); }
        });
    });
    
    // ========== SINGLE TOGGLE END TIME ==========
    
    $(document).on('click', '.aih-toggle-end-time', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        var currentlyVisible = $btn.data('visible') == 1 || $btn.data('visible') == '1';
        var newShow = currentlyVisible ? '0' : '1';
        
        $btn.prop('disabled', true);
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_toggle_end_time', nonce: aihAdmin.nonce, id: id, show: newShow },
            success: function(r) {
                $btn.prop('disabled', false);
                if (r.success) {
                    $btn.data('visible', r.data.show);
                    if (r.data.show == 1) {
                        $btn.addClass('is-visible').attr('title', '<?php echo esc_js(__('Hide end time', 'art-in-heaven')); ?>');
                        $btn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
                        $btn.closest('tr').find('.aih-vis-icon').remove();
                        $btn.closest('tr').find('[data-field="auction_end"] .aih-cell-value').after('<span class="aih-vis-icon">👁</span>');
                    } else {
                        $btn.removeClass('is-visible').attr('title', '<?php echo esc_js(__('Show end time', 'art-in-heaven')); ?>');
                        $btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
                        $btn.closest('tr').find('.aih-vis-icon').remove();
                    }
                } else {
                    alert(r.data ? r.data.message : 'Error');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert('Request failed');
            }
        });
    });
    
    // ========== BULK DELETE ==========

    $('#aih-bulk-delete-btn').on('click', function() {
        var ids = $('.aih-art-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (!ids.length) return;
        if (!confirm('Delete ' + ids.length + ' selected art pieces? This cannot be undone.')) return;
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Deleting...', 'art-in-heaven')); ?>');
        var deleted = 0, failed = 0, total = ids.length;
        ids.forEach(function(id) {
            $.post(aihAdmin.ajaxurl, { action: 'aih_admin_delete_art', nonce: aihAdmin.nonce, id: id }, function(r) {
                if (r.success) deleted++; else failed++;
            }).fail(function() { failed++; }).always(function() {
                if (deleted + failed === total) {
                    alert(deleted + ' deleted' + (failed ? ', ' + failed + ' failed' : ''));
                    location.reload();
                }
            });
        });
    });

    // ========== DELETE ==========

    $('.aih-delete-art').on('click', function() {
        if (!confirm(aihAdmin.strings.confirmDelete)) return;
        var $row = $(this).closest('tr'), id = $(this).data('id');
        $.post(aihAdmin.ajaxurl, { action: 'aih_admin_delete_art', nonce: aihAdmin.nonce, id: id }, function(r) {
            if (r.success) { $row.fadeOut(300, function() { $(this).remove(); }); }
            else { alert(r.data.message); }
        });
    });

    // ========== CSV IMPORT ==========

    var $importModal = $('#aih-import-csv-modal');
    var $uploadView = $('#aih-import-upload-view');
    var $progressView = $('#aih-import-progress-view');
    var $resultsView = $('#aih-import-results-view');
    var importHadChanges = false;

    // Open modal
    $('#aih-import-csv-btn').on('click', function(e) {
        e.preventDefault();
        // Reset state
        $uploadView.show();
        $progressView.hide();
        $resultsView.hide();
        $('#aih-import-file').val('');
        $('#aih-import-file-text').text('<?php echo esc_js(__('Choose CSV file (max 2MB)', 'art-in-heaven')); ?>');
        $('#aih-import-update-existing').prop('checked', false);
        $('#aih-import-start').val('');
        $('#aih-import-end').val('');
        $('#aih-import-submit').prop('disabled', true);
        importHadChanges = false;
        $importModal.fadeIn(200);
    });

    // Close modal
    $importModal.find('.aih-modal-close, #aih-import-cancel').on('click', function() {
        $importModal.fadeOut(200);
    });

    // File selection
    $('#aih-import-file').on('change', function() {
        var file = this.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('<?php echo esc_js(__('File exceeds 2MB limit.', 'art-in-heaven')); ?>');
                $(this).val('');
                $('#aih-import-submit').prop('disabled', true);
                $('#aih-import-file-text').text('<?php echo esc_js(__('Choose CSV file (max 2MB)', 'art-in-heaven')); ?>');
                return;
            }
            $('#aih-import-file-text').text(file.name);
            $('#aih-import-submit').prop('disabled', false);
        } else {
            $('#aih-import-file-text').text('<?php echo esc_js(__('Choose CSV file (max 2MB)', 'art-in-heaven')); ?>');
            $('#aih-import-submit').prop('disabled', true);
        }
    });

    // Download template
    $('#aih-download-template').on('click', function() {
        var bom = '\uFEFF';
        var headers = 'art_id,title,artist,medium,dimensions,description,starting_bid,tier';
        var example = 'ART-001,Sunset Over Mountains,Jane Doe,Oil on Canvas,24 x 36 in,A vibrant sunset landscape,150.00,2';
        var csv = bom + headers + '\n' + example + '\n';
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'art-import-template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    // Submit import
    $('#aih-import-submit').on('click', function() {
        var file = $('#aih-import-file')[0].files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('action', 'aih_admin_import_csv');
        formData.append('nonce', aihAdmin.nonce);
        formData.append('csv_file', file);
        formData.append('update_existing', $('#aih-import-update-existing').is(':checked') ? '1' : '0');
        var startVal = $('#aih-import-start').val();
        var endVal = $('#aih-import-end').val();
        if (startVal) formData.append('auction_start', startVal.replace('T', ' ') + ':00');
        if (endVal) formData.append('auction_end', endVal.replace('T', ' ') + ':00');

        // Switch to progress view
        $uploadView.hide();
        $progressView.show();
        $('.aih-import-progress-fill').css('width', '0%');
        $('.aih-import-progress-text').text('<?php echo esc_js(__('Uploading file...', 'art-in-heaven')); ?>');

        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var pct = Math.round((e.loaded / e.total) * 100);
                        $('.aih-import-progress-fill').css('width', pct + '%');
                        if (pct >= 100) {
                            $('.aih-import-progress-text').text('<?php echo esc_js(__('Processing rows...', 'art-in-heaven')); ?>');
                        }
                    }
                });
                return xhr;
            },
            success: function(r) {
                $progressView.hide();
                $resultsView.show();

                if (r.success) {
                    var s = r.data.summary;
                    importHadChanges = (s.created > 0 || s.updated > 0);

                    // Build summary grid
                    var summaryHtml = '<div class="aih-import-summary-grid">' +
                        '<div class="aih-import-stat"><span class="aih-import-stat-num">' + s.total + '</span><span class="aih-import-stat-label"><?php echo esc_js(__('Total', 'art-in-heaven')); ?></span></div>' +
                        '<div class="aih-import-stat aih-stat-created"><span class="aih-import-stat-num">' + s.created + '</span><span class="aih-import-stat-label"><?php echo esc_js(__('Created', 'art-in-heaven')); ?></span></div>' +
                        '<div class="aih-import-stat aih-stat-updated"><span class="aih-import-stat-num">' + s.updated + '</span><span class="aih-import-stat-label"><?php echo esc_js(__('Updated', 'art-in-heaven')); ?></span></div>' +
                        '<div class="aih-import-stat aih-stat-skipped"><span class="aih-import-stat-num">' + s.skipped + '</span><span class="aih-import-stat-label"><?php echo esc_js(__('Skipped', 'art-in-heaven')); ?></span></div>' +
                        '<div class="aih-import-stat aih-stat-errors"><span class="aih-import-stat-num">' + s.errors + '</span><span class="aih-import-stat-label"><?php echo esc_js(__('Errors', 'art-in-heaven')); ?></span></div>' +
                        '</div>';
                    $('.aih-import-summary').html(summaryHtml);

                    // Build per-row details
                    var rowsHtml = '<div class="aih-import-rows-list">';
                    $.each(r.data.rows, function(i, row) {
                        var cls = 'aih-import-row-' + row.status;
                        rowsHtml += '<div class="aih-import-row-item ' + cls + '">' +
                            '<span class="aih-import-row-num">' + row.row + '</span>' +
                            '<span class="aih-import-row-artid">' + escapeHtml(row.art_id) + '</span>' +
                            '<span class="aih-import-row-status">' + row.status + '</span>' +
                            '<span class="aih-import-row-msg">' + escapeHtml(row.message) + '</span>' +
                            '</div>';
                    });
                    rowsHtml += '</div>';
                    $('.aih-import-row-details').html(rowsHtml);
                } else {
                    $('.aih-import-summary').html('<div class="notice notice-error"><p>' + escapeHtml(r.data.message || '<?php echo esc_js(__('Import failed.', 'art-in-heaven')); ?>') + '</p></div>');
                    $('.aih-import-row-details').html('');
                }
            },
            error: function() {
                $progressView.hide();
                $resultsView.show();
                $('.aih-import-summary').html('<div class="notice notice-error"><p><?php echo esc_js(__('Request failed. Please try again.', 'art-in-heaven')); ?></p></div>');
                $('.aih-import-row-details').html('');
            }
        });
    });

    // Done button
    $('#aih-import-done').on('click', function() {
        $importModal.fadeOut(200);
        if (importHadChanges) {
            location.reload();
        }
    });
});
</script>
