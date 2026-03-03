/**
 * Template for test-data.js — copy this file to test-data.js and fill in
 * real values.  test-data.js is git-ignored so secrets stay out of VCS.
 *
 * Confirmation codes come from the CCB registration CSV.
 * Art piece IDs are discovered dynamically from the gallery endpoint at runtime.
 */

// Unique confirmation codes from the testing registration CSV.
// Each VU gets one code; for tiers that exceed the code count, codes are
// reused round-robin (acceptable up to ~2x sharing; beyond that, generate
// synthetic LOADTEST* registrants in the DB).
export const TEST_CODES = [
  // 'XXXXXXXX',
  // 'YYYYYYYY',
];
