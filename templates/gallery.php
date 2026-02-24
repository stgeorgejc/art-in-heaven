<?php
/**
 * Gallery Template - Elegant Design
 */
if (!defined('ABSPATH')) exit;

// Use consolidated helper for bidder info
$bidder_info = AIH_Template_Helper::get_current_bidder_info();
$is_logged_in = $bidder_info['is_logged_in'];
$bidder = $bidder_info['bidder'];
$bidder_id = $bidder_info['id'];
$bidder_name = $bidder_info['name'];
?>
<?php if (!$is_logged_in):
    $sub_heading = __('Enter your confirmation code to access the auction', 'art-in-heaven');
    include AIH_PLUGIN_DIR . 'templates/partials/login-gate.php';
    return;
endif; ?>
<?php
// Get page URLs using consolidated helper
$gallery_url = AIH_Template_Helper::get_gallery_url();
$my_bids_url = AIH_Template_Helper::get_my_bids_url();
$checkout_url = AIH_Template_Helper::get_checkout_url();

// Get data
$art_model = new AIH_Art_Piece();
$active_pieces = $art_model->get_all(array('status' => 'active', 'bidder_id' => $bidder_id));
$show_sold = get_option('aih_show_sold_items', 1);
$ended_pieces = $show_sold ? $art_model->get_all(array('status' => 'ended', 'bidder_id' => $bidder_id)) : array();
$art_pieces = array_merge($active_pieces, $ended_pieces);

$favorites = new AIH_Favorites();
$bid_model = new AIH_Bid();
$art_images = new AIH_Art_Images();

$artists = array();
$mediums = array();
foreach ($art_pieces as $piece) {
    if (!empty($piece->artist)) $artists[] = $piece->artist;
    if (!empty($piece->medium)) $mediums[] = $piece->medium;
}
$artists = array_values(array_unique($artists));
$mediums = array_values(array_unique($mediums));
sort($artists);
sort($mediums);

$cart_count = 0;
$payment_statuses = array();
if ($is_logged_in) {
    $checkout = AIH_Checkout::get_instance();
    $cart_count = count($checkout->get_won_items($bidder_id));
    $payment_statuses = $checkout->get_bidder_payment_statuses($bidder_id);
}

// Batch pre-fetch bid data to avoid N+1 queries in the loop
$piece_ids = array_map(function($p) { return $p->id; }, $art_pieces);
$highest_bids = $bid_model->get_highest_bids_batch($piece_ids);
$winning_ids = $bid_model->get_winning_ids_batch($piece_ids, $bidder_id);
$bidder_bid_ids = $bid_model->get_bidder_bid_ids_batch($piece_ids, $bidder_id);
?>

