<?php
/**
 * Gallery Template - Elegant Design
 */
if (!defined('ABSPATH')) exit;

// Use consolidated helper for bidder info
$bidder_info = AIH_Template_Helper::get_current_bidder_info();
$is_logged_in = $bidder_info['is_logged_in'];
$bidder = $bidder_info['bidder'];
$bidder_id = $bidder_info['id'];
$bidder_name = $bidder_info['name'];
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

// Get page URLs using consolidated helper
$gallery_url = AIH_Template_Helper::get_gallery_url();
$my_bids_url = AIH_Template_Helper::get_my_bids_url();
$checkout_url = AIH_Template_Helper::get_checkout_url();

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
        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1>Collection</h1>
                <p class="aih-subtitle"><?php echo count($art_pieces); ?> pieces available</p>
            </div>
            <div class="aih-gallery-controls">
                <div class="aih-search-box">
                    <input type="text" id="aih-search" placeholder="Search collection..." class="aih-search-input">
                </div>
                <button type="button" class="aih-filter-toggle" id="aih-filter-toggle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    <span>Sort & Filter</span>
                    <svg class="aih-filter-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>
                <div class="aih-view-toggle">
                    <button type="button" class="aih-view-btn active" data-view="grid" title="Grid View">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </button>
                    <button type="button" class="aih-view-btn" data-view="single" title="Single View">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                    </button>
                </div>
            </div>
            <!-- Desktop: inline panel appears here in the header flow -->
            <div class="aih-filter-panel" id="aih-filter-panel">
            <div class="aih-filter-panel-header">
                <span class="aih-filter-panel-title">Sort & Filter</span>
                <button type="button" class="aih-filter-panel-close" id="aih-filter-close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            <div class="aih-filter-panel-content">
                <div class="aih-filter-section">
                    <label class="aih-filter-label">Sort By</label>
                    <select id="aih-sort" class="aih-select">
                        <option value="default">Default</option>
                        <option value="title-asc">Title: A to Z</option>
                        <option value="title-desc">Title: Z to A</option>
                        <option value="artist-asc">Artist: A to Z</option>
                        <option value="artist-desc">Artist: Z to A</option>
                    </select>
                </div>
                <div class="aih-filter-section">
                    <label class="aih-filter-label">Artist</label>
                    <select id="aih-filter-artist" class="aih-select">
                        <option value="">All Artists</option>
                        <?php foreach ($artists as $a): ?>
                        <option value="<?php echo esc_attr($a); ?>"><?php echo esc_html($a); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aih-filter-section">
                    <label class="aih-filter-label">Medium</label>
                    <select id="aih-filter-medium" class="aih-select">
                        <option value="">All Mediums</option>
                        <?php foreach ($mediums as $m): ?>
                        <option value="<?php echo esc_attr($m); ?>"><?php echo esc_html($m); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="aih-filter-section aih-filter-favorites-section" style="display: none;">
                    <label class="aih-filter-label">Show</label>
                    <select id="aih-filter-favorites" class="aih-select">
                        <option value="">All Items</option>
                        <option value="favorites">Favorites Only</option>
                    </select>
                </div>
            </div>
            </div>
        </div>

        <!-- Mobile: overlay backdrop -->
        <div class="aih-filter-overlay" id="aih-filter-overlay"></div>

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
                    $is_ended = $piece->status === 'ended' || (!empty($piece->auction_end) && strtotime($piece->auction_end) && strtotime($piece->auction_end) <= current_time('timestamp'));
                }

                $status_class = '';
                $status_text = '';

                if ($is_ended) {
                    $status_class = 'ended';
                    $status_text = 'Ended';
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

                <div class="aih-card-image" data-favorite="<?php echo $is_favorite ? '1' : '0'; ?>">
                    <?php if ($primary_image): ?>
                    <a href="?art_id=<?php echo $piece->id; ?>">
                        <img src="<?php echo esc_url($primary_image); ?>" alt="<?php echo esc_attr($piece->title); ?>" loading="lazy">
                    </a>
                    <?php else: ?>
                    <a href="?art_id=<?php echo $piece->id; ?>" class="aih-placeholder-link">
                        <div class="aih-placeholder">
                            <span class="aih-placeholder-id"><?php echo esc_html($piece->art_id); ?></span>
                            <span class="aih-placeholder-text">No Image</span>
                        </div>
                    </a>
                    <?php endif; ?>

                    <span class="aih-art-id-badge"><?php echo esc_html($piece->art_id); ?></span>

                    <?php if ($status_text): ?>
                    <div class="aih-badge aih-badge-<?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                    <?php endif; ?>

                    <button type="button" class="aih-fav-btn <?php echo $is_favorite ? 'active' : ''; ?>" data-id="<?php echo $piece->id; ?>">
                        <span class="aih-fav-icon">♥</span>
                    </button>

                    <?php if (!$is_ended && $piece->auction_end && !empty($piece->show_end_time)): ?>
                    <div class="aih-time-remaining" data-end="<?php echo esc_attr($piece->auction_end); ?>">
                        <span class="aih-time-value">--:--:--</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="aih-card-body">
                    <h3 class="aih-card-title">
                        <a href="?art_id=<?php echo $piece->id; ?>"><?php echo esc_html($piece->title); ?></a>
                    </h3>
                    <p class="aih-card-artist"><?php echo esc_html($piece->artist); ?></p>
                    <?php if (!$has_bids): ?>
                    <p class="aih-card-bid">
                        <span class="aih-bid-label">Starting Bid</span>
                        <span class="aih-bid-amount">$<?php echo number_format($piece->starting_bid, 2); ?></span>
                    </p>
                    <?php endif; ?>
                </div>

                <?php if (!$is_ended): ?>
                <div class="aih-card-footer">
                    <div class="aih-bid-form">
                        <input type="text" inputmode="numeric" pattern="[0-9]*" class="aih-bid-input" data-min="<?php echo $min_bid; ?>" placeholder="$">
                        <button type="button" class="aih-bid-btn" data-id="<?php echo $piece->id; ?>">Bid</button>
                    </div>
                </div>
                <?php endif; ?>
                
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

<!-- Scroll to Top Button -->
<button type="button" class="aih-scroll-top" id="aih-scroll-top" title="Scroll to top">↑</button>

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
        var favoritesOnly = $('#aih-filter-favorites').val() === 'favorites';

        var visibleCount = 0;

        $('.aih-card').each(function() {
            var $card = $(this);
            var show = true;

            // Get data attributes
            var cardArtId = ($card.attr('data-art-id') || '').toLowerCase();
            var cardTitle = ($card.attr('data-title') || '').toLowerCase();
            var cardArtist = ($card.attr('data-artist') || '');
            var cardMedium = ($card.attr('data-medium') || '');
            var isFavorite = $card.find('.aih-card-image').attr('data-favorite') === '1';

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

            // Favorites filter
            if (show && favoritesOnly && !isFavorite) {
                show = false;
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

    // Check if any favorites exist and show/hide favorites filter section
    function updateFavoritesFilterVisibility() {
        var hasFavorites = $('.aih-card-image[data-favorite="1"]').length > 0;
        if (hasFavorites) {
            $('.aih-filter-favorites-section').show();
        } else {
            $('.aih-filter-favorites-section').hide();
            $('#aih-filter-favorites').val('');
        }
    }
    updateFavoritesFilterVisibility();

    // Sort & Filter Panel Toggle
    function isMobile() {
        return window.innerWidth <= 600;
    }

    function openFilterPanel() {
        $('#aih-filter-toggle').addClass('active');
        $('#aih-filter-panel').addClass('open');
        if (isMobile()) {
            $('#aih-filter-overlay').addClass('open');
            $('body').addClass('filter-open');
        }
    }

    function closeFilterPanel() {
        $('#aih-filter-toggle').removeClass('active');
        $('#aih-filter-panel').removeClass('open');
        $('#aih-filter-overlay').removeClass('open');
        $('body').removeClass('filter-open');
    }

    $('#aih-filter-toggle').on('click', function() {
        if ($('#aih-filter-panel').hasClass('open')) {
            closeFilterPanel();
        } else {
            openFilterPanel();
        }
    });

    $('#aih-filter-close, #aih-filter-overlay').on('click', function() {
        closeFilterPanel();
    });

    // Sort gallery cards
    function sortCards() {
        var sortBy = $('#aih-sort').val();
        if (sortBy === 'default') return;

        var $grid = $('#aih-gallery');
        var $cards = $grid.children('.aih-card');

        $cards.sort(function(a, b) {
            var $a = $(a);
            var $b = $(b);
            var aVal, bVal;

            switch (sortBy) {
                case 'title-asc':
                    aVal = ($a.attr('data-title') || '').toLowerCase();
                    bVal = ($b.attr('data-title') || '').toLowerCase();
                    return aVal.localeCompare(bVal);
                case 'title-desc':
                    aVal = ($a.attr('data-title') || '').toLowerCase();
                    bVal = ($b.attr('data-title') || '').toLowerCase();
                    return bVal.localeCompare(aVal);
                case 'artist-asc':
                    aVal = ($a.attr('data-artist') || '').toLowerCase();
                    bVal = ($b.attr('data-artist') || '').toLowerCase();
                    return aVal.localeCompare(bVal);
                case 'artist-desc':
                    aVal = ($a.attr('data-artist') || '').toLowerCase();
                    bVal = ($b.attr('data-artist') || '').toLowerCase();
                    return bVal.localeCompare(aVal);
                default:
                    return 0;
            }
        });

        $grid.append($cards);
    }

    // Bind filter events
    $('#aih-search').on('input keyup change', function() {
        filterCards();
    });

    $('#aih-filter-artist, #aih-filter-medium, #aih-filter-favorites').on('change', function() {
        filterCards();
    });

    // Bind sort event
    $('#aih-sort').on('change', function() {
        sortCards();
    });

    // Run filter on page load
    filterCards();
    
    // Favorite toggle
    $('.aih-fav-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var id = $btn.data('id');

        $.post(aihAjax.ajaxurl, {action:'aih_toggle_favorite', nonce:aihAjax.nonce, art_piece_id:id}, function(r) {
            if (r.success) {
                $btn.toggleClass('active');
                // Icon stays the same, CSS handles the visual difference
                // Update data attribute for filtering
                var $cardImage = $btn.closest('.aih-card-image');
                $cardImage.attr('data-favorite', $btn.hasClass('active') ? '1' : '0');
                // Update favorites filter visibility
                updateFavoritesFilterVisibility();
                filterCards();
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
                $card.addClass('winning');
                var $badge = $card.find('.aih-badge');
                if ($badge.length) {
                    $badge.removeClass('aih-badge-ended').addClass('aih-badge-winning').text('Winning');
                } else {
                    $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-winning">Winning</div>');
                }
                $input.val('');
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
    
    // View toggle (grid vs single column)
    $('.aih-view-btn').on('click', function() {
        var view = $(this).data('view');
        $('.aih-view-btn').removeClass('active');
        $(this).addClass('active');

        if (view === 'single') {
            $('#aih-gallery').addClass('single-view');
        } else {
            $('#aih-gallery').removeClass('single-view');
        }
    });

    // Countdown timer functionality
    function updateCountdowns() {
        $('.aih-time-remaining').each(function() {
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
                timeStr = days + 'd ' + hours + 'h';
            } else if (hours > 0) {
                timeStr = hours + 'h ' + minutes + 'm';
            } else {
                timeStr = minutes + 'm ' + seconds + 's';
            }

            $el.find('.aih-time-value').text(timeStr);

            // Add urgency class if less than 1 hour
            if (diff < 3600000) {
                $el.addClass('urgent');
            }
        });
    }

    // Update countdowns immediately and every second
    updateCountdowns();
    setInterval(updateCountdowns, 1000);

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
