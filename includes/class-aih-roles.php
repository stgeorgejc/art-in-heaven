<?php
/**
 * Roles & Capabilities Management
 *
 * Three admin roles:
 * 1. AIH - Super Admin — Full access to everything
 * 2. AIH - Art Manager — Add/edit art, view reports, manage pickup
 * 3. AIH - Operations — Manage registrants and pickup
 */

if (!defined('ABSPATH')) {
    exit;
}

class AIH_Roles {

    /** @var self|null */
    private static $instance = null;
    
    // Role slugs
    const ROLE_SUPER_ADMIN = 'aih_super_admin';
    const ROLE_ART_MANAGER = 'aih_art_manager';
    const ROLE_OPERATIONS = 'aih_operations';
    
    // Capabilities
    const CAP_MANAGE_AUCTION = 'aih_manage_auction';        // Full access
    const CAP_MANAGE_ART = 'aih_manage_art';                // Add/edit art pieces
    const CAP_VIEW_BIDS = 'aih_view_bids';                  // View bid amounts
    const CAP_VIEW_FINANCIAL = 'aih_view_financial';        // View orders, payments, totals
    const CAP_MANAGE_BIDDERS = 'aih_manage_bidders';        // View/sync bidders
    const CAP_MANAGE_SETTINGS = 'aih_manage_settings';      // Plugin settings
    const CAP_VIEW_REPORTS = 'aih_view_reports';            // Reports & exports
    const CAP_MANAGE_PICKUP = 'aih_manage_pickup';          // Manage pickup status
    
    /** @var array<int, string> Legacy roles to clean up */
    private static $legacy_roles = array(
        'sa_super_admin',
        'sa_art_manager',
        'silent_auction_admin',
        'auction_manager',
        'sa_admin',
        'aih_admin',
        'aih_pickup_manager',
    );

    /** @var string Bump this key when adding new legacy roles so cleanup re-runs */
    private static $legacy_cleanup_key = 'aih_legacy_roles_cleaned_v2';

    /**
     * @return self
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add capabilities to admin on init
        add_action('admin_init', array($this, 'ensure_admin_caps'));

        // One-time cleanup of legacy roles (bumped key triggers re-run)
        add_action('admin_init', array($this, 'cleanup_legacy_roles'));
    }

    /**
     * Clean up any legacy roles from previous versions
     *
     * @return void
     */
    public function cleanup_legacy_roles() {
        if (get_option(self::$legacy_cleanup_key, false)) {
            return;
        }

        // Migrate users from removed roles to their replacements
        self::migrate_legacy_role_users();

        foreach (self::$legacy_roles as $legacy_role) {
            remove_role($legacy_role);
        }

        update_option(self::$legacy_cleanup_key, true);
    }

    /**
     * Migrate users from removed roles to their replacement roles
     *
     * @return void
     */
    private static function migrate_legacy_role_users() {
        $migration_map = array(
            'aih_pickup_manager' => self::ROLE_OPERATIONS,
        );

        foreach ($migration_map as $old_role => $new_role) {
            $users = get_users(array('role' => $old_role));
            foreach ($users as $user) {
                $user->remove_role($old_role);
                $user->add_role($new_role);
            }
        }
    }
    
    /**
     * Install roles and capabilities (run on plugin activation)
     *
     * @return void
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
            $admin->add_cap(self::CAP_MANAGE_PICKUP);
        }
        
        // Create AIH - Super Admin role (full access)
        remove_role(self::ROLE_SUPER_ADMIN);
        add_role(
            self::ROLE_SUPER_ADMIN,
            __('AIH - Super Admin', 'art-in-heaven'),
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
                self::CAP_MANAGE_PICKUP => true,
            )
        );

        // Create AIH - Art Manager role (art + reports + pickup)
        remove_role(self::ROLE_ART_MANAGER);
        add_role(
            self::ROLE_ART_MANAGER,
            __('AIH - Art Manager', 'art-in-heaven'),
            array(
                'read' => true,
                'upload_files' => true,
                self::CAP_MANAGE_ART => true,
                self::CAP_VIEW_REPORTS => true,
                self::CAP_MANAGE_PICKUP => true,
            )
        );

        // Create AIH - Operations role (registrants + pickup)
        remove_role(self::ROLE_OPERATIONS);
        add_role(
            self::ROLE_OPERATIONS,
            __('AIH - Operations', 'art-in-heaven'),
            array(
                'read' => true,
                self::CAP_MANAGE_BIDDERS => true,
                self::CAP_MANAGE_PICKUP => true,
            )
        );

        // Mark legacy cleanup as done
        update_option(self::$legacy_cleanup_key, true);
    }
    
    /**
     * Remove roles and capabilities (run on plugin deactivation)
     *
     * @return void
     */
    public static function uninstall() {
        // Remove custom roles
        remove_role(self::ROLE_SUPER_ADMIN);
        remove_role(self::ROLE_ART_MANAGER);
        remove_role(self::ROLE_OPERATIONS);
        
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
            $admin->remove_cap(self::CAP_MANAGE_PICKUP);
        }
        
