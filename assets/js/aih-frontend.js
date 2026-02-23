/**
 * Art in Heaven - Frontend JavaScript
 * Silent Auction Frontend JavaScript
 * Version: 0.9.7
 */

(function($) {
    'use strict';
    
    // API URL helper: use clean /api/ endpoint when available, fall back to admin-ajax.php
    window.aihApiUrl = function(endpoint) {
        var base = (aihAjax.apiurl || '').replace(/\/$/, '');
        return base ? (base + '/' + endpoint) : aihAjax.ajaxurl;
    };

    // Toast notification helper — auto-inject element if missing
    if (!$('#aih-toast').length) {
        $('body').append('<div id="aih-toast" class="aih-toast"></div>');
    }

    function showToast(message, type = 'info') {
        var $toast = $('#aih-toast');
        $toast.removeClass('show success error').text(message);
        
        if (type === 'success') {
            $toast.addClass('success');
        } else if (type === 'error') {
            $toast.addClass('error');
        }
        
        $toast.addClass('show');
        
        setTimeout(function() {
            $toast.removeClass('show');
        }, 4000);
    }
    
    // Format time remaining
    function formatTimeRemaining(seconds) {
        if (seconds <= 0) {
            return aihAjax.strings.auctionEnded || 'Ended';
        }
        
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;
        
        if (days > 0) {
            return days + 'd ' + hours + 'h';
        } else if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (minutes > 0) {
            return minutes + 'm ' + secs + 's';
        } else {
            return secs + 's';
        }
    }
    
    // Update all countdowns
    function updateCountdowns() {
        $('[data-seconds]').each(function() {
            var $el = $(this);
            var seconds = parseInt($el.attr('data-seconds'), 10);
            if (isNaN(seconds)) return;

            if (seconds > 0) {
                seconds--;
                $el.attr('data-seconds', seconds);
                
                var $countdown = $el.find('.aih-countdown');
                if ($countdown.length === 0) {
                    $countdown = $el;
                }
                
                $countdown.text(formatTimeRemaining(seconds));
                
                // Add urgency class when less than 1 hour
                if (seconds < 3600 && seconds > 0) {
                    $el.closest('.aih-art-card, .aih-single-item').addClass('ending-soon');
                }
                
                // Mark as ended
                if (seconds <= 0) {
                    $countdown.text('Ended');
                    $el.closest('.aih-art-card, .aih-single-item').addClass('ended');
                }
            }
        });
    }
    
    // Initialize countdown timer, pausing when tab is hidden
    var countdownInterval = setInterval(updateCountdowns, 1000);
    updateCountdowns();
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(countdownInterval);
        } else {
            updateCountdowns();
            countdownInterval = setInterval(updateCountdowns, 1000);
        }
    });
    
    // Search functionality
    var searchTimeout;
    $('#aih-search-input, #aih-search').on('input', function() {
        var query = $(this).val();
        
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            // Reset to show all
            $('.aih-art-card, .aih-card').show();
            return;
        }
        
        searchTimeout = setTimeout(function() {
            $.ajax({
                url: aihApiUrl('search'),
                type: 'POST',
                data: {
                    action: 'aih_search',
                    nonce: aihAjax.nonce,
                    search: query
                },
                success: function(response) {
                    if (response.success) {
                        var ids = response.data.map(function(item) {
                            return item.id;
                        });
                        
                        $('.aih-art-card, .aih-card').each(function() {
                            var cardId = parseInt($(this).data('id'), 10);
                            if (ids.indexOf(cardId) > -1) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    }
                },
                error: function() {
                    // Silently fail - search will just not filter
                }
            });
        }, 300);
    });
    
    // Search button click
    $('#aih-search-btn').on('click', function() {
        $('#aih-search-input').trigger('input');
    });
    
    // View details button (only for .aih-detail-btn, not .aih-bid-btn or .aih-view-btn which are handled by templates)
    $(document).on('click', '.aih-detail-btn', function(e) {
        e.preventDefault();

        var artId = $(this).data('id');
        loadArtDetails(artId, false);
    });
    
    // Load art details into modal
    function loadArtDetails(artId, focusBid) {
        $.ajax({
            url: aihApiUrl('art-details'),
            type: 'POST',
            data: {
                action: 'aih_get_art_details',
                nonce: aihAjax.nonce,
                art_id: artId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Populate modal
                    $('#aih-detail-img').attr('src', data.image_url);
                    $('#aih-detail-title').text(data.title);
                    $('#aih-detail-artist').text(data.artist);
                    $('#aih-detail-medium').text(data.medium);
                    $('#aih-detail-dimensions').text(data.dimensions || '');
                    $('#aih-detail-description').text(data.description || '');
                    $('#aih-detail-starting-bid').text('$' + data.starting_bid);
                    $('#aih-detail-time').attr('data-seconds', data.seconds_remaining)
                                        .text(formatTimeRemaining(data.seconds_remaining));
                    
                    // Store art ID for bidding
                    $('#aih-submit-bid').data('id', data.id);
                    $('#aih-bid-amount').val('');
                    $('#aih-bid-notice').removeClass('error success').hide();
                    
                    // Show/hide bid form based on auction status
                    if (data.auction_ended || data.auction_upcoming) {
                        $('#aih-bid-form-container').hide();
                    } else {
                        $('#aih-bid-form-container').show();
                    }
                    
                    // User bids
                    var $bidsList = $('#aih-user-bids-list');
                    $bidsList.empty();
                    
                    if (data.user_bids && data.user_bids.length > 0) {
                        data.user_bids.forEach(function(bid) {
                            var $item = $('<div>').addClass('aih-bid-item');
                            if (bid.is_winning) $item.addClass('winning');
                            $item.append($('<span>').addClass('aih-amount').text('$' + bid.amount));
                            $item.append($('<span>').addClass('aih-time').text(bid.time));
                            if (bid.is_winning) {
                                $item.append($('<span>').addClass('aih-winning').text('\u2713 Winning'));
                            }
                            $bidsList.append($item);
                        });
                        $('#aih-user-bids').show();
                    } else {
                        $('#aih-user-bids').hide();
                    }
                    
                    // Show modal
                    $('#aih-detail-modal').fadeIn(200);
                    
                    // Focus on bid input if bid button was clicked
                    if (focusBid && !data.auction_ended && !data.auction_upcoming) {
                        setTimeout(function() {
                            $('#aih-bid-amount').focus();
                        }, 300);
                    }
                }
            },
            error: function() {
                showToast('Failed to load details. Please try again.', 'error');
            }
        });
    }
    
    // Close modal
    $(document).on('click', '.aih-modal-close, .aih-modal-overlay', function() {
        $('#aih-detail-modal').fadeOut(200);
    });
    
    // Close modal on escape
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            $('#aih-detail-modal').fadeOut(200);
        }
    });
    
    // Submit bid (modal)
    $('#aih-submit-bid').on('click', function() {
        var artId = $(this).data('id');
        var bidAmount = $('#aih-bid-amount').val();
        
        submitBid(artId, bidAmount, '#aih-bid-notice');
    });
    
    // Submit bid (single page)
    $(document).on('click', '#aih-single-submit-bid', function() {
        var artId = $(this).data('id');
        var bidAmount = $('#aih-single-bid-amount').val();
        
        submitBid(artId, bidAmount, '#aih-single-bid-notice');
    });
    
    // Bid submission handler
    function submitBid(artId, bidAmount, noticeSelector) {
        var $notice = $(noticeSelector);
        $notice.removeClass('error success').hide();

        if (!bidAmount || parseFloat(bidAmount) <= 0) {
            $notice.addClass('error').text('Please enter a valid bid amount.').show();
            return;
        }

        // Confirm bid amount to prevent fat-finger mistakes
        var numericBid = parseFloat(bidAmount);
        var formatted = '$' + numericBid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (typeof window.aihConfirmBid === 'function') {
            window.aihConfirmBid(formatted, function() { doSubmitBid(artId, bidAmount, $notice); });
        } else {
            if (!confirm('Please confirm your bid of ' + formatted)) { return; }
            doSubmitBid(artId, bidAmount, $notice);
        }
    }

    function doSubmitBid(artId, bidAmount, $notice) {
        $.ajax({
            url: aihApiUrl('bid'),
            type: 'POST',
            data: {
                action: 'aih_place_bid',
                nonce: aihAjax.nonce,
                art_piece_id: artId,
                bid_amount: bidAmount
            },
            success: function(response) {
                if (response.success) {
                    if (navigator.vibrate) navigator.vibrate(100);
                    $notice.addClass('success').text(aihAjax.strings.bidSuccess).show();
                    showToast(aihAjax.strings.bidSuccess, 'success');

                    // Clear input
                    $('#aih-bid-amount, #aih-single-bid-amount').val('');

                    // Reload details to update user bids (only if modal is still open)
                    setTimeout(function() {
                        if ($('#aih-detail-modal').is(':visible')) {
                            loadArtDetails(artId, false);
                        }
                    }, 1000);

                    // Update card if visible
                    updateCardWinningStatus(artId, true);

                    // Prompt for push notification permission after bid (if not yet asked)
                    if (window.AIHPush) {
                        window.AIHPush.promptAfterBid();
                    }

                } else {
                    var message = response.data.message || aihAjax.strings.bidError;

                    if (response.data.bid_too_low) {
                        message = aihAjax.strings.bidTooLow;
                    }

                    if (response.data.login_required) {
                        message = aihAjax.strings.loginRequired;
                    }

                    $notice.addClass('error').text(message).show();
                    showToast(message, 'error');
                }
            },
            error: function() {
                $notice.addClass('error').text(aihAjax.strings.bidError).show();
                showToast(aihAjax.strings.bidError, 'error');
            }
        });
    }
    
    // Update card winning status
    function updateCardWinningStatus(artId, isWinning) {
        var $card = $('.aih-art-card[data-id="' + artId + '"]');
        
        if (isWinning) {
            if ($card.find('.aih-winning-badge').length === 0) {
                $card.find('.aih-card-image').prepend(
                    '<div class="aih-winning-badge">You\'re Winning!</div>'
                );
            }
        } else {
            $card.find('.aih-winning-badge').remove();
        }
    }
    
    // Enter key on bid input
    $('#aih-bid-amount').on('keypress', function(e) {
        if (e.which === 13) {
            $('#aih-submit-bid').trigger('click');
        }
    });
    
    $('#aih-single-bid-amount').on('keypress', function(e) {
        if (e.which === 13) {
            $('#aih-single-submit-bid').trigger('click');
        }
    });
    
    // Prevent right-click on images (basic protection)
    $(document).on('contextmenu', '.aih-card-image img, .aih-detail-image img, .aih-single-image img', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevent drag on images
    $(document).on('dragstart', '.aih-card-image img, .aih-detail-image img, .aih-single-image img', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Auto-sort gallery by time remaining (favorites first, then by time)
    function sortGallery() {
        var $grid = $('.aih-gallery-grid');
        var $cards = $grid.children('.aih-art-card, .aih-card');
        
        $cards.sort(function(a, b) {
            var aFav = $(a).hasClass('is-favorite') ? 0 : 1;
            var bFav = $(b).hasClass('is-favorite') ? 0 : 1;
            
            if (aFav !== bFav) {
                return aFav - bFav;
            }
            
            var aSeconds = parseInt($(a).attr('data-seconds'), 10) || 999999999;
            var bSeconds = parseInt($(b).attr('data-seconds'), 10) || 999999999;
            
            return aSeconds - bSeconds;
        });
        
        $grid.append($cards);
    }
    
    // Sort on load
    sortGallery();
    
    // Re-sort periodically (every minute), pausing when tab is hidden
    var sortTimer = setInterval(sortGallery, 60000);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(sortTimer);
        } else {
            sortGallery();
            sortTimer = setInterval(sortGallery, 60000);
        }
    });
    
    // =============================================
    // DARK MODE THEME TOGGLE
    // =============================================

    (function initThemeToggle() {
        var $page = $('.aih-page').first();
        var $toggle = $('#aih-theme-toggle');
        if (!$page.length || !$toggle.length) return;

        var STORAGE_KEY = 'aih-theme';

        function isDark() {
            return $page.hasClass('dark-mode');
        }

        function setTheme(dark) {
            if (dark) {
                $page.addClass('dark-mode');
            } else {
                $page.removeClass('dark-mode');
            }
        }

        function applyInitialTheme() {
            try {
                var saved = localStorage.getItem(STORAGE_KEY);
                if (saved === 'dark') {
                    setTheme(true);
                } else if (saved === 'light') {
                    setTheme(false);
                }
            } catch (e) {
                // localStorage unavailable (private browsing, etc.)
            }
        }

        // Apply theme (FOUC script handles initial, this is a fallback)
        applyInitialTheme();

        // Toggle click handler
        $toggle.on('click', function() {
            var newDark = !isDark();
            setTheme(newDark);
            try {
                localStorage.setItem(STORAGE_KEY, newDark ? 'dark' : 'light');
            } catch (e) {
                // localStorage unavailable
            }
        });

        // Add transition class after initial paint to prevent FOUC
        setTimeout(function() {
            $page.addClass('dark-mode-transition');
        }, 100);
    })();

    // Expose showToast for push notification fallback
    window.showToast = showToast;

    // =============================================
    // PERSISTENT OUTBID ALERTS (Layer 2)
    // =============================================
    (function initOutbidAlerts() {
        var MAX_VISIBLE = 3;
        var EXPIRY_MS = 5 * 60 * 1000; // 5 minutes
        var alertCount = 0;

        // Inject alert container below header
        var $container = $('<div id="aih-alert-container" class="aih-alert-container"></div>');
        $('.aih-header').first().after($container);

        /**
         * Show a persistent outbid alert card.
         * Same art_piece_id replaces previous alert for that piece.
         *
         * @param {number|string} artPieceId
         * @param {string} title - Art piece title
         * @param {string} [url] - Optional link to the piece
         */
        function showOutbidAlert(artPieceId, title, url) {
            var $existing = $container.find('.aih-alert-card[data-art-id="' + artPieceId + '"]');
            if ($existing.length) {
                // Replace: update text, reset expiry timer
                $existing.find('.aih-alert-text').text('You\'ve been outbid on "' + title + '"!');
                clearTimeout($existing.data('expiryTimer'));
                setExpiry($existing);
                // Flash to draw attention
                $existing.removeClass('aih-alert-flash');
                setTimeout(function() { $existing.addClass('aih-alert-flash'); }, 10);
                return;
            }

            // Collapse oldest if at max
            var $cards = $container.find('.aih-alert-card');
            if ($cards.length >= MAX_VISIBLE) {
                var $oldest = $cards.first();
                removeAlert($oldest);
            }

            // Build alert card
            var viewUrl = url || '';
            if (!viewUrl && aihAjax.galleryUrl) {
                viewUrl = aihAjax.galleryUrl + '?art_id=' + artPieceId;
            }

            var $alert = $(
                '<div class="aih-alert-card" data-art-id="' + artPieceId + '">' +
                    '<div class="aih-alert-icon">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
                    '</div>' +
                    '<span class="aih-alert-text">You\'ve been outbid on "' + $('<span>').text(title).html() + '"!</span>' +
                    (viewUrl ? '<a href="' + viewUrl + '" class="aih-alert-view">View</a>' : '') +
                    '<button type="button" class="aih-alert-dismiss" aria-label="Dismiss">&times;</button>' +
                '</div>'
            );

            $alert.find('.aih-alert-dismiss').on('click', function() {
                removeAlert($alert);
            });

            setExpiry($alert);
            $container.append($alert);

            // Animate in
            setTimeout(function() { $alert.addClass('aih-alert-show'); }, 10);

            alertCount++;
            updateBellBadge();
        }

        function setExpiry($alert) {
            var timer = setTimeout(function() {
                removeAlert($alert);
            }, EXPIRY_MS);
            $alert.data('expiryTimer', timer);
        }

        function removeAlert($alert) {
            clearTimeout($alert.data('expiryTimer'));
            $alert.removeClass('aih-alert-show');
            setTimeout(function() {
                $alert.remove();
                // Don't decrement below 0
                if (alertCount > 0) alertCount--;
                updateBellBadge();
            }, 300);
        }

        /**
         * Update bell button badge with unread alert count
         */
        function updateBellBadge() {
            var $btn = $('#aih-notify-btn');
            if (!$btn.length) return;

            var $badge = $btn.find('.aih-notify-badge');
            var count = $container.find('.aih-alert-card').length;

            if (count > 0) {
                if (!$badge.length) {
                    $badge = $('<span class="aih-notify-badge"></span>');
                    $btn.append($badge);
                }
                $badge.text(count > 9 ? '9+' : count);
            } else {
                $badge.remove();
            }
        }

        // Expose globally
        window.showOutbidAlert = showOutbidAlert;
    })();

    // =============================================
    // CONFIRM BID MODAL (replaces native confirm())
    // =============================================
    (function initConfirmModal() {
        // Inject modal HTML if not present
        if (!$('#aih-confirm-modal').length) {
            $('body').append(
                '<div id="aih-confirm-modal" class="aih-confirm-overlay">' +
                    '<div class="aih-confirm-card">' +
                        '<p class="aih-confirm-text">Confirm bid of <strong id="aih-confirm-amount"></strong>?</p>' +
                        '<div class="aih-confirm-actions">' +
                            '<button type="button" class="aih-confirm-cancel">Cancel</button>' +
                            '<button type="button" class="aih-confirm-yes">Confirm</button>' +
                        '</div>' +
                    '</div>' +
                '</div>'
            );
        }

        var $modal = $('#aih-confirm-modal');
        var pendingCallback = null;

        function closeModal() {
            $modal.removeClass('active');
            pendingCallback = null;
        }

        $modal.on('click', '.aih-confirm-cancel', closeModal);
        $modal.on('click', function(e) {
            if (e.target === this) closeModal();
        });

        $modal.on('click', '.aih-confirm-yes', function() {
            var cb = pendingCallback;
            closeModal();
            if (typeof cb === 'function') cb();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('active')) {
                closeModal();
            }
        });

        // Global function used by all templates
        window.aihConfirmBid = function(amount, callback) {
            $('#aih-confirm-amount').text(amount);
            pendingCallback = callback;
            $modal.addClass('active');
            // Focus trap: focus the confirm button
            $modal.find('.aih-confirm-yes').focus();
        };
    })();

    // =============================================
    // PULL-TO-REFRESH (mobile only)
    // =============================================
    (function initPullToRefresh() {
        if (!('ontouchstart' in window)) return;

        var $indicator = $('.aih-ptr-indicator');
        if (!$indicator.length) return;

        var startY = 0;
        var pulling = false;
        var threshold = 60;

        var main = document.querySelector('.aih-main');
        if (!main) return;

        main.addEventListener('touchstart', function(e) {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
                pulling = true;
            }
        }, { passive: true });

        main.addEventListener('touchmove', function(e) {
            if (!pulling) return;
            var dist = e.touches[0].clientY - startY;
            if (dist < 0) { pulling = false; return; }
            if (dist > 0 && window.scrollY === 0) {
                var translateY = Math.min(dist * 0.4, 80) - 60;
                $indicator.addClass('pulling').css('transform', 'translateX(-50%) translateY(' + translateY + 'px)');
            }
        }, { passive: true });

        main.addEventListener('touchend', function() {
            if (!pulling) return;
            pulling = false;
            var currentY = parseFloat($indicator.css('transform').split(',')[5]) || -60;
            if (currentY > -10) {
                $indicator.removeClass('pulling').addClass('releasing');
                setTimeout(function() { location.reload(); }, 600);
            } else {
                $indicator.removeClass('pulling').css('transform', '');
            }
        }, { passive: true });
    })();

})(jQuery);
