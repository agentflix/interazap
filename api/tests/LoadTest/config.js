/**
 * Load Test Configuration
 *
 * Shared configuration for all k6 load test scripts.
 */

// Environment configuration
export const config = {
    // Base URL - override with -e BASE_URL=http://your-server
    baseUrl: __ENV.BASE_URL || "http://localhost:8000",

    // API prefix
    apiPrefix: "/api",

    // Auth token - override with -e AUTH_TOKEN=your-token
    authToken: __ENV.AUTH_TOKEN || "test-token-for-load-testing",

    // Tenant ID for multi-tenant testing
    tenantId: __ENV.TENANT_ID || "1",
};

// Performance thresholds (must match spec.md targets)
export const thresholds = {
    // P95 response time must be under 200ms
    http_req_duration: ["p(95)<200"],

    // Error rate must be under 1%
    http_req_failed: ["rate<0.01"],

    // 95% of requests should complete under 200ms
    "http_req_duration{endpoint:tickets}": ["p(95)<200"],
    "http_req_duration{endpoint:contacts}": ["p(95)<200"],
    "http_req_duration{endpoint:negotiations}": ["p(95)<200"],
    "http_req_duration{endpoint:kanban}": ["p(95)<200"],
};

// Endpoints to test (from task.md Phase 1.4)
export const endpoints = {
    tickets: "/api/chat/tickets",
    contacts: "/api/crm/contacts",
    negotiations: "/api/crm/negotiations",
    kanban: "/api/crm/negotiations/kanban",
    funnels: "/api/crm/funnels",
    quickAnswers: "/api/chat/quick-answers",
};

// Common headers
export function getHeaders() {
    return {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${config.authToken}`,
        "X-Tenant-ID": config.tenantId,
    };
}

// VU (Virtual User) configurations
export const vuConfigs = {
    baseline: {
        vus: 10,
        duration: "30s",
    },
    stress: {
        stages: [
            { duration: "30s", target: 50 }, // Ramp up
            { duration: "1m", target: 100 }, // Stay at 100 req/s
            { duration: "30s", target: 0 }, // Ramp down
        ],
    },
    spike: {
        stages: [
            { duration: "10s", target: 10 }, // Baseline
            { duration: "5s", target: 500 }, // Spike!
            { duration: "10s", target: 500 }, // Hold spike
            { duration: "10s", target: 10 }, // Recovery
            { duration: "10s", target: 0 }, // Cool down
        ],
    },
    soak: {
        stages: [
            { duration: "1m", target: 50 }, // Ramp up
            { duration: "10m", target: 50 }, // Sustain
            { duration: "1m", target: 0 }, // Ramp down
        ],
    },
};
