# Art in Heaven - How-To Guide

Step-by-step instructions for common backend tasks.

---

## Table of Contents

1. [Art Pieces](#art-pieces)
   - [Add a New Art Piece](#add-a-new-art-piece)
   - [Edit an Art Piece](#edit-an-art-piece)
   - [Delete an Art Piece](#delete-an-art-piece)
   - [Bulk Update End Times](#bulk-update-end-times)
2. [Transactions](#transactions)
   - [Sync Transactions from PushPay](#sync-transactions-from-pushpay)
   - [Match a Transaction to an Order](#match-a-transaction-to-an-order)
3. [Bidders](#bidders)
   - [Sync Bidders from API](#sync-bidders-from-api)
4. [Orders](#orders)
   - [View an Order](#view-an-order)
   - [Update Payment Status](#update-payment-status)
5. [Pickup](#pickup)
   - [Mark an Order as Picked Up](#mark-an-order-as-picked-up)
   - [Undo a Pickup](#undo-a-pickup)
6. [Settings](#settings)
   - [Configure Event Dates](#configure-event-dates)
   - [Set Up PushPay Integration](#set-up-pushpay-integration)
   - [Set Up CCB Integration](#set-up-ccb-integration)
   - [Customize Colors](#customize-colors)

---

## Art Pieces

### Add a New Art Piece

1. Go to **Art in Heaven > Add New** in the sidebar.
2. Fill in the required fields:
   - **Title** - Name of the art piece.
   - **Artist** - Artist's name.
   - **Medium** - e.g. "Oil on Canvas", "Watercolor".
   - **Art ID** - A unique identifier (e.g. AIH-001).
   - **Tier** - Select Tier 1, 2, 3, or 4.
   - **Starting Bid** - The minimum opening bid amount.
3. Set the auction timing:
   - **Auction Start** - When bidding opens (defaults to now).
   - **Auction End** - When bidding closes (defaults to 7 days from now).
   - **Show End Time to Bidders** - Uncheck to display "Closing time TBD" instead of the actual end time.
4. Optionally fill in **Dimensions** and **Description**.
5. Upload an image by clicking the **Upload Image** button in the sidebar. This opens the WordPress media library.
6. Click **Add Art Piece**.
7. After creating the piece, you can go back and edit it to add additional images.

**Notes:**
- The status is automatically computed based on auction times. If the start time is in the future, status will be "Draft". If the end time is in the past, status will be "Ended".
- Check **Override Auto-Status** if you want to manually force a specific status.
- Images are automatically watermarked.

---

### Edit an Art Piece

**Quick inline edit (from the list):**

1. Go to **Art in Heaven > Art Pieces**.
2. Double-click any field with a pencil icon (Title, Artist, Art ID, Start Time, End Time).
3. Make your change in the inline editor that appears.
4. Click the checkmark to save or the X to cancel.

**Full edit:**

1. Go to **Art in Heaven > Art Pieces**.
2. Click the **pencil icon** on the art piece you want to edit.
3. Update any fields as needed.
4. To manage images:
   - Click **Add Image** to upload additional images.
   - Click the **star icon** on an image to set it as the primary image.
   - Click the **X** on an image to remove it.
5. Click **Update Art Piece**.

---

### Delete an Art Piece

1. Go to **Art in Heaven > Art Pieces**.
2. Find the art piece you want to delete.
3. Click the **trash icon** on that row.
4. Confirm the deletion in the dialog that appears.

**Warning:** This removes the art piece and all associated data permanently.

---

### Bulk Update End Times

1. Go to **Art in Heaven > Art Pieces**.
2. Select multiple art pieces using the checkboxes.
3. From the toolbar, choose an action:
   - **Change End Times** - Set a new auction end time for all selected pieces.
   - **Set Event Start Time** - Set a new start time for all selected pieces.
   - **Reveal End Times** / **Hide End Times** - Show or hide the end time from bidders.
4. Confirm the changes in the modal dialog.

---

## Transactions

### Sync Transactions from PushPay

> **Prerequisite:** PushPay must be configured in **Art in Heaven > Integrations** first.

1. Go to **Art in Heaven > Transactions**.
2. Verify the connection status at the top shows your environment (Sandbox or Production).
3. Click **Test Connection** to confirm the API credentials are working.
4. Click **Sync from PushPay**.
5. Wait for the sync to complete. The stats will update showing:
   - Total Transactions synced.
   - Matched (linked to orders) vs. Unmatched counts.
   - Total Amount collected.

**Filtering transactions:**
- Use the **Status** dropdown to filter by Success, Processing, or Failed.
- Use the **Matched** dropdown to show only Matched or Unmatched transactions.
- Use the **Search** bar to find transactions by payer name, email, or reference number.

---

### Match a Transaction to an Order

If a transaction wasn't automatically matched to an order:

1. Go to **Art in Heaven > Transactions**.
2. Filter by **Unmatched** to find transactions that need linking.
3. Click the **link icon** on the transaction you want to match.
4. In the modal, search for the correct order by order number or bidder name.
5. Review the match - a warning will appear if the amounts don't match.
6. Click **Confirm** to save the match.

---

## Bidders

### Sync Bidders from API

> **Prerequisite:** CCB integration must be configured in **Art in Heaven > Integrations** first.

1. Go to **Art in Heaven > Bidders**.
2. Click **Sync from API**.
3. Confirm the sync when prompted.
4. Wait for the sync to complete. The registrant count and last sync timestamp will update.

**Understanding the tabs:**
- **Not Logged In** - Registered but haven't logged in yet. Consider sending reminder emails.
- **Logged In - No Bids** - Logged in but haven't placed any bids yet.
- **Logged In - Has Bids** - Active participants who have placed bids.
- **All Registrants** - Everyone registered, regardless of status.

---

## Orders

### View an Order

1. Go to **Art in Heaven > Orders**.
2. Use the tabs to filter: All Orders, Pending Payment, Paid, Refunded, or Cancelled.
3. Click the **eye icon** or the **order number** to view the full order details.
4. The order detail page shows:
   - All art pieces in the order with winning bid amounts.
   - Subtotal, tax, and total.
   - Bidder contact information.
   - Payment and pickup status.

---

### Update Payment Status

1. Open an order (see [View an Order](#view-an-order) above).
2. In the **Payment Status** sidebar section:
   - Select the new **Status** (Pending, Paid, Refunded, Cancelled).
   - Select the **Payment Method** (PushPay, Cash, Check, Credit Card, Other).
   - Optionally enter a **Reference/Check #**.
   - Optionally add **Notes**.
3. Click **Update Payment**.

---

## Pickup

### Mark an Order as Picked Up

1. Go to **Art in Heaven > Pickup**.
2. You'll see the **Ready for Pickup** tab with all paid orders awaiting pickup.
3. Use the search bar to find an order by name, email, order number, or art piece.
4. Click **Mark as Picked Up** on the order.
5. In the modal:
   - Enter **Your Name** (required) - the person handling the pickup.
   - Optionally add **Notes**.
6. Click **Confirm Pickup**.

The order will move to the **Picked Up** tab.

---

### Undo a Pickup

1. Go to **Art in Heaven > Pickup**.
2. Click the **Picked Up** tab.
3. Find the order and click **Undo Pickup**.
4. The order will move back to the **Ready for Pickup** tab.

---

## Settings

### Configure Event Dates

1. Go to **Art in Heaven > Settings**.
2. Under **Event Settings**:
   - Set the **Event Start Date & Time** - This is the default start time for new art pieces.
   - Set the **Event End Date & Time** - This is the default end time for new art pieces.
3. Click **Save Settings**.

---

### Set Up PushPay Integration

1. Go to **Art in Heaven > Integrations**.
2. Scroll to the **PushPay Payment Processing** section.
3. Select your environment: **Production** (live) or **Sandbox** (testing).
4. Enter your credentials:
   - **Client ID**
   - **Client Secret**
5. Click **Discover Keys from API** to automatically find your Organization Key, Merchant Key, and Merchant Handle.
   - If multiple organizations are found, select the correct one from the list.
   - If multiple merchants exist, select the correct merchant.
6. Set the **Fund/Category** (required for payment processing).
7. Optionally set a **Return URL** for after payment completes.
8. Click **Save**.
9. Click **Test Connection** to verify everything is working.

---

### Set Up CCB Integration

1. Go to **Art in Heaven > Integrations**.
2. Under **CCB Church Management System**, enter:
   - **API Base URL**
   - **Form ID** - The CCB form used for auction registration.
   - **API Username**
   - **API Password**
3. Optionally enable **Auto Sync** and choose the interval:
   - **Every Hour** - Standard interval.
   - **Every 30 Seconds** - Use only during active registration periods (increases API usage).
4. Click **Save**.
5. Click **Test Connection** to verify credentials.
6. Click **Sync Bidders from API** to pull in all registrants.

---

### Customize Colors

1. Go to **Art in Heaven > Settings**.
2. Scroll to **Colors & Theme**.
3. For each color, either:
   - Click the color swatch to open the color picker, or
   - Type a hex code directly into the text field.
4. Available colors:
   - **Primary/Accent** - Main brand color (buttons, links).
   - **Secondary/Dark** - Darker accent color.
   - **Success** - Used for success messages and paid status.
   - **Error/Warning** - Used for error messages and alerts.
   - **Text** - Main text color.
   - **Muted Text** - Secondary/lighter text color.
5. Use the live preview on the right to see how your colors look.
6. Click **Reset** next to any color to restore the default.
7. Click **Save Settings**.
