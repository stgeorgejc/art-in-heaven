/**
 * Spike test — simulates the last minutes of an auction when everyone
 * is placing last-second bids and polling aggressively.
 *
 * Ramps from 0 to 200 active bidders in 30 seconds, holds for 3 minutes,
 * then ramps down.  All VUs are active bidders with tight polling intervals.
 *
 * Usage:
 *   k6 run load-tests/tests/spike.js
 */

import { sleep } from 'k6';
import { THRESHOLDS } from '../config/base.js';
import { loginBidder } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { TEST_CODES } from '../config/test-data.js';
import {
  thinkTime, randomItem, randomIntBetween, randomBidAmount,
} from '../lib/helpers.js';

export const options = {
  scenarios: {
    spike: {
      executor: 'ramping-vus',
      exec: 'spikeBidder',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 200 },   // sudden surge
        { duration: '3m',  target: 200 },    // sustained peak
        { duration: '30s', target: 0 },      // rapid drop-off
      ],
      gracefulRampDown: '15s',
    },
  },
  thresholds: THRESHOLDS,
};

export function spikeBidder() {
  if (!TEST_CODES || TEST_CODES.length === 0) {
    throw new Error('TEST_CODES is empty; configure load-tests/config/test-data.js');
  }

  // Minimal login stagger (spike is meant to be sudden)
  sleep(Math.random() * 3);

  const code = TEST_CODES[__VU % TEST_CODES.length];
  const session = loginBidder(code);

  if (!session.success) {
    sleep(2);
    return;
  }

  const { frontendNonce, ajaxUrl } = session;

  // Quick gallery fetch to get art IDs
  const galleryRes = api.getGallery(ajaxUrl, frontendNonce);
  let artIds = [];
  try {
    const data = JSON.parse(galleryRes.body);
    if (data.success && data.data) {
      artIds = data.data.map((p) => p.id).filter(Boolean);
    }
  } catch (_) { /* noop */ }

  if (artIds.length === 0) {
    sleep(2);
    return;
  }

  // Tight loop: bid -> poll -> bid -> poll (simulating auction-ending frenzy)
  const rounds = randomIntBetween(3, 6);
  for (let i = 0; i < rounds; i++) {
    // View a piece and bid
    const artId = randomItem(artIds);
    api.getArtDetails(ajaxUrl, frontendNonce, artId);
    sleep(1);

    api.placeBid(ajaxUrl, frontendNonce, artId, randomBidAmount(10, 500));
    sleep(randomIntBetween(2, 4));

    // Aggressive polling (every 2-3 seconds, like the real app does <1 min)
    api.pollStatus(ajaxUrl, frontendNonce, artIds.slice(0, 50));
    api.checkOutbid(ajaxUrl, frontendNonce);
    sleep(randomIntBetween(2, 3));
  }

  thinkTime(2, 5);
}
