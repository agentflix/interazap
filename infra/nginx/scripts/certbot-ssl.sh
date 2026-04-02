#!/bin/bash
set -e

echo "=== Certbot SSL for AgentFlix ==="

certbot --nginx \
  -d www.agentflix.com.br \
  -d stage.www.agentflix.com.br \
  -d stage.api.agentflix.com.br \
  -d api.agentflix.com.br \
  -d stage.app.agentflix.com.br \
  -d app.agentflix.com.br \
  -d stage.gateway.agentflix.com.br \
  -d gateway.agentflix.com.br \
  --non-interactive \
  --agree-tos \
  --email admin@agentflix.com.br \
  --redirect

echo "=== SSL certificates deployed ==="
