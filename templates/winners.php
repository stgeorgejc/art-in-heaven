<?php
/**
 * Winners Page - Elegant Design
 */
if (!defined('ABSPATH')) exit;

if (!AIH_Database::tables_exist()) {
    echo '<div style="text-align:center;padding:60px;">The auction system is being set up.</div>';
    return;
}

// Use consolidated helper for page URLs
$gallery_url = AIH_Template_Helper::get_gallery_url();

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
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link">Gallery</a>
                <a href="#" class="aih-nav-link aih-nav-active">Winners</a>
            </nav>
            <div class="aih-header-actions"></div>
        </div>
    </header>

    <main class="aih-main">
        <div class="aih-winners-header">
            <div class="aih-ornament">✦</div>
            <h1>Art Piece Winners</h1>
            <p class="aih-subtitle"><?php echo count($winning_bids); ?> pieces sold</p>
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
                            <?php else: ?>
                            <div class="aih-placeholder">
                                <span class="aih-placeholder-id"><?php echo esc_html($bid->art_id); ?></span>
                                <span class="aih-placeholder-text">No Image</span>
                            </div>
                            <?php endif; ?>
                            <span class="aih-art-id-badge"><?php echo esc_html($bid->art_id); ?></span>
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
                                    <span class="aih-winner-code"><?php echo esc_html($bid->bidder_id); ?></span>
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

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>

<style>
.aih-winners-header {
    text-align: center;
    margin-bottom: 48px;
    padding-bottom: 32px;
    border-bottom: 1px solid var(--color-border);
}

.aih-winners-header .aih-ornament {
    margin-bottom: 24px;
}

.aih-winners-header h1 {
    font-family: var(--font-display);
    font-size: 48px;
    font-weight: 500;
    margin-bottom: 8px;
}

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
    .aih-winners-header h1 {
        font-size: 32px;
    }

    .aih-winner-code {
        font-size: 13px;
    }
}
</style>
