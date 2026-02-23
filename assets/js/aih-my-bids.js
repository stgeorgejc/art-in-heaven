/**
 * Art in Heaven - My Bids Page JavaScript
 */
jQuery(document).ready(function($) {
    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    $('#aih-logout').on('click', function() {
        $.post(aihApiUrl('logout'), {action:'aih_logout', nonce:aihAjax.nonce}, function() { location.reload(); });
    });

    // Order details modal
    var lastFocusedElement;
    $('.aih-order-clickable').on('click', function() {
        lastFocusedElement = this;
        var orderNumber = $(this).data('order');
        var $modal = $('#aih-order-modal');
        var $body = $('#aih-modal-body');

        $modal.show();
        $modal.find('.aih-modal-close').focus();
        $body.html('<div class="aih-loading">Loading order details...</div>');
        $('#aih-modal-title').text('Order ' + orderNumber);

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
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Unknown error';
                $body.html('<p class="aih-error">Error: ' + escapeHtml(msg) + '</p>');
            }
        }).fail(function(xhr) {
            $body.html('<p class="aih-error">Request failed: ' + escapeHtml(xhr.status + ' ' + xhr.statusText) + '</p>');
        });
    });

    // Close modal and restore focus
    $('.aih-modal-close, .aih-modal-backdrop').on('click', function() {
        $('#aih-order-modal').hide();
        if (lastFocusedElement) lastFocusedElement.focus();
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape' && $('#aih-order-modal').is(':visible')) {
            $('#aih-order-modal').hide();
            if (lastFocusedElement) lastFocusedElement.focus();
        }
    });

    $('.aih-bid-btn').on('click', function() {
        var $btn = $(this);
        var $card = $btn.closest('.aih-card');
        var $input = $card.find('.aih-bid-input');
        var $msg = $card.find('.aih-bid-message');
        var id = $btn.data('id');
        var amount = parseInt(($input.val() || '').replace(/[^0-9]/g, ''), 10);

        if (!amount || amount < 1) { $msg.addClass('error').text(aihAjax.strings.enterValidBid).show(); return; }

        var formatted = '$' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        window.aihConfirmBid(formatted, function() {
            $btn.prop('disabled', true).text('...');
            $msg.hide();

            $.post(aihApiUrl('bid'), {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:id, bid_amount:amount}, function(r) {
                if (r.success) {
                    if (navigator.vibrate) navigator.vibrate(100);
                    $msg.removeClass('error').addClass('success').text(aihAjax.strings.bidPlaced).show();
                    $btn.prop('disabled', true);
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $msg.removeClass('success').addClass('error').text(r.data.message || 'Failed').show();
                    $btn.prop('disabled', false).text('Bid');
                }
            }).fail(function() {
                $msg.removeClass('success').addClass('error').text(aihAjax.strings.connectionError).show();
                $btn.prop('disabled', false).text('Bid');
            });
        });
    });

    $('.aih-bid-input').on('keypress', function(e) {
        if (e.which === 13) $(this).closest('.aih-card').find('.aih-bid-btn').click();
    });

    // Countdown: update badges when auctions end
    var serverTime = parseInt($('#aih-mybids-wrapper').data('server-time')) || new Date().getTime();
    var timeOffset = serverTime - new Date().getTime();

    function updateMyBidsCountdowns() {
        // Update visible countdown timers
        $('.aih-time-remaining').each(function() {
            var $el = $(this);
            var endTime = $el.attr('data-end');
            if (!endTime) return;

            var end = new Date(endTime.replace(/-/g, '/')).getTime();
            var now = new Date().getTime() + timeOffset;
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

            if (diff < 3600000) {
                $el.addClass('urgent');
            }
        });

        // Update badges and hide forms when auctions end
        $('.aih-card[data-end]').each(function() {
            var $card = $(this);
            if ($card.hasClass('ended') || $card.hasClass('won') || $card.hasClass('paid')) return;

            var endTime = $card.attr('data-end');
            var end = new Date(endTime.replace(/-/g, '/')).getTime();
            var now = new Date().getTime() + timeOffset;
            var diff = end - now;

            if (diff <= 0) {
                var wasWinning = $card.hasClass('winning');
                var newStatus = wasWinning ? 'won' : 'ended';
                var newText = wasWinning ? 'Won' : 'Ended';

                $card.removeClass('winning outbid').addClass(newStatus);

                var $badge = $card.find('.aih-badge');
                if ($badge.length) {
                    $badge.attr('class', 'aih-badge aih-badge-' + newStatus).text(newText);
                }

                // Hide bid form and countdown
                $card.find('.aih-bid-form').hide();
                $card.find('.aih-time-remaining').addClass('ended').find('.aih-time-value').text('Ended');
            }
        });
    }

    updateMyBidsCountdowns();
    setInterval(updateMyBidsCountdowns, 1000);

    // === Live bid status polling ===
    var pollTimer = null;

    function hasActiveAuctions() {
        var hasActive = false;
        $('.aih-card').each(function() {
            var $card = $(this);
            if (!$card.hasClass('ended') && !$card.hasClass('won') && !$card.hasClass('paid')) {
                hasActive = true;
                return false;
            }
        });
        return hasActive;
    }

    // Smart polling: calculate interval based on soonest-ending auction
    function getSmartInterval() {
        var soonest = Infinity;
        $('.aih-card[data-end]').each(function() {
            var $c = $(this);
            if ($c.hasClass('ended') || $c.hasClass('won') || $c.hasClass('paid')) return;
            var endMs = new Date($c.attr('data-end').replace(/-/g, '/')).getTime();
            var remaining = endMs - (Date.now() + timeOffset);
            if (remaining > 0 && remaining < soonest) soonest = remaining;
        });

        if (soonest < 60000) return 2000;       // < 1 min: poll every 2s
        if (soonest < 300000) return 5000;       // < 5 min: poll every 5s
        if (soonest < 3600000) return 10000;     // < 1 hour: poll every 10s
        return 30000;                             // > 1 hour: poll every 30s
    }

    // Expose for push notification handler to trigger immediate refresh
    window.aihPollStatus = pollStatus;

    function pollStatus() {
        if (window.aihSSEConnected) return; // SSE handles real-time updates
        if (!aihAjax.isLoggedIn || !hasActiveAuctions()) return;

        var ids = [];
        $('.aih-card').each(function() {
            var id = $(this).data('id');
            if (id) ids.push(id);
        });
        if (ids.length === 0) return;

        $.post(aihApiUrl('poll-status'), {
            action: 'aih_poll_status',
            nonce: aihAjax.nonce,
            art_piece_ids: ids
        }, function(r) {
            if (!r.success || !r.data || !r.data.items) return;
            var items = r.data.items;

            $.each(items, function(id, info) {
                if (info.status === 'ended') return; // countdown handles ended transitions
                var $card = $('.aih-card[data-id="' + id + '"]');
                if (!$card.length) return;
                // Skip cards already transitioned to ended/won/paid
                if ($card.hasClass('ended') || $card.hasClass('won') || $card.hasClass('paid')) return;

                var wasWinning = $card.hasClass('winning');

                if (info.is_winning && !wasWinning) {
                    // Became winning
                    $card.removeClass('outbid').addClass('winning');
                    var $badge = $card.find('.aih-badge');
                    $badge.attr('class', 'aih-badge aih-badge-winning').text('Winning');
                    // Hide bid form when winning
                    $card.find('.aih-bid-form').hide();
                } else if (!info.is_winning && wasWinning) {
                    // Got outbid
                    $card.removeClass('winning').addClass('outbid');
                    var $badge = $card.find('.aih-badge');
                    $badge.attr('class', 'aih-badge aih-badge-outbid').text('Outbid');
                    // Show bid form when outbid
                    var $form = $card.find('.aih-bid-form');
                    if ($form.length) {
                        $form.show();
                    } else {
                        // Create bid form if it doesn't exist
                        $card.find('.aih-card-footer').append(
                            '<div class="aih-bid-form">' +
                            '<input type="text" inputmode="numeric" pattern="[0-9]*" class="aih-bid-input" data-min="' + info.min_bid + '" placeholder="$">' +
                            '<button type="button" class="aih-bid-btn" data-id="' + id + '">Bid</button>' +
                            '</div>'
                        );
                    }
                }

                // Update min bid on input
                var $input = $card.find('.aih-bid-input');
                if ($input.length) {
                    $input.attr('data-min', info.min_bid).data('min', info.min_bid);
                }
            });
        }).fail(function() {
            // Network error or server error — polling continues via setTimeout chain
        });
    }

    function startPolling() {
        if (pollTimer) clearTimeout(pollTimer);
        var interval = document.hidden ? 60000 : getSmartInterval();
        pollTimer = setTimeout(function() {
            pollStatus();
            startPolling();
        }, interval);
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    // First poll after 3 seconds, then use smart intervals
    setTimeout(function() {
        pollStatus();
        startPolling();
    }, 3000);

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
            pollTimer = setTimeout(pollStatus, 60000);
        } else {
            stopPolling();
            pollStatus();
            startPolling();
        }
    });
});
