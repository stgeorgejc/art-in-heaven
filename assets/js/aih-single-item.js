/**
 * Art in Heaven - Single Item Page JavaScript
 */
jQuery(document).ready(function($) {
    var $wrapper = $('#aih-single-wrapper');

    // Favorite
    $('.aih-fav-btn').on('click', function() {
        var $btn = $(this);
        if ($btn.hasClass('loading')) return;
        $btn.addClass('loading');
        aihPost('favorite', {action:'aih_toggle_favorite', nonce:aihAjax.nonce, art_piece_id:$btn.data('id')}, function(r) {
            $btn.removeClass('loading');
            if (r.success) {
                $btn.toggleClass('active');
            }
        }, function() {
            $btn.removeClass('loading');
        });
    });

    // Image navigation - current index
    var currentImgIndex = 0;
    var totalImages = $('.aih-image-dot').length || 1;

    function showImage(index) {
        if (index < 0) index = totalImages - 1;
        if (index >= totalImages) index = 0;
        currentImgIndex = index;

        var $dot = $('.aih-image-dot[data-index="' + index + '"]');
        var src = $dot.data('src');
        if (src) {
            aihUpdatePicture($('.aih-single-image'), src);
            $('.aih-image-dot').removeClass('active');
            $dot.addClass('active');
        }
    }

    // Dot navigation
    $('.aih-image-dot').on('click', function() {
        var index = parseInt($(this).data('index'));
        showImage(index);
    });

    // Arrow navigation
    $('.aih-img-nav-prev').on('click', function() {
        showImage(currentImgIndex - 1);
    });

    $('.aih-img-nav-next').on('click', function() {
        showImage(currentImgIndex + 1);
    });

    // Touch swipe for main image navigation
    var mainTouchStartX = 0;
    $('.aih-single-image').on('touchstart', function(e) {
        mainTouchStartX = e.originalEvent.touches[0].clientX;
    }).on('touchend', function(e) {
        var diff = mainTouchStartX - e.originalEvent.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            showImage(currentImgIndex + (diff > 0 ? 1 : -1));
        }
    });

    // Lightbox functionality
    var $lightbox = $('#aih-lightbox');
    var $lightboxImg = $('#aih-lightbox-img');
    var lightboxIndex = 0;

    // Image sources from data attribute
    var allImages = [];
    try {
        allImages = JSON.parse($wrapper.attr('data-images') || '[]');
    } catch (e) {
        allImages = [];
    }
    // Fallback if data attribute is empty
    if (!allImages || allImages.length === 0) {
        var mainSrc = $('#aih-main-image').attr('src');
        if (mainSrc) allImages = [mainSrc];
    }

    // Generate lightbox dots dynamically based on actual image count
    var $dotsContainer = $lightbox.find('.aih-lightbox-dots');
    $dotsContainer.empty();
    for (var i = 0; i < allImages.length; i++) {
        var activeClass = i === 0 ? ' active' : '';
        $dotsContainer.append('<span class="aih-lightbox-dot' + activeClass + '" data-index="' + i + '"></span>');
    }

    // Bind click events for dynamically created dots
    $dotsContainer.on('click', '.aih-lightbox-dot', function() {
        var index = parseInt($(this).data('index'));
        lightboxIndex = index;
        $lightboxImg.attr('src', allImages[index]);
        updateLightboxDots(index);
    });

    function updateLightboxDots(index) {
        $lightbox.find('.aih-lightbox-dot').removeClass('active');
        $lightbox.find('.aih-lightbox-dot[data-index="' + index + '"]').addClass('active');
    }

    function openLightbox(index) {
        if (allImages.length === 0) return;
        if (index < 0 || index >= allImages.length) index = 0;
        lightboxIndex = index;
        var imgSrc = allImages[index];
        $lightboxImg.attr('src', imgSrc);
        $('#aih-lb-current').text(index + 1);
        updateLightboxDots(index);
        $lightbox.addClass('active');
        $('html').addClass('aih-lightbox-open');

        // Show/hide navigation based on image count
        if (allImages.length > 1) {
            $lightbox.addClass('has-multiple');
        } else {
            $lightbox.removeClass('has-multiple');
        }
    }

    function closeLightbox() {
        $lightbox.removeClass('active has-multiple');
        $('html').removeClass('aih-lightbox-open');
        $('body').css('overflow', '');
    }

    function lightboxNav(direction) {
        lightboxIndex += direction;
        if (lightboxIndex < 0) lightboxIndex = allImages.length - 1;
        if (lightboxIndex >= allImages.length) lightboxIndex = 0;
        $lightboxImg.attr('src', allImages[lightboxIndex]);
        $('#aih-lb-current').text(lightboxIndex + 1);
        updateLightboxDots(lightboxIndex);
    }

    // Open lightbox on main image click
    $('#aih-main-image').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        openLightbox(currentImgIndex);
    });

    // Close lightbox
    $('.aih-lightbox-close').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        closeLightbox();
    });
    $lightbox.on('click', function(e) {
        if (e.target === this) closeLightbox();
    });

    // Lightbox navigation
    $('.aih-lightbox-prev').on('click', function() {
        lightboxNav(-1);
    });
    $('.aih-lightbox-next').on('click', function() {
        lightboxNav(1);
    });

    // Keyboard navigation
    $(document).on('keydown', function(e) {
        if (!$lightbox.hasClass('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') lightboxNav(-1);
        if (e.key === 'ArrowRight') lightboxNav(1);
    });

    // Touch swipe for lightbox
    var touchStartX = 0;
    $lightbox.on('touchstart', function(e) {
        touchStartX = e.originalEvent.touches[0].clientX;
    }).on('touchend', function(e) {
        var diff = touchStartX - e.originalEvent.changedTouches[0].clientX;
        if (Math.abs(diff) > 50) {
            lightboxNav(diff > 0 ? 1 : -1); // swipe left = next, right = prev
        }
    });

    // Place bid
    var bidInProgress = false;
    $('#place-bid').on('click', function() {
        if (bidInProgress) return;
        var $btn = $(this);
        var amount = parseInt(($('#bid-amount').val() || '').replace(/[^0-9]/g, ''), 10);
        var $msg = $('#bid-message');

        if (!amount || amount < 1) { $msg.addClass('error').text(aihAjax.strings.enterValidBid).show(); return; }

        // Confirm bid amount to prevent fat-finger mistakes
        var formatted = '$' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        window.aihConfirmBid(formatted, function() {
        bidInProgress = true;
        $btn.prop('disabled', true).addClass('loading');
        $msg.hide().removeClass('error success');

        aihPost('bid', {action:'aih_place_bid', nonce:aihAjax.nonce, art_piece_id:$btn.data('id'), bid_amount:amount}, function(r) {
            if (r.success) {
                if (navigator.vibrate) navigator.vibrate(100);
                bidJustPlaced = true;
                setTimeout(function() { bidJustPlaced = false; }, 5000);
                $msg.removeClass('error').addClass('success').text(aihAjax.strings.bidSuccess).show();
                $('.aih-single-image').find('.aih-badge').remove();
                $('.aih-single-image').prepend('<span class="aih-badge aih-badge-winning aih-badge-single">Winning</span>');
                $('#bid-amount').val('');
            } else {
                $msg.removeClass('success').addClass('error').text(r.data.message || 'Failed').show();
            }
            bidInProgress = false;
            $btn.prop('disabled', false).removeClass('loading');
        }, function() {
            $msg.removeClass('success').addClass('error').text(aihAjax.strings.connectionError).show();
            bidInProgress = false;
            $btn.prop('disabled', false).removeClass('loading');
        }, { mutating: true });
        }); // end aihConfirmBid
    });

    $('#bid-amount').on('keypress', function(e) {
        if (e.which === 13) $('#place-bid').click();
    });

    // Countdown timer for single item page
    var serverTime = parseInt($wrapper.data('server-time')) || new Date().getTime();
    var timeOffset = serverTime - new Date().getTime();

    function updateCountdown() {
        $('.aih-time-remaining-single').each(function() {
            var $el = $(this);
            var endTime = $el.attr('data-end');
            if (!endTime) return;

            var end = new Date(endTime.replace(/-/g, '/')).getTime();
            var now = new Date().getTime() + timeOffset;
            var diff = end - now;

            if (diff <= 0) {
                $el.find('.aih-time-value').text('Ended');
                $el.addClass('ended');
                // Disable bid form
                $('#bid-amount').prop('disabled', true).attr('placeholder', 'Ended');
                $('#place-bid').prop('disabled', true).text('Ended');

                // Update status badge
                var $badge = $('.aih-badge-single');
                if ($badge.length) {
                    if ($badge.text().trim() === 'Winning') {
                        $badge.attr('class', 'aih-badge aih-badge-won aih-badge-single').text('Won');
                    } else {
                        $badge.attr('class', 'aih-badge aih-badge-ended aih-badge-single').text('Ended');
                    }
                } else {
                    $('.aih-single-image').append('<span class="aih-badge aih-badge-ended aih-badge-single">Ended</span>');
                }
                return;
            }

            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((diff % (1000 * 60)) / 1000);

            var timeStr = '';
            if (days > 0) {
                timeStr = days + 'd ' + hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                timeStr = hours + 'h ' + minutes + 'm ' + seconds + 's';
            } else {
                timeStr = minutes + 'm ' + seconds + 's';
            }

            $el.find('.aih-time-value').text(timeStr);

            if (diff < 3600000) {
                $el.addClass('urgent');
            }
        });
    }

    updateCountdown();
    var countdownTimer = setInterval(updateCountdown, 1000);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(countdownTimer);
        } else {
            updateCountdown();
            countdownTimer = setInterval(updateCountdown, 1000);
        }
    });

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
    var pieceId = parseInt($wrapper.attr('data-piece-id')) || 0;
    var isEnded = $wrapper.attr('data-is-ended') === '1';
    var bidJustPlaced = false;

    if (!isEnded && pieceId && typeof window.aihStartPolling === 'function') {
        window.aihStartPolling({
            timeOffset: timeOffset,
            getPieceIds: function() { return [pieceId]; },
            getEndTimes: function() {
                var $timeEl = $('.aih-time-remaining-single[data-end]');
                if (!$timeEl.length) return [];
                return [new Date($timeEl.attr('data-end').replace(/-/g, '/')).getTime()];
            },
            isEnded: function() { return isEnded; },
            shouldSkip: function() { return bidJustPlaced; },
            onUpdate: function(items) {
                var info = items[pieceId];
                if (!info || info.status === 'ended') return;

                var $badge = $('.aih-badge-single');
                var wasWinning = $badge.length && $badge.text().trim() === 'Winning';

                if (info.is_winning && !wasWinning) {
                    if ($badge.length) {
                        $badge.attr('class', 'aih-badge aih-badge-winning aih-badge-single').text('Winning');
                    } else {
                        $('.aih-single-image').prepend('<span class="aih-badge aih-badge-winning aih-badge-single">Winning</span>');
                    }
                } else if (!info.is_winning && info.has_bid) {
                    if ($badge.length) {
                        $badge.attr('class', 'aih-badge aih-badge-outbid aih-badge-single').text('Outbid');
                    } else {
                        $('.aih-single-image').prepend('<span class="aih-badge aih-badge-outbid aih-badge-single">Outbid</span>');
                    }
                }

            }
        });
    }
});
