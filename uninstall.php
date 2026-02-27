<?php
/**
 * Art in Heaven Uninstall
 * 
 * Fired when the plugin is uninstalled.
 * Removes all plugin data including database tables, options, and files.
 * 
 * @package ArtInHeaven
 * @since 2.7.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user capabilities
if (!current_user_can('activate_plugins')) {
    exit;
}

// Check if we should delete data (optional setting)
$delete_data = get_option('aih_delete_data_on_uninstall', false);

if (!$delete_data) {
    // Just remove capabilities and exit, keep data
    aih_uninstall_remove_capabilities();
    return;
}

global $wpdb;

// Get all years that might have tables
$years = range(2020, wp_date('Y') + 5);

// Tables to delete for each year
$table_suffixes = array(
    '_ArtPieces',
    '_ArtImages',
    '_Bids',
    '_Favorites',
    '_Bidders',
    '_Registrants',
    '_Orders',
    '_OrderItems',
    '_PushpayTransactions',
    '_PushSubscriptions',
    '_AuditLog'
);

// Delete tables for all years
foreach ($years as $year) {
    foreach ($table_suffixes as $suffix) {
        $table = $wpdb->prefix . $year . $suffix;
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
}

// Delete all plugin options
$options_to_delete = array(
    'aih_db_version',
    'aih_auction_year',
    'aih_event_date',
    'aih_tax_rate',
    'aih_currency',
    'aih_enable_favorites',
    'aih_enable_notifications',
    'aih_enable_outbid_notifications',
    'aih_enable_winner_notifications',
    'aih_enable_ending_reminders',
    'aih_watermark_enabled',
    'aih_watermark_text',
    'aih_watermark_position',
    'aih_watermark_opacity',
    'aih_min_bid_increment',
    'aih_gallery_page',
    'aih_checkout_page',
    'aih_logo_url',
    'aih_notification_email',
    'aih_notification_name',
    'aih_api_base_url',
    'aih_api_form_id',
    'aih_api_username',
    'aih_api_password',
    'aih_pushpay_merchant_key',
    'aih_pushpay_url',
    'aih_last_bidder_sync',
    'aih_last_sync_count',
    'aih_delete_data_on_uninstall',
    'aih_color_primary',
    'aih_color_secondary',
    'aih_color_success',
    'aih_color_error',
    'aih_color_text',
    'aih_color_muted',
    'aih_auto_sync_enabled',
    'aih_auto_sync_interval',
    'aih_watermark_overlay_id',
    'aih_event_end_date',
    'aih_settings',
    'aih_disable_image_sizes',
    // Pushpay settings
    'aih_pushpay_auto_sync_enabled',
    'aih_pushpay_auto_sync_interval',
    'aih_pushpay_client_id',
    'aih_pushpay_client_secret',
    'aih_pushpay_organization_key',
    'aih_pushpay_merchant_handle',
    'aih_pushpay_sandbox',
    'aih_pushpay_sandbox_client_id',
    'aih_pushpay_sandbox_client_secret',
    'aih_pushpay_sandbox_organization_key',
    'aih_pushpay_sandbox_merchant_key',
    'aih_pushpay_sandbox_merchant_handle',
    'aih_pushpay_fund',
    'aih_pushpay_base_url',
    'aih_pushpay_redirect_key',
    'aih_pushpay_last_sync',
    'aih_pushpay_last_sync_count',
    // VAPID keys
    'aih_vapid_public_key',
    'aih_vapid_private_key',
    'aih_vapid_subject',
    // Login/page settings
    'aih_login_page',
    'aih_my_bids_page',
    'aih_currency_symbol',
    'aih_bid_increment',
    'aih_min_bid_increment',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Delete all transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_aih_%' 
     OR option_name LIKE '_transient_timeout_aih_%'
     OR option_name LIKE '_transient_aih_cache_%'
     OR option_name LIKE '_transient_timeout_aih_cache_%'"
);

// Delete cache group options
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE 'aih_cache_group_%'"
);

// Remove custom roles
aih_uninstall_remove_capabilities();

// Delete upload directory
$upload_dir = wp_upload_dir();
$aih_upload_dir = $upload_dir['basedir'] . '/art-in-heaven';
if (is_dir($aih_upload_dir)) {
    aih_uninstall_recursive_delete($aih_upload_dir);
}

// Clear all scheduled events
wp_clear_scheduled_hook('aih_hourly_cleanup');
wp_clear_scheduled_hook('aih_check_expired_auctions');
wp_clear_scheduled_hook('aih_send_ending_reminders');
wp_clear_scheduled_hook('aih_five_minute_check');
wp_clear_scheduled_hook('aih_auto_sync_registrants');
wp_clear_scheduled_hook('aih_auto_sync_pushpay');
wp_clear_scheduled_hook('aih_scheduled_activate_piece');
wp_clear_scheduled_hook('aih_scheduled_end_piece');

// Delete rate limit transients
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_aih_rate_%'
     OR option_name LIKE '_transient_timeout_aih_rate_%'
     OR option_name LIKE '_transient_aih_cron_%'
     OR option_name LIKE '_transient_timeout_aih_cron_%'"
);

// Flush rewrite rules
flush_rewrite_rules();

/**
 * Remove custom capabilities and roles
 */
function aih_uninstall_remove_capabilities() {
    // Remove role
    remove_role('auction_art_manager');
    
    // Remove capabilities from admin
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $caps = array(
            'manage_auction',
            'manage_auction_art',
            'view_auction_bids',
            'manage_auction_bidders',
            'view_auction_financial',
            'manage_auction_settings',
            'view_auction_reports',
        );
        
        foreach ($caps as $cap) {
            $admin_role->remove_cap($cap);
        }
    }
}

/**
 * Recursively delete a directory
 * 
 * @param string $dir Directory path
 * @return bool
 */
function aih_uninstall_recursive_delete($dir, $depth = 0) {
    if (!is_dir($dir) || $depth > 10) {
        return false;
    }

    $files = @scandir($dir);
    if ($files === false) {
        return false;
    }
    $files = array_diff($files, array('.', '..'));

    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            aih_uninstall_recursive_delete($path, $depth + 1);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}
