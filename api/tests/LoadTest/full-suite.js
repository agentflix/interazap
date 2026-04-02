/**
 * Full Load Test Suite
 *
 * Combines all scenarios: baseline → stress → spike
 * Run: k6 run api/tests/LoadTest/full-suite.js
 *
 * Scenarios can be run individually with:
 * k6 run --env SCENARIO=stress api/tests/LoadTest/full-suite.js
 */

import http from "k6/http";
import { check, sleep } from "k6";
import { Rate, Trend, Counter } from "k6/metrics";
import { config, thresholds, endpoints, getHeaders } from "./config.js";

// Custom metrics
const errorRate = new Rate("errors");
const ticketsLatency = new Trend("tickets_latency");
const contactsLatency = new Trend("contacts_latency");
const negotiationsLatency = new Trend("negotiations_latency");
const kanbanLatency = new Trend("kanban_latency");
const requestsTotal = new Counter("requests_total");

// Dynamic scenario selection
const selectedScenario = __ENV.SCENARIO || "all";

const scenarios = {
    baseline: {
        executor: "constant-vus",
        vus: 10,
        duration: "30s",
        gracefulStop: "5s",
        exec: "testEndpoints",
        startTime: "0s",
    },
    stress: {
        executor: "ramping-vus",
        startVUs: 0,
        stages: [
            { duration: "30s", target: 50 },
            { duration: "1m", target: 100 },
            { duration: "30s", target: 0 },
        ],
        gracefulStop: "10s",
        exec: "testEndpoints",
        startTime: "35s",
    },
    spike: {
        executor: "ramping-vus",
        startVUs: 10,
        stages: [
            { duration: "10s", target: 10 },
            { duration: "5s", target: 300 },
            { duration: "10s", target: 300 },
            { duration: "10s", target: 10 },
            { duration: "10s", target: 0 },
        ],
        gracefulStop: "10s",
        exec: "testEndpoints",
        startTime: "3m35s",
    },
};

// Build options based on selected scenario
function buildOptions() {
    let selectedScenarios = {};

    if (selectedScenario === "all") {
        selectedScenarios = scenarios;
    } else if (scenarios[selectedScenario]) {
        selectedScenarios[selectedScenario] = {
            ...scenarios[selectedScenario],
            startTime: "0s",
        };
    } else {
        console.error(`Unknown scenario: ${selectedScenario}`);
        selectedScenarios = { baseline: scenarios.baseline };
    }

    return {
        scenarios: selectedScenarios,
        thresholds: {
            ...thresholds,
            tickets_latency: ["p(95)<200"],
            contacts_latency: ["p(95)<200"],
            negotiations_latency: ["p(95)<200"],
            kanban_latency: ["p(95)<200"],
        },
    };
}

export const options = buildOptions();

export function setup() {
    console.log(`Running load test suite: ${selectedScenario}`);
    console.log(`Target URL: ${config.baseUrl}`);

    // Verify server is reachable
    const healthCheck = http.get(`${config.baseUrl}/api/health`, {
        headers: getHeaders(),
        timeout: "10s",
    });

    if (healthCheck.status !== 200) {
        console.warn(`Warning: Health check returned ${healthCheck.status}`);
    }

    return { startTime: Date.now() };
}

export function testEndpoints(data) {
    const headers = getHeaders();
    const baseUrl = config.baseUrl;

    // Test all critical endpoints
    const tests = [
        { name: "tickets", url: endpoints.tickets, trend: ticketsLatency },
        { name: "contacts", url: endpoints.contacts, trend: contactsLatency },
        {
            name: "negotiations",
            url: endpoints.negotiations,
            trend: negotiationsLatency,
        },
        { name: "kanban", url: endpoints.kanban, trend: kanbanLatency },
    ];

    for (const test of tests) {
        const res = http.get(`${baseUrl}${test.url}`, {
            headers,
            tags: { endpoint: test.name },
        });

        const success = check(res, {
            [`${test.name} status 2xx`]: (r) =>
                r.status >= 200 && r.status < 300,
            [`${test.name} < 200ms`]: (r) => r.timings.duration < 200,
        });

        errorRate.add(!success);
        test.trend.add(res.timings.duration);
        requestsTotal.add(1);

        sleep(0.05);
    }

    sleep(0.2);
}

export function teardown(data) {
    const duration = (Date.now() - data.startTime) / 1000;
    console.log(`Load test suite completed in ${duration.toFixed(2)}s`);
}

export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test: "full-suite",
        scenario: selectedScenario,
        overall: {
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
        },
        endpoints: {
            tickets_p95: data.metrics.tickets_latency?.values?.["p(95)"] || 0,
            contacts_p95: data.metrics.contacts_latency?.values?.["p(95)"] || 0,
            negotiations_p95:
                data.metrics.negotiations_latency?.values?.["p(95)"] || 0,
            kanban_p95: data.metrics.kanban_latency?.values?.["p(95)"] || 0,
        },
        thresholds: {
            passed: Object.entries(data.metrics)
                .filter(([_, m]) => m.thresholds)
                .every(([_, m]) =>
                    Object.values(m.thresholds).every((t) => t.ok),
                ),
            details: Object.entries(data.metrics)
                .filter(([_, m]) => m.thresholds)
                .reduce((acc, [name, m]) => {
                    acc[name] = Object.entries(m.thresholds).reduce(
                        (t, [k, v]) => {
                            t[k] = v.ok;
                            return t;
                        },
                        {},
                    );
                    return acc;
                }, {}),
        },
        verdict: {
            p95_under_200ms:
                (data.metrics.http_req_duration?.values?.["p(95)"] || 0) < 200,
            error_rate_under_1pct:
                (data.metrics.http_req_failed?.values?.rate || 0) < 0.01,
            all_endpoints_healthy: [
                data.metrics.tickets_latency?.values?.["p(95)"] || 0,
                data.metrics.contacts_latency?.values?.["p(95)"] || 0,
                data.metrics.negotiations_latency?.values?.["p(95)"] || 0,
                data.metrics.kanban_latency?.values?.["p(95)"] || 0,
            ].every((p95) => p95 < 200),
        },
    };

    const passed =
        summary.verdict.p95_under_200ms &&
        summary.verdict.error_rate_under_1pct &&
        summary.verdict.all_endpoints_healthy;

    console.log(passed ? "✅ LOAD TEST PASSED" : "❌ LOAD TEST FAILED");

    return {
        stdout: JSON.stringify(summary, null, 2) + "\n",
        "api/tests/LoadTest/results/full-suite.json": JSON.stringify(
            summary,
            null,
            2,
        ),
    };
}
