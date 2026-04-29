#!/usr/bin/env bash
# apply-agent-instructions-paths.sh
# Run this script as a BOARD USER (not agent) to wire all agent instructionsPath
# configs to their canonical .claude/agents/*.md files.
#
# Usage:
#   PAPERCLIP_API_URL=http://127.0.0.1:3100 PAPERCLIP_API_KEY=<board-token> bash apply-agent-instructions-paths.sh
#
# Created by: CTO (INTA-8 / INTA-9)

set -e

API="${PAPERCLIP_API_URL:-http://127.0.0.1:3100}"
TOKEN="${PAPERCLIP_API_KEY:?PAPERCLIP_API_KEY is required}"
BASE="/Users/rafael.silva/Documents/interazap/.claude/agents"
COMPANY_ID="8c3ad2ec-659e-4147-888a-21ce233c7b56"
CEO_ID="476bd6d0-f362-4451-bb49-9cd96c77ca4f"

patch_agent() {
  local name="$1"
  local agent_id="$2"
  local path="$3"
  local http_status

  http_status=$(curl -s -o /tmp/patch_response.json -w "%{http_code}" \
    -X PATCH "$API/api/agents/$agent_id/instructions-path" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"path\": \"$path\"}")

  if [ "$http_status" = "200" ]; then
    echo "✅ $name ($agent_id) → $path [HTTP $http_status]"
  else
    echo "❌ $name ($agent_id) → HTTP $http_status: $(cat /tmp/patch_response.json)"
  fi
}

echo "=== Patching agent instructionsPath (13 agents) ==="
echo ""

patch_agent "BACKEND"    "bd36efb8-164c-4c34-9ef4-3b4fe4f738bc" "$BASE/BACKEND.md"
patch_agent "FRONTEND"   "7efef43e-00b4-4ad7-9f12-7b3180a3e1e6" "$BASE/FRONTEND.md"
patch_agent "DBA"        "007cfab9-a449-42a5-b09b-056efad3e169" "$BASE/DBA.md"
patch_agent "DEV"        "aeeef220-e1d9-4f4e-b97a-4c8863582a58" "$BASE/DEV.md"
patch_agent "DESIGNER"   "bdedb2d5-b1a5-494f-9156-43cc8042cbb0" "$BASE/DESIGNER.md"
patch_agent "DOC"        "763df58f-9081-4f32-8b04-553be9275921" "$BASE/DOC.md"
patch_agent "GIT_COMMIT" "037418a5-32b4-40a9-aca5-9218627f5e50" "$BASE/GIT_COMMIT.md"
patch_agent "ARCHITECT"  "a40d054a-7010-4b1a-aef6-6d8da1ea523b" "$BASE/ARCHITECT.md"
patch_agent "DEBUG"      "8606f0a1-efac-4fd6-b85d-1c1c66c630bd" "$BASE/DEBUG.md"
patch_agent "REVIEWER"   "aa310b10-ef0b-4da2-a01f-3105e8846870" "$BASE/REVIEWER.md"
patch_agent "QA"         "9615ea50-4c87-4939-9fef-580720a08635" "$BASE/QA.md"
patch_agent "PM"         "a7f072b1-e35f-4c1a-a9e9-15079a2570c6" "$BASE/PM.md"
# CTO patches itself
patch_agent "CTO"        "91929077-f9e5-4c97-a80e-58136bcd8f5f" "/Users/rafael.silva/Documents/interazap/.claude/agents/ORCHESTRATOR.md"

echo ""
echo "=== Hiring GATEWAY agent ==="
echo ""

GATEWAY_RESPONSE=$(curl -s -w "\n%{http_code}" \
  -X POST "$API/api/companies/$COMPANY_ID/agents" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "GATEWAY",
    "role": "engineer",
    "title": "NestJS Gateway Specialist",
    "reportsTo": "'"$CEO_ID"'",
    "adapterType": "opencode_local",
    "adapterConfig": {
      "model": "github-copilot/claude-sonnet-4.6",
      "cwd": "/Users/rafael.silva/Documents/interazap"
    },
    "runtimeConfig": { "wakeOnDemand": true },
    "capabilities": "NestJS 11, BullMQ, Redis Streams, WebSocket, Jest, webhook processing, idempotency"
  }')

HTTP_CODE=$(echo "$GATEWAY_RESPONSE" | tail -1)
GATEWAY_BODY=$(echo "$GATEWAY_RESPONSE" | head -1)
GATEWAY_ID=$(echo "$GATEWAY_BODY" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('id',''))" 2>/dev/null)

if [ "$HTTP_CODE" = "201" ] && [ -n "$GATEWAY_ID" ]; then
  echo "✅ GATEWAY hired: $GATEWAY_ID [HTTP $HTTP_CODE]"
  echo ""
  echo "Setting instructionsPath for GATEWAY..."
  patch_agent "GATEWAY" "$GATEWAY_ID" "/Users/rafael.silva/Documents/interazap/gateway/AGENTS.md"
else
  echo "❌ GATEWAY hire failed [HTTP $HTTP_CODE]: $GATEWAY_BODY"
fi

echo ""
echo "Done. All 14 actions attempted."
