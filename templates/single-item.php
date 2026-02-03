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
            <div class="aih-nav-center">
                <?php if ($prev_id): ?>
                <a href="?art_id=<?php echo $prev_id; ?>" class="aih-nav-arrow" title="Previous">←</a>
                <?php else: ?>
                <span class="aih-nav-arrow disabled">←</span>
                <?php endif; ?>
                <span class="aih-piece-counter"><?php echo $current_index + 1; ?> / <?php echo count($all_pieces); ?></span>
                <?php if ($next_id): ?>
                <a href="?art_id=<?php echo $next_id; ?>" class="aih-nav-arrow" title="Next">→</a>
                <?php else: ?>
                <span class="aih-nav-arrow disabled">→</span>
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
                    <button type="button" class="aih-img-nav aih-img-nav-prev" aria-label="Previous image">‹</button>
                    <button type="button" class="aih-img-nav aih-img-nav-next" aria-label="Next image">›</button>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="aih-single-placeholder">
                        <span class="aih-placeholder-id"><?php echo esc_html($art_piece->art_id); ?></span>
                        <span class="aih-placeholder-text">No Image Available</span>
                    </div>
                    <?php endif; ?>

                    <!-- Status Badge on image -->
                    <?php if ($is_winning && !$is_ended): ?>
                    <span class="aih-badge aih-badge-winning aih-badge-single">Winning</span>
                    <?php elseif ($is_ended): ?>
                    <span class="aih-badge aih-badge-ended aih-badge-single"><?php echo $is_winning ? 'Won' : 'Ended'; ?></span>
                    <?php endif; ?>

                    <!-- Art ID Badge on image -->
                    <span class="aih-art-id-badge-single"><?php echo esc_html($art_piece->art_id); ?></span>

                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo $art_piece->id; ?>">
                        <span class="aih-fav-icon">♥</span>
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
                        <span class="aih-description-label">Description</span>
                        <?php echo wpautop(esc_html($art_piece->description)); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($is_upcoming): ?>
                    <div class="aih-bid-section">
                        <div class="aih-upcoming-notice" style="padding: 16px; background: #f0f0f0; border-radius: 8px; text-align: center; color: #555;">
                            Bidding starts <?php echo date('M j, Y \a\t g:i A', strtotime($art_piece->auction_start)); ?>
                        </div>
                    </div>
                    <?php elseif (!$is_ended): ?>
                    <div class="aih-bid-section">
                        <?php if ($art_piece->auction_end && !empty($art_piece->show_end_time)): ?>
                        <div class="aih-time-remaining-single" data-end="<?php echo esc_attr($art_piece->auction_end); ?>">
                            <span class="aih-time-label">Time Remaining</span>
                            <span class="aih-time-value">--:--:--</span>
                        </div>
                        <?php endif; ?>

                        <div class="aih-bid-form-single">
                            <div class="aih-field">
                                <label>Your Bid</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" id="bid-amount" data-min="<?php echo $min_bid; ?>" placeholder="$">
                            </div>
                            <button type="button" id="place-bid" class="aih-btn" data-id="<?php echo $art_piece->id; ?>">
                                Bid
                            </button>
                        </div>
                        <div id="bid-message" class="aih-message"></div>
                    </div>
                    <?php endif; ?>
                    
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
        </div>
    </main>

    <footer class="aih-footer">
        <p>&copy; <?php echo date('Y'); ?> Art in Heaven. All rights reserved.</p>
    </footer>
</div>

<!-- Scroll to Top Button -->
<button type="button" class="aih-scroll-top" id="aih-scroll-top" title="Scroll to top">↑</button>

