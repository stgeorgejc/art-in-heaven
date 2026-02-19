<?php
/**
 * Admin Log Viewer View
 */

if (!defined('ABSPATH')) {
    exit;
}

$log_file  = WP_CONTENT_DIR . '/debug.log';
$file_size = file_exists($log_file) ? size_format(filesize($log_file)) : '0 B';
$last_mod  = file_exists($log_file) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($log_file)) : '—';
?>
<div class="wrap aih-admin-wrap">
    <h1><?php esc_html_e('Log Viewer', 'art-in-heaven'); ?></h1>
    <p class="description">
        <?php echo esc_html(sprintf(__('File size: %s | Last modified: %s', 'art-in-heaven'), $file_size, $last_mod)); ?>
        <span id="aih-log-file-size"></span>
    </p>

    <div class="aih-log-controls" style="display:flex;align-items:center;gap:10px;margin:15px 0;flex-wrap:wrap;">
        <label for="aih-log-filter"><?php esc_html_e('Filter:', 'art-in-heaven'); ?></label>
        <select id="aih-log-filter">
            <option value="aih"><?php esc_html_e('AIH Only', 'art-in-heaven'); ?></option>
            <option value="all"><?php esc_html_e('All', 'art-in-heaven'); ?></option>
        </select>

        <label for="aih-log-lines"><?php esc_html_e('Lines:', 'art-in-heaven'); ?></label>
        <select id="aih-log-lines">
            <option value="50">50</option>
            <option value="100">100</option>
            <option value="200" selected>200</option>
            <option value="500">500</option>
        </select>

        <button type="button" class="button" id="aih-log-refresh"><?php esc_html_e('Refresh', 'art-in-heaven'); ?></button>

        <label style="display:flex;align-items:center;gap:4px;">
            <input type="checkbox" id="aih-log-auto-refresh">
            <?php esc_html_e('Auto-refresh (5s)', 'art-in-heaven'); ?>
        </label>

        <button type="button" class="button button-link-delete" id="aih-log-clear"><?php esc_html_e('Clear Log', 'art-in-heaven'); ?></button>

        <span id="aih-log-status" style="color:#888;"></span>
    </div>

    <div id="aih-log-count" style="margin-bottom:8px;color:#666;"></div>

    <pre id="aih-log-output" style="
        background:#1e1e1e;
        color:#d4d4d4;
        font-family:monospace;
        font-size:12px;
        line-height:1.5;
        padding:15px;
        max-height:600px;
        overflow:auto;
        white-space:pre-wrap;
        word-wrap:break-word;
        border-radius:4px;
    "><?php esc_html_e('Loading...', 'art-in-heaven'); ?></pre>
</div>

<script>
jQuery(function($) {
    var autoRefreshTimer = null;
    var nonce = '<?php echo esc_js(wp_create_nonce('aih_admin_nonce')); ?>';

    function loadLogs() {
        var filter = $('#aih-log-filter').val();
        var lines  = $('#aih-log-lines').val();
        $('#aih-log-status').text('<?php echo esc_js(__('Loading...', 'art-in-heaven')); ?>');

        $.post(ajaxurl, {
            action: 'aih_admin_get_logs',
            nonce:  nonce,
            filter: filter,
            lines:  lines
        }, function(res) {
            if (res.success) {
                var d = res.data;
                var text = d.entries.length ? d.entries.join("\n") : '<?php echo esc_js(__('No log entries found.', 'art-in-heaven')); ?>';
                $('#aih-log-output').text(text);
                $('#aih-log-count').text(
                    '<?php echo esc_js(__('Showing', 'art-in-heaven')); ?> ' + d.entries.length +
                    ' <?php echo esc_js(__('entries', 'art-in-heaven')); ?>' +
                    (filter === 'aih' ? ' (<?php echo esc_js(__('filtered', 'art-in-heaven')); ?>)' : '') +
                    ' — <?php echo esc_js(__('Total lines in file:', 'art-in-heaven')); ?> ' + d.total +
                    ' — <?php echo esc_js(__('File size:', 'art-in-heaven')); ?> ' + d.file_size
                );
                $('#aih-log-status').text('');
            } else {
                $('#aih-log-status').text(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error loading logs.', 'art-in-heaven')); ?>');
            }
        }).fail(function() {
            $('#aih-log-status').text('<?php echo esc_js(__('Request failed.', 'art-in-heaven')); ?>');
        });
    }

    function clearLogs() {
        if (!confirm('<?php echo esc_js(__('Are you sure you want to clear the log file? This cannot be undone.', 'art-in-heaven')); ?>')) {
            return;
        }
        $.post(ajaxurl, {
            action: 'aih_admin_clear_logs',
            nonce:  nonce
        }, function(res) {
            if (res.success) {
                $('#aih-log-output').text('<?php echo esc_js(__('Log cleared.', 'art-in-heaven')); ?>');
                $('#aih-log-count').text('');
                $('#aih-log-status').text(res.data.message);
            } else {
                $('#aih-log-status').text(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error clearing logs.', 'art-in-heaven')); ?>');
            }
        });
    }

    $('#aih-log-refresh').on('click', loadLogs);
    $('#aih-log-clear').on('click', clearLogs);
    $('#aih-log-filter, #aih-log-lines').on('change', loadLogs);

    $('#aih-log-auto-refresh').on('change', function() {
        if (this.checked) {
            autoRefreshTimer = setInterval(loadLogs, 5000);
        } else {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    });

    // Initial load
    loadLogs();
});
</script>
