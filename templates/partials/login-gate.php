<?php
/**
 * Login Gate Partial
 *
 * Accepts (via extract or direct variable scope):
 *   $sub_heading  - Text below the "Sign In Required" heading
 *   $gallery_url  - URL for the logo link
 */
if (!defined('ABSPATH')) exit;

$sub_heading = isset($sub_heading) ? $sub_heading : __('Enter your confirmation code to access the auction', 'art-in-heaven');
$gallery_url = isset($gallery_url) ? $gallery_url : home_url();
?>
<div class="aih-page">
    <header class="aih-header">
        <div class="aih-header-inner">
            <a href="<?php echo esc_url($gallery_url); ?>" class="aih-logo"><?php _e('Art in Heaven', 'art-in-heaven'); ?></a>
        </div>
    </header>
    <main class="aih-main aih-main-centered">
        <div class="aih-login-card">
            <div class="aih-login-header">
                <div class="aih-ornament">&#10022;</div>
                <h1><?php _e('Sign In Required', 'art-in-heaven'); ?></h1>
                <p><?php echo esc_html($sub_heading); ?></p>
            </div>
            <div class="aih-login-form">
                <div class="aih-field">
                    <label for="aih-login-code"><?php _e('Confirmation Code', 'art-in-heaven'); ?></label>
                    <input type="text" id="aih-login-code" placeholder="XXXXXXXX" autocomplete="off">
                </div>
                <button type="button" id="aih-login-btn" class="aih-btn"><?php _e('Sign In', 'art-in-heaven'); ?></button>
                <div id="aih-login-msg" class="aih-message"></div>
            </div>
        </div>
    </main>
</div>
<script>
jQuery(document).ready(function($) {
    $('#aih-login-btn').on('click', function() {
        var code = $('#aih-login-code').val().trim().toUpperCase();
        var $btn = $(this);
        var $msg = $('#aih-login-msg');
        if (!code) { $msg.addClass('error').text(aihAjax.strings.enterCode || 'Please enter your code').show(); return; }
        $btn.prop('disabled', true).addClass('loading');
        $.post(aihApiUrl('verify-code'), {action:'aih_verify_code', nonce:aihAjax.nonce, code:code}, function(r) {
            if (r.success) location.reload();
            else { $msg.addClass('error').text(r.data.message || 'Invalid code').show(); $btn.prop('disabled', false).removeClass('loading'); }
        }).fail(function() {
            $btn.prop('disabled', false).removeClass('loading');
            $msg.text(aihAjax.strings.networkError || 'Network error. Please try again.').addClass('error').show();
        });
    });
    $('#aih-login-code').on('keypress', function(e) { if (e.which === 13) $('#aih-login-btn').click(); })
        .on('input', function() { this.value = this.value.toUpperCase(); });
});
</script>