<!-- Lightbox for image viewing -->
<div class="aih-lightbox" id="aih-lightbox">
    <button type="button" class="aih-lightbox-close" aria-label="Close">&times;</button>
    <button type="button" class="aih-lightbox-nav aih-lightbox-prev" aria-label="Previous image">‹</button>
    <button type="button" class="aih-lightbox-nav aih-lightbox-next" aria-label="Next image">›</button>
    <div class="aih-lightbox-content">
        <img src="" alt="" class="aih-lightbox-image" id="aih-lightbox-img">
    </div>
    <div class="aih-lightbox-dots"></div>
    <div class="aih-lightbox-counter"><span id="aih-lb-current">1</span> / <?php echo count($images); ?></div>
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
                // Icon stays the same, CSS handles the visual difference
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

    // Lightbox functionality
    var $lightbox = $('#aih-lightbox');
    var $lightboxImg = $('#aih-lightbox-img');
    var lightboxIndex = 0;

    // Image sources from PHP - include primary image as fallback
    var allImages = <?php
        $image_urls = array_map(function($img) { return $img->watermarked_url; }, $images);
        // If no images in array, use primary image
        if (empty($image_urls) && $primary_image) {
            $image_urls = array($primary_image);
        }
        echo json_encode($image_urls);
    ?>;
    // Additional fallback if PHP array is still empty
    if (!allImages || allImages.length === 0) {
        var mainSrc = $('#aih-main-image').attr('src');
        if (mainSrc) allImages = [mainSrc];
    }
    console.log('Lightbox images:', allImages.length, allImages);

    // Generate lightbox dots dynamically based on actual image count
    var $dotsContainer = $lightbox.find('.aih-lightbox-dots');
    $dotsContainer.empty();
    for (var i = 0; i < allImages.length; i++) {
        var activeClass = i === 0 ? ' active' : '';
        $dotsContainer.append('<span class="aih-lightbox-dot' + activeClass + '" data-index="' + i + '"></span>');
    }

    // Bind click events for dynamically created dots
    $dotsContainer.on('click', '.aih-lightbox-dot', function() {
        var index = parseInt($(this).data('index'));
        lightboxIndex = index;
        $lightboxImg.attr('src', allImages[index]);
        updateLightboxDots(index);
    });

    function updateLightboxDots(index) {
        $lightbox.find('.aih-lightbox-dot').removeClass('active');
        $lightbox.find('.aih-lightbox-dot[data-index="' + index + '"]').addClass('active');
    }

    function openLightbox(index) {
        console.log('Opening lightbox, index:', index, 'total images:', allImages.length, 'images:', allImages);
        if (allImages.length === 0) {
            console.log('No images available, cannot open lightbox');
            return;
        }
        // Ensure index is valid
        if (index < 0 || index >= allImages.length) {
            index = 0;
        }
        lightboxIndex = index;
        var imgSrc = allImages[index];
        console.log('Setting lightbox image to:', imgSrc);
        $lightboxImg.attr('src', imgSrc);
        $('#aih-lb-current').text(index + 1);
        updateLightboxDots(index);
        $lightbox.addClass('active');
        $('html').addClass('aih-lightbox-open');

        // Show/hide navigation based on image count
        if (allImages.length > 1) {
            console.log('Showing nav buttons');
            $lightbox.addClass('has-multiple');
        } else {
            console.log('Hiding nav buttons (single image)');
            $lightbox.removeClass('has-multiple');
        }
    }

    function closeLightbox() {
        $lightbox.removeClass('active has-multiple');
        $('html').removeClass('aih-lightbox-open');
        // Ensure body scroll is restored
        $('body').css('overflow', '');
    }

    function lightboxNav(direction) {
        lightboxIndex += direction;
        if (lightboxIndex < 0) lightboxIndex = allImages.length - 1;
        if (lightboxIndex >= allImages.length) lightboxIndex = 0;
        $lightboxImg.attr('src', allImages[lightboxIndex]);
        $('#aih-lb-current').text(lightboxIndex + 1);
        updateLightboxDots(lightboxIndex);
    }

    // Open lightbox on main image click
    $('#aih-main-image').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Main image clicked, currentImgIndex:', currentImgIndex, 'allImages:', allImages);
        openLightbox(currentImgIndex);
    });

    // Close lightbox
    $('.aih-lightbox-close').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeLightbox();
    });
    $lightbox.on('click', function(e) {
        // Only close if clicking directly on the lightbox background
        if (e.target === this) {
            closeLightbox();
        }
    });

    // Lightbox navigation
    $('.aih-lightbox-prev').on('click', function() {
        lightboxNav(-1);
    });
    $('.aih-lightbox-next').on('click', function() {
        lightboxNav(1);
    });

    // Keyboard navigation
    $(document).on('keydown', function(e) {
        if (!$lightbox.hasClass('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') lightboxNav(-1);
        if (e.key === 'ArrowRight') lightboxNav(1);
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
                $('.aih-single-image').find('.aih-badge').remove();
                $('.aih-single-image').prepend('<span class="aih-badge aih-badge-winning aih-badge-single">Winning</span>');
                $('#bid-amount').val('');
            } else {
                $msg.removeClass('success').addClass('error').text(r.data.message || 'Failed').show();
            }
            $btn.prop('disabled', false).removeClass('loading');
        });
    });
    
    $('#bid-amount').on('keypress', function(e) {
        if (e.which === 13) $('#place-bid').click();
    });

    // Countdown timer for single item page
    function updateCountdown() {
        $('.aih-time-remaining-single').each(function() {
            var $el = $(this);
            var endTime = $el.attr('data-end');
            if (!endTime) return;

            var end = new Date(endTime.replace(/-/g, '/')).getTime();
            var now = new Date().getTime();
            var diff = end - now;

            if (diff <= 0) {
                $el.find('.aih-time-value').text('Ended');
                $el.addClass('ended');
                return;
            }

            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((diff % (1000 * 60)) / 1000);

            var timeStr = '';
            if (days > 0) {
                timeStr = days + 'd ' + hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                timeStr = hours + 'h ' + minutes + 'm ' + seconds + 's';
            } else {
                timeStr = minutes + 'm ' + seconds + 's';
            }

            $el.find('.aih-time-value').text(timeStr);

            if (diff < 3600000) {
                $el.addClass('urgent');
            }
        });
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);

    // Scroll to Top functionality
    var $scrollBtn = $('#aih-scroll-top');
    $(window).on('scroll', function() {
        if ($(this).scrollTop() > 300) {
            $scrollBtn.addClass('visible');
        } else {
            $scrollBtn.removeClass('visible');
        }
    });

    $scrollBtn.on('click', function() {
        $('html, body').animate({ scrollTop: 0 }, 400);
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
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
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

.aih-nav-center {
    display: flex;
    align-items: center;
    gap: 12px;
}

.aih-nav-arrow {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 1px solid var(--color-border);
    border-radius: 50%;
    color: var(--color-primary);
    text-decoration: none;
    font-size: 14px;
    transition: all var(--transition);
    background: var(--color-surface);
}

.aih-nav-arrow:hover {
    border-color: var(--color-accent);
    color: var(--color-accent);
}

.aih-nav-arrow.disabled {
    opacity: 0.3;
    cursor: default;
    pointer-events: none;
}

.aih-piece-counter {
    font-size: 14px;
    color: var(--color-muted);
    min-width: 50px;
    text-align: center;
}

.aih-nav-spacer {
    width: 100px; /* Balance the back link */
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
    cursor: zoom-in;
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

/* Art ID Badge on single image - like gallery cards */
.aih-art-id-badge-single {
    position: absolute;
    bottom: 24px;
    left: 24px;
    padding: 8px 14px;
    font-size: 20px;
    font-weight: 700;
    font-family: var(--font-display);
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.95);
    color: var(--color-accent);
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    line-height: 1;
    z-index: 4;
}

/* Status Badge on single image - top left corner */
.aih-badge-single {
    position: absolute;
    top: 24px;
    left: 24px;
    z-index: 4;
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
    justify-content: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

/* Hide art ID from meta since it's now on the image */
.aih-single-meta .aih-art-id {
    display: none;
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

/* Bid form - horizontal compact layout */
.aih-bid-form-single {
    display: flex;
    flex-direction: row;
    align-items: flex-end;
    gap: 8px;
}

.aih-bid-form-single .aih-field {
    flex: 1;
    margin-bottom: 0;
}

.aih-bid-form-single .aih-field label {
    font-size: 10px;
    margin-bottom: 6px;
}

.aih-bid-form-single input {
    width: 100%;
    height: 38px;
    padding: 0 10px;
    font-size: 14px;
    box-sizing: border-box;
}

.aih-bid-form-single .aih-btn {
    flex-shrink: 0;
    width: 50px;
    height: 38px;
    padding: 0;
    font-size: 14px;
    line-height: 1;
    white-space: nowrap;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
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
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
    }

    .aih-back-link {
        width: 100%;
        text-align: center;
        order: 2;
        font-size: 13px;
    }

    .aih-nav-center {
        order: 1;
    }

    .aih-nav-spacer {
        display: none;
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

    /* Art ID badge mobile */
    .aih-art-id-badge-single {
        bottom: 16px;
        left: 16px;
        padding: 6px 10px;
        font-size: 16px;
    }

    /* Status badge mobile */
    .aih-badge-single {
        top: 16px;
        left: 16px;
    }

    /* Nav arrows mobile - consistent with other elements */
    .aih-img-nav-prev {
        left: 16px;
    }

    .aih-img-nav-next {
        right: 16px;
    }

    /* Bid section mobile scaling */
    .aih-bid-section {
        padding: 14px;
    }

    .aih-current-bid {
        margin-bottom: 14px;
    }

    .aih-current-bid .aih-bid-amount {
        font-size: 24px;
    }

    /* Keep bid form horizontal on mobile - properly sized for touch */
    .aih-bid-form-single {
        flex-direction: row;
        gap: 8px;
    }

    .aih-bid-form-single .aih-field {
        flex: 1 1 auto;
    }

    .aih-bid-form-single .aih-field label {
        font-size: 10px;
        margin-bottom: 4px;
    }

    .aih-bid-form-single input {
        height: 44px;
        padding: 0 10px;
        font-size: 16px;
    }

    .aih-bid-form-single .aih-btn {
        height: 44px;
        width: 60px;
        padding: 0;
        font-size: 16px;
        line-height: 1;
    }
}

/* Time remaining in single item page */
.aih-time-remaining-single {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 16px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    margin-bottom: 16px;
    text-align: center;
}

.aih-time-remaining-single .aih-time-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
}

.aih-time-remaining-single .aih-time-value {
    font-family: var(--font-display);
    font-size: 24px;
    font-weight: 600;
    color: var(--color-primary);
}

.aih-time-remaining-single.urgent .aih-time-value {
    color: var(--color-error);
}

.aih-time-remaining-single.ended .aih-time-value {
    color: var(--color-muted);
}

/* Single item image navigation arrows - ALWAYS VISIBLE */
.aih-img-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 32px;
    min-width: 32px;
    min-height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.95);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    font-size: 20px;
    font-weight: normal;
    line-height: 32px;
    padding: 0;
    text-align: center;
    color: var(--color-primary);
    z-index: 5;
    opacity: 1;
    transition: background 0.2s ease, transform 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

/* Adjust arrow position for better optical centering */
.aih-img-nav-prev {
    left: 24px;
    padding-right: 2px;
}

.aih-img-nav-next {
    right: 24px;
    padding-left: 2px;
}

.aih-img-nav:hover {
    background: var(--color-accent);
    color: white;
    transform: translateY(-50%) scale(1.1);
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
    min-width: 10px;
    min-height: 10px;
    aspect-ratio: 1;
    flex-shrink: 0;
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

/* Lightbox styles */
.aih-lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    min-width: 100vw;
    min-height: 100vh;
    margin: 0;
    padding: 0;
    background: rgba(0, 0, 0, 0.98);
    z-index: 999999;
    overflow: hidden;
    box-sizing: border-box;
}

.aih-lightbox.active {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

/* Ensure lightbox covers entire viewport even with scrollbars */
html.aih-lightbox-open,
html.aih-lightbox-open body {
    overflow: hidden !important;
}

.aih-lightbox-close {
    position: fixed;
    top: 20px;
    right: 20px;
    font-size: 40px;
    color: #fff;
    background: rgba(0, 0, 0, 0.5);
    border: none;
    cursor: pointer;
    z-index: 1000001;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s ease, transform 0.2s ease;
}

.aih-lightbox-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: scale(1.1);
}

.aih-lightbox-content {
    position: relative;
    max-width: 90vw;
    max-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.aih-lightbox-image {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.5);
}

/* Hide lightbox nav elements by default */
.aih-lightbox-nav,
.aih-lightbox-dots,
.aih-lightbox-counter {
    display: none !important;
}

/* Show nav elements when lightbox has multiple images */
.aih-lightbox.has-multiple .aih-lightbox-nav {
    display: flex !important;
}

.aih-lightbox.has-multiple .aih-lightbox-dots {
    display: flex !important;
}

/* Hide counter - using dots only */
.aih-lightbox.has-multiple .aih-lightbox-counter {
    display: none !important;
}

.aih-lightbox-nav {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    min-width: 44px;
    min-height: 44px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.8);
    font-size: 24px;
    font-weight: normal;
    line-height: 44px;
    text-align: center;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, transform 0.2s ease, color 0.2s ease;
    z-index: 1000000;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
}

.aih-lightbox-nav:hover {
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.4);
    transform: translateY(-50%) scale(1.1);
}

