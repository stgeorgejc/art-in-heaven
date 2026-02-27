/**
 * Checkout scenario — authenticated user checking out won items.
 *
 * This scenario only produces meaningful results when there are ended
 * auctions with winning bids for the test codes.  If no won items exist,
 * the scenario gracefully idles (the endpoint will return an empty list).
 *
 * Flow: login -> get won items -> create order -> get pushpay link -> done
 */

import { sleep } from 'k6';
import { loginBidder } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { TEST_CODES } from '../config/test-data.js';
import { thinkTime } from '../lib/helpers.js';

export default function checkoutScenario() {
  // Stagger logins
  sleep(Math.random() * 12);

  const code = TEST_CODES[__VU % TEST_CODES.length];
  const session = loginBidder(code);

  if (!session.success) {
    sleep(5);
    return;
  }

  const { frontendNonce, ajaxUrl } = session;
  thinkTime(2, 5);

  // 1. Check for won items
  const wonRes = api.getWonItems(ajaxUrl, frontendNonce);
  let wonItems = [];
  try {
    const data = JSON.parse(wonRes.body);
    if (data.success && data.data && data.data.items) {
      wonItems = data.data.items;
    }
  } catch (_) { /* noop */ }

  if (wonItems.length === 0) {
    // No won items — this is expected during active auctions.
    // Just idle to keep the VU alive.
    thinkTime(10, 30);
    return;
  }

  thinkTime(3, 8); // reviewing won items

  // 2. Create order for all won items
  const itemIds = wonItems.map((item) => item.id || item.art_piece_id).filter(Boolean);
  const orderRes = api.createOrder(ajaxUrl, frontendNonce, itemIds);

  let orderNumber = null;
  try {
    const data = JSON.parse(orderRes.body);
    if (data.success && data.data) {
      orderNumber = data.data.order_number;
    }
  } catch (_) { /* noop */ }

  thinkTime(2, 5);

  // 3. Get payment link (if order was created)
  if (orderNumber) {
    api.getPushpayLink(ajaxUrl, frontendNonce, orderNumber);
    thinkTime(3, 8);
  }

  thinkTime(5, 10);
}
