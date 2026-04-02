# Load Testing - k6

Performance load testing suite using [k6](https://k6.io/).

## Installation

```bash
# macOS
brew install k6

# Linux
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker run -i grafana/k6 run - <script.js
```

## Scripts

| Script          | Description                       |
| --------------- | --------------------------------- |
| `baseline.js`   | Baseline performance measurement  |
| `stress.js`     | Stress test (100 req/s sustained) |
| `spike.js`      | Spike test (500 req/s burst)      |
| `soak.js`       | Soak test (50 req/s for 10 min)   |
| `full-suite.js` | All scenarios combined            |

## Running Tests

```bash
# Run individual test
k6 run api/tests/LoadTest/baseline.js

# Run with environment variables
k6 run -e BASE_URL=http://localhost:8000 api/tests/LoadTest/stress.js

# Run with output to JSON
k6 run --out json=results.json api/tests/LoadTest/full-suite.js

# Run specific scenario from full suite
k6 run --env SCENARIO=spike api/tests/LoadTest/full-suite.js
```

## Thresholds

| Metric            | Target  |
| ----------------- | ------- |
| P95 Response Time | < 200ms |
| Error Rate        | < 1%    |
| Cache Hit Ratio   | > 80%   |

## Test Scenarios

### Baseline (baseline.js)

- 10 VUs for 30s
- Measures current performance

### Stress Test (stress.js)

- Ramp up to 100 req/s over 1 minute
- Sustain for 1 minute
- Ramp down

### Spike Test (spike.js)

- Sudden spike to 500 req/s
- Duration: 10 seconds
- Tests system resilience

### Soak Test (soak.js)

- 50 req/s sustained
- Duration: 10 minutes
- Detects memory leaks and degradation

## Results Interpretation

```
✓ http_req_duration..............: avg=45ms  min=12ms  med=38ms  max=180ms p(95)=120ms
✓ http_req_failed................: 0.00% ✓ 0 ✗ 5000
✓ http_reqs......................: 5000  166.66/s
```

- **http_req_duration p(95)**: Must be < 200ms ✅
- **http_req_failed**: Must be < 1% ✅
- **http_reqs**: Total requests processed
