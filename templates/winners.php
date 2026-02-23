<?php
/**
 * Winners Page - Elegant Design
 */
if (!defined('ABSPATH')) exit;

if (!AIH_Database::tables_exist()) {
    echo '<div style="text-align:center;padding:60px;">' . esc_html__('The auction system is being set up.', 'art-in-heaven') . '</div>';
    return;
}

// Use consolidated helper for bidder info and page URLs
$bidder_info = AIH_Template_Helper::get_current_bidder_info();
$is_logged_in = $bidder_info['is_logged_in'];
$bidder_name = $bidder_info['name'];

$gallery_url = AIH_Template_Helper::get_gallery_url();
$my_bids_url = AIH_Template_Helper::get_my_bids_url();

$bid_model = new AIH_Bid();
$all_winning_bids = $bid_model->get_all_winning_bids();

$winning_bids = array();
if ($all_winning_bids) {
    foreach ($all_winning_bids as $bid) {
        if ($bid->auction_computed_status === 'ended') {
            $winning_bids[] = $bid;
        }
    }
}

$art_images = new AIH_Art_Images();
?>

<div class="aih-page aih-winners-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'winners'; $cart_count = 0; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1><?php _e('Winners', 'art-in-heaven'); ?></h1>
                <p class="aih-subtitle"><?php printf(esc_html__('%d pieces sold', 'art-in-heaven'), count($winning_bids)); ?></p>
            </div>
        </div>

        <?php if (empty($winning_bids)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2><?php _e('Coming Soon', 'art-in-heaven'); ?></h2>
            <p><?php _e('Winners will be displayed here after the auction ends.', 'art-in-heaven'); ?></p>
        </div>
        <?php else: 
            // Group by end date
            $grouped = array();
            foreach ($winning_bids as $bid) {
                $date_key = wp_date('Y-m-d H:i', strtotime($bid->auction_end));
                if (!isset($grouped[$date_key])) $grouped[$date_key] = array();
                $grouped[$date_key][] = $bid;
            }
            krsort($grouped);
        ?>
            <?php foreach ($grouped as $date => $bids): ?>
            <div class="aih-winners-group">
                <div class="aih-date-divider">
                    <span><?php echo wp_date('F j, Y \a\t g:i A', strtotime($date)); ?></span>
                </div>
                <div class="aih-gallery-grid">
                    <?php foreach ($bids as $bid):
                        $images = $art_images->get_images($bid->art_piece_id);
                        $watermarked = isset($bid->watermarked_url) ? $bid->watermarked_url : '';
                        $original = isset($bid->image_url) ? $bid->image_url : '';
                        $image_url = !empty($images) ? $images[0]->watermarked_url : ($watermarked ?: $original);
                    ?>
                    <article class="aih-card aih-winner-card">
                        <div class="aih-card-image">
                            <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($bid->title); ?>" loading="lazy">
                            <span class="aih-art-id-badge"><?php echo esc_html($bid->art_id); ?></span>
                            <?php else: ?>
                            <div class="aih-placeholder">
                                <span class="aih-placeholder-id"><?php echo esc_html($bid->art_id); ?></span>
                                <span class="aih-placeholder-text"><?php _e('No Image', 'art-in-heaven'); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="aih-badge aih-badge-ended"><?php _e('Ended', 'art-in-heaven'); ?></div>
                        </div>

                        <div class="aih-card-body">
                            <h3 class="aih-card-title"><?php echo esc_html($bid->title); ?></h3>
                            <p class="aih-card-artist"><?php echo esc_html($bid->artist); ?></p>
                        </div>
                        
                        <div class="aih-card-footer">
                            <div class="aih-winner-info aih-winner-info-full">
                                <div class="aih-winner-info-item">
                                    <span class="aih-bid-label"><?php _e('Winner', 'art-in-heaven'); ?></span>
                                    <span class="aih-winner-code"><?php echo esc_html(substr($bid->bidder_id, 0, 3) . '***'); ?></span>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
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


