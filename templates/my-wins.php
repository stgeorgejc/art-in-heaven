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
                <p>Please sign in to view your collection</p>
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
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link">Gallery</a>
                <?php if ($my_bids_url): ?>
                <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-nav-link">My Bids</a>
                <?php endif; ?>
                <a href="#" class="aih-nav-link aih-nav-active">My Collection</a>
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
                <h1>My Collection</h1>
                <p class="aih-subtitle"><?php echo count($purchases); ?> pieces purchased</p>
            </div>
        </div>

        <?php if (empty($purchases)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2>No Purchases Yet</h2>
            <p>Art pieces you've won and paid for will appear here.</p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn" style="display: inline-block; width: auto; margin-top: 24px;">Browse Gallery</a>
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
                            <span class="aih-placeholder-text">No Image</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    <?php if ($item->image_url && $item->art_id): ?>
                    <span class="aih-art-id-badge"><?php echo esc_html($item->art_id); ?></span>
                    <?php endif; ?>
                    <div class="aih-badge aih-badge-owned">Owned</div>
                    <?php if ($item->pickup_status === 'picked_up'): ?>
                    <div class="aih-badge aih-badge-pickup">Picked Up</div>
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
                        <span class="aih-win-label">Purchase Price</span>
                        <span class="aih-win-amount">$<?php echo number_format($item->winning_bid); ?></span>
                    </div>
                    <div class="aih-win-order">
                        <span class="aih-win-label">Order</span>
                        <span class="aih-win-order-num"><?php echo esc_html($item->order_number); ?></span>
                    </div>
                </div>
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
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>

<style>
/* My Wins Page Styles */
.aih-mywins-page .aih-main {
    max-width: 100% !important;
    width: 100% !important;
}

.aih-wins-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
    width: 100%;
}

.aih-win-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
}

.aih-win-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.aih-win-image {
    position: relative;
    aspect-ratio: 1/1;
    overflow: hidden;
    background: var(--color-bg-alt);
}

.aih-win-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.aih-win-image a {
    display: block;
    width: 100%;
    height: 100%;
}

.aih-win-image .aih-art-id-badge {
    position: absolute;
    bottom: 10px;
    left: 10px;
    padding: 5px 8px;
    font-size: clamp(14px, 4vw, 18px);
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

.aih-badge-owned {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 5px 10px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 2px;
    background: var(--color-accent);
    color: white;
}

.aih-badge-pickup {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 10px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 2px;
    background: var(--color-success);
    color: white;
}

.aih-win-body {
    padding: 16px;
    flex: 1;
}

.aih-win-title {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 500;
    line-height: 1.3;
    margin-bottom: 4px;
    /* Ellipsis for long titles - 2 lines max */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aih-win-title a {
    color: var(--color-primary);
    text-decoration: none;
}

.aih-win-title a:hover {
    color: var(--color-accent);
}

.aih-win-artist {
    font-size: 14px;
    color: var(--color-muted);
    margin-bottom: 8px;
    /* Ellipsis for long artist names - 1 line */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.aih-win-details {
    font-size: 12px;
    color: var(--color-muted);
}

.aih-win-footer {
    padding: 16px;
    border-top: 1px solid var(--color-border);
    background: var(--color-bg);
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.aih-win-price,
.aih-win-order {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.aih-win-label {
    font-size: 9px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--color-muted);
}

.aih-win-amount {
    font-family: var(--font-display);
    font-size: 20px;
    font-weight: 600;
    color: var(--color-primary);
}

.aih-win-order-num {
    font-size: 12px;
    color: var(--color-secondary);
    font-weight: 500;
}

/* Mobile adjustments */
@media (max-width: 600px) {
    .aih-wins-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
    }

    .aih-win-image .aih-art-id-badge {
        bottom: 8px;
        left: 8px;
        padding: 4px 6px;
        font-size: clamp(12px, 3.5vw, 16px);
    }

    .aih-badge-owned,
    .aih-badge-pickup {
        top: 8px;
        padding: 4px 8px;
        font-size: 9px;
    }

    .aih-badge-pickup {
        right: 8px;
    }

    .aih-win-body {
        padding: 12px;
    }

    .aih-win-title {
        font-size: 14px;
        -webkit-line-clamp: 1;
    }

    .aih-win-artist {
        font-size: 12px;
    }

    .aih-win-details {
        font-size: 11px;
    }

    .aih-win-footer {
        padding: 12px;
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }

    .aih-win-amount {
        font-size: 16px;
    }

    .aih-win-order-num {
        font-size: 11px;
    }
}

@media (max-width: 400px) {
    .aih-wins-grid {
        grid-template-columns: 1fr;
    }
}
</style>
