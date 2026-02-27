/**
 * Smoke test — single VU, validates every endpoint works before a real load run.
 *
 * Usage:
 *   k6 run load-tests/tests/smoke.js
 *   k6 run -e BASE_URL=https://staging.example.com load-tests/tests/smoke.js
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, GALLERY_PATH } from '../config/base.js';
import { loadGalleryPage, loginBidder } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { TEST_CODES } from '../config/test-data.js';

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    http_req_failed: ['rate==0'], // zero failures allowed
  },
};

export default function () {
  const code = TEST_CODES[0];
  console.log(`Smoke test using confirmation code: ${code}`);

  // ── 1. Gallery page load (unauthenticated) ──────────────────────────────
  console.log('\n--- 1. Gallery page load ---');
  const page = loadGalleryPage();
  check(null, {
    'extracted frontendNonce': () => page.frontendNonce !== null,
    'extracted publicNonce':   () => page.publicNonce !== null,
  });
  console.log(`  frontendNonce: ${page.frontendNonce ? 'OK' : 'MISSING'}`);
  console.log(`  publicNonce:   ${page.publicNonce ? 'OK' : 'MISSING'}`);
  sleep(1);

  // ── 2. Login ────────────────────────────────────────────────────────────
  console.log('\n--- 2. Login ---');
  const session = loginBidder(code);
  check(null, { 'login succeeded': () => session.success });
  console.log(`  login: ${session.success ? 'OK' : 'FAILED'}`);

  if (!session.success) {
    console.error('Login failed — aborting smoke test.');
    return;
  }

  const { frontendNonce, ajaxUrl } = session;
  sleep(1);

  // ── 3. Gallery data ────────────────────────────────────────────────────
  console.log('\n--- 3. Gallery data ---');
  const galleryRes = api.getGallery(ajaxUrl, frontendNonce);
  let artIds = [];
  try {
    const data = JSON.parse(galleryRes.body);
    check(null, {
      'gallery success flag': () => data.success === true,
      'gallery has pieces':   () => data.data && data.data.length > 0,
    });
    if (data.data) {
      artIds = data.data.map((p) => p.id).filter(Boolean);
    }
  } catch (e) {
    console.error(`  Failed to parse gallery response: ${e}`);
  }
  console.log(`  Found ${artIds.length} art pieces`);
  sleep(1);

  if (artIds.length === 0) {
    console.error('No art pieces found — most endpoint tests will be skipped.');
  }

  // ── 4. Art details ─────────────────────────────────────────────────────
  if (artIds.length > 0) {
    console.log('\n--- 4. Art details ---');
    const detailRes = api.getArtDetails(ajaxUrl, frontendNonce, artIds[0]);
    try {
      const data = JSON.parse(detailRes.body);
      check(null, { 'art details success': () => data.success === true });
      console.log(`  Art piece ${artIds[0]}: ${data.success ? 'OK' : 'FAILED'}`);
    } catch (_) {
      console.error('  Failed to parse art details');
    }
    sleep(1);
  }

  // ── 5. Search ──────────────────────────────────────────────────────────
  console.log('\n--- 5. Search ---');
  const searchRes = api.searchArt(ajaxUrl, frontendNonce, 'art');
  check(null, { 'search returns 200': () => searchRes.status === 200 });
  console.log(`  Search "art": status ${searchRes.status}`);
  sleep(1);

  // ── 6. Poll status ─────────────────────────────────────────────────────
  if (artIds.length > 0) {
    console.log('\n--- 6. Poll status ---');
    const pollRes = api.pollStatus(ajaxUrl, frontendNonce, artIds.slice(0, 10));
    try {
      const data = JSON.parse(pollRes.body);
      check(null, { 'poll_status success': () => data.success === true });
      console.log(`  poll_status: ${data.success ? 'OK' : 'FAILED'}`);
    } catch (_) {
      console.error('  Failed to parse poll_status');
    }
    sleep(1);
  }

  // ── 7. Check outbid ───────────────────────────────────────────────────
  console.log('\n--- 7. Check outbid ---');
  const outbidRes = api.checkOutbid(ajaxUrl, frontendNonce);
  check(null, { 'check_outbid 200': () => outbidRes.status === 200 });
  console.log(`  check_outbid: status ${outbidRes.status}`);
  sleep(1);

  // ── 8. Toggle favorite (on then off) ──────────────────────────────────
  if (artIds.length > 0) {
    console.log('\n--- 8. Toggle favorite ---');
    const favRes1 = api.toggleFavorite(ajaxUrl, frontendNonce, artIds[0]);
    sleep(0.5);
    const favRes2 = api.toggleFavorite(ajaxUrl, frontendNonce, artIds[0]);
    check(null, {
      'favorite toggle on':  () => favRes1.status === 200,
      'favorite toggle off': () => favRes2.status === 200,
    });
    console.log(`  Favorite toggle: ${favRes1.status === 200 && favRes2.status === 200 ? 'OK' : 'FAILED'}`);
    sleep(1);
  }

  // ── 9. Place bid ($1 minimum — identifiable as test) ──────────────────
  if (artIds.length > 0) {
    console.log('\n--- 9. Place bid ---');
    const bidRes = api.placeBid(ajaxUrl, frontendNonce, artIds[0], 1);
    try {
      const data = JSON.parse(bidRes.body);
      // Bid may fail (auction ended, too low, etc.) — that's fine for smoke test
      console.log(`  Bid result: success=${data.success}, message=${data.data ? data.data.message : 'n/a'}`);
    } catch (_) {
      console.error('  Failed to parse bid response');
    }
    check(null, { 'bid endpoint responds': () => bidRes.status === 200 });
    sleep(1);
  }

  // ── 10. Won items (checkout) ──────────────────────────────────────────
  console.log('\n--- 10. Won items ---');
  const wonRes = api.getWonItems(ajaxUrl, frontendNonce);
  check(null, { 'won_items 200': () => wonRes.status === 200 });
  console.log(`  won_items: status ${wonRes.status}`);
  sleep(1);

  // ── 11. Auth check ────────────────────────────────────────────────────
  console.log('\n--- 11. Auth check ---');
  const authRes = api.checkAuth(ajaxUrl, session.publicNonce);
  try {
    const data = JSON.parse(authRes.body);
    check(null, { 'auth check logged_in': () => data.success === true });
    console.log(`  Logged in: ${data.success ? 'YES' : 'NO'}`);
  } catch (_) {
    console.error('  Failed to parse auth check');
  }

  console.log('\n=== Smoke test complete ===');
}
