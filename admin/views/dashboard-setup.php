<?php
/**
 * Dashboard Setup View - Shown when database tables don't exist
 */
if (!defined('ABSPATH')) exit;

$year = AIH_Database::get_auction_year();
?>
<div class="wrap aih-admin-wrap">
    <h1><?php _e('Art in Heaven Dashboard', 'art-in-heaven'); ?></h1>

    <?php if (isset($_GET['tables_error'])): ?>
    <div class="notice notice-error" style="padding: 15px; margin: 20px 0;">
        <strong><?php _e('Error:', 'art-in-heaven'); ?></strong>
        <?php _e('Failed to create database tables. Please check your database permissions and try again.', 'art-in-heaven'); ?>
        <?php
        global $wpdb;
        $table = $wpdb->prefix . absint($year) . '_ArtPieces';
        ?>
        <br><small><?php printf(__('Looking for table: %s | DB prefix: %s | Year: %s', 'art-in-heaven'), esc_html($table), esc_html($wpdb->prefix), esc_html($year)); ?></small>
    </div>
    <?php endif; ?>

    <div class="notice notice-warning" style="padding: 20px; margin: 20px 0;">
        <h2 style="margin-top: 0;"><?php _e('Database Setup Required', 'art-in-heaven'); ?></h2>
        <p><?php printf(__('The database tables for auction year %s have not been created yet.', 'art-in-heaven'), '<strong>' . esc_html($year) . '</strong>'); ?></p>
        <p><?php _e('Click the button below to create the required tables and get started.', 'art-in-heaven'); ?></p>
        
        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=art-in-heaven')); ?>">
            <?php wp_nonce_field('aih_create_tables', 'aih_create_tables_nonce'); ?>
            <input type="hidden" name="aih_action" value="create_tables">
            <button type="submit" class="button button-primary button-hero">
                <?php _e('Create Database Tables', 'art-in-heaven'); ?>
            </button>
        </form>
    </div>
    
    <div class="aih-setup-info" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
        <h3><?php _e('What happens next?', 'art-in-heaven'); ?></h3>
        <ol>
            <li><?php _e('Database tables will be created for the current auction year', 'art-in-heaven'); ?></li>
            <li><?php _e('You can then add art pieces and configure settings', 'art-in-heaven'); ?></li>
            <li><?php _e('Import bidders from CCB or add them manually', 'art-in-heaven'); ?></li>
            <li><?php _e('Your auction will be ready to go!', 'art-in-heaven'); ?></li>
        </ol>
    </div>
</div>
