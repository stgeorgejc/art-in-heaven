<?php
/**
 * Admin Bidders View - Fixed Tab Flow
 * 
 * Tabs:
 * 1. Not Logged In - Registered but haven't logged in yet
 * 2. Logged In - No Bids - Logged in but haven't placed any bids
 * 3. Logged In - Has Bids - Logged in and placed at least one bid
 * 4. All Registrants - Everyone (for reference)
 */
if (!defined('ABSPATH')) exit;

// Check if tables exist
if (!AIH_Database::tables_exist()) {
    echo '<div class="wrap"><div class="notice notice-warning"><p>' . __('Database tables have not been created yet. Please visit the Dashboard first.', 'art-in-heaven') . '</p></div></div>';
    return;
}

$auth = AIH_Auth::get_instance();
$sync_status = $auth->get_sync_status();

global $wpdb;
$registrants_table = AIH_Database::get_table('registrants');
$bidders_table = AIH_Database::get_table('bidders');
$bids_table = AIH_Database::get_table('bids');

// Count for each category - check actual bids table for accuracy
$not_logged_in_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $registrants_table WHERE has_logged_in = 0");
$logged_in_no_bids_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $registrants_table r 
     WHERE r.has_logged_in = 1 
     AND NOT EXISTS (SELECT 1 FROM $bids_table b WHERE b.bidder_id = r.confirmation_code)"
);
$logged_in_has_bids_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM $registrants_table r 
     WHERE r.has_logged_in = 1 
     AND EXISTS (SELECT 1 FROM $bids_table b WHERE b.bidder_id = r.confirmation_code)"
);
$all_registrants_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $registrants_table");

// Get current tab - default to not_logged_in
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'not_logged_in';

