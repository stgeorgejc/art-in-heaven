/**
 * Art in Heaven - Admin JavaScript
 * Silent Auction Admin JavaScript
 * Note: Form handlers for add/edit art are in the template itself
 * Version: 0.9.7
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
