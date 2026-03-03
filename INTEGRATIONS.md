# Art in Heaven — Integrations Setup Guide

How to obtain and configure every credential on the **Art in Heaven > Integrations** admin page.

---

## 1. CCB (Church Community Builder)

Syncs registered bidders from a CCB form into the plugin's local database.

### What You Need

| Field | Where to Get It |
|-------|----------------|
| **API Base URL** | Your church's CCB URL + `/api.php`. Example: `https://yourchurch.ccbchurch.com/api.php` |
| **Form ID** | The numeric ID of your registration form in CCB |
| **API Username** | Created in CCB admin under API settings |
| **API Password** | Created alongside the API username |

### Step-by-Step

1. **Log into CCB** at `https://yourchurch.ccbchurch.com`
2. Go to **Settings > API** (requires admin access)
3. **Enable API access** if not already enabled
4. **Create an API user** — note the username and password
5. **Find your Form ID**:
   - Go to **Forms** in CCB
   - Open your auction registration form
   - The Form ID is in the URL: `form_detail.php?id=YOUR_FORM_ID`
6. Enter all four values on the Integrations page
7. Click **Test Connection** to verify
8. Click **Sync Bidders** to import registrants

### Auto-Sync

- Toggle **Auto Sync** on to keep bidders updated automatically
- **Interval options**: Hourly (recommended) or Every 30 Seconds (high API load)

### What Gets Synced

CCB fields mapped to the plugin:
- Name (first, last), Email, Phone (mobile/home/work)
- Birthday, Gender, Marital Status
- Mailing address (street, city, state, zip)
- Confirmation code (used for bidder login)
- CCB Individual ID

---

## 2. Pushpay (Payment Processing)

Syncs payment status from Pushpay so the plugin can mark orders as paid.

### What You Need

| Field | Where to Get It |
|-------|----------------|
| **Client ID** | Pushpay developer portal or your Pushpay account rep |
| **Client Secret** | Same source as Client ID |
| **Organization Key** | Auto-discovered via the **Discover Keys** button |
| **Merchant Key** | Auto-discovered via the **Discover Keys** button |
| **Merchant Handle** | The handle from your giving page URL (e.g., `pushpay.com/g/your-handle`) |
| **Fund/Category** | The fund name in Pushpay that auction payments should be tagged with |
| **Redirect Key** | Configured in Pushpay merchant portal |

### Step-by-Step

