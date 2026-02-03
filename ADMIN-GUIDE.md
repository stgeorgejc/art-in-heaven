# Art in Heaven - Admin Guide

A step-by-step guide for managing your silent art auction from the WordPress admin panel.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard](#dashboard)
3. [Settings](#settings)
4. [Integrations](#integrations)
5. [Managing Art Pieces](#managing-art-pieces)
6. [Adding a New Art Piece](#adding-a-new-art-piece)
7. [Managing Bidders](#managing-bidders)
8. [Orders & Payments](#orders--payments)
9. [Winners & Sales](#winners--sales)
10. [Pickup Management](#pickup-management)
11. [PushPay Transactions](#pushpay-transactions)
12. [Reports & Exports](#reports--exports)
13. [Shortcodes](#shortcodes)
14. [Troubleshooting](#troubleshooting)

---

## Getting Started

After activating the plugin, you will see a new **Art in Heaven** menu item in the left sidebar of your WordPress admin panel. This is where you manage everything related to your auction.

Before your auction can go live, you need to complete these steps in order:

1. Go to **Settings** and set your auction year and create database tables.
2. Go to **Integrations** and connect your CCB API and PushPay accounts.
3. Go to **Settings** and configure your event dates, tax rate, and other options.
4. Sync your bidders from the CCB API.
5. Start adding art pieces.

---

## Dashboard

The Dashboard is your home base. It gives you a quick overview of your auction at a glance.

**What you will see:**

- **Total Art Pieces** - How many art pieces are in the system.
- **Active Auctions** - How many pieces are currently accepting bids.
- **With Bids** - How many active pieces have received at least one bid.
- **Active No Bids** - How many active pieces have not received any bids yet.
- **Total Bids** - The total number of bids placed across all pieces.
- **Collected** - The total dollar amount collected from paid orders.

**Quick Links** at the top let you jump to common tasks like adding a new art piece or viewing orders.

**Recent Activity** shows the 10 most recent art pieces with their current bid status and time remaining.

---

## Settings

Go to **Art in Heaven > Settings** to configure your auction.

### Event Settings

- **Event Start Date & Time** - The default start time for new art pieces. When you set this and click "Apply to All Active Art Pieces," every active piece will use this start time.
- **Event End Date & Time** - The default end time for new art pieces.
- **Gallery Page URL** - The URL of the page where you placed the gallery shortcode. This is used for navigation links.

### Auction Year & Database

- **Auction Year** - Enter the year for your auction (e.g., 2025). This is used as a prefix for your database tables. Each year gets its own set of tables so data stays separate.
- **Create Tables** - Click this button after setting the year to create the database tables. You must do this before adding any data.
- **Cleanup & Migrate Old Columns** - Use this if you are upgrading from an older version of the plugin.
- **Delete All Data** - Permanently deletes all auction data. Use with extreme caution.

### General Settings

- **Currency Symbol** - The symbol shown next to dollar amounts (default: $).
- **Min Bid Increment** - The minimum amount a new bid must be above the current highest bid.
- **Tax Rate (%)** - The tax percentage applied to orders at checkout.
- **Login Page URL** - The URL of the page where you placed the login shortcode.
- **Show Sold Items** - When checked, ended/sold items still appear in the gallery. Uncheck to hide them.

### Watermark Settings

All uploaded art images are automatically watermarked to prevent unauthorized use.

- **Watermark Text** - The text overlaid on images. The year is automatically added.
- **Watermark Text Visibility** - Uncheck to disable the text watermark.
- **Crosshatch Pattern** - Adds diagonal lines across the image for extra protection.
- **Watermark Overlay Image** - An optional logo or image tiled across watermarked images. Click "Select Image" to choose one from your media library.
- **Regenerate Watermarks** - After changing any watermark settings, click this to re-watermark all existing images.
- **Image Optimization** - Disables WordPress thumbnail generation to save storage space.

### Colors & Theme

Customize the look of your auction pages to match your branding.

- **Primary/Accent Color** - Used for buttons, links, and badges (default: warm bronze).
- **Secondary/Dark Color** - Used for headings and button text.
- **Success Color** - Used for winning bid indicators and success messages.
- **Error/Warning Color** - Used for outbid notices and error messages.
- **Text Color** - Main body text color.
- **Muted Text Color** - Secondary/lighter text color.

Each color has a color picker and a text field where you can enter a hex code directly. Click the circular arrow button to reset a color to its default. A live preview at the bottom shows how your colors will look.

When you are done, click **Save Settings** at the bottom of the page.

---

## Integrations

Go to **Art in Heaven > Integrations** to connect external services.

### CCB Church API

This integration syncs your event registrants from Church Community Builder so they can log in and bid.

**Setting up the connection:**

1. Enter your **API Base URL** - This is your church's CCB API endpoint (e.g., `https://yourchurch.ccbchurch.com/api.php`).
2. Enter your **Form ID** - The ID of the registration form in CCB.
3. Enter your **API Username** and **API Password**.
4. Click **Test Connection** to verify your credentials work. You should see a success message with the number of registrants found.
5. Click **Save Settings**.

**Syncing bidders:**

1. Click the **Sync from API** button.
2. Wait for the sync to complete. You will see a message showing how many registrants were imported.
3. Bidders must be synced before they can log in to the auction.

**Auto Sync:**

- Check **Enable automatic sync** to have bidders synced automatically.
- Choose a sync interval: **Every Hour** (recommended) or **Every 30 Seconds** (uses more API calls).

### PushPay Payment Processing

This integration connects to PushPay so bidders can pay for their won items.

**Setting up the connection:**

1. Choose your environment: **Production** for real payments, or **Sandbox** for testing.
2. Enter your **Client ID** and **Client Secret** from PushPay.
3. Click **Discover Keys from API** to automatically find your Organization Key, Merchant Key, and Merchant Handle.
   - A list of organizations will appear. Click the one you want.
   - If the organization has multiple merchants, select the correct one.
   - Click **Apply Selected** to fill in the fields.
4. Enter a **Fund/Category** name (default: "art-in-heaven"). This is the fund payments are categorized under in PushPay.
5. Click **Save Settings**.
6. Click **Test Connection** to verify everything works.

---

## Managing Art Pieces

Go to **Art in Heaven > Art Pieces** to see all your art pieces.

### Filtering and Searching

At the top of the page, you have several tabs to filter the list:

- **All** - Shows every art piece.
- **Active with Bids** - Pieces that are live and have received bids.
- **Active - No Bids** - Pieces that are live but have not received any bids yet.
- **Draft** - Pieces that are not visible to bidders.
- **Ended** - Pieces whose auction time has passed.

Use the **Search box** to find pieces by title, artist, or art ID.

Use the **Filter by Artist** dropdown to show only pieces by a specific artist.

Use the **Sort** dropdown to reorder the list (by title, artist, bid amount, number of bids, or end time).

### Bulk Actions

To perform actions on multiple pieces at once:

1. Check the boxes next to the pieces you want to change, or use the **Select All** checkbox at the top.
2. Click one of the bulk action buttons:
   - **Change End Times** (clock icon) - Set a new auction end time for all selected pieces.
   - **Set Event Start** (calendar icon) - Set a new auction start time for all selected pieces.
   - **Reveal End Times** (eye icon) - Make the end time visible to bidders on all selected pieces.
   - **Hide End Times** (hidden eye icon) - Hide the end time from bidders on all selected pieces.

### Inline Editing

You can edit most fields directly in the table without opening the full edit page:

1. Double-click on a cell you want to edit (Art ID, Title, Artist, Medium, Tier, Starting Bid, Start Time, or End Time).
2. The cell will become an input field. Make your change.
3. Press **Enter** or click the checkmark to save.
4. Press **Escape** or click the X to cancel.

### Actions

Each art piece row has action buttons on the right:

- **Edit** (pencil icon) - Opens the full edit page for the piece.
- **Stats** (chart icon) - Shows bid statistics for the piece.
- **Toggle End Time Visibility** (eye icon) - Shows or hides the end time from bidders.
- **Delete** (trash icon) - Permanently deletes the piece. You will be asked to confirm.

---

## Adding a New Art Piece

Go to **Art in Heaven > Add New Art** to create a new art piece.

### Step 1: Basic Information

Fill in the following fields:

- **Title** (required) - The name of the art piece.
- **Artist** (required) - The artist's name.
- **Medium** (required) - The type of artwork (e.g., "Oil on Canvas," "Watercolor on Paper").
- **Dimensions** (optional) - The size of the piece (e.g., "24\" x 36\"").
- **Description** (optional) - A description of the piece that will be shown to bidders.

### Step 2: Auction Settings

- **Starting Bid** (required) - The minimum amount for the first bid.
- **Auction Start** - When bidding opens. If you leave this as the default, bidding starts immediately when the status is set to Active.
- **Auction End** (required) - When bidding closes. As you set this, you will see a live countdown showing how much time remains.
- **Show end time to bidders** - Check this to let bidders see when the auction ends. Uncheck to show "Closing time TBD" instead.
- **Status** - Choose the initial status:
  - **Active** - The piece is live and accepting bids.
  - **Draft** - The piece is hidden from bidders. It will automatically become active when the start time passes.
  - **Ended** - The auction for this piece is over.

### Step 3: Art ID and Tier

In the sidebar on the right:

- **Art ID** (required) - A unique identifier for the piece (e.g., "AIH-001"). Use a consistent format.
- **Tier** (required) - Select a tier from 1 to 4 to categorize the piece.

### Step 4: Image

- Click **Select Image** to open the WordPress media library.
- Choose an image or upload a new one.
- The image will be automatically watermarked after saving.
- After saving the piece, you can add more images from the edit page.

### Step 5: Save

Click **Add Art Piece** to save. You will be taken to the edit page where you can add additional images.

---

## Managing Bidders

Go to **Art in Heaven > Bidders** to see everyone who has registered for your auction.

### Understanding the Tabs

- **Not Logged In** - People who registered through CCB but have not yet logged in to the auction site. You may want to send them a reminder.
- **Logged In - No Bids** - People who have logged in but have not placed any bids yet.
- **Logged In - Has Bids** - People who are actively participating and have placed at least one bid.
- **All Registrants** - Everyone in the system.

### Syncing Bidders

Click the **Sync from API** button at the top to pull the latest registrant data from CCB. The page will show when the last sync occurred and how many people were imported.

### Bidder Information

For each person, you can see:

- **Name** - First and last name.
- **Email** - Their email address.
- **Phone** - Their mobile phone number.
- **Confirmation Code** - The code they use to log in.
- **Bids** - How many bids they have placed.
- **Status** - Whether they have bids, have logged in, or are not yet active.
- **Last Login** - When they last logged in to the auction site.

---

## Orders & Payments

Go to **Art in Heaven > Orders** to manage orders.

### Overview

At the top of the page, you will see summary cards showing:

- **Total Orders** - The total number of orders created.
- **Paid Orders** - How many orders have been paid.
- **Pending Payment** - How many orders are still waiting for payment.
- **Total Collected** - The total dollar amount collected so far.

### Filtering Orders

Use the tabs to filter by payment status:

- **All Orders** - Shows everything.
- **Pending** - Orders waiting for payment.
- **Paid** - Orders that have been paid.
- **Refunded** - Orders that were refunded.
- **Cancelled** - Orders that were cancelled.

Use the **Search box** to find orders by order number, email, or name.

### Viewing an Order

Click the **View** button on any order to see its details.

**Order Detail Page:**

The left side shows the **Order Items** - a list of art pieces in the order with images, titles, artists, art IDs, and winning bid amounts. The subtotal, tax, and total are shown at the bottom.

The right side shows:

- **Bidder Information** - The bidder's name, email, and phone number.
- **Payment Status** - A form where you can update:
  - **Status** - Pending, Paid, Refunded, or Cancelled.
  - **Payment Method** - PushPay, Cash, Check, Credit Card, or Other.
  - **Reference/Check #** - A reference number for the payment.
  - **Notes** - Any notes about the payment.
  - Click **Update Payment** to save changes.
- **Pickup Status** - Shows whether the order has been picked up (only available for paid orders).

### Deleting an Order

Click the **Delete** button on any order in the list view. You will be asked to confirm before the order is permanently deleted.

---

## Winners & Sales

Go to **Art in Heaven > Winners & Sales** to see auction results.

### Summary Cards

At the top, you will see:

- **Total Won** - The total value of all winning bids.
- **Paid** - How much has been paid.
- **Pending Orders** - How much is in pending orders.
- **Not Yet Ordered** - How much has been won but no order has been created yet.

### Tabs

**By Art Piece** - Shows each art piece that has a winning bid, along with the winner's name, the winning amount, and payment status.

**By Bidder** - Groups winning items by bidder. Each bidder card shows their contact info, all the items they won, and their total owed vs. paid.

**Amounts Owed** - Shows only bidders who still owe money, with a summary of their total won, paid, and outstanding amounts.

### Exporting

Click the **Export to CSV** button to download the winners data as a spreadsheet file.

---

## Pickup Management

Go to **Art in Heaven > Pickup** to manage item pickup at your event.

### Tabs

- **Ready for Pickup** - Paid orders that have not been picked up yet.
- **Picked Up** - Orders that have already been picked up.

### Search

Use the search bar at the top to find orders by bidder name, email, order number, or art piece title.

### Marking an Order as Picked Up

1. Find the order in the **Ready for Pickup** tab.
2. Each card shows the order number, bidder name, contact info, and the items in the order.
3. Click the **Mark as Picked Up** button.
4. A popup will appear asking for:
   - **Your Name** (required) - Enter the name of the person handling the pickup.
   - **Notes** (optional) - Any notes about the pickup.
5. Click **Confirm Pickup**.
6. The order will move to the **Picked Up** tab.

### Undoing a Pickup

If a pickup was marked by mistake:

1. Go to the **Picked Up** tab.
2. Find the order and click **Undo Pickup**.
3. Confirm when prompted.
4. The order will move back to the **Ready for Pickup** tab.

---

## PushPay Transactions

Go to **Art in Heaven > Transactions** to view and manage PushPay transactions.

### Syncing Transactions

1. Click the **Sync from PushPay** button to pull the latest transactions.
2. The page will show when the last sync occurred and how many transactions were found.
3. Click **Test Connection** to verify your PushPay connection is working.

### Viewing Transactions

The table shows all synced transactions with:

- **ID** - The internal transaction ID.
- **Date** - When the payment was made.
- **Payer** - The name and email of the person who paid.
- **Amount** - The payment amount.
- **Status** - Success, Processing, or Failed.
- **Fund** - Which fund the payment was categorized under.
- **Order** - The matched order number (if linked to an order).
- **PushPay ID** - The unique transaction ID from PushPay.

### Sorting

Click on any column header (ID, Date, Payer, Amount, Status, Fund, or Order) to sort the table. Click again to reverse the sort direction. The arrow icon shows which column is being sorted and in which direction.

### Filtering

Use the dropdown filters at the top to narrow down the list:

- **Status** - Filter by Success, Processing, or Failed.
- **Matched** - Show only Matched (linked to an order) or Unmatched transactions.
- **Search** - Search by payer name, email, PushPay ID, reference, or order number.

Click **Filter** to apply, or **Clear** to reset all filters.

### Matching Transactions to Orders

If a transaction is not automatically matched to an order:

1. Click the **link icon** button in the Actions column of the unmatched transaction.
2. A popup will appear with a dropdown of available orders.
3. Select the correct order. If the amounts do not match, you will see a warning.
4. Click **Match** to link the transaction to the order.

### Viewing Transaction Details

Click the **eye icon** button in the Actions column to see the full details of a transaction.

---

## Reports & Exports

Go to **Art in Heaven > Reports** to see auction analytics and export data.

### Statistics

The reports page shows:

- **Quick Stats** - Total art pieces, total bids, unique bidders, total winning bid value, and time of last bid.
- **Art Piece Statistics** - Counts of active, draft, and ended pieces, pieces with bids, total starting value, highest bid, and average bid.
- **Payment Statistics** - Total orders, paid and pending counts, total collected, and total pending.
- **Top 10 Art Pieces by Bids** - The most popular pieces ranked by number of bids received.

### Exporting Data

At the bottom of the reports page, you can export your data as JSON files:

- **Export Art Pieces** - Downloads all art piece data.
- **Export Bids** - Downloads all bid data.
- **Export Bidders** - Downloads all bidder/registrant data.
- **Export Orders** - Downloads all order data.

Each file is named with the data type and current date (e.g., `art-in-heaven-bids-2025-06-15.json`).

---

## Shortcodes

Place these shortcodes on WordPress pages to create your auction frontend:

| Shortcode | What It Creates |
|-----------|----------------|
| `[art_in_heaven_gallery]` | The main gallery page showing all art pieces with bidding |
| `[art_in_heaven_login]` | The login page where bidders enter their confirmation code |
| `[art_in_heaven_my_bids]` | A page showing the logged-in user's bid history and orders |
| `[art_in_heaven_checkout]` | The checkout page for paying for won items |
| `[art_in_heaven_my_wins]` | A page showing the logged-in user's winning items |
| `[art_in_heaven_winners]` | A public page showing all auction winners |
| `[art_in_heaven_item id="123"]` | Displays a single art piece (replace 123 with the piece's database ID) |

**To add a shortcode to a page:**

1. In WordPress, go to **Pages > Add New** (or edit an existing page).
2. Add a **Shortcode** block or switch to the text editor.
3. Paste the shortcode (e.g., `[art_in_heaven_gallery]`).
4. Publish or update the page.

---

## Troubleshooting

### Bidders cannot log in
- Make sure you have synced bidders from the CCB API. Go to **Integrations** and click **Sync from API**.
- Verify the confirmation code exists by checking the **Bidders** page.

### Art piece is stuck in Draft
- Draft pieces automatically become active when their auction start time passes. Check that the start time is set correctly on the piece's edit page.

### Images are not watermarked
- Make sure GD Library is enabled on your server. Check **Settings > Server Information**.
- Try clicking **Regenerate Watermarks** in Settings.

### Payments are not showing
- Make sure PushPay is configured in **Integrations**.
- Click **Sync from PushPay** on the Transactions page.
- Check that transactions are being matched to orders.

### Gallery page is empty
- Verify that art pieces are set to **Active** status.
- Check that the auction start time has passed and the end time has not.
- Make sure the gallery page has the `[art_in_heaven_gallery]` shortcode.

### Database tables not found
- Go to **Settings**, set the correct auction year, and click **Create Tables**.
