# Art in Heaven — Load Testing Plan

## 1. Objective

Validate that the gallery at https://aihgallery.org/live/ can handle 2x, 5x, and 10x growth beyond the historical baseline of 50 concurrent active bidders before the live auction event.

## 2. Infrastructure Under Test

| Component | Detail |
|-----------|--------|
| **URL** | https://aihgallery.org/live/ |
| **Server** | MediaTemple / GoDaddy VPS |
| **CPU** | AMD EPYC (4 cores) |
| **RAM** | 8 GB |
| **OS** | CentOS Linux 7.9 |
| **Panel** | Plesk Obsidian v18.0.75 |
| **Stack** | WordPress + PHP + MySQL |
| **Caching** | WordPress transients (object cache + DB fallback) |

## 3. Load Tiers

| Tier | Total VUs | Browser (20%) | Passive Bidder (40%) | Active Bidder (35%) | Checkout (5%) | Duration |
|------|-----------|---------------|----------------------|---------------------|---------------|----------|
| **Smoke** | 1 | 1 | 0 | 0 | 0 | 30s |
| **Baseline** | 50 | 10 | 20 | 18 | 2 | 14 min |
| **2x** | 100 | 20 | 40 | 35 | 5 | 14 min |
| **5x** | 250 | 50 | 100 | 88 | 12 | 18 min |
| **10x** | 500 | 100 | 200 | 175 | 25 | 18 min |
| **Spike** | 200 | 0 | 0 | 200 | 0 | 4 min |

All tier tests use a ramping-VU pattern: gradual ramp-up → sustained steady state → gradual ramp-down.

The spike test simulates the last minutes of an auction: 0 → 200 active bidders in 30 seconds, sustained for 3 minutes, then rapid drop-off.

## 4. User Personas

### 4.1 Browser (Unauthenticated — 20% of VUs)

Simulates a visitor who lands on the gallery, scrolls, and occasionally searches but never logs in.

**Actions per iteration:**
1. GET gallery page (full HTML)
2. Load gallery data via AJAX
3. View 1–2 art piece details
4. 30% chance: search for a term (e.g., "landscape", "oil")
5. Idle 5–15 seconds

**Think time between actions:** 3–15 seconds

### 4.2 Passive Bidder (Authenticated — 40% of VUs)

Simulates a logged-in attendee who browses and favorites but doesn't actively bid.

**Actions per iteration:**
1. Login with confirmation code (uses `publicNonce`)
2. Load gallery data (uses `frontendNonce`)
3. Browse 2–3 art piece details
4. Toggle 1–2 favorites
5. 40% chance: search
6. Poll status 3–5 times at 10–30 second intervals
7. Check outbid notifications alongside each poll

**Think time between actions:** 2–15 seconds

### 4.3 Active Bidder (Authenticated — 35% of VUs)

Simulates a bidder actively competing in the auction.

**Actions per iteration:**
1. Login with confirmation code
2. Load gallery data
3. Browse 2–4 art pieces (studying before bidding)
4. Place 1–3 bids (view details first, then bid — whole dollar amounts $10–$500)
5. Aggressive polling: 3–5 rounds at 5–15 second intervals
6. 30% chance: search
7. 20% chance: toggle a favorite

**Think time between actions:** 1–15 seconds

### 4.4 Checkout User (Authenticated — 5% of VUs)

Simulates a winner checking out after auctions end. Only produces meaningful load when ended auctions with winning bids exist.

**Actions per iteration:**
1. Login with confirmation code
2. Fetch won items
3. If items exist: create order → get Pushpay payment link
4. If no items: idle 10–30 seconds (graceful no-op)

**Think time between actions:** 2–8 seconds

## 5. Endpoints Under Test

All AJAX endpoints go through `POST /wp-admin/admin-ajax.php`.

### Public endpoints (use `aih_public_nonce`)

