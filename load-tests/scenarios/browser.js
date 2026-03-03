/**
 * Browser scenario — unauthenticated user browsing the gallery.
 *
 * Simulates someone who lands on the gallery page, scrolls around,
 * occasionally searches, but never logs in or bids.
 */

import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, GALLERY_PATH, SEARCH_TERMS } from '../config/base.js';
import { extractNonces } from '../lib/auth.js';
import * as api from '../lib/endpoints.js';
import { thinkTime, randomItem, randomIntBetween } from '../lib/helpers.js';

export default function browserScenario() {
  // 1. Load the gallery page (full HTML)
  const pageRes = http.get(`${BASE_URL}${GALLERY_PATH}`, {
    tags: { name: 'gallery_page' },
  });
  check(pageRes, { 'page loads': (r) => r.status === 200 });

  const { frontendNonce } = extractNonces(pageRes.body);
  const ajaxUrl = `${BASE_URL}/wp-admin/admin-ajax.php`;

  thinkTime(3, 8); // looking at the page

  // 2. Load gallery data (this works without auth)
  if (frontendNonce) {
    const galleryRes = api.getGallery(ajaxUrl, frontendNonce);
    thinkTime(5, 15); // scrolling through art

    // 3. Occasionally search (30% chance)
    if (Math.random() < 0.3) {
      api.searchArt(ajaxUrl, frontendNonce, randomItem(SEARCH_TERMS));
      thinkTime(3, 8);
    }

    // 4. View 1-2 art piece details
    let galleryData;
    try { galleryData = JSON.parse(galleryRes.body); } catch (_) { /* noop */ }

    if (galleryData && galleryData.success && galleryData.data) {
      const artIds = galleryData.data.map((p) => p.id).filter(Boolean);
      const viewCount = randomIntBetween(1, Math.min(2, artIds.length));

      for (let i = 0; i < viewCount; i++) {
        api.getArtDetails(ajaxUrl, frontendNonce, randomItem(artIds));
        thinkTime(5, 12);
      }
    }
  }

  thinkTime(5, 15); // idle time before next iteration
}
