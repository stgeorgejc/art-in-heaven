# Art in Heaven WordPress Plugin

A comprehensive WordPress plugin for managing silent art auctions. The plugin enables churches and organizations to run fully-featured online art auctions where participants can browse artwork, place bids in real-time, track favorites, and complete purchases through integrated payment processing.

## Key Capabilities

- **Silent Auction Management**: Create and manage art piece listings with images, descriptions, starting prices, and scheduled auction times
- **Real-time Bidding**: Secure bidding system with validation, minimum increment enforcement, and live countdown timers
- **User Authentication**: Confirmation code-based login integrated with Church Community Builder (CCB) API for registrant management
- **Automatic Watermarking**: Protect artwork images with customizable watermarks
- **Favorites System**: Allow bidders to track items they're interested in
- **Payment Integration**: Seamless checkout via Pushpay for won items
- **Email Notifications**: Automated alerts for outbids, wins, and auction reminders
- **Admin Dashboard**: Comprehensive management interface for art pieces, bids, bidders, orders, and reports
- **GDPR Compliance**: Built-in data export and erasure capabilities

## Version 0.9.157

## What's New in 0.9.x

### Security Enhancements
- **SA_Security Class**: Centralized input sanitization, validation, and escaping
- **Rate Limiting**: Prevents brute-force attacks on login and bidding
- **Audit Logging**: Tracks important events for security monitoring
- **Prepared Statements**: All database queries use proper escaping

### Performance Improvements
- **SA_Cache Class**: Transient and object cache support
- **Database Indexes**: Additional indexes for faster queries
- **Conditional Asset Loading**: CSS/JS only loaded where needed
- **Throttled Operations**: Expensive operations are rate-limited

### New Features
- **REST API**: Modern REST endpoints for all operations
- **SA_Notifications Class**: Email notifications for outbids, wins, and reminders
- **SA_Export Class**: CSV exports and GDPR data export/erasure
- **Two-Table Registrant System**: Separate tables for registrants vs active bidders

### GDPR Compliance
- Privacy policy integration
- Personal data export
- Personal data erasure (anonymization)

### Developer Experience
- PHPDoc comments on all classes and methods
- Uninstall.php for clean removal
- Better error handling and logging

## Features

### Core Features
- **Art Piece Management**: Add, edit, and organize art pieces with images, descriptions, and pricing
- **Automatic Watermarking**: Images are automatically watermarked to prevent unauthorized downloads
- **Real-time Countdown**: Live countdown timers for auction end times
- **Bidding System**: Secure bidding with validation and conflict prevention
- **Favorites**: Users can favorite items for easy tracking

### Security Features
- Input sanitization on all user data
- Nonce verification on all forms
- Capability checks for admin actions
- Rate limiting on authentication and bids
- IP logging for audit trails

### CCB API Integration
- **Clean API Client**: Dedicated `SA_CCB_API` class for easy data pulling
- **Flexible Field Mapping**: Customize which CCB fields map to your database
- **One-Click Sync**: Import all registrants with a single button
- **Incremental Updates**: Sync only adds/updates, never deletes existing data

### People Management
- **Registrants Table**: All people from CCB API
- **Bidders Table**: Only people who have logged in
- **Status Tracking**: See who registered, logged in, and placed bids
- **Admin Tabs**: Filter by status (All, Logged In, Not Logged In, No Bids)

### Notifications
- Outbid email alerts
- Auction winning notifications
- Ending soon reminders
- Order confirmations

### Payment
- **Pushpay Integration**: Seamless checkout with Pushpay payment links
- **Order Management**: Create and track orders for won items
- **Tax Calculation**: Configurable tax rate

### REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/art-in-heaven/v1/art` | GET | List art pieces |
| `/art-in-heaven/v1/art/{id}` | GET | Get single art piece |
| `/art-in-heaven/v1/bids` | POST | Place a bid |
| `/art-in-heaven/v1/favorites` | GET/POST | List/toggle favorites |
| `/art-in-heaven/v1/auth/verify` | POST | Verify confirmation code |
| `/art-in-heaven/v1/auth/status` | GET | Check login status |
| `/art-in-heaven/v1/checkout/won-items` | GET | Get won items |
| `/art-in-heaven/v1/stats` | GET | Get auction stats (admin) |

---

## CCB API Client

