/**
 * Baseline Performance Test
 *
 * Measures current performance baseline with moderate load.
 * Run: k6 run api/tests/LoadTest/baseline.js
 */

import http from "k6/http";
import { check, sleep } from "k6";
import { Rate, Trend } from "k6/metrics";
import {
    config,
    thresholds,
    endpoints,
    getHeaders,
    vuConfigs,
} from "./config.js";

// Custom metrics
const errorRate = new Rate("errors");
const ticketsDuration = new Trend("tickets_duration");
const contactsDuration = new Trend("contacts_duration");
const negotiationsDuration = new Trend("negotiations_duration");

export const options = {
    vus: vuConfigs.baseline.vus,
    duration: vuConfigs.baseline.duration,
    thresholds: thresholds,
};

export default function () {
    const headers = getHeaders();
    const baseUrl = config.baseUrl;

    // Test GET /api/chat/tickets
    const ticketsRes = http.get(`${baseUrl}${endpoints.tickets}`, {
        headers,
        tags: { endpoint: "tickets" },
    });

    check(ticketsRes, {
        "tickets status is 200": (r) => r.status === 200,
        "tickets response time < 200ms": (r) => r.timings.duration < 200,
    });

    errorRate.add(ticketsRes.status !== 200);
    ticketsDuration.add(ticketsRes.timings.duration);

    sleep(0.1);

    // Test GET /api/crm/contacts
    const contactsRes = http.get(`${baseUrl}${endpoints.contacts}`, {
        headers,
        tags: { endpoint: "contacts" },
    });

    check(contactsRes, {
        "contacts status is 200": (r) => r.status === 200,
        "contacts response time < 200ms": (r) => r.timings.duration < 200,
    });

    errorRate.add(contactsRes.status !== 200);
    contactsDuration.add(contactsRes.timings.duration);

    sleep(0.1);

    // Test GET /api/crm/negotiations
    const negotiationsRes = http.get(`${baseUrl}${endpoints.negotiations}`, {
        headers,
        tags: { endpoint: "negotiations" },
    });

    check(negotiationsRes, {
        "negotiations status is 200": (r) => r.status === 200,
        "negotiations response time < 200ms": (r) => r.timings.duration < 200,
    });

    errorRate.add(negotiationsRes.status !== 200);
    negotiationsDuration.add(negotiationsRes.timings.duration);

    sleep(0.1);

    // Test GET /api/crm/negotiations/kanban
    const kanbanRes = http.get(`${baseUrl}${endpoints.kanban}`, {
        headers,
        tags: { endpoint: "kanban" },
    });

    check(kanbanRes, {
        "kanban status is 200": (r) => r.status === 200,
        "kanban response time < 200ms": (r) => r.timings.duration < 200,
    });

    errorRate.add(kanbanRes.status !== 200);

    sleep(0.5);
}

export function handleSummary(data) {
    const summary = {
        timestamp: new Date().toISOString(),
        test: "baseline",
        metrics: {
            http_req_duration_p95:
                data.metrics.http_req_duration?.values?.["p(95)"] || 0,
            http_req_failed_rate:
                data.metrics.http_req_failed?.values?.rate || 0,
            http_reqs_count: data.metrics.http_reqs?.values?.count || 0,
            http_reqs_rate: data.metrics.http_reqs?.values?.rate || 0,
        },
        endpoints: {
            tickets_p95: data.metrics.tickets_duration?.values?.["p(95)"] || 0,
            contacts_p95:
                data.metrics.contacts_duration?.values?.["p(95)"] || 0,
            negotiations_p95:
                data.metrics.negotiations_duration?.values?.["p(95)"] || 0,
        },
        thresholds_passed: Object.entries(data.metrics)
            .filter(([_, m]) => m.thresholds)
            .every(([_, m]) => Object.values(m.thresholds).every((t) => t.ok)),
    };

    return {
        stdout: JSON.stringify(summary, null, 2) + "\n",
        "api/tests/LoadTest/results/baseline.json": JSON.stringify(
            summary,
            null,
            2,
        ),
    };
}