        // Clean up options (current + any old keys)
        delete_option(self::$legacy_cleanup_key);
        delete_option('aih_legacy_roles_cleaned');
    }
    
    /**
     * Ensure admin always has all caps (in case they were removed)
     *
     * @return void
     */
    public function ensure_admin_caps() {
        $admin = get_role('administrator');
        if (!$admin) {
            return;
        }

        $required = array(
            self::CAP_MANAGE_AUCTION,
            self::CAP_MANAGE_ART,
            self::CAP_VIEW_BIDS,
            self::CAP_VIEW_FINANCIAL,
            self::CAP_MANAGE_BIDDERS,
            self::CAP_MANAGE_SETTINGS,
            self::CAP_VIEW_REPORTS,
            self::CAP_MANAGE_PICKUP,
        );

        foreach ($required as $cap) {
            if (!$admin->has_cap($cap)) {
                self::install();
                return;
            }
        }
    }
    
    /**
     * Check if current user can manage auction (super admin level)
     *
     * @return bool
     */
    public static function can_manage_auction() {
        return current_user_can(self::CAP_MANAGE_AUCTION) || current_user_can('manage_options');
    }
    
    /**
     * Check if current user can manage art pieces
     *
     * @return bool
     */
    public static function can_manage_art() {
        return current_user_can(self::CAP_MANAGE_ART) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view bid amounts
     *
     * @return bool
     */
    public static function can_view_bids() {
        return current_user_can(self::CAP_VIEW_BIDS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view financial data (orders, payments, totals)
     *
     * @return bool
     */
    public static function can_view_financial() {
        return current_user_can(self::CAP_VIEW_FINANCIAL) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can manage bidders
     *
     * @return bool
     */
    public static function can_manage_bidders() {
        return current_user_can(self::CAP_MANAGE_BIDDERS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can manage settings
     *
     * @return bool
     */
    public static function can_manage_settings() {
        return current_user_can(self::CAP_MANAGE_SETTINGS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can view reports
     *
     * @return bool
     */
    public static function can_view_reports() {
        return current_user_can(self::CAP_VIEW_REPORTS) || self::can_manage_auction();
    }
    
    /**
     * Check if current user can manage pickup
     *
     * @return bool
     */
    public static function can_manage_pickup() {
        return current_user_can(self::CAP_MANAGE_PICKUP) || self::can_manage_auction();
    }

    /**
     * Check if current user has any AIH capability (can access menu at all)
     *
     * @return bool
     */
    public static function can_access_menu() {
        return self::can_manage_art() || self::can_manage_pickup() || self::can_manage_bidders() || self::can_manage_auction();
    }

    /**
     * Get minimum capability required for menu access
     *
     * @return string
     */
    public static function get_menu_capability() {
        return self::CAP_MANAGE_ART;
    }
    
    /**
     * Get all AIH roles for display
     *
     * @return array<string, array{name: string, description: string}>
     */
    public static function get_roles() {
        return array(
            self::ROLE_SUPER_ADMIN => array(
                'name' => __('AIH - Super Admin', 'art-in-heaven'),
                'description' => __('Full access to all Art in Heaven features including financial data, settings, and reports.', 'art-in-heaven'),
            ),
            self::ROLE_ART_MANAGER => array(
                'name' => __('AIH - Art Manager', 'art-in-heaven'),
                'description' => __('Can add and edit art pieces, view reports, and manage pickup. Cannot view bids, orders, payments, or settings.', 'art-in-heaven'),
            ),
            self::ROLE_OPERATIONS => array(
                'name' => __('AIH - Operations', 'art-in-heaven'),
                'description' => __('Can manage registrants and pickup status. Cannot view art pieces, bids, orders, payments, or settings.', 'art-in-heaven'),
            ),
        );
    }
    
    /**
     * Get capabilities for a role
     *
     * @param string $role_slug
     * @return array<int, string>
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
                self::CAP_MANAGE_PICKUP,
            ),
            self::ROLE_ART_MANAGER => array(
                self::CAP_MANAGE_ART,
                self::CAP_VIEW_REPORTS,
                self::CAP_MANAGE_PICKUP,
            ),
            self::ROLE_OPERATIONS => array(
                self::CAP_MANAGE_BIDDERS,
                self::CAP_MANAGE_PICKUP,
            ),
        );
        
        return isset($caps[$role_slug]) ? $caps[$role_slug] : array();
    }
}
