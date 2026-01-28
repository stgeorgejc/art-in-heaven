<?php
/**
 * Admin Integrations View - API Connections
 * 
 * Manages CCB Church API and Pushpay API connections
 */
if (!defined('ABSPATH')) exit;

// Get sync status for CCB
$auth = AIH_Auth::get_instance();
$sync_status = $auth->get_sync_status();

// Get Pushpay status
$pushpay = AIH_Pushpay_API::get_instance();
$pp_settings = $pushpay->get_settings();
$last_pp_sync = get_option('aih_pushpay_last_sync', '');
$last_pp_sync_count = get_option('aih_pushpay_last_sync_count', 0);
$is_sandbox = get_option('aih_pushpay_sandbox', 0);
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('API Integrations', 'art-in-heaven'); ?></h1>
    <p class="description"><?php _e('Configure connections to external services for bidder management and payment processing.', 'art-in-heaven'); ?></p>
    
    <form method="post" action="options.php">
        <?php settings_fields('aih_integrations'); ?>
        
        <!-- CCB Church API Section -->
        <div class="aih-settings-section">
            <h2>
                <span class="dashicons dashicons-groups" style="margin-right: 8px;"></span>
                <?php _e('CCB Church Management System', 'art-in-heaven'); ?>
            </h2>
            <p class="description"><?php _e('Connect to your Church Community Builder (CCB) account to sync event registrants as bidders.', 'art-in-heaven'); ?></p>
            
            <!-- Status Box -->
            <div class="aih-sync-status-box">
                <h3><?php _e('Connection Status', 'art-in-heaven'); ?></h3>
                <table class="aih-sync-info">
                    <tr>
                        <td><strong><?php _e('API Configured:', 'art-in-heaven'); ?></strong></td>
                        <td>
                            <?php if (get_option('aih_api_base_url') && get_option('aih_api_username') && get_option('aih_api_password')): ?>
                            <span style="color: #4a7c59;">✓ <?php _e('Yes', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                            <span style="color: #a63d40;">✗ <?php _e('No - Enter credentials below', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Bidders in Database:', 'art-in-heaven'); ?></strong></td>
                        <td><span class="aih-bidder-count"><?php echo number_format($sync_status['current_count']); ?></span></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Last Sync:', 'art-in-heaven'); ?></strong></td>
                        <td><?php echo esc_html($sync_status['last_sync']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Last Sync Count:', 'art-in-heaven'); ?></strong></td>
                        <td><?php echo number_format($sync_status['last_count']); ?> <?php _e('registrants from API', 'art-in-heaven'); ?></td>
                    </tr>
                </table>
                <p style="margin-top: 15px;">
                    <button type="button" id="aih-test-api" class="button"><?php _e('Test Connection', 'art-in-heaven'); ?></button>
                    <button type="button" id="aih-sync-bidders" class="button button-primary"><?php _e('Sync Bidders from API', 'art-in-heaven'); ?></button>
                    <span id="aih-api-test-result" style="margin-left: 10px;"></span>
                </p>
            </div>
            
            <!-- CCB Credentials -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_api_base_url"><?php _e('API Base URL', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="url" id="aih_api_base_url" name="aih_api_base_url" value="<?php echo esc_attr(get_option('aih_api_base_url', 'https://stgeorgejc.ccbchurch.com/api.php')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your CCB API endpoint (e.g., https://yourchurch.ccbchurch.com/api.php)', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_api_form_id"><?php _e('Form ID', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" id="aih_api_form_id" name="aih_api_form_id" value="<?php echo esc_attr(get_option('aih_api_form_id', '')); ?>" class="regular-text">
                        <p class="description"><?php _e('The ID of your registration form in CCB. Used in: api.php?srv=form_responses&form_id=YOUR_ID', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_api_username"><?php _e('API Username', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" id="aih_api_username" name="aih_api_username" value="<?php echo esc_attr(get_option('aih_api_username', '')); ?>" class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_api_password"><?php _e('API Password', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="password" id="aih_api_password" name="aih_api_password" value="<?php echo esc_attr(get_option('aih_api_password', '')); ?>" class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto Sync', 'art-in-heaven'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aih_auto_sync_enabled" value="1" <?php checked(get_option('aih_auto_sync_enabled', false)); ?>>
                            <?php _e('Enable automatic sync of registrants from API', 'art-in-heaven'); ?>
                        </label>
                        <p class="description"><?php _e('Automatically sync new registrants at the selected interval.', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_auto_sync_interval"><?php _e('Sync Interval', 'art-in-heaven'); ?></label></th>
                    <td>
                        <?php $current_interval = get_option('aih_auto_sync_interval', 'hourly'); ?>
                        <select name="aih_auto_sync_interval" id="aih_auto_sync_interval">
                            <option value="hourly" <?php selected($current_interval, 'hourly'); ?>><?php _e('Every Hour', 'art-in-heaven'); ?></option>
                            <option value="every_thirty_seconds" <?php selected($current_interval, 'every_thirty_seconds'); ?>><?php _e('Every 30 Seconds', 'art-in-heaven'); ?></option>
                        </select>
                        <p class="description"><?php _e('Choose how often to sync registrants. Use 30 seconds during live events for near real-time updates.', 'art-in-heaven'); ?></p>
                        <?php if ($current_interval === 'every_thirty_seconds'): ?>
                        <p class="description" style="color: #b45309; margin-top: 8px;">
                            <strong><?php _e('⚠️ Note:', 'art-in-heaven'); ?></strong>
                            <?php _e('30-second sync increases API calls significantly. Only use during active registration periods.', 'art-in-heaven'); ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <div class="aih-info-box" style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
                <strong><?php _e('How Bidder Login Works:', 'art-in-heaven'); ?></strong>
                <p style="margin: 10px 0 0 0;"><?php _e('Bidders must be synced from the CCB API before they can login. When a user logs in with their confirmation code, the system looks them up in the local database. If they\'re not found, they won\'t be able to bid.', 'art-in-heaven'); ?></p>
            </div>
        </div>
        
        <!-- Pushpay API Section -->
        <div class="aih-settings-section">
            <h2>
                <span class="dashicons dashicons-money-alt" style="margin-right: 8px;"></span>
                <?php _e('Pushpay Payment Processing', 'art-in-heaven'); ?>
            </h2>
            <p class="description"><?php _e('Connect to Pushpay to process payments and sync transaction data.', 'art-in-heaven'); ?></p>
            
            <!-- Status Box -->
            <div class="aih-sync-status-box" style="margin-bottom: 20px;">
                <h3><?php _e('Connection Status', 'art-in-heaven'); ?></h3>
                <table class="aih-sync-info">
                    <tr>
                        <td><strong><?php _e('Current Mode:', 'art-in-heaven'); ?></strong></td>
                        <td>
                            <?php if ($is_sandbox): ?>
                            <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 3px; font-weight: 600;">
                                🧪 <?php _e('SANDBOX', 'art-in-heaven'); ?>
                            </span>
                            <?php else: ?>
                            <span style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 3px; font-weight: 600;">
                                🔒 <?php _e('PRODUCTION', 'art-in-heaven'); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('API Configured:', 'art-in-heaven'); ?></strong></td>
                        <td>
                            <?php if ($pushpay->is_configured()): ?>
                            <span style="color: #4a7c59;">✓ <?php _e('Yes', 'art-in-heaven'); ?></span>
                            <?php else: ?>
                            <span style="color: #a63d40;">✗ <?php _e('No - Enter credentials below', 'art-in-heaven'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Last Sync:', 'art-in-heaven'); ?></strong></td>
                        <td><?php echo $last_pp_sync ? date_i18n('M j, Y g:i a', strtotime($last_pp_sync)) : __('Never', 'art-in-heaven'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Transactions Synced:', 'art-in-heaven'); ?></strong></td>
                        <td><?php echo intval($last_pp_sync_count); ?></td>
                    </tr>
                </table>
                <?php if ($pushpay->is_configured()): ?>
                <p style="margin-top: 15px;">
                    <button type="button" id="aih-test-pushpay" class="button"><?php _e('Test Connection', 'art-in-heaven'); ?></button>
                    <button type="button" id="aih-sync-pushpay" class="button button-primary"><?php _e('Sync Transactions', 'art-in-heaven'); ?></button>
                    <span id="aih-pushpay-result" style="margin-left: 10px;"></span>
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Environment Toggle -->
            <table class="form-table">
                <tr>
                    <th scope="row"><label><?php _e('Environment', 'art-in-heaven'); ?></label></th>
                    <td>
                        <fieldset>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="aih_pushpay_sandbox" value="0" <?php checked($is_sandbox, 0); ?>>
                                <strong><?php _e('Production', 'art-in-heaven'); ?></strong>
                                <span class="description"> - <?php _e('Live payments', 'art-in-heaven'); ?></span>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="aih_pushpay_sandbox" value="1" <?php checked($is_sandbox, 1); ?>>
                                <strong><?php _e('Sandbox', 'art-in-heaven'); ?></strong>
                                <span class="description"> - <?php _e('Test mode, no real payments', 'art-in-heaven'); ?></span>
                            </label>
                        </fieldset>
                        <p class="description" style="margin-top: 10px;">
                            <?php _e('Sandbox mode uses test API endpoints. Switch to Production for live payments.', 'art-in-heaven'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <!-- Sandbox Credentials -->
            <div class="aih-pushpay-env-section" id="aih-sandbox-credentials" style="background: #fffbeb; border: 1px solid #fbbf24; border-radius: 8px; padding: 20px; margin: 20px 0; <?php echo !$is_sandbox ? 'display:none;' : ''; ?>">
                <h3 style="margin-top: 0; color: #92400e;">
                    🧪 <?php _e('Sandbox Credentials', 'art-in-heaven'); ?>
                </h3>
                <p class="description"><?php _e('Use these credentials for testing. Get them from the Pushpay Sandbox Developer Portal.', 'art-in-heaven'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aih_pushpay_sandbox_client_id"><?php _e('Client ID', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_sandbox_client_id" name="aih_pushpay_sandbox_client_id" value="<?php echo esc_attr(get_option('aih_pushpay_sandbox_client_id', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_sandbox_client_secret"><?php _e('Client Secret', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="password" id="aih_pushpay_sandbox_client_secret" name="aih_pushpay_sandbox_client_secret" value="<?php echo esc_attr(get_option('aih_pushpay_sandbox_client_secret', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_sandbox_organization_key"><?php _e('Organization Key', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_sandbox_organization_key" name="aih_pushpay_sandbox_organization_key" value="<?php echo esc_attr(get_option('aih_pushpay_sandbox_organization_key', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_sandbox_merchant_key"><?php _e('Merchant Key', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_sandbox_merchant_key" name="aih_pushpay_sandbox_merchant_key" value="<?php echo esc_attr(get_option('aih_pushpay_sandbox_merchant_key', '')); ?>" class="regular-text">
                            <p class="description"><?php _e('For payment links (the part after pushpay.com/pay/)', 'art-in-heaven'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Production Credentials -->
            <div class="aih-pushpay-env-section" id="aih-production-credentials" style="background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; padding: 20px; margin: 20px 0; <?php echo $is_sandbox ? 'display:none;' : ''; ?>">
                <h3 style="margin-top: 0; color: #065f46;">
                    🔒 <?php _e('Production Credentials', 'art-in-heaven'); ?>
                </h3>
                <p class="description"><?php _e('Live credentials for real payments. Get them from the Pushpay Developer Portal.', 'art-in-heaven'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="aih_pushpay_client_id"><?php _e('Client ID', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_client_id" name="aih_pushpay_client_id" value="<?php echo esc_attr(get_option('aih_pushpay_client_id', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_client_secret"><?php _e('Client Secret', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="password" id="aih_pushpay_client_secret" name="aih_pushpay_client_secret" value="<?php echo esc_attr(get_option('aih_pushpay_client_secret', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_organization_key"><?php _e('Organization Key', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_organization_key" name="aih_pushpay_organization_key" value="<?php echo esc_attr(get_option('aih_pushpay_organization_key', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="aih_pushpay_merchant_key"><?php _e('Merchant Key', 'art-in-heaven'); ?></label></th>
                        <td>
                            <input type="text" id="aih_pushpay_merchant_key" name="aih_pushpay_merchant_key" value="<?php echo esc_attr(get_option('aih_pushpay_merchant_key', '')); ?>" class="regular-text">
                            <p class="description"><?php _e('For payment links (the part after pushpay.com/pay/)', 'art-in-heaven'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Shared Payment Link Settings -->
            <h3><?php _e('Payment Link Settings', 'art-in-heaven'); ?></h3>
            <p class="description"><?php _e('These settings apply to both environments.', 'art-in-heaven'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="aih_pushpay_base_url"><?php _e('Payment URL', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="url" id="aih_pushpay_base_url" name="aih_pushpay_base_url" value="<?php echo esc_attr(get_option('aih_pushpay_base_url', 'https://pushpay.com/pay/')); ?>" class="regular-text">
                        <p class="description"><?php _e('Base URL for payment links', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="aih_pushpay_fund"><?php _e('Fund/Category', 'art-in-heaven'); ?></label></th>
                    <td>
                        <input type="text" id="aih_pushpay_fund" name="aih_pushpay_fund" value="<?php echo esc_attr(get_option('aih_pushpay_fund', 'art-in-heaven')); ?>" class="regular-text">
                        <p class="description"><?php _e('The fund/category name for auction payments in Pushpay', 'art-in-heaven'); ?></p>
                    </td>
                </tr>
            </table>
            
            <div class="aih-info-box" style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
                <strong><?php _e('Getting Pushpay API Credentials:', 'art-in-heaven'); ?></strong>
                <ol style="margin: 10px 0 0 20px;">
                    <li><?php _e('Log into the Pushpay Developer Portal', 'art-in-heaven'); ?></li>
                    <li><?php _e('Create an application with OAuth 2.0 client credentials', 'art-in-heaven'); ?></li>
                    <li><?php _e('Request "read" scope for transaction access', 'art-in-heaven'); ?></li>
                    <li><?php _e('Copy the Client ID and Client Secret here', 'art-in-heaven'); ?></li>
                    <li><?php _e('Find your Organization Key in your Pushpay admin settings', 'art-in-heaven'); ?></li>
                </ol>
            </div>
        </div>
        
        <?php submit_button(__('Save Integration Settings', 'art-in-heaven')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var nonce = '<?php echo wp_create_nonce('aih_admin_nonce'); ?>';
    
    // Test CCB API
    $('#aih-test-api').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-api-test-result').html('<span style="color:#666;"><?php echo esc_js(__('Testing...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_test_api', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Connection failed') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color:red;">✗ ' + error + '</span>');
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    // Sync Bidders from CCB
    $('#aih-sync-bidders').on('click', function() {
        if (!confirm('<?php echo esc_js(__('This will fetch all registrants from the CCB API and save them to the local database. Continue?', 'art-in-heaven')); ?>')) return;
        
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'art-in-heaven')); ?>');
        var $result = $('#aih-api-test-result').html('<span style="color:#666;"><?php echo esc_js(__('Fetching registrants...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 120000,
            data: { action: 'aih_admin_sync_bidders', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    if (response.data.count !== undefined) {
                        $('.aih-bidder-count').text(response.data.count.toLocaleString());
                    }
                } else {
                    $result.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Sync failed') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color:red;">✗ ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Sync Bidders from API', 'art-in-heaven')); ?>');
            }
        });
    });
    
    // Test Pushpay API
    $('#aih-test-pushpay').on('click', function() {
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-pushpay-result').html('<span style="color:#666;"><?php echo esc_js(__('Testing...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_test_pushpay', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Connection failed') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color:red;">✗ ' + error + '</span>');
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    // Sync Pushpay Transactions
    $('#aih-sync-pushpay').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('<?php echo esc_js(__('Syncing...', 'art-in-heaven')); ?>');
        var $result = $('#aih-pushpay-result').html('<span style="color:#666;"><?php echo esc_js(__('Fetching transactions...', 'art-in-heaven')); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            timeout: 180000,
            data: { action: 'aih_admin_sync_pushpay', nonce: nonce },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 2000);
                } else {
                    $result.html('<span style="color:red;">✗ ' + (response.data ? response.data.message : 'Sync failed') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $result.html('<span style="color:red;">✗ ' + error + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Sync Transactions', 'art-in-heaven')); ?>');
            }
        });
    });
    
    // Toggle Pushpay environment sections
    $('input[name="aih_pushpay_sandbox"]').on('change', function() {
        var isSandbox = $(this).val() === '1';
        if (isSandbox) {
            $('#aih-sandbox-credentials').show();
            $('#aih-production-credentials').hide();
        } else {
            $('#aih-sandbox-credentials').hide();
            $('#aih-production-credentials').show();
        }
    });
});
</script>
