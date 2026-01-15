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
<script>
if (typeof aihAjax === 'undefined') {
    var aihAjax = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('aih_nonce'); ?>',
        isLoggedIn: <?php echo $is_logged_in ? 'true' : 'false'; ?>
    };
}
</script>

<?php if (!$is_logged_in): ?>
<div class="aih-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
        </div>
    </header>
    <main class="aih-main aih-main-centered">
        <div class="aih-login-card">
            <div class="aih-login-header">
                <div class="aih-ornament">✦</div>
                <h1>Sign In Required</h1>
                <p>Please sign in to view this piece</p>
            </div>
            <div class="aih-login-form">
                <div class="aih-field">
                    <label>Confirmation Code</label>
                    <input type="text" id="aih-login-code" placeholder="XXXXXXXX" autocomplete="off">
                </div>
                <button type="button" id="aih-login-btn" class="aih-btn">Sign In</button>
                <div id="aih-login-msg" class="aih-message"></div>
            </div>
        </div>
    </main>
</div>
<script>
jQuery(document).ready(function($) {
    $('#aih-login-btn').on('click', function() {
        var code = $('#aih-login-code').val().trim().toUpperCase();
        if (!code) { $('#aih-login-msg').addClass('error').text('Enter your code').show(); return; }
        $(this).prop('disabled', true).addClass('loading');
        $.post(aihAjax.ajaxurl, {action:'aih_verify_code', nonce:aihAjax.nonce, code:code}, function(r) {
            if (r.success) location.reload();
            else { $('#aih-login-msg').addClass('error').text(r.data.message).show(); $('#aih-login-btn').prop('disabled', false).removeClass('loading'); }
        });
    });
    $('#aih-login-code').on('keypress', function(e) { if (e.which === 13) $('#aih-login-btn').click(); })
        .on('input', function() { this.value = this.value.toUpperCase(); });
});
</script>
<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); return; endif;

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
$my_bid_history = $bidder_id ? $bid_model->get_bidder_bids_for_art_piece($art_piece->id, $bidder_id, true) : array();

// Proper status calculation - check computed_status first, then calculate from dates
$computed_status = isset($art_piece->computed_status) ? $art_piece->computed_status : null;
if ($computed_status === 'ended') {
    $is_ended = true;
} elseif ($computed_status === 'active') {
    $is_ended = false;
} else {
    // Fallback: calculate from status and dates
    $is_ended = $art_piece->status === 'ended' || ($art_piece->auction_end && strtotime($art_piece->auction_end) <= time());
}

$images = $art_images->get_images($art_piece->id);
$primary_image = !empty($images) ? $images[0]->watermarked_url : ($art_piece->watermarked_url ?: $art_piece->image_url);

// Navigation
$art_model = new AIH_Art_Piece();
$all_pieces = $art_model->get_all(array('status' => 'active', 'bidder_id' => $bidder_id));
$current_index = -1;
foreach ($all_pieces as $i => $p) {
    if ($p->id == $art_piece->id) { $current_index = $i; break; }
}
$prev_id = $current_index > 0 ? $all_pieces[$current_index - 1]->id : null;
$next_id = $current_index < count($all_pieces) - 1 ? $all_pieces[$current_index + 1]->id : null;

