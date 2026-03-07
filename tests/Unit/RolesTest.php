<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Roles;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RolesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        if (!class_exists('AIH_Roles')) {
            require_once __DIR__ . '/../../includes/class-aih-roles.php';
        }

        // Reset singleton so each test starts fresh
        $ref = new \ReflectionClass(AIH_Roles::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── Constants ──

    public function testRoleConstantsExist(): void
    {
        $this->assertSame('aih_super_admin', AIH_Roles::ROLE_SUPER_ADMIN);
        $this->assertSame('aih_art_manager', AIH_Roles::ROLE_ART_MANAGER);
        $this->assertSame('aih_operations', AIH_Roles::ROLE_OPERATIONS);
    }

    public function testCapabilityConstantsExist(): void
    {
        $this->assertSame('aih_manage_auction', AIH_Roles::CAP_MANAGE_AUCTION);
        $this->assertSame('aih_manage_art', AIH_Roles::CAP_MANAGE_ART);
        $this->assertSame('aih_view_bids', AIH_Roles::CAP_VIEW_BIDS);
        $this->assertSame('aih_view_financial', AIH_Roles::CAP_VIEW_FINANCIAL);
        $this->assertSame('aih_manage_bidders', AIH_Roles::CAP_MANAGE_BIDDERS);
        $this->assertSame('aih_manage_settings', AIH_Roles::CAP_MANAGE_SETTINGS);
        $this->assertSame('aih_view_reports', AIH_Roles::CAP_VIEW_REPORTS);
        $this->assertSame('aih_manage_pickup', AIH_Roles::CAP_MANAGE_PICKUP);
    }

    public function testNoPickupManagerConstant(): void
    {
        $ref = new \ReflectionClass(AIH_Roles::class);
        $constants = $ref->getConstants();
        $this->assertArrayNotHasKey('ROLE_PICKUP_MANAGER', $constants);
    }

    // ── Legacy roles list ──

    public function testLegacyRolesIncludesPickupManager(): void
    {
        $ref = new \ReflectionClass(AIH_Roles::class);
        $prop = $ref->getProperty('legacy_roles');
        /** @var array<int, string> $legacy */
        $legacy = $prop->getValue();

        $this->assertContains('aih_pickup_manager', $legacy);
    }

    public function testLegacyCleanupKeyIsVersioned(): void
    {
        $ref = new \ReflectionClass(AIH_Roles::class);
        $prop = $ref->getProperty('legacy_cleanup_key');
        /** @var string $key */
        $key = $prop->getValue();

        $this->assertSame('aih_legacy_roles_cleaned_v2', $key);
    }

    // ── get_roles() ──

    public function testGetRolesReturnsThreeRoles(): void
    {
        Functions\stubs(['__' => function ($text) { return $text; }]);

        $roles = AIH_Roles::get_roles();

        $this->assertCount(3, $roles);
        $this->assertArrayHasKey(AIH_Roles::ROLE_SUPER_ADMIN, $roles);
        $this->assertArrayHasKey(AIH_Roles::ROLE_ART_MANAGER, $roles);
        $this->assertArrayHasKey(AIH_Roles::ROLE_OPERATIONS, $roles);
    }

    public function testGetRolesUsesAihPrefix(): void
    {
        Functions\stubs(['__' => function ($text) { return $text; }]);

        $roles = AIH_Roles::get_roles();

        foreach ($roles as $role) {
            $this->assertStringStartsWith('AIH - ', $role['name']);
        }
    }

    public function testGetRolesOperationsDescription(): void
    {
        Functions\stubs(['__' => function ($text) { return $text; }]);

        $roles = AIH_Roles::get_roles();
        $ops = $roles[AIH_Roles::ROLE_OPERATIONS];

        $this->assertSame('AIH - Operations', $ops['name']);
        $this->assertStringContainsString('registrants', $ops['description']);
        $this->assertStringContainsString('pickup', $ops['description']);
    }

    public function testGetRolesArtManagerDescription(): void
    {
        Functions\stubs(['__' => function ($text) { return $text; }]);

        $roles = AIH_Roles::get_roles();
        $art = $roles[AIH_Roles::ROLE_ART_MANAGER];

        $this->assertStringContainsString('reports', $art['description']);
        $this->assertStringContainsString('pickup', $art['description']);
        $this->assertStringNotContainsString('financial', strtolower($art['description']));
    }

    // ── get_role_capabilities() ──

    public function testSuperAdminHasAllCaps(): void
    {
        $caps = AIH_Roles::get_role_capabilities(AIH_Roles::ROLE_SUPER_ADMIN);

        $this->assertContains(AIH_Roles::CAP_MANAGE_AUCTION, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_ART, $caps);
        $this->assertContains(AIH_Roles::CAP_VIEW_BIDS, $caps);
        $this->assertContains(AIH_Roles::CAP_VIEW_FINANCIAL, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_BIDDERS, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_SETTINGS, $caps);
        $this->assertContains(AIH_Roles::CAP_VIEW_REPORTS, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_PICKUP, $caps);
        $this->assertCount(8, $caps);
    }

    public function testArtManagerCaps(): void
    {
        $caps = AIH_Roles::get_role_capabilities(AIH_Roles::ROLE_ART_MANAGER);

        $this->assertContains(AIH_Roles::CAP_MANAGE_ART, $caps);
        $this->assertContains(AIH_Roles::CAP_VIEW_REPORTS, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_PICKUP, $caps);
        $this->assertCount(3, $caps);

        // Must NOT have financial/bid/settings/auction caps
        $this->assertNotContains(AIH_Roles::CAP_MANAGE_AUCTION, $caps);
        $this->assertNotContains(AIH_Roles::CAP_VIEW_BIDS, $caps);
        $this->assertNotContains(AIH_Roles::CAP_VIEW_FINANCIAL, $caps);
        $this->assertNotContains(AIH_Roles::CAP_MANAGE_SETTINGS, $caps);
    }

    public function testOperationsCaps(): void
    {
        $caps = AIH_Roles::get_role_capabilities(AIH_Roles::ROLE_OPERATIONS);

        $this->assertContains(AIH_Roles::CAP_MANAGE_BIDDERS, $caps);
        $this->assertContains(AIH_Roles::CAP_MANAGE_PICKUP, $caps);
        $this->assertCount(2, $caps);

        // Must NOT have art/financial/settings caps
        $this->assertNotContains(AIH_Roles::CAP_MANAGE_ART, $caps);
        $this->assertNotContains(AIH_Roles::CAP_MANAGE_AUCTION, $caps);
        $this->assertNotContains(AIH_Roles::CAP_VIEW_FINANCIAL, $caps);
        $this->assertNotContains(AIH_Roles::CAP_VIEW_REPORTS, $caps);
    }

    public function testUnknownRoleReturnsEmptyCaps(): void
    {
        $caps = AIH_Roles::get_role_capabilities('nonexistent_role');
        $this->assertSame([], $caps);
    }

    // ── Capability check methods ──

    #[DataProvider('capabilityMethodProvider')]
    public function testCapabilityMethodReturnsTrueWhenUserHasCap(string $method, string $cap): void
    {
        Functions\expect('current_user_can')
            ->andReturnUsing(function (string $c) use ($cap): bool {
                return $c === $cap;
            });

        $this->assertTrue(AIH_Roles::$method());
    }

    #[DataProvider('capabilityMethodProvider')]
    public function testCapabilityMethodFallsBackToManageAuction(string $method, string $_cap): void
    {
        Functions\expect('current_user_can')
            ->andReturnUsing(function (string $c): bool {
                // Only manage_auction returns true (fallback)
                return $c === AIH_Roles::CAP_MANAGE_AUCTION;
            });

        $this->assertTrue(AIH_Roles::$method());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function capabilityMethodProvider(): array
    {
        if (!class_exists('AIH_Roles')) {
            require_once __DIR__ . '/../../includes/class-aih-roles.php';
        }

        return array(
            'can_manage_art'     => array('can_manage_art', AIH_Roles::CAP_MANAGE_ART),
            'can_view_bids'      => array('can_view_bids', AIH_Roles::CAP_VIEW_BIDS),
            'can_view_financial' => array('can_view_financial', AIH_Roles::CAP_VIEW_FINANCIAL),
            'can_manage_bidders' => array('can_manage_bidders', AIH_Roles::CAP_MANAGE_BIDDERS),
            'can_manage_settings'=> array('can_manage_settings', AIH_Roles::CAP_MANAGE_SETTINGS),
            'can_view_reports'   => array('can_view_reports', AIH_Roles::CAP_VIEW_REPORTS),
            'can_manage_pickup'  => array('can_manage_pickup', AIH_Roles::CAP_MANAGE_PICKUP),
        );
    }

    public function testCanManageAuctionChecksManageOptions(): void
    {
        Functions\expect('current_user_can')
            ->andReturnUsing(function (string $cap): bool {
                return $cap === 'manage_options';
            });

        $this->assertTrue(AIH_Roles::can_manage_auction());
    }

    public function testCanManageAuctionReturnsFalseWithNoCaps(): void
    {
        Functions\stubs(['current_user_can' => false]);

        $this->assertFalse(AIH_Roles::can_manage_auction());
    }

    // ── can_access_menu() ──

    public function testCanAccessMenuIncludesBidders(): void
    {
        // User only has manage_bidders — should still access menu
        Functions\expect('current_user_can')
            ->andReturnUsing(function (string $cap): bool {
                return $cap === AIH_Roles::CAP_MANAGE_BIDDERS;
            });

        $this->assertTrue(AIH_Roles::can_access_menu());
    }

    public function testCanAccessMenuReturnsFalseWithNoCaps(): void
    {
        Functions\stubs(['current_user_can' => false]);

        $this->assertFalse(AIH_Roles::can_access_menu());
    }

    // ── get_menu_capability() ──

    public function testGetMenuCapabilityReturnsManageArt(): void
    {
        $this->assertSame(AIH_Roles::CAP_MANAGE_ART, AIH_Roles::get_menu_capability());
    }

    // ── install() ──

    public function testInstallCreatesAllThreeRoles(): void
    {
        /** @var array<string, array{name: string, caps: array<string, bool>}> $rolesCreated */
        $rolesCreated = [];

        Functions\stubs(['__' => function ($text) { return $text; }]);

        $adminRole = new class {
            /** @var array<string, bool> */
            public array $caps = [];
            public function add_cap(string $cap): void { $this->caps[$cap] = true; }
            public function has_cap(string $cap): bool { return $this->caps[$cap] ?? false; }
        };

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn($adminRole);

        Functions\expect('remove_role')->andReturn(null);

        Functions\expect('add_role')
            ->andReturnUsing(function (string $slug, string $name, array $caps) use (&$rolesCreated): void {
                $rolesCreated[$slug] = ['name' => $name, 'caps' => $caps];
            });

        Functions\expect('update_option')
            ->with('aih_legacy_roles_cleaned_v2', true)
            ->andReturn(true);

        AIH_Roles::install();

        // All three roles created
        $this->assertArrayHasKey('aih_super_admin', $rolesCreated);
        $this->assertArrayHasKey('aih_art_manager', $rolesCreated);
        $this->assertArrayHasKey('aih_operations', $rolesCreated);

        // Role names use AIH prefix
        $this->assertSame('AIH - Super Admin', $rolesCreated['aih_super_admin']['name']);
        $this->assertSame('AIH - Art Manager', $rolesCreated['aih_art_manager']['name']);
        $this->assertSame('AIH - Operations', $rolesCreated['aih_operations']['name']);

        // Operations has bidders + pickup
        $opsCaps = $rolesCreated['aih_operations']['caps'];
        $this->assertTrue($opsCaps[AIH_Roles::CAP_MANAGE_BIDDERS]);
        $this->assertTrue($opsCaps[AIH_Roles::CAP_MANAGE_PICKUP]);
        $this->assertArrayNotHasKey(AIH_Roles::CAP_MANAGE_ART, $opsCaps);

        // Art Manager has art + reports + pickup
        $artCaps = $rolesCreated['aih_art_manager']['caps'];
        $this->assertTrue($artCaps[AIH_Roles::CAP_MANAGE_ART]);
        $this->assertTrue($artCaps[AIH_Roles::CAP_VIEW_REPORTS]);
        $this->assertTrue($artCaps[AIH_Roles::CAP_MANAGE_PICKUP]);
        $this->assertArrayNotHasKey(AIH_Roles::CAP_VIEW_FINANCIAL, $artCaps);

        // Admin gets all 8 caps
        $this->assertCount(8, $adminRole->caps);
    }

    public function testInstallRemovesLegacyRoles(): void
    {
        /** @var array<int, string> $rolesRemoved */
        $rolesRemoved = [];

        Functions\stubs([
            '__' => function ($text) { return $text; },
            'add_role' => null,
        ]);

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn(null);

        Functions\expect('remove_role')
            ->andReturnUsing(function (string $role) use (&$rolesRemoved): void {
                $rolesRemoved[] = $role;
            });

        Functions\expect('update_option')->andReturn(true);

        AIH_Roles::install();

        $this->assertContains('aih_pickup_manager', $rolesRemoved);
        $this->assertContains('sa_super_admin', $rolesRemoved);
        $this->assertContains('aih_admin', $rolesRemoved);
    }

    public function testInstallUpdatesVersionedCleanupOption(): void
    {
        Functions\stubs([
            '__' => function ($text) { return $text; },
            'remove_role' => null,
            'add_role' => null,
        ]);

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn(null);

        $optionUpdated = false;
        Functions\expect('update_option')
            ->with('aih_legacy_roles_cleaned_v2', true)
            ->andReturnUsing(function () use (&$optionUpdated): bool {
                $optionUpdated = true;
                return true;
            });

        AIH_Roles::install();

        $this->assertTrue($optionUpdated);
    }

    // ── uninstall() ──

    public function testUninstallRemovesCurrentRoles(): void
    {
        /** @var array<int, string> $rolesRemoved */
        $rolesRemoved = [];

        $adminRole = new class {
            public function remove_cap(string $cap): void {}
        };

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn($adminRole);

        Functions\expect('remove_role')
            ->andReturnUsing(function (string $role) use (&$rolesRemoved): void {
                $rolesRemoved[] = $role;
            });

        Functions\stubs(['delete_option' => true]);

        AIH_Roles::uninstall();

        $this->assertContains('aih_operations', $rolesRemoved);
        $this->assertContains('aih_super_admin', $rolesRemoved);
        $this->assertContains('aih_art_manager', $rolesRemoved);
        // Legacy roles are also removed via the legacy loop
        $this->assertContains('aih_pickup_manager', $rolesRemoved);
    }

    public function testUninstallDeletesBothCleanupOptions(): void
    {
        /** @var array<int, string> $deletedOptions */
        $deletedOptions = [];

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn(null);

        Functions\stubs(['remove_role' => null]);

        Functions\expect('delete_option')
            ->andReturnUsing(function (string $key) use (&$deletedOptions): bool {
                $deletedOptions[] = $key;
                return true;
            });

        AIH_Roles::uninstall();

        $this->assertContains('aih_legacy_roles_cleaned_v2', $deletedOptions);
        $this->assertContains('aih_legacy_roles_cleaned', $deletedOptions);
    }

    // ── ensure_admin_caps() ──

    public function testEnsureAdminCapsChecksAllEightCaps(): void
    {
        $adminRole = new class {
            /** @var array<int, string> */
            public array $checked = [];
            public function has_cap(string $cap): bool
            {
                $this->checked[] = $cap;
                return true;
            }
            public function add_cap(string $cap): void {}
        };

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn($adminRole);

        Functions\stubs(['add_action' => null]);

        $instance = AIH_Roles::get_instance();
        $instance->ensure_admin_caps();

        $this->assertCount(8, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_MANAGE_AUCTION, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_MANAGE_ART, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_VIEW_BIDS, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_VIEW_FINANCIAL, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_MANAGE_BIDDERS, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_MANAGE_SETTINGS, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_VIEW_REPORTS, $adminRole->checked);
        $this->assertContains(AIH_Roles::CAP_MANAGE_PICKUP, $adminRole->checked);
    }

    public function testEnsureAdminCapsTriggersInstallOnMissingCap(): void
    {
        $addRoleCalled = false;

        $adminRole = new class {
            public function has_cap(string $cap): bool
            {
                // Missing the pickup cap
                return $cap !== AIH_Roles::CAP_MANAGE_PICKUP;
            }
            public function add_cap(string $cap): void {}
        };

        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn($adminRole);

        Functions\stubs([
            '__' => function ($text) { return $text; },
            'remove_role' => null,
            'update_option' => true,
            'add_action' => null,
        ]);

        Functions\expect('add_role')
            ->andReturnUsing(function () use (&$addRoleCalled): void {
                $addRoleCalled = true;
            });

        $instance = AIH_Roles::get_instance();
        $instance->ensure_admin_caps();

        $this->assertTrue($addRoleCalled, 'install() should have been called, triggering add_role');
    }

    public function testEnsureAdminCapsSkipsWhenNoAdminRole(): void
    {
        Functions\expect('get_role')
            ->with('administrator')
            ->andReturn(null);

        // add_role should NOT be called (no install triggered)
        Functions\expect('add_role')->never();

        Functions\stubs(['add_action' => null]);

        $instance = AIH_Roles::get_instance();
        $instance->ensure_admin_caps();

        $this->addToAssertionCount(1);
    }

    // ── cleanup_legacy_roles() ──

    public function testCleanupSkipsWhenAlreadyRun(): void
    {
        Functions\expect('get_option')
            ->with('aih_legacy_roles_cleaned_v2', false)
            ->andReturn(true);

        // remove_role should NOT be called
        Functions\expect('remove_role')->never();

        Functions\stubs(['add_action' => null]);

        $instance = AIH_Roles::get_instance();
        $instance->cleanup_legacy_roles();

        $this->addToAssertionCount(1);
    }

    public function testCleanupRunsWhenNotYetDone(): void
    {
        /** @var array<int, string> $rolesRemoved */
        $rolesRemoved = [];

        Functions\expect('get_option')
            ->with('aih_legacy_roles_cleaned_v2', false)
            ->andReturn(false);

        Functions\expect('get_users')
            ->andReturn([]);

        Functions\expect('remove_role')
            ->andReturnUsing(function (string $role) use (&$rolesRemoved): void {
                $rolesRemoved[] = $role;
            });

        Functions\expect('update_option')
            ->with('aih_legacy_roles_cleaned_v2', true)
            ->andReturn(true);

        Functions\stubs(['add_action' => null]);

        $instance = AIH_Roles::get_instance();
        $instance->cleanup_legacy_roles();

        $this->assertContains('aih_pickup_manager', $rolesRemoved);
    }

    // ── migrate_legacy_role_users() ──

    public function testMigratePickupManagerUsersToOperations(): void
    {
        Functions\expect('get_option')
            ->with('aih_legacy_roles_cleaned_v2', false)
            ->andReturn(false);

        $mockUser = new class {
            /** @var array<int, string> */
            public array $removed = [];
            /** @var array<int, string> */
            public array $added = [];

            public function remove_role(string $role): void
            {
                $this->removed[] = $role;
            }

            public function add_role(string $role): void
            {
                $this->added[] = $role;
            }
        };

        Functions\expect('get_users')
            ->with(['role' => 'aih_pickup_manager'])
            ->andReturn([$mockUser]);

        Functions\stubs([
            'remove_role' => null,
            'update_option' => true,
            'add_action' => null,
        ]);

        $instance = AIH_Roles::get_instance();
        $instance->cleanup_legacy_roles();

        $this->assertContains('aih_pickup_manager', $mockUser->removed);
        $this->assertContains('aih_operations', $mockUser->added);
    }

    // ── Singleton ──

    public function testGetInstanceReturnsSameInstance(): void
    {
        Functions\stubs(['add_action' => null]);

        $a = AIH_Roles::get_instance();
        $b = AIH_Roles::get_instance();

        $this->assertSame($a, $b);
    }
}