.aih-lightbox-prev {
    left: 20px;
    padding-right: 2px;
}

.aih-lightbox-next {
    right: 20px;
    padding-left: 2px;
}

@media (max-width: 768px) {
    .aih-lightbox-nav {
        width: 36px;
        height: 36px;
        min-width: 36px;
        min-height: 36px;
        font-size: 20px;
        line-height: 36px;
    }

    .aih-lightbox-prev {
        left: 10px;
    }

    .aih-lightbox-next {
        right: 10px;
    }
}

.aih-lightbox-counter {
    color: #fff;
    font-size: 14px;
    margin-top: 20px;
    opacity: 0.8;
}

/* Lightbox dots */
.aih-lightbox-dots {
    flex-direction: row;
    gap: 10px;
    margin-top: 16px;
    justify-content: center;
}

.aih-lightbox-dot {
    width: 10px;
    height: 10px;
    min-width: 10px;
    min-height: 10px;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.aih-lightbox-dot:hover {
    background: rgba(0, 0, 0, 0.7);
    border-color: rgba(255, 255, 255, 0.4);
}

.aih-lightbox-dot.active {
    background: rgba(0, 0, 0, 0.8);
    border-color: rgba(255, 255, 255, 0.6);
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
        width: 28px;
        height: 28px;
        min-width: 28px;
        min-height: 28px;
        font-size: 16px;
        line-height: 28px;
    }

    .aih-img-nav-prev {
        left: 12px;
        padding-right: 1px;
        padding-left: 0;
    }

    .aih-img-nav-next {
        right: 12px;
        padding-left: 1px;
        padding-right: 0;
    }

    .aih-image-dot {
        width: 8px;
        height: 8px;
        min-width: 8px;
        min-height: 8px;
    }

    .aih-thumbnails {
        display: none;
    }
}
</style>
