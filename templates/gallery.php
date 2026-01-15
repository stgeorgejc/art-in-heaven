<?php
/**
 * Gallery Template - Elegant Design
 */
if (!defined('ABSPATH')) exit;

$auth = AIH_Auth::get_instance();
$is_logged_in = $auth->is_logged_in();
$bidder = $is_logged_in ? $auth->get_current_bidder() : null;
$bidder_id = $is_logged_in ? $auth->get_current_bidder_id() : null;

$bidder_name = '';
if ($bidder) {
    $bidder_name = !empty($bidder->name_first) ? $bidder->name_first : 
                   (!empty($bidder->individual_name) ? explode(' ', $bidder->individual_name)[0] : $bidder_id);
}
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
<!-- Login Gate -->
<div class="aih-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo home_url(); ?>" class="aih-logo">Art in Heaven</a>
        </div>
    </header>
    <main class="aih-main">
        <div class="aih-login-card">
            <div class="aih-login-header">
                <div class="aih-ornament">✦</div>
                <h1>Welcome</h1>
                <p>Enter your confirmation code to access the auction</p>
            </div>
            <div class="aih-login-form">
                <div class="aih-field">
                    <label>Confirmation Code</label>
                    <input type="text" id="aih-gate-code" placeholder="XXXXXXXX" autocomplete="off">
                </div>
                <button type="button" id="aih-gate-verify" class="aih-btn">Sign In</button>
                <div id="aih-gate-message" class="aih-message"></div>
            </div>
        </div>
    </main>
</div>
<script>
jQuery(document).ready(function($) {
    $('#aih-gate-verify').on('click', function() {
        var code = $('#aih-gate-code').val().trim().toUpperCase();
        if (!code) { $('#aih-gate-message').addClass('error').text('Please enter your code').show(); return; }
        $(this).prop('disabled', true).addClass('loading');
        $.post(aihAjax.ajaxurl, {action:'aih_verify_code', nonce:aihAjax.nonce, code:code}, function(r) {
            if (r.success) { location.reload(); } 
            else { $('#aih-gate-message').addClass('error').text(r.data.message || 'Invalid code').show(); $('#aih-gate-verify').prop('disabled', false).removeClass('loading'); }
        });
    });
    $('#aih-gate-code').on('keypress', function(e) { if (e.which === 13) $('#aih-gate-verify').click(); })
        .on('input', function() { this.value = this.value.toUpperCase(); });
});
</script>
<?php 
// Include elegant styles
include(dirname(__FILE__) . '/../assets/css/elegant-theme.php');
return; 
endif;

// Get page URLs
global $wpdb;

