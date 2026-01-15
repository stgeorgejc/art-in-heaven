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
$checkout_url = AIH_Template_Helper::get_checkout_url();
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
                <p>Please sign in to view your bids</p>
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

// Get user's bids - only show successful (valid) bids
$bid_model = new AIH_Bid();
$favorites = new AIH_Favorites();
$art_images = new AIH_Art_Images();
$all_bids = $bid_model->get_bidder_bids($bidder_id);
$bid_increment = floatval(get_option('aih_bid_increment', 1));

// Filter to only show valid bids (exclude failed bids like 'too_low')
$my_bids = array();
foreach ($all_bids as $bid) {
    $status = isset($bid->bid_status) ? $bid->bid_status : 'valid';
    if ($status === 'valid') {
        $my_bids[] = $bid;
    }
}

$cart_count = 0;
$checkout = AIH_Checkout::get_instance();
$cart_count = count($checkout->get_won_items($bidder_id));
?>

<div class="aih-page aih-mybids-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link">Gallery</a>
                <a href="#" class="aih-nav-link aih-nav-active">My Bids</a>
            </nav>
            <div class="aih-header-actions">
                <?php if ($checkout_url && $cart_count > 0): ?>
                <a href="<?php echo esc_url($checkout_url); ?>" class="aih-cart-link">
                    <span>🛒</span>
                    <span class="aih-cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php endif; ?>
                <div class="aih-user-menu">
                    <span class="aih-user-name"><?php echo esc_html($bidder_name); ?></span>
                    <button type="button" class="aih-logout-btn" id="aih-logout">Sign Out</button>
                </div>
            </div>
        </div>
    </header>

    <main class="aih-main">
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1>My Bids</h1>
                <p class="aih-subtitle"><?php echo count($my_bids); ?> items</p>
            </div>
        </div>

        <?php if (empty($my_bids)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2>No Bids Yet</h2>
            <p>Browse the gallery and place your first bid!</p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn" style="display: inline-block; width: auto; margin-top: 24px;">View Gallery</a>
        </div>
        <?php else: ?>
        <div class="aih-gallery-grid">
            <?php foreach ($my_bids as $bid):
                $is_winning = ($bid->is_winning == 1);
                $bid_status = isset($bid->computed_status) ? $bid->computed_status : (isset($bid->auction_status) ? $bid->auction_status : 'active');
                $is_ended = $bid_status === 'ended' || ($bid->auction_end && strtotime($bid->auction_end) <= time());
                $images = $art_images->get_images($bid->art_piece_id);
                $bid_title = isset($bid->title) ? $bid->title : (isset($bid->art_title) ? $bid->art_title : '');
                $image_url = !empty($images) ? $images[0]->watermarked_url : (isset($bid->watermarked_url) ? $bid->watermarked_url : (isset($bid->image_url) ? $bid->image_url : ''));
                $highest_bid = $bid_model->get_highest_bid_amount($bid->art_piece_id);
                $min_bid = $highest_bid + $bid_increment;

                $status_class = $is_ended ? 'ended' : ($is_winning ? 'winning' : 'outbid');
                $status_text = $is_ended ? ($is_winning ? 'Won' : 'Sold') : ($is_winning ? 'Winning' : 'Outbid');
            ?>
            <article class="aih-card <?php echo $status_class; ?>" data-id="<?php echo $bid->art_piece_id; ?>">
                <div class="aih-card-image">
                    <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo $bid->art_piece_id; ?>">
                        <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($bid_title); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="aih-placeholder">No Image</div>
                        <?php endif; ?>
                    </a>
                    <span class="aih-art-id-badge"><?php echo esc_html(isset($bid->art_id) ? $bid->art_id : ''); ?></span>
                    <div class="aih-badge aih-badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                </div>

                <div class="aih-card-body">
                    <h3 class="aih-card-title">
                        <a href="<?php echo esc_url($gallery_url); ?>?art_id=<?php echo $bid->art_piece_id; ?>"><?php echo esc_html($bid_title); ?></a>
                    </h3>
                    <p class="aih-card-artist"><?php echo esc_html(isset($bid->artist) ? $bid->artist : ''); ?></p>
                </div>
                
                <div class="aih-card-footer">
                    <div class="aih-bid-info aih-bid-info-centered">
                        <div>
                            <span class="aih-bid-label">Your Bid</span>
                            <span class="aih-bid-amount">$<?php echo number_format($bid->bid_amount); ?></span>
                        </div>
                    </div>

                    <?php if (!$is_ended && !$is_winning): ?>
                    <div class="aih-bid-form">
                        <input type="number" class="aih-bid-input" min="<?php echo $min_bid; ?>" step="1" placeholder="Enter bid">
                        <button type="button" class="aih-bid-btn" data-id="<?php echo $bid->art_piece_id; ?>">Bid</button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="aih-bid-message"></div>
            </article>
            <?php endforeach; ?>
        </div>
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
    
    $('.aih-bid-btn').on('click', function() {
        var $btn = $(this);
        var $card = $btn.closest('.aih-card');
        var $input = $card.find('.aih-bid-input');
        var $msg = $card.find('.aih-bid-message');
        var id = $btn.data('id');
        var amount = parseInt($input.val());
        
        if (!amount) { $msg.addClass('error').text('Enter a bid amount').show(); return; }
        
        $btn.prop('disabled', true).text('...');
        $msg.hide();
        
        $.post(aihAjax.ajaxurl, {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:id, bid_amount:amount}, function(r) {
            if (r.success) {
                $msg.removeClass('error').addClass('success').text('Bid placed!').show();
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                $msg.removeClass('success').addClass('error').text(r.data.message || 'Failed').show();
                $btn.prop('disabled', false).text('Bid');
            }
        });
    });
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>

<style>
.aih-card.outbid { border-color: var(--color-error); }
.aih-badge-outbid { background: var(--color-error); color: white; }
.aih-bid-info-centered {
    justify-content: center;
    text-align: center;
}
.aih-bid-info-centered > div {
    text-align: center;
}
</style>