$checkout_url = '';
$checkout_page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[art_in_heaven_checkout%' LIMIT 1");
if ($checkout_page) $checkout_url = get_permalink($checkout_page);

$cart_count = 0;
$checkout = AIH_Checkout::get_instance();
$cart_count = count($checkout->get_won_items($bidder_id));
?>

<div class="aih-page aih-single-page">
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
                <?php if ($checkout_url && $cart_count > 0): ?>
                <a href="<?php echo esc_url($checkout_url); ?>" class="aih-cart-link">
                    <span>🛒</span>
                    <span class="aih-cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php endif; ?>
                <div class="aih-user-menu">
                    <?php if ($my_bids_url): ?>
                    <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-user-name aih-user-name-link"><?php echo esc_html($bidder_name); ?></a>
                    <?php else: ?>
                    <span class="aih-user-name"><?php echo esc_html($bidder_name); ?></span>
                    <?php endif; ?>
                    <button type="button" class="aih-logout-btn" id="aih-logout">Sign Out</button>
                </div>
            </div>
        </div>
    </header>

    <main class="aih-main">
        <div class="aih-single-nav-bar">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-back-link">← Back to Gallery</a>
            <span class="aih-piece-counter"><?php echo $current_index + 1; ?> / <?php echo count($all_pieces); ?></span>
        </div>

        <div class="aih-single-content-wrapper">
            <?php if ($prev_id): ?>
            <a href="?art_id=<?php echo $prev_id; ?>" class="aih-nav-arrow aih-nav-prev">←</a>
            <?php endif; ?>
            
            <div class="aih-single-content">
                <div class="aih-single-image">
                    <?php if ($primary_image): ?>
                    <img src="<?php echo esc_url($primary_image); ?>" alt="<?php echo esc_attr($art_piece->title); ?>" id="aih-main-image">
                    <?php if (count($images) > 1): ?>
                    <button type="button" class="aih-img-nav aih-img-nav-prev" aria-label="Previous image">‹</button>
                    <button type="button" class="aih-img-nav aih-img-nav-next" aria-label="Next image">›</button>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="aih-single-placeholder">
                        <span class="aih-placeholder-id"><?php echo esc_html($art_piece->art_id); ?></span>
                        <span class="aih-placeholder-text">No Image Available</span>
                    </div>
                    <?php endif; ?>

                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo $art_piece->id; ?>">
                        <span class="aih-fav-icon"><?php echo $is_favorite ? '♥' : '♡'; ?></span>
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
                        <?php if ($is_winning && !$is_ended): ?>
                        <span class="aih-badge aih-badge-winning">Winning</span>
                        <?php elseif ($is_ended): ?>
                        <span class="aih-badge aih-badge-ended"><?php echo $is_winning ? 'Won' : 'Sold'; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <h1><?php echo esc_html($art_piece->title); ?></h1>
                    <p class="aih-artist"><?php echo esc_html($art_piece->artist); ?></p>
                    
                    <div class="aih-piece-info">
                        <?php if ($art_piece->medium): ?>
                        <div class="aih-info-row">
                            <span class="aih-info-label">Medium</span>
                            <span><?php echo esc_html($art_piece->medium); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($art_piece->dimensions): ?>
                        <div class="aih-info-row">
                            <span class="aih-info-label">Dimensions</span>
                            <span><?php echo esc_html($art_piece->dimensions); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($art_piece->description): ?>
                    <div class="aih-description">
                        <?php echo wpautop(esc_html($art_piece->description)); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="aih-bid-section">
                        <div class="aih-current-bid">
                            <?php if ($has_bids): ?>
                            <span class="aih-bid-label">Bid Placed</span>
                            <span class="aih-bid-amount aih-bid-hidden" id="current-bid"></span>
                            <?php else: ?>
                            <span class="aih-bid-label">Starting Bid</span>
                            <span class="aih-bid-amount" id="current-bid">$<?php echo number_format($display_bid); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$is_ended): ?>
                        <div class="aih-bid-form-single">
                            <div class="aih-field">
                                <label>Your Bid</label>
                                <input type="number" id="bid-amount" min="<?php echo $min_bid; ?>" step="1" placeholder="Enter bid amount">
                            </div>
                            <button type="button" id="place-bid" class="aih-btn" data-id="<?php echo $art_piece->id; ?>">
                                Place Bid
                            </button>
                        </div>
                        <div id="bid-message" class="aih-message"></div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($my_bid_history)): ?>
                    <div class="aih-bid-history">
                        <h3>Your Bid History</h3>
                        <div class="aih-bid-history-list">
                            <?php foreach ($my_bid_history as $bid): ?>
                            <div class="aih-bid-history-item <?php echo $bid->is_winning ? 'winning' : ''; ?>">
                                <span class="aih-bid-history-amount">$<?php echo number_format($bid->bid_amount); ?></span>
                                <span class="aih-bid-history-time"><?php echo date('M j, g:i A', strtotime($bid->bid_time)); ?></span>
                                <?php if ($bid->is_winning): ?>
                                <span class="aih-bid-history-status">✓ Winning</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($next_id): ?>
            <a href="?art_id=<?php echo $next_id; ?>" class="aih-nav-arrow aih-nav-next">→</a>
            <?php endif; ?>
        </div>
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
    
    // Favorite
    $('.aih-fav-btn').on('click', function() {
        var $btn = $(this);
        $.post(aihAjax.ajaxurl, {action:'aih_toggle_favorite', nonce:aihAjax.nonce, art_piece_id:$btn.data('id')}, function(r) {
            if (r.success) {
                $btn.toggleClass('active');
                $btn.find('.aih-fav-icon').text($btn.hasClass('active') ? '♥' : '♡');
            }
        });
    });
    
    // Image navigation - current index
    var currentImgIndex = 0;
    var totalImages = $('.aih-image-dot').length || 1;

    function showImage(index) {
        if (index < 0) index = totalImages - 1;
        if (index >= totalImages) index = 0;
        currentImgIndex = index;

        var $dot = $('.aih-image-dot[data-index="' + index + '"]');
        var src = $dot.data('src');
        if (src) {
            $('#aih-main-image').attr('src', src);
            $('.aih-image-dot').removeClass('active');
            $dot.addClass('active');
        }
    }

    // Dot navigation
    $('.aih-image-dot').on('click', function() {
        var index = parseInt($(this).data('index'));
        showImage(index);
    });

    // Arrow navigation
    $('.aih-img-nav-prev').on('click', function() {
        showImage(currentImgIndex - 1);
    });

    $('.aih-img-nav-next').on('click', function() {
        showImage(currentImgIndex + 1);
    });
    
    // Place bid
    $('#place-bid').on('click', function() {
        var $btn = $(this);
        var amount = parseInt($('#bid-amount').val());
        var $msg = $('#bid-message');
        
        if (!amount) { $msg.addClass('error').text('Enter a bid amount').show(); return; }
        
        $btn.prop('disabled', true).addClass('loading');
        $msg.hide();
        
        $.post(aihAjax.ajaxurl, {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:$btn.data('id'), bid_amount:amount}, function(r) {
            if (r.success) {
                $msg.removeClass('error').addClass('success').text('Bid placed successfully!').show();
                $('#current-bid').text('').addClass('aih-bid-hidden');
                $('.aih-current-bid .aih-bid-label').text('Bid Placed');
                $('.aih-single-meta').find('.aih-badge').remove();
                $('.aih-single-meta').append('<span class="aih-badge aih-badge-winning">Winning</span>');
                $('#bid-amount').val('').attr('placeholder', 'Enter bid');
            } else {
                $msg.removeClass('success').addClass('error').text(r.data.message || 'Failed').show();
            }
            $btn.prop('disabled', false).removeClass('loading');
        });
    });
    
    $('#bid-amount').on('keypress', function(e) {
        if (e.which === 13) $('#place-bid').click();
    });
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>

