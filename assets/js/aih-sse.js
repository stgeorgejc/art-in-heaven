/**
 * Art in Heaven - Server-Sent Events (Mercure) Integration
 *
 * Subscribes to real-time bid updates and outbid notifications
 * via Mercure hub using native EventSource. Falls back to polling
 * if SSE fails or Mercure is unavailable.
 *
 * @package ArtInHeaven
 * @since   1.0.0
 */
(function($) {
    'use strict';

    // Guard: only activate if Mercure config is available
    if (!window.aihAjax || !aihAjax.mercureUrl) {
        return;
    }

    var AIHSSE = {
        eventSource: null,
        connected: false,
        reconnectTimer: null,
        reconnectAttempts: 0,
        maxReconnectAttempts: 10,

        /**
         * Initialize SSE connection
         */
        init: function() {
            if (typeof EventSource === 'undefined') {
                return; // Browser doesn't support SSE
            }
            this.connect();
        },

        /**
         * Build EventSource URL with topic subscriptions
         */
        buildUrl: function() {
            var hubUrl = aihAjax.mercureUrl;
            var prefix = (aihAjax.siteUrl || '').replace(/\/$/, '');
            var url = new URL(hubUrl, window.location.origin);

            // Subscribe to all visible art piece topics
            var artIds = [];
            $('.aih-card[data-id]').each(function() {
                artIds.push(parseInt($(this).data('id')));
            });

            // Single item page
            var $wrapper = $('#aih-single-wrapper');
            if ($wrapper.length) {
                var pieceId = parseInt($wrapper.attr('data-piece-id'));
                if (pieceId) artIds.push(pieceId);
            }

            // Deduplicate
            var seen = {};
            artIds = artIds.filter(function(id) {
                if (seen[id] || !id) return false;
                seen[id] = true;
                return true;
            });

            for (var i = 0; i < artIds.length; i++) {
                url.searchParams.append('topic', prefix + '/auction/' + artIds[i]);
            }

            // Subscribe to private bidder channel if logged in
            if (aihAjax.bidderId) {
                url.searchParams.append('topic', prefix + '/bidder/' + aihAjax.bidderId);
            }

            return url.toString();
        },

        /**
         * Create EventSource connection to Mercure hub
         */
        connect: function() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }

            var url = this.buildUrl();

            // Don't connect if no topics to subscribe to
            if (url.indexOf('topic=') === -1) {
                return;
            }

            var self = this;

            try {
                this.eventSource = new EventSource(url, {
                    withCredentials: true
                });
            } catch (e) {
                this.onDisconnect();
                return;
            }

            this.eventSource.onopen = function() {
                self.connected = true;
                self.reconnectAttempts = 0;
                self.disablePolling();
            };

            this.eventSource.onmessage = function(event) {
                try {
                    var data = JSON.parse(event.data);
                    self.handleEvent(data);
                } catch (e) {
                    // Malformed event -- ignore
                }
            };

            this.eventSource.onerror = function() {
                self.onDisconnect();
            };
        },

        /**
         * Handle incoming SSE events
         */
        handleEvent: function(data) {
            if (!data || !data.type) return;

            switch (data.type) {
                case 'bid_update':
                    this.handleBidUpdate(data);
                    break;
                case 'outbid':
                    this.handleOutbid(data);
                    break;
                case 'auction_ended':
                    this.handleAuctionEnded(data);
                    break;
            }
        },

        /**
         * Handle real-time bid update (public topic)
         */
        handleBidUpdate: function(data) {
            var id = data.art_piece_id;

            // Gallery page: update card min bid
            var $card = $('.aih-card[data-id="' + id + '"]');
            if ($card.length) {
                var $input = $card.find('.aih-bid-input');
                if ($input.length && data.min_bid) {
                    $input.attr('min', data.min_bid).attr('placeholder',
                        '$' + parseFloat(data.min_bid).toFixed(0));
                    $input.data('min', data.min_bid);
                }
            }

            // Single item page: update min bid
            var $wrapper = $('#aih-single-wrapper');
            if ($wrapper.length && parseInt($wrapper.attr('data-piece-id')) === id) {
                var $bidInput = $('#bid-amount');
                if ($bidInput.length && data.min_bid) {
                    $bidInput.attr('min', data.min_bid).attr('placeholder',
                        '$' + parseFloat(data.min_bid).toFixed(0));
                    $bidInput.data('min', data.min_bid);
                }
            }

            // Trigger a status poll to update per-bidder state (winning/outbid badges)
            if (typeof window.aihPollStatus === 'function') {
                window.aihPollStatus();
            }
        },

        /**
         * Handle outbid notification (private topic)
         */
        handleOutbid: function(data) {
            var msg = 'You\'ve been outbid on "' + (data.title || 'an item') + '"!';
            if (typeof window.showToast === 'function') {
                window.showToast(msg, 'error');
            }
            // Trigger immediate status poll to update badges
            if (typeof window.aihPollStatus === 'function') {
                window.aihPollStatus();
            }
        },

        /**
         * Handle auction ended (public topic)
         */
        handleAuctionEnded: function(data) {
            // Trigger a status poll for authoritative state update
            if (typeof window.aihPollStatus === 'function') {
                window.aihPollStatus();
            }
        },

        /**
         * Signal polling to stop when SSE is connected
         */
        disablePolling: function() {
            window.aihSSEConnected = true;
        },

        /**
         * Re-enable polling on SSE disconnect
         */
        enablePolling: function() {
            window.aihSSEConnected = false;
        },

        /**
         * Handle disconnect with exponential backoff reconnection
         */
        onDisconnect: function() {
            this.connected = false;
            this.enablePolling();

            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }

            if (this.reconnectAttempts < this.maxReconnectAttempts) {
                var delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
                this.reconnectAttempts++;
                var self = this;
                this.reconnectTimer = setTimeout(function() {
                    self.connect();
                }, delay);
            }
            // After max attempts, polling stays as permanent fallback
        },

        /**
         * Clean up resources
         */
        destroy: function() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
            this.connected = false;
            this.enablePolling();
        }
    };

    // Initialize when DOM ready
    $(document).ready(function() {
        AIHSSE.init();
    });

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        AIHSSE.destroy();
    });

    // Pause SSE on tab hidden, resume on visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            // Close connection to free resources while tab hidden
            if (AIHSSE.eventSource) {
                AIHSSE.eventSource.close();
                AIHSSE.eventSource = null;
                AIHSSE.connected = false;
                AIHSSE.enablePolling();
            }
        } else {
            // Reconnect when tab becomes visible
            if (!AIHSSE.connected && !AIHSSE.eventSource) {
                AIHSSE.reconnectAttempts = 0;
                AIHSSE.connect();
            }
        }
    });

    // Expose for external access
    window.AIHSSE = AIHSSE;

})(jQuery);
