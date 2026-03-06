# Art in Heaven — Load Testing Suite

k6-based load tests for the Art in Heaven gallery at `aihgallery.org/live/`.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Setup](#setup)
- [Quick Start](#quick-start)
- [Test Plan](#test-plan)
- [User Personas](#user-personas)
- [Endpoints Under Test](#endpoints-under-test)
- [Push Notification Modes](#push-notification-modes)
- [Authentication Strategy](#authentication-strategy)
- [Thresholds](#thresholds)
- [Infrastructure](#infrastructure)
- [Execution Playbook](#execution-playbook)
- [Server Monitoring](#server-monitoring)
- [Post-Test Cleanup](#post-test-cleanup)
- [Scaling Test Codes](#scaling-test-codes)
- [File Reference](#file-reference)

## Prerequisites

```bash
brew install k6    # macOS
# or: https://grafana.com/docs/k6/latest/set-up/install-k6/
```

## Setup

1. Copy the test data template and fill in real confirmation codes:

```bash
cp load-tests/config/test-data.example.js load-tests/config/test-data.js
```

The file is git-ignored. It should export `TEST_CODES` — an array of CCB confirmation code strings.

## Quick Start

All commands run from the project root.

```bash
# 1. Smoke test — validate all endpoints work
k6 run load-tests/tests/smoke.js

# 2. Progressive load tiers
k6 run load-tests/tests/baseline.js   # 50 VUs  (historical peak)
k6 run load-tests/tests/load-2x.js    # 100 VUs
k6 run load-tests/tests/load-5x.js    # 250 VUs
k6 run load-tests/tests/load-10x.js   # 500 VUs

# 3. Spike test — auction-ending frenzy
k6 run load-tests/tests/spike.js      # 0 → 200 VUs in 30s
```

### Save results

```bash
k6 run --out json=load-tests/results/baseline.json load-tests/tests/baseline.js \
  2>&1 | tee load-tests/results/baseline.log
```

### Override settings

```bash
k6 run -e BASE_URL=https://staging.example.com load-tests/tests/smoke.js
k6 run -e GALLERY_PATH=/gallery/ load-tests/tests/smoke.js
k6 run -e PUSH_ENABLED=true load-tests/tests/load-5x.js
```

## Test Plan

### Objective

Validate that the gallery can handle 2x–10x growth beyond the historical baseline of 50 concurrent active bidders before the live auction event.

### Load Tiers

| Tier | Total VUs | Browser (20%) | Passive (40%) | Active (35%) | Checkout (5%) | Duration |
|:-----|:---------:|:-------------:|:-------------:|:------------:|:-------------:|:--------:|
| Smoke | 1 | 1 | 0 | 0 | 0 | 30s |
| Baseline | 50 | 10 | 20 | 18 | 2 | 14 min |
| 2x | 100 | 20 | 40 | 35 | 5 | 14 min |
| 5x | 250 | 50 | 100 | 88 | 12 | 18 min |
| 10x | 500 | 100 | 200 | 175 | 25 | 18 min |
| Spike | 200 | 0 | 0 | 200 | 0 | 4 min |

All tier tests use a **ramping-VU** pattern: gradual ramp-up → sustained steady state → gradual ramp-down.

The **spike test** simulates the last minutes of an auction: 0 → 200 active bidders in 30 seconds, sustained for 3 minutes, then rapid drop-off.

### Go / No-Go

| Tier Result | Decision |
|:------------|:---------|
| Baseline passes all thresholds | Server handles current load — proceed to 2x |
| 2x passes all thresholds | Good headroom — proceed to 5x |
| 5x passes all thresholds | Strong capacity — proceed to 10x |
| Any tier fails thresholds | Stop, diagnose bottleneck, optimize |
| Spike test passes | Server handles auction-ending surge |

## User Personas

### Browser (Unauthenticated — 20%)

Visitor who browses the gallery but never logs in.

1. GET gallery page (full HTML)
2. Load gallery data via AJAX
3. View 1–2 art piece details
4. 30% chance: search
5. Idle 5–15s

### Passive Bidder (Authenticated — 40%)

Logged-in attendee who browses and favorites but doesn't bid.

1. Login with confirmation code
2. Load gallery data
3. Browse 2–3 art piece details
4. Toggle 1–2 favorites
5. 40% chance: search
6. Poll status 3–5 times (10–30s intervals with push disabled; 30–60s with push enabled)
7. Check outbid notifications alongside each poll (push disabled only)

### Active Bidder (Authenticated — 35%)

Bidder actively competing in the auction.

1. Login with confirmation code
2. Load gallery data
3. Browse 2–4 art pieces
4. Place 1–3 bids ($10–$500)
5. Aggressive polling: 3–5 rounds (5–15s with push disabled; 15–30s with push enabled)
6. 30% chance: search
7. 20% chance: toggle a favorite

### Checkout User (Authenticated — 5%)

Winner checking out after auctions end.

1. Login with confirmation code
2. Fetch won items
3. If items exist: create order → get Pushpay payment link
4. If no items: idle 10–30s (graceful no-op)

## Endpoints Under Test

All AJAX endpoints go through `POST /wp-admin/admin-ajax.php`.

### Public (use `aih_public_nonce`)

| Action | Purpose | Rate Limit |
|:-------|:--------|:-----------|
| `aih_verify_code` | Login with confirmation code | 5 / 60s per IP |
| `aih_check_auth` | Verify login state | — |

### Authenticated (use `aih_frontend_nonce`)

| Action | Purpose | Rate Limit |
|:-------|:--------|:-----------|
| `aih_get_gallery` | Fetch all active art pieces | — |
| `aih_get_art_details` | Single art piece with bid data | — |
| `aih_search` | Search by title/artist/medium | — |
| `aih_place_bid` | Place a bid (whole dollars, min $1) | 10 / 60s per bidder |
| `aih_toggle_favorite` | Favorite / unfavorite a piece | — |
| `aih_poll_status` | Status for up to 200 pieces | Cached 3s per bidder |
| `aih_check_outbid` | Outbid notification polling fallback | — |
| `aih_get_won_items` | Items won at ended auctions | — |
| `aih_create_order` | Create checkout order | 5 / 60s per bidder |
| `aih_get_pushpay_link` | Payment URL for an order | — |

## Push Notification Modes

Push notifications are controlled by the `aih_push_enabled` admin setting. The load tests mirror this with the `PUSH_ENABLED` env variable.

### Push disabled (default — `PUSH_ENABLED=false`)

- All clients rely on `check_outbid` polling as the fallback
- Polling intervals are aggressive (5–30s depending on persona)
- **This is the heavier/worst-case server load scenario**
- Default for load tests since it matches the current production configuration

### Push enabled (`PUSH_ENABLED=true`)

- Clients receive Web Push (VAPID) notifications for outbid events
- `check_outbid` polling is skipped (push handles it)
- `poll_status` intervals are longer (15–60s depending on persona)
- Lighter server load, but adds Web Push delivery overhead per outbid event
- **Current limitation:** the k6 scenarios do **not** call `aih_push_subscribe` (or otherwise seed push subscriptions), so `AIH_Push::send_push()` returns early with no work to do. As a result, `PUSH_ENABLED=true` tests primarily measure the effect of reduced polling, **not** the full push-enabled production load. Interpret results accordingly.

### Spike test

The spike test always uses aggressive polling regardless of `PUSH_ENABLED`, since during the final seconds of an auction all clients poll aggressively as a safety net — even with push enabled.

```bash
# Test with push disabled (default — worst case)
k6 run load-tests/tests/load-5x.js

# Test with push enabled (lighter polling)
k6 run -e PUSH_ENABLED=true load-tests/tests/load-5x.js
```

## Authentication Strategy

### Two-nonce system

The gallery page injects two nonces via `wp_localize_script`:
- `aihAjax.publicNonce` — for login/logout/auth-check
- `aihAjax.nonce` — for all authenticated endpoints

### Login flow per VU

1. **GET** gallery page HTML
2. **Extract** both nonces via regex from `aihAjax` JS object
3. **POST** `aih_verify_code` with `publicNonce` + confirmation code
4. Server regenerates session; k6 cookie jar captures `PHPSESSID`
5. All subsequent requests use `frontendNonce` + session cookie

### Rate limit mitigation

The auth endpoint is rate-limited to 5 attempts/60s per source IP. Since all k6 VUs share one IP, logins are staggered with a random 0–12s delay before each VU's first login.

## Thresholds

### Performance (must pass)

| Metric | Threshold |
|:-------|:----------|
| Overall p95 | < 2 seconds |
| Overall p99 | < 5 seconds |
| Gallery page + data p95 | < 3 seconds |
| Bid placement p95 | < 1.5 seconds |
| Poll status p95 | < 500 milliseconds |
| Art details p95 | < 2 seconds |
| Login p95 | < 2 seconds |
| Error rate | < 5% |

### Auto-abort (safety)

| Condition | Action |
|:----------|:-------|
| Error rate > 10% for 30s | Test aborts |
| p95 > 10 seconds for 30s | Test aborts |

## Infrastructure

| Component | Detail |
|:----------|:-------|
| **URL** | `https://aihgallery.org/live/` |
| **Server** | MediaTemple / GoDaddy VPS (AMD EPYC, 4 cores, 8 GB RAM) |
| **OS** | CentOS Linux 7.9 |
| **Panel** | Plesk Obsidian v18.0.75 |
| **Web Stack** | Nginx (reverse proxy) → Apache (event MPM) → PHP-FPM 8.3 |
| **PHP-FPM** | `pm=dynamic`, `max_children=40`, `start_servers=5`, `min_spare=3`, `max_spare=10` |
| **MySQL** | `max_connections=200` |
| **Caching** | WordPress transients (object cache + DB fallback) |
| **Push** | Web Push via VAPID (toggleable via admin setting) |

### Application optimizations applied

- `poll_status` consolidated from 4 queries to 1 dual-LEFT-JOIN query
- Composite index `art_bidder_status (art_piece_id, bid_status, bidder_id, is_winning)` on bids table
- Per-request static caching for Pushpay settings and VAPID keys
- Stale pending orders auto-cancelled after 10 minutes

## Execution Playbook

### Pre-test checklist

- [ ] k6 installed (`brew install k6`)
- [ ] `config/test-data.js` populated with confirmation codes
- [ ] Confirm push notification setting matches test mode (`PUSH_ENABLED` env var)
- [ ] Smoke test passes: `k6 run load-tests/tests/smoke.js`
- [ ] SSH session open to VPS with monitoring script ready
- [ ] Tests scheduled during off-peak hours

### Run order

```bash
# 1. Validate endpoints
k6 run load-tests/tests/smoke.js

# 2. Progressive load (analyze between each tier)
k6 run load-tests/tests/baseline.js \
  2>&1 | tee load-tests/results/baseline-latest.log

k6 run load-tests/tests/load-2x.js \
  2>&1 | tee load-tests/results/load-2x-latest.log

k6 run load-tests/tests/load-5x.js \
  2>&1 | tee load-tests/results/load-5x-latest.log

k6 run load-tests/tests/load-10x.js \
  2>&1 | tee load-tests/results/load-10x-latest.log

# 3. Spike test
k6 run load-tests/tests/spike.js \
  2>&1 | tee load-tests/results/spike-latest.log
```

### Between each tier

1. Review k6 summary — did all thresholds pass?
2. Check server monitoring — CPU/memory/MySQL healthy?
3. Look for error patterns — 503s, nonce failures, rate limit hits?
4. If thresholds failed → stop and investigate
5. Wait 2–3 minutes for server recovery before next tier

## Server Monitoring

Run on the VPS via SSH in a separate terminal during tests:

```bash
ssh user@server 'bash -s' < load-tests/monitoring/server-checks.sh
```

Monitors: CPU load, memory, MySQL connections, slow queries, PHP-FPM workers, Nginx workers, TCP sockets, PHP session files, disk I/O.

## Post-Test Cleanup

Remove test bids, orders, and favorites created during load testing.
Replace `wp_` with your actual table prefix and `2026` with the event year.

```sql
-- Remove bids from test codes
DELETE FROM wp_2026_Bids
WHERE bidder_id IN (
  SELECT confirmation_code FROM wp_2026_Registrants
  WHERE confirmation_code IN ('00355657','00368989', /* ... all test codes ... */)
);

-- Remove favorites from test codes
DELETE FROM wp_2026_Favorites
WHERE bidder_id IN (
  SELECT confirmation_code FROM wp_2026_Registrants
  WHERE confirmation_code IN ('00355657','00368989', /* ... all test codes ... */)
);

-- Remove test orders and order items
DELETE oi FROM wp_2026_OrderItems oi
JOIN wp_2026_Orders o ON oi.order_id = o.id
WHERE o.bidder_id IN ('00355657','00368989', /* ... all test codes ... */);

DELETE FROM wp_2026_Orders
WHERE bidder_id IN ('00355657','00368989', /* ... all test codes ... */);

-- Clear rate limit transients
DELETE FROM wp_options WHERE option_name LIKE '_transient_aih_rate_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_aih_rate_%';

-- Clear plugin cache
DELETE FROM wp_options WHERE option_name LIKE '_transient_aih_cache_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_aih_cache_%';
```

## Scaling Test Codes

For 5x/10x tiers where 93 codes aren't enough for 1:1 mapping, generate synthetic registrants:

```sql
INSERT INTO wp_2026_Registrants (confirmation_code, first_name, last_name, email)
SELECT
  CONCAT('LOADTEST', LPAD(seq, 3, '0')),
  CONCAT('LoadTest', seq),
  'User',
  CONCAT('loadtest', seq, '@test.local')
FROM (
  SELECT @row := @row + 1 AS seq
  FROM information_schema.columns, (SELECT @row := 0) r
  LIMIT 500
) nums;
```

Then add the generated codes to `config/test-data.js`.

### Code sharing ratios

| Tier | Codes needed | Available | Sharing ratio |
|:-----|:-------------|:----------|:--------------|
| Smoke | 1 | 93 | 1:1 |
| Baseline | 50 | 93 | 1:1 |
| 2x | 100 | 93 | ~1.1:1 |
| 5x | 250 | 93 | ~2.7:1 |
| 10x | 500 | 93 | ~5.4:1 |

## File Reference

```
load-tests/
├── config/
│   ├── base.js              # URLs, thresholds, tier defs, PUSH_ENABLED flag
│   ├── test-data.js          # Confirmation codes (git-ignored)
│   └── test-data.example.js  # Template for test-data.js
├── lib/
│   ├── auth.js               # Two-nonce extraction + login flow
│   ├── endpoints.js          # AJAX endpoint wrappers with metric tags
│   └── helpers.js            # Think time, random selection, form encoding
├── scenarios/
│   ├── browser.js            # Unauthenticated browsing
│   ├── passive-bidder.js     # Authenticated browse + favorites + polling
│   ├── active-bidder.js      # Authenticated bidding + aggressive polling
│   └── checkout-user.js      # Won items + order creation
├── tests/
│   ├── smoke.js              # 1 VU endpoint validation
│   ├── baseline.js           # 50 VUs (historical peak)
│   ├── load-2x.js            # 100 VUs
│   ├── load-5x.js            # 250 VUs
│   ├── load-10x.js           # 500 VUs
│   └── spike.js              # 0 → 200 VUs in 30 seconds
├── monitoring/
│   └── server-checks.sh      # SSH server monitoring script
├── results/                   # Test output (git-ignored)
└── README.md                  # This document
```
