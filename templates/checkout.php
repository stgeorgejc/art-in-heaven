<?php
/**
 * Checkout Page - Elegant Design
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
                <p>Please sign in to complete your purchase</p>
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

// Get won items
$checkout = AIH_Checkout::get_instance();
$won_items = $checkout->get_won_items($bidder_id);
$orders = $checkout->get_bidder_orders($bidder_id);
$art_images = new AIH_Art_Images();

$subtotal = 0;
foreach ($won_items as $item) {
    // Support both winning_bid and winning_amount property names
    $winning_amount = isset($item->winning_bid) ? $item->winning_bid : (isset($item->winning_amount) ? $item->winning_amount : 0);
    $subtotal += $winning_amount;
}
$tax_rate = floatval(get_option('aih_tax_rate', 0));
$tax = $subtotal * ($tax_rate / 100);
$total = $subtotal + $tax;
?>

<div class="aih-page aih-checkout-page">
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
                <h1>Checkout</h1>
                <p class="aih-subtitle"><?php echo count($won_items); ?> items won</p>
            </div>
        </div>

        <?php if (empty($won_items)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2>No Items to Checkout</h2>
            <p>You haven't won any auctions yet.</p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn" style="display: inline-block; width: auto; margin-top: 24px;">Browse Gallery</a>
        </div>
        <?php else: ?>
        <div class="aih-checkout-layout">
            <div class="aih-checkout-items">
                <h2 style="font-family: var(--font-display); font-size: 24px; margin-bottom: 24px;">Won Items</h2>
                <?php foreach ($won_items as $item):
                    // Support both id and art_piece_id property names
                    $art_piece_id = isset($item->art_piece_id) ? $item->art_piece_id : (isset($item->id) ? $item->id : 0);
                    $images = $art_images->get_images($art_piece_id);
                    $image_url = !empty($images) ? $images[0]->watermarked_url : (isset($item->watermarked_url) ? $item->watermarked_url : (isset($item->image_url) ? $item->image_url : ''));
                    // Support both winning_bid and winning_amount property names
                    $winning_amount = isset($item->winning_bid) ? $item->winning_bid : (isset($item->winning_amount) ? $item->winning_amount : 0);
                ?>
                <div class="aih-checkout-item">
                    <div class="aih-checkout-item-image">
                        <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(isset($item->title) ? $item->title : ''); ?>">
                        <span class="aih-art-id-badge"><?php echo esc_html(isset($item->art_id) ? $item->art_id : ''); ?></span>
                        <?php else: ?>
                        <div class="aih-checkout-placeholder">
                            <span><?php echo esc_html(isset($item->art_id) ? $item->art_id : ''); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="aih-checkout-item-details">
                        <h4><?php echo esc_html(isset($item->title) ? $item->title : ''); ?></h4>
                        <p><?php echo esc_html(isset($item->artist) ? $item->artist : ''); ?></p>
                    </div>
                    <div class="aih-checkout-item-price">
                        <span>Winning Bid</span>
                        <strong>$<?php echo number_format($winning_amount); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="aih-checkout-summary">
                <h3>Order Summary</h3>
                <div class="aih-summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($subtotal); ?></span>
                </div>
                <?php if ($tax > 0): ?>
                <div class="aih-summary-row">
                    <span>Tax (<?php echo esc_html($tax_rate); ?>%)</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="aih-summary-row aih-summary-total">
                    <span>Total</span>
                    <span>$<?php echo number_format($total, 2); ?></span>
                </div>
                <button type="button" id="aih-create-order" class="aih-btn" style="margin-top: 24px;">
                    Proceed to Payment
                </button>
                <p class="aih-checkout-note">You'll be redirected to our secure payment portal.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($orders)): ?>
        <div class="aih-previous-orders">
            <h2 style="font-family: var(--font-display); font-size: 24px; margin: 48px 0 24px;">Previous Orders</h2>
            <div class="aih-orders-grid">
                <?php foreach ($orders as $order): ?>
                <div class="aih-order-card aih-order-clickable" data-order="<?php echo esc_attr($order->order_number); ?>">
                    <div class="aih-order-header">
                        <strong><?php echo esc_html($order->order_number); ?></strong>
                        <span class="aih-order-status aih-status-<?php echo $order->payment_status; ?>">
                            <?php echo ucfirst($order->payment_status); ?>
                        </span>
                    </div>
                    <div class="aih-order-details">
                        <p><?php echo $order->item_count; ?> items • $<?php echo number_format($order->total); ?></p>
                        <p class="aih-order-date"><?php echo date('M j, Y', strtotime($order->created_at)); ?></p>
                    </div>
                    <div class="aih-order-view-link">
                        <span>View Details →</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Details Modal -->
        <div id="aih-order-modal" class="aih-modal" style="display: none;">
            <div class="aih-modal-backdrop"></div>
            <div class="aih-modal-content">
                <div class="aih-modal-header">
                    <h3 id="aih-modal-title">Order Details</h3>
                    <button type="button" class="aih-modal-close">&times;</button>
                </div>
                <div class="aih-modal-body" id="aih-modal-body">
                    <div class="aih-loading">Loading...</div>
                </div>
            </div>
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

    $('#aih-create-order').on('click', function() {
        var $btn = $(this).prop('disabled', true).addClass('loading');
        $.post(aihAjax.ajaxurl, {action:'aih_create_order', nonce:aihAjax.nonce}, function(r) {
            if (r.success && r.data.pushpay_url) {
                window.location.href = r.data.pushpay_url;
            } else {
                alert(r.data.message || 'Error creating order. Payment URL could not be generated.');
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Order details modal
    $('.aih-order-clickable').on('click', function() {
        var orderNumber = $(this).data('order');
        var $modal = $('#aih-order-modal');
        var $body = $('#aih-modal-body');

        $modal.show();
        $body.html('<div class="aih-loading">Loading order details...</div>');
        $('#aih-modal-title').text('Order ' + orderNumber);

        $.post(aihAjax.ajaxurl, {
            action: 'aih_get_order_details',
            nonce: aihAjax.nonce,
            order_number: orderNumber
        }).done(function(r) {
            if (r.success) {
                var data = r.data;
                var html = '<div class="aih-order-modal-info">';
                html += '<div class="aih-order-meta">';
                html += '<span class="aih-order-status aih-status-' + data.payment_status + '">' + data.payment_status.charAt(0).toUpperCase() + data.payment_status.slice(1) + '</span>';
                if (data.pickup_status === 'picked_up') {
                    html += ' <span class="aih-pickup-badge">Picked Up</span>';
                }
                html += '<span class="aih-order-date">' + data.created_at + '</span>';
                html += '</div>';
                if (data.payment_reference) {
                    html += '<div class="aih-order-txn"><span class="aih-txn-label">Transaction ID:</span> <span class="aih-txn-value">' + data.payment_reference + '</span></div>';
                }
                html += '</div>';

                html += '<div class="aih-order-items-list">';
                html += '<h4>Items Purchased</h4>';

                if (data.items && data.items.length > 0) {
                    data.items.forEach(function(item) {
                        html += '<div class="aih-order-item-row">';
                        html += '<div class="aih-order-item-image">';
                        if (item.image_url) {
                            html += '<img src="' + item.image_url + '" alt="' + (item.title || '') + '">';
                        }
                        if (item.art_id) {
                            html += '<span class="aih-art-id-badge">' + item.art_id + '</span>';
                        }
                        html += '</div>';
                        html += '<div class="aih-order-item-info">';
                        html += '<h5>' + (item.title || 'Untitled') + '</h5>';
                        html += '<p>' + (item.artist || '') + '</p>';
                        html += '</div>';
                        html += '<div class="aih-order-item-price">$' + item.winning_bid.toLocaleString() + '</div>';
                        html += '</div>';
                    });
                }

                html += '</div>';

                html += '<div class="aih-order-totals">';
                html += '<div class="aih-order-total-row"><span>Subtotal</span><span>$' + data.subtotal.toLocaleString() + '</span></div>';
                if (data.tax > 0) {
                    html += '<div class="aih-order-total-row"><span>Tax</span><span>$' + data.tax.toFixed(2) + '</span></div>';
                }
                html += '<div class="aih-order-total-row aih-order-total-final"><span>Total</span><span>$' + data.total.toFixed(2) + '</span></div>';
                html += '</div>';

                $body.html(html);
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Unknown error';
                $body.html('<p class="aih-error">Error: ' + msg + '</p>');
            }
        }).fail(function(xhr) {
            $body.html('<p class="aih-error">Request failed: ' + xhr.status + ' ' + xhr.statusText + '</p>');
        });
    });

    // Close modal
    $('.aih-modal-close, .aih-modal-backdrop').on('click', function() {
        $('#aih-order-modal').hide();
    });

    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#aih-order-modal').hide();
        }
    });
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>

<style>
.aih-checkout-layout {
    display: grid;
    grid-template-columns: 1fr minmax(350px, 500px);
    gap: 48px;
    align-items: start;
    width: 100%;
}

@media (max-width: 900px) {
    .aih-checkout-layout {
        grid-template-columns: 1fr;
    }
}

.aih-checkout-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    margin-bottom: 16px;
}

.aih-checkout-item-image {
    position: relative;
    width: 100px;
    height: 100px;
    flex-shrink: 0;
    background: var(--color-bg-alt);
    display: flex;
    align-items: center;
    justify-content: center;
}

.aih-checkout-item-image .aih-art-id-badge {
    position: absolute;
    bottom: 6px;
    left: 6px;
    padding: 3px 6px;
    font-size: 12px;
    font-weight: 700;
    font-family: var(--font-display);
    letter-spacing: 0.5px;
    background: rgba(255, 255, 255, 0.95);
    color: var(--color-accent);
    border-radius: 3px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
    line-height: 1;
    z-index: 2;
}

.aih-checkout-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.aih-checkout-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--color-bg-alt) 0%, var(--color-border) 100%);
}

.aih-checkout-placeholder span {
    font-family: var(--font-display);
    font-size: 18px;
    font-weight: 600;
    color: var(--color-accent);
}

.aih-checkout-item-details {
    flex: 1;
    min-width: 0;
}

.aih-checkout-item-details h4 {
    font-family: var(--font-display);
    font-size: 18px;
    margin: 4px 0;
}

.aih-checkout-item-details p {
    color: var(--color-muted);
    font-size: 14px;
}

.aih-checkout-item-price {
    text-align: right;
}

.aih-checkout-item-price span {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--color-muted);
    margin-bottom: 4px;
}

.aih-checkout-item-price strong {
    font-family: var(--font-display);
    font-size: 22px;
}

.aih-checkout-summary {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    padding: 32px;
    position: sticky;
    top: 100px;
    min-width: 320px;
}

.aih-checkout-summary h3 {
    font-family: var(--font-display);
    font-size: 22px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
}

.aih-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    padding: 12px 0;
    font-size: 15px;
    gap: 12px;
    flex-wrap: wrap;
}

.aih-summary-row span:first-child {
    flex-shrink: 0;
}

.aih-summary-row span:last-child {
    text-align: right;
    word-break: break-word;
}

.aih-summary-total {
    font-size: 18px;
    font-weight: 600;
    border-top: 1px solid var(--color-border);
    margin-top: 12px;
    padding-top: 16px;
}

.aih-summary-total span:last-child {
    font-family: var(--font-display);
}

.aih-checkout-note {
    text-align: center;
    font-size: 13px;
    color: var(--color-muted);
    margin-top: 16px;
}

.aih-orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.aih-order-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    padding: 20px;
}

.aih-order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.aih-order-status {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 4px 10px;
    border-radius: 2px;
}

.aih-status-paid { background: #e8f5e9; color: var(--color-success); }
.aih-status-pending { background: #fff3e0; color: #e65100; }

.aih-order-details p {
    font-size: 14px;
    margin: 0;
}

.aih-order-date {
    color: var(--color-muted);
    margin-top: 4px !important;
}

@media (max-width: 900px) {
    .aih-checkout-layout {
        grid-template-columns: 1fr;
        gap: 32px;
    }

    .aih-checkout-summary {
        position: static;
        max-width: 100%;
    }
}

@media (max-width: 600px) {
    .aih-checkout-page .aih-main {
        padding: 16px;
    }

    .aih-checkout-item {
        flex-direction: column;
        padding: 16px;
    }

    .aih-checkout-item-image {
        width: 100%;
        height: 180px;
    }

    .aih-checkout-item-image .aih-art-id-badge {
        bottom: 8px;
        left: 8px;
        padding: 4px 8px;
        font-size: 14px;
    }

    .aih-checkout-item-details {
        padding: 12px 0;
    }

    .aih-checkout-item-details h4 {
        font-size: 16px;
    }

    .aih-checkout-item-price {
        text-align: left;
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        padding-top: 12px;
        border-top: 1px solid var(--color-border);
    }

    .aih-checkout-item-price span {
        margin-bottom: 0;
    }

    .aih-checkout-item-price strong {
        font-size: 20px;
    }

    .aih-checkout-summary {
        padding: 20px;
    }

    .aih-checkout-summary h3 {
        font-size: 18px;
        margin-bottom: 16px;
        padding-bottom: 12px;
    }

    .aih-summary-row {
        font-size: 14px;
        padding: 10px 0;
    }

    .aih-summary-total {
        font-size: 16px;
    }

    .aih-checkout-layout {
        gap: 24px;
    }
}

/* Clickable Order Cards */
.aih-order-clickable {
    cursor: pointer;
    transition: all 0.2s ease;
}

