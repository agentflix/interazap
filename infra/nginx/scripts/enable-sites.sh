#!/bin/bash
set -e

echo "=== Enabling Nginx sites ==="

SITES=(
    "stage.www.agentflix.com.br"
    "stage.api.agentflix.com.br"
    "api.agentflix.com.br"
    "stage.app.agentflix.com.br"
    "app.agentflix.com.br"
    "stage.gateway.agentflix.com.br"
    "gateway.agentflix.com.br"
)

for site in "${SITES[@]}"; do
    echo "Enabling $site..."
    ln -sf /etc/nginx/sites-available/$site /etc/nginx/sites-enabled/$site
done

echo "Testing nginx config..."
nginx -t

echo "Reloading nginx..."
systemctl reload nginx

echo "=== Done ==="
