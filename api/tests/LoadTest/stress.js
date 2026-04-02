/**
 * Stress Test - 100 req/s
 *
 * Tests system under high sustained load (100 requests per second).
 * Run: k6 run api/tests/LoadTest/stress.js
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
const requestsPerSecond = new Counter("requests_per_second");
const endpointLatency = new Trend("endpoint_latency");

export const options = {
    stages: vuConfigs.stress.stages,
    thresholds: {
        ...thresholds,
        // Stress test specific thresholds
        http_req_duration: ["p(95)<250", "p(99)<500"], // Slightly relaxed for stress
        http_req_failed: ["rate<0.02"], // Allow 2% error rate under stress
    },
};

// Weighted endpoint selection (simulates real traffic patterns)
const endpointWeights = [
    { endpoint: "tickets", weight: 35 }, // 35% of traffic
    { endpoint: "contacts", weight: 25 }, // 25% of traffic
    { endpoint: "negotiations", weight: 20 }, // 20% of traffic
    { endpoint: "kanban", weight: 15 }, // 15% of traffic
    { endpoint: "funnels", weight: 5 }, // 5% of traffic
];

function selectEndpoint() {
    const rand = Math.random() * 100;
    let cumulative = 0;

    for (const item of endpointWeights) {
        cumulative += item.weight;
        if (rand <= cumulative) {
            return endpoints[item.endpoint];
        }
    }

    return endpoints.tickets;
}

export default function () {
    const headers = getHeaders();
    const baseUrl = config.baseUrl;
    const endpoint = selectEndpoint();

    const startTime = Date.now();

    const res = http.get(`${baseUrl}${endpoint}`, {
        headers,
        tags: { endpoint: endpoint.split("/").pop() },
    });

    const duration = Date.now() - startTime;

    const success = check(res, {
        "status is 200": (r) => r.status === 200,
        "response time < 250ms": (r) => r.timings.duration < 250,
        "response has body": (r) => r.body && r.body.length > 0,
    });

    errorRate.add(!success);
    requestsPerSecond.add(1);
    endpointLatency.add(duration);

    // Small sleep to control request rate
    sleep(0.05);
}

export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test: "stress",
        target_rps: 100,
        metrics: {
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
            http_reqs_rate: data.metrics.http_reqs?.values?.rate || 0,
            vus_max: data.metrics.vus?.values?.max || 0,
        },
        thresholds_passed: Object.entries(data.metrics)
            .filter(([_, m]) => m.thresholds)
            .every(([_, m]) => Object.values(m.thresholds).every((t) => t.ok)),
    };

    return {
        stdout: JSON.stringify(summary, null, 2) + "\n",
        "api/tests/LoadTest/results/stress.json": JSON.stringify(
            summary,
            null,
            2,
        ),
    };
}
