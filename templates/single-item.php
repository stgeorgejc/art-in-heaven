<?php
/**
 * Single Item Page - Elegant Design
 */
if (!defined('ABSPATH')) exit;

// Use consolidated helper for bidder info and page URLs
$bidder_info = AIH_Template_Helper::get_current_bidder_info();
$is_logged_in = $bidder_info['is_logged_in'];
$bidder = $bidder_info['bidder'];
$bidder_id = $bidder_info['id'];
$bidder_name = $bidder_info['name'];

$gallery_url = AIH_Template_Helper::get_gallery_url();
$my_bids_url = AIH_Template_Helper::get_my_bids_url();
?>
<?php if (!$is_logged_in):
    $sub_heading = __('Please sign in to view this piece', 'art-in-heaven');
    include AIH_PLUGIN_DIR . 'templates/partials/login-gate.php';
    return;
endif;

// Get art piece
$favorites = new AIH_Favorites();
$bid_model = new AIH_Bid();
$art_images = new AIH_Art_Images();
$bid_increment = floatval(get_option('aih_bid_increment', 1));

$is_favorite = $bidder_id ? $favorites->is_favorite($bidder_id, $art_piece->id) : false;
$is_winning = $bidder_id ? $bid_model->is_bidder_winning($art_piece->id, $bidder_id) : false;
$current_bid = $bid_model->get_highest_bid_amount($art_piece->id);
$has_bids = $current_bid > 0;
$display_bid = $has_bids ? $current_bid : $art_piece->starting_bid;
$min_bid = $has_bids ? $current_bid + $bid_increment : $art_piece->starting_bid;

// Get bidder's successful bid history for this piece
$my_bid_history = $bidder_id ? $bid_model->get_bidder_bids_for_art_piece($art_piece->id, $bidder_id) : array();

// Proper status calculation - check computed_status first, then calculate from dates
$computed_status = isset($art_piece->computed_status) ? $art_piece->computed_status : null;
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
    $is_ended = $art_piece->status === 'ended' || (!empty($art_piece->auction_end) && strtotime($art_piece->auction_end) && strtotime($art_piece->auction_end) <= current_time('timestamp'));
    $is_upcoming = !$is_ended && !empty($art_piece->auction_start) && strtotime($art_piece->auction_start) && strtotime($art_piece->auction_start) > current_time('timestamp');
}

$images = $art_images->get_images($art_piece->id);
$primary_image = !empty($images) ? $images[0]->watermarked_url : ($art_piece->watermarked_url ?: $art_piece->image_url);

// Navigation - include active and ended pieces, exclude upcoming
// TODO: optimize to fetch only IDs for navigation
$art_model = new AIH_Art_Piece();
$nav_active = $art_model->get_all(array('status' => 'active', 'bidder_id' => $bidder_id));
$nav_ended = $art_model->get_all(array('status' => 'ended', 'bidder_id' => $bidder_id));
$all_pieces = array_merge($nav_active, $nav_ended);
$current_index = -1;
foreach ($all_pieces as $i => $p) {
    if ($p->id == $art_piece->id) { $current_index = $i; break; }
}
$prev_id = $current_index > 0 ? $all_pieces[$current_index - 1]->id : null;
$next_id = $current_index < count($all_pieces) - 1 ? $all_pieces[$current_index + 1]->id : null;

$checkout_url = AIH_Template_Helper::get_checkout_url();

$cart_count = 0;
$checkout = AIH_Checkout::get_instance();
$cart_count = count($checkout->get_won_items($bidder_id));
$payment_statuses = $bidder_id ? $checkout->get_bidder_payment_statuses($bidder_id) : array();
$is_paid = isset($payment_statuses[$art_piece->id]) && $payment_statuses[$art_piece->id] === 'paid';

// Build image URLs array for JS (used by external aih-single-item.js)
$image_urls = array_map(function($img) { return $img->watermarked_url; }, $images);
if (empty($image_urls) && $primary_image) {
    $image_urls = array($primary_image);
}
?>

