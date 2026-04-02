/**
 * Spike Test - 500 req/s burst
 *
 * Tests system resilience to sudden traffic spikes.
 * Run: k6 run api/tests/LoadTest/spike.js
 */

import http from "k6/http";
import { check, sleep } from "k6";
import { Rate, Trend, Counter } from "k6/metrics";
import {
    config,
    thresholds,
    endpoints,
    getHeaders,
    vuConfigs,
} from "./config.js";

// Custom metrics
const errorRate = new Rate("errors");
const spikeLatency = new Trend("spike_latency");
const recoveryLatency = new Trend("recovery_latency");
const requestsTotal = new Counter("requests_total");

export const options = {
    stages: vuConfigs.spike.stages,
    thresholds: {
        // Spike test thresholds (more relaxed during spike)
        http_req_duration: ["p(95)<500", "p(99)<1000"], // Allow degradation during spike
        http_req_failed: ["rate<0.05"], // Allow 5% error during spike
        // Recovery should be fast
        recovery_latency: ["p(95)<200"],
    },
};

// Track which phase we're in
let currentPhase = "baseline";

export function setup() {
    console.log("Starting spike test...");
    console.log(`Target URL: ${config.baseUrl}`);
    return { startTime: Date.now() };
}

export default function (data) {
    const headers = getHeaders();
    const baseUrl = config.baseUrl;
    const elapsed = (Date.now() - data.startTime) / 1000;

    // Determine phase based on elapsed time
    if (elapsed < 10) {
        currentPhase = "baseline";
    } else if (elapsed < 25) {
        currentPhase = "spike";
    } else if (elapsed < 35) {
        currentPhase = "recovery";
    } else {
        currentPhase = "cooldown";
    }

    // Test all critical endpoints
    const endpointList = [
        { name: "tickets", url: endpoints.tickets },
        { name: "contacts", url: endpoints.contacts },
        { name: "kanban", url: endpoints.kanban },
    ];

    for (const ep of endpointList) {
        const res = http.get(`${baseUrl}${ep.url}`, {
            headers,
            tags: {
                endpoint: ep.name,
                phase: currentPhase,
            },
        });

        const success = check(res, {
            [`${ep.name} status is 2xx`]: (r) =>
                r.status >= 200 && r.status < 300,
            [`${ep.name} has response`]: (r) => r.body && r.body.length > 0,
        });

        errorRate.add(!success);
        requestsTotal.add(1);

        // Track latency by phase
        if (currentPhase === "spike") {
            spikeLatency.add(res.timings.duration);
        } else if (currentPhase === "recovery") {
            recoveryLatency.add(res.timings.duration);
        }
    }

    // Minimal sleep during spike to maximize load
    sleep(currentPhase === "spike" ? 0.01 : 0.1);
}

export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log(`Spike test completed in ${duration.toFixed(2)}s`);
}

export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test: "spike",
        peak_vus: data.metrics.vus?.values?.max || 0,
        metrics: {
            // Overall metrics
            http_req_duration_avg:
                data.metrics.http_req_duration?.values?.avg || 0,
            http_req_duration_p95:
                data.metrics.http_req_duration?.values?.["p(95)"] || 0,
            http_req_duration_p99:
                data.metrics.http_req_duration?.values?.["p(99)"] || 0,
            http_req_duration_max:
                data.metrics.http_req_duration?.values?.max || 0,
            http_req_failed_rate:
                data.metrics.http_req_failed?.values?.rate || 0,
            http_reqs_count: data.metrics.http_reqs?.values?.count || 0,

            // Phase-specific metrics
            spike_latency_p95:
                data.metrics.spike_latency?.values?.["p(95)"] || 0,
            recovery_latency_p95:
                data.metrics.recovery_latency?.values?.["p(95)"] || 0,
        },
        thresholds_passed: Object.entries(data.metrics)
            .filter(([_, m]) => m.thresholds)
            .every(([_, m]) => Object.values(m.thresholds).every((t) => t.ok)),
        analysis: {
            recovered_within_target:
                (data.metrics.recovery_latency?.values?.["p(95)"] || 0) < 200,
            spike_handled:
                (data.metrics.http_req_failed?.values?.rate || 0) < 0.05,
        },
    };

    return {
        stdout: JSON.stringify(summary, null, 2) + "\n",
        "api/tests/LoadTest/results/spike.json": JSON.stringify(
            summary,
            null,
            2,
        ),
    };
}
