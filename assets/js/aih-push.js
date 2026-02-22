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
        pollingInterval: null,
        swRegistration: null,

        init: function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                this.startPolling();
                return;
            }

            var self = this;

            navigator.serviceWorker.register(aihAjax.swUrl, { scope: '/' })
                .then(function(registration) {
                    self.swRegistration = registration;
                    return registration.pushManager.getSubscription();
                })
                .then(function(subscription) {
                    if (subscription) {
                        self.pushSubscribed = true;
                        self.syncSubscription(subscription);
                    } else {
                        // No subscription yet — start polling until user grants permission
                        self.startPolling();
                    }
                })
                .catch(function() {
                    self.startPolling();
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

                // Stop polling — push is active
                if (self.pollingInterval) {
                    clearInterval(self.pollingInterval);
                    self.pollingInterval = null;
                }
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
                url: aihAjax.ajaxurl,
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
         * Polling fallback: check for outbid events every 5 seconds
         */
        startPolling: function() {
            if (this.pollingInterval) {
                return;
            }

            var self = this;

            this.pollingInterval = setInterval(function() {
                $.ajax({
                    url: aihAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aih_check_outbid',
                        nonce:  aihAjax.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.length > 0) {
                            for (var i = 0; i < response.data.length; i++) {
                                var evt = response.data[i];
                                var msg = 'You\'ve been outbid on "' + evt.title + '"!';
                                if (typeof window.showToast === 'function') {
                                    window.showToast(msg, 'error');
                                }
                            }
                        }
                    }
                });
            }, 5000);
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

    // Expose for external triggering (e.g. after first bid)
    window.AIHPush = AIHPush;

})(jQuery);
