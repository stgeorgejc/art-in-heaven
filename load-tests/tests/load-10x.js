/**
 * 10x load test — 500 concurrent users.
 *
 * With 93 test codes, each code is shared by ~5-6 VUs. This will surface
 * PHP session contention issues, which is valuable signal.
 *
 * For cleaner results at this tier, generate additional LOADTEST* registrants
 * in the database (see README for SQL).
 *
 * Usage:
 *   k6 run load-tests/tests/load-10x.js
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
        { duration: '3m', target: 100 },
        { duration: '12m', target: 100 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    passive_bidders: {
      executor: 'ramping-vus',
      exec: 'passiveFlow',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 200 },
        { duration: '12m', target: 200 },
        { duration: '3m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    active_bidders: {
      executor: 'ramping-vus',
      exec: 'activeFlow',
      startVUs: 0,
      stages: [
        { duration: '3m', target: 175 },
        { duration: '12m', target: 175 },
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
        { duration: '3m', target: 25 },
        { duration: '6m', target: 25 },
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
