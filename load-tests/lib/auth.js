import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, GALLERY_PATH } from '../config/base.js';

/**
 * Extract the two nonces from the gallery page HTML.
 *
 * The page contains a wp_localize_script block like:
 *   var aihAjax = {"ajaxurl":"...","nonce":"abc123","publicNonce":"def456",...};
 *
 * Returns { frontendNonce, publicNonce, ajaxUrl } or nulls on failure.
 */
export function extractNonces(html) {
  // Frontend nonce (for authenticated AJAX calls)
  // WordPress nonces are alphanumeric (not just hex), so match [a-zA-Z0-9]+
  const nonceMatch = html.match(/"nonce"\s*:\s*"([a-zA-Z0-9]+)"/);
  // Public nonce (for login / logout / auth check)
  const publicMatch = html.match(/"publicNonce"\s*:\s*"([a-zA-Z0-9]+)"/);

  // AJAX URL (defensive — may differ from expected)
  const ajaxMatch = html.match(/"ajaxurl"\s*:\s*"([^"]+)"/);

  return {
    frontendNonce: nonceMatch ? nonceMatch[1] : null,
    publicNonce:   publicMatch ? publicMatch[1] : null,
    ajaxUrl:       ajaxMatch ? ajaxMatch[1].replace(/\\\//g, '/') : null,
  };
}

/**
 * Load the gallery page and extract nonces.
 * Returns { frontendNonce, publicNonce, ajaxUrl, pageOk }.
 */
export function loadGalleryPage() {
  const res = http.get(`${BASE_URL}${GALLERY_PATH}`, {
    tags: { name: 'gallery_page' },
  });

  const pageOk = check(res, {
    'gallery page loads (200)': (r) => r.status === 200,
    'page contains nonce':      (r) => r.body.includes('"nonce"'),
    'page contains publicNonce': (r) => r.body.includes('"publicNonce"'),
  });

  const nonces = extractNonces(res.body);

  return {
    frontendNonce: nonces.frontendNonce,
    publicNonce:   nonces.publicNonce,
    ajaxUrl:       nonces.ajaxUrl,
    pageOk,
  };
}

/**
 * Full login flow for a single virtual user.
 *
 * 1. GET gallery page  -> extract nonces + get initial cookies
 * 2. POST aih_verify_code -> authenticate, get new session cookie
 *
 * Returns { success, frontendNonce, publicNonce, ajaxUrl }.
 */
export function loginBidder(confirmationCode) {
  // Step 1 — load page and extract nonces
  const page = loadGalleryPage();

  if (!page.pageOk || !page.publicNonce) {
    console.error(`VU ${__VU}: Gallery page load failed or nonce missing`);
    return { success: false, frontendNonce: null, publicNonce: null, ajaxUrl: null };
  }

  const ajaxUrl = page.ajaxUrl || `${BASE_URL}/wp-admin/admin-ajax.php`;

  // Step 2 — authenticate with confirmation code using the PUBLIC nonce
  const loginRes = http.post(ajaxUrl, {
    action: 'aih_verify_code',
    nonce:  page.publicNonce,
    code:   confirmationCode,
  }, {
    tags: { name: 'auth_login' },
  });

  let loginData;
  try {
    loginData = JSON.parse(loginRes.body);
  } catch (_) {
    loginData = { success: false };
  }

  const success = check(loginRes, {
    'login HTTP 200':     (r) => r.status === 200,
    'login success flag': () => loginData.success === true,
  });

  return {
    success:       loginData.success === true,
    frontendNonce: page.frontendNonce,
    publicNonce:   page.publicNonce,
    ajaxUrl,
  };
}
