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
<?php if (!$is_logged_in):
    $sub_heading = __('Please sign in to complete your purchase', 'art-in-heaven');
    include AIH_PLUGIN_DIR . 'templates/partials/login-gate.php';
    return;
endif;

// Handle PushPay redirect - check for payment token
$payment_result = null;
if (!empty($_GET['paymentToken'])) {
    $pushpay = AIH_Pushpay_API::get_instance();
    $payment_data = $pushpay->get_payment_by_token(sanitize_text_field($_GET['paymentToken']));
    if (!is_wp_error($payment_data)) {
        $payment_result = 'success';
    } else {
        $payment_result = 'error';
    }
} elseif (isset($_GET['sr']) && !isset($_GET['paymentToken'])) {
    // Source reference present but no payment token = payment was cancelled/failed
    $payment_result = 'cancelled';
}

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
<script>(function(){var t=localStorage.getItem('aih-theme');if(t==='dark'){document.currentScript.parentElement.classList.add('dark-mode');}})();</script>
    <?php $active_page = 'checkout'; $cart_count = 0; include AIH_PLUGIN_DIR . 'templates/partials/header.php'; ?>

    <main class="aih-main">
        <?php if ($payment_result === 'success'): ?>
        <div class="aih-payment-banner aih-payment-success">
            <span class="aih-payment-icon">&#10003;</span>
            <div>
                <strong><?php _e('Payment Successful', 'art-in-heaven'); ?></strong>
                <p><?php _e('Thank you! Your payment has been received. You can view your order details below.', 'art-in-heaven'); ?></p>
            </div>
        </div>
        <?php elseif ($payment_result === 'cancelled'): ?>
        <div class="aih-payment-banner aih-payment-cancelled">
            <span class="aih-payment-icon">!</span>
            <div>
                <strong><?php _e('Payment Not Completed', 'art-in-heaven'); ?></strong>
                <p><?php _e('It looks like the payment was not completed. You can try again below.', 'art-in-heaven'); ?></p>
            </div>
        </div>
        <?php elseif ($payment_result === 'error'): ?>
        <div class="aih-payment-banner aih-payment-error">
            <span class="aih-payment-icon">&#10007;</span>
            <div>
                <strong><?php _e('Payment Issue', 'art-in-heaven'); ?></strong>
                <p><?php _e('There was a problem verifying your payment. Please contact support if you were charged.', 'art-in-heaven'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="aih-gallery-header">
            <div class="aih-gallery-title">
                <h1><?php _e('Checkout', 'art-in-heaven'); ?></h1>
                <p class="aih-subtitle"><?php printf(esc_html__('%d items won', 'art-in-heaven'), count($won_items)); ?></p>
            </div>
        </div>

        <?php if (empty($won_items)): ?>
        <div class="aih-empty-state">
            <div class="aih-ornament">✦</div>
            <h2><?php _e('No Items to Checkout', 'art-in-heaven'); ?></h2>
            <p><?php _e("You haven't won any auctions yet.", 'art-in-heaven'); ?></p>
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-btn aih-btn--inline"><?php _e('Browse Gallery', 'art-in-heaven'); ?></a>
        </div>
        <?php else: ?>
        <div class="aih-checkout-layout">
            <div class="aih-checkout-items">
                <div class="aih-checkout-items-header">
                    <h2 class="aih-section-heading"><?php _e('Won Items', 'art-in-heaven'); ?></h2>
                    <?php if (count($won_items) > 1): ?>
                    <a href="#" id="aih-select-toggle" class="aih-select-toggle"><?php _e('Deselect All', 'art-in-heaven'); ?></a>
                    <?php endif; ?>
                </div>
                <?php foreach ($won_items as $item):
                    // Support both id and art_piece_id property names
                    $art_piece_id = isset($item->art_piece_id) ? $item->art_piece_id : (isset($item->id) ? $item->id : 0);
                    $images = $art_images->get_images($art_piece_id);
                    $image_url = !empty($images) ? $images[0]->watermarked_url : (isset($item->watermarked_url) ? $item->watermarked_url : (isset($item->image_url) ? $item->image_url : ''));
                    // Support both winning_bid and winning_amount property names
                    $winning_amount = isset($item->winning_bid) ? $item->winning_bid : (isset($item->winning_amount) ? $item->winning_amount : 0);
                ?>
                <div class="aih-checkout-item">
                    <label class="aih-checkout-checkbox">
                        <input type="checkbox" class="aih-item-check" data-id="<?php echo esc_attr($art_piece_id); ?>" data-amount="<?php echo esc_attr($winning_amount); ?>" checked>
                        <span class="aih-checkmark"></span>
                    </label>
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
                        <span><?php _e('Winning Bid', 'art-in-heaven'); ?></span>
                        <strong>$<?php echo number_format($winning_amount); ?></strong>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="aih-checkout-summary" data-tax-rate="<?php echo esc_attr($tax_rate); ?>">
                <h3><?php _e('Order Summary', 'art-in-heaven'); ?></h3>
                <div class="aih-summary-row">
                    <span><?php _e('Subtotal', 'art-in-heaven'); ?></span>
                    <span id="aih-subtotal">$<?php echo number_format($subtotal); ?></span>
                </div>
                <?php if ($tax_rate > 0): ?>
                <div class="aih-summary-row">
                    <span><?php printf(esc_html__('Tax (%s%%)', 'art-in-heaven'), esc_html($tax_rate)); ?></span>
                    <span id="aih-tax">$<?php echo number_format($tax, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="aih-summary-row aih-summary-total">
                    <span><?php _e('Total', 'art-in-heaven'); ?></span>
                    <span id="aih-total">$<?php echo number_format($total, 2); ?></span>
                </div>
                <button type="button" id="aih-create-order" class="aih-btn" style="margin-top: 24px;">
                    <?php _e('Proceed to Payment', 'art-in-heaven'); ?>
                </button>
                <div id="aih-checkout-msg" class="aih-message" style="display:none; margin-top: 12px;"></div>
                <p class="aih-checkout-note"><?php _e("You'll be redirected to our secure payment portal.", 'art-in-heaven'); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($orders)): ?>
        <div class="aih-previous-orders">
            <h2 class="aih-section-heading" style="margin-top: 48px;"><?php _e('Previous Orders', 'art-in-heaven'); ?></h2>
            <div class="aih-orders-grid">
                <?php foreach ($orders as $order): ?>
                <div class="aih-order-card aih-order-clickable" data-order="<?php echo esc_attr($order->order_number); ?>">
                    <div class="aih-order-header">
                        <strong><?php echo esc_html($order->order_number); ?></strong>
                        <span class="aih-order-status aih-status-<?php echo esc_attr($order->payment_status); ?>">
                            <?php echo esc_html(ucfirst($order->payment_status)); ?>
                        </span>
                    </div>
                    <div class="aih-order-details">
                        <p><?php echo intval($order->item_count); ?> <?php echo $order->item_count != 1 ? __('items', 'art-in-heaven') : __('item', 'art-in-heaven'); ?> &bull; $<?php echo number_format($order->total); ?></p>
                        <p class="aih-order-date"><?php echo esc_html(wp_date('M j, Y', strtotime($order->created_at))); ?></p>
                    </div>
                    <div class="aih-order-view-link">
                        <span><?php _e('View Details', 'art-in-heaven'); ?> &rarr;</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Details Modal -->
        <div id="aih-order-modal" class="aih-modal" role="dialog" aria-modal="true" aria-labelledby="aih-modal-title" style="display: none;">
            <div class="aih-modal-backdrop" aria-hidden="true"></div>
            <div class="aih-modal-content">
                <div class="aih-modal-header">
                    <h3 id="aih-modal-title"><?php _e('Order Details', 'art-in-heaven'); ?></h3>
                    <button type="button" class="aih-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="aih-modal-body" id="aih-modal-body">
                    <div class="aih-loading"><?php _e('Loading...', 'art-in-heaven'); ?></div>
                </div>
            </div>
        </div>
    </main>

    <footer class="aih-footer">
        <p><?php printf(esc_html__('&copy; %s Art in Heaven. All rights reserved.', 'art-in-heaven'), wp_date('Y')); ?></p>
    </footer>
</div>

<script>
jQuery(document).ready(function($) {
    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    $('#aih-logout').on('click', function() {
        $.post(aihApiUrl('logout'), {action:'aih_logout', nonce:aihAjax.nonce}, function() { location.reload(); });
    });

    // Item selection logic
    var taxRate = parseFloat($('.aih-checkout-summary').data('tax-rate')) || 0;

    function recalcTotals() {
        var subtotal = 0;
        var count = 0;
        $('.aih-item-check').each(function() {
            var $item = $(this).closest('.aih-checkout-item');
            if (this.checked) {
                subtotal += parseFloat($(this).data('amount')) || 0;
                count++;
                $item.removeClass('deselected');
            } else {
                $item.addClass('deselected');
            }
        });
        var tax = subtotal * (taxRate / 100);
        var total = subtotal + tax;
        $('#aih-subtotal').text('$' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
        if ($('#aih-tax').length) {
            $('#aih-tax').text('$' + tax.toFixed(2));
        }
        $('#aih-total').text('$' + total.toFixed(2));
        var $btn = $('#aih-create-order');
        $btn.prop('disabled', count === 0);
        // Update toggle text
        var allCount = $('.aih-item-check').length;
        if ($('#aih-select-toggle').length) {
            $('#aih-select-toggle').text(count === allCount ? 'Deselect All' : 'Select All');
        }
    }

    $('.aih-item-check').on('change', recalcTotals);

    $('#aih-select-toggle').on('click', function(e) {
        e.preventDefault();
        var allChecked = $('.aih-item-check:checked').length === $('.aih-item-check').length;
        $('.aih-item-check').prop('checked', !allChecked);
        recalcTotals();
    });

    $('#aih-create-order').on('click', function() {
        var ids = [];
        $('.aih-item-check:checked').each(function() {
            ids.push($(this).data('id'));
        });
        if (ids.length === 0) return;
        var $btn = $(this).prop('disabled', true).addClass('loading');
        $.post(aihApiUrl('create-order'), {action:'aih_create_order', nonce:aihAjax.nonce, 'art_piece_ids[]': ids}, function(r) {
            if (r.success && r.data.pushpay_url) {
                window.location.href = r.data.pushpay_url;
            } else {
                $('#aih-checkout-msg').addClass('error').text(r.data.message || 'Error creating order. Payment URL could not be generated.').show();
                $btn.prop('disabled', false).removeClass('loading');
            }
        }).fail(function() {
            $('#aih-checkout-msg').addClass('error').text('Connection error. Please try again.').show();
            $btn.prop('disabled', false).removeClass('loading');
        });
    });

    // Order details modal with caching
    var orderCache = {};
    $('.aih-order-clickable').on('click', function() {
        lastFocusedElement = this;
        var orderNumber = $(this).data('order');
        var $modal = $('#aih-order-modal');
        var $body = $('#aih-modal-body');

        $modal.show();
        $modal.find('.aih-modal-close').focus();
        $('#aih-modal-title').text('Order ' + orderNumber);

        // Use cached data if available
        if (orderCache[orderNumber]) {
            $body.html(orderCache[orderNumber]);
            return;
        }

        $body.html('<div class="aih-loading">Loading order details...</div>');

        $.post(aihApiUrl('order-details'), {
            action: 'aih_get_order_details',
            nonce: aihAjax.nonce,
            order_number: orderNumber
        }).done(function(r) {
            if (r.success) {
                var data = r.data;
                var html = '<div class="aih-order-modal-info">';
                html += '<div class="aih-order-meta">';
                var safeStatus = escapeHtml(data.payment_status);
                var statusClass = ['paid', 'pending', 'refunded', 'cancelled'].indexOf(safeStatus) > -1 ? safeStatus : 'pending';
                html += '<span class="aih-order-status aih-status-' + statusClass + '">' + safeStatus.charAt(0).toUpperCase() + safeStatus.slice(1) + '</span>';
                if (data.pickup_status === 'picked_up') {
                    html += ' <span class="aih-pickup-badge">Picked Up</span>';
                }
                html += '<span class="aih-order-date">' + escapeHtml(data.created_at) + '</span>';
                html += '</div>';
                if (data.payment_reference) {
                    html += '<div class="aih-order-txn"><span class="aih-txn-label">Transaction ID:</span> <span class="aih-txn-value">' + escapeHtml(data.payment_reference) + '</span></div>';
                }
                html += '</div>';

                html += '<div class="aih-order-items-list">';
                html += '<h4>Items Purchased</h4>';

                if (data.items && data.items.length > 0) {
                    data.items.forEach(function(item) {
                        html += '<div class="aih-order-item-row">';
                        html += '<div class="aih-order-item-image">';
                        if (item.image_url) {
                            html += '<img src="' + escapeHtml(item.image_url) + '" alt="' + escapeHtml(item.title || '') + '">';
                        }
                        if (item.art_id) {
                            html += '<span class="aih-art-id-badge">' + escapeHtml(item.art_id) + '</span>';
                        }
                        html += '</div>';
                        html += '<div class="aih-order-item-info">';
                        html += '<h5>' + escapeHtml(item.title || 'Untitled') + '</h5>';
                        html += '<p>' + escapeHtml(item.artist || '') + '</p>';
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
                orderCache[orderNumber] = html;
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Unknown error';
                $body.html('<p class="aih-error">Error: ' + escapeHtml(msg) + '</p>');
            }
        }).fail(function(xhr) {
            $body.html('<p class="aih-error">Request failed: ' + escapeHtml(xhr.status + ' ' + xhr.statusText) + '</p>');
        });
    });

    // Close modal and restore focus
    var lastFocusedElement;
    $('.aih-modal-close, .aih-modal-backdrop').on('click', function() {
        $('#aih-order-modal').hide();
        if (lastFocusedElement) lastFocusedElement.focus();
    });

    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#aih-order-modal').hide();
            if (lastFocusedElement) lastFocusedElement.focus();
        }
    });
});
</script>


