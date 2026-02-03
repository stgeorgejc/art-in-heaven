/**
 * Art in Heaven - Frontend JavaScript
 * Silent Auction Frontend JavaScript
 * Version: 0.9.115
 */

(function($) {
    'use strict';
    
    // Toast notification helper
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
    
    // Initialize countdown timer
    setInterval(updateCountdowns, 1000);
    updateCountdowns();
    
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
                url: aihAjax.ajaxurl,
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
                }
            });
        }, 300);
    });
    
    // Search button click
    $('#aih-search-btn').on('click', function() {
        $('#aih-search-input').trigger('input');
    });
    
    // Favorite toggle
    $(document).on('click', '.aih-favorite-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $btn = $(this);
        var artId = $btn.data('id');
        
        $.ajax({
            url: aihAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_toggle_favorite',
                nonce: aihAjax.nonce,
                art_piece_id: artId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.is_favorite) {
                        $btn.addClass('active');
                        $btn.find('svg').attr('fill', 'currentColor');
                        $btn.closest('.aih-art-card').addClass('is-favorite');
                        showToast(aihAjax.strings.favoriteAdded, 'success');
                    } else {
                        $btn.removeClass('active');
                        $btn.find('svg').attr('fill', 'none');
                        $btn.closest('.aih-art-card').removeClass('is-favorite');
                        showToast(aihAjax.strings.favoriteRemoved, 'success');
                    }
                } else if (response.data && response.data.login_required) {
                    showToast(aihAjax.strings.loginRequired, 'error');
                }
            }
        });
    });
    
    // View details button
    $(document).on('click', '.aih-view-btn, .aih-bid-btn', function(e) {
        e.preventDefault();
        
        var artId = $(this).data('id');
        var isBidClick = $(this).hasClass('aih-bid-btn');
        
        loadArtDetails(artId, isBidClick);
    });
    
    // Load art details into modal
    function loadArtDetails(artId, focusBid) {
        $.ajax({
            url: aihAjax.ajaxurl,
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
                            var winningClass = bid.is_winning ? 'winning' : '';
                            $bidsList.append(
                                '<div class="aih-bid-item ' + winningClass + '">' +
                                    '<span class="aih-amount">$' + bid.amount + '</span>' +
                                    '<span class="aih-time">' + bid.time + '</span>' +
                                    (bid.is_winning ? '<span class="aih-winning">✓ Winning</span>' : '') +
                                '</div>'
                            );
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
        
        $.ajax({
            url: aihAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_place_bid',
                nonce: aihAjax.nonce,
                art_piece_id: artId,
                bid_amount: bidAmount
            },
            success: function(response) {
                if (response.success) {
                    $notice.addClass('success').text(aihAjax.strings.bidSuccess).show();
                    showToast(aihAjax.strings.bidSuccess, 'success');
                    
                    // Clear input
                    $('#aih-bid-amount, #aih-single-bid-amount').val('');
                    
                    // Reload details to update user bids
                    setTimeout(function() {
                        loadArtDetails(artId, false);
                    }, 1000);
                    
                    // Update card if visible
                    updateCardWinningStatus(artId, true);
                    
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
    
    // Re-sort periodically (every minute)
    setInterval(sortGallery, 60000);
    
    // =============================================
    // LIGHTBOX / SPOTLIGHT
    // =============================================
    
    var lightboxImages = [];
    var currentLightboxIndex = 0;
    
    // Create lightbox element if it doesn't exist
    function createLightbox() {
        if ($('#aih-lightbox').length) return;
        
        var html = '<div id="aih-lightbox" class="aih-lightbox">' +
            '<button type="button" class="aih-lightbox-close">&times;</button>' +
            '<div class="aih-lightbox-content">' +
                '<button type="button" class="aih-lightbox-nav aih-lightbox-prev">❮</button>' +
                '<img class="aih-lightbox-image" src="" alt="">' +
                '<button type="button" class="aih-lightbox-nav aih-lightbox-next">❯</button>' +
            '</div>' +
            '<div class="aih-lightbox-dots"></div>' +
            '<div class="aih-lightbox-counter"></div>' +
            '<div class="aih-lightbox-thumbnails"></div>' +
        '</div>';
        
        $('body').append(html);
        initLightboxEvents();
    }
    
    // Initialize lightbox events
    function initLightboxEvents() {
        var $lightbox = $('#aih-lightbox');
        
        // Close button
        $lightbox.on('click', '.aih-lightbox-close', function() {
            closeLightbox();
        });
        
        // Click outside to close
        $lightbox.on('click', function(e) {
            if ($(e.target).is('#aih-lightbox')) {
                closeLightbox();
            }
        });
        
        // Navigation
        $lightbox.on('click', '.aih-lightbox-prev', function(e) {
            e.stopPropagation();
            navigateLightbox(-1);
        });
        
        $lightbox.on('click', '.aih-lightbox-next', function(e) {
            e.stopPropagation();
            navigateLightbox(1);
        });
        
        // Thumbnail click
        $lightbox.on('click', '.aih-lightbox-thumb', function(e) {
            e.stopPropagation();
            var index = $(this).data('index');
            showLightboxImage(index);
        });

        // Dot indicator click
        $lightbox.on('click', '.aih-lightbox-dot', function(e) {
            e.stopPropagation();
            var index = $(this).data('index');
            showLightboxImage(index);
        });
        
        // Keyboard navigation
        $(document).on('keydown', function(e) {
            if (!$lightbox.hasClass('active')) return;
            
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                navigateLightbox(-1);
            } else if (e.key === 'ArrowRight') {
                navigateLightbox(1);
            }
        });
        
        // Touch/swipe support
        var touchStartX = 0;
        var touchEndX = 0;
        
        $lightbox.on('touchstart', function(e) {
            touchStartX = e.originalEvent.changedTouches[0].screenX;
        });
        
        $lightbox.on('touchend', function(e) {
            touchEndX = e.originalEvent.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            var diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) {
                    navigateLightbox(1); // Swipe left = next
                } else {
                    navigateLightbox(-1); // Swipe right = prev
                }
            }
        }
    }
    
    // Open lightbox
    function openLightbox(images, startIndex) {
        createLightbox();
        
        lightboxImages = images;
        currentLightboxIndex = startIndex || 0;
        
        var $lightbox = $('#aih-lightbox');
        var $thumbContainer = $lightbox.find('.aih-lightbox-thumbnails');
        
        // Build thumbnails using jQuery DOM methods (safer than string concatenation)
        $thumbContainer.empty();
        images.forEach(function(img, i) {
            var $thumb = $('<img>')
                .addClass('aih-lightbox-thumb')
                .toggleClass('active', i === currentLightboxIndex)
                .attr('src', img)
                .attr('data-index', i)
                .attr('alt', '');
            $thumbContainer.append($thumb);
        });

        // Build dot indicators
        var $dotsContainer = $lightbox.find('.aih-lightbox-dots');
        $dotsContainer.empty();
        images.forEach(function(img, i) {
            var $dot = $('<span>')
                .addClass('aih-lightbox-dot')
                .toggleClass('active', i === currentLightboxIndex)
                .attr('data-index', i);
            $dotsContainer.append($dot);
        });

        // Show/hide nav, thumbnails, dots, and counter based on image count
        if (images.length <= 1) {
            $lightbox.find('.aih-lightbox-nav, .aih-lightbox-thumbnails, .aih-lightbox-dots, .aih-lightbox-counter').hide();
        } else {
            $lightbox.find('.aih-lightbox-nav, .aih-lightbox-thumbnails, .aih-lightbox-dots, .aih-lightbox-counter').show();
        }
        
        showLightboxImage(currentLightboxIndex);
        
        $lightbox.addClass('active');
        $('body').css('overflow', 'hidden');
    }
    
    // Show specific image
    function showLightboxImage(index) {
        if (index < 0) index = lightboxImages.length - 1;
        if (index >= lightboxImages.length) index = 0;
        
        currentLightboxIndex = index;
        
        var $lightbox = $('#aih-lightbox');
        $lightbox.find('.aih-lightbox-image').attr('src', lightboxImages[index]);
        $lightbox.find('.aih-lightbox-counter').text((index + 1) + ' / ' + lightboxImages.length);
        
        // Update thumbnail active state
        $lightbox.find('.aih-lightbox-thumb').removeClass('active');
        $lightbox.find('.aih-lightbox-thumb[data-index="' + index + '"]').addClass('active');

        // Update dot indicator active state
        $lightbox.find('.aih-lightbox-dot').removeClass('active');
        $lightbox.find('.aih-lightbox-dot[data-index="' + index + '"]').addClass('active');
        
        // Scroll thumbnail into view
        var $thumb = $lightbox.find('.aih-lightbox-thumb.active');
        if ($thumb.length) {
            $thumb[0].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
    }
    
    // Navigate lightbox
    function navigateLightbox(direction) {
        showLightboxImage(currentLightboxIndex + direction);
    }
    
    // Close lightbox
    function closeLightbox() {
        var $lightbox = $('#aih-lightbox');
        $lightbox.removeClass('active');
        $('body').css('overflow', '');
    }
    
    // Click handlers for images
    
    // Single item main image ONLY (not gallery cards)
    $(document).on('click', '#aih-main-image, .aih-single-image > img', function(e) {
        e.preventDefault();
        
        // Collect all images from thumbnails or dots or use current image
        var images = [];
        var $thumbs = $('.aih-thumbnails .aih-thumb');
        var $dots = $('.aih-image-dots .aih-dot');
        
        if ($thumbs.length > 0) {
            $thumbs.each(function() {
                var src = $(this).data('src') || $(this).find('img').attr('src');
                if (src) images.push(src);
            });
        } else if ($dots.length > 0) {
            $dots.each(function() {
                var url = $(this).data('url');
                if (url) images.push(url);
            });
        } else {
            images.push($(this).attr('src'));
        }
        
        // Find current index
        var currentSrc = $(this).attr('src');
        var startIndex = 0;
        images.forEach(function(img, i) {
            if (img === currentSrc) startIndex = i;
        });
        
        openLightbox(images, startIndex);
    });
    
})(jQuery);