.aih-order-clickable:hover {
    border-color: var(--color-accent);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.aih-order-view-link {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--color-border);
}

.aih-order-view-link span {
    font-size: 13px;
    color: var(--color-accent);
    font-weight: 500;
}

/* Modal Styles */
.aih-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.aih-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
}

.aih-modal-content {
    position: relative;
    background: var(--color-surface);
    border-radius: 8px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}

.aih-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--color-border);
}

.aih-modal-header h3 {
    font-family: var(--font-display);
    font-size: 22px;
    font-weight: 500;
    margin: 0;
}

.aih-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--color-muted);
    padding: 0;
    line-height: 1;
}

.aih-modal-close:hover {
    color: var(--color-primary);
}

.aih-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.aih-loading {
    text-align: center;
    padding: 40px;
    color: var(--color-muted);
}

.aih-order-modal-info {
    margin-bottom: 20px;
}

.aih-order-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.aih-order-meta .aih-order-date {
    color: var(--color-muted);
    font-size: 13px;
    margin: 0 !important;
}

.aih-pickup-badge {
    background: #e3f2fd;
    color: #1565c0;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 4px 10px;
    border-radius: 2px;
}

.aih-order-items-list {
    margin-bottom: 20px;
}

.aih-order-items-list h4 {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 500;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border);
}

