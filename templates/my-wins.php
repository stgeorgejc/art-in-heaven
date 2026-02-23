<?php
/**
 * My Wins Page - View Purchased Art Pieces
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
    $sub_heading = __('Please sign in to view your collection', 'art-in-heaven');
    include AIH_PLUGIN_DIR . 'templates/partials/login-gate.php';
    return;
endif;

// Get purchased items from paid orders
global $wpdb;
$orders_table = AIH_Database::get_table('orders');
$items_table = AIH_Database::get_table('order_items');
$art_table = AIH_Database::get_table('art_pieces');
$images_table = AIH_Database::get_table('art_images');

$purchases = $wpdb->get_results($wpdb->prepare(
    "SELECT
        oi.id as item_id,
        oi.winning_bid,
        o.order_number,
        o.payment_status,
        o.pickup_status,
        o.created_at as order_date,
        ap.id as art_piece_id,
        ap.art_id,
        ap.title,
        ap.artist,
        ap.medium,
        ap.dimensions,
        ap.description,
        COALESCE(ai.watermarked_url, ap.watermarked_url, ap.image_url) as image_url
    FROM {$items_table} oi
    JOIN {$orders_table} o ON oi.order_id = o.id
    JOIN {$art_table} ap ON oi.art_piece_id = ap.id
    LEFT JOIN {$images_table} ai ON ap.id = ai.art_piece_id AND ai.is_primary = 1
    WHERE o.bidder_id = %s
    AND o.payment_status = 'paid'
    ORDER BY o.created_at DESC",
    $bidder_id
));

$cart_count = 0;
$checkout = AIH_Checkout::get_instance();
$cart_count = count($checkout->get_won_items($bidder_id));
?>

<div class="aih-page aih-mywins-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'my-wins'; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1><?php _e('My Collection', 'art-in-heaven'); ?></h1>
                <p class="aih-subtitle"><?php printf(esc_html__('%d pieces purchased', 'art-in-heaven'), count($purchases)); ?></p>
            </div>
        </div>

        <?php if (empty($purchases)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2><?php _e('No Purchases Yet', 'art-in-heaven'); ?></h2>
            <p><?php _e("Art pieces you've won and paid for will appear here.", 'art-in-heaven'); ?></p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn aih-btn--inline"><?php _e('Browse Gallery', 'art-in-heaven'); ?></a>
        </div>
        <?php else: ?>
        <div class="aih-wins-grid">
            <?php foreach ($purchases as $item): ?>
            <article class="aih-win-card" data-id="<?php echo $item->art_piece_id; ?>">
                <div class="aih-win-image">
                    <?php if ($item->image_url): ?>
                    <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo $item->art_piece_id; ?>">
                        <img src="<?php echo esc_url($item->image_url); ?>" alt="<?php echo esc_attr($item->title); ?>" loading="lazy">
                    </a>
                    <?php else: ?>
                    <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo $item->art_piece_id; ?>" class="aih-placeholder-link">
                        <div class="aih-placeholder">
                            <span class="aih-placeholder-id"><?php echo esc_html($item->art_id); ?></span>
                            <span class="aih-placeholder-text"><?php _e('No Image', 'art-in-heaven'); ?></span>
                        </div>
                    </a>
                    <?php endif; ?>
                    <?php if ($item->image_url && $item->art_id): ?>
                    <span class="aih-art-id-badge"><?php echo esc_html($item->art_id); ?></span>
                    <?php endif; ?>
                    <div class="aih-badge aih-badge-owned"><?php _e('Owned', 'art-in-heaven'); ?></div>
                    <?php if ($item->pickup_status === 'picked_up'): ?>
                    <div class="aih-badge aih-badge-pickup"><?php _e('Picked Up', 'art-in-heaven'); ?></div>
                    <?php endif; ?>
                </div>

                <div class="aih-win-body">
                    <h3 class="aih-win-title">
                        <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo $item->art_piece_id; ?>"><?php echo esc_html($item->title); ?></a>
                    </h3>
                    <p class="aih-win-artist"><?php echo esc_html($item->artist); ?></p>
                    <?php if ($item->medium || $item->dimensions): ?>
                    <p class="aih-win-details">
                        <?php echo esc_html($item->medium); ?>
                        <?php if ($item->medium && $item->dimensions) echo ' • '; ?>
                        <?php echo esc_html($item->dimensions); ?>
                    </p>
                    <?php endif; ?>
                </div>

                <div class="aih-win-footer">
                    <div class="aih-win-price">
                        <span class="aih-win-label"><?php _e('Purchase Price', 'art-in-heaven'); ?></span>
                        <span class="aih-win-amount">$<?php echo number_format($item->winning_bid); ?></span>
                    </div>
                    <div class="aih-win-order">
                        <span class="aih-win-label"><?php _e('Order', 'art-in-heaven'); ?></span>
                        <span class="aih-win-order-num"><?php echo esc_html($item->order_number); ?></span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <footer class="aih-footer">
        <p><?php printf(esc_html__('&copy; %s Art in Heaven. All rights reserved.', 'art-in-heaven'), wp_date('Y')); ?></p>
    </footer>
</div>

<script>
jQuery(document).ready(function($) {
    $('#aih-logout').on('click', function() {
        $.post(aihApiUrl('logout'), {action:'aih_logout', nonce:aihAjax.nonce}, function() { location.reload(); });
    });
});
</script>


