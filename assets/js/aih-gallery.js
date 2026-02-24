/**
 * Art in Heaven - Gallery Page JavaScript
 */
jQuery(document).ready(function($) {
    // Search & Filter
    var serverTime = parseInt($('#aih-gallery-wrapper').data('server-time')) || new Date().getTime();
    var timeOffset = serverTime - new Date().getTime();

    function filterCards() {
        var search = $('#aih-search').val().toLowerCase().trim();
        var artist = $('#aih-filter-artist').val();
        var medium = $('#aih-filter-medium').val();
        var favoritesOnly = $('#aih-filter-favorites').val() === 'favorites';
        var statusFilter = $('#aih-filter-status').val();

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

            // Status filter
            if (show && statusFilter) {
                var isCardEnded = $card.hasClass('ended') || $card.hasClass('won') || $card.hasClass('paid');
                if (statusFilter === 'ended') {
                    if (!isCardEnded) show = false;
                } else if (statusFilter === 'active') {
                    if (isCardEnded) show = false;
                } else if (statusFilter === 'ending-soon') {
                    if (isCardEnded) { show = false; }
                    else {
                        var cardEnd = $card.attr('data-end');
                        if (!cardEnd) { show = false; }
                        else {
                            var endMs = new Date(cardEnd.replace(/-/g, '/')).getTime();
                            var nowMs = new Date().getTime() + timeOffset;
                            var remaining = endMs - nowMs;
                            if (remaining <= 0 || remaining >= 3600000) show = false;
                        }
                    }
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
    function sortCards(overrideSortBy) {
        var sortBy = overrideSortBy || $('#aih-sort').val();

        var $grid = $('#aih-gallery');
        var $cards = $grid.children('.aih-card');

        $cards.sort(function(a, b) {
            var $a = $(a);
            var $b = $(b);
            var aVal, bVal;

            // Always push ended/won/paid/sold to the bottom
            var aEnded = $a.hasClass('ended') || $a.hasClass('won') || $a.hasClass('paid') || $a.hasClass('sold') ? 1 : 0;
            var bEnded = $b.hasClass('ended') || $b.hasClass('won') || $b.hasClass('paid') || $b.hasClass('sold') ? 1 : 0;
            if (aEnded !== bEnded) return aEnded - bEnded;

            switch (sortBy) {
                case 'default':
                    // Favorites first, then by soonest ending
                    var aFav = $a.find('.aih-card-image').attr('data-favorite') === '1' ? 0 : 1;
                    var bFav = $b.find('.aih-card-image').attr('data-favorite') === '1' ? 0 : 1;
                    if (aFav !== bFav) return aFav - bFav;
                    var aEnd = $a.attr('data-end') ? new Date($a.attr('data-end').replace(/-/g, '/')).getTime() : Infinity;
                    var bEnd = $b.attr('data-end') ? new Date($b.attr('data-end').replace(/-/g, '/')).getTime() : Infinity;
                    return aEnd - bEnd;
                case 'artid-asc':
                    aVal = parseInt($a.attr('data-art-id')) || 0;
                    bVal = parseInt($b.attr('data-art-id')) || 0;
                    return aVal - bVal;
                case 'artid-desc':
                    aVal = parseInt($a.attr('data-art-id')) || 0;
                    bVal = parseInt($b.attr('data-art-id')) || 0;
                    return bVal - aVal;
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

    // Bind filter events with debounce for search input
    var filterTimer;
    $('#aih-search').on('input', function() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(filterCards, 200);
    }).on('change', function() {
        filterCards();
    });

    $('#aih-filter-artist, #aih-filter-medium, #aih-filter-favorites, #aih-filter-status').on('change', function() {
        filterCards();
    });

    // Track whether user has manually picked a sort
    var userChangedSort = false;

    // Bind sort event
    $('#aih-sort').on('change', function() {
        userChangedSort = true;
        sortCards();
    });

    // Reset filters
    $('#aih-filter-reset').on('click', function() {
        $('#aih-search').val('');
        $('#aih-sort').prop('selectedIndex', 0);
        $('#aih-filter-artist').val('');
        $('#aih-filter-medium').val('');
        $('#aih-filter-favorites').val('');
        $('#aih-filter-status').val('');
        userChangedSort = false;
        sortCards('default');
        filterCards();
    });

    // Apply default sort and filter on page load
    sortCards('default');
    filterCards();

    // Favorite toggle - only update UI after server confirmation
    $('#aih-gallery').on('click', '.aih-fav-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        if ($btn.hasClass('loading')) return;
        var id = $btn.data('id');
        $btn.addClass('loading');

        aihPost('favorite', {action:'aih_toggle_favorite', nonce:aihAjax.nonce, art_piece_id:id}, function(r) {
            $btn.removeClass('loading');
            if (r.success) {
                $btn.toggleClass('active');
                var $cardImage = $btn.closest('.aih-card-image');
                $cardImage.attr('data-favorite', $btn.hasClass('active') ? '1' : '0');
                updateFavoritesFilterVisibility();
                if (!userChangedSort) sortCards('default');
                filterCards();
            }
        }, function() {
            $btn.removeClass('loading');
        });
    });

    // Place bid
    $('#aih-gallery').on('click', '.aih-bid-btn', function() {
        var $btn = $(this);
        var $card = $btn.closest('.aih-card');
        var $input = $card.find('.aih-bid-input');
        var $msg = $card.find('.aih-bid-message');
        var id = $btn.data('id');
        var amount = parseInt(($input.val() || '').replace(/[^0-9]/g, ''), 10);

        if (!amount || amount < 1) {
            $msg.text(aihAjax.strings.enterValidBid).addClass('error').show();
            return;
        }

        // Confirm bid amount to prevent fat-finger mistakes
        var formatted = '$' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        window.aihConfirmBid(formatted, function() {
            $btn.prop('disabled', true).text('...');
            $msg.hide();

            aihPost('bid', {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:id, bid_amount:amount}, function(r) {
                if (r.success) {
                    if (navigator.vibrate) navigator.vibrate(100);
                    bidJustPlaced = true;
                    setTimeout(function() { bidJustPlaced = false; }, 5000);
                    $msg.removeClass('error').addClass('success').text(aihAjax.strings.bidPlaced).show();
                    $card.removeClass('outbid').addClass('winning').attr('data-has-bid', '1');
                    var $badge = $card.find('.aih-badge');
                    if ($badge.length) {
                        $badge.attr('class', 'aih-badge aih-badge-winning').text('Winning');
                    } else {
                        $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-winning">Winning</div>');
                    }
                    $input.val('');
                    setTimeout(function() { $msg.fadeOut(); }, 2000);
                } else {
                    $msg.removeClass('success').addClass('error').text(r.data.message || 'Bid failed').show();
                }
                $btn.prop('disabled', false).text('Bid');
            }, function() {
                $msg.removeClass('success').addClass('error').text(aihAjax.strings.connectionError).show();
                $btn.prop('disabled', false).text('Bid');
            }, { mutating: true });
        });
    });

    // Enter key to bid
    $('#aih-gallery').on('keypress', '.aih-bid-input', function(e) {
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

            // Add urgency class if less than 1 hour
            if (diff < 3600000) {
                $el.addClass('urgent');
            }
        });

        // Update badges and disable forms for all cards with data-end
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

                $card.removeClass('winning').addClass(newStatus);

                var $badge = $card.find('.aih-badge');
                if ($badge.length) {
                    $badge.attr('class', 'aih-badge aih-badge-' + newStatus).text(newText);
                } else {
                    $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-' + newStatus + '">' + newText + '</div>');
                }

                // Disable bid form
                $card.find('.aih-bid-input').prop('disabled', true).attr('placeholder', 'Ended');
                $card.find('.aih-bid-btn').prop('disabled', true).text('Ended');
                $card.find('.aih-card-footer').hide();
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

    // === Live bid status polling ===
    var bidJustPlaced = false;

    if (typeof window.aihStartPolling === 'function') {
        window.aihStartPolling({
            timeOffset: timeOffset,
            getPieceIds: function() {
                var ids = [];
                $('.aih-card').each(function() {
                    var id = $(this).data('id');
                    if (id) ids.push(id);
                });
                return ids;
            },
            getEndTimes: function() {
                var times = [];
                $('.aih-card[data-end]').each(function() {
                    var $c = $(this);
                    if ($c.hasClass('ended') || $c.hasClass('won') || $c.hasClass('paid')) return;
                    times.push(new Date($c.attr('data-end').replace(/-/g, '/')).getTime());
                });
                return times;
            },
            isEnded: function() {
                var hasActive = false;
                $('.aih-card').each(function() {
                    var $card = $(this);
                    if (!$card.hasClass('ended') && !$card.hasClass('won') && !$card.hasClass('paid')) {
                        hasActive = true;
                        return false;
                    }
                });
                return !hasActive;
            },
            shouldSkip: function() { return bidJustPlaced; },
            onUpdate: function(items) {
                // Build card map for efficient DOM access
                var cardMap = {};
                $('.aih-card').each(function() { cardMap[$(this).data('id')] = $(this); });

                $.each(items, function(id, info) {
                    if (info.status === 'ended') return;
                    var $card = cardMap[id];
                    if (!$card || !$card.length) return;

                    var wasWinning = $card.hasClass('winning');
                    var hasBid = $card.attr('data-has-bid') === '1' || info.has_bid;
                    if (info.has_bid) $card.attr('data-has-bid', '1');

                    if (info.is_winning && !wasWinning) {
                        $card.removeClass('outbid').addClass('winning');
                        var $badge = $card.find('.aih-badge');
                        if ($badge.length) {
                            $badge.attr('class', 'aih-badge aih-badge-winning').text('Winning');
                        } else {
                            $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-winning">Winning</div>');
                        }
                    } else if (!info.is_winning && wasWinning) {
                        $card.removeClass('winning');
                        if (hasBid) {
                            $card.addClass('outbid');
                            var $badge = $card.find('.aih-badge');
                            if ($badge.length) {
                                $badge.attr('class', 'aih-badge aih-badge-outbid').text('Outbid');
                            } else {
                                $card.find('.aih-card-image').append('<div class="aih-badge aih-badge-outbid">Outbid</div>');
                            }
                        } else {
                            $card.find('.aih-badge').remove();
                        }
                    }

                    if (info.has_bids) {
                        $card.find('.aih-badge-no-bids').remove();
                    }
                });
            }
        });
    }
});