.aih-order-item-row {
    display: flex;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
    align-items: center;
}

.aih-order-item-row:last-child {
    border-bottom: none;
}

.aih-order-item-image {
    position: relative;
    width: 70px;
    height: 70px;
    flex-shrink: 0;
    background: var(--color-bg-alt);
    border-radius: 4px;
    overflow: hidden;
}

.aih-order-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.aih-order-item-image .aih-art-id-badge {
    position: absolute;
    bottom: 4px;
    left: 4px;
    padding: 2px 4px;
    font-size: 10px;
    font-weight: 700;
    font-family: var(--font-display);
    background: rgba(255, 255, 255, 0.95);
    color: var(--color-accent);
    border-radius: 2px;
    line-height: 1;
}

.aih-order-item-info {
    flex: 1;
    min-width: 0;
}

.aih-order-item-info h5 {
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 500;
    margin: 0 0 4px 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.aih-order-item-info p {
    font-size: 13px;
    color: var(--color-muted);
    margin: 0;
}

.aih-order-item-price {
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 600;
    color: var(--color-primary);
    flex-shrink: 0;
}

.aih-order-totals {
    background: var(--color-bg);
    padding: 16px;
    border-radius: 4px;
}

.aih-order-total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 14px;
}

.aih-order-total-final {
    border-top: 1px solid var(--color-border);
    margin-top: 8px;
    padding-top: 12px;
    font-size: 16px;
    font-weight: 600;
}

.aih-order-total-final span:last-child {
    font-family: var(--font-display);
}

.aih-error {
    color: var(--color-error);
    text-align: center;
    padding: 20px;
}

@media (max-width: 600px) {
    .aih-modal {
        padding: 10px;
    }

    .aih-modal-content {
        max-height: 95vh;
    }

    .aih-modal-header {
        padding: 16px 20px;
    }

    .aih-modal-header h3 {
        font-size: 18px;
    }

    .aih-modal-body {
        padding: 16px;
    }

    .aih-order-item-row {
        gap: 12px;
    }

    .aih-order-item-image {
        width: 60px;
        height: 60px;
    }

    .aih-order-item-info h5 {
        font-size: 14px;
    }

    .aih-order-item-price {
        font-size: 14px;
    }
}
</style>
