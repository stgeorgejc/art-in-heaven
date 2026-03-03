<?php
/**
 * Header / Nav Partial
 *
 * Accepts (via variable scope):
 *   $active_page   - 'gallery'|'my-bids'|'checkout'|'my-wins'|'winners'|'single-item'
 *   $gallery_url   - URL to gallery page
 *   $my_bids_url   - URL to My Bids page (optional)
 *   $checkout_url  - URL to checkout page (optional)
 *   $bidder_name   - Display name of current bidder
 *   $cart_count    - Number of items in cart (0 to hide icon)
 *   $is_logged_in  - Whether the user is authenticated
 */
if (!defined('ABSPATH')) exit;

$active_page  = isset($active_page) ? $active_page : '';
$gallery_url  = isset($gallery_url) ? $gallery_url : '';
$my_bids_url  = isset($my_bids_url) ? $my_bids_url : '';
$checkout_url = isset($checkout_url) ? $checkout_url : '';
$bidder_name  = isset($bidder_name) ? $bidder_name : '';
$cart_count   = isset($cart_count) ? (int) $cart_count : 0;
$is_logged_in = isset($is_logged_in) ? $is_logged_in : false;
?>
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo"><?php _e('Art in Heaven', 'art-in-heaven'); ?></a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link<?php echo $active_page === 'gallery' ? ' aih-nav-active' : ''; ?>"><?php _e('Gallery', 'art-in-heaven'); ?></a>
                <?php if ($my_bids_url): ?>
                <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-nav-link<?php echo $active_page === 'my-bids' ? ' aih-nav-active' : ''; ?>"><?php _e('My Bids', 'art-in-heaven'); ?></a>
                <?php endif; ?>
                <?php if ($active_page === 'my-wins'): ?>
                <a href="#" class="aih-nav-link aih-nav-active"><?php _e('My Collection', 'art-in-heaven'); ?></a>
                <?php endif; ?>
            </nav>
            <div class="aih-header-actions">
                <?php if ($is_logged_in): ?>
                <button type="button" class="aih-notify-btn" id="aih-notify-btn" title="<?php esc_attr_e('Notification settings', 'art-in-heaven'); ?>"><svg class="aih-notify-icon aih-icon-bell" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><svg class="aih-notify-icon aih-icon-bell-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.73 21a2 2 0 0 1-3.46 0"/><path d="M18.63 13A17.89 17.89 0 0 1 18 8"/><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"/><path d="M18 8a6 6 0 0 0-9.33-5"/><line x1="1" y1="1" x2="23" y2="23"/></svg><span class="aih-notify-label"><?php _e('Alerts', 'art-in-heaven'); ?></span></button>
                <button type="button" class="aih-theme-toggle" id="aih-theme-toggle" title="<?php esc_attr_e('Toggle dark mode', 'art-in-heaven'); ?>"><svg class="aih-theme-icon aih-icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="aih-theme-icon aih-icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><span class="aih-theme-toggle-label"><?php _e('Theme', 'art-in-heaven'); ?></span></button>
                <?php if ($checkout_url && $cart_count > 0): ?>
                <a href="<?php echo esc_url($checkout_url); ?>" class="aih-cart-link" aria-label="<?php printf(esc_attr__('Cart (%d items)', 'art-in-heaven'), $cart_count); ?>">
                    <span class="aih-cart-icon">&#128722;</span>
                    <span class="aih-cart-count"><?php echo $cart_count; ?></span>
                </a>
                <?php endif; ?>
                <div class="aih-user-menu">
                    <?php if ($my_bids_url && $active_page !== 'my-bids'): ?>
                    <a href="<?php echo esc_url($my_bids_url); ?>" class="aih-user-name aih-user-name-link"><?php echo esc_html($bidder_name); ?></a>
                    <?php else: ?>
                    <span class="aih-user-name"><?php echo esc_html($bidder_name); ?></span>
                    <?php endif; ?>
                    <button type="button" class="aih-logout-btn" id="aih-logout" title="<?php esc_attr_e('Sign Out', 'art-in-heaven'); ?>" aria-label="<?php esc_attr_e('Sign Out', 'art-in-heaven'); ?>"><svg class="aih-logout-icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span class="aih-logout-label"><?php _e('Sign Out', 'art-in-heaven'); ?></span></button>
                </div>
                <?php else: ?>
                <button type="button" class="aih-theme-toggle" id="aih-theme-toggle" title="<?php esc_attr_e('Toggle dark mode', 'art-in-heaven'); ?>"><svg class="aih-theme-icon aih-icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg><svg class="aih-theme-icon aih-icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg><span class="aih-theme-toggle-label"><?php _e('Theme', 'art-in-heaven'); ?></span></button>
                <?php endif; ?>
            </div>
        </div>
    </header>
