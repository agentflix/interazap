#!/usr/bin/env bash

# Trilha E2E do módulo Autopilot — InteraZap
# Uso: bash tests/E2E/run-e2e.sh [--teardown-only]
#
# Requisitos:
#   - Executar a partir da raiz de api/
#   - APP_ENV=local ou staging (nunca production)
#   - Banco de dados acessível com migrations rodadas

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"

cd "$ROOT_DIR"

# Guardrail: bloqueia execução em production
APP_ENV_VALUE=$(php artisan tinker --execute="echo app()->environment();" 2>/dev/null | tr -d '[:space:]')
if [ "$APP_ENV_VALUE" = "production" ]; then
  echo "❌ ERRO: trilha E2E não pode ser executada em production."
  exit 1
fi

if [ "${1:-}" = "--teardown-only" ]; then
  echo "→ Executando apenas teardown..."
  php artisan tinker --execute="require base_path('tests/E2E/Autopilot/teardown.php');"
  exit 0
fi

echo ""
echo "▶ Iniciando trilha E2E — Autopilot Module"
echo "  Env: $APP_ENV_VALUE"
echo ""

php artisan tinker --execute="require base_path('tests/E2E/Autopilot/run.php');"
