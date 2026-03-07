<?php
/**
 * QR Code Print Page
 *
 * Opened in a new tab from the Art Pieces bulk action.
 * Receives art IDs via sessionStorage (set by the art-pieces JS).
 * Generates QR codes server-side and renders them in a printable grid layout.
 */
if (!defined('ABSPATH')) exit;

$admin_nonce = wp_create_nonce('aih_admin_nonce');
?>
<div class="wrap" id="aih-qr-print-wrap">
    <h1><?php _e('Print QR Codes', 'art-in-heaven'); ?></h1>

    <div id="aih-qr-controls" class="aih-qr-controls">
        <label for="aih-qr-layout"><?php _e('Layout:', 'art-in-heaven'); ?></label>
        <select id="aih-qr-layout">
            <option value="1"><?php _e('1 per page (large)', 'art-in-heaven'); ?></option>
            <option value="4" selected><?php _e('4 per page (2x2)', 'art-in-heaven'); ?></option>
            <option value="6"><?php _e('6 per page (2x3)', 'art-in-heaven'); ?></option>
            <option value="9"><?php _e('9 per page (3x3)', 'art-in-heaven'); ?></option>
        </select>

        <button type="button" class="button button-primary" id="aih-qr-print-btn">
            <span class="dashicons dashicons-printer" style="vertical-align:middle;margin-top:-2px;"></span>
            <?php _e('Print', 'art-in-heaven'); ?>
        </button>

        <span id="aih-qr-status" style="margin-left:12px;color:#666;"></span>
    </div>

    <div id="aih-qr-empty" style="display:none;padding:40px;text-align:center;">
        <p><?php _e('No art pieces selected. Please go back to Art Pieces, select items, and click "Print QR Codes".', 'art-in-heaven'); ?></p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=art-in-heaven-art')); ?>" class="button"><?php _e('Go to Art Pieces', 'art-in-heaven'); ?></a>
    </div>

    <div id="aih-qr-grid" class="aih-qr-grid layout-4"></div>
</div>

<style>
/* ===== Screen styles ===== */
.aih-qr-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 0;
    margin-bottom: 20px;
    border-bottom: 1px solid #ccc;
}
.aih-qr-controls label { font-weight: 600; }
.aih-qr-grid {
    display: grid;
    gap: 24px;
}
.aih-qr-grid.layout-1 { grid-template-columns: 1fr; max-width: 600px; margin: 0 auto; }
.aih-qr-grid.layout-4 { grid-template-columns: repeat(2, 1fr); }
.aih-qr-grid.layout-6 { grid-template-columns: repeat(2, 1fr); }
.aih-qr-grid.layout-9 { grid-template-columns: repeat(3, 1fr); }

.aih-qr-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    background: #fff;
    break-inside: avoid;
    page-break-inside: avoid;
}
.aih-qr-card img {
    width: 100%;
    max-width: 100%;
    height: auto;
    display: block;
    margin: 0 auto 12px;
}
.aih-qr-card .aih-qr-label {
    font-weight: 700;
    font-size: 16px;
    margin: 0 0 4px;
    line-height: 1.3;
}
.aih-qr-card .aih-qr-title {
    font-size: 13px;
    color: #555;
    margin: 0;
    line-height: 1.3;
}
.aih-qr-card .aih-qr-spinner {
    padding: 40px 0;
    color: #999;
}

/* Layout-specific card sizing */
.aih-qr-grid.layout-1 .aih-qr-card img { max-width: 500px; }
.aih-qr-grid.layout-9 .aih-qr-card { padding: 12px; }
.aih-qr-grid.layout-9 .aih-qr-label { font-size: 13px; }
.aih-qr-grid.layout-9 .aih-qr-title { font-size: 11px; }

