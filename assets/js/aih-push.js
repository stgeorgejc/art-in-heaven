/**
 * Art in Heaven - Push Notification Manager
 *
 * Handles push subscription, permission prompts, and polling fallback
 * for outbid notifications. Manages the header bell button state.
 */

(function($) {
    'use strict';

    // Only activate for logged-in bidders
    if (!window.aihAjax || !aihAjax.isLoggedIn || !aihAjax.bidderId) {
        // Hide bell button for logged-out users
        $('#aih-notify-btn').hide();
        return;
    }

    var AIHPush = {
        pushSubscribed: false,
        pollTimer: null,
        swRegistration: null,
        shownEvents: {},
        bellBtn: null,
        iosInstallShown: false,

        /**
         * Detect iOS Safari running in browser (not as installed PWA).
         * Returns true only for real Safari on iOS — not Chrome/Firefox for iOS,
         * and not when already running in standalone (home-screen) mode.
         */
        isIOSSafariNonStandalone: function() {
            var ua = navigator.userAgent;
            var isIOS = /iPad|iPhone|iPod/.test(ua) ||
                        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            if (!isIOS) return false;

            var isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|Chrome/.test(ua);
            if (!isSafari) return false;

            var isStandalone = window.navigator.standalone === true ||
                               window.matchMedia('(display-mode: standalone)').matches;
            return !isStandalone;
        },

        init: function() {
            this.bellBtn = document.getElementById('aih-notify-btn');

            // Bind bell button click
            if (this.bellBtn) {
                var self = this;
                this.bellBtn.addEventListener('click', function() {
                    self.handleBellClick();
                });
            }

            // Check if push is supported
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                // iOS Safari (non-standalone): show bell with install prompt instead of hiding it
                if (this.isIOSSafariNonStandalone()) {
                    this.updateBellState('default');
                    this.maybeAutoShowIOSBanner();
                } else {
                    this.updateBellState('unsupported');
                }
                this.startPolling();
                return;
            }

            var self = this;

            // Update bell to current permission state immediately
            this.updateBellState(Notification.permission);

            // Always start polling as a safety net — push may fail silently
            self.startPolling();

            navigator.serviceWorker.register(aihAjax.swUrl, { scope: '/' })
                .then(function(registration) {
                    self.swRegistration = registration;
                    return registration.pushManager.getSubscription();
                })
                .then(function(subscription) {
                    if (subscription) {
                        self.pushSubscribed = true;
                        self.syncSubscription(subscription);
                        self.updateBellState('granted');
                    } else if (Notification.permission === 'granted') {
                        // Permission granted but not subscribed — re-subscribe
                        self.subscribe();
                    }
                })
                .catch(function() {
                    // Service worker failed — polling already running
                });
        },

        // ========== BELL BUTTON STATE ==========

        /**
         * Update the header bell button appearance.
         * States: 'default', 'granted', 'denied', 'unsupported'
         */
        updateBellState: function(state) {
            if (!this.bellBtn) return;

            this.bellBtn.classList.remove(
                'aih-notify-default',
                'aih-notify-granted',
                'aih-notify-denied',
                'aih-notify-unsupported'
            );

            switch (state) {
                case 'granted':
                    this.bellBtn.classList.add('aih-notify-granted');
                    this.bellBtn.title = aihAjax.strings.notifyEnabled || 'Notifications enabled';
                    break;
                case 'denied':
                    this.bellBtn.classList.add('aih-notify-denied');
                    this.bellBtn.title = aihAjax.strings.notifyDenied || 'Notifications blocked — check browser settings';
                    break;
                case 'unsupported':
                    this.bellBtn.classList.add('aih-notify-unsupported');
                    this.bellBtn.title = aihAjax.strings.notifyUnsupported || 'Notifications not supported';
                    break;
                default:
                    this.bellBtn.classList.add('aih-notify-default');
                    this.bellBtn.title = aihAjax.strings.notifyEnable || 'Enable notifications';
                    break;
            }
        },

        /**
         * Handle bell button click
         */
        handleBellClick: function() {
            // iOS Safari (non-standalone): show install banner instead of error
            if (this.isIOSSafariNonStandalone()) {
                this.showIOSInstallBanner();
                return;
            }

            if (!('Notification' in window) || !('PushManager' in window)) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Your browser does not support push notifications.', 'error');
                }
                return;
            }

            var permission = Notification.permission;

            if (permission === 'granted') {
                if (this.pushSubscribed) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Notifications are already enabled!', 'success');
                    }
                } else {
                    // Granted but not subscribed — try subscribing
                    this.subscribe();
                }
                return;
            }

            if (permission === 'denied') {
                this.showDeniedHelp();
                return;
            }

            // permission === 'default' — request it
            this.requestPermission();
        },

        // ========== DENIED HELP MODAL ==========

        /**
         * Show a help modal with browser-specific instructions to unblock notifications.
         */
        showDeniedHelp: function() {
            if ($('#aih-notify-help').length) {
                $('#aih-notify-help').addClass('active');
                return;
            }

            var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
            var isAndroid = /Android/.test(navigator.userAgent);
            var isChrome = /Chrome/.test(navigator.userAgent) && !/Edg/.test(navigator.userAgent);
            var isSafari = /Safari/.test(navigator.userAgent) && !isChrome;
            var isFirefox = /Firefox/.test(navigator.userAgent);

            var steps;
            if (isIOS && isSafari) {
                steps =
                    '<li>Open <strong>Settings</strong> on your device</li>' +
                    '<li>Scroll down and tap <strong>Safari</strong></li>' +
                    '<li>Tap <strong>Notifications</strong></li>' +
                    '<li>Find this website and toggle notifications <strong>On</strong></li>' +
                    '<li>Return here and refresh the page</li>';
            } else if (isIOS) {
                steps =
                    '<li>Open <strong>Settings</strong> on your device</li>' +
                    '<li>Scroll down and tap your browser app</li>' +
                    '<li>Tap <strong>Notifications</strong> and enable them</li>' +
                    '<li>Return here and refresh the page</li>';
            } else if (isAndroid && isChrome) {
                steps =
                    '<li>Tap the <strong>lock icon</strong> in the address bar</li>' +
                    '<li>Tap <strong>Permissions</strong></li>' +
                    '<li>Set <strong>Notifications</strong> to <strong>Allow</strong></li>' +
                    '<li>Refresh the page</li>';
            } else if (isChrome) {
                steps =
                    '<li>Click the <strong>tune/lock icon</strong> in the address bar</li>' +
                    '<li>Find <strong>Notifications</strong></li>' +
                    '<li>Change from <strong>Block</strong> to <strong>Allow</strong></li>' +
                    '<li>Refresh the page</li>';
            } else if (isFirefox) {
                steps =
                    '<li>Click the <strong>lock icon</strong> in the address bar</li>' +
                    '<li>Click <strong>Connection secure</strong> &rarr; <strong>More information</strong></li>' +
                    '<li>Go to <strong>Permissions</strong> tab</li>' +
                    '<li>Find <strong>Send Notifications</strong> and click <strong>Allow</strong></li>' +
                    '<li>Refresh the page</li>';
            } else if (isSafari) {
                steps =
                    '<li>Click <strong>Safari</strong> in the menu bar &rarr; <strong>Settings</strong></li>' +
                    '<li>Go to the <strong>Websites</strong> tab</li>' +
                    '<li>Click <strong>Notifications</strong> in the sidebar</li>' +
                    '<li>Find this website and change to <strong>Allow</strong></li>' +
                    '<li>Refresh the page</li>';
            } else {
                steps =
                    '<li>Click the <strong>lock/info icon</strong> in the address bar</li>' +
                    '<li>Find <strong>Notifications</strong> in site permissions</li>' +
                    '<li>Change from <strong>Block</strong> to <strong>Allow</strong></li>' +
                    '<li>Refresh the page</li>';
            }

            var $modal = $(
                '<div id="aih-notify-help" class="aih-notify-help-overlay active">' +
                    '<div class="aih-notify-help-card">' +
                        '<button type="button" class="aih-notify-help-close" aria-label="Close">&times;</button>' +
                        '<h3>Notifications are blocked</h3>' +
                        '<p>To receive outbid alerts, enable notifications for this site:</p>' +
                        '<ol>' + steps + '</ol>' +
                    '</div>' +
                '</div>'
            );

            function closeHelp() {
                $modal.removeClass('active');
            }

            $modal.find('.aih-notify-help-close').on('click', closeHelp);
            $modal.on('click', function(e) {
                if (e.target === this) closeHelp();
            });

            $('body').append($modal);
        },

        // ========== iOS INSTALL BANNER ==========

        /**
         * Show bottom-sheet modal guiding iOS Safari users to Add to Home Screen.
         */
        showIOSInstallBanner: function() {
            if ($('#aih-ios-install').length) {
                $('#aih-ios-install').addClass('active');
                return;
            }

            // Inline SVG for iOS share icon
            var shareIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>';

            var $modal = $(
                '<div id="aih-ios-install" class="aih-ios-install-overlay active">' +
                    '<div class="aih-ios-install-card">' +
                        '<button type="button" class="aih-ios-install-close" aria-label="Close">&times;</button>' +
                        '<div class="aih-ios-install-icon">' +
                            '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>' +
                        '</div>' +
                        '<h3>Get Instant Bid Alerts</h3>' +
                        '<p>Add this app to your Home Screen to receive push notifications when you\'re outbid.</p>' +
                        '<ol class="aih-ios-install-steps">' +
                            '<li>Tap the <strong>Share</strong> button ' + shareIcon + ' in Safari</li>' +
                            '<li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>' +
                            '<li>Tap <strong>"Add"</strong> and open the app from your Home Screen</li>' +
                        '</ol>' +
                    '</div>' +
                '</div>'
            );

            var self = this;
            function closeBanner() {
                $modal.removeClass('active');
                try {
                    localStorage.setItem('aih_ios_banner_dismissed', Date.now().toString());
                } catch (e) {}
            }

            $modal.find('.aih-ios-install-close').on('click', closeBanner);
            $modal.on('click', function(e) {
                if (e.target === this) closeBanner();
            });

            $('body').append($modal);
        },

        /**
         * Auto-show the iOS install banner once, with a 7-day cooldown.
         */
        maybeAutoShowIOSBanner: function() {
            try {
                var dismissed = localStorage.getItem('aih_ios_banner_dismissed');
                if (dismissed) {
                    var elapsed = Date.now() - parseInt(dismissed, 10);
                    if (elapsed < 7 * 24 * 60 * 60 * 1000) return; // 7 days
                }
            } catch (e) {}

            var self = this;
            setTimeout(function() {
                self.showIOSInstallBanner();
            }, 3000);
        },

        // ========== PERMISSION & SUBSCRIPTION ==========

        /**
         * Request notification permission and subscribe.
         * Called from: bell button click, after bid.
         */
        requestPermission: function() {
            if (this.pushSubscribed) {
                return;
            }

            if (!this.swRegistration) {
                return;
            }

            var self = this;

            Notification.requestPermission().then(function(permission) {
                self.updateBellState(permission);

                if (permission === 'granted') {
                    self.subscribe();
                    if (typeof window.showToast === 'function') {
                        window.showToast('Notifications enabled! You\'ll be alerted when outbid.', 'success');
                    }
                }
                // If denied, polling is already running
            });
        },

        /**
         * Called after a successful bid to prompt if permission is still default.
         */
        promptAfterBid: function() {
            if (this.pushSubscribed) return;
            if (!('Notification' in window)) return;
            if (Notification.permission !== 'default') return;
            if (!this.swRegistration) return;

            var self = this;
            setTimeout(function() { self.requestPermission(); }, 2000);
        },

        /**
         * Subscribe to push notifications via PushManager
         */
        subscribe: function() {
            if (!this.swRegistration) {
                return;
            }

            var self = this;
            var applicationServerKey = this.urlBase64ToUint8Array(aihAjax.vapidPublicKey);

            this.swRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            })
            .then(function(subscription) {
                self.pushSubscribed = true;
                self.syncSubscription(subscription);
                self.updateBellState('granted');
                // Polling continues as safety net
            })
            .catch(function() {
                // Permission denied or error — polling continues
                self.updateBellState(Notification.permission);
            });
        },

        /**
         * Sync subscription to server
         */
        syncSubscription: function(subscription) {
            var key   = subscription.getKey('p256dh');
            var auth  = subscription.getKey('auth');

            aihPost('push-subscribe', {
                action:   'aih_push_subscribe',
                nonce:    aihAjax.nonce,
                endpoint: subscription.endpoint,
                p256dh:   key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '',
                auth:     auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : ''
            });
        },

        // ========== POLLING FALLBACK ==========

        /**
         * Smart polling interval based on soonest-ending auction.
         * Polls more frequently as auctions near their end.
         */
        getSmartInterval: function() {
            var soonest = Infinity;
            $('.aih-card[data-end]').each(function() {
                var $c = $(this);
                if ($c.hasClass('ended') || $c.hasClass('won') || $c.hasClass('paid')) return;
                var endMs = new Date($c.attr('data-end').replace(/-/g, '/')).getTime();
                var remaining = endMs - Date.now();
                if (remaining > 0 && remaining < soonest) soonest = remaining;
            });

            // Also check single-item page timer
            var $single = $('.aih-time-remaining-single[data-end]');
            if ($single.length) {
                var endMs = new Date($single.attr('data-end').replace(/-/g, '/')).getTime();
                var remaining = endMs - Date.now();
                if (remaining > 0 && remaining < soonest) soonest = remaining;
            }

            if (soonest < 60000) return 2000;       // < 1 min: every 2s
            if (soonest < 300000) return 5000;       // < 5 min: every 5s
            if (soonest < 3600000) return 10000;     // < 1 hour: every 10s
            return 30000;                             // > 1 hour: every 30s
        },

        /**
         * Polling fallback: check for outbid events using smart intervals
         */
        pollOutbid: function() {
            if (window.aihSSEConnected) return; // SSE handles real-time outbid notifications
            console.log('[AIH] Polling for outbid events (fallback)');
            var self = this;
            aihPost('check-outbid', {
                action: 'aih_check_outbid',
                nonce:  aihAjax.nonce
            }, function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    for (var i = 0; i < response.data.length; i++) {
                        var evt = response.data[i];
                        // Dedup: skip events already shown this session
                        var eventKey = evt.art_piece_id + '_' + evt.time;
                        if (self.shownEvents[eventKey]) continue;
                        self.shownEvents[eventKey] = true;
                        // Show persistent alert card (falls back to toast)
                        console.log('[AIH] Outbid via POLLING:', evt.title, '(art #' + evt.art_piece_id + ')');
                        if (typeof window.showOutbidAlert === 'function') {
                            window.showOutbidAlert(evt.art_piece_id, evt.title);
                        } else if (typeof window.showToast === 'function') {
                            window.showToast('You\'ve been outbid on "' + evt.title + '"!', 'error');
                        }
                    }
                    // Trigger immediate status poll to update badges
                    if (typeof window.aihPollStatus === 'function') {
                        window.aihPollStatus();
                    }
                }
            });
        },

        startPolling: function() {
            if (this.pollTimer) clearTimeout(this.pollTimer);
            var self = this;
            var interval = document.hidden ? 60000 : this.getSmartInterval();
            this.pollTimer = setTimeout(function() {
                self.pollOutbid();
                self.startPolling();
            }, interval);
        },

        stopPolling: function() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        /**
         * Convert a base64-URL string to a Uint8Array for applicationServerKey
         */
        urlBase64ToUint8Array: function(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; i++) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
    };

    // Initialize
    $(document).ready(function() {
        AIHPush.init();
    });

    // Pause/resume polling on tab visibility change
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            AIHPush.stopPolling();
            AIHPush.pollTimer = setTimeout(function() {
                AIHPush.pollOutbid();
            }, 60000);
        } else {
            AIHPush.stopPolling();
            AIHPush.pollOutbid();
            AIHPush.startPolling();
        }
    });

    // Expose for external triggering (e.g. after bid, from other scripts)
    window.AIHPush = AIHPush;

})(jQuery);