The plugin includes a clean, flexible API client for Church Community Builder.

### Basic Usage

```php
// Get the API instance
$api = SA_CCB_API::get_instance();

// Test connection
$result = $api->test_connection();
// Returns: ['success' => true, 'message' => 'Connected! Found 42 registrants.', 'count' => 42]

// Get all registrants
$result = $api->get_form_responses();
// Returns: ['success' => true, 'data' => [...], 'count' => 42]

// Access the data
foreach ($result['data'] as $registrant) {
    echo $registrant['name_first'] . ' ' . $registrant['name_last'];
    echo $registrant['email_primary'];
    echo $registrant['confirmation_code'];
}
```

### Custom Field Mapping

By default, the API maps these CCB fields:

| CCB Field | Local Field |
|-----------|-------------|
| `email_primary` | `email_primary` |
| `name_first` | `name_first` |
| `name_last` | `name_last` |
| `phone_mobile` | `phone_mobile` |
| `phone_home` | `phone_home` |
| `phone_work` | `phone_work` |
| `birthday` | `birthday` |
| `gender` | `gender` |
| `marital_status` | `marital_status` |
| `mailing_street` | `mailing_street` |
| `mailing_city` | `mailing_city` |
| `mailing_state` | `mailing_state` |
| `mailing_zip` | `mailing_zip` |

You can add custom mappings:

```php
$api = SA_CCB_API::get_instance();

// Add a single mapping
$api->add_field_mapping('custom_ccb_field', 'my_local_column');

// Add multiple mappings
$api->set_field_mappings(array(
    'emergency_contact' => 'emergency_contact',
    'dietary_restrictions' => 'dietary_needs',
));

// Get current mappings
$mappings = $api->get_field_mappings();
```

### Direct Fields (Always Extracted)

These fields are extracted directly from the XML (not from `profile_info`):

- `confirmation_code` - The registrant's confirmation code
- `individual_id` - CCB individual ID
- `individual_name` - Full name from individual element
- `api_data` - Raw XML for debugging

### Making Custom API Requests

```php
$api = SA_CCB_API::get_instance();

// Make any CCB API request
$result = $api->request('form_responses', array(
    'form_id' => '123',
    'modified_since' => '2025-01-01',
));

if ($result['success']) {
    $xml = $result['data']; // Raw XML response
}
```

### Check Configuration Status

```php
$api = SA_CCB_API::get_instance();

$status = $api->get_config_status();
// Returns:
// [
//     'base_url' => true,
//     'form_id' => true,
//     'username' => true,
//     'password' => true,
//     'configured' => true,
// ]

if (!$api->is_configured()) {
    echo 'Please configure the API in Settings.';
}
```

---

## Installation

1. Upload the `art-in-heaven-wp` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. Go to **Art in Heaven → Settings** to configure:
   - CCB API credentials
   - Pushpay settings
   - Event date
   - Tax rate
4. Click **"Sync Bidders from API"** to import registrants

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[art_in_heaven_gallery]` | Main gallery displaying all active art pieces |
| `[art_in_heaven_login]` | Login page with confirmation code entry |
| `[art_in_heaven_my_bids]` | User's bid history (requires login) |
| `[art_in_heaven_checkout]` | Checkout for won items (requires login) |
| `[art_in_heaven_item id="123"]` | Display a single art piece |

## Configuration

### API Settings

| Setting | Description |
|---------|-------------|
| API Base URL | Your CCB API endpoint (e.g., `https://yourchurch.ccbchurch.com/api.php`) |
| Form ID | ID of the registration form in CCB |
| API Username | CCB API username |
| API Password | CCB API password |

### Pushpay Settings

| Setting | Description |
|---------|-------------|
| Merchant Key | Your Pushpay merchant identifier |
| Base URL | Pushpay payment URL base |
| Fund | Fund/category for payments |

### General Settings

| Setting | Description |
|---------|-------------|
| Event Date | Default start time for new art pieces |
| Auction Year | Year prefix for database tables |
| Currency Symbol | Default `$` |
| Min Bid Increment | Minimum amount above current bid |
| Tax Rate | Percentage for checkout calculations |
| Watermark Text | Text overlaid on images |
| Login Page URL | Page for user authentication |

## Database Tables

For each year (e.g., 2025):