/* ===== Print styles ===== */
@media print {
    /* Hide WP admin chrome */
    #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap,
    #wpfooter, .aih-qr-controls, .update-nag, .notice,
    .aih-skip-link, #screen-meta, #screen-meta-links { display: none !important; }

    #wpcontent, #wpbody, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
    #aih-qr-print-wrap { padding: 0; }
    #aih-qr-print-wrap h1 { display: none; }

    .aih-qr-grid {
        gap: 0;
    }
    .aih-qr-card {
        border: 1px solid #eee;
        border-radius: 0;
        padding: 12px;
    }

    /* 1 per page */
    .aih-qr-grid.layout-1 {
        max-width: none;
    }
    .aih-qr-grid.layout-1 .aih-qr-card {
        page-break-after: always;
        height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 40px;
    }
    .aih-qr-grid.layout-1 .aih-qr-card img { max-width: 5in; }
    .aih-qr-grid.layout-1 .aih-qr-label { font-size: 28px; }
    .aih-qr-grid.layout-1 .aih-qr-title { font-size: 20px; }

    /* 4 per page (2x2) */
    .aih-qr-grid.layout-4 {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: calc(50vh - 0.25in);
    }
    .aih-qr-grid.layout-4 .aih-qr-card:nth-child(4n+1) { page-break-before: auto; }
    .aih-qr-grid.layout-4 .aih-qr-card:nth-child(4n) ~ .aih-qr-card:nth-child(4n+1) { page-break-before: always; }
    .aih-qr-grid.layout-4 .aih-qr-card img { max-width: 2.8in; }

    /* 6 per page (2x3) */
    .aih-qr-grid.layout-6 {
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: calc(33.33vh - 0.17in);
    }
    .aih-qr-grid.layout-6 .aih-qr-card:nth-child(6n) ~ .aih-qr-card:nth-child(6n+1) { page-break-before: always; }
    .aih-qr-grid.layout-6 .aih-qr-card img { max-width: 2in; }
    .aih-qr-grid.layout-6 .aih-qr-label { font-size: 14px; }
    .aih-qr-grid.layout-6 .aih-qr-title { font-size: 11px; }

    /* 9 per page (3x3) */
    .aih-qr-grid.layout-9 {
        grid-template-columns: repeat(3, 1fr);
        grid-auto-rows: calc(33.33vh - 0.17in);
    }
    .aih-qr-grid.layout-9 .aih-qr-card:nth-child(9n) ~ .aih-qr-card:nth-child(9n+1) { page-break-before: always; }
    .aih-qr-grid.layout-9 .aih-qr-card img { max-width: 1.6in; }
    .aih-qr-grid.layout-9 .aih-qr-label { font-size: 12px; }
    .aih-qr-grid.layout-9 .aih-qr-title { font-size: 10px; }
}

@page {
    size: letter;
    margin: 0.5in;
}
</style>

<script>
jQuery(function($) {
    var items = [];
    try {
        items = JSON.parse(sessionStorage.getItem('aih_qr_print_items') || '[]');
    } catch(e) {}

    if (!items.length) {
        $('#aih-qr-controls').hide();
        $('#aih-qr-empty').show();
        return;
    }

    var $grid = $('#aih-qr-grid');
    var $status = $('#aih-qr-status');
    var nonce = <?php echo wp_json_encode($admin_nonce); ?>;

    // Build placeholder cards
    $.each(items, function(i, item) {
        $grid.append(
            '<div class="aih-qr-card" data-art-id="' + escapeAttr(item.art_id) + '">' +
                '<div class="aih-qr-spinner"><?php echo esc_js(__('Generating...', 'art-in-heaven')); ?></div>' +
                '<p class="aih-qr-label">' + escapeHtml(item.art_id) + '</p>' +
                '<p class="aih-qr-title">' + escapeHtml(item.title) + '</p>' +
            '</div>'
        );
    });

    // Generate QR codes sequentially to avoid overwhelming the server
    var generated = 0;
    $status.text('<?php echo esc_js(__('Generating QR codes...', 'art-in-heaven')); ?> 0/' + items.length);

    function generateNext(index) {
        if (index >= items.length) {
            $status.text('<?php echo esc_js(__('All QR codes ready.', 'art-in-heaven')); ?>');
            return;
        }

        var item = items[index];
        $.post(ajaxurl, {
            action: 'aih_admin_generate_qr',
            nonce: nonce,
            art_id: item.art_id,
            title: item.title
        }, function(r) {
            var $card = $grid.find('.aih-qr-card[data-art-id="' + escapeAttr(item.art_id) + '"]');
            if (r.success) {
                $card.find('.aih-qr-spinner').replaceWith('<img src="' + r.data.qr + '" alt="QR">');
            } else {
                $card.find('.aih-qr-spinner').text('<?php echo esc_js(__('Error', 'art-in-heaven')); ?>');
            }
            generated++;
            $status.text('<?php echo esc_js(__('Generating QR codes...', 'art-in-heaven')); ?> ' + generated + '/' + items.length);
            generateNext(index + 1);
        }).fail(function() {
            generated++;
            generateNext(index + 1);
        });
    }

    generateNext(0);

    // Layout switcher
    $('#aih-qr-layout').on('change', function() {
        var val = $(this).val();
        $grid.removeClass('layout-1 layout-4 layout-6 layout-9').addClass('layout-' + val);
    });

    // Print button
    $('#aih-qr-print-btn').on('click', function() {
        window.print();
    });

    // Helper functions
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }
    function escapeAttr(str) {
        return (str || '').replace(/[&"'<>]/g, function(c) {
            return {'&':'&amp;','"':'&quot;',"'":'&#39;','<':'&lt;','>':'&gt;'}[c];
        });
    }
});
</script>