| Action | Purpose | Rate Limit |
|--------|---------|------------|
| `aih_verify_code` | Login with confirmation code | 5 / 60s per IP |
| `aih_logout` | End session | — |
| `aih_check_auth` | Verify login state | — |

### Authenticated endpoints (use `aih_frontend_nonce`)

| Action | Purpose | Rate Limit |
|--------|---------|------------|
| `aih_get_gallery` | Fetch all active art pieces | — |
| `aih_get_art_details` | Single art piece with bid history | — |
| `aih_search` | Search by title/artist/medium | — |
| `aih_place_bid` | Place a bid (whole dollars, min $1) | 10 / 60s per bidder |
| `aih_toggle_favorite` | Favorite / unfavorite a piece | — |
| `aih_poll_status` | Lightweight status for up to 200 pieces | Cached 3s per bidder |
| `aih_check_outbid` | Check for outbid notifications | — |
| `aih_get_won_items` | Fetch items won at ended auctions | — |
| `aih_create_order` | Create checkout order | 5 / 60s per bidder |
| `aih_get_pushpay_link` | Get payment URL for an order | — |

## 6. Authentication Strategy

### Two-nonce system

The gallery page injects two nonces via `wp_localize_script`:
- `aihAjax.publicNonce` — generated from `aih_public_nonce` action, used for login/logout/auth-check
- `aihAjax.nonce` — generated from `aih_frontend_nonce` action, used for all other endpoints

### Login flow per virtual user

1. **GET** the gallery page HTML
2. **Extract** both nonces via regex from the `aihAjax` JavaScript object
3. **POST** `aih_verify_code` with `publicNonce` + confirmation code
4. Server regenerates the PHP session ID; k6 cookie jar captures the new `PHPSESSID` automatically
5. All subsequent requests use the `frontendNonce` + session cookie

### Rate limit mitigation

The auth endpoint is rate-limited to 5 attempts per 60 seconds **per source IP**. Since all k6 VUs share one IP, logins are staggered with a random 0–12 second delay before each VU's first login to spread requests across the ramp-up window.

## 7. Test Data

### Confirmation codes

93 unique codes extracted from the testing registration CSV (`Form-Responses-TESTING-Art-in-Heaven-Registration-2026.csv`). Stored in `config/test-data.js` (git-ignored).

| Tier | Codes needed | Available | Sharing ratio |
|------|-------------|-----------|---------------|
| Smoke | 1 | 93 | 1:1 |
| Baseline | 50 | 93 | 1:1 |
| 2x | 100 | 93 | ~1.1:1 |
| 5x | 250 | 93 | ~2.7:1 |
| 10x | 500 | 93 | ~5.4:1 |

For 5x and 10x, codes are reused round-robin. This intentionally tests PHP session contention under code sharing. For cleaner isolation, additional synthetic `LOADTEST*` registrants can be generated via SQL INSERT (see README).

### Art pieces

Art piece IDs are discovered dynamically from the `aih_get_gallery` response at the start of each VU iteration. No hard-coded IDs required.

### Bid amounts

Random whole-dollar integers between $10 and $500. The server `floor()`s all bids to whole dollars, so this matches real behavior.

## 8. Pass/Fail Thresholds

### Performance (must pass)

| Metric | Threshold |
|--------|-----------|
| Overall p95 response time | < 2 seconds |
| Overall p99 response time | < 5 seconds |
| Gallery page + data load p95 | < 3 seconds |
| Bid placement p95 | < 1.5 seconds |
| Poll status p95 | < 500 milliseconds |
| Art details p95 | < 2 seconds |
| Login p95 | < 2 seconds |
| Error rate | < 5% |

### Auto-abort (safety kill switch)

| Condition | Action |
|-----------|--------|
| Error rate > 10% for 30 consecutive seconds | Test aborts |
| p95 response time > 10 seconds for 30 consecutive seconds | Test aborts |

## 9. Expected Bottlenecks

