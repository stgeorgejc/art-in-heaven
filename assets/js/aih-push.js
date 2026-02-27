/**
 * Art in Heaven - Push Notification Manager
 *
 * Handles push subscription, permission prompts, and polling fallback
 * for outbid notifications. Manages the header bell button state.
 */

// Capture beforeinstallprompt early — Chrome/Edge fire this before user interaction.
// Stored globally so the logged-in code below can use it for the Android install banner.
// Note: we do NOT call preventDefault() here — that would suppress Chrome's native
// install mini-infobar for all users (including unauthenticated ones who never see
// our custom banner). Letting the native UI show is the correct default; logged-in
// users still get our custom banner which calls prompt() explicitly.
var aihDeferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', function(e) {
    aihDeferredInstallPrompt = e;
    // If AIHPush already initialized, trigger the banner
    if (window.AIHPush && window.AIHPush.initialized) {
        window.AIHPush.maybeAutoShowAndroidBanner();
    }
});

(function($) {
    'use strict';

    // Only activate for logged-in bidders
    if (!window.aihAjax || !aihAjax.isLoggedIn || !aihAjax.bidderId) {
        // Hide bell button for logged-out users
        $('#aih-notify-btn').hide();
        return;
    }

    var AIHPush = {
        initialized: false,
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

            // Android/Chrome install prompt (works alongside push — independent concern)
            if (aihDeferredInstallPrompt) {
                this.maybeAutoShowAndroidBanner();
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
                this.initialized = true;
                return;
            }

            var self = this;

            // Update bell to current permission state immediately
            this.updateBellState(Notification.permission);

            // Always start polling as a safety net — push may fail silently
            self.startPolling();

            this.initialized = true;

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
                        // Verify subscription still exists server-side
                        self.verifySubscription(subscription);
                    } else if (Notification.permission === 'granted') {
                        // Permission granted but not subscribed — re-subscribe
                        self.subscribe();
                    }
                })
                .catch(function() {
                    // Service worker failed — polling already running
                });

            // Listen for messages from the service worker (push → page bridge)
            navigator.serviceWorker.addEventListener('message', function(event) {
                if (!event.data || event.data.type !== 'aih-push') return;
                var payload = event.data.data;
                if (!payload) return;

                // Dedup: skip if we already showed this via SSE/polling
                var dedupeKey = (payload.type || '') + '-' + (payload.art_piece_id || '');
                if (self.shownEvents[dedupeKey]) return;
                self.shownEvents[dedupeKey] = true;

                if (payload.type === 'outbid' && typeof window.showOutbidAlert === 'function') {
                    var title = payload.title || 'an item';
                    window.showOutbidAlert(payload.art_piece_id, title, payload.url);
                }

                // Trigger status poll for fresh badge/state data
                if (typeof window.aihPollStatus === 'function') {
                    window.aihPollStatus();
                }
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
                    // Open notification drawer
                    if (typeof window.aihToggleDrawer === 'function') {
                        window.aihToggleDrawer();
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
                            '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                        '</div>' +
                        '<h3>Install Art in Heaven</h3>' +
                        '<p>Get instant bid alerts on your phone — just 3 quick steps:</p>' +
                        '<ol class="aih-ios-install-steps">' +
                            '<li>Tap the <strong>Share</strong> button ' + shareIcon + ' in Safari</li>' +
                            '<li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>' +
                            '<li>Tap <strong>"Add"</strong> to install</li>' +
                        '</ol>' +
                        '<button type="button" class="aih-install-btn aih-ios-acknowledge-btn">Got it!</button>' +
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
            $modal.find('.aih-ios-acknowledge-btn').on('click', closeBanner);
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

        // ========== ANDROID INSTALL BANNER ==========

        /**
         * Show bottom-sheet install banner for Android/Chrome with a real "Install" button.
         * Uses the deferred beforeinstallprompt event to trigger the native install dialog.
         */
        showAndroidInstallBanner: function() {
            if (!aihDeferredInstallPrompt) return;
            if ($('#aih-android-install').length) {
                $('#aih-android-install').addClass('active');
                return;
            }

            var $modal = $(
                '<div id="aih-android-install" class="aih-ios-install-overlay active">' +
                    '<div class="aih-ios-install-card">' +
                        '<button type="button" class="aih-ios-install-close" aria-label="Close">&times;</button>' +
                        '<div class="aih-ios-install-icon">' +
                            '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                        '</div>' +
                        '<h3>Install Art in Heaven</h3>' +
                        '<p>Add the app to your home screen for a faster experience and instant bid alerts.</p>' +
                        '<button type="button" class="aih-install-btn aih-android-install-btn">Install App</button>' +
                        '<button type="button" class="aih-install-dismiss-btn">Not now</button>' +
                    '</div>' +
                '</div>'
            );

            function closeBanner() {
                $modal.removeClass('active');
                try {
                    localStorage.setItem('aih_android_banner_dismissed', Date.now().toString());
                } catch (e) {}
            }

            $modal.find('.aih-ios-install-close').on('click', closeBanner);
            $modal.find('.aih-install-dismiss-btn').on('click', closeBanner);
            $modal.on('click', function(e) {
                if (e.target === this) closeBanner();
            });

            $modal.find('.aih-android-install-btn').on('click', function() {
                if (!aihDeferredInstallPrompt) return;
                try {
                    aihDeferredInstallPrompt.prompt();
                    aihDeferredInstallPrompt.userChoice.then(function() {
                        aihDeferredInstallPrompt = null;
                        closeBanner();
                    }).catch(function() {
                        aihDeferredInstallPrompt = null;
                        closeBanner();
                    });
                } catch (e) {
                    aihDeferredInstallPrompt = null;
                    closeBanner();
                }
            });

            $('body').append($modal);
        },

        /**
         * Auto-show the Android install banner once, with a 7-day cooldown.
         */
        maybeAutoShowAndroidBanner: function() {
            if (window.matchMedia('(display-mode: standalone)').matches) return;

            try {
                var dismissed = localStorage.getItem('aih_android_banner_dismissed');
                if (dismissed) {
                    var elapsed = Date.now() - parseInt(dismissed, 10);
                    if (elapsed < 7 * 24 * 60 * 60 * 1000) return; // 7 days
                }
            } catch (e) {}

            var self = this;
            setTimeout(function() {
                self.showAndroidInstallBanner();
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

        /**
         * Verify subscription exists server-side; re-subscribe if stale.
         */
        verifySubscription: function(subscription) {
            var self = this;
            aihPost('push-verify', {
                action:   'aih_push_verify',
                nonce:    aihAjax.nonce,
                endpoint: subscription.endpoint
            }, function(response) {
                // Server knows this subscription — all good
            }, function() {
                // Server doesn't recognize it — unsubscribe and re-subscribe
                console.warn('[AIH] Push subscription unknown to server — re-subscribing');
                subscription.unsubscribe().then(function() {
                    self.pushSubscribed = false;
                    self.subscribe();
                });
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
            console.log('[AIH] Polling for outbid events' + (window.aihSSEConnected ? ' (background check)' : ' (fallback)'));
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
            // Use slower interval when SSE is connected (background safety net)
            var interval = document.hidden ? 60000 :
                           (window.aihSSEConnected ? 60000 : this.getSmartInterval());
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

    // Offline/online detection — dispatch connection status changes
    window.addEventListener('offline', function() {
        window.aihConnectionStatus = 'offline';
        window.dispatchEvent(new CustomEvent('aih:connectionchange', { detail: { status: 'offline' } }));
    });

    window.addEventListener('online', function() {
        // Go back to polling; SSE will promote to 'realtime' if it reconnects
        window.aihConnectionStatus = 'polling';
        window.dispatchEvent(new CustomEvent('aih:connectionchange', { detail: { status: 'polling' } }));
    });

    // Set initial connection status
    window.aihConnectionStatus = navigator.onLine ? 'polling' : 'offline';

    // Expose for external triggering (e.g. after bid, from other scripts)
    window.AIHPush = AIHPush;

})(jQuery);
