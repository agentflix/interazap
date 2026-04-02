# Gateway Migration Guide

This document describes the process for migrating from the old gateway to the new DDD-based gateway.

## Overview

The new gateway (v2) provides:

- Modular DDD architecture
- Multi-provider support (UazAPI, Z-API)
- Enhanced AI processing with circuit breaker
- Comprehensive test coverage (151+ tests)
- Redis Streams for async processing

## Prerequisites

- [ ] Docker and Docker Compose installed
- [ ] k6 installed for load testing (`brew install k6`)
- [ ] Redis 7.x available
- [ ] All environment variables configured

## Migration Phases

### Phase 1: Parallel Deployment

1. **Deploy new gateway on port 3001**

```bash
cd gateway
docker-compose -f docker/docker-compose.prod.yml up -d
```

2. **Verify health check**

```bash
curl http://localhost:3000/health
# Expected: {"status":"ok","timestamp":"..."}
```

3. **Run initial load test**

```bash
GATEWAY_URL=http://localhost:3000 k6 run scripts/load-test.js
```

**Success criteria:**

- p95 latency < 150ms
- Error rate < 1%
- All webhook types processed correctly

### Phase 2: Gradual Traffic Migration

1. **Configure Nginx for 10% traffic**

Edit `/etc/nginx/conf.d/gateway.conf`:

```nginx
upstream gateway_backends {
    server 127.0.0.1:3000 weight=10;  # New gateway - 10%
    server 127.0.0.1:3000 weight=90;  # Old gateway - 90%
}
```

2. **Reload Nginx**

```bash
sudo nginx -t && sudo nginx -s reload
```

3. **Monitor for 24 hours**

Check logs and metrics:

```bash
# New gateway logs
docker logs -f agentflix-gateway

# Redis stream activity
redis-cli XINFO STREAM chat.inbound_message_received
```

4. **Increase traffic incrementally**

| Day | New Gateway | Old Gateway |
| --- | ----------- | ----------- |
| 1   | 10%         | 90%         |
| 2   | 25%         | 75%         |
| 3   | 50%         | 50%         |
| 4   | 75%         | 25%         |
| 5   | 100%        | 0% (backup) |

### Phase 3: Full Cutover

1. **Route 100% traffic to new gateway**

```nginx
upstream gateway_backends {
    server 127.0.0.10:3000 weight=100;
    server 127.0.0.1:3000 backup;  # Keep as backup
}
```

2. **Monitor for 48 hours**

3. **Remove old gateway**

```bash
# Stop old gateway
docker stop agentflix-gateway-old

# Remove legacy code (optional)
rm -rf gateway/_legacy/
```

## Rollback Procedure

If issues occur during migration:

1. **Immediate rollback via Nginx**

```nginx
upstream gateway_backends {
    server 127.0.0.1:3000 weight=100;  # Old gateway
    server 127.0.0.1:3000 backup;      # New as backup
}
```

```bash
sudo nginx -s reload
```

2. **Investigate and fix**

Check new gateway logs:

```bash
docker logs agentflix-gateway 2>&1 | grep -i error
```

## Validation Checklist

### Functional Parity

- [ ] UazAPI webhooks processed correctly
- [ ] Z-API webhooks processed correctly
- [ ] Asaas payment webhooks work
- [ ] AI chat completions work
- [ ] AI embeddings work
- [ ] WebSocket connections stable
- [ ] Outbound messages delivered

### Performance

- [ ] p95 latency < 150ms
- [ ] p99 latency < 500ms
- [ ] Error rate < 1%
- [ ] WebSocket latency < 100ms

### Observability

- [ ] Health endpoint responding
- [ ] Logs structured correctly
- [ ] Metrics available
- [ ] Redis streams monitored

## Environment Variables

Required environment variables for production:

```env
# App
NODE_ENV=production
PORT=3001

# Redis
REDIS_URL=redis://redis:6379

# Database
DATABASE_URL=postgresql://...

# UazAPI
UAZAPI_BASE_URL=https://api.uazapi.io
UAZAPI_API_KEY=your-key

# Z-API
ZAPI_BASE_URL=https://api.z-api.io
ZAPI_CLIENT_TOKEN=your-token

# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Asaas
ASAAS_BASE_URL=https://api.asaas.com/v3
ASAAS_API_KEY=your-key
```

## Support

For issues during migration:

1. Check gateway logs: `docker logs agentflix-gateway`
2. Check Redis connectivity: `redis-cli ping`
3. Run health check: `curl localhost:3000/health`
4. Review this document's rollback procedure

## Post-Migration Cleanup

After 7 days of stable operation at 100%:

1. Remove old gateway container
2. Delete legacy configuration files
3. Update documentation references
4. Archive old gateway code (optional)
