/**
 * 2x load test — 100 concurrent users.
 *
 * Usage:
 *   k6 run load-tests/tests/load-2x.js
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
        { duration: '2m', target: 20 },
        { duration: '10m', target: 20 },
        { duration: '2m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    passive_bidders: {
      executor: 'ramping-vus',
      exec: 'passiveFlow',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 40 },
        { duration: '10m', target: 40 },
        { duration: '2m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    active_bidders: {
      executor: 'ramping-vus',
      exec: 'activeFlow',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 35 },
        { duration: '10m', target: 35 },
        { duration: '2m', target: 0 },
      ],
      gracefulRampDown: '30s',
    },
    checkout_users: {
      executor: 'ramping-vus',
      exec: 'checkoutFlow',
      startVUs: 0,
      stages: [
        { duration: '5m', target: 0 },
        { duration: '2m', target: 5 },
        { duration: '5m', target: 5 },
        { duration: '2m', target: 0 },
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
