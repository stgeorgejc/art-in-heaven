/**
 * Passive bidder scenario — authenticated user who browses and favorites
 * but rarely or never bids.
 *
 * Flow: login -> gallery -> browse details -> toggle favorites ->
 *       poll status periodically -> repeat
 */

import { sleep } from 'k6';
import { loginBidder } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { SEARCH_TERMS, PUSH_ENABLED } from '../config/base.js';
import { TEST_CODES } from '../config/test-data.js';
import {
  thinkTime, randomItem, randomItems, randomIntBetween,
} from '../lib/helpers.js';

export default function passiveBidderScenario() {
  if (!TEST_CODES || TEST_CODES.length === 0) {
    throw new Error('TEST_CODES is empty; configure load-tests/config/test-data.js');
  }

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

  // 2. Browse 2-3 art pieces
  const browsePieces = randomItems(artIds, randomIntBetween(2, 3));
  for (const artId of browsePieces) {
    api.getArtDetails(ajaxUrl, frontendNonce, artId);
    thinkTime(5, 15);
  }

  // 3. Toggle 1-2 favorites
  const favPieces = randomItems(artIds, randomIntBetween(1, 2));
  for (const artId of favPieces) {
    api.toggleFavorite(ajaxUrl, frontendNonce, artId);
    thinkTime(2, 5);
  }

  // 4. Occasional search (40% chance)
  if (Math.random() < 0.4) {
    api.searchArt(ajaxUrl, frontendNonce, randomItem(SEARCH_TERMS));
    thinkTime(3, 8);
  }

  // 5. Poll status (simulating staying on the page)
  // With push disabled: aggressive polling via check_outbid (10-30s intervals)
  // With push enabled: lighter polling (30-60s), push handles outbid alerts
  const pollCount = PUSH_ENABLED ? randomIntBetween(2, 3) : randomIntBetween(3, 5);
  const pollIds = artIds.slice(0, Math.min(50, artIds.length));

  for (let i = 0; i < pollCount; i++) {
    api.pollStatus(ajaxUrl, frontendNonce, pollIds);
    if (!PUSH_ENABLED) {
      api.checkOutbid(ajaxUrl, frontendNonce);
    }
    sleep(PUSH_ENABLED ? randomIntBetween(30, 60) : randomIntBetween(10, 30));
  }

  thinkTime(5, 10);
}
