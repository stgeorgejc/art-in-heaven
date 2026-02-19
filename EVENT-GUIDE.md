# Art in Heaven — Event Setup Guide

A step-by-step checklist for everything that needs to happen before and after your silent auction event.

---

## Table of Contents

- [Pre-Event Setup (Days Before)](#pre-event-setup-days-before)
  - [1. WordPress Hosting & Server](#1-wordpress-hosting--server)
  - [2. Create WordPress Pages](#2-create-wordpress-pages)
  - [3. Plugin Settings](#3-plugin-settings)
  - [4. CCB API Integration](#4-ccb-api-integration)
  - [5. PushPay Integration](#5-pushpay-integration)
  - [6. Art Pieces](#6-art-pieces)
  - [7. Watermark Settings](#7-watermark-settings)
  - [8. Admin User Accounts](#8-admin-user-accounts)
  - [9. Pre-Event Testing](#9-pre-event-testing)
- [Day of Event](#day-of-event)
  - [10. Switch to Live Sync Intervals](#10-switch-to-live-sync-intervals)
  - [11. Activate Art Pieces](#11-activate-art-pieces)
  - [12. Monitor During Event](#12-monitor-during-event)
- [Post-Event](#post-event)
  - [13. Immediately After Auctions End](#13-immediately-after-auctions-end)
  - [14. Payment Collection](#14-payment-collection)
  - [15. Pickup Management](#15-pickup-management)
  - [16. Wind Down](#16-wind-down)
  - [17. Export & Archive](#17-export--archive)

---

## Pre-Event Setup (Days Before)

### 1. WordPress Hosting & Server

Your server needs to handle real-time bidding traffic. Set it up properly ahead of time.

**Server-side cron (required for reliable syncing):**

WordPress has a built-in "pseudo-cron" that only runs when someone visits the site. This is unreliable, especially for 30-second sync intervals. Set up a real server cron instead.

1. Open your hosting control panel (cPanel, Plesk, or your host's dashboard)
2. Find the **Cron Jobs** or **Scheduled Tasks** section
3. Add a new cron job that runs **every minute**:
   ```
   * * * * * curl -s https://yoursite.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```
   Replace `yoursite.com` with your actual domain.

4. Disable WordPress pseudo-cron by adding this line to `wp-config.php` (located in your WordPress root directory):
   ```php
   define('DISABLE_WP_CRON', true);
   ```
   This prevents WordPress from running cron on every page load, which slows the site down.

**SSL certificate:** Make sure your site has HTTPS enabled. Payment redirects and API calls require it.

**PHP requirements:** PHP 7.4+ with the GD library enabled (needed for watermarking images). Most modern hosts have this by default.

---

### 2. Create WordPress Pages

Create the following WordPress pages and add the corresponding shortcode to each. The shortcode is all the page content needs — the plugin handles the rest.

| Page Name | Shortcode | Notes |
|-----------|-----------|-------|
| Gallery | `[art_in_heaven_gallery]` | Main browsing page where bidders view and bid on art |
| Login | `[art_in_heaven_login]` | Where bidders enter their confirmation code |
| My Bids | `[art_in_heaven_my_bids]` | Bidder's bid history and status |
| Checkout | `[art_in_heaven_checkout]` | Payment page for winning bidders |
| My Wins | `[art_in_heaven_my_wins]` | Shows what a bidder has won |
| Winners | `[art_in_heaven_winners]` | Public list of winning bids (no login required) |

After creating these pages, note their URLs — you'll need them in Plugin Settings.

---

### 3. Plugin Settings

Go to **Art in Heaven > Settings** in the WordPress admin.

**General Settings:**

| Setting | What to Enter | Example |
|---------|--------------|---------|
| Auction Year | The year for this event | `2026` |
| Event Start Date/Time | When auctions open | `2026-03-15 6:00 PM` |
| Event End Date/Time | When auctions close | `2026-03-15 9:00 PM` |
| Login Page | URL of the Login page you created | `https://yoursite.com/login/` |
| Gallery Page | URL of the Gallery page you created | `https://yoursite.com/gallery/` |
| Show Sold Items | Whether to display sold items in the gallery | Recommended: On |

**Bidding Settings:**

| Setting | What to Enter | Notes |
|---------|--------------|-------|
| Currency Symbol | `$` | Displayed next to all prices |
| Bid Increment | `1` | Minimum dollar amount above the current bid |
| Tax Rate | `0` | Enter a number like `8.5` for 8.5% sales tax on checkout. Enter `0` for no tax. |
| Enable Favorites | On | Lets bidders save favorite art pieces |

**Color Settings (optional):**

Customize the theme colors to match your event branding. The defaults work well, but you can change:
- Primary/Accent color (buttons, links)
- Success color (winning status)
- Error color (outbid alerts, urgent countdowns)
- Text and muted text colors

---

### 4. CCB API Integration

This connects to Church Community Builder to import your event registrants so they can log in and bid.

Go to **Art in Heaven > Integrations** in the admin.

**CCB Church Management System section:**

| Field | What to Enter | Where to Find It |
|-------|--------------|------------------|
| API Base URL | `https://yourchurch.ccbchurch.com/api.php` | Your CCB subdomain |
| Form ID | The numeric ID of your registration form | In CCB, go to the form and check the URL — the number is the form ID |
| API Username | Your CCB API username | CCB Admin > API settings |
| API Password | Your CCB API password | CCB Admin > API settings |

**After entering credentials:**
1. Click **Test Connection** to verify it works
2. Click **Sync Bidders from API** to pull in all current registrants
3. Check the **Auto Sync** checkbox to enable automatic syncing
4. Set **Sync Interval** to **Every Hour** for now (you'll switch to 30 seconds on event day)

**Verify:** Go to **Art in Heaven > Bidders** and confirm your registrants appear in the list.

---

### 5. PushPay Integration

This connects to PushPay for processing payments after bidders win.

**In the PushPay Merchant Portal (do this first):**

1. Log into your PushPay merchant portal
2. Go to **Settings > API Settings**
3. Create an OAuth 2.0 application and note the **Client ID** and **Client Secret**
4. Note your **Organization Key** and **Merchant Key** from the portal
5. Note your **Merchant Handle** — this is the part after `pushpay.com/g/` in your giving link (e.g., if your link is `pushpay.com/g/stgeorgejc`, the handle is `stgeorgejc`)
6. Go to **Settings > API Settings > Preconfigured Redirects**
7. Add a new redirect:
   - **Key:** `auction-return` (or any short name you choose)
   - **URL:** Your checkout page URL (e.g., `https://yoursite.com/checkout/`)
8. Save

**In the plugin (Art in Heaven > Integrations > PushPay section):**

| Field | What to Enter |
|-------|--------------|
| Environment | **Production** for real payments, **Sandbox** for testing |
| Client ID | From PushPay portal |
| Client Secret | From PushPay portal |
| Organization Key | From PushPay portal |
| Merchant Key | From PushPay portal |
| Merchant Handle | The handle from your giving link |
| Fund/Category | The fund name in PushPay where auction payments should go (e.g., `Art in Heaven`) |
| Redirect Key | The key you created in step 7 above (e.g., `auction-return`) |

**After entering credentials:**
1. Click **Test Connection** to verify the API works
2. Click **Sync Transactions** to test the sync (will be empty if no payments yet)
3. Check the **Auto Sync** checkbox to enable automatic transaction syncing
4. Set **Sync Interval** to **Every Hour** for now

**Important:** The Fund/Category name must match **exactly** what's configured in PushPay. The plugin filters transactions by this fund name to match payments to orders.

---

### 6. Art Pieces

**Adding art pieces individually:**

Go to **Art in Heaven > Add New** and fill in:
- **Art ID** — Your internal numbering (e.g., `001`, `A12`)
- **Title** — Name of the artwork
- **Artist** — Artist's name
- **Medium** — e.g., Oil on Canvas, Watercolor, Photography
- **Dimensions** — e.g., 24" x 36"
- **Description** — Optional description shown on the detail page
- **Starting Bid** — Minimum opening bid amount
- **Images** — Upload one or more photos (watermarks are applied automatically)
- **Auction Start** — Pre-filled from your Event Start Date
- **Auction End** — Pre-filled from your Event End Date
- **Show Timer** — Whether to display the countdown timer on this piece

Art pieces are created in **Draft** status. They will automatically activate when the auction start time arrives.

**Importing art pieces from CSV:**

If you have many art pieces, use the CSV import:
1. Go to **Art in Heaven > Art Pieces**
2. Click **Import Art Pieces**
3. Upload a CSV file with columns: `art_id`, `title`, `artist`, `medium`, `dimensions`, `description`, `starting_bid`
4. Images can be uploaded separately after import

**Setting times in bulk:**

On the Art Pieces page, use the toolbar buttons:
- **Set Start Time** — Set the auction start time for all selected pieces
- **Set End Time** — Set the auction end time for all selected pieces
- **Show Timer** / **Hide Timer** — Toggle countdown visibility for selected pieces

---

### 7. Watermark Settings

Go to **Art in Heaven > Settings** and scroll to the Watermark section.

| Setting | Recommendation |
|---------|---------------|
| Watermark Text | Your event name (e.g., `SILENT AUCTION`) — the year is auto-appended |
| Show Text Watermark | On |
| Overlay Image | Upload a transparent PNG logo to tile across images (optional) |
| Disable WP Image Sizes | On — prevents WordPress from creating unnecessary image copies |

After changing watermark settings, click **Regenerate All Watermarks** to reprocess existing images.

---

### 8. Admin User Accounts

Create WordPress accounts for your event staff. Go to **Users > Add New** in WordPress admin.

| Role | Who Should Have It | What They Can Do |
|------|-------------------|-----------------|
| AIH Super Admin | Event organizer | Everything — full access to all settings and data |
| AIH Art Manager | Art team volunteers | Add/edit art pieces, upload images |
| AIH Pickup Manager | Pickup table volunteers | Only see the Pickup page to check off items as picked up |

WordPress Administrators automatically get full plugin access.

---

### 9. Pre-Event Testing

Do a complete test run before the event:

- [ ] **Login flow:** Use a test confirmation code to log in on the Gallery page
- [ ] **Browse gallery:** Verify art pieces display correctly with images and watermarks
- [ ] **Place a bid:** Bid on an art piece and confirm it appears in My Bids
- [ ] **Bid increment:** Verify the minimum bid increment is enforced (e.g., must bid at least $1 more)
- [ ] **Favorites:** Star an art piece and verify it appears when filtering by favorites
- [ ] **Countdown timer:** Confirm timers display correctly and pieces auto-end when time runs out
- [ ] **Checkout flow:** After an auction ends, verify the winning bidder sees items in Checkout
- [ ] **PushPay redirect:** Click "Proceed to Payment" and verify it opens PushPay with the correct amount
- [ ] **Payment return:** After paying (or cancelling), verify you're redirected back with a status banner
- [ ] **Admin pages:** Check that Bids, Orders, Payments, and Winners pages show correct data
- [ ] **Mobile:** Test the entire flow on a phone — most bidders will use their phones
- [ ] **Multiple bidders:** Have 2-3 people bid on the same item to test the outbid flow

**After testing, clean up:**
1. Go to **Art in Heaven > Art Pieces** and delete any test art pieces
2. Check the database for any test bids/orders and clean them up
3. Re-sync bidders from CCB to get the latest registrant list

---

## Day of Event

### 10. Switch to Live Sync Intervals

Right before the event starts, switch both sync intervals to 30 seconds for near real-time updates.

Go to **Art in Heaven > Integrations**:

1. **CCB section:** Change Sync Interval to **Every 30 Seconds**
   - This picks up last-minute registrations so people can log in immediately
2. **PushPay section:** Change Sync Interval to **Every 30 Seconds**
   - This syncs payment transactions quickly so order statuses update fast
3. Click **Save Integration Settings**

---

### 11. Activate Art Pieces

If your art pieces have auction start/end times set, they will **automatically activate** when the start time arrives. No manual action needed.

If you need to manually activate:
1. Go to **Art in Heaven > Art Pieces**
2. Select the pieces you want to activate
3. Use the **Set Start Time** button to set the start time to now (or a few minutes from now)

**Verify:** Refresh the Gallery page and confirm art pieces are showing as active with countdown timers (if enabled).

---

### 12. Monitor During Event

Keep these admin pages open during the event:

| Page | What to Watch |
|------|--------------|
| **Art Pieces** | Auction statuses — pieces should transition from Draft → Active → Ended automatically |
| **Bids** | Real-time bid activity — watch for any issues with bid placement |
| **Dashboard** | Overview stats — total bids, active auctions, registered bidders |

**Common issues during the event:**
- **Bidder can't log in:** Sync bidders from CCB — they may have registered after the last sync
- **Art piece stuck in Draft:** Check that the auction start time has passed; manually set the start time to now
- **Timer not showing:** Make sure Show Timer is enabled for that art piece
- **Bids not going through:** Check the browser console for JavaScript errors; verify the bidder is logged in

---

## Post-Event

### 13. Immediately After Auctions End

Once all auctions have ended:

1. **Verify all auctions ended:** Go to **Art in Heaven > Art Pieces** and confirm all pieces show as "Ended" or "Sold"
2. **Check winners:** Go to **Art in Heaven > Winners** to see the winning bid for each piece
3. **Check for ties:** Review the **Bids** page — if two bids have the same amount, the earlier bid (by milliseconds) wins

---

### 14. Payment Collection

Winners need to pay for their items:

1. **Direct winners to checkout:** Winners should go to the Checkout page (or My Wins page) to see their winning items
2. **They click "Proceed to Payment"** which redirects them to PushPay with the correct amount pre-filled
3. **After payment:** They're redirected back and see a success/failure banner
4. **Sync transactions:** Go to **Art in Heaven > Integrations** and click **Sync Transactions** to pull payment data from PushPay
5. **Verify:** Go to **Art in Heaven > Payments** to see which orders have been paid

**For manual payment updates:**
If someone pays by cash or check, you can manually update their order status in **Art in Heaven > Orders**.

---

### 15. Pickup Management

For tracking which items have been picked up:

1. Go to **Art in Heaven > Pickup**
2. As winners collect their items, mark each order as "Picked Up"
3. Pickup Manager accounts can access only this page — useful for volunteers at the pickup table

---

### 16. Wind Down

After all payments are collected and items picked up:

1. **Switch sync intervals back:**
   - Go to **Art in Heaven > Integrations**
   - Change both CCB and PushPay sync intervals back to **Every Hour** (or disable auto-sync entirely)
   - Click **Save Integration Settings**

2. **Do a final PushPay sync:**
   - Click **Sync Transactions** one last time to capture any remaining payments

3. **If using server cron for 30-second intervals:**
   - You can leave the server cron running (it's only hitting wp-cron.php once per minute)
   - Or remove it from your hosting cron settings if the event is fully over

---

### 17. Export & Archive

Export your data for record-keeping:

Go to **Art in Heaven > Reports** and export:

| Export | What It Contains |
|--------|-----------------|
| Art Pieces | All pieces with status, starting bid, winning bid, dates |
| Bids | Complete bid history with bidder info and timestamps |
| Bidders | All bidders who logged in (name, email, phone, confirmation code) |
| Registrants | All CCB registrants (including those who never logged in) |
| Orders | All orders with payment status, amounts, and PushPay references |

**To prepare for next year:**
1. Go to **Art in Heaven > Migration**
2. Create tables for the new year
3. Optionally migrate bidder data to the new year
4. Switch the active year in **Settings > Auction Year**

The previous year's data remains in the database and can be accessed by switching the year back.

---

## Quick Reference — Settings Checklist

### Before Event
- [ ] Server cron job configured (every minute)
- [ ] `DISABLE_WP_CRON` set to `true` in wp-config.php
- [ ] All 6 WordPress pages created with shortcodes
- [ ] Plugin Settings: year, event dates, login page, gallery page configured
- [ ] CCB API: credentials entered, test connection passes, initial sync done
- [ ] PushPay: credentials entered, test connection passes, redirect key configured in both PushPay portal and plugin
- [ ] Art pieces added with images, starting bids, and auction times
- [ ] Watermark settings configured and applied
- [ ] Staff accounts created with appropriate roles
- [ ] Full test run completed on mobile and desktop
- [ ] Both auto-sync intervals set to Every Hour

### Day of Event
- [ ] Both auto-sync intervals switched to Every 30 Seconds
- [ ] Final CCB bidder sync done
- [ ] Art pieces activating on schedule
- [ ] Admin monitoring pages open

### After Event
- [ ] All auctions ended
- [ ] Winners verified
- [ ] Final PushPay transaction sync done
- [ ] Payments collected and verified
- [ ] Pickups tracked
- [ ] Both auto-sync intervals switched back to Every Hour (or disabled)
- [ ] Data exported for records