<div id="aih-gallery-wrapper" data-server-time="<?php echo esc_attr(time() * 1000); ?>">
<div class="aih-page aih-gallery-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'gallery'; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <div class="aih-ptr-indicator"><span class="aih-ptr-spinner"></span></div>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1><?php _e('Gallery', 'art-in-heaven'); ?></h1>
                <p class="aih-subtitle"><?php printf(esc_html__('%d pieces available', 'art-in-heaven'), count($art_pieces)); ?></p>
            </div>
            <div class="aih-gallery-controls">
                <div class="aih-search-box">
                    <input type="text" id="aih-search" placeholder="Search collection..." class="aih-search-input" aria-label="<?php esc_attr_e('Search collection', 'art-in-heaven'); ?>">
                </div>
                <button type="button" class="aih-filter-toggle" id="aih-filter-toggle" aria-label="<?php esc_attr_e('Toggle filters', 'art-in-heaven'); ?>" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span><?php _e('Sort &amp; Filter', 'art-in-heaven'); ?></span>
                    <svg class="aih-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="aih-view-toggle">
                    <button type="button" class="aih-view-btn active" data-view="grid" title="<?php esc_attr_e('Grid View', 'art-in-heaven'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </button>
                    <button type="button" class="aih-view-btn" data-view="single" title="<?php esc_attr_e('Single View', 'art-in-heaven'); ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                    </button>
                </div>
            </div>
            <!-- Desktop: inline panel appears here in the header flow -->
            <div class="aih-filter-panel" id="aih-filter-panel">
            <div class="aih-filter-panel-header">
                <span class="aih-filter-panel-title"><?php _e('Sort &amp; Filter', 'art-in-heaven'); ?></span>
                <button type="button" class="aih-filter-panel-close" id="aih-filter-close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="aih-filter-panel-content">
                <div class="aih-filter-section">
                    <label class="aih-filter-label" for="aih-sort"><?php _e('Sort By', 'art-in-heaven'); ?></label>
                    <select id="aih-sort" class="aih-select">
                        <option value="artid-asc"><?php printf(__('Art ID: 1 → %d', 'art-in-heaven'), count($art_pieces)); ?></option>
                        <option value="artid-desc"><?php printf(__('Art ID: %d → 1', 'art-in-heaven'), count($art_pieces)); ?></option>
                        <option value="title-asc"><?php _e('Title: A to Z', 'art-in-heaven'); ?></option>
                        <option value="title-desc"><?php _e('Title: Z to A', 'art-in-heaven'); ?></option>
                        <option value="artist-asc"><?php _e('Artist: A to Z', 'art-in-heaven'); ?></option>
                        <option value="artist-desc"><?php _e('Artist: Z to A', 'art-in-heaven'); ?></option>
                    </select>
                </div>
                <div class="aih-filter-section">
                    <label class="aih-filter-label" for="aih-filter-artist"><?php _e('Artist', 'art-in-heaven'); ?></label>
                    <select id="aih-filter-artist" class="aih-select">
                        <option value=""><?php _e('All Artists', 'art-in-heaven'); ?></option>
                        <?php foreach ($artists as $a): ?>
                        <option value="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aih-filter-section">
                    <label class="aih-filter-label" for="aih-filter-medium"><?php _e('Medium', 'art-in-heaven'); ?></label>
                    <select id="aih-filter-medium" class="aih-select">
                        <option value=""><?php _e('All Mediums', 'art-in-heaven'); ?></option>
                        <?php foreach ($mediums as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aih-filter-section aih-filter-favorites-section" style="display: none;">
                    <label class="aih-filter-label" for="aih-filter-favorites"><?php _e('Show', 'art-in-heaven'); ?></label>
                    <select id="aih-filter-favorites" class="aih-select">
                        <option value=""><?php _e('All Items', 'art-in-heaven'); ?></option>
                        <option value="favorites"><?php _e('Favorites Only', 'art-in-heaven'); ?></option>
                    </select>
                </div>
                <div class="aih-filter-section">
                    <label class="aih-filter-label" for="aih-filter-status"><?php _e('Status', 'art-in-heaven'); ?></label>
                    <select id="aih-filter-status" class="aih-select">
                        <option value=""><?php _e('All', 'art-in-heaven'); ?></option>
                        <option value="ending-soon"><?php _e('Ending Soon (&lt; 1 hour)', 'art-in-heaven'); ?></option>
                        <option value="active"><?php _e('Active', 'art-in-heaven'); ?></option>
                        <option value="ended"><?php _e('Ended', 'art-in-heaven'); ?></option>
                    </select>
                </div>
            </div>
            <button type="button" class="aih-filter-reset" id="aih-filter-reset"><?php _e('Reset Filters', 'art-in-heaven'); ?></button>
            </div>
        </div>

        <!-- Mobile: overlay backdrop -->
        <div class="aih-filter-overlay" id="aih-filter-overlay"></div>

        <?php if (empty($art_pieces)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">&#10022;</div>
            <h2><?php _e('Coming Soon', 'art-in-heaven'); ?></h2>
            <p><?php _e('Art pieces will be available here when the auction begins.', 'art-in-heaven'); ?></p>
        </div>
        <?php else: ?>
        <div class="aih-gallery-grid" id="aih-gallery">
            <?php foreach ($art_pieces as $piece):
                $bidder_has_bid_check = false;
                $is_favorite = !empty($piece->is_favorite);
                $is_winning = isset($winning_ids[$piece->id]);
                $current_bid = isset($highest_bids[$piece->id]) ? $highest_bids[$piece->id] : 0;
                $has_bids = $current_bid > 0;
                $display_bid = $has_bids ? $current_bid : $piece->starting_bid;
                $min_bid = $piece->starting_bid;
                $primary_image = $piece->watermarked_url ?: $piece->image_url;

                // Proper status calculation - check computed_status first, then calculate from dates
                $computed_status = isset($piece->computed_status) ? $piece->computed_status : null;
                $is_ended = false;
                $is_upcoming = false;
                if ($computed_status === 'ended') {
                    $is_ended = true;
                } elseif ($computed_status === 'upcoming') {
                    $is_upcoming = true;
                } elseif ($computed_status === 'active') {
                    $is_ended = false;
                } else {
                    // Fallback: calculate from status and dates
                    $is_ended = $piece->status === 'ended' || (!empty($piece->auction_end) && strtotime($piece->auction_end) && strtotime($piece->auction_end) <= current_time('timestamp'));
                    $is_upcoming = !$is_ended && !empty($piece->auction_start) && strtotime($piece->auction_start) && strtotime($piece->auction_start) > current_time('timestamp');
                }

                $status_class = '';
                $status_text = '';

                $is_paid = isset($payment_statuses[$piece->id]) && $payment_statuses[$piece->id] === 'paid';

                if ($is_ended && $is_winning && $is_paid) {
                    $status_class = 'paid';
                    $status_text = 'Paid';
                } elseif ($is_ended && $is_winning) {
                    $status_class = 'won';
                    $status_text = 'Won';
                } elseif ($is_ended) {
                    $status_class = 'ended';
                    $status_text = 'Ended';
                } elseif ($is_upcoming) {
                    $status_class = 'upcoming';
                    $status_text = 'Upcoming';
                } elseif ($is_winning) {
                    $status_class = 'winning';
                    $status_text = 'Winning';
                } else {
                    // Check if bidder has placed a bid on this piece (outbid)
                    $bidder_has_bid_check = isset($bidder_bid_ids[$piece->id]);
                    if ($bidder_has_bid_check) {
                        $status_class = 'outbid';
                        $status_text = 'Outbid';
                    }
                }
                $bidder_has_bid_on_piece = $is_winning || (!empty($bidder_has_bid_check));
            ?>
            <article class="aih-card <?php echo $status_class; ?>"
                     data-id="<?php echo intval($piece->id); ?>"
                     data-art-id="<?php echo esc_attr($piece->art_id); ?>"
                     data-title="<?php echo esc_attr($piece->title); ?>"
                     data-artist="<?php echo esc_attr($piece->artist); ?>"
                     data-medium="<?php echo esc_attr($piece->medium); ?>"
                     <?php if (!empty($piece->auction_end)): ?>data-end="<?php echo esc_attr($piece->auction_end); ?>"<?php endif; ?>
                     <?php if (!empty($bidder_has_bid_on_piece)): ?>data-has-bid="1"<?php endif; ?>>

                <div class="aih-card-image" data-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                    <?php if ($primary_image): ?>
                    <a href="?art_id=<?php echo intval($piece->id); ?>">
                        <?php echo AIH_Template_Helper::picture_tag($primary_image, $piece->title, '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw'); ?>
                    </a>
                    <?php else: ?>
                    <a href="?art_id=<?php echo intval($piece->id); ?>" class="aih-placeholder-link">
                        <div class="aih-placeholder">
                            <span class="aih-placeholder-id"><?php echo esc_html($piece->art_id); ?></span>
                            <span class="aih-placeholder-text"><?php _e('No Image', 'art-in-heaven'); ?></span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <span class="aih-art-id-badge"><?php echo esc_html($piece->art_id); ?></span>

                    <?php if ($status_text): ?>
                    <div class="aih-badge aih-badge-<?php echo $status_class; ?>"><?php echo esc_html($status_text); ?></div>
                    <?php elseif (!$has_bids && !$is_ended && !$is_upcoming): ?>
                    <div class="aih-badge aih-badge-no-bids"><?php _e('No Bids', 'art-in-heaven'); ?></div>
                    <?php endif; ?>

                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo intval($piece->id); ?>" aria-label="<?php echo $is_favorite ? esc_attr__('Remove from favorites', 'art-in-heaven') : esc_attr__('Add to favorites', 'art-in-heaven'); ?>" aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                        <span class="aih-fav-icon">&#9829;</span>
                    </button>

                    <?php if (!$is_ended && $piece->auction_end && !empty($piece->show_end_time)): ?>
                    <div class="aih-time-remaining" data-end="<?php echo esc_attr($piece->auction_end); ?>">
                        <span class="aih-time-value">--:--:--</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="aih-card-body">
                    <h3 class="aih-card-title">
                        <a href="?art_id=<?php echo intval($piece->id); ?>"><?php echo esc_html($piece->title); ?></a>
                    </h3>
                    <p class="aih-card-artist"><?php echo esc_html($piece->artist); ?></p>
                    <p class="aih-card-bid">
                        <span class="aih-bid-label"><?php _e('Starting Bid', 'art-in-heaven'); ?></span>
                        <span class="aih-bid-amount">$<?php echo number_format($piece->starting_bid, 2); ?></span>
                    </p>
                </div>

                <?php if ($is_upcoming): ?>
                <div class="aih-card-footer">
                    <div class="aih-upcoming-notice aih-upcoming-notice--card">
                        <?php printf(esc_html__('Bidding starts %s', 'art-in-heaven'), esc_html(wp_date('M j, g:i A', strtotime($piece->auction_start)))); ?>
                    </div>
                </div>
                <?php elseif (!$is_ended): ?>
                <div class="aih-card-footer">
                    <div class="aih-bid-form">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" class="aih-bid-input" data-min="<?php echo esc_attr($min_bid); ?>" placeholder="$" aria-label="<?php printf(esc_attr__('Bid amount for %s', 'art-in-heaven'), esc_attr($piece->title)); ?>">
                        <button type="button" class="aih-bid-btn" data-id="<?php echo intval($piece->id); ?>" aria-label="<?php printf(esc_attr__('Place bid on %s', 'art-in-heaven'), esc_attr($piece->title)); ?>"><?php _e('Bid', 'art-in-heaven'); ?></button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="aih-bid-message"></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <footer class="aih-footer">
        <p><?php printf(esc_html__('© %s Art in Heaven. All rights reserved.', 'art-in-heaven'), wp_date('Y')); ?></p>
    </footer>
</div>
</div>

<!-- Toast -->
<div id="aih-toast" class="aih-toast"></div>

<!-- Scroll to Top Button -->
<button type="button" class="aih-scroll-top" id="aih-scroll-top" title="<?php esc_attr_e('Scroll to top', 'art-in-heaven'); ?>">&uarr;</button>