// Get data based on tab - check actual bids table for accuracy
if ($current_tab === 'not_logged_in') {
    $people = $wpdb->get_results("SELECT * FROM $registrants_table WHERE has_logged_in = 0 ORDER BY name_last, name_first ASC");
    $tab_title = __('Not Logged In', 'art-in-heaven');
    $tab_description = __('Registrants who have NOT logged in yet. Consider sending them a reminder email with their confirmation code.', 'art-in-heaven');
} elseif ($current_tab === 'logged_in_no_bids') {
    $people = $wpdb->get_results(
        "SELECT r.* FROM $registrants_table r 
         WHERE r.has_logged_in = 1 
         AND NOT EXISTS (SELECT 1 FROM $bids_table b WHERE b.bidder_id = r.confirmation_code)
         ORDER BY r.name_last, r.name_first ASC"
    );
    $tab_title = __('Logged In - No Bids', 'art-in-heaven');
    $tab_description = __('People who logged in but haven\'t placed any bids yet. They may need encouragement!', 'art-in-heaven');
} elseif ($current_tab === 'logged_in_has_bids') {
    $people = $wpdb->get_results(
        "SELECT r.* FROM $registrants_table r 
         WHERE r.has_logged_in = 1 
         AND EXISTS (SELECT 1 FROM $bids_table b WHERE b.bidder_id = r.confirmation_code)
         ORDER BY r.name_last, r.name_first ASC"
    );
    $tab_title = __('Logged In - Has Bids', 'art-in-heaven');
    $tab_description = __('Active bidders who have placed at least one bid. These are your engaged participants!', 'art-in-heaven');
} else {
    $people = $wpdb->get_results("SELECT * FROM $registrants_table ORDER BY name_last, name_first ASC");
    $tab_title = __('All Registrants', 'art-in-heaven');
    $tab_description = __('Everyone who registered via the CCB form. This list is populated by syncing from the API.', 'art-in-heaven');
    $current_tab = 'all';
}
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Bidders Management', 'art-in-heaven'); ?></h1>
    
    <!-- Sync Status & Actions -->
    <div class="aih-bidders-toolbar">
        <div class="aih-sync-info-bar">
            <span><strong><?php echo number_format($all_registrants_count); ?></strong> <?php _e('total registered', 'art-in-heaven'); ?></span>
            <span class="aih-divider">|</span>
            <span><?php _e('Last Sync:', 'art-in-heaven'); ?> <?php echo esc_html($sync_status['last_sync']); ?></span>
        </div>
        <div class="aih-toolbar-actions">
            <button type="button" id="aih-sync-now" class="button button-primary">
                <span class="dashicons dashicons-update"></span> <?php _e('Sync from API', 'art-in-heaven'); ?>
            </button>
        </div>
    </div>
    <div id="aih-sync-result" style="margin-bottom: 15px;"></div>
    
    <!-- Summary Cards -->
    <div class="aih-bidder-stats">
        <div class="aih-stat-card" style="border-left: 4px solid #ef4444;">
            <div class="aih-stat-number"><?php echo number_format($not_logged_in_count); ?></div>
            <div class="aih-stat-label"><?php _e('Not Logged In', 'art-in-heaven'); ?></div>
            <div class="aih-stat-icon">🔴</div>
        </div>
        <div class="aih-stat-card" style="border-left: 4px solid #f59e0b;">
            <div class="aih-stat-number"><?php echo number_format($logged_in_no_bids_count); ?></div>
            <div class="aih-stat-label"><?php _e('Logged In - No Bids', 'art-in-heaven'); ?></div>
            <div class="aih-stat-icon">🟡</div>
        </div>
        <div class="aih-stat-card" style="border-left: 4px solid #10b981;">
            <div class="aih-stat-number"><?php echo number_format($logged_in_has_bids_count); ?></div>
            <div class="aih-stat-label"><?php _e('Logged In - Has Bids', 'art-in-heaven'); ?></div>
            <div class="aih-stat-icon">🟢</div>
        </div>
        <div class="aih-stat-card" style="border-left: 4px solid #6366f1;">
            <div class="aih-stat-number"><?php echo number_format($all_registrants_count); ?></div>
            <div class="aih-stat-label"><?php _e('All Registrants', 'art-in-heaven'); ?></div>
            <div class="aih-stat-icon">📋</div>
        </div>
    </div>
    
    <!-- Tabs -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-bidders&tab=not_logged_in'); ?>" 
           class="nav-tab <?php echo $current_tab === 'not_logged_in' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Not Logged In', 'art-in-heaven'); ?> (<?php echo $not_logged_in_count; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-bidders&tab=logged_in_no_bids'); ?>" 
           class="nav-tab <?php echo $current_tab === 'logged_in_no_bids' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Logged In - No Bids', 'art-in-heaven'); ?> (<?php echo $logged_in_no_bids_count; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-bidders&tab=logged_in_has_bids'); ?>" 
           class="nav-tab <?php echo $current_tab === 'logged_in_has_bids' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Logged In - Has Bids', 'art-in-heaven'); ?> (<?php echo $logged_in_has_bids_count; ?>)
        </a>
        <a href="<?php echo admin_url('admin.php?page=art-in-heaven-bidders&tab=all'); ?>" 
           class="nav-tab <?php echo $current_tab === 'all' ? 'nav-tab-active' : ''; ?>">
            <?php _e('All Registrants', 'art-in-heaven'); ?> (<?php echo $all_registrants_count; ?>)
        </a>
    </nav>
    
    <div class="aih-tab-content">
    
    <!-- People Table -->
    <div class="aih-table-wrap">
    <table class="wp-list-table widefat fixed striped aih-admin-table">
        <thead>
            <tr>
                <th style="width: 25%;"><?php _e('Name', 'art-in-heaven'); ?></th>
                <th style="width: 20%;"><?php _e('Email', 'art-in-heaven'); ?></th>
                <th style="width: 12%;"><?php _e('Phone', 'art-in-heaven'); ?></th>
                <th style="width: 12%;"><?php _e('Confirmation Code', 'art-in-heaven'); ?></th>
                <th style="width: 8%;"><?php _e('Bids', 'art-in-heaven'); ?></th>
                <th style="width: 10%;"><?php _e('Status', 'art-in-heaven'); ?></th>
                <th style="width: 13%;"><?php _e('Last Login', 'art-in-heaven'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($people)): ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 40px;">
                    <?php if ($current_tab === 'not_logged_in'): ?>
                        <span style="color: #10b981; font-size: 24px;">🎉</span><br>
                        <strong><?php _e('Everyone has logged in! Great job!', 'art-in-heaven'); ?></strong>
                    <?php elseif ($current_tab === 'logged_in_no_bids'): ?>
                        <span style="color: #10b981; font-size: 24px;">🎉</span><br>
                        <strong><?php _e('Everyone who logged in has placed a bid!', 'art-in-heaven'); ?></strong>
                    <?php elseif ($current_tab === 'logged_in_has_bids'): ?>
                        <span style="color: #9ca3af; font-size: 24px;">📭</span><br>
                        <strong><?php _e('No bids have been placed yet.', 'art-in-heaven'); ?></strong>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 24px;">📭</span><br>
                        <strong><?php _e('No registrants in database.', 'art-in-heaven'); ?></strong><br>
                        <?php _e('Click "Sync from API" to import registrants.', 'art-in-heaven'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($people as $person): 
                    // Get bid count using confirmation_code (NOT email)
                    $bid_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $bids_table WHERE bidder_id = %s",
                        $person->confirmation_code
                    ));
                ?>
                <tr>
                    <td data-label="<?php esc_attr_e('Name', 'art-in-heaven'); ?>">
                        <strong><?php echo esc_html(trim($person->name_first . ' ' . $person->name_last) ?: '—'); ?></strong>
                    </td>
                    <td data-label="<?php esc_attr_e('Email', 'art-in-heaven'); ?>"><?php echo esc_html($person->email_primary ?: '—'); ?></td>
                    <td data-label="<?php esc_attr_e('Phone', 'art-in-heaven'); ?>"><?php echo esc_html($person->phone_mobile ?: '—'); ?></td>
                    <td data-label="<?php esc_attr_e('Code', 'art-in-heaven'); ?>"><code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($person->confirmation_code ?: '—'); ?></code></td>
                    <td data-label="<?php esc_attr_e('Bids', 'art-in-heaven'); ?>">
                        <?php if ($bid_count > 0): ?>
                            <span class="aih-bid-badge"><?php echo $bid_count; ?></span>
                        <?php else: ?>
                            <span style="color: #9ca3af;">0</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Status', 'art-in-heaven'); ?>">
                        <?php if ($bid_count > 0): ?>
                            <span class="aih-status-pill has-bid"><?php _e('Has Bids', 'art-in-heaven'); ?></span>
                        <?php elseif ($person->has_logged_in): ?>
                            <span class="aih-status-pill logged-in"><?php _e('Logged In', 'art-in-heaven'); ?></span>
                        <?php else: ?>
                            <span class="aih-status-pill not-active"><?php _e('Not Active', 'art-in-heaven'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="<?php esc_attr_e('Last Login', 'art-in-heaven'); ?>">
                        <?php 
                        // Get last login from bidders table
                        $last_login = $wpdb->get_var($wpdb->prepare(
                            "SELECT last_login FROM $bidders_table WHERE confirmation_code = %s",
                            $person->confirmation_code
                        ));
                        if ($last_login): ?>
                            <?php echo date_i18n('M j, g:i a', strtotime($last_login)); ?>
                        <?php else: ?>
                            <span style="color:#9ca3af;"><?php _e('Never', 'art-in-heaven'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div><!-- /.aih-table-wrap -->
    
    <?php if (!empty($people)): ?>
    <p class="aih-table-footer">
        <?php printf(__('Showing %d people', 'art-in-heaven'), count($people)); ?>
    </p>
    <?php endif; ?>
    
    </div><!-- /.aih-tab-content -->
</div>

<style>
/* bidders minimal overrides - main styles in aih-admin.css */
</style>

<script>
jQuery(document).ready(function($) {
    $('#aih-sync-now').on('click', function() {
        if (!confirm('<?php _e('Sync all registrants from the CCB API?', 'art-in-heaven'); ?>')) return;
        
        var $btn = $(this).prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Syncing...', 'art-in-heaven'); ?>');
        var $result = $('#aih-sync-result').html('<div class="notice notice-info"><p><?php _e('Fetching data from API...', 'art-in-heaven'); ?></p></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 120000,
            data: { action: 'aih_admin_sync_bidders', nonce: '<?php echo wp_create_nonce('aih_admin_nonce'); ?>' },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>✓ ' + response.data.message + '</p></div>');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>✗ ' + (response.data ? response.data.message : 'Sync failed') + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>✗ <?php _e('Request failed. Please try again.', 'art-in-heaven'); ?></p></div>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Sync from API', 'art-in-heaven'); ?>');
            }
        });
    });
});
</script>
