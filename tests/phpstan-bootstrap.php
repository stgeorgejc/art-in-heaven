<?php
/**
 * PHPStan Bootstrap
 *
 * Defines plugin-specific constants for static analysis.
 * WordPress constants are provided by szepeviktor/phpstan-wordpress stubs.
 */

// Plugin constants — version parsed from the define() in art-in-heaven.php (single source of truth)
preg_match("/define\('AIH_VERSION',\s*'([^']+)'\)/", file_get_contents(__DIR__ . '/../art-in-heaven.php'), $aih_ver);
define('AIH_VERSION', $aih_ver[1] ?? '0.0.0');
define('AIH_DB_VERSION', '0.9.6');
define('AIH_PLUGIN_DIR', __DIR__ . '/../');
define('AIH_PLUGIN_URL', 'https://example.com/wp-content/plugins/art-in-heaven/');
define('AIH_PLUGIN_BASENAME', 'art-in-heaven/art-in-heaven.php');
define('AIH_CACHE_GROUP', 'art_in_heaven');
define('AIH_CACHE_EXPIRY', HOUR_IN_SECONDS);

// Optional wp-config.php constant for test code bypass (may or may not exist at runtime)
define('AIH_TEST_CODE_PREFIX', 'AIHTEST');
