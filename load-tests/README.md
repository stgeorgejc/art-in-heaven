# Art in Heaven — Load Testing Suite

k6-based load tests for the gallery at https://aihgallery.org/gallery/.

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

The file is git-ignored. It should export `TEST_CODES` (array of confirmation
code strings) and optionally `ART_PIECE_IDS`.

## Running Tests

All commands are run from the project root.

### Smoke test (validate endpoints)

```bash
k6 run load-tests/tests/smoke.js
```

### Tier tests (progressive load)

```bash
k6 run load-tests/tests/baseline.js   # 50 VUs  (historical peak)
k6 run load-tests/tests/load-2x.js    # 100 VUs
k6 run load-tests/tests/load-5x.js    # 250 VUs
k6 run load-tests/tests/load-10x.js   # 500 VUs
```

### Spike test (auction-ending frenzy)

```bash
k6 run load-tests/tests/spike.js      # 0 → 200 VUs in 30s
```

### Save results to JSON

```bash
k6 run --out json=results.json load-tests/tests/load-2x.js
```

### Override target URL

```bash
k6 run -e BASE_URL=https://staging.example.com load-tests/tests/smoke.js
```

## Server Monitoring

Run on the VPS via SSH in a separate terminal during tests:

```bash
ssh user@server 'bash -s' < load-tests/monitoring/server-checks.sh
```

## Test Architecture

### Personas (VU distribution)

| Persona | % | Behavior |
|---------|---|----------|
| Browser | 20% | Unauthenticated: page load, search, view details |
| Passive Bidder | 40% | Authenticated: browse, favorite, poll status |
| Active Bidder | 35% | Authenticated: view details, place bids, aggressive polling |
| Checkout User | 5% | Authenticated: won items, create order, payment link |

### Tier breakdown

| Tier | Browser | Passive | Active | Checkout | Total |
|------|---------|---------|--------|----------|-------|
| Baseline | 10 | 20 | 18 | 2 | 50 |
| 2x | 20 | 40 | 35 | 5 | 100 |
| 5x | 50 | 100 | 88 | 12 | 250 |
| 10x | 100 | 200 | 175 | 25 | 500 |

### Pass/fail thresholds

- Gallery load: p95 < 3s
- Bid placement: p95 < 1.5s
- Poll status: p95 < 500ms
- Overall: p95 < 2s, p99 < 5s, error rate < 5%

### Auto-abort (safety)

Tests automatically stop if:
- Error rate exceeds 10% for 30 consecutive seconds
- p95 response time exceeds 10s for 30 consecutive seconds

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

## Scaling Beyond 93 Test Codes

For 5x/10x tiers with unique codes per VU, generate synthetic registrants:

```sql
-- Generate LOADTEST codes (run via phpMyAdmin or MySQL CLI)
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
