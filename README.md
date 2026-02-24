# Art in Heaven

A WordPress plugin for running silent art auctions. Built for churches and organizations to let participants browse artwork, place bids, and pay for won items online.

## Version 1.0.0

## How It Works

### For Participants

1. **Register** through Church Community Builder (CCB). Registrant data is synced into the plugin.
2. **Log in** using a confirmation code on the login page.
3. **Browse** the gallery of active art pieces with images, descriptions, and current bids.
4. **Bid** on art pieces. The system enforces minimum bid increments and prevents bidding on ended or upcoming auctions. Outbid notifications are sent via email and web push.
5. **Track** bids and favorites from the My Bids page, which also shows order history.
6. **Pay** for won items through Pushpay at checkout. Orders are created automatically for winning bids once the auction ends.

### For Admins

1. **Set up** the auction year, tax rate, and integrations (CCB API, Pushpay) in Settings.
2. **Add art pieces** with images, starting bids, and scheduled start/end times. Images are automatically watermarked.
3. **Manage** the auction from the admin dashboard: view bids, track bidders, monitor orders, and handle payments.
4. **Sync** registrants from CCB and transactions from Pushpay with one-click buttons.
5. **Match** Pushpay transactions to orders, manage pickup status, and export reports.

### Auction Lifecycle

- Art pieces are created as **draft** and automatically become **active** when their start time passes (computed at query time, no cron dependency).
- Active pieces accept bids until their end time, when they become **ended**.
- The highest bid on each ended piece is marked as the winning bid.
- Winners can check out and pay via Pushpay. Admins track payment and pickup status.

## Installation

1. Download the latest release zip from GitHub Releases.
2. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload `art-in-heaven.zip`.
3. Activate the plugin.
4. Go to **Art in Heaven > Settings** to configure the auction year and create database tables.
5. Set up integrations (CCB API, Pushpay) under **Art in Heaven > Integrations**.

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[art_in_heaven_gallery]` | Art piece gallery with bidding |
| `[art_in_heaven_login]` | Login page with confirmation code entry |
| `[art_in_heaven_my_bids]` | User's bid history and order history |
| `[art_in_heaven_my_wins]` | User's won items / collection |
| `[art_in_heaven_checkout]` | Checkout for won items |
| `[art_in_heaven_item id="123"]` | Single art piece display |

## Admin Pages

| Page | Purpose |
|------|---------|
| Dashboard | Overview stats and quick actions |
| Art Pieces | Add, edit, and manage artwork listings |
| Bids | View all bids across all pieces |
| Bidders | Manage registrants and bidders |
| Orders | Track orders and payment status |
| Payments | Payment management and status updates |
| Winners | View winning bids and pickup status |
| Pickup | Manage item pickup at the event |
| Transactions | Pushpay transaction sync, matching, and filtering |
| Reports | Auction analytics and CSV exports |
| Integrations | CCB API and Pushpay configuration |
| Settings | Auction year, tax rate, watermark, and general config |

## Database

Tables are prefixed by auction year (e.g., `wp_2025_`):

| Table | Purpose |
|-------|---------|
| `Registrants` | All people synced from CCB |
| `Bidders` | People who have logged in |
| `ArtPieces` | Art piece records with images and auction times |
| `Bids` | All bids placed (valid + rejected) |
| `Favorites` | User favorites |
| `Orders` | Checkout orders |
| `OrderItems` | Order line items |
| `PushpayTransactions` | Synced Pushpay payment data |

## File Structure

```
art-in-heaven/
├── art-in-heaven.php                # Main plugin file
├── uninstall.php                    # Clean removal handler
├── admin/
│   ├── class-aih-admin.php          # Admin panel handler
│   └── views/                       # Admin page templates
├── includes/
│   ├── class-aih-database.php       # Database table management
│   ├── class-aih-art-piece.php      # Art piece model
│   ├── class-aih-art-images.php     # Image upload handling
│   ├── class-aih-bid.php            # Bid model
│   ├── class-aih-auth.php           # Authentication
│   ├── class-aih-ccb-api.php        # CCB API client
│   ├── class-aih-pushpay.php        # Pushpay API integration
│   ├── class-aih-checkout.php       # Orders and checkout
│   ├── class-aih-favorites.php      # Favorites model
│   ├── class-aih-shortcodes.php     # Shortcode handlers
│   ├── class-aih-ajax.php           # AJAX request handlers
│   ├── class-aih-rest-api.php       # REST API endpoints
│   ├── class-aih-api-router.php     # Lightweight API routing
│   ├── class-aih-watermark.php      # Image watermarking
│   ├── class-aih-image-optimizer.php # AVIF/WebP responsive images
│   ├── class-aih-push.php           # Web push notifications
│   ├── class-aih-mercure.php        # Real-time updates (Mercure)
│   ├── class-aih-security.php       # Input sanitization and rate limiting
│   ├── class-aih-cache.php          # Transient caching with group versioning
│   ├── class-aih-status.php         # Auction status computation
│   ├── class-aih-cron-scheduler.php # Cron job scheduling
│   ├── class-aih-export.php         # CSV and GDPR exports
│   ├── class-aih-roles.php          # Role and capability management
│   ├── class-aih-template-helper.php # Frontend data formatting
│   └── class-aih-assets.php         # CSS/JS loading
├── templates/
│   ├── gallery.php                  # Art gallery grid
│   ├── single-item.php              # Individual art piece page
│   ├── login.php                    # Confirmation code login
│   ├── my-bids.php                  # User bid history and orders
│   ├── my-wins.php                  # User won items / collection
│   ├── checkout.php                 # Won items checkout
│   ├── winners.php                  # Public winners display
│   └── partials/                    # Reusable template components
├── tests/
│   ├── bootstrap.php                # PHPUnit test bootstrap
│   └── Unit/                        # Unit tests
└── assets/
    ├── css/                         # Stylesheets
    ├── js/                          # Frontend and admin JavaScript
    ├── fonts/                       # Custom fonts
    └── images/                      # Plugin icons and logo
```

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, testing, and contribution guidelines.

```bash
# Quick start
composer install
composer setup-hooks    # install git hooks (one-time)
composer test           # run PHPUnit
composer analyze        # run PHPStan
```

## CI/CD

Every pull request runs three checks in parallel:

| Check | Tool | What it catches |
|-------|------|-----------------|
| **Lint** | `php -l` | Syntax errors |
| **Test** | PHPUnit 12 | Logic regressions |
| **Analyze** | PHPStan level 5 | Type errors, undefined variables |

On merge to `main`, the release workflow automatically drafts a release, builds zip artifacts, and deploys to production.

## Requirements

- WordPress 5.8+
- PHP 8.3+
- MySQL 5.7+
- GD Library (for image watermarking)

## License

GPL v2 or later
