<?php
/**
 * Login Page Template - Elegant Design
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

// Gallery URL - try settings first, then search for shortcode
$gallery_page = get_option('aih_gallery_page', '');
if (!$gallery_page) {
    $gallery_page = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[art_in_heaven_gallery%' LIMIT 1");
}
$gallery_url = $gallery_page ? get_permalink($gallery_page) : home_url();
?>
<script>
if (typeof aihAjax === 'undefined') {
    var aihAjax = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('aih_nonce'); ?>',
        isLoggedIn: false
    };
}
</script>

<div class="aih-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo">Art in Heaven</a>
            <nav class="aih-nav">
                <a href="<?php echo esc_url($gallery_url); ?>" class="aih-nav-link">Gallery</a>
            </nav>
            <div class="aih-header-actions"></div>
        </div>
    </header>

    <main class="aih-main aih-main-centered">
        <div class="aih-login-card">
            <div class="aih-login-header">
                <div class="aih-ornament">✦</div>
                <h1>Welcome</h1>
                <p>Enter your confirmation code to access the auction</p>
            </div>
            
            <div class="aih-login-form">
                <div class="aih-field">
                    <label for="aih-login-code">Confirmation Code</label>
                    <input type="text" id="aih-login-code" placeholder="XXXXXXXX" autocomplete="off" spellcheck="false">
                </div>
                <input type="hidden" id="aih-redirect-to" value="<?php echo esc_attr($redirect_to); ?>">
                <button type="button" id="aih-login-submit" class="aih-btn">Sign In</button>
                <div id="aih-login-message" class="aih-message"></div>
            </div>
            
            <div class="aih-login-footer" style="text-align: center; padding: 20px 40px; background: var(--color-bg-alt, #f5f3f0); border-top: 1px solid var(--color-border, #e8e6e3);">
                <p style="font-size: 13px; color: var(--color-muted, #8a8a8a);">Don't have a code? <a href="#" style="color: var(--color-accent, #b8956b); text-decoration: none; font-weight: 500;">Register for the event</a></p>
            </div>
        </div>
    </main>

    <footer class="aih-footer">
        <p>&copy; <?php echo date('Y'); ?> Art in Heaven. All rights reserved.</p>
    </footer>
</div>

<script>
jQuery(document).ready(function($) {
    $('#aih-login-submit').on('click', function() {
        var code = $('#aih-login-code').val().trim().toUpperCase();
        var redirect = $('#aih-redirect-to').val();
        var $msg = $('#aih-login-message');
        
        if (!code) {
            $msg.removeClass('success').addClass('error').text('Please enter your confirmation code').show();
            return;
        }
        
        var $btn = $(this).prop('disabled', true).addClass('loading');
        $msg.hide();
        
        $.ajax({
            url: aihAjax.ajaxurl,
            type: 'POST',
            data: { action: 'aih_verify_code', nonce: aihAjax.nonce, code: code },
            success: function(response) {
                if (response.success) {
                    $msg.removeClass('error').addClass('success').text(response.data.message).show();
                    setTimeout(function() {
                        window.location.href = redirect || location.href;
                    }, 800);
                } else {
                    $msg.removeClass('success').addClass('error').text(response.data.message).show();
                    $btn.prop('disabled', false).removeClass('loading');
                }
            },
            error: function() {
                $msg.removeClass('success').addClass('error').text('Connection error. Please try again.').show();
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    });
    
    $('#aih-login-code').on('keypress', function(e) {
        if (e.which === 13) $('#aih-login-submit').click();
    }).on('input', function() {
        this.value = this.value.toUpperCase();
    });
});
</script>

<?php include(dirname(__FILE__) . '/../assets/css/elegant-theme.php'); ?>
