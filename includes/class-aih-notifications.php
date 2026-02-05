<?php
/**
 * Notifications Class
 * 
 * Handles email notifications and in-app alerts for:
 * - Outbid notifications
 * - Auction ending reminders
 * - Winning bid confirmations
 * - New art piece announcements
 * 
 * @package ArtInHeaven
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Notifications {
    
    /**
     * Single instance
     * @var AIH_Notifications|null
     */
    private static $instance = null;
    
    /**
     * Email from address
     * @var string
     */
    private $from_email;
    
    /**
     * Email from name
     * @var string
     */
    private $from_name;
    
    /**
     * Get single instance
     * @return AIH_Notifications
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->from_email = get_option('aih_notification_email', get_option('admin_email'));
        $this->from_name = get_option('aih_notification_name', get_bloginfo('name'));
        
        // Hook into events
        add_action('aih_bid_placed', array($this, 'notify_outbid'), 10, 3);
        add_action('aih_auctions_expired', array($this, 'notify_auction_ended'));
        add_action('aih_order_created', array($this, 'notify_order_confirmation'), 10, 2);
        
        // Scheduled notifications
        add_action('aih_send_ending_reminders', array($this, 'send_ending_reminders'));
    }
    
    /**
     * Send outbid notification
     * 
     * @param int    $bid_id      New bid ID
     * @param int    $art_id      Art piece ID
     * @param string $new_bidder  New bidder email
     */
    public function notify_outbid($bid_id, $art_id, $new_bidder) {
        if (!get_option('aih_enable_outbid_notifications', true)) {
            return;
        }
        
        global $wpdb;
        $bids_table = AIH_Database::get_table('bids');
        $art_table = AIH_Database::get_table('art_pieces');
        $bidders_table = AIH_Database::get_table('bidders');
        
        // Get the art piece
        $art = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $art_table WHERE id = %d",
            $art_id
        ));
        
        if (!$art) return;
        
        // Get previous winning bidder
        $previous_winner = $wpdb->get_row($wpdb->prepare(
            "SELECT b.bidder_id, bd.name_first, bd.email_primary
             FROM $bids_table b
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.email_primary
             WHERE b.art_piece_id = %d AND b.bidder_id != %s
             ORDER BY b.bid_amount DESC
             LIMIT 1",
            $art_id,
            $new_bidder
        ));
        
        if (!$previous_winner || empty($previous_winner->email_primary)) {
            return;
        }
        
        // Don't notify if they weren't actually the previous winner
        $was_winning = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bids_table 
             WHERE art_piece_id = %d AND bidder_id = %s AND is_winning = 0
             ORDER BY bid_time DESC LIMIT 1",
            $art_id,
            $previous_winner->bidder_id
        ));
        
        // Send notification
        $subject = sprintf(
            __('[%s] You\'ve been outbid on "%s"', 'art-in-heaven'),
            get_bloginfo('name'),
            $art->title
        );
        
        $message = $this->get_email_template('outbid', array(
            'bidder_name' => $previous_winner->name_first ?: 'Bidder',
            'art_title' => $art->title,
            'art_id' => $art->art_id,
            'current_bid' => number_format($art->current_bid, 2),
            'gallery_url' => $this->get_gallery_url($art->id),
        ));
        
        $this->send_email($previous_winner->email_primary, $subject, $message);
    }
    
    /**
     * Notify auction ended
     * 
     * @param int $count Number of auctions ended
     */
    public function notify_auction_ended($count) {
        if (!get_option('aih_enable_winner_notifications', true)) {
            return;
        }
        
        global $wpdb;
        $art_table = AIH_Database::get_table('art_pieces');
        $bids_table = AIH_Database::get_table('bids');
        $bidders_table = AIH_Database::get_table('bidders');
        
        $now = current_time('mysql');
        
        // Get recently ended auctions with winning bids
        $winners = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, b.bidder_id, b.bid_amount, bd.name_first, bd.email_primary
             FROM $art_table a
             JOIN $bids_table b ON a.id = b.art_piece_id AND b.is_winning = 1
             LEFT JOIN $bidders_table bd ON b.bidder_id = bd.email_primary
             WHERE a.status = 'ended' 
             AND a.auction_end >= DATE_SUB(%s, INTERVAL 1 HOUR)
             AND a.auction_end <= %s",
            $now, $now
        ));
        
        foreach ($winners as $winner) {
            if (empty($winner->email_primary)) continue;
            
            // Check if we already notified
            $notified = get_transient('aih_won_notified_' . $winner->id . '_' . $winner->bidder_id);
            if ($notified) continue;
            
            $subject = sprintf(
                __('[%s] Congratulations! You won "%s"', 'art-in-heaven'),
                get_bloginfo('name'),
                $winner->title
            );
            
            $message = $this->get_email_template('winner', array(
                'bidder_name' => $winner->name_first ?: 'Winner',
                'art_title' => $winner->title,
                'art_id' => $winner->art_id,
                'winning_bid' => number_format($winner->bid_amount, 2),
                'checkout_url' => $this->get_checkout_url(),
            ));
            
            if ($this->send_email($winner->email_primary, $subject, $message)) {
                set_transient('aih_won_notified_' . $winner->id . '_' . $winner->bidder_id, 1, DAY_IN_SECONDS);
            }
        }
    }
    
    /**
     * Send order confirmation
     * 
     * @param int   $order_id Order ID
     * @param array $items    Order items
     */
    public function notify_order_confirmation($order_id, $items) {
        global $wpdb;
        $orders_table = AIH_Database::get_table('orders');
        $bidders_table = AIH_Database::get_table('bidders');
        
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT o.*, bd.name_first, bd.email_primary
             FROM $orders_table o
             LEFT JOIN $bidders_table bd ON o.bidder_id = bd.email_primary
             WHERE o.id = %d",
            $order_id
        ));
        
        if (!$order || empty($order->email_primary)) return;
        
        $subject = sprintf(
            __('[%s] Order Confirmation #%s', 'art-in-heaven'),
            get_bloginfo('name'),
            $order->order_number
        );
        
        $message = $this->get_email_template('order_confirmation', array(
            'bidder_name' => $order->name_first ?: 'Customer',
            'order_number' => $order->order_number,
            'subtotal' => number_format($order->subtotal, 2),
            'tax' => number_format($order->tax, 2),
            'total' => number_format($order->total, 2),
            'items' => $items,
        ));
        
        $this->send_email($order->email_primary, $subject, $message);
    }
    
    /**
     * Send ending soon reminders
     */
    public function send_ending_reminders() {
        if (!get_option('aih_enable_ending_reminders', true)) {
            return;
        }
        
        global $wpdb;
        $art_table = AIH_Database::get_table('art_pieces');
        $bids_table = AIH_Database::get_table('bids');
        $bidders_table = AIH_Database::get_table('bidders');
        $favorites_table = AIH_Database::get_table('favorites');
        
        $now = current_time('mysql');
        
        // Get auctions ending in 1 hour
        $ending_soon = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, 
                    GROUP_CONCAT(DISTINCT COALESCE(b.bidder_id, f.bidder_id)) as interested_bidders
             FROM $art_table a
             LEFT JOIN $bids_table b ON a.id = b.art_piece_id
             LEFT JOIN $favorites_table f ON a.id = f.art_piece_id
             WHERE a.status = 'active'
             AND a.auction_end > %s
             AND a.auction_end <= DATE_ADD(%s, INTERVAL 1 HOUR)
             GROUP BY a.id",
            $now, $now
        ));
        
        foreach ($ending_soon as $art) {
            if (empty($art->interested_bidders)) continue;
            
            $bidder_emails = explode(',', $art->interested_bidders);
            
            foreach ($bidder_emails as $email) {
                if (empty($email)) continue;
                
                // Check if already reminded
                $reminded = get_transient('aih_ending_reminder_' . $art->id . '_' . md5($email));
                if ($reminded) continue;
                
                $bidder = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $bidders_table WHERE email_primary = %s",
                    $email
                ));
                
                if (!$bidder) continue;
                
                $subject = sprintf(
                    __('[%s] "%s" is ending soon!', 'art-in-heaven'),
                    get_bloginfo('name'),
                    $art->title
                );
                
                $message = $this->get_email_template('ending_soon', array(
                    'bidder_name' => $bidder->name_first ?: 'Bidder',
                    'art_title' => $art->title,
                    'art_id' => $art->art_id,
                    'current_bid' => number_format($art->current_bid, 2),
                    'ends_at' => date_i18n('M j, g:i a', strtotime($art->auction_end)),
                    'gallery_url' => $this->get_gallery_url($art->id),
                ));
                
                if ($this->send_email($email, $subject, $message)) {
                    set_transient('aih_ending_reminder_' . $art->id . '_' . md5($email), 1, 2 * HOUR_IN_SECONDS);
                }
            }
        }
    }
    
    /**
     * Send email
     * 
     * @param string $to      Recipient email
     * @param string $subject Email subject
     * @param string $message Email body (HTML)
     * @return bool
     */
    private function send_email($to, $subject, $message) {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $this->from_name, $this->from_email),
        );
        
        // Wrap message in template
        $html = $this->wrap_email_template($message);
        
        return wp_mail($to, $subject, $html, $headers);
    }
    
    /**
     * Get email template
     * 
     * @param string $template Template name
     * @param array  $vars     Template variables
     * @return string
     */
    private function get_email_template($template, $vars = array()) {
        $templates = array(
            'outbid' => '
                <h2>' . __('You\'ve Been Outbid!', 'art-in-heaven') . '</h2>
                <p>' . sprintf(__('Hi %s,', 'art-in-heaven'), '{bidder_name}') . '</p>
                <p>' . sprintf(__('Someone has placed a higher bid on "%s" (Art ID: %s).', 'art-in-heaven'), '{art_title}', '{art_id}') . '</p>
                <p>' . sprintf(__('Current bid: $%s', 'art-in-heaven'), '{current_bid}') . '</p>
                <p><a href="{gallery_url}" style="background:#6366f1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;">' . __('Place a Higher Bid', 'art-in-heaven') . '</a></p>
            ',
            
            'winner' => '
                <h2>🎉 ' . __('Congratulations!', 'art-in-heaven') . '</h2>
                <p>' . sprintf(__('Hi %s,', 'art-in-heaven'), '{bidder_name}') . '</p>
                <p>' . sprintf(__('You\'ve won "%s" (Art ID: %s) with a bid of $%s!', 'art-in-heaven'), '{art_title}', '{art_id}', '{winning_bid}') . '</p>
                <p>' . __('Please complete your checkout to finalize your purchase.', 'art-in-heaven') . '</p>
                <p><a href="{checkout_url}" style="background:#10b981;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;">' . __('Complete Checkout', 'art-in-heaven') . '</a></p>
            ',
            
            'ending_soon' => '
                <h2>⏰ ' . __('Auction Ending Soon!', 'art-in-heaven') . '</h2>
                <p>' . sprintf(__('Hi %s,', 'art-in-heaven'), '{bidder_name}') . '</p>
                <p>' . sprintf(__('"%s" (Art ID: %s) is ending soon!', 'art-in-heaven'), '{art_title}', '{art_id}') . '</p>
                <p>' . sprintf(__('Current bid: $%s', 'art-in-heaven'), '{current_bid}') . '</p>
                <p>' . sprintf(__('Ends: %s', 'art-in-heaven'), '{ends_at}') . '</p>
                <p><a href="{gallery_url}" style="background:#f59e0b;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;display:inline-block;">' . __('Place Your Bid', 'art-in-heaven') . '</a></p>
            ',
            
            'order_confirmation' => '
                <h2>' . __('Order Confirmation', 'art-in-heaven') . '</h2>
                <p>' . sprintf(__('Hi %s,', 'art-in-heaven'), '{bidder_name}') . '</p>
                <p>' . sprintf(__('Thank you for your order #%s', 'art-in-heaven'), '{order_number}') . '</p>
                <table style="width:100%;border-collapse:collapse;margin:20px 0;">
                    <tr><td style="padding:8px;border-bottom:1px solid #eee;">' . __('Subtotal', 'art-in-heaven') . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">$' . '{subtotal}' . '</td></tr>
                    <tr><td style="padding:8px;border-bottom:1px solid #eee;">' . __('Tax', 'art-in-heaven') . '</td><td style="padding:8px;border-bottom:1px solid #eee;text-align:right;">$' . '{tax}' . '</td></tr>
                    <tr><td style="padding:8px;font-weight:bold;">' . __('Total', 'art-in-heaven') . '</td><td style="padding:8px;text-align:right;font-weight:bold;">$' . '{total}' . '</td></tr>
                </table>
            ',
        );
        
        $content = isset($templates[$template]) ? $templates[$template] : '';
        
        // Replace variables
        foreach ($vars as $key => $value) {
            if (!is_array($value)) {
                $content = str_replace('{' . $key . '}', esc_html($value), $content);
            }
        }
        
        return $content;
    }
    
    /**
     * Wrap email content in HTML template
     * 
     * @param string $content Email content
     * @return string
     */
    private function wrap_email_template($content) {
        $logo_url = get_option('aih_logo_url', '');
        $site_name = get_bloginfo('name');
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="max-width:600px;margin:0 auto;padding:40px 20px;">
                <div style="background:#fff;border-radius:12px;padding:40px;box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                    ' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-width:200px;margin-bottom:20px;">' : '<h1 style="margin:0 0 20px;color:#6366f1;">' . esc_html($site_name) . '</h1>') . '
                    ' . $content . '
                </div>
                <div style="text-align:center;padding:20px;color:#71717a;font-size:12px;">
                    <p>' . esc_html($site_name) . ' | ' . __('Art in Heaven', 'art-in-heaven') . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get gallery URL for an art piece
     * 
     * @param int $art_id Art piece ID
     * @return string
     */
    private function get_gallery_url($art_id = null) {
        $page_id = get_option('aih_gallery_page', 0);
        $url = $page_id ? get_permalink($page_id) : home_url('/auction/');
        
        if ($art_id) {
            $url = add_query_arg('art_id', $art_id, $url);
        }
        
        return $url;
    }
    
    /**
     * Get checkout URL
     * 
     * @return string
     */
    private function get_checkout_url() {
        $page_id = get_option('aih_checkout_page', 0);
        return $page_id ? get_permalink($page_id) : home_url('/checkout/');
    }
}
