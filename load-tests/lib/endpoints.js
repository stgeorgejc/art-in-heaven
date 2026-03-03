import http from 'k6/http';
import { check } from 'k6';
import { encodeFormData } from './helpers.js';

// ---------------------------------------------------------------------------
// AJAX endpoint wrappers.
// Every function takes (ajaxUrl, nonce, ...params) and returns the k6 Response.
// The `nonce` param is whichever tier is appropriate:
//   - publicNonce for login/logout/auth-check
//   - frontendNonce for everything else
// ---------------------------------------------------------------------------

const FORM_HEADERS = { 'Content-Type': 'application/x-www-form-urlencoded' };

// ---- Gallery ---------------------------------------------------------------

export function getGallery(ajaxUrl, nonce) {
  const res = http.post(ajaxUrl, {
    action: 'aih_get_gallery',
    nonce,
  }, { tags: { name: 'gallery_load' } });

  check(res, { 'gallery data 200': (r) => r.status === 200 });
  return res;
}

export function getArtDetails(ajaxUrl, nonce, artId) {
  const res = http.post(ajaxUrl, {
    action: 'aih_get_art_details',
    nonce,
    art_id: String(artId),
  }, { tags: { name: 'art_details' } });

  check(res, { 'art details 200': (r) => r.status === 200 });
  return res;
}

export function searchArt(ajaxUrl, nonce, query) {
  const res = http.post(ajaxUrl, {
    action: 'aih_search',
    nonce,
    search: query,
  }, { tags: { name: 'search' } });

  check(res, { 'search 200': (r) => r.status === 200 });
  return res;
}

// ---- Bidding ---------------------------------------------------------------

export function placeBid(ajaxUrl, nonce, artPieceId, bidAmount) {
  const res = http.post(ajaxUrl, {
    action:       'aih_place_bid',
    nonce,
    art_piece_id: String(artPieceId),
    bid_amount:   String(Math.floor(bidAmount)),  // whole dollars
  }, { tags: { name: 'bid_placement' } });

  check(res, { 'bid endpoint 200': (r) => r.status === 200 });
  return res;
}

export function toggleFavorite(ajaxUrl, nonce, artPieceId) {
  const res = http.post(ajaxUrl, {
    action:       'aih_toggle_favorite',
    nonce,
    art_piece_id: String(artPieceId),
  }, { tags: { name: 'toggle_favorite' } });

  check(res, { 'favorite 200': (r) => r.status === 200 });
  return res;
}

// ---- Polling ---------------------------------------------------------------

export function pollStatus(ajaxUrl, nonce, artPieceIds) {
  const body = encodeFormData({
    action:        'aih_poll_status',
    nonce,
    art_piece_ids: artPieceIds,
  });

  const res = http.post(ajaxUrl, body, {
    headers: FORM_HEADERS,
    tags: { name: 'poll_status' },
  });

  check(res, { 'poll_status 200': (r) => r.status === 200 });
  return res;
}

export function checkOutbid(ajaxUrl, nonce) {
  const res = http.post(ajaxUrl, {
    action: 'aih_check_outbid',
    nonce,
  }, { tags: { name: 'check_outbid' } });

  check(res, { 'check_outbid 200': (r) => r.status === 200 });
  return res;
}

// ---- Checkout --------------------------------------------------------------

export function getWonItems(ajaxUrl, nonce) {
  const res = http.post(ajaxUrl, {
    action: 'aih_get_won_items',
    nonce,
  }, { tags: { name: 'won_items' } });

  check(res, { 'won_items 200': (r) => r.status === 200 });
  return res;
}

export function createOrder(ajaxUrl, nonce, artPieceIds) {
  const body = encodeFormData({
    action:        'aih_create_order',
    nonce,
    art_piece_ids: artPieceIds,
  });

  const res = http.post(ajaxUrl, body, {
    headers: FORM_HEADERS,
    tags: { name: 'checkout' },
  });

  check(res, { 'create_order 200': (r) => r.status === 200 });
  return res;
}

export function getPushpayLink(ajaxUrl, nonce, orderNumber) {
  const res = http.post(ajaxUrl, {
    action:       'aih_get_pushpay_link',
    nonce,
    order_number: orderNumber,
  }, { tags: { name: 'pushpay_link' } });

  check(res, { 'pushpay_link 200': (r) => r.status === 200 });
  return res;
}

// ---- Auth (uses publicNonce) -----------------------------------------------

export function checkAuth(ajaxUrl, publicNonce) {
  const res = http.post(ajaxUrl, {
    action: 'aih_check_auth',
    nonce:  publicNonce,
  }, { tags: { name: 'auth_check' } });

  check(res, { 'auth_check 200': (r) => r.status === 200 });
  return res;
}