// Gallery URL - try settings first, then search for shortcode, then current page
$gallery_page = get_option('aih_gallery_page', '');
if (!$gallery_page) {
    $gallery_page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[art_in_heaven_gallery%' LIMIT 1");
}
$gallery_url = $gallery_page ? get_permalink($gallery_page) : get_permalink();

$my_bids_url = '';
$my_bids_page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[art_in_heaven_my_bids%' LIMIT 1");
if ($my_bids_page) $my_bids_url = get_permalink($my_bids_page);

$checkout_url = '';
$checkout_page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[art_in_heaven_checkout%' LIMIT 1");
if ($checkout_page) $checkout_url = get_permalink($checkout_page);

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
    if (!empty($piece->artist) && !in_array($piece->artist, $artists)) $artists[] = $piece->artist;
    if (!empty($piece->medium) && !in_array($piece->medium, $mediums)) $mediums[] = $piece->medium;
}
sort($artists);
sort($mediums);

$cart_count = 0;
if ($is_logged_in) {
    $checkout = AIH_Checkout::get_instance();
    $cart_count = count($checkout->get_won_items($bidder_id));
}

$bid_increment = floatval(get_option('aih_bid_increment', 1));
?>

<div class="aih-page aih-gallery-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link aih-nav-active">Gallery</a>
                <?php if ($my_bids_url): ?>
                <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-nav-link">My Bids</a>
                <?php endif; ?>
            </nav>
            <div class="aih-header-actions">
                <?php if ($checkout_url && $cart_count > 0): ?>
                <a href="<?php echo esc_url($checkout_url); ?>" class="aih-cart-link">
                    <span class="aih-cart-icon">🛒</span>
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
                <h1>Collection</h1>
                <p class="aih-subtitle"><?php echo count($art_pieces); ?> pieces available</p>
            </div>
            <div class="aih-gallery-controls">
                <div class="aih-search-box">
                    <input type="text" id="aih-search" placeholder="Search collection..." class="aih-search-input">
                </div>
                <div class="aih-filter-group">
                    <select id="aih-filter-artist" class="aih-select">
                        <option value="">All Artists</option>
                        <?php foreach ($artists as $a): ?>
                        <option value="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="aih-filter-medium" class="aih-select">
                        <option value="">All Mediums</option>
                        <?php foreach ($mediums as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aih-view-toggle">
                    <button type="button" class="aih-view-btn active" data-view="grid" title="Grid View">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </button>
                    <button type="button" class="aih-view-btn" data-view="single" title="Single View">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <?php if (empty($art_pieces)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2>Coming Soon</h2>
            <p>Art pieces will be available here when the auction begins.</p>
        </div>
        <?php else: ?>
        <div class="aih-gallery-grid" id="aih-gallery">
            <?php foreach ($art_pieces as $piece): 
                $is_favorite = $favorites->is_favorite($bidder_id, $piece->id);
                $is_winning = $bid_model->is_bidder_winning($piece->id, $bidder_id);
                $current_bid = $bid_model->get_highest_bid_amount($piece->id);
                $has_bids = $current_bid > 0;
                $display_bid = $has_bids ? $current_bid : $piece->starting_bid;
                $min_bid = $has_bids ? $current_bid + $bid_increment : $piece->starting_bid;
                $images = $art_images->get_images($piece->id);
                $primary_image = !empty($images) ? $images[0]->watermarked_url : ($piece->watermarked_url ?: $piece->image_url);
                
                // Proper status calculation - check computed_status first, then calculate from dates
                $computed_status = isset($piece->computed_status) ? $piece->computed_status : null;
                if ($computed_status === 'ended') {
                    $is_ended = true;
                } elseif ($computed_status === 'active') {
                    $is_ended = false;
                } else {
                    // Fallback: calculate from status and dates
                    $is_ended = $piece->status === 'ended' || ($piece->auction_end && strtotime($piece->auction_end) <= time());
                }
                
                $status_class = '';
                $status_text = '';
                
                if ($is_ended) {
                    $status_class = 'ended';
                    $status_text = $is_winning ? 'Won' : 'Sold';
                } elseif ($is_winning) {
                    $status_class = 'winning';
                    $status_text = 'Winning';
                }
            ?>
            <article class="aih-card <?php echo $status_class; ?>" 
                     data-id="<?php echo $piece->id; ?>"
                     data-art-id="<?php echo esc_attr($piece->art_id); ?>"
                     data-title="<?php echo esc_attr($piece->title); ?>"
                     data-artist="<?php echo esc_attr($piece->artist); ?>"
                     data-medium="<?php echo esc_attr($piece->medium); ?>">
                
                <div class="aih-card-image">
                    <a href="?art_id=<?php echo $piece->id; ?>">
                        <?php if ($primary_image): ?>
                        <img src="<?php echo esc_url($primary_image); ?>" alt="<?php echo esc_attr($piece->title); ?>" loading="lazy">
                        <?php else: ?>
                        <div class="aih-placeholder">No Image</div>
                        <?php endif; ?>
                    </a>
                    
                    <span class="aih-art-id-badge"><?php echo esc_html($piece->art_id); ?></span>
                    
                    <?php if ($status_text): ?>
                    <div class="aih-badge aih-badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                    <?php endif; ?>
                    
                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo $piece->id; ?>">
                        <span class="aih-fav-icon"><?php echo $is_favorite ? '♥' : '♡'; ?></span>
                    </button>
                </div>
                
                <div class="aih-card-body">
                    <h3 class="aih-card-title">
                        <a href="?art_id=<?php echo $piece->id; ?>"><?php echo esc_html($piece->title); ?></a>
                    </h3>
                    <p class="aih-card-artist"><?php echo esc_html($piece->artist); ?></p>
                </div>
                
                <div class="aih-card-footer">
                    <div class="aih-bid-info">
                        <?php if ($has_bids): ?>
                        <span class="aih-bid-label">Bid Placed</span>
                        <span class="aih-bid-amount aih-bid-hidden"></span>
                        <?php else: ?>
                        <span class="aih-bid-label">Starting Bid</span>
                        <span class="aih-bid-amount">$<?php echo number_format($display_bid); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$is_ended): ?>
                    <div class="aih-bid-form">
                        <input type="number" class="aih-bid-input" min="<?php echo $min_bid; ?>" step="1" placeholder="Enter bid">
                        <button type="button" class="aih-bid-btn" data-id="<?php echo $piece->id; ?>">Bid</button>
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

<!-- Toast -->
<div id="aih-toast" class="aih-toast"></div>

<script>
jQuery(document).ready(function($) {
    // Logout
    $('#aih-logout').on('click', function() {
        $.post(aihAjax.ajaxurl, {action:'aih_logout', nonce:aihAjax.nonce}, function() {
            location.reload();
        });
    });
    
    // Search & Filter
    function filterCards() {
        var search = $('#aih-search').val().toLowerCase().trim();
        var artist = $('#aih-filter-artist').val();
        var medium = $('#aih-filter-medium').val();
        
        var visibleCount = 0;
        
        $('.aih-card').each(function() {
            var $card = $(this);
            var show = true;
            
            // Get data attributes
            var cardArtId = ($card.attr('data-art-id') || '').toLowerCase();
            var cardTitle = ($card.attr('data-title') || '').toLowerCase();
            var cardArtist = ($card.attr('data-artist') || '');
            var cardMedium = ($card.attr('data-medium') || '');
            
            // Search filter - check against art ID, title, and artist
            if (search) {
                var searchText = cardArtId + ' ' + cardTitle + ' ' + cardArtist.toLowerCase();
                if (searchText.indexOf(search) === -1) {
                    show = false;
                }
            }
            
            // Artist filter - exact match (case-insensitive)
            if (show && artist) {
                if (cardArtist.toLowerCase() !== artist.toLowerCase()) {
                    show = false;
                }
            }
            
            // Medium filter - exact match (case-insensitive)
            if (show && medium) {
                if (cardMedium.toLowerCase() !== medium.toLowerCase()) {
                    show = false;
                }
            }
            
            // Show or hide the card
            if (show) {
                $card.css('display', '');
                visibleCount++;
            } else {
                $card.css('display', 'none');
            }
        });
    }
    
    // Bind filter events
    $('#aih-search').on('input', function() {
        filterCards();
    });
    
    $('#aih-filter-artist, #aih-filter-medium').on('change', function() {
        filterCards();
    });
    
    // Favorite toggle
    $('.aih-fav-btn').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.data('id');
        
        $.post(aihAjax.ajaxurl, {action:'aih_toggle_favorite', nonce:aihAjax.nonce, art_piece_id:id}, function(r) {
            if (r.success) {
                $btn.toggleClass('active');
                $btn.find('.aih-fav-icon').text($btn.hasClass('active') ? '♥' : '♡');
            }
        });
    });
    
    // Place bid
    $('.aih-bid-btn').on('click', function() {
        var $btn = $(this);
        var $card = $btn.closest('.aih-card');
        var $input = $card.find('.aih-bid-input');
        var $msg = $card.find('.aih-bid-message');
        var id = $btn.data('id');
        var amount = parseInt($input.val());
        
        if (!amount || amount < 1) {
            $msg.text('Enter a valid bid').addClass('error').show();
            return;
        }
        
        $btn.prop('disabled', true).text('...');
        $msg.hide();
        
        $.post(aihAjax.ajaxurl, {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:id, bid_amount:amount}, function(r) {
            if (r.success) {
                $msg.removeClass('error').addClass('success').text('Bid placed!').show();
                $card.find('.aih-bid-amount').text('').addClass('aih-bid-hidden');
                $card.find('.aih-bid-label').text('Bid Placed');
                $card.addClass('winning');
                var $badge = $card.find('.aih-badge');
                if ($badge.length) {
                    $badge.removeClass('aih-badge-ended').addClass('aih-badge-winning').text('Winning');
                } else {
                    $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-winning">Winning</div>');
                }
                $input.val('').attr('placeholder', 'Enter bid');
                setTimeout(function() { $msg.fadeOut(); }, 2000);
            } else {
                $msg.removeClass('success').addClass('error').text(r.data.message || 'Bid failed').show();
            }
            $btn.prop('disabled', false).text('Bid');
        });
    });
    
    // Enter key to bid
    $('.aih-bid-input').on('keypress', function(e) {
        if (e.which === 13) $(this).closest('.aih-card').find('.aih-bid-btn').click();
    });
    
    // View toggle (grid vs single)
    var currentIndex = 0;
    var $cards = $('.aih-card');
    
    $('.aih-view-btn').on('click', function() {
        var view = $(this).data('view');
        $('.aih-view-btn').removeClass('active');
        $(this).addClass('active');
        
        if (view === 'single') {
            $('#aih-gallery').addClass('single-view');
            showSingleCard(currentIndex);
            $('.aih-single-nav').show();
        } else {
            $('#aih-gallery').removeClass('single-view');
            $('.aih-card').show();
            $('.aih-single-nav').hide();
        }
    });
    
    function showSingleCard(index) {
        $cards = $('.aih-card:not([style*="display: none"])');
        if ($cards.length === 0) return;
        
        if (index < 0) index = $cards.length - 1;
        if (index >= $cards.length) index = 0;
        currentIndex = index;
        
        $cards.hide();
        $cards.eq(index).show();
        
        // Update counter
        $('.aih-single-counter').text((index + 1) + ' / ' + $cards.length);
    }
    
    // Add navigation for single view if not exists
    if ($('.aih-single-nav').length === 0) {
        $('#aih-gallery').after('<div class="aih-single-nav" style="display:none;"><button type="button" class="aih-nav-prev">← Previous</button><span class="aih-single-counter"></span><button type="button" class="aih-nav-next">Next →</button></div>');
    }
    
    $(document).on('click', '.aih-nav-prev', function() {
        showSingleCard(currentIndex - 1);
    });
    
    $(document).on('click', '.aih-nav-next', function() {
        showSingleCard(currentIndex + 1);
    });
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>
