/**
 * Art in Heaven - Admin JavaScript
 * Silent Auction Admin JavaScript
 * Note: Form handlers for add/edit art are in the template itself
 */

(function($) {
    'use strict';
    
    // Bulk selection - Select All
    $('#aih-select-all, #aih-select-all-top').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.aih-art-checkbox').prop('checked', isChecked);
        $('#aih-select-all, #aih-select-all-top').prop('checked', isChecked);
        updateSelectedCount();
    });
    
    // Individual checkbox change
    $('.aih-art-checkbox').on('change', function() {
        updateSelectedCount();
        
        // Update select all checkbox
        var total = $('.aih-art-checkbox').length;
        var checked = $('.aih-art-checkbox:checked').length;
        $('#aih-select-all, #aih-select-all-top').prop('checked', total === checked);
    });
    
    // Update selected count
    function updateSelectedCount() {
        var count = $('.aih-art-checkbox:checked').length;
        $('#aih-selected-num').text(count);
        $('#aih-bulk-time-btn').prop('disabled', count === 0);
    }
    
    // Bulk time change - Open modal
    $('#aih-bulk-time-btn').on('click', function() {
        if ($('.aih-art-checkbox:checked').length === 0) {
            alert('Please select at least one art piece.');
            return;
        }
        
        // Set default datetime to 7 days from now
        var defaultDate = new Date();
        defaultDate.setDate(defaultDate.getDate() + 7);
        var dateStr = defaultDate.toISOString().slice(0, 16);
        $('#aih-bulk-datetime').val(dateStr);
        
        $('#aih-bulk-time-modal').fadeIn(200);
    });
    
    // Close modal
    $('.aih-modal-close, #aih-cancel-bulk-time').on('click', function() {
        $('#aih-bulk-time-modal').fadeOut(200);
    });
    
    // Close modal on overlay click
    $('#aih-bulk-time-modal').on('click', function(e) {
        if ($(e.target).is('#aih-bulk-time-modal')) {
            $(this).fadeOut(200);
        }
    });
    
    // Apply bulk time change
    $('#aih-apply-bulk-time').on('click', function() {
        var ids = [];
        $('.aih-art-checkbox:checked').each(function() {
            ids.push($(this).val());
        });
        
        var newTime = $('#aih-bulk-datetime').val();
        if (!newTime) {
            alert('Please select a date and time.');
            return;
        }
        
        var $btn = $(this);
        
        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_bulk_update_times',
                nonce: aihAdmin.nonce,
                ids: ids,
                new_end_time: newTime.replace('T', ' ') + ':00'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Updating...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('Error updating end times.');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Apply Changes');
            }
        });
    });
    
    // Escape key closes modal
    $(document).on('keyup', function(e) {
        if (e.key === 'Escape') {
            $('#aih-bulk-time-modal').fadeOut(200);
        }
    });
    
    // Export CSV
    $('#aih-export-csv').on('click', function() {
        var data = [];
        data.push(['Art ID', 'Title', 'Artist', 'Status', 'Unique Bidders', 'Total Bids', 'Current Bid']);
        
        $('.aih-stats-table tbody tr').each(function() {
            var row = [];
            row.push($(this).find('.aih-col-id code').text().trim());
            row.push($(this).find('.aih-col-title strong').text().trim());
            row.push($(this).find('.aih-col-title small').text().trim());
            row.push($(this).find('.aih-status-badge').text().trim());
            row.push($(this).find('td:eq(4) .aih-stat-value').text().trim());
            row.push($(this).find('td:eq(5) .aih-stat-value').text().trim());
            row.push($(this).find('.aih-bid-amount').text().trim());
            data.push(row);
        });
        
        var csv = data.map(function(row) {
            return row.map(function(cell) {
                // Escape quotes and wrap in quotes
                return '"' + String(cell).replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\n');
        
        // Add BOM for Excel compatibility
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'silent-auction-stats-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Initialize selected count on page load
    updateSelectedCount();

})(jQuery);

/**
 * Analytics Live Polling — auto-refreshes overview data every 30 seconds.
 * Only runs on the analytics page (overview tab).
 */
(function($) {
    'use strict';

    // Only run on the analytics overview tab.
    if (!$('#aih-alerts-panel, #aih-auction-pulse, [data-stat="sell-through"]').length) {
        return;
    }

    var POLL_INTERVAL = 30000; // 30 seconds
    var pollTimer = null;
    var isPolling = false;

    /**
     * Update a stat card's displayed value and optional sublabel.
     */
    function updateStatCard(key, value, sublabel) {
        var $card = $('[data-stat="' + key + '"]');
        if (!$card.length) return;
        $card.find('.aih-stat-number').html(value);
        if (sublabel !== undefined && sublabel !== null) {
            var $sub = $card.find('.aih-stat-sublabel');
            if ($sub.length) {
                $sub.html(sublabel);
            }
        }
    }

    /**
     * Update chart data and redraw.
     */
    function updateChart(chartName, data) {
        if (!window.aihCharts || !window.aihCharts[chartName]) return;
        var chart = window.aihCharts[chartName];

        if (chartName === 'timeline') {
            chart.data.labels = data.labels;
            chart.data.datasets[0].data = data.organic;
            chart.data.datasets[1].data = data.push;
        } else if (chartName === 'inventory') {
            chart.data.datasets[0].data = [data.sold];
            chart.data.datasets[1].data = [data.active_bids];
            chart.data.datasets[2].data = [data.active_no_bids];
            chart.data.datasets[3].data = [data.unsold];
        }

        chart.update();
    }

    /**
     * Rebuild the Auction Pulse widget HTML.
     */
    function updatePulse(pulse) {
        var $pulse = $('#aih-auction-pulse');
        if (!$pulse.length) return;

        var statusLabels = { hot: 'Hot', warm: 'Warm', cooling: 'Cooling' };
        $pulse.find('.aih-pulse-dot').attr('class', 'aih-pulse-dot ' + pulse.status)
              .attr('title', statusLabels[pulse.status] || '');
        $pulse.find('.aih-pulse-label').text(statusLabels[pulse.status] || '');

        updateStatCard('pulse-5m', Number(pulse.bids_5m).toLocaleString());
        updateStatCard('pulse-15m', Number(pulse.bids_15m).toLocaleString());
        updateStatCard('pulse-60m', Number(pulse.bids_60m).toLocaleString());
    }

    /**
     * Rebuild the Alerts panel.
     */
    function updateAlerts(alerts) {
        var $panel = $('#aih-alerts-panel');
        if (!alerts || !alerts.length) {
            $panel.remove();
            return;
        }
        var html = '';
        $.each(alerts, function(_, alert) {
            html += '<div class="notice notice-warning inline"><p>'
                 + '<strong>' + $('<span>').text(alert.title).html() + '</strong> '
                 + '<span class="aih-alert-count">(' + parseInt(alert.count, 10) + ')</span>'
                 + '</p></div>';
        });
        if ($panel.length) {
            $panel.html(html);
        } else {
            // Insert before hero stat cards.
            $('[data-stat="sell-through"]').closest('.aih-stat-grid').before(
                '<div class="aih-alerts-panel" id="aih-alerts-panel">' + html + '</div>'
            );
        }
    }

    /**
     * Rebuild the Urgency Board table body.
     */
    function updateUrgency(items) {
        var $board = $('#aih-urgency-board');
        if (!items || !items.length) {
            $board.remove();
            return;
        }
        var rows = '';
        $.each(items, function(_, item) {
            var mins = Math.floor(item.seconds_remaining / 60);
            var hrs = Math.floor(mins / 60);
            var rem = mins % 60;
            var timeStr = hrs > 0 ? hrs + 'h ' + rem + 'm' : mins + 'm';
            var cls = item.total_bids === 0 ? ' class="aih-urgency-zero"' : '';
            rows += '<tr' + cls + '>'
                 + '<td>' + $('<span>').text(item.art_id).html() + '</td>'
                 + '<td>' + $('<span>').text(item.title).html() + '</td>'
                 + '<td>' + timeStr + '</td>'
                 + '<td>' + parseInt(item.total_bids, 10) + '</td>'
                 + '</tr>';
        });
        if ($board.length) {
            $board.find('tbody').html(rows);
        } else {
            // Create the widget on first poll when it wasn't server-rendered.
            var html = '<div class="postbox aih-urgency-board" id="aih-urgency-board" style="margin-top: 24px;">'
                     + '<h2 class="hndle"><span>Ending Soon (&lt; 2 hours)</span></h2>'
                     + '<div class="inside"><table class="widefat striped">'
                     + '<thead><tr><th>Art ID</th><th>Title</th><th>Time Left</th><th>Bids</th></tr></thead>'
                     + '<tbody>' + rows + '</tbody></table></div></div>';
            $('.aih-report-sections').before(html);
        }
    }

    /**
     * Rebuild the Live Bid Feed list.
     */
    function updateBidFeed(feed) {
        var $feed = $('#aih-bid-feed');
        if (!feed || !feed.length) {
            $feed.remove();
            return;
        }
        var items = '';
        $.each(feed, function(_, entry) {
            items += '<li>'
                  + '<span class="aih-feed-time">' + $('<span>').text(entry.time_ago).html() + '</span> '
                  + '<span class="aih-feed-bidder">' + $('<span>').text(entry.bidder_masked).html() + '</span> '
                  + 'bid on '
                  + '<strong>' + $('<span>').text(entry.piece_title).html() + '</strong>';
            if (entry.amount !== undefined) {
                items += ' <span class="aih-feed-amount">$' + parseFloat(entry.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span>';
            }
            items += '</li>';
        });
        if ($feed.length) {
            $feed.find('.aih-bid-feed-list').html(items);
        } else {
            // Create the widget on first poll when it wasn't server-rendered.
            var html = '<div class="postbox aih-bid-feed" id="aih-bid-feed" style="margin-top: 24px;">'
                     + '<h2 class="hndle"><span>Live Bid Feed</span></h2>'
                     + '<div class="inside"><ul class="aih-bid-feed-list">' + items + '</ul></div></div>';
            // Insert before summary tables, or after urgency board if it exists.
            var $urgency = $('#aih-urgency-board');
            if ($urgency.length) {
                $urgency.after(html);
            } else {
                $('.aih-report-sections').before(html);
            }
        }
    }

    /**
     * Perform a single poll cycle.
     */
    function poll() {
        if (isPolling) return;
        isPolling = true;

        $.ajax({
            url: aihAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'aih_admin_analytics_live',
                nonce: aihAdmin.nonce,
                tab: 'overview'
            },
            success: function(response) {
                if (!response.success || !response.data) return;
                var d = response.data;
                var o = d.overview || {};

                // Update overview stat cards.
                updateStatCard('sell-through', o.sell_through + '%',
                    o.pieces_with_bids + ' of ' + o.total_pieces + ' pieces');
                if (o.total_revenue !== undefined) {
                    updateStatCard('total-revenue', '$' + parseFloat(o.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                }
                updateStatCard('unique-bidders', Number(o.unique_bidders).toLocaleString());
                updateStatCard('active-auctions', Number(o.active_auctions).toLocaleString());

                // Health cards.
                updateStatCard('avg-bids', o.avg_bids_per_piece);
                if (o.rev_vs_starting !== undefined) {
                    updateStatCard('rev-vs-starting', o.rev_vs_starting + '%');
                }
                updateStatCard('single-bid', Number(o.single_bid_count).toLocaleString());
                updateStatCard('last-bid', o.last_bid_display);
                // Update the .aih-stat-detail element (timestamp) which is separate from sublabel.
                $('[data-stat="last-bid"]').find('.aih-stat-detail').text(o.last_bid_time || '');
                updateStatCard('repeat-bidders', o.repeat_bidder_rate + '%',
                    o.repeat_bidders + ' of ' + o.total_bidders + ' bidders');

                // Widgets.
                if (d.pulse) updatePulse(d.pulse);
                if (d.alerts !== undefined) updateAlerts(d.alerts);
                if (d.urgency !== undefined) updateUrgency(d.urgency);
                if (d.bid_feed !== undefined) updateBidFeed(d.bid_feed);

                // Charts.
                if (d.charts) {
                    if (d.charts.timeline) updateChart('timeline', d.charts.timeline);
                    if (d.charts.inventory) updateChart('inventory', d.charts.inventory);
                }
            },
            complete: function() {
                isPolling = false;
                schedulePoll();
            }
        });
    }

    /**
     * Schedule next poll via setTimeout (avoids stacking).
     */
    function schedulePoll() {
        pollTimer = setTimeout(poll, POLL_INTERVAL);
    }

    /**
     * Visibility API — pause when tab hidden, resume when visible.
     */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearTimeout(pollTimer);
            pollTimer = null;
        } else {
            // Immediately poll on tab focus, then resume schedule.
            poll();
        }
    });

    // Immediate first poll to populate live widgets, then schedule recurring.
    poll();

})(jQuery);
