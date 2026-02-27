/**
 * Template for test-data.js — copy this file to test-data.js and fill in
 * real values.  test-data.js is git-ignored so secrets stay out of VCS.
 *
 * Confirmation codes come from the CCB registration CSV.
 * Art piece IDs are populated dynamically during the smoke test, but you
 * can hard-code them here after a first run if preferred.
 */

// Unique confirmation codes from the testing registration CSV.
// Each VU gets one code; for tiers that exceed the code count, codes are
// reused round-robin (acceptable up to ~2x sharing; beyond that, generate
// synthetic LOADTEST* registrants in the DB).
export const TEST_CODES = [
  // 'XXXXXXXX',
  // 'YYYYYYYY',
];

// Art piece IDs currently active on the gallery.
// Leave empty to auto-discover from the gallery endpoint at runtime.
export const ART_PIECE_IDS = [];
