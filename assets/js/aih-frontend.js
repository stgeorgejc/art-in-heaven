/**
 * Art in Heaven - Frontend JavaScript
 * Silent Auction Frontend JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';
    
    // API URL helper: use clean /api/ endpoint when available, fall back to admin-ajax.php
    window.aihApiUrl = function(endpoint) {
        var base = (aihAjax.apiurl || '').replace(/\/$/, '');
        return base ? (base + '/' + endpoint) : aihAjax.ajaxurl;
    };

    // AJAX helper with automatic admin-ajax.php fallback
    // Tries /api/ route first, falls back to admin-ajax.php on network failure
    window.aihPost = function(endpoint, data, successFn, failFn, opts) {
        opts = opts || {};
        var apiUrl = aihApiUrl(endpoint);
        return $.post(apiUrl, data, successFn).fail(function(jqXHR, textStatus) {
            // If server returned 200 but jQuery couldn't parse JSON (parsererror),
            // try to salvage the JSON from the response (PHP notices may precede it).
            if (jqXHR.status === 200 && textStatus === 'parsererror') {
                var text = jqXHR.responseText || '';
                var jsonStart = text.indexOf('{"success"');
                if (jsonStart !== -1) {
                    try {
                        var parsed = JSON.parse(text.substring(jsonStart));
                        if (typeof successFn === 'function') successFn(parsed);
                        return;
                    } catch(e) { /* fall through */ }
                }
                // For mutating operations, don't retry — bid likely went through
                if (opts.mutating) {
                    if (typeof failFn === 'function') failFn();
                    return;
                }
            }
            // Fall back to admin-ajax
            $.post(aihAjax.ajaxurl, data, successFn).fail(function() {
                if (typeof failFn === 'function') failFn();
            });
        });
    };

    // Responsive image helpers — derive AVIF/WebP variant URLs from watermarked URL
    window.aihResponsiveBase = function(baseUrl) {
        return baseUrl.replace(/\.[^.]+$/, '').replace('/watermarked/', '/responsive/');
    };

    // Build <picture> HTML string from a watermarked image URL
    window.aihPictureTag = function(baseUrl, alt, sizes) {
        if (!baseUrl || baseUrl.indexOf('/watermarked/') === -1) {
            return '<img src="' + baseUrl + '" alt="' + alt + '" loading="lazy">';
        }
        var r = aihResponsiveBase(baseUrl);
        var widths = [400, 800, 1200];
        var avifSet = widths.map(function(w) { return r + '-' + w + '.avif ' + w + 'w'; }).join(', ');
        var webpSet = widths.map(function(w) { return r + '-' + w + '.webp ' + w + 'w'; }).join(', ');
        return '<picture>' +
            '<source type="image/avif" srcset="' + avifSet + '" sizes="' + (sizes || '100vw') + '">' +
            '<source type="image/webp" srcset="' + webpSet + '" sizes="' + (sizes || '100vw') + '">' +
            '<img src="' + baseUrl + '" alt="' + alt + '" loading="lazy">' +
            '</picture>';
    };

    // Update an existing <picture> element's sources for image switching
    window.aihUpdatePicture = function($container, baseUrl) {
        var $picture = $container.find('picture');
        if ($picture.length && baseUrl.indexOf('/watermarked/') !== -1) {
            var r = aihResponsiveBase(baseUrl);
            var widths = [400, 800, 1200];
            $picture.find('source[type="image/avif"]').attr('srcset',
                widths.map(function(w) { return r + '-' + w + '.avif ' + w + 'w'; }).join(', '));
            $picture.find('source[type="image/webp"]').attr('srcset',
                widths.map(function(w) { return r + '-' + w + '.webp ' + w + 'w'; }).join(', '));
            $picture.find('img').attr('src', baseUrl);
        } else {
            $container.find('img').attr('src', baseUrl);
        }
    };

    // Global logout handler with fallback
    $('#aih-logout').on('click', function() {
        var data = {action: 'aih_logout', nonce: aihAjax.publicNonce};
        aihPost('logout', data, function() {
            location.reload();
        }, function() {
            document.cookie = 'PHPSESSID=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            location.reload();
        });
    });

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
            aihPost('search', {
                action: 'aih_search',
                nonce: aihAjax.nonce,
                search: query
            }, function(response) {
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
        var data = {action: 'aih_get_art_details', nonce: aihAjax.nonce, art_id: artId};
        aihPost('art-details', data, function(response) {
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
        }, function() {
            showToast('Failed to load details. Please try again.', 'error');
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

    var bidInProgress = false;
    function doSubmitBid(artId, bidAmount, $notice) {
        if (bidInProgress) return;
        bidInProgress = true;
        var data = {action: 'aih_place_bid', nonce: aihAjax.nonce, art_piece_id: artId, bid_amount: bidAmount};
        aihPost('bid', data, function(response) {
                bidInProgress = false;
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
        }, function() {
                bidInProgress = false;
                $notice.addClass('error').text(aihAjax.strings.bidError).show();
                showToast(aihAjax.strings.bidError, 'error');
        }, { mutating: true });
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

    // Cleanup timers on page unload
    window.addEventListener('beforeunload', function() {
        clearInterval(countdownInterval);
        clearInterval(sortTimer);
    });

    // =============================================
    // SHARED POLLING MODULE
    // =============================================
    /**
     * Shared polling module used by gallery, single-item, and my-bids pages.
     *
     * @param {Object} opts
     * @param {Function} opts.getPieceIds  - Returns array of piece IDs to poll
     * @param {number}   opts.timeOffset   - Server-client time offset in ms
     * @param {Function} opts.onUpdate     - Callback(items) with poll response data
     * @param {Function} [opts.getEndTimes]- Returns array of end-time ms values for smart interval
     * @param {Function} [opts.isEnded]    - Returns true if all auctions have ended
     * @param {Function} [opts.shouldSkip] - Returns true to skip UI update (e.g. bidJustPlaced)
     * @returns {{ stop: Function, poll: Function }}
     */
    window.aihStartPolling = function(opts) {
        var pollTimer = null;

        function getSmartInterval() {
            if (typeof opts.getEndTimes === 'function') {
                var times = opts.getEndTimes();
                var soonest = Infinity;
                for (var i = 0; i < times.length; i++) {
                    var remaining = times[i] - (Date.now() + (opts.timeOffset || 0));
                    if (remaining > 0 && remaining < soonest) soonest = remaining;
                }
                if (soonest < 60000) return 2000;
                if (soonest < 300000) return 5000;
                if (soonest < 3600000) return 10000;
            }
            return 30000;
        }

        function poll() {
            if (!aihAjax.isLoggedIn) return;
            if (typeof opts.isEnded === 'function' && opts.isEnded()) return;

            var ids = opts.getPieceIds();
            if (!ids || ids.length === 0) return;

            // Optimization: limit poll to max 50 piece IDs to reduce server load.
            // Prefer pieces the bidder has interacted with (cards with bid-related classes).
            if (ids.length > 50) {
                var biddedIds = [];
                var otherIds = [];
                for (var j = 0; j < ids.length; j++) {
                    var $el = $('.aih-card[data-id="' + ids[j] + '"]');
                    if ($el.hasClass('winning') || $el.hasClass('outbid') || $el.find('.aih-bid-input').length) {
                        biddedIds.push(ids[j]);
                    } else {
                        otherIds.push(ids[j]);
                    }
                }
                // Prioritize pieces the bidder interacted with, then fill up to 50
                ids = biddedIds.concat(otherIds).slice(0, 50);
            }

            aihPost('poll-status', {
                action: 'aih_poll_status',
                nonce: aihAjax.nonce,
                art_piece_ids: ids
            }, function(r) {
                if (!r.success || !r.data || !r.data.items) return;
                if (typeof opts.shouldSkip === 'function' && opts.shouldSkip()) return;
                opts.onUpdate(r.data.items);
            });
        }

        function start() {
            if (pollTimer) clearTimeout(pollTimer);
            // Use slower interval when SSE is connected (background safety net)
            var interval = document.hidden ? 60000 :
                           (window.aihSSEConnected ? 60000 : getSmartInterval());
            pollTimer = setTimeout(function() {
                poll();
                start();
            }, interval);
        }

        function stop() {
            if (pollTimer) {
                clearTimeout(pollTimer);
                pollTimer = null;
            }
        }

        // Visibility change handler
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stop();
                pollTimer = setTimeout(poll, 60000);
            } else {
                stop();
                poll();
                start();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            stop();
        });

        // Restart polling loop on connection status change to adjust interval
        window.addEventListener('aih:connectionchange', function() {
            if (!document.hidden) {
                stop();
                start();
            }
        });

        // Initial poll after 3 seconds, then smart intervals
        setTimeout(function() {
            poll();
            start();
        }, 3000);

        // Expose poll for push notification handler
        window.aihPollStatus = poll;

        return { stop: stop, poll: poll };
    };

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
    // NOTIFICATION STORE (localStorage-backed)
    // =============================================
    var AIHNotificationStore = (function() {
        var STORAGE_KEY = 'aih_notifications';
        var MAX_ITEMS = 50;
        var TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

        function load() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return [];
                var items = JSON.parse(raw);
                var now = Date.now();
                // Prune expired items
                return items.filter(function(item) {
                    return (now - item.timestamp) < TTL_MS;
                });
            } catch (e) {
                return [];
            }
        }

        function save(items) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_ITEMS)));
            } catch (e) {
                // localStorage unavailable
            }
        }

        return {
            getAll: function() {
                return load();
            },

            add: function(artPieceId, title, url) {
                var items = load();
                // Dedup: update existing entry for same art piece
                for (var i = 0; i < items.length; i++) {
                    if (items[i].art_piece_id == artPieceId) {
                        items[i].title = title;
                        items[i].url = url || items[i].url;
                        items[i].timestamp = Date.now();
                        items[i].read = false;
                        // Move to front
                        var updated = items.splice(i, 1)[0];
                        items.unshift(updated);
                        save(items);
                        return updated.id;
                    }
                }
                var id = Date.now() + '-' + Math.random().toString(36).substr(2, 5);
                items.unshift({
                    id: id,
                    art_piece_id: artPieceId,
                    title: title,
                    url: url || '',
                    timestamp: Date.now(),
                    read: false
                });
                save(items);
                return id;
            },

            markRead: function(id) {
                var items = load();
                for (var i = 0; i < items.length; i++) {
                    if (items[i].id === id) {
                        items[i].read = true;
                        break;
                    }
                }
                save(items);
            },

            markAllRead: function() {
                var items = load();
                for (var i = 0; i < items.length; i++) {
                    items[i].read = true;
                }
                save(items);
            },

            getUnreadCount: function() {
                var items = load();
                var count = 0;
                for (var i = 0; i < items.length; i++) {
                    if (!items[i].read) count++;
                }
                return count;
            },

            clear: function() {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch (e) {
                    // ignore
                }
            }
        };
    })();

    // Expose store globally
    window.AIHNotificationStore = AIHNotificationStore;

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
            // Persist to notification store
            var viewUrl = url || '';
            if (!viewUrl && aihAjax.artUrlBase) {
                viewUrl = aihAjax.artUrlBase + artPieceId + '/';
            }
            AIHNotificationStore.add(artPieceId, title, viewUrl);
            updateBellBadge();

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
         * Update bell button badge with unread notification count from store
         */
        function updateBellBadge() {
            var $btn = $('#aih-notify-btn');
            if (!$btn.length) return;

            var $badge = $btn.find('.aih-notify-badge');
            var count = AIHNotificationStore.getUnreadCount();

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

        // Restore badge on page load from persisted store
        updateBellBadge();

        // Expose globally
        window.showOutbidAlert = showOutbidAlert;
    })();

    // =============================================
    // CONNECTION STATUS DOT
    // =============================================
    (function initConnectionStatus() {
        var $btn = $('#aih-notify-btn');
        if (!$btn.length) return;

        // Append connection dot element
        var $dot = $('<span class="aih-connection-dot"></span>');
        $btn.append($dot);

        function updateDot(status) {
            $dot.removeClass('aih-conn-realtime aih-conn-polling aih-conn-offline');
            switch (status) {
                case 'realtime':
                    $dot.addClass('aih-conn-realtime');
                    $dot.attr('title', 'Real-time sync active');
                    break;
                case 'offline':
                    $dot.addClass('aih-conn-offline');
                    $dot.attr('title', 'Offline');
                    break;
                default:
                    $dot.addClass('aih-conn-polling');
                    $dot.attr('title', 'Syncing periodically');
                    break;
            }
        }

        // Set initial state
        updateDot(window.aihConnectionStatus || 'polling');

        // Listen for changes
        window.addEventListener('aih:connectionchange', function(e) {
            updateDot(e.detail.status);
        });
    })();

    // =============================================
    // NOTIFICATION DRAWER
    // =============================================
    (function initNotificationDrawer() {
        var $btn = $('#aih-notify-btn');
        if (!$btn.length) return;

        // Build drawer HTML
        var $backdrop = $('<div class="aih-drawer-backdrop"></div>');
        var $drawer = $(
            '<div class="aih-notification-drawer" aria-label="Notifications">' +
                '<div class="aih-drawer-header">' +
                    '<h3 class="aih-drawer-title">Notifications</h3>' +
                    '<div class="aih-drawer-actions">' +
                        '<button type="button" class="aih-drawer-mark-read">Mark all read</button>' +
                        '<button type="button" class="aih-drawer-close" aria-label="Close">&times;</button>' +
                    '</div>' +
                '</div>' +
                '<div class="aih-drawer-status"></div>' +
                '<div class="aih-drawer-list"></div>' +
            '</div>'
        );

        $('body').append($backdrop).append($drawer);

        var $list = $drawer.find('.aih-drawer-list');
        var $status = $drawer.find('.aih-drawer-status');

        function formatRelativeTime(timestamp) {
            var diff = Math.floor((Date.now() - timestamp) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        }

        function updateStatusBar() {
            var status = window.aihConnectionStatus || 'polling';
            var dotClass = 'aih-conn-' + status;
            var label = status === 'realtime' ? 'Real-time sync' :
                        status === 'offline' ? 'Offline' : 'Periodic sync';
            $status.html(
                '<span class="aih-drawer-dot ' + dotClass + '"></span>' +
                '<span class="aih-drawer-status-text">' + label + '</span>'
            );
        }

        function renderList() {
            var items = AIHNotificationStore.getAll();
            $list.empty();

            if (items.length === 0) {
                $list.html('<div class="aih-drawer-empty">No notifications yet</div>');
                return;
            }

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var readClass = item.read ? 'aih-drawer-item-read' : '';
                var $item = $(
                    '<a href="' + (item.url || '#') + '" class="aih-drawer-item ' + readClass + '" data-id="' + item.id + '">' +
                        '<div class="aih-drawer-item-icon">' +
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
                        '</div>' +
                        '<div class="aih-drawer-item-body">' +
                            '<span class="aih-drawer-item-text">Outbid on "' + $('<span>').text(item.title).html() + '"</span>' +
                            '<span class="aih-drawer-item-time">' + formatRelativeTime(item.timestamp) + '</span>' +
                        '</div>' +
                    '</a>'
                );
                $list.append($item);
            }
        }

        function openDrawer() {
            renderList();
            updateStatusBar();
            $backdrop.addClass('active');
            $drawer.addClass('active');
        }

        function closeDrawer() {
            $backdrop.removeClass('active');
            $drawer.removeClass('active');
        }

        // Toggle drawer
        window.aihToggleDrawer = function() {
            if ($drawer.hasClass('active')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        };

        // Close handlers
        $backdrop.on('click', closeDrawer);
        $drawer.find('.aih-drawer-close').on('click', closeDrawer);

        // Mark all read
        $drawer.find('.aih-drawer-mark-read').on('click', function() {
            AIHNotificationStore.markAllRead();
            renderList();
            // Update bell badge
            var $badge = $btn.find('.aih-notify-badge');
            $badge.remove();
        });

        // Mark individual item read on click
        $list.on('click', '.aih-drawer-item', function() {
            var id = $(this).data('id');
            AIHNotificationStore.markRead(id);
            // Update badge
            var count = AIHNotificationStore.getUnreadCount();
            var $badge = $btn.find('.aih-notify-badge');
            if (count > 0) {
                if (!$badge.length) {
                    $badge = $('<span class="aih-notify-badge"></span>');
                    $btn.append($badge);
                }
                $badge.text(count > 9 ? '9+' : count);
            } else {
                $badge.remove();
            }
        });

        // Update status bar when connection changes
        window.addEventListener('aih:connectionchange', function() {
            if ($drawer.hasClass('active')) {
                updateStatusBar();
            }
        });

        // Close drawer on escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $drawer.hasClass('active')) {
                closeDrawer();
            }
        });
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
