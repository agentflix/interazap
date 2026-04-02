/**
 * k6 Load Test Script for InteraZap Gateway
 *
 * Simulates webhook traffic to test gateway performance.
 *
 * Usage:
 *   k6 run scripts/load-test.js
 *   k6 run --vus 50 --duration 5m scripts/load-test.js
 *
 * Environment variables:
 *   GATEWAY_URL - Gateway base URL (default: http://localhost:3000)
 *   WEBHOOK_TOKEN - Test webhook token
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

// Custom metrics
const webhookSuccessRate = new Rate('webhook_success_rate');
const webhookDuration = new Trend('webhook_duration_ms');

// Test configuration
export const options = {
  stages: [
    { duration: '30s', target: 10 }, // Ramp up to 10 VUs
    { duration: '1m', target: 50 }, // Ramp up to 50 VUs
    { duration: '2m', target: 50 }, // Stay at 50 VUs
    { duration: '30s', target: 100 }, // Ramp up to 100 VUs
    { duration: '1m', target: 100 }, // Stay at 100 VUs
    { duration: '30s', target: 0 }, // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<150'], // 95% of requests under 150ms
    http_req_failed: ['rate<0.01'], // Less than 1% failures
    webhook_success_rate: ['rate>0.99'],
  },
};

const GATEWAY_URL = __ENV.GATEWAY_URL || 'http://localhost:3000';
const WEBHOOK_TOKEN = __ENV.WEBHOOK_TOKEN || 'test-webhook-token';

// UazAPI webhook payload
function generateUazapiPayload() {
  const messageId = `msg_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  return {
    event: 'message.received',
    data: {
      key: {
        remoteJid: `5511${Math.floor(900000000 + Math.random() * 99999999)}@s.whatsapp.net`,
        fromMe: false,
        id: messageId,
      },
      message: {
        conversation: `Test message ${Date.now()}`,
      },
      messageTimestamp: Math.floor(Date.now() / 1000),
      pushName: 'Load Test User',
    },
  };
}

// Z-API webhook payload
function generateZapiPayload() {
  const messageId = `ZAPI_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  return {
    phone: `5511${Math.floor(900000000 + Math.random() * 99999999)}`,
    messageId: messageId,
    text: {
      message: `Z-API test message ${Date.now()}`,
    },
    isFromMe: false,
    momment: Date.now(),
    status: 'RECEIVED',
    senderName: 'Z-API Load Test',
    type: 'ReceivedCallback',
  };
}

// Billing webhook payload (Asaas)
function generateAsaasPayload() {
  const paymentId = `pay_${Date.now()}`;
  return {
    event: 'PAYMENT_RECEIVED',
    payment: {
      id: paymentId,
      customer: `cus_${Math.random().toString(36).substr(2, 9)}`,
      value: Math.floor(Math.random() * 1000) + 10,
      netValue: Math.floor(Math.random() * 950) + 10,
      billingType: 'PIX',
      status: 'RECEIVED',
      dueDate: new Date().toISOString().split('T')[0],
      paymentDate: new Date().toISOString().split('T')[0],
    },
  };
}

export default function () {
  const scenarios = [
    { provider: 'uazapi', payload: generateUazapiPayload },
    { provider: 'zapi', payload: generateZapiPayload },
  ];

  // Pick random scenario
  const scenario = scenarios[Math.floor(Math.random() * scenarios.length)];
  const payload = scenario.payload();

  const url = `${GATEWAY_URL}/webhooks/${scenario.provider}/instances/${WEBHOOK_TOKEN}`;
  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    timeout: '10s',
  };

  const startTime = Date.now();
  const response = http.post(url, JSON.stringify(payload), params);
  const duration = Date.now() - startTime;

  // Record custom metrics
  webhookDuration.add(duration);

  const success = check(response, {
    'status is 200 or 202': (r) => r.status === 200 || r.status === 202,
    'response time < 150ms': (r) => r.timings.duration < 150,
    'no server errors': (r) => r.status < 500,
  });

  webhookSuccessRate.add(success);

  // Small sleep to simulate realistic traffic
  sleep(Math.random() * 0.1);
}

// Separate scenario for billing webhooks
export function billingWebhook() {
  const payload = generateAsaasPayload();
  const url = `${GATEWAY_URL}/webhooks/billing/asaas/${WEBHOOK_TOKEN}`;

  const params = {
    headers: {
      'Content-Type': 'application/json',
    },
    timeout: '10s',
  };

  const response = http.post(url, JSON.stringify(payload), params);

  check(response, {
    'billing status is 200': (r) => r.status === 200 || r.status === 202,
    'billing response time < 200ms': (r) => r.timings.duration < 200,
  });

  sleep(Math.random() * 0.5);
}

export function handleSummary(data) {
  return {
    stdout: textSummary(data, { indent: ' ', enableColors: true }),
    'scripts/load-test-results.json': JSON.stringify(data, null, 2),
  };
}

function textSummary(data, options) {
  const indent = options.indent || '';
  let summary = '\n';
  summary += `${indent}╔══════════════════════════════════════════════════════════════╗\n`;
  summary += `${indent}║           InteraZap Gateway Load Test Summary                ║\n`;
  summary += `${indent}╠══════════════════════════════════════════════════════════════╣\n`;

  const metrics = data.metrics;

  if (metrics.http_req_duration) {
    const p95 = metrics.http_req_duration.values['p(95)'];
    const avg = metrics.http_req_duration.values.avg;
    summary += `${indent}║  HTTP Request Duration                                       ║\n`;
    summary += `${indent}║    - Average: ${avg.toFixed(2)}ms                                        ║\n`;
    summary += `${indent}║    - P95: ${p95.toFixed(2)}ms ${p95 < 150 ? '✅' : '❌'}                                          ║\n`;
  }

  if (metrics.http_reqs) {
    const total = metrics.http_reqs.values.count;
    const rate = metrics.http_reqs.values.rate;
    summary += `${indent}║  Total Requests: ${total} (${rate.toFixed(2)}/s)                          ║\n`;
  }

  if (metrics.http_req_failed) {
    const failRate = metrics.http_req_failed.values.rate * 100;
    summary += `${indent}║  Failure Rate: ${failRate.toFixed(2)}% ${failRate < 1 ? '✅' : '❌'}                                    ║\n`;
  }

  summary += `${indent}╚══════════════════════════════════════════════════════════════╝\n`;

  return summary;
}
