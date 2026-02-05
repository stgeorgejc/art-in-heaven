<?php
/**
 * Roles & Capabilities Management
 * 
 * Two admin roles:
 * 1. AIH Super Admin - Full access to everything
 * 2. AIH Art Manager - Can only add/edit art pieces, no financial data
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Roles {
    
    private static $instance = null;
    
    // Role slugs
    const ROLE_SUPER_ADMIN = 'aih_super_admin';
    const ROLE_ART_MANAGER = 'aih_art_manager';
    
    // Capabilities
    const CAP_MANAGE_AUCTION = 'aih_manage_auction';        // Full access
    const CAP_MANAGE_ART = 'aih_manage_art';                // Add/edit art pieces
    const CAP_VIEW_BIDS = 'aih_view_bids';                  // View bid amounts
    const CAP_VIEW_FINANCIAL = 'aih_view_financial';        // View orders, payments, totals
    const CAP_MANAGE_BIDDERS = 'aih_manage_bidders';        // View/sync bidders
    const CAP_MANAGE_SETTINGS = 'aih_manage_settings';      // Plugin settings
    const CAP_VIEW_REPORTS = 'aih_view_reports';            // Reports & exports
    
    // Legacy roles to clean up
    private static $legacy_roles = array(
        'sa_super_admin',
        'sa_art_manager', 
        'silent_auction_admin',
        'auction_manager',
        'sa_admin',
        'aih_admin',
    );
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add capabilities to admin on init
        add_action('admin_init', array($this, 'ensure_admin_caps'));
        
        // One-time cleanup of legacy roles (v0.9.91)
        add_action('admin_init', array($this, 'cleanup_legacy_roles'));
    }
    
    /**
     * Clean up any legacy roles from previous versions
     */
    public function cleanup_legacy_roles() {
        // Only run once
        if (get_option('aih_legacy_roles_cleaned', false)) {
            return;
        }
        
        foreach (self::$legacy_roles as $legacy_role) {
            remove_role($legacy_role);
        }
        
        update_option('aih_legacy_roles_cleaned', true);
    }
    
    /**
     * Install roles and capabilities (run on plugin activation)
     */
    public static function install() {
        // Remove any legacy/old roles that might exist from previous versions
        foreach (self::$legacy_roles as $legacy_role) {
            remove_role($legacy_role);
        }
        
        // Add capabilities to Administrator role
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::CAP_MANAGE_AUCTION);
            $admin->add_cap(self::CAP_MANAGE_ART);
            $admin->add_cap(self::CAP_VIEW_BIDS);
            $admin->add_cap(self::CAP_VIEW_FINANCIAL);
            $admin->add_cap(self::CAP_MANAGE_BIDDERS);
            $admin->add_cap(self::CAP_MANAGE_SETTINGS);
            $admin->add_cap(self::CAP_VIEW_REPORTS);
        }
        
        // Create AIH Super Admin role (full access)
        remove_role(self::ROLE_SUPER_ADMIN);
        add_role(
            self::ROLE_SUPER_ADMIN,
            __('AIH Super Admin', 'art-in-heaven'),
            array(
                'read' => true,
                'upload_files' => true,
                self::CAP_MANAGE_AUCTION => true,
                self::CAP_MANAGE_ART => true,
                self::CAP_VIEW_BIDS => true,
                self::CAP_VIEW_FINANCIAL => true,
                self::CAP_MANAGE_BIDDERS => true,
                self::CAP_MANAGE_SETTINGS => true,
                self::CAP_VIEW_REPORTS => true,
            )
        );
        
        // Create AIH Art Manager role (limited access)
        remove_role(self::ROLE_ART_MANAGER);
        add_role(
            self::ROLE_ART_MANAGER,
            __('AIH Art Manager', 'art-in-heaven'),
            array(
                'read' => true,
                'upload_files' => true,
                self::CAP_MANAGE_ART => true,
                // No financial capabilities
            )
        );
        
        // Mark legacy cleanup as done
        update_option('aih_legacy_roles_cleaned', true);
    }
    
    /**
     * Remove roles and capabilities (run on plugin deactivation)
     */
    public static function uninstall() {
        // Remove custom roles
        remove_role(self::ROLE_SUPER_ADMIN);
        remove_role(self::ROLE_ART_MANAGER);
        
        // Also remove any legacy roles
        foreach (self::$legacy_roles as $legacy_role) {
            remove_role($legacy_role);
        }
        
        // Remove capabilities from Administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap(self::CAP_MANAGE_AUCTION);
            $admin->remove_cap(self::CAP_MANAGE_ART);
            $admin->remove_cap(self::CAP_VIEW_BIDS);
            $admin->remove_cap(self::CAP_VIEW_FINANCIAL);
            $admin->remove_cap(self::CAP_MANAGE_BIDDERS);
            $admin->remove_cap(self::CAP_MANAGE_SETTINGS);
            $admin->remove_cap(self::CAP_VIEW_REPORTS);
        }
        
        // Clean up option
        delete_option('aih_legacy_roles_cleaned');
    }
    
    /**
     * Ensure admin always has all caps (in case they were removed)
     */
    public function ensure_admin_caps() {
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap(self::CAP_MANAGE_AUCTION)) {
            self::install();
        }
    }
    
    /**
     * Check if current user can manage auction (super admin level)
     */
    public static function can_manage_auction() {
        return current_user_can(self::CAP_MANAGE_AUCTION) || current_user_can('manage_options');
    }
    
    /**
     * Check if current user can manage art pieces
     */
    public static function can_manage_art() {
        return current_user_can(self::CAP_MANAGE_ART) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view bid amounts
     */
    public static function can_view_bids() {
        return current_user_can(self::CAP_VIEW_BIDS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view financial data (orders, payments, totals)
     */
    public static function can_view_financial() {
        return current_user_can(self::CAP_VIEW_FINANCIAL) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can manage bidders
     */
    public static function can_manage_bidders() {
        return current_user_can(self::CAP_MANAGE_BIDDERS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can manage settings
     */
    public static function can_manage_settings() {
        return current_user_can(self::CAP_MANAGE_SETTINGS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view reports
     */
    public static function can_view_reports() {
        return current_user_can(self::CAP_VIEW_REPORTS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user has any AIH capability (can access menu at all)
     */
    public static function can_access_menu() {
        return self::can_manage_art() || self::can_manage_auction();
    }
    
    /**
     * Get minimum capability required for menu access
     */
    public static function get_menu_capability() {
        return self::CAP_MANAGE_ART;
    }
    
    /**
     * Get all AIH roles for display
     */
    public static function get_roles() {
        return array(
            self::ROLE_SUPER_ADMIN => array(
                'name' => __('AIH Super Admin', 'art-in-heaven'),
                'description' => __('Full access to all Art in Heaven features including financial data, settings, and reports.', 'art-in-heaven'),
            ),
            self::ROLE_ART_MANAGER => array(
                'name' => __('AIH Art Manager', 'art-in-heaven'),
                'description' => __('Can add and edit art pieces only. Cannot view bids, orders, payments, or settings.', 'art-in-heaven'),
            ),
        );
    }
    
    /**
     * Get capabilities for a role
     */
    public static function get_role_capabilities($role_slug) {
        $caps = array(
            self::ROLE_SUPER_ADMIN => array(
                self::CAP_MANAGE_AUCTION,
                self::CAP_MANAGE_ART,
                self::CAP_VIEW_BIDS,
                self::CAP_VIEW_FINANCIAL,
                self::CAP_MANAGE_BIDDERS,
                self::CAP_MANAGE_SETTINGS,
                self::CAP_VIEW_REPORTS,
            ),
            self::ROLE_ART_MANAGER => array(
                self::CAP_MANAGE_ART,
            ),
        );
        
        return isset($caps[$role_slug]) ? $caps[$role_slug] : array();
    }
}
