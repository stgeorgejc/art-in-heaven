/**
 * 5x load test — 250 concurrent users.
 *
 * With 93 test codes, each code is shared by ~2-3 VUs. This is acceptable
 * and also stress-tests PHP session handling under code reuse.
 *
 * Usage:
 *   k6 run load-tests/tests/load-5x.js
 */

import { THRESHOLDS } from '../config/base.js';
import browserScenario from '../scenarios/browser.js';
import passiveBidderScenario from '../scenarios/passive-bidder.js';
import activeBidderScenario from '../scenarios/active-bidder.js';
import checkoutScenario from '../scenarios/checkout-user.js';

export const options = {
  scenarios: {
    browsers: {
      executor: 'ramping-vus',
      exec: 'browserFlow',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 50 },
        { duration: '12m', target: 50 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    passive_bidders: {
      executor: 'ramping-vus',
      exec: 'passiveFlow',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 100 },
        { duration: '12m', target: 100 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    active_bidders: {
      executor: 'ramping-vus',
      exec: 'activeFlow',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 88 },
        { duration: '12m', target: 88 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    checkout_users: {
      executor: 'ramping-vus',
      exec: 'checkoutFlow',
      startVUs: 0,
      stages: [
        { duration: '6m', target: 0 },
        { duration: '3m', target: 12 },
        { duration: '6m', target: 12 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
  },
  thresholds: THRESHOLDS,
};

export function browserFlow()  { browserScenario(); }
export function passiveFlow()  { passiveBidderScenario(); }
export function activeFlow()   { activeBidderScenario(); }
export function checkoutFlow() { checkoutScenario(); }
