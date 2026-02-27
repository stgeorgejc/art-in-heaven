/**
 * Active bidder scenario — authenticated user who actively bids.
 *
 * Flow: login -> gallery -> view details -> place bids ->
 *       aggressive polling -> occasional search -> repeat
 */

import { sleep } from 'k6';
import { loginBidder } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { SEARCH_TERMS } from '../config/base.js';
import { TEST_CODES } from '../config/test-data.js';
import {
  thinkTime, randomItem, randomItems, randomIntBetween, randomBidAmount,
} from '../lib/helpers.js';

export default function activeBidderScenario() {
  // Stagger logins to respect auth rate limit (5/60s per IP)
  sleep(Math.random() * 12);

  const code = TEST_CODES[__VU % TEST_CODES.length];
  const session = loginBidder(code);

  if (!session.success) {
    sleep(5);
    return;
  }

  const { frontendNonce, ajaxUrl } = session;
  thinkTime(2, 5);

  // 1. Load gallery data
  const galleryRes = api.getGallery(ajaxUrl, frontendNonce);
  let artIds = [];
  try {
    const data = JSON.parse(galleryRes.body);
    if (data.success && data.data) {
      artIds = data.data.map((p) => p.id).filter(Boolean);
    }
  } catch (_) { /* noop */ }

  if (artIds.length === 0) {
    sleep(5);
    return;
  }

  thinkTime(3, 8);

  // 2. Browse 2-4 art pieces (studying before bidding)
  const browsePieces = randomItems(artIds, randomIntBetween(2, 4));
  for (const artId of browsePieces) {
    api.getArtDetails(ajaxUrl, frontendNonce, artId);
    thinkTime(5, 15);
  }

  // 3. Place 1-3 bids on random pieces
  const bidPieces = randomItems(artIds, randomIntBetween(1, 3));
  for (const artId of bidPieces) {
    // View details first (realistic — user checks current price)
    api.getArtDetails(ajaxUrl, frontendNonce, artId);
    thinkTime(2, 5);

    const amount = randomBidAmount(10, 500);
    api.placeBid(ajaxUrl, frontendNonce, artId, amount);
    thinkTime(3, 8);
  }

  // 4. Aggressive polling (5-15s intervals, simulating watching the auction)
  const pollCount = randomIntBetween(3, 5);
  const pollIds = artIds.slice(0, Math.min(50, artIds.length));

  for (let i = 0; i < pollCount; i++) {
    api.pollStatus(ajaxUrl, frontendNonce, pollIds);
    api.checkOutbid(ajaxUrl, frontendNonce);
    sleep(randomIntBetween(5, 15));
  }

  // 5. Occasional search (30% chance)
  if (Math.random() < 0.3) {
    api.searchArt(ajaxUrl, frontendNonce, randomItem(SEARCH_TERMS));
    thinkTime(2, 5);
  }

  // 6. Maybe toggle a favorite (20% chance)
  if (Math.random() < 0.2) {
    api.toggleFavorite(ajaxUrl, frontendNonce, randomItem(artIds));
    thinkTime(1, 3);
  }

  thinkTime(5, 10);
}
