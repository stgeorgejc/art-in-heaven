<?php
/**
 * My Bids Page - Elegant Design
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
$checkout_url = AIH_Template_Helper::get_checkout_url();
?>
<?php if (!$is_logged_in):
    $sub_heading = __('Please sign in to view your bids', 'art-in-heaven');
    include AIH_PLUGIN_DIR . 'templates/partials/login-gate.php';
    return;
endif;

// Get user's bids - returns only the highest valid bid per art piece
$bid_model = new AIH_Bid();
$favorites = new AIH_Favorites();
$art_images = new AIH_Art_Images();
$my_bids = $bid_model->get_bidder_bids($bidder_id);
$cart_count = 0;
$checkout = AIH_Checkout::get_instance();
$cart_count = count($checkout->get_won_items($bidder_id));
$my_orders = $checkout->get_bidder_orders($bidder_id);
$payment_statuses = $checkout->get_bidder_payment_statuses($bidder_id);
?>

<div id="aih-mybids-wrapper" data-server-time="<?php echo esc_attr(time() * 1000); ?>">
<div class="aih-page aih-mybids-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'my-bids'; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <div class="aih-ptr-indicator"><span class="aih-ptr-spinner"></span></div>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1><?php _e('My Bids', 'art-in-heaven'); ?></h1>
                <p class="aih-subtitle"><?php printf(esc_html__('%d items', 'art-in-heaven'), count($my_bids)); ?></p>
            </div>
        </div>

        <?php if (empty($my_bids)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2><?php _e('No Bids Yet', 'art-in-heaven'); ?></h2>
            <p><?php _e('Browse the gallery and place your first bid!', 'art-in-heaven'); ?></p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn aih-btn--inline"><?php _e('View Gallery', 'art-in-heaven'); ?></a>
        </div>
        <?php else: ?>
        <div class="aih-gallery-grid">
            <?php foreach ($my_bids as $bid):
                $is_winning = ($bid->is_winning == 1);
                $bid_status = isset($bid->computed_status) ? $bid->computed_status : (isset($bid->auction_status) ? $bid->auction_status : 'active');
                $is_ended = $bid_status === 'ended' || (!empty($bid->auction_end) && strtotime($bid->auction_end) && strtotime($bid->auction_end) <= current_time('timestamp'));
                $images = $art_images->get_images($bid->art_piece_id);
                $bid_title = isset($bid->title) ? $bid->title : (isset($bid->art_title) ? $bid->art_title : '');
                $image_url = !empty($images) ? $images[0]->watermarked_url : (isset($bid->watermarked_url) ? $bid->watermarked_url : (isset($bid->image_url) ? $bid->image_url : ''));
                $min_bid = floatval($bid->starting_bid);

                $is_paid = isset($payment_statuses[$bid->art_piece_id]) && $payment_statuses[$bid->art_piece_id] === 'paid';

                if ($is_ended && $is_winning && $is_paid) {
                    $status_class = 'paid';
                    $status_text = 'Paid';
                } elseif ($is_ended && $is_winning) {
                    $status_class = 'won';
                    $status_text = 'Won';
                } elseif ($is_ended) {
                    $status_class = 'ended';
                    $status_text = 'Ended';
                } elseif ($is_winning) {
                    $status_class = 'winning';
                    $status_text = 'Winning';
                } else {
                    $status_class = 'outbid';
                    $status_text = 'Outbid';
                }
            ?>
            <article class="aih-card <?php echo esc_attr($status_class); ?>" data-id="<?php echo intval($bid->art_piece_id); ?>" data-starting-bid="<?php echo esc_attr($bid->starting_bid); ?>" <?php if (!empty($bid->auction_end)): ?>data-end="<?php echo esc_attr($bid->auction_end); ?>"<?php endif; ?>>
                <div class="aih-card-image">
                    <?php if ($image_url): ?>
                    <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo intval($bid->art_piece_id); ?>">
                        <?php echo AIH_Template_Helper::picture_tag($image_url, $bid_title, '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw'); ?>
                    </a>
                    <?php else: ?>
                    <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo intval($bid->art_piece_id); ?>" class="aih-placeholder-link">
                        <div class="aih-placeholder">
                            <span class="aih-placeholder-id"><?php echo esc_html(isset($bid->art_id) ? $bid->art_id : ''); ?></span>
                            <span class="aih-placeholder-text"><?php _e('No Image', 'art-in-heaven'); ?></span>
                        </div>
                    </a>
                    <?php endif; ?>
                    <?php if ($image_url): ?>
                    <span class="aih-art-id-badge"><?php echo esc_html(isset($bid->art_id) ? $bid->art_id : ''); ?></span>
                    <?php endif; ?>
                    <div class="aih-badge aih-badge-<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></div>

                    <?php if (!$is_ended && !empty($bid->auction_end) && !empty($bid->show_end_time)): ?>
                    <div class="aih-time-remaining" data-end="<?php echo esc_attr($bid->auction_end); ?>">
                        <span class="aih-time-value">--:--:--</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="aih-card-body">
                    <h3 class="aih-card-title">
                        <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo intval($bid->art_piece_id); ?>"><?php echo esc_html($bid_title); ?></a>
                    </h3>
                    <p class="aih-card-artist"><?php echo esc_html(isset($bid->artist) ? $bid->artist : ''); ?></p>
                </div>
                
                <div class="aih-card-footer">
                    <div class="aih-bid-info aih-bid-info-centered">
                        <div>
                            <span class="aih-bid-label"><?php _e('Your Bid', 'art-in-heaven'); ?></span>
                            <span class="aih-bid-amount">$<?php echo number_format($bid->bid_amount); ?></span>
                            <?php if (isset($bid->bid_count) && $bid->bid_count > 1): ?>
                            <span class="aih-bid-count"><?php printf(esc_html__('(%d bids)', 'art-in-heaven'), intval($bid->bid_count)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$is_ended && !$is_winning): ?>
                    <div class="aih-bid-form">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" class="aih-bid-input" data-min="<?php echo esc_attr($min_bid); ?>" placeholder="$">
                        <button type="button" class="aih-bid-btn" data-id="<?php echo intval($bid->art_piece_id); ?>"><?php _e('Bid', 'art-in-heaven'); ?></button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="aih-bid-message"></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($my_orders)): ?>
        <div class="aih-previous-orders">
            <h2 class="aih-orders-heading"><?php _e('My Orders', 'art-in-heaven'); ?></h2>
            <div class="aih-orders-grid">
                <?php foreach ($my_orders as $order): ?>
                <div class="aih-order-card aih-order-clickable" data-order="<?php echo esc_attr($order->order_number); ?>">
                    <div class="aih-order-header">
                        <strong><?php echo esc_html($order->order_number); ?></strong>
                        <span class="aih-order-status aih-status-<?php echo esc_attr($order->payment_status); ?>">
                            <?php echo esc_html(ucfirst($order->payment_status)); ?>
                        </span>
                    </div>
                    <div class="aih-order-details">
                        <p><?php echo intval($order->item_count); ?> <?php echo $order->item_count != 1 ? __('items', 'art-in-heaven') : __('item', 'art-in-heaven'); ?> &bull; $<?php echo number_format($order->total); ?></p>
                        <p class="aih-order-date"><?php echo esc_html(wp_date('M j, Y', strtotime($order->created_at))); ?></p>
                    </div>
                    <div class="aih-order-view-link">
                        <span><?php _e('View Details', 'art-in-heaven'); ?> &rarr;</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Order Details Modal -->
        <div id="aih-order-modal" class="aih-modal" role="dialog" aria-modal="true" aria-labelledby="aih-modal-title" style="display: none;">
            <div class="aih-modal-backdrop" aria-hidden="true"></div>
            <div class="aih-modal-content">
                <div class="aih-modal-header">
                    <h3 id="aih-modal-title"><?php _e('Order Details', 'art-in-heaven'); ?></h3>
                    <button type="button" class="aih-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="aih-modal-body" id="aih-modal-body">
                    <div class="aih-loading"><?php _e('Loading...', 'art-in-heaven'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="aih-footer">
        <p><?php printf(esc_html__('&copy; %s Art in Heaven. All rights reserved.', 'art-in-heaven'), wp_date('Y')); ?></p>
    </footer>
</div>
</div>


