/**
 * Art in Heaven - Push Notification Manager
 *
 * Handles push subscription, permission prompts, and polling fallback
 * for outbid notifications.
 */

(function($) {
    'use strict';

    // Only activate for logged-in bidders
    if (!window.aihAjax || !aihAjax.isLoggedIn || !aihAjax.bidderId) {
        return;
    }

    var AIHPush = {
        pushSubscribed: false,
        pollTimer: null,
        swRegistration: null,
        shownEvents: {},

        init: function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.startPolling();
                return;
            }

            var self = this;

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
                    }
                })
                .catch(function() {
                    // Service worker failed — polling already running
                });
        },

        /**
         * Request notification permission and subscribe.
         * Called after first successful bid (with a 2s delay).
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
                if (permission === 'granted') {
                    self.subscribe();
                }
                // If denied, polling is already running
            });
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
                // Polling continues as safety net
            })
            .catch(function() {
                // Permission denied or error — polling continues
            });
        },

        /**
         * Sync subscription to server
         */
        syncSubscription: function(subscription) {
            var key   = subscription.getKey('p256dh');
            var auth  = subscription.getKey('auth');

            $.ajax({
                url: aihApiUrl('push-subscribe'),
                type: 'POST',
                data: {
                    action:   'aih_push_subscribe',
                    nonce:    aihAjax.nonce,
                    endpoint: subscription.endpoint,
                    p256dh:   key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '',
                    auth:     auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : ''
                }
            });
        },

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
            var self = this;
            $.ajax({
                url: aihApiUrl('check-outbid'),
                type: 'POST',
                data: {
                    action: 'aih_check_outbid',
                    nonce:  aihAjax.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        for (var i = 0; i < response.data.length; i++) {
                            var evt = response.data[i];
                            var eventKey = evt.art_piece_id + '_' + evt.time;
                            if (self.shownEvents[eventKey]) continue;
                            self.shownEvents[eventKey] = true;
                            var msg = 'You\'ve been outbid on "' + evt.title + '"!';
                            if (typeof window.showToast === 'function') {
                                window.showToast(msg, 'error');
                            }
                        }
                        // Trigger immediate status poll to update badges
                        if (typeof window.aihPollStatus === 'function') {
                            window.aihPollStatus();
                        }
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

    // Expose for external triggering (e.g. after first bid)
    window.AIHPush = AIHPush;

})(jQuery);
