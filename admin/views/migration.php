<?php
/**
 * Admin Migration View
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$current_year = AIH_Database::get_auction_year();

// Get list of existing year tables
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}%_ArtPieces'", ARRAY_N);
$years = array();
foreach ($tables as $table) {
    if (preg_match('/(\d{4})_ArtPieces/', $table[0], $matches)) {
        $years[] = $matches[1];
    }
}
rsort($years);
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Migration Tools', 'art-in-heaven'); ?></h1>
    
    <div class="aih-migration-section">
        <h2><?php _e('Year-to-Year Migration', 'art-in-heaven'); ?></h2>
        <p><?php _e('Migrate data from one auction year to another. This is useful for setting up a new year while keeping historical data.', 'art-in-heaven'); ?></p>
        
        <table class="form-table">
            <tr>
                <th><?php _e('Source Year', 'art-in-heaven'); ?></th>
                <td>
                    <select id="aih-source-year">
                        <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php _e('Target Year', 'art-in-heaven'); ?></th>
                <td>
                    <input type="number" id="aih-target-year" value="<?php echo date('Y') + 1; ?>" min="2020" max="2099">
                </td>
            </tr>
            <tr>
                <th><?php _e('What to Migrate', 'art-in-heaven'); ?></th>
                <td>
                    <label><input type="checkbox" name="migrate_art" value="1" checked> <?php _e('Art Pieces (without bids)', 'art-in-heaven'); ?></label><br>
                    <label><input type="checkbox" name="migrate_bidders" value="1"> <?php _e('Bidders', 'art-in-heaven'); ?></label>
                </td>
            </tr>
        </table>
        
        <button type="button" id="aih-migrate-btn" class="button button-primary"><?php _e('Start Migration', 'art-in-heaven'); ?></button>
        <span id="aih-migrate-result"></span>
    </div>
    
    <hr>
    
    <div class="aih-migration-section">
        <h2><?php _e('Existing Auction Years', 'art-in-heaven'); ?></h2>
        <p><?php _e('Database tables found for the following years:', 'art-in-heaven'); ?></p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Year', 'art-in-heaven'); ?></th>
                    <th><?php _e('Art Pieces', 'art-in-heaven'); ?></th>
                    <th><?php _e('Bids', 'art-in-heaven'); ?></th>
                    <th><?php _e('Bidders', 'art-in-heaven'); ?></th>
                    <th><?php _e('Orders', 'art-in-heaven'); ?></th>
                    <th><?php _e('Current', 'art-in-heaven'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($years as $year): 
                    $art_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$year}_ArtPieces");
                    $bids_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$year}_Bids");
                    $bidders_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$year}_Bidders");
                    $orders_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$year}_Orders'");
                    $orders_count = $orders_table ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}{$year}_Orders") : 0;
                ?>
                <tr>
                    <td><strong><?php echo $year; ?></strong></td>
                    <td><?php echo number_format($art_count); ?></td>
                    <td><?php echo number_format($bids_count); ?></td>
                    <td><?php echo number_format($bidders_count); ?></td>
                    <td><?php echo number_format($orders_count); ?></td>
                    <td>
                        <?php if ($year == $current_year): ?>
                            <span class="aih-current-badge"><?php _e('Active', 'art-in-heaven'); ?></span>
                        <?php else: ?>
                            <button type="button" class="button button-small aih-switch-year" data-year="<?php echo $year; ?>"><?php _e('Switch', 'art-in-heaven'); ?></button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <hr>
    
    <div class="aih-migration-section">
        <h2><?php _e('Database Maintenance', 'art-in-heaven'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th><?php _e('Create New Year Tables', 'art-in-heaven'); ?></th>
                <td>
                    <input type="number" id="aih-new-year" value="<?php echo date('Y'); ?>" min="2020" max="2099" style="width: 100px;">
                    <button type="button" id="aih-create-year-btn" class="button"><?php _e('Create Tables', 'art-in-heaven'); ?></button>
                    <span id="aih-create-result"></span>
                </td>
            </tr>
        </table>
    </div>
</div>

<style>
/* migration minimal overrides - main styles in aih-admin.css */
</style>

<script>
jQuery(document).ready(function($) {
    $('#aih-migrate-btn').on('click', function() {
        if (!confirm('<?php _e('This will copy data to the target year. Continue?', 'art-in-heaven'); ?>')) return;
        
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-migrate-result').text('Migrating...');
        
        // Migration would be handled server-side - placeholder for now
        setTimeout(function() {
            $result.html('<span style="color:green;">✓ Migration feature coming soon</span>');
            $btn.prop('disabled', false);
        }, 1000);
    });
    
    $('#aih-create-year-btn').on('click', function() {
        var year = $('#aih-new-year').val();
        var $btn = $(this).prop('disabled', true);
        var $result = $('#aih-create-result').text('Creating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'aih_admin_create_tables', nonce: '<?php echo wp_create_nonce('aih_admin_nonce'); ?>', year: year },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $result.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                }
            },
            complete: function() { $btn.prop('disabled', false); }
        });
    });
    
    $('.aih-switch-year').on('click', function() {
        if (!confirm('<?php _e('Switch to this year? You will need to save settings.', 'art-in-heaven'); ?>')) return;
        var year = $(this).data('year');
        window.location.href = '<?php echo admin_url('admin.php?page=art-in-heaven-settings'); ?>&switch_year=' + year;
    });
});
</script>
