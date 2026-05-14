#!/bin/bash
set -e

echo "=== Enabling Nginx sites ==="

SITES=(
    "stage.www.interazap.com.br"
    "stage.api.interazap.com.br"
    "api.interazap.com.br"
    "stage.app.interazap.com.br"
    "app.interazap.com.br"
    "stage.gateway.interazap.com.br"
    "gateway.interazap.com.br"
    "clinicas.interazap.com.br"
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
