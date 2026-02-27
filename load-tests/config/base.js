/**
 * Shared configuration for all k6 load tests.
 *
 * Override BASE_URL at runtime:
 *   k6 run -e BASE_URL=https://staging.example.com tests/smoke.js
 */

export const BASE_URL = __ENV.BASE_URL || 'https://aihgallery.org';
export const GALLERY_PATH = '/live/';
export const AJAX_URL = `${BASE_URL}/wp-admin/admin-ajax.php`;

// ---------------------------------------------------------------------------
// Test tier definitions (VU counts and durations)
// ---------------------------------------------------------------------------
export const TIERS = {
  smoke:     { vus: 1,   duration: '30s'  },
  baseline:  { vus: 50,  duration: '10m'  },
  '2x':      { vus: 100, duration: '15m'  },
  '5x':      { vus: 250, duration: '15m'  },
  '10x':     { vus: 500, duration: '15m'  },
};

// ---------------------------------------------------------------------------
// Persona mix — percentage of total VUs per scenario
// ---------------------------------------------------------------------------
export const PERSONA_MIX = {
  browser:       0.20,  // unauthenticated browsing
  passiveBidder: 0.40,  // authenticated read-heavy
  activeBidder:  0.35,  // authenticated with bidding
  checkoutUser:  0.05,  // post-auction checkout
};

// ---------------------------------------------------------------------------
// Pass/fail thresholds (k6 built-in threshold syntax)
// ---------------------------------------------------------------------------
export const THRESHOLDS = {
  // Global
  http_req_duration: ['p(95)<2000', 'p(99)<5000'],
  http_req_failed:   ['rate<0.05'],

  // Per-endpoint (matched via tags.name)
  'http_req_duration{name:gallery_page}':  ['p(95)<3000'],
  'http_req_duration{name:gallery_load}':  ['p(95)<3000'],
  'http_req_duration{name:art_details}':   ['p(95)<2000'],
  'http_req_duration{name:search}':        ['p(95)<2000'],
  'http_req_duration{name:bid_placement}': ['p(95)<1500'],
  'http_req_duration{name:poll_status}':   ['p(95)<500'],
  'http_req_duration{name:auth_login}':    ['p(95)<2000'],
  'http_req_duration{name:checkout}':      ['p(95)<3000'],
};

// ---------------------------------------------------------------------------
// Auto-abort thresholds (stop the test if things go really wrong)
// ---------------------------------------------------------------------------
export const ABORT_THRESHOLDS = {
  http_req_failed:   [{ threshold: 'rate<0.10', abortOnFail: true, delayAbortEval: '30s' }],
  http_req_duration: [{ threshold: 'p(95)<10000', abortOnFail: true, delayAbortEval: '30s' }],
};

// ---------------------------------------------------------------------------
// Search terms used by scenarios
// ---------------------------------------------------------------------------
export const SEARCH_TERMS = [
  'landscape', 'abstract', 'oil', 'canvas', 'portrait',
  'acrylic', 'watercolor', 'mixed', 'charcoal', 'ink',
];