1. **Get API Credentials**
   - Contact your Pushpay account representative, or
   - Log into the [Pushpay Developer Portal](https://developer.pushpay.com) and create an application
   - You'll receive a **Client ID** and **Client Secret**

2. **Choose Environment**
   - Toggle **Sandbox Mode** on for testing (uses sandbox API endpoints)
   - Toggle off for production (live payments)

3. **Enter Client ID and Client Secret** in the appropriate fields

4. **Discover Keys** — Click the **Discover Keys** button
   - The plugin will call the Pushpay API to find your Organization and Merchant keys
   - If you have multiple organizations, select the correct one
   - Keys will auto-populate

5. **Enter Merchant Handle**
   - Go to your Pushpay giving page
   - The handle is the last segment of the URL: `pushpay.com/g/YOUR-HANDLE`

6. **Set Fund Name**
   - Enter the exact fund/category name in Pushpay where auction payments are recorded
   - This must match exactly (case-insensitive) — e.g., "Silent Auction"

7. **Configure Redirect Key**
   - In Pushpay merchant portal: **Settings > API Settings > Preconfigured Redirects**
   - Create a redirect pointing back to your checkout page
   - Copy the redirect key into the plugin settings

8. Click **Test Connection** to verify
9. Click **Sync Transactions** to pull in payment data

### Sandbox vs Production

| | Sandbox | Production |
|---|---------|------------|
| Auth URL | `auth.pushpay.com/pushpay-sandbox/oauth/token` | `auth.pushpay.com/pushpay/oauth/token` |
| API URL | `sandbox-api.pushpay.io/v1` | `api.pushpay.com/v1` |

Each environment has its own set of credentials. Switch the toggle to configure each one independently.

### Auto-Sync

- Toggle **Auto Sync** on to check for new payments automatically
- **Interval options**: Hourly (recommended) or Every 30 Seconds
- The plugin matches payments to orders by looking for the order number pattern (`AIH-XXXXXXXX`) in transaction notes

---

## 3. Mercure (Real-Time Updates via SSE)

Enables instant bid updates and outbid notifications without page refresh. If not configured, the plugin falls back to polling (still works, just slightly delayed).

### What You Need

| Field | Where to Get It |
|-------|----------------|
| **Internal Hub URL** | The URL where Mercure is running on your server (default: `http://127.0.0.1:3000/.well-known/mercure`) |
| **Public Hub URL** | The URL browsers will connect to (leave blank to auto-detect as `https://yoursite.com/.well-known/mercure`) |
| **JWT Secret** | A shared secret you create — must match Mercure's config |

### Step-by-Step

1. **Install Mercure on your server**
   ```bash
   mkdir -p ~/mercure && cd ~/mercure
   curl -sL -o mercure.tar.gz https://github.com/dunglas/mercure/releases/download/v0.21.8/mercure_Linux_x86_64.tar.gz
   tar -xzf mercure.tar.gz && chmod +x mercure && rm mercure.tar.gz

   # Create the data directory Mercure needs
   mkdir -p ~/.local/share/caddy
   ```

2. **Create a JWT Secret**
   - Generate a strong random string (32+ characters)
   - Example: `openssl rand -base64 32`
   - You'll use this same secret in both Mercure config and WordPress settings

3. **Configure Mercure** — Create a Caddyfile at `~/mercure/Caddyfile`:
   ```caddyfile
   {
       order mercure after encode
       auto_https off
       http_port 3000
       log {
           output file /path/to/mercure/mercure.log {
               roll_size 10mb
               roll_keep 3
           }
       }
   }

   :3000 {
       route {
           mercure {
               publisher_jwt {env.MERCURE_PUBLISHER_JWT_KEY} HS256
               subscriber_jwt {env.MERCURE_SUBSCRIBER_JWT_KEY} HS256
               cors_origins https://yoursite.com
               publish_origins *
               anonymous
           }
           respond "Mercure hub"
       }
   }
   ```
   Replace `https://yoursite.com` with your actual domain.

4. **Run Mercure** — Choose one of the following:

   **Option A: Systemd service (requires root/sudo)**
   ```bash
   sudo systemctl start mercure
   sudo systemctl enable mercure
   ```

   **Option B: User-level scripts (shared hosting / Plesk / no root)**

   Create `~/mercure/start.sh`:
   ```bash
   #!/bin/bash
   export MERCURE_PUBLISHER_JWT_KEY="your-jwt-secret-here"
   export MERCURE_SUBSCRIBER_JWT_KEY="your-jwt-secret-here"

   PIDFILE="$HOME/mercure/mercure.pid"

   if [ -f "$PIDFILE" ] && kill -0 "$(cat "$PIDFILE")" 2>/dev/null; then
       echo "Mercure is already running (PID $(cat "$PIDFILE"))"
       exit 0
   fi

   cd "$HOME/mercure"
   nohup ./mercure run --config Caddyfile >> mercure.log 2>&1 &
   echo $! > "$PIDFILE"
   echo "Mercure started (PID $!)"
   ```

   Create `~/mercure/stop.sh`:
   ```bash
   #!/bin/bash
   PIDFILE="$HOME/mercure/mercure.pid"
   if [ -f "$PIDFILE" ]; then
       PID=$(cat "$PIDFILE")
       if kill -0 "$PID" 2>/dev/null; then
           kill "$PID" && rm "$PIDFILE"
           echo "Mercure stopped (PID $PID)"
       else
           rm "$PIDFILE"
           echo "Mercure was not running (stale PID file removed)"
       fi
   else
       echo "No PID file found"
   fi
   ```

   Make them executable and add a cron entry to auto-start on reboot:
   ```bash
   chmod +x ~/mercure/start.sh ~/mercure/stop.sh
   (crontab -l 2>/dev/null; echo "@reboot $HOME/mercure/start.sh") | crontab -
   ```

   Mercure listens on port 3000 by default.

5. **Configure nginx** to proxy the Mercure endpoint:

   **Standard nginx config:**
   ```nginx
   location /.well-known/mercure {
       proxy_pass http://127.0.0.1:3000/.well-known/mercure;
       proxy_set_header Host $host;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;

       # SSE-specific settings
       proxy_buffering off;
       proxy_cache off;
       proxy_read_timeout 24h;
       proxy_set_header Connection '';
       proxy_http_version 1.1;
       chunked_transfer_encoding off;
   }
   ```

   **Plesk:** Go to Websites & Domains > your domain > Apache & nginx Settings > **Additional nginx directives** and paste the config above.

6. **Enter settings in WordPress**:
   - **Internal Hub URL**: `http://127.0.0.1:3000/.well-known/mercure`
   - **Public Hub URL**: Leave blank (auto-detects) or set to `https://yoursite.com/.well-known/mercure`
   - **JWT Secret**: The same secret from step 2

7. **Enable Mercure** — Toggle on

### How It Works

- When a bid is placed, PHP publishes an event to Mercure
- Browsers connected via SSE receive the update instantly
- If Mercure goes down, the plugin automatically falls back to polling
- Two topic types:
  - **Public** (`/auction/{id}`): Bid updates visible to everyone
  - **Private** (`/bidder/{id}`): Outbid alerts for specific bidders

---

## 4. Web Push Notifications

Browser push notifications that alert bidders when outbid, even if they've closed the tab.

### What You Need

Nothing! VAPID keys are **auto-generated** the first time the plugin loads.

### How It Works

1. The plugin generates VAPID keys automatically on activation
2. When a logged-in bidder visits the site, they see a bell icon in the header
3. Clicking the bell prompts them to allow notifications
4. After a bid, if permission hasn't been granted yet, the plugin prompts again
5. When outbid, the bidder receives a browser push notification

### Requirements

- **HTTPS** — Push notifications require a secure connection (browsers block them on HTTP)
- **PHP `openssl` extension** — Required by the web-push library
- **Composer dependency**: `minishlink/web-push` must be installed

### Checking VAPID Keys

The keys are stored in `wp_options`:
- `aih_vapid_public_key` — Shared with browsers
- `aih_vapid_private_key` — Server-side only
- `aih_vapid_subject` — Defaults to `mailto:` + your admin email

If you need to regenerate keys (e.g., switching domains), delete these three options from the database and reload any admin page.

### Supported Browsers

- Chrome (desktop + Android)
- Firefox (desktop + Android)
- Edge (desktop)
- Safari 16+ (macOS Ventura+ and iOS 16.4+)

### Fallback

If push is unavailable (unsupported browser, permission denied, or service worker fails), the plugin falls back to polling for outbid events. Users still get in-page alerts.

---

## 5. Server Requirements Summary

| Requirement | Needed For | How to Check |
|-------------|-----------|--------------|
| PHP 7.4+ | Plugin core | `php -v` |
| PHP `openssl` extension | Encryption + Web Push | `php -m \| grep openssl` |
| PHP `gd` extension | Image watermarking | `php -m \| grep gd` |
| PHP `freetype` (in GD) | High-quality watermark text | Check GD info in `phpinfo()` |
| Composer dependencies | Web Push library | `composer install` in plugin directory |
| HTTPS | Web Push, Mercure cookies | Check site URL |
| Mercure binary (optional) | Real-time SSE updates | `mercure --version` |
| nginx proxy (optional) | Mercure public access | Check nginx config |
| System cron (recommended) | Reliable auto-sync | `crontab -l` |

### Recommended wp-config.php Settings

```php
// Use system cron instead of WordPress pseudo-cron
define('DISABLE_WP_CRON', true);

// System crontab entry (run every minute):
// * * * * * cd /path/to/wordpress && php wp-cron.php > /dev/null 2>&1
```

---

## 6. Testing Your Setup

### CCB
1. Enter credentials and click **Test Connection**
2. Green success message = API is reachable and authenticated
3. Click **Sync Bidders** and verify bidder count increases

### Pushpay
1. Start in **Sandbox Mode** for testing
2. Enter sandbox credentials and click **Test Connection**
3. Use **Discover Keys** to auto-populate org/merchant keys
4. Click **Sync Transactions** to verify transaction fetch works
5. Switch to production when ready

### Mercure
1. Enter hub URL and JWT secret, enable Mercure
2. Open browser DevTools Console on the gallery page
3. Look for: `[AIH] SSE connected to Mercure hub — polling disabled`
4. Place a test bid — you should see: `[AIH] SSE event received: bid_update {...}`
5. If you see `[AIH] SSE disconnected — falling back to polling`, check Mercure/nginx config

### Web Push
1. Visit the site as a logged-in bidder
2. Click the bell icon in the header
3. Allow notifications when prompted
4. Bell should turn to "enabled" state
5. Get outbid on an item — you should receive a browser notification

### Console Debug Messages

Open browser DevTools (F12) > Console to see which notification channel is active:

| Message | Meaning |
|---------|---------|
| `[AIH] SSE connected to Mercure hub — polling disabled` | Mercure SSE is active |
| `[AIH] SSE event received: {type} {data}` | Real-time event via SSE |
| `[AIH] SSE disconnected — falling back to polling` | SSE lost, now polling |
| `[AIH] Polling for outbid events (fallback)` | Polling is running |
| `[AIH] Outbid via POLLING: {title} (art #{id})` | Outbid alert via polling |

---

## 7. Troubleshooting

| Problem | Solution |
|---------|----------|
| CCB "Connection failed" | Verify API URL ends with `/api.php`, check username/password |
| CCB sync returns 0 bidders | Check the Form ID matches your registration form |
| Pushpay "Invalid credentials" | Ensure you're using the right environment (sandbox vs production) |
| Pushpay discover returns no keys | Your Client ID may not have organization scope — contact Pushpay |
| Mercure not connecting | Check Mercure is running (`curl http://127.0.0.1:3000/.well-known/mercure`), verify JWT secret matches |
| Push notifications not showing | Ensure HTTPS, check browser hasn't blocked notifications, verify `openssl` extension |
| Bell icon not appearing | User must be logged in as a bidder |
| Polling instead of SSE | Mercure may be disabled or unreachable — check settings and server |
| Payments not matching orders | Verify the fund name matches exactly, and order numbers appear in Pushpay transaction notes |
