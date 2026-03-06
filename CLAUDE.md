# Art in Heaven - Development Guide

## Architecture

WordPress plugin for silent/blind art auctions. Registrants authenticate via CCB (Church Community Builder) confirmation codes — no WordPress user accounts.

### Key Directories
- `includes/` — PHP classes (AJAX handlers, models, auth, security, API router)
- `templates/` — Full-page templates (gallery, login, checkout, single-item, my-bids, winners)
- `templates/partials/` — Reusable template fragments (login-gate, header, etc.)
- `assets/js/` — Frontend JavaScript (gallery, bidding, push notifications, PWA)
- `assets/css/` — Stylesheets
- `tests/` — PHPUnit tests

### Important Design Decisions

**Silent/Blind Auction**: Gallery cards intentionally show "Starting Bid" only, never "Current Bid". Bidders must not see what others have bid. This is a core product decision, not a bug.

**Nonce Architecture** (three tiers):
- `aih_public_nonce` — Login page, login gate, logout, auth check (visible to unauthenticated users)
- `aih_frontend_nonce` — All authenticated actions: bidding, favorites, gallery, checkout, push
- `aih_admin_nonce` — Admin panel operations

**Test Code Bypass**: Requires both `AIH_TEST_CODE_PREFIX` constant AND `WP_DEBUG = true` in `wp-config.php`. Example: `define('AIH_TEST_CODE_PREFIX', 'AIHTEST');`. Any code starting with this prefix auto-creates a synthetic registrant. The prefix should be alphabetic to avoid collision with CCB's numeric codes. The WP_DEBUG guard prevents accidental use in production.

**HTTP Security Headers**: Added via `wp_headers` filter at priority 99 with `!isset()` guards. Never overrides headers set by other plugins, themes, or server config.

## Static Analysis

PHPStan is configured at **level 6** with zero errors and zero `ignoreErrors`. All new code must meet this standard.

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse --memory-limit=2G
```

- Every method must have `@param` and `@return` type annotations (PHPDoc or native)
- Do not add entries to `ignoreErrors` in `phpstan.neon` — fix the root cause instead
- `bidder_id` is a **string** (confirmation code), not an int — type accordingly
- WP action callbacks must return `void` (use `wp_doing_ajax()` instead of `DOING_AJAX` constant)

## Testing

```bash
composer test          # Run all PHPUnit tests
composer test -- --filter=TestName  # Run specific test
```

## Coding Conventions
- PHP: WordPress coding standards
- JS: jQuery-based, uses `aihPost()` helper for AJAX with rate limiting
- All AJAX endpoints go through `class-aih-ajax.php`
- Use `AIH_Template_Helper` for page URL lookups and shared formatting
