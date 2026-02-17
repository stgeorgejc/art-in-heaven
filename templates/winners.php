<?php
/**
 * Winners Page - Elegant Design
 */
if (!defined('ABSPATH')) exit;

if (!AIH_Database::tables_exist()) {
    echo '<div style="text-align:center;padding:60px;">The auction system is being set up.</div>';
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
<script>
if (typeof aihAjax === 'undefined') {
    var aihAjax = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('aih_nonce'); ?>',
        isLoggedIn: <?php echo $is_logged_in ? 'true' : 'false'; ?>
    };
}
</script>

<div class="aih-page aih-winners-page">
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link">Gallery</a>
                <?php if ($my_bids_url): ?>
                <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-nav-link">My Bids</a>
                <?php endif; ?>
            </nav>
            <div class="aih-header-actions">
                <?php if ($is_logged_in): ?>
                <button type="button" class="aih-theme-toggle" id="aih-theme-toggle" title="Toggle dark mode"><svg class="aih-theme-icon aih-icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="aih-theme-icon aih-icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><span class="aih-theme-toggle-label">Theme</span></button>
                <div class="aih-user-menu">
                    <?php if ($my_bids_url): ?>
                    <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-user-name aih-user-name-link"><?php echo esc_html($bidder_name); ?></a>
                    <?php else: ?>
                    <span class="aih-user-name"><?php echo esc_html($bidder_name); ?></span>
                    <?php endif; ?>
                    <button type="button" class="aih-logout-btn" id="aih-logout">Sign Out</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1>Winners</h1>
                <p class="aih-subtitle"><?php echo count($winning_bids); ?> pieces sold</p>
            </div>
        </div>

        <?php if (empty($winning_bids)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2>Coming Soon</h2>
            <p>Winners will be displayed here after the auction ends.</p>
        </div>
        <?php else: 
            // Group by end date
            $grouped = array();
            foreach ($winning_bids as $bid) {
                $date_key = date('Y-m-d H:i', strtotime($bid->auction_end));
                if (!isset($grouped[$date_key])) $grouped[$date_key] = array();
                $grouped[$date_key][] = $bid;
            }
            krsort($grouped);
        ?>
            <?php foreach ($grouped as $date => $bids): ?>
            <div class="aih-winners-group">
                <div class="aih-date-divider">
                    <span><?php echo date('F j, Y \a\t g:i A', strtotime($date)); ?></span>
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
                                <span class="aih-placeholder-text">No Image</span>
                            </div>
                            <?php endif; ?>
                            <div class="aih-badge aih-badge-ended">Ended</div>
                        </div>

                        <div class="aih-card-body">
                            <h3 class="aih-card-title"><?php echo esc_html($bid->title); ?></h3>
                            <p class="aih-card-artist"><?php echo esc_html($bid->artist); ?></p>
                        </div>
                        
                        <div class="aih-card-footer">
                            <div class="aih-winner-info aih-winner-info-full">
                                <div class="aih-winner-info-item">
                                    <span class="aih-bid-label">Winner</span>
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
        <p>&copy; <?php echo date('Y'); ?> Art in Heaven. All rights reserved.</p>
    </footer>
</div>

<script>
jQuery(document).ready(function($) {
    $('#aih-logout').on('click', function() {
        $.post(aihAjax.ajaxurl, {action:'aih_logout', nonce:aihAjax.nonce}, function() { location.reload(); });
    });
});
</script>

<style>
.aih-date-divider {
    display: flex;
    align-items: center;
    margin: 40px 0 24px;
}

.aih-date-divider::before,
.aih-date-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--color-border);
}

.aih-date-divider span {
    padding: 0 24px;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
}

.aih-badge-sold {
    background: var(--color-accent);
    color: white;
}

.aih-winner-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.aih-winner-info-full {
    justify-content: center;
}

.aih-winner-info-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.aih-winner-code {
    font-family: var(--font-body);
    font-size: 15px;
    font-weight: 600;
    letter-spacing: 1.5px;
    color: var(--color-primary);
}

/* Placeholder for winners page */
.aih-winner-card .aih-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    min-height: 200px;
    background: var(--color-bg-alt);
}

.aih-winner-card .aih-placeholder-id {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 600;
    color: var(--color-accent);
    margin-bottom: 6px;
}

.aih-winner-card .aih-placeholder-text {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
}

/* Fix descender cutoff on card text */
.aih-winner-card .aih-card-title {
    line-height: 1.6;
    padding-bottom: 3px;
}

.aih-winner-card .aih-card-artist {
    line-height: 1.4;
    padding-bottom: 2px;
}

@media (max-width: 600px) {
    .aih-winner-code {
        font-size: 13px;
    }
}
</style>
