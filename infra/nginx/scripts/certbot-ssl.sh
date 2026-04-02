#!/bin/bash
set -e

echo "=== Certbot SSL for InteraZap ==="

certbot --nginx \
  -d www.interazap.com.br \
  -d stage.www.interazap.com.br \
  -d stage.api.interazap.com.br \
  -d api.interazap.com.br \
  -d stage.app.interazap.com.br \
  -d app.interazap.com.br \
  -d stage.gateway.interazap.com.br \
  -d gateway.interazap.com.br \
  --non-interactive \
  --agree-tos \
  --email admin@interazap.com.br \
  --redirect

echo "=== SSL certificates deployed ==="