| Table | Description |
|-------|-------------|
| `wp_2025_ArtPieces` | Art piece records |
| `wp_2025_Bids` | All bids placed |
| `wp_2025_Bidders` | Synced registrants |
| `wp_2025_Favorites` | User favorites |
| `wp_2025_Orders` | Checkout orders |
| `wp_2025_OrderItems` | Order line items |

### Bidders Table Schema

| Column | Type | Description |
|--------|------|-------------|
| `confirmation_code` | varchar(100) | Primary login identifier |
| `email_primary` | varchar(255) | Email address (bidder ID) |
| `name_first` | varchar(100) | First name |
| `name_last` | varchar(100) | Last name |
| `phone_mobile` | varchar(50) | Mobile phone |
| `phone_home` | varchar(50) | Home phone |
| `phone_work` | varchar(50) | Work phone |
| `birthday` | varchar(20) | Birth date |
| `gender` | varchar(10) | Gender |
| `marital_status` | varchar(10) | Marital status |
| `mailing_street` | varchar(255) | Street address |
| `mailing_city` | varchar(100) | City |
| `mailing_state` | varchar(50) | State |
| `mailing_zip` | varchar(20) | ZIP code |
| `individual_id` | varchar(50) | CCB individual ID |
| `individual_name` | varchar(255) | CCB individual name |
| `api_data` | text | Raw XML for debugging |
| `last_login` | datetime | Last login timestamp |

## File Structure

```
art-in-heaven-wp/
├── art-in-heaven.php          # Main plugin file
├── README.md                   # This documentation
├── admin/
│   ├── class-sa-admin.php      # Admin panel handler
│   └── views/                  # Admin page templates
├── includes/
│   ├── class-sa-database.php   # Database handler
│   ├── class-sa-ccb-api.php    # CCB API client (NEW)
│   ├── class-sa-auth.php       # Authentication & bidders
│   ├── class-sa-ajax.php       # AJAX handlers
│   ├── class-sa-art-piece.php  # Art piece model
│   ├── class-sa-bid.php        # Bid model
│   ├── class-sa-favorites.php  # Favorites model
│   ├── class-sa-checkout.php   # Checkout & orders
│   ├── class-sa-shortcodes.php # Shortcode handlers
│   └── class-sa-watermark.php  # Image watermarking
├── templates/                  # Frontend templates
└── assets/                     # CSS & JS files
```

## Requirements

- WordPress 5.0+
- PHP 7.4+
- GD Library (for watermarking)
- MySQL 5.7+

## Changelog

### 2.7.0
- **NEW**: SA_Security class for centralized input/output handling
- **NEW**: SA_Cache class for transient and object caching
- **NEW**: SA_Notifications class for email alerts
- **NEW**: SA_Export class for CSV/GDPR exports
- **NEW**: SA_REST_API class with modern REST endpoints
- **NEW**: Audit log table for security tracking
- **NEW**: uninstall.php for clean plugin removal
- **NEW**: Rate limiting on authentication and bids
- **IMPROVED**: Database indexes for better query performance
- **IMPROVED**: All queries use prepared statements
- **IMPROVED**: GDPR compliance with data export/erasure
- **IMPROVED**: PHPDoc comments throughout codebase

### 2.6.0
- **NEW**: Registrants table (all API users)
- **NEW**: Bidders table (only logged-in users)
- **NEW**: People management tabs (All, Logged In, Not Logged In, No Bids)
- **FIXED**: Double notification issue on admin forms

### 2.5.0
- **NEW**: Role-based access control
- **NEW**: Art Manager role
- **NEW**: Capability system

### 2.4.0
- **NEW**: Dedicated CCB API client class (`SA_CCB_API`)
- **NEW**: Flexible field mapping for custom CCB fields
- **NEW**: Cleaner code separation (API vs Auth)
- Simplified authentication class
- Better error handling and responses
- Improved documentation

### 2.3.0
- Code cleanup and bug fixes
- Removed dead code from auth class
- Removed debug logging from production

### 2.2.0
- Added bidder pre-loading from CCB API
- Login uses local database lookup only
- Added sync status display

### 2.1.0
- Added login page shortcode
- Added event date setting
- Added art pieces tabs
- Added reports and migration tools

### 2.0.0
- Year-based database tables
- Confirmation code authentication
- Pushpay payment integration

### 1.0.0
- Initial release

## License

GPL v2 or later