| Bottleneck | Symptom | Expected VU Threshold |
|------------|---------|----------------------|
| **MySQL row locks** | `bid_placement` p95 spikes when many VUs bid on the same piece | ~200+ VUs |
| **PHP session file I/O** | All endpoints slow equally regardless of type | ~300+ VUs |
| **Apache MaxRequestWorkers** | HTTP 503 errors | ~150–250 VUs |
| **WordPress bootstrap** | High baseline latency across all AJAX calls | All tiers |
| **wp_options transient bloat** | Gradual response time increase over test duration | 5x+ tiers |

## 10. Execution Plan

### Pre-test checklist

- [ ] k6 installed (`brew install k6`)
- [ ] `config/test-data.js` populated with confirmation codes
- [ ] Smoke test passes: `k6 run load-tests/tests/smoke.js`
- [ ] SSH session open to VPS with monitoring script ready
- [ ] Tests scheduled during off-peak hours to minimize impact on real users

### Test execution order

Run each tier sequentially, analyzing results between runs:

```
1. k6 run load-tests/tests/smoke.js                     # validate endpoints
2. k6 run load-tests/tests/baseline.js                   # 50 VUs — historical peak
3. k6 run load-tests/tests/load-2x.js                    # 100 VUs
4. k6 run load-tests/tests/load-5x.js                    # 250 VUs
5. k6 run load-tests/tests/load-10x.js                   # 500 VUs
6. k6 run load-tests/tests/spike.js                      # auction-ending frenzy
```

To save results for post-analysis:
```bash
k6 run --out json=load-tests/results/baseline.json load-tests/tests/baseline.js \
  2>&1 | tee load-tests/results/baseline.log
```

### Server monitoring (parallel SSH session)

```bash
ssh user@server 'bash -s' < load-tests/monitoring/server-checks.sh
```

Monitors: CPU load, memory, MySQL connections, slow queries, Apache/PHP-FPM workers, TCP sockets, PHP session file count, disk I/O.

### Between each tier

1. Review k6 summary output — did all thresholds pass?
2. Check server monitoring — was CPU/memory/MySQL healthy?
3. Look for error patterns — 503s, nonce failures, rate limit hits?
4. If thresholds failed, stop and investigate before running the next tier
5. Allow 2–3 minutes between runs for server recovery

### Go / no-go decision

| Tier Result | Decision |
|-------------|----------|
| Baseline passes all thresholds | Server handles current load — proceed to 2x |
| 2x passes all thresholds | Good headroom — proceed to 5x |
| 5x passes all thresholds | Strong capacity — proceed to 10x |
| Any tier fails thresholds | Stop, diagnose bottleneck, consider optimization |
| Spike test passes | Server can handle auction-ending surge |

## 11. Post-Test Cleanup

After all testing is complete, remove test data generated during load tests:

```sql
-- Remove bids placed by test registrants
DELETE FROM wp_2026_Bids WHERE bidder_id IN (SELECT confirmation_code FROM wp_2026_Registrants WHERE confirmation_code IN (/* test codes */));

-- Remove favorites toggled during tests
DELETE FROM wp_2026_Favorites WHERE bidder_id IN (/* test codes */);

-- Remove orders and order items
DELETE oi FROM wp_2026_OrderItems oi JOIN wp_2026_Orders o ON oi.order_id = o.id WHERE o.bidder_id IN (/* test codes */);
DELETE FROM wp_2026_Orders WHERE bidder_id IN (/* test codes */);

-- Clear rate limit and cache transients
DELETE FROM wp_options WHERE option_name LIKE '_transient_aih_rate_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_aih_rate_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_aih_cache_%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_aih_cache_%';
```

See `load-tests/README.md` for the complete cleanup SQL with all 93 test codes.

## 12. File Reference

```
load-tests/
├── config/
│   ├── base.js              # URLs, thresholds, tier definitions
│   ├── test-data.js          # 93 confirmation codes (git-ignored)
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
├── LOAD-TEST-PLAN.md          # This document
└── README.md                  # Quick-start guide
```