<style>
/* Single Item Page Overrides */
.aih-single-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.aih-back-link {
    font-size: 14px;
    color: var(--color-accent);
    text-decoration: none;
    font-weight: 500;
}

.aih-back-link:hover {
    text-decoration: underline;
}

.aih-piece-counter {
    font-size: 13px;
    color: var(--color-muted);
}

/* Content wrapper with navigation arrows at edges */
.aih-single-content-wrapper {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 0;
}

.aih-single-content-wrapper > .aih-nav-arrow {
    position: absolute;
    top: 200px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 50%;
    color: var(--color-primary);
    text-decoration: none;
    font-size: 20px;
    transition: all var(--transition);
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.aih-single-content-wrapper > .aih-nav-arrow:hover {
    background: var(--color-accent);
    border-color: var(--color-accent);
    color: white;
}

.aih-single-content-wrapper > .aih-nav-prev {
    left: -24px;
}

.aih-single-content-wrapper > .aih-nav-next {
    right: -24px;
}

.aih-single-content {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
    align-items: start;
    flex: 1;
    min-width: 0;
}

.aih-single-image {
    position: relative;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    padding: 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.aih-single-image > img {
    width: auto;
    max-width: 100%;
    height: auto;
    max-height: 55vh;
    display: block;
    object-fit: contain;
    object-position: center;
}

.aih-single-image .aih-fav-btn {
    position: absolute;
    top: 24px;
    right: 24px;
    width: 40px;
    height: 40px;
    font-size: 18px;
}

.aih-thumbnails {
    display: flex;
    gap: 12px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
    flex-wrap: wrap;
    justify-content: center;
}

.aih-thumb {
    width: 60px;
    height: 60px;
    border: 2px solid var(--color-border);
    background: none;
    cursor: pointer;
    padding: 2px;
    transition: border-color var(--transition);
}

.aih-thumb.active,
.aih-thumb:hover {
    border-color: var(--color-accent);
}

.aih-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Details section - prevent text overlay */
.aih-single-details {
    padding: 0;
    overflow-wrap: break-word;
    word-wrap: break-word;
    word-break: break-word;
}

.aih-single-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.aih-single-details h1 {
    font-family: var(--font-display);
    font-size: clamp(24px, 6vw, 36px);
    font-weight: 500;
    margin-bottom: 8px;
    line-height: 1.3;
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.aih-artist {
    font-size: clamp(14px, 3.5vw, 16px);
    color: var(--color-muted);
    margin-bottom: 24px;
}

.aih-piece-info {
    padding: 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    margin-bottom: 20px;
}

.aih-info-row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-border);
}

.aih-info-row:last-child {
    border-bottom: none;
}

.aih-info-label {
    font-size: clamp(10px, 2.5vw, 12px);
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
}

.aih-info-row span:last-child {
    font-size: clamp(13px, 3vw, 15px);
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.aih-description {
    color: var(--color-secondary);
    line-height: 1.6;
    margin-bottom: 20px;
    font-size: clamp(13px, 3vw, 14px);
    overflow-wrap: break-word;
    word-wrap: break-word;
}

.aih-bid-section {
    background: var(--color-bg);
    padding: 20px;
    border: 1px solid var(--color-border);
    margin-bottom: 20px;
}

.aih-current-bid {
    margin-bottom: 20px;
    text-align: center;
}

.aih-current-bid .aih-bid-label {
    display: block;
    margin-bottom: 8px;
    font-size: clamp(10px, 2.5vw, 12px);
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
}

.aih-current-bid .aih-bid-amount {
    font-family: var(--font-display);
    font-size: clamp(28px, 7vw, 36px);
    font-weight: 600;
}

/* Hidden bid when bid is placed */
.aih-bid-hidden {
    display: none !important;
}

.aih-bid-form-single .aih-field {
    margin-bottom: 16px;
}

.aih-bid-form-single input {
    width: 100%;
    padding: 14px 16px;
    font-size: 16px;
    text-align: center;
}

/* Bid History Section */
.aih-bid-history {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    padding: 20px;
}

.aih-bid-history h3 {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 16px;
    color: var(--color-primary);
}

.aih-bid-history-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.aih-bid-history-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: var(--color-bg);
    border-radius: var(--radius);
    font-size: 14px;
}

.aih-bid-history-item.winning {
    background: rgba(45, 90, 61, 0.1);
    border: 1px solid var(--color-winning);
}

.aih-bid-history-amount {
    font-weight: 600;
    font-family: var(--font-display);
    font-size: 16px;
}

.aih-bid-history-time {
    color: var(--color-muted);
    font-size: 12px;
}

.aih-bid-history-status {
    margin-left: auto;
    color: var(--color-winning);
    font-weight: 500;
    font-size: 12px;
}

@media (min-width: 900px) {
    .aih-single-content-wrapper {
        padding: 0 60px;
    }

    .aih-single-content-wrapper > .aih-nav-prev {
        left: 0;
    }

    .aih-single-content-wrapper > .aih-nav-next {
        right: 0;
    }

    .aih-single-content {
        grid-template-columns: 1fr 1fr;
        gap: 32px;
    }

    .aih-single-image > img {
        max-height: 55vh;
    }

    .aih-single-details h1 {
        font-size: 28px;
    }

    .aih-info-row {
        flex-direction: row;
        justify-content: space-between;
        gap: 16px;
    }
}

@media (max-width: 600px) {
    .aih-single-nav-bar {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
        text-align: center;
    }

    .aih-single-content-wrapper {
        padding: 0;
    }

    .aih-single-content-wrapper > .aih-nav-arrow {
        position: fixed;
        top: 50%;
        transform: translateY(-50%);
        width: 36px;
        height: 36px;
        font-size: 16px;
        z-index: 100;
    }

    .aih-single-content-wrapper > .aih-nav-prev {
        left: 4px;
    }

    .aih-single-content-wrapper > .aih-nav-next {
        right: 4px;
    }

    .aih-single-image {
        padding: 12px;
    }

    .aih-single-image .aih-fav-btn {
        top: 16px;
        right: 16px;
        width: 32px;
        height: 32px;
        font-size: 14px;
    }

    .aih-single-meta {
        flex-direction: row;
        justify-content: flex-start;
        gap: 8px;
    }

    .aih-single-details h1 {
        font-size: 22px;
    }

    /* Bid section mobile scaling */
    .aih-bid-section {
        padding: 16px;
    }

    .aih-current-bid .aih-bid-amount {
        font-size: 26px;
    }

    .aih-bid-form-single {
        flex-direction: column;
    }

    .aih-bid-form-single .aih-btn {
        width: 100%;
    }
}

/* Single item image navigation arrows */
.aih-img-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 22px;
    font-weight: bold;
    color: var(--color-primary);
    z-index: 5;
    opacity: 0;
    transition: opacity 0.2s ease, background 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.aih-single-image:hover .aih-img-nav {
    opacity: 1;
}

.aih-img-nav-prev {
    left: 12px;
}

.aih-img-nav-next {
    right: 12px;
}

.aih-img-nav:hover {
    background: var(--color-accent);
    color: white;
}

/* Image dots for single item page */
.aih-image-dots {
    position: absolute;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 5;
}

.aih-image-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.aih-image-dot:hover {
    background: rgba(255, 255, 255, 0.8);
}

.aih-image-dot.active {
    background: white;
    transform: scale(1.3);
}

/* Single item placeholder */
.aih-single-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 400px;
    width: 100%;
    padding: 60px 40px;
    background: var(--color-bg-alt);
    color: var(--color-muted);
}