<div id="aih-single-wrapper" data-server-time="<?php echo esc_attr(time() * 1000); ?>" data-piece-id="<?php echo intval($art_piece->id); ?>" data-is-ended="<?php echo $is_ended ? '1' : '0'; ?>" data-images="<?php echo esc_attr(wp_json_encode($image_urls)); ?>">
<div class="aih-page aih-single-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'single-item'; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <main class="aih-main">
        <div class="aih-single-nav-bar">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-back-link">&larr; <?php _e('Back to Gallery', 'art-in-heaven'); ?></a>
            <div class="aih-nav-center">
                <?php if ($prev_id): ?>
                <a href="?art_id=<?php echo intval($prev_id); ?>" class="aih-nav-arrow" title="<?php esc_attr_e('Previous', 'art-in-heaven'); ?>">&larr;</a>
                <?php else: ?>
                <span class="aih-nav-arrow disabled">&larr;</span>
                <?php endif; ?>
                <span class="aih-piece-counter"><?php echo $current_index + 1; ?> / <?php echo count($all_pieces); ?></span>
                <?php if ($next_id): ?>
                <a href="?art_id=<?php echo intval($next_id); ?>" class="aih-nav-arrow" title="<?php esc_attr_e('Next', 'art-in-heaven'); ?>">&rarr;</a>
                <?php else: ?>
                <span class="aih-nav-arrow disabled">&rarr;</span>
                <?php endif; ?>
            </div>
            <div class="aih-nav-spacer"></div>
        </div>

        <div class="aih-single-content-wrapper">

            <div class="aih-single-content">
                <div class="aih-single-image <?php echo count($images) > 1 ? 'has-multiple-images' : ''; ?>">
                    <?php if ($primary_image): ?>
                    <img src="<?php echo esc_url($primary_image); ?>" alt="<?php echo esc_attr($art_piece->title); ?>" id="aih-main-image">
                    <?php if (count($images) > 1): ?>
                    <button type="button" class="aih-img-nav aih-img-nav-prev" aria-label="<?php esc_attr_e('Previous image', 'art-in-heaven'); ?>">&lsaquo;</button>
                    <button type="button" class="aih-img-nav aih-img-nav-next" aria-label="<?php esc_attr_e('Next image', 'art-in-heaven'); ?>">&rsaquo;</button>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="aih-single-placeholder">
                        <span class="aih-placeholder-id"><?php echo esc_html($art_piece->art_id); ?></span>
                        <span class="aih-placeholder-text"><?php _e('No Image Available', 'art-in-heaven'); ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Status Badge on image -->
                    <?php if ($is_ended && $is_winning && $is_paid): ?>
                    <span class="aih-badge aih-badge-paid aih-badge-single"><?php _e('Paid', 'art-in-heaven'); ?></span>
                    <?php elseif ($is_ended && $is_winning): ?>
                    <span class="aih-badge aih-badge-won aih-badge-single"><?php _e('Won', 'art-in-heaven'); ?></span>
                    <?php elseif ($is_ended): ?>
                    <span class="aih-badge aih-badge-ended aih-badge-single"><?php _e('Ended', 'art-in-heaven'); ?></span>
                    <?php elseif ($is_winning): ?>
                    <span class="aih-badge aih-badge-winning aih-badge-single"><?php _e('Winning', 'art-in-heaven'); ?></span>
                    <?php elseif (!empty($my_bid_history)): ?>
                    <span class="aih-badge aih-badge-outbid aih-badge-single"><?php _e('Outbid', 'art-in-heaven'); ?></span>
                    <?php elseif (!$has_bids && !$is_ended && !$is_upcoming): ?>
                    <span class="aih-badge aih-badge-no-bids aih-badge-single"><?php _e('No Bids Yet', 'art-in-heaven'); ?></span>
                    <?php endif; ?>

                    <!-- Art ID Badge on image -->
                    <span class="aih-art-id-badge-single"><?php echo esc_html($art_piece->art_id); ?></span>

                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo intval($art_piece->id); ?>" aria-label="<?php echo $is_favorite ? esc_attr__('Remove from favorites', 'art-in-heaven') : esc_attr__('Add to favorites', 'art-in-heaven'); ?>" aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>">
                        <span class="aih-fav-icon">&#9829;</span>
                    </button>

                    <?php if (count($images) > 1): ?>
                    <div class="aih-image-dots">
                        <?php foreach ($images as $i => $img): ?>
                        <span class="aih-image-dot <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>" data-src="<?php echo esc_url($img->watermarked_url); ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="aih-single-details">
                    <div class="aih-single-meta">
                        <span class="aih-art-id"><?php echo esc_html($art_piece->art_id); ?></span>
                    </div>

                    <h1><?php echo esc_html($art_piece->title); ?></h1>
                    <p class="aih-artist"><?php echo esc_html($art_piece->artist); ?></p>

                    <div class="aih-piece-info">
                        <?php if ($art_piece->medium): ?>
                        <div class="aih-info-row">
                            <span class="aih-info-label"><?php _e('Medium', 'art-in-heaven'); ?></span>
                            <span><?php echo esc_html($art_piece->medium); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($art_piece->dimensions): ?>
                        <div class="aih-info-row">
                            <span class="aih-info-label"><?php _e('Dimensions', 'art-in-heaven'); ?></span>
                            <span><?php echo esc_html($art_piece->dimensions); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($art_piece->description): ?>
                        <div class="aih-info-row aih-description-row">
                            <span class="aih-info-label"><?php _e('Description', 'art-in-heaven'); ?></span>
                            <div class="aih-description-text"><?php echo wpautop(esc_html($art_piece->description)); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_upcoming): ?>
                    <div class="aih-bid-section">
                        <div class="aih-upcoming-notice">
                            <?php printf(esc_html__('Bidding starts %s', 'art-in-heaven'), esc_html(wp_date('M j, Y \a\t g:i A', strtotime($art_piece->auction_start)))); ?>
                        </div>
                    </div>
                    <?php elseif (!$is_ended): ?>
                    <div class="aih-bid-section">
                        <?php if ($art_piece->auction_end && !empty($art_piece->show_end_time)): ?>
                        <div class="aih-time-remaining-single" data-end="<?php echo esc_attr($art_piece->auction_end); ?>">
                            <span class="aih-time-label"><?php _e('Time Remaining', 'art-in-heaven'); ?></span>
                            <span class="aih-time-value">--:--:--</span>
                        </div>
                        <?php endif; ?>

                        <div class="aih-bid-info">
                            <span class="aih-bid-label"><?php _e('Starting Bid', 'art-in-heaven'); ?></span>
                            <span class="aih-bid-amount">$<?php echo number_format($art_piece->starting_bid); ?></span>
                        </div>

                        <div class="aih-bid-form-single">
                            <div class="aih-field">
                                <label for="bid-amount"><?php _e('Your Bid', 'art-in-heaven'); ?></label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" id="bid-amount" data-min="<?php echo esc_attr($min_bid); ?>" placeholder="$" aria-label="<?php printf(esc_attr__('Bid amount for %s', 'art-in-heaven'), esc_attr($art_piece->title)); ?>">
                            </div>
                            <button type="button" id="place-bid" class="aih-bid-btn" data-id="<?php echo intval($art_piece->id); ?>" aria-label="<?php printf(esc_attr__('Place bid on %s', 'art-in-heaven'), esc_attr($art_piece->title)); ?>">
                                <?php _e('Bid', 'art-in-heaven'); ?>
                            </button>
                        </div>
                        <div id="bid-message" class="aih-message"></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($my_bid_history)): ?>
                    <div class="aih-bid-history">
                        <h3><?php _e('Your Bid History', 'art-in-heaven'); ?></h3>
                        <div class="aih-bid-history-list">
                            <?php foreach ($my_bid_history as $bid): ?>
                            <div class="aih-bid-history-item <?php echo $bid->is_winning ? 'winning' : ''; ?>">
                                <span class="aih-bid-history-amount">$<?php echo number_format($bid->bid_amount); ?></span>
                                <span class="aih-bid-history-time"><?php echo esc_html(date_i18n('M j, g:i A', strtotime($bid->bid_time))); ?></span>
                                <?php if ($bid->is_winning): ?>
                                <span class="aih-bid-history-status">&#10003; <?php _e('Winning', 'art-in-heaven'); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="aih-footer">
        <p><?php printf(esc_html__('© %s Art in Heaven. All rights reserved.', 'art-in-heaven'), wp_date('Y')); ?></p>
    </footer>
</div>
</div>

<!-- Scroll to Top Button -->
<button type="button" class="aih-scroll-top" id="aih-scroll-top" title="<?php esc_attr_e('Scroll to top', 'art-in-heaven'); ?>">&uarr;</button>

<!-- Lightbox for image viewing -->
<div class="aih-lightbox" id="aih-lightbox">
    <button type="button" class="aih-lightbox-close" aria-label="<?php esc_attr_e('Close', 'art-in-heaven'); ?>">&times;</button>
    <button type="button" class="aih-lightbox-nav aih-lightbox-prev" aria-label="<?php esc_attr_e('Previous image', 'art-in-heaven'); ?>">&lsaquo;</button>
    <button type="button" class="aih-lightbox-nav aih-lightbox-next" aria-label="<?php esc_attr_e('Next image', 'art-in-heaven'); ?>">&rsaquo;</button>
    <div class="aih-lightbox-content">
        <img src="" alt="" class="aih-lightbox-image" id="aih-lightbox-img">
    </div>
    <div class="aih-lightbox-dots"></div>
    <div class="aih-lightbox-counter"><span id="aih-lb-current">1</span> / <?php echo count($images); ?></div>
</div>


