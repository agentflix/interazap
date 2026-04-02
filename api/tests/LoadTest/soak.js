/**
 * Soak Test - 50 req/s for 10 minutes
 *
 * Tests system stability over extended period.
 * Detects memory leaks, connection pool exhaustion, and performance degradation.
 *
 * Run: k6 run api/tests/LoadTest/soak.js
 */

import http from "k6/http";
import { check, sleep } from "k6";
import { Rate, Trend, Counter, Gauge } from "k6/metrics";
import {
    config,
    thresholds,
    endpoints,
    getHeaders,
    vuConfigs,
} from "./config.js";

// Custom metrics for soak testing
const errorRate = new Rate("errors");
const latencyTrend = new Trend("latency_over_time");
const requestsPerMinute = new Counter("requests_per_minute");
const activeConnections = new Gauge("active_connections");

// Track latency over time windows
const latencyWindows = {
    minute_1: new Trend("latency_minute_1"),
    minute_5: new Trend("latency_minute_5"),
    minute_10: new Trend("latency_minute_10"),
};

export const options = {
    stages: vuConfigs.soak.stages,
    thresholds: {
        ...thresholds,
        // Soak test specific thresholds
        http_req_duration: ["p(95)<200", "p(99)<300"],
        http_req_failed: ["rate<0.01"], // Very strict for soak
        // Ensure no degradation over time
        latency_minute_1: ["p(95)<200"],
        latency_minute_5: ["p(95)<200"],
        latency_minute_10: ["p(95)<200"],
    },
};

export function setup() {
    console.log("Starting soak test (10 minutes)...");
    console.log(`Target URL: ${config.baseUrl}`);
    console.log(
        "This test will detect memory leaks and performance degradation.",
    );

    return {
        startTime: Date.now(),
        checkpoints: [],
    };
}

export default function (data) {
    const headers = getHeaders();
    const baseUrl = config.baseUrl;
    const elapsedMinutes = (Date.now() - data.startTime) / 60000;

    // Rotate through endpoints to simulate varied traffic
    const endpointList = Object.values(endpoints);
    const endpoint =
        endpointList[Math.floor(Math.random() * endpointList.length)];

    const res = http.get(`${baseUrl}${endpoint}`, {
        headers,
        tags: {
            endpoint: endpoint.split("/").pop(),
            minute: Math.floor(elapsedMinutes).toString(),
        },
    });

    const success = check(res, {
        "status is 200": (r) => r.status === 200,
        "response time < 200ms": (r) => r.timings.duration < 200,
        "response has body": (r) => r.body && r.body.length > 0,
    });

    errorRate.add(!success);
    latencyTrend.add(res.timings.duration);
    requestsPerMinute.add(1);
    activeConnections.add(__VU);

    // Track latency in time windows
    if (elapsedMinutes <= 1) {
        latencyWindows.minute_1.add(res.timings.duration);
    } else if (elapsedMinutes <= 5) {
        latencyWindows.minute_5.add(res.timings.duration);
    } else {
        latencyWindows.minute_10.add(res.timings.duration);
    }

    // Consistent request rate
    sleep(0.1);
}

export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log(`Soak test completed in ${(duration / 60).toFixed(2)} minutes`);
}

export function handleSummary(data) {
    // Analyze latency degradation
    const minute1P95 = data.metrics.latency_minute_1?.values?.["p(95)"] || 0;
    const minute5P95 = data.metrics.latency_minute_5?.values?.["p(95)"] || 0;
    const minute10P95 = data.metrics.latency_minute_10?.values?.["p(95)"] || 0;

    const degradationPercent =
        minute1P95 > 0
            ? (((minute10P95 - minute1P95) / minute1P95) * 100).toFixed(2)
            : 0;

    const summary = {
        timestamp: new Date().toISOString(),
        test: "soak",
        duration_minutes: 12, // 1m ramp + 10m sustain + 1m down
        target_rps: 50,
        metrics: {
            // Overall metrics
            http_req_duration_avg:
                data.metrics.http_req_duration?.values?.avg || 0,
            http_req_duration_p95:
                data.metrics.http_req_duration?.values?.["p(95)"] || 0,
            http_req_duration_p99:
                data.metrics.http_req_duration?.values?.["p(99)"] || 0,
            http_req_duration_min:
                data.metrics.http_req_duration?.values?.min || 0,
            http_req_duration_max:
                data.metrics.http_req_duration?.values?.max || 0,
            http_req_failed_rate:
                data.metrics.http_req_failed?.values?.rate || 0,
            http_reqs_count: data.metrics.http_reqs?.values?.count || 0,
            http_reqs_rate: data.metrics.http_reqs?.values?.rate || 0,
        },
        time_windows: {
            minute_1_p95: minute1P95,
            minute_5_p95: minute5P95,
            minute_10_p95: minute10P95,
            degradation_percent: parseFloat(degradationPercent),
        },
        thresholds_passed: Object.entries(data.metrics)
            .filter(([_, m]) => m.thresholds)
            .every(([_, m]) => Object.values(m.thresholds).every((t) => t.ok)),
        analysis: {
            stable_performance: Math.abs(parseFloat(degradationPercent)) < 20,
            no_memory_leaks: minute10P95 < minute1P95 * 1.5,
            consistent_errors:
                (data.metrics.http_req_failed?.values?.rate || 0) < 0.01,
        },
    };

    return {
        stdout: JSON.stringify(summary, null, 2) + "\n",
        "api/tests/LoadTest/results/soak.json": JSON.stringify(
            summary,
            null,
            2,
        ),
    };
}