.aih-single-placeholder .aih-placeholder-id {
    font-family: var(--font-display);
    font-size: 48px;
    font-weight: 600;
    color: var(--color-accent);
    margin-bottom: 16px;
}

.aih-single-placeholder .aih-placeholder-text {
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

/* Wider details section - improved grid */
@media (min-width: 900px) {
    .aih-single-content {
        grid-template-columns: 1fr 1fr;
        gap: 32px;
    }

    .aih-single-details {
        max-width: 100%;
    }

    .aih-piece-info {
        padding: 20px;
    }

    .aih-info-row span:last-child {
        max-width: 65%;
        text-align: right;
    }

    /* Better bid section scaling */
    .aih-bid-section {
        padding: 24px;
    }

    .aih-current-bid .aih-bid-amount {
        font-size: 32px;
    }

    .aih-single-details h1 {
        font-size: 28px;
    }
}

@media (min-width: 1100px) {
    .aih-single-content {
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }

    .aih-current-bid .aih-bid-amount {
        font-size: 36px;
    }

    .aih-single-details h1 {
        font-size: 32px;
    }
}

@media (min-width: 1400px) {
    .aih-single-content {
        grid-template-columns: 1.1fr 0.9fr;
        gap: 48px;
    }
}

/* Mobile image nav always visible */
@media (max-width: 768px) {
    .aih-img-nav {
        opacity: 1;
        width: 32px;
        height: 32px;
        font-size: 18px;
    }

    .aih-img-nav-prev {
        left: 8px;
    }

    .aih-img-nav-next {
        right: 8px;
    }

    .aih-image-dot {
        width: 8px;
        height: 8px;
    }

    .aih-thumbnails {
        display: none;
    }
}
</style>
