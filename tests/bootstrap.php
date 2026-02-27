<?php
/**
 * PHPUnit Test Bootstrap
 *
 * Sets up the test environment with Brain Monkey for WordPress function mocking.
 * No WordPress installation required.
 */

// Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// WordPress core constants used by plugin code
define('ABSPATH', '/tmp/wordpress/');
define('WP_DEBUG', true);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);

// WordPress auth constants (used by AIH_Security encryption)
define('LOGGED_IN_KEY', 'test-encryption-key-for-unit-tests-only-not-real');
define('AUTH_KEY', 'test-auth-key-for-unit-tests-only');

// Plugin constants — version parsed from plugin header (single source of truth)
$aih_header = file_get_contents(dirname(__DIR__) . '/art-in-heaven.php');
preg_match('/^\s*\*\s*Version:\s*(.+)$/m', $aih_header, $aih_ver);
define('AIH_VERSION', trim($aih_ver[1] ?? '0.0.0'));
define('AIH_DB_VERSION', '0.9.6');
define('AIH_PLUGIN_DIR', dirname(__DIR__) . '/');
define('AIH_PLUGIN_URL', 'https://example.com/wp-content/plugins/art-in-heaven/');
define('AIH_PLUGIN_BASENAME', 'art-in-heaven/art-in-heaven.php');
define('AIH_CACHE_GROUP', 'art_in_heaven');
define('AIH_CACHE_EXPIRY', HOUR_IN_SECONDS);

// Test code bypass constant
define('AIH_TEST_CODE_PREFIX', 'AIHTEST');

// Load plugin source files under test
require_once __DIR__ . '/../includes/class-aih-security.php';
require_once __DIR__ . '/../includes/class-aih-status.php';
require_once __DIR__ . '/../includes/class-aih-template-helper.php';

// Additional classes for Bid, Checkout, and Favorites testing.
// Note: class-aih-auth.php has a file-scope add_action() call, so it must be
// loaded AFTER Brain Monkey setUp() — see AuthTest::setUp().
require_once __DIR__ . '/../includes/class-aih-database.php';
require_once __DIR__ . '/../includes/class-aih-bid.php';
require_once __DIR__ . '/../includes/class-aih-checkout.php';
require_once __DIR__ . '/../includes/class-aih-favorites.php';
