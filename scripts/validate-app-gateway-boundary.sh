#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

matches="$(
  rg -n "environment\\.gateway\\.url|gateway\\.url" \
    "$ROOT_DIR/app/src/app" \
    -g '!**/core/services/realtime.service.ts' \
    || true
)"

if [[ -n "$matches" ]]; then
  printf 'Uso direto de environment.gateway.url fora do realtime permitido:\n%s\n' "$matches" >&2
  exit 1
fi

rg -n "http.*environment\\.gateway\\.url|environment\\.gateway\\.url.*http" "$ROOT_DIR/app/src/app" && {
  printf 'HTTP direto App -> Gateway detectado.\n' >&2
  exit 1
} || true
