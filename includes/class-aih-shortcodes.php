<?php
/**
 * Shortcodes Handler - With Login Page & Redirects
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcodes
        add_shortcode('art_in_heaven_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('art_in_heaven_item', array($this, 'item_shortcode'));
        add_shortcode('art_in_heaven_my_bids', array($this, 'my_bids_shortcode'));
        add_shortcode('art_in_heaven_checkout', array($this, 'checkout_shortcode'));
        add_shortcode('art_in_heaven_login', array($this, 'login_shortcode'));
        add_shortcode('art_in_heaven_winners', array($this, 'winners_shortcode'));
        add_shortcode('art_in_heaven_my_wins', array($this, 'my_wins_shortcode'));

        // Clean URLs: register rewrite rule for /gallery/art/{id}
        add_action('init', array($this, 'register_art_rewrite_rules'));
        add_filter('query_vars', array($this, 'register_art_query_vars'));
        add_action('update_option_aih_gallery_page', array($this, 'flush_art_rewrite_rules'));

        // Handle login redirect and legacy URL redirect
        add_action('template_redirect', array($this, 'redirect_legacy_art_urls'), 5);
        add_action('template_redirect', array($this, 'check_login_required'));
    }

    /**
     * Register rewrite rule for clean art piece URLs
     *
     * Maps {gallery-slug}/art/{id} to the gallery page with aih_art_id query var.
     */
    public function register_art_rewrite_rules() {
        $gallery_page_id = get_option('aih_gallery_page', '');
        if (!$gallery_page_id) return;

        $page = get_post($gallery_page_id);
        if (!$page) return;

        $slug = $page->post_name;
        add_rewrite_rule(
            '^' . preg_quote($slug, '/') . '/art/([0-9]+)/?$',
            'index.php?pagename=' . $slug . '&aih_art_id=$matches[1]',
            'top'
        );
    }

    /**
     * Register custom query variable for clean art URLs
     */
    public function register_art_query_vars($vars) {
        $vars[] = 'aih_art_id';
        return $vars;
    }

    /**
     * 301 redirect legacy ?art_id= URLs to clean /art/{id}/ URLs
     */
    public function redirect_legacy_art_urls() {
        if (isset($_GET['art_id']) && !empty($_GET['art_id'])) {
            $clean_url = AIH_Template_Helper::get_art_url(intval($_GET['art_id']));
            wp_safe_redirect($clean_url, 301);
            exit;
        }
    }

    /**
     * Flush rewrite rules when gallery page option changes
     */
    public function flush_art_rewrite_rules() {
        $this->register_art_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Check if login is required and redirect
     */
    public function check_login_required() {
        $auth = AIH_Auth::get_instance();
        $login_page = get_option('aih_login_page', '');
        
        if (empty($login_page) || $auth->is_logged_in()) {
            return;
        }
        
        // Check if current page requires login
        global $post;
        if (!$post) return;
        
        $requires_login = array(
            'art_in_heaven_my_bids',
            'art_in_heaven_checkout',
            'art_in_heaven_my_wins'
        );
        
        foreach ($requires_login as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                $redirect_url = add_query_arg('redirect_to', urlencode(get_permalink()), $login_page);
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }
    
    /**
     * Login page shortcode
     */
    public function login_shortcode($atts) {
        $auth = AIH_Auth::get_instance();
        
        // If already logged in, redirect or show message
        if ($auth->is_logged_in()) {
            $redirect_to = isset($_GET['redirect_to']) ? wp_validate_redirect($_GET['redirect_to'], home_url()) : '';
            if ($redirect_to) {
                wp_safe_redirect($redirect_to);
                exit;
            }
            $bidder = $auth->get_current_bidder();
            $name = !empty($bidder->name_first) ? $bidder->name_first : (!empty($bidder->email_primary) ? $bidder->email_primary : '');
            return '<div class="aih-login-success"><p>' . sprintf(__('Welcome, %s! You are already signed in.', 'art-in-heaven'), esc_html($name)) . '</p><button type="button" onclick="jQuery.post(aihAjax.ajaxurl, {action:\'aih_logout\'}, function(){location.reload();});" class="aih-btn-secondary">' . __('Sign Out', 'art-in-heaven') . '</button></div>';
        }
        
        $redirect_to = isset($_GET['redirect_to']) ? esc_url($_GET['redirect_to']) : '';
        
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/login.php';
        return ob_get_clean();
    }
    
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array('columns' => 3, 'per_page' => -1), $atts);
        
        // Check if viewing my bids
        if (isset($_GET['my_bids']) && $_GET['my_bids'] == '1') {
            ob_start();
            include AIH_PLUGIN_DIR . 'templates/my-bids.php';
            return ob_get_clean();
        }
        
        // Check if viewing checkout
        if (isset($_GET['checkout']) && $_GET['checkout'] == '1') {
            ob_start();
            include AIH_PLUGIN_DIR . 'templates/checkout.php';
            return ob_get_clean();
        }
        
        // Check if viewing individual art piece (clean URL or legacy query string)
        $art_id = intval(get_query_var('aih_art_id', 0));
        if ($art_id) {
            $art_model = new AIH_Art_Piece();
            $art_piece = $art_model->get($art_id);

            if ($art_piece && (!isset($art_piece->computed_status) || $art_piece->computed_status !== 'upcoming')) {
                ob_start();
                include AIH_PLUGIN_DIR . 'templates/single-item.php';
                return ob_get_clean();
            }
        }
        
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/gallery.php';
        return ob_get_clean();
    }
    
    public function item_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        if (!$atts['id']) return '<p>' . __('Please specify an art piece ID.', 'art-in-heaven') . '</p>';
        
        $art_model = new AIH_Art_Piece();
        $art_piece = $art_model->get($atts['id']);
        if (!$art_piece) return '<p>' . __('Art piece not found.', 'art-in-heaven') . '</p>';
        
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/single-item.php';
        return ob_get_clean();
    }
    
    public function my_bids_shortcode($atts) {
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/my-bids.php';
        return ob_get_clean();
    }
    
    public function checkout_shortcode($atts) {
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }
    
    public function winners_shortcode($atts) {
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/winners.php';
        return ob_get_clean();
    }

    public function my_wins_shortcode($atts) {
        ob_start();
        include AIH_PLUGIN_DIR . 'templates/my-wins.php';
        return ob_get_clean();
    }
}
