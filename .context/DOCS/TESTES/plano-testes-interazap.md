# Plano de Testes — InteraZap

## Versão: 1.0 | Data: 2026-05-02 | Cobertura existente: 80% (frontend + gateway)

### Índice
1. [Setup e variáveis de ambiente](#1-setup-e-variáveis-de-ambiente)
2. [Testes de integração (curl)](#2-testes-de-integração-curl)
3. [Validação de contratos de API](#3-validação-de-contratos-de-api)
4. [Stress e performance (Autopilot)](#4-stress-e-performance-autopilot)
5. [Seed de dados para relatórios](#5-seed-de-dados-para-relatórios)
6. [Riscos e avisos](#6-riscos-e-avisos)
7. [Checklist de execução](#7-checklist-de-execução)

---

## 1. Setup e variáveis de ambiente

### Pré-condições
- Ambiente de teste configurado (nunca produção)
- `curl`, `jq`, `bc` instalados
- `k6` instalado (opcional, para stress profissional)
- `redis-cli` instalado (para monitoramento)
- Credenciais válidas de usuário de teste
- Canal/gate de teste configurado (ou permissão para criar)
- Agente Autopilot ativo com triggers configurados

### Configuração

Crie o arquivo `env.sh`:

```bash
#!/bin/bash
# ============================================
# CONFIGURAÇÃO GLOBAL — INTERAZAP QA
# ============================================

# URLs (altere para seu ambiente)
export BASE_URL="https://api.interazap.local"
export GATEWAY_URL="https://gateway.interazap.local"

# Headers
export CONTENT_TYPE="Content-Type: application/json"
export ACCEPT="Accept: application/json"

# Credenciais (substitua pelas do ambiente de teste)
export TEST_EMAIL="qa@interazap.test"
export TEST_PASSWORD="qa-password-123"

# Tokens (preenchidos automaticamente após login)
export TOKEN=""
export REFRESH_TOKEN=""

# Entidades (preenchidas durante os testes)
export GATE_ID=""
export ATENDIMENTO_ID=""
export AGENT_ID=""
export RUN_ID=""
export TENANT_ID=""
export USER_ID=""

# Timeouts
export CURL_TIMEOUT=30
export POLL_INTERVAL=2
export MAX_POLL_ATTEMPTS=30

# Cores
export RED='\033[0;31m'
export GREEN='\033[0;32m'
export YELLOW='\033[1;33m'
export NC='\033[0m'

# Funções utilitárias
ok() { echo -e "${GREEN}✓${NC} $1"; }
err() { echo -e "${RED}✗${NC} $1"; }
info() { echo -e "${YELLOW}ℹ${NC} $1"; }

# curl com validação de status HTTP
curl_check() {
    local method=$1 url=$2 data=$3 expected_status=${4:-200} extra_headers=$5
    local cmd="curl -s --max-time 30 -w '\n%{http_code}' -X '$method' -H '$CONTENT_TYPE' -H '$ACCEPT'"
    [ -n "$extra_headers" ] && cmd="$cmd -H '$extra_headers'"
    [ -n "$data" ] && cmd="$cmd -d '$data'"
    cmd="$cmd --max-time $CURL_TIMEOUT '$url'"
    
    local response=$(eval "$cmd" 2>&1)
    local http_status=$(echo "$response" | tail -n1)
    local body=$(echo "$response" | sed '$d')
    
    if [ "$http_status" -eq "$expected_status" ]; then
        ok "$method $url → $http_status"
        echo "$body"
        return 0
    else
        err "$method $url → $http_status (esperado $expected_status)"
        echo "$body" | jq . 2>/dev/null || echo "$body"
        return 1
    fi
}

# Como obter o token:
# 1. Execute: source env.sh
# 2. Execute: ./scripts/01-login.sh
# 3. O TOKEN será preenchido automaticamente
```

---

## 2. Testes de integração (curl)

### INT-001: Login e obtenção de token

**Pré-condição:** Usuário de teste existe no sistema

**Comando:**
```bash
source env.sh

LOGIN_PAYLOAD='{"email":"'$TEST_EMAIL'","password":"'$TEST_PASSWORD'"}'
LOGIN_RESPONSE=$(curl -s --max-time 30 -X POST -H "$CONTENT_TYPE" -d "$LOGIN_PAYLOAD" "$BASE_URL/auth/login")

# Extrair token
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // .token')
REFRESH_TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.refresh_token // .refresh_token')
TENANT_ID=$(echo "$LOGIN_RESPONSE" | jq -r '.data.tenant.id // .tenant.id')
USER_ID=$(echo "$LOGIN_RESPONSE" | jq -r '.data.user.id // .user.id')

echo "TOKEN=$TOKEN"
echo "TENANT_ID=$TENANT_ID"
```

**Critério de sucesso:**
- Status HTTP 200
- `TOKEN` preenchido com string JWT (mínimo 50 caracteres)
- `TENANT_ID` e `USER_ID` são UUIDs válidos

**Critério de falha:**
- Status 401 → credenciais incorretas
- Status 422 → payload malformado (email inválido)
- Token vazio ou null → backend não retornou token

---

### INT-002: Criar gate (canal)

**Pré-condição:** Token obtido em INT-001

**Comando:**
```bash
source env.sh

CREATE_GATE_PAYLOAD='{
    "name": "Gate QA Teste",
    "type": "whatsapp",
    "provider": "uazapi",
    "isActive": true
}'

GATE_RESPONSE=$(curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$CREATE_GATE_PAYLOAD" \
    "$BASE_URL/chat/instances")

GATE_ID=$(echo "$GATE_RESPONSE" | jq -r '.data.id // .id')
echo "GATE_ID=$GATE_ID"
```

**Critério de sucesso:**
- Status HTTP 201
- `GATE_ID` é UUID válido
- Resposta contém `name`, `type`, `isActive`

**Critério de falha:**
- Status 422 → campo obrigatório faltando ou tipo inválido
- Status 401 → token expirado ou inválido
- Status 409 → gate com mesmo nome já existe

---

### INT-003: Buscar gate por ID

**Pré-condição:** GATE_ID obtido em INT-002

**Comando:**
```bash
source env.sh

curl -s --max-time 30 -X GET \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/instances/$GATE_ID" | jq .
```

**Critério de sucesso:**
- Status HTTP 200
- `id` na resposta é igual ao `GATE_ID` enviado
- Campos obrigatórios presentes: `name`, `type`, `isActive`

**Critério de falha:**
- Status 404 → gate não encontrado (ID incorreto ou deletado)
- Status 403 → gate pertence a outro tenant

---

### INT-004: Criar atendimento via webhook inbound

**Pré-condição:** GATE_ID obtido em INT-002

**Comando:**
```bash
source env.sh

WEBHOOK_PAYLOAD='{
    "type": "message",
    "phone": "5511999999999",
    "body": "Olá, preciso de ajuda com meu pedido",
    "instance_token": "test-instance-token",
    "timestamp": '"$(date +%s)"',
    "message_id": "test-msg-'"$(date +%s)"'"
}'

curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -d "$WEBHOOK_PAYLOAD" \
    "$BASE_URL/webhooks/uazapi/instances/test-instance"

# Aguardar criação do ticket
sleep 3

# Buscar ticket criado
TICKETS=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets?phone=5511999999999&limit=1")

ATENDIMENTO_ID=$(echo "$TICKETS" | jq -r '.data[0].id // .[0].id // empty')
echo "ATENDIMENTO_ID=$ATENDIMENTO_ID"
```

**Critério de sucesso:**
- Webhook retorna status 200
- Ticket é criado e encontrado na busca por telefone
- `ATENDIMENTO_ID` é UUID válido

**Critério de falha:**
- Status 404 → rota de webhook não configurada
- Ticket não encontrado → webhook aceito mas não processado (erro no worker)
- Status 400 → payload do webhook malformado

---

### INT-005: Validar payload do atendimento

**Pré-condição:** ATENDIMENTO_ID obtido em INT-004

**Comando:**
```bash
source env.sh

TICKET_RESPONSE=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/$ATENDIMENTO_ID")

echo "$TICKET_RESPONSE" | jq '{
    id: .data.id,
    status: .data.status,
    phone: .data.contact.phone,
    channel_id: .data.channel_id,
    created_at: .data.createdAt
}'
```

**Critério de sucesso:**
- Status HTTP 200
- `id` corresponde ao `ATENDIMENTO_ID`
- `status` é um dos valores válidos: `open`, `closed`, `pending`
- `phone` contém "5511999999999"

**Critério de falha:**
- Status 404 → atendimento não existe
- Status 403 → atendimento de outro tenant
- Campos obrigatórios ausentes → contrato quebrado

---

### INT-006: Enviar mensagem no atendimento

**Pré-condição:** ATENDIMENTO_ID obtido em INT-004

**Comando:**
```bash
source env.sh

MESSAGE_PAYLOAD='{
    "content": "Olá! Como posso ajudar?",
    "type": "text"
}'

curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d "$MESSAGE_PAYLOAD" \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/messages"
```

**Critério de sucesso:**
- Status HTTP 201
- Resposta contém `id` da mensagem criada
- `content` é igual ao payload enviado

**Critério de falha:**
- Status 422 → payload inválido (campo `content` vazio)
- Status 404 → atendimento não encontrado
- Status 403 → usuário sem permissão para enviar mensagem neste atendimento

---

### INT-007: Fechar atendimento

**Pré-condição:** ATENDIMENTO_ID obtido em INT-004

**Comando:**
```bash
source env.sh

curl -s --max-time 30 -X POST \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/close"

# Validar fechamento
TICKET_STATUS=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/$ATENDIMENTO_ID" | jq -r '.data.status')

echo "Status: $TICKET_STATUS"
```

**Critério de sucesso:**
- Status HTTP 200 no POST
- Status do ticket é `closed` após fechamento

**Critério de falha:**
- Status 409 → ticket já está fechado
- Status 403 → usuário sem permissão para fechar
- Status permanece `open` → operação não persistiu

---

### INT-008: Autopilot — mensagem inbound completa

**Pré-condição:** GATE_ID obtido em INT-002, agente Autopilot configurado com trigger INBOUND_MESSAGE

**Comando:**
```bash
source env.sh

# Verificar se há agente ativo
AGENTS=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/ai/agents?isActive=true&limit=1")

AGENT_ID=$(echo "$AGENTS" | jq -r '.data[0].id // .[0].id // empty')

if [ -z "$AGENT_ID" ] || [ "$AGENT_ID" = "null" ]; then
    # Criar agente de teste
    AGENT_PAYLOAD='{
        "name": "Agente Teste QA",
        "model": "gpt-4o-mini",
        "isActive": true,
        "maxTokens": 500
    }'
    
    AGENT_RESPONSE=$(curl -s --max-time 30 -X POST \
        -H "$CONTENT_TYPE" \
        -H "Authorization: Bearer $TOKEN" \
        -d "$AGENT_PAYLOAD" \
        "$BASE_URL/ai/agents")
    
    AGENT_ID=$(echo "$AGENT_RESPONSE" | jq -r '.data.id // .id')
    
    # Configurar trigger
    TRIGGER_PAYLOAD='{
        "type": "INBOUND_MESSAGE",
        "channels": ["'$GATE_ID'"],
        "isActive": true
    }'
    
    curl -s --max-time 30 -X POST \
        -H "$CONTENT_TYPE" \
        -H "Authorization: Bearer $TOKEN" \
        -d "$TRIGGER_PAYLOAD" \
        "$BASE_URL/ai/agents/$AGENT_ID/triggers" > /dev/null
fi

echo "AGENT_ID=$AGENT_ID"

# Enviar mensagem que dispara Autopilot
AUTO_PAYLOAD='{
    "type": "message",
    "phone": "5511888888888",
    "body": "Quero saber os preços dos produtos",
    "instance_token": "autopilot-test",
    "timestamp": '"$(date +%s)"',
    "message_id": "auto-msg-'"$(date +%s)"'"
}'

curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -d "$AUTO_PAYLOAD" \
    "$BASE_URL/webhooks/uazapi/instances/autopilot-test"

# Aguardar processamento
sleep 10

# Buscar run criada
RUNS=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/ai/runs?limit=1&sort=-createdAt")

RUN_ID=$(echo "$RUNS" | jq -r '.data[0].id // .[0].id // empty')
echo "RUN_ID=$RUN_ID"

# Validar status da run
if [ -n "$RUN_ID" ] && [ "$RUN_ID" != "null" ]; then
    RUN_STATUS=$(curl -s --max-time 30 -X GET \
        -H "Authorization: Bearer $TOKEN" \
        "$BASE_URL/ai/runs/$RUN_ID" | jq -r '.data.status')
    echo "RUN_STATUS=$RUN_STATUS"
fi
```

**Critério de sucesso:**
- Webhook retorna 200
- Run é criada (`RUN_ID` preenchido)
- Status da run é `completed` ou `running` (após polling)
- Ticket contém mensagens do Autopilot (verificar via GET /tickets/{id}/messages)

**Critério de falha:**
- Run não criada → trigger não configurado ou agente inativo
- Status `failed` → erro no Gateway ou LLM
- Status `blocked` → guardrail bloqueou (pode ser comportamento esperado)
- Timeout → Autopilot não processou em tempo aceitável

---

### INT-009: Human takeover

**Pré-condição:** ATENDIMENTO_ID obtido em INT-004

**Comando:**
```bash
source env.sh

# Ativar takeover
curl -s --max-time 30 -X POST \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/takeover"

# Validar
IS_TAKEN=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/$ATENDIMENTO_ID" | jq -r '.data.is_human_handled')

echo "is_human_handled=$IS_TAKEN"

# Enviar mensagem como humano
curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"content":"Sou o atendente humano","type":"text"}' \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/messages"

# Liberar para IA
curl -s --max-time 30 -X POST \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/release-to-ai"

# Validar release
IS_AI=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/$ATENDIMENTO_ID" | jq -r '.data.is_ai_handled')

echo "is_ai_handled=$IS_AI"
```

**Critério de sucesso:**
- Takeover retorna 200
- `is_human_handled` = true após takeover
- `is_ai_handled` = true após release
- Mensagem humana foi enviada com sucesso

**Critério de falha:**
- Status 409 → outro atendente já assumiu o ticket
- Takeover não persiste → problema de concorrência
- Release falha → ticket já está com IA ou não está em takeover

---

### INT-010: Refresh token

**Pré-condição:** TOKEN e REFRESH_TOKEN obtidos em INT-001

**Comando:**
```bash
source env.sh

REFRESH_PAYLOAD='{"refresh_token":"'$REFRESH_TOKEN'"}'

NEW_TOKEN_RESPONSE=$(curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -d "$REFRESH_PAYLOAD" \
    "$BASE_URL/auth/refresh")

NEW_TOKEN=$(echo "$NEW_TOKEN_RESPONSE" | jq -r '.data.token // .token')
echo "NEW_TOKEN=${NEW_TOKEN:0:30}..."

# Validar novo token
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $NEW_TOKEN" \
    "$BASE_URL/auth/me" | jq -r '.data.email'
```

**Critério de sucesso:**
- Status HTTP 200
- `NEW_TOKEN` é string válida e diferente do TOKEN original
- `/auth/me` com novo token retorna dados do usuário

**Critério de falha:**
- Status 401 → refresh token expirado ou inválido
- Novo token igual ao antigo → backend não rotacionou token

---

### INT-011: Logout e invalidação de token

**Pré-condição:** TOKEN obtido em INT-001

**Comando:**
```bash
source env.sh

# Logout
curl -s --max-time 30 -X POST \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/auth/logout"

# Tentar usar token após logout
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/auth/me"
```

**Critério de sucesso:**
- Logout retorna 200
- Uso do token após logout retorna 401

**Critério de falha:**
- Token continua funcionando após logout → invalidação não implementada
- Status 500 → erro no servidor

---

### INT-012: Erros esperados — 404 Not Found

**Pré-condição:** Nenhuma

**Comando:**
```bash
source env.sh

# Ticket inexistente
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/chat/tickets/00000000-0000-0000-0000-000000000000"

# Gate inexistente
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/chat/instances/00000000-0000-0000-0000-000000000000"

# Agente inexistente
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/ai/agents/00000000-0000-0000-0000-000000000000"
```

**Critério de sucesso:**
- Todos retornam status 404
- Corpo da resposta contém mensagem de erro estruturada

**Critério de falha:**
- Status 200 → endpoint não valida existência do recurso
- Status 500 → erro inesperado no servidor
- Status 403 → pode indicar que o recurso existe mas pertence a outro tenant

---

### INT-013: Erros esperados — 422 Unprocessable Entity

**Pré-condição:** TOKEN obtido em INT-001

**Comando:**
```bash
source env.sh

# Email inválido
curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -d '{"email":"invalid","password":"123"}' \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/auth/login"

# Gate sem nome
curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"name":"","type":"whatsapp"}' \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/chat/instances"

# Tipo de canal inválido
curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"name":"Test","type":"invalid_type"}' \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/chat/instances"

# Mensagem sem content
curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -H "Authorization: Bearer $TOKEN" \
    -d '{"type":"text"}' \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/chat/tickets/"$ATENDIMENTO_ID"/messages"
```

**Critério de sucesso:**
- Todos retornam status 422
- Resposta contém detalhes do erro (campo inválido, regra violada)

**Critério de falha:**
- Status 200 → validação não está funcionando
- Status 500 → erro no servidor ao processar validação
- Mensagem de erro genérica → não indica qual campo está inválido

---

### INT-014: Erros esperados — 401 Unauthorized

**Pré-condição:** Nenhuma

**Comando:**
```bash
source env.sh

# Sem token
curl -s --max-time 30 -X GET \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/auth/me"

# Token inválido
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer invalid_token_xyz" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/auth/me"

# Token expirado (se houver)
curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiYWRtaW4iOnRydWUsImlhdCI6MTUxNjIzOTAyMn0=" \
    -w "\nHTTP_STATUS: %{http_code}\n" \
    "$BASE_URL/auth/me"
```

**Critério de sucesso:**
- Todos retornam status 401
- Resposta contém mensagem indicando autenticação necessária

**Critério de falha:**
- Status 200 → endpoint não exige autenticação
- Status 403 → pode indicar que a autenticação foi aceita mas autorização falhou

---

## 3. Validação de contratos de API

### CONTRACT-001: Schema do gate

**Pré-condição:** GATE_ID obtido em INT-002

**Comando:**
```bash
source env.sh

# Obter resposta
GATE_RESPONSE=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/instances/$GATE_ID")

# Validar campos obrigatórios
echo "$GATE_RESPONSE" | jq '
    def validate:
        .data as $d |
        {
            has_id: ($d.id != null),
            has_name: ($d.name != null and ($d.name | length) > 0),
            has_type: ($d.type != null),
            has_isActive: ($d.isActive != null),
            id_is_uuid: ($d.id | test("^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$"))
        };
    validate
'
```

**Critério de sucesso:**
- Todos os campos obrigatórios presentes
- `id` é UUID válido
- `name` não é vazio
- `type` é um dos valores permitidos: `whatsapp`, `telegram`, `webchat`

**Critério de falha:**
- Campo obrigatório ausente → contrato quebrado
- `id` não é UUID → problema de geração de ID
- `type` não está no enum → valor inválido

---

### CONTRACT-002: Schema do atendimento (ticket)

**Pré-condição:** ATENDIMENTO_ID obtido em INT-004

**Comando:**
```bash
source env.sh

TICKET_RESPONSE=$(curl -s --max-time 30 -X GET \
    -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/chat/tickets/$ATENDIMENTO_ID")

echo "$TICKET_RESPONSE" | jq '
    def validate:
        .data as $d |
        {
            has_id: ($d.id != null),
            has_status: ($d.status != null),
            has_channel_id: ($d.channel_id != null),
            status_valid: ($d.status | IN("open", "closed", "pending", "resolved")),
            has_created_at: ($d.createdAt != null),
            has_updated_at: ($d.updatedAt != null)
        };
    validate
'
```

**Critério de sucesso:**
- Campos obrigatórios: `id`, `status`, `channel_id`
- `status` é um dos valores válidos
- `createdAt` e `updatedAt` são timestamps válidos

**Critério de falha:**
- `contact_id` é obrigatório mas está null → contrato inconsistente
- `status` não está no enum → valor inválido retornado
- Campos de data ausentes → problema de serialização

---

### CONTRACT-003: Schema da run do Autopilot

**Pré-condição:** RUN_ID obtido em INT-008

**Comando:**
```bash
source env.sh

if [ -n "$RUN_ID" ] && [ "$RUN_ID" != "null" ]; then
    RUN_RESPONSE=$(curl -s --max-time 30 -X GET \
        -H "Authorization: Bearer $TOKEN" \
        "$BASE_URL/ai/runs/$RUN_ID")
    
    echo "$RUN_RESPONSE" | jq '
        def validate:
            .data as $d |
            {
                has_id: ($d.id != null),
                has_status: ($d.status != null),
                status_valid: ($d.status | IN("queued", "running", "completed", "blocked", "failed", "cancelled")),
                has_playbook_id: ($d.playbookId != null),
                has_tenant_id: ($d.tenantId != null),
                usage_optional: ($d.usage == null or ($d.usage.tokensInput != null and $d.usage.tokensOutput != null))
            };
        validate
    '
else
    echo "RUN_ID não disponível — pule este teste ou execute INT-008 primeiro"
fi
```

**Critério de sucesso:**
- `id`, `status`, `playbookId`, `tenantId` são obrigatórios e presentes
- `status` é um dos valores do enum
- `usage` é opcional, mas se presente contém `tokensInput` e `tokensOutput`

**Critério de falha:**
- `toolCalls` ausente quando deveria existir → run completed sem invocações
- `usage` presente mas sem campos obrigatórios → cálculo de custo quebrado
- `errorMessage` null em run failed → diagnóstico impossível

---

### CONTRACT-004: Schema do login

**Pré-condição:** Nenhuma

**Comando:**
```bash
source env.sh

LOGIN_PAYLOAD='{"email":"'$TEST_EMAIL'","password":"'$TEST_PASSWORD'"}'
LOGIN_RESPONSE=$(curl -s --max-time 30 -X POST \
    -H "$CONTENT_TYPE" \
    -d "$LOGIN_PAYLOAD" \
    "$BASE_URL/auth/login")

echo "$LOGIN_RESPONSE" | jq '
    def validate:
        .data as $d |
        {
            has_token: ($d.token != null and ($d.token | length) > 10),
            has_user: ($d.user != null),
            has_user_id: ($d.user.id != null),
            has_user_email: ($d.user.email != null),
            has_user_name: ($d.user.name != null),
            has_tenant: ($d.tenant != null),
            has_tenant_id: ($d.tenant.id != null)
        };
    validate
'
```

**Critério de sucesso:**
- `token` é string não-vazia (JWT)
- `user` contém `id`, `email`, `name`
- `tenant` contém `id`, `name`

**Critério de falha:**
- `refresh_token` ausente → funcionalidade de refresh não implementada
- `tenant` null → usuário sem tenant associado
- Campos do usuário incompletos → contrato quebrado

---

### CONTRACT-005: Detecção de campos ausentes

**Pré-condição:** ATENDIMENTO_ID ou GATE_ID preenchidos

**Comando:**
```bash
source env.sh

# Definir campos esperados
EXPECTED_TICKET_FIELDS='id,status,channel_id,contact_id,assigned_to,createdAt,updatedAt'

if [ -n "$ATENDIMENTO_ID" ]; then
    TICKET_RESPONSE=$(curl -s --max-time 30 -X GET \
        -H "Authorization: Bearer $TOKEN" \
        "$BASE_URL/chat/tickets/$ATENDIMENTO_ID")
    
    echo "=== Campos presentes no ticket ==="
    echo "$TICKET_RESPONSE" | jq '.data | keys'
    
    echo "=== Campos ausentes ==="
    for field in $(echo "$EXPECTED_TICKET_FIELDS" | tr ',' ' '); do
        present=$(echo "$TICKET_RESPONSE" | jq -r ".data.$field // empty")
        if [ -z "$present" ]; then
            echo "  AUSENTE: $field"
        fi
    done
fi
```

**Critério de sucesso:**
- Todos os campos esperados estão presentes
- Campos opcionais podem estar ausentes ou null

**Critério de falha:**
- Campo obrigatório ausente → quebra de contrato
- Campo presente mas com tipo incorreto → problemas de serialização

---

### CONTRACT-006: Detecção de nulls inesperados

**Pré-condição:** ATENDIMENTO_ID preenchido

**Comando:**
```bash
source env.sh

if [ -n "$ATENDIMENTO_ID" ]; then
    TICKET_RESPONSE=$(curl -s --max-time 30 -X GET \
        -H "Authorization: Bearer $TOKEN" \
        "$BASE_URL/chat/tickets/$ATENDIMENTO_ID")
    
    echo "=== Verificando nulls inesperados ==="
    
    # Campos que NÃO devem ser null
    REQUIRED_FIELDS="id status channel_id createdAt updatedAt"
    
    for field in $REQUIRED_FIELDS; do
        value=$(echo "$TICKET_RESPONSE" | jq -r ".data.$field // empty")
        if [ -z "$value" ] || [ "$value" = "null" ]; then
            echo "  NULL INESPERADO: $field"
        else
            echo "  OK: $field = $value"
        fi
    done
fi
```

**Critério de sucesso:**
- Campos obrigatórios não são null

**Critério de falha:**
- Campo obrigatório é null → dados incompletos no banco ou serialização falhou

---

## 4. Stress e performance (Autopilot)

### STRESS-001: Baseline (10 atendimentos simultâneos)

**Pré-condição:** Agente Autopilot ativo, ambiente isolado de teste

**Comando:**
```bash
source env.sh

echo "=== STRESS-001: Baseline (10 requests) ==="
start_time=$(date +%s)

for i in {1..10}; do
    phone="55119$(printf '%07d' $i)"
    payload='{
        "type": "message",
        "phone": "'$phone'",
        "body": "Mensagem baseline '$i'",
        "instance_token": "stress-baseline",
        "timestamp": '$(date +%s)',
        "message_id": "baseline-msg-'$i'"
    }'
    
    (
        http_status=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
            -H "$CONTENT_TYPE" \
            -d "$payload" \
            --max-time 10 \
            "$BASE_URL/webhooks/uazapi/instances/stress-baseline")
        echo "req-$i: status=$http_status"
    ) &
done

wait

end_time=$(date +%s)
echo "Duração total: $((end_time - start_time))s"

# Verificar fila Redis
redis-cli XLEN ai.run.request 2>/dev/null | xargs -I {} echo "Fila Redis: {} mensagens"
```

**Critério de sucesso:**
- 100% das requisições retornam 200
- Duração total < 30 segundos
- Fila Redis < 20 mensagens após teste

**Critério de falha:**
- Qualquer erro 5xx → instabilidade do servidor
- Fila Redis > 50 mensagens → processamento lento
- Timeout > 10s → latência excessiva

---

### STRESS-002: Pico normal (100 atendimentos = 10× baseline)

**Pré-condição:** STRESS-001 passou, ambiente suporta carga

**Comando:**
```bash
source env.sh

echo "=== STRESS-002: Pico Normal (100 requests) ==="
start_time=$(date +%s)
results_dir=$(mktemp -d)

for batch in {0..4}; do
    echo "Lote $batch/5..."
    for i in {1..20}; do
        idx=$((batch * 20 + i))
        phone="55119$(printf '%07d' $idx)"
        payload='{
            "type": "message",
            "phone": "'$phone'",
            "body": "Mensagem pico '$idx'",
            "instance_token": "stress-peak",
            "timestamp": '$(date +%s)',
            "message_id": "peak-msg-'$idx'"
        }'
        
        (
            response=$(curl -s -o /dev/null -w "%{http_code},%{time_total}" -X POST \
                -H "$CONTENT_TYPE" \
                -d "$payload" \
                --max-time 30 \
                "$BASE_URL/webhooks/uazapi/instances/stress-peak")
            echo "$response" > "$results_dir/req-$idx.txt"
        ) &
    done
    wait
    sleep 2
done

# Análise
echo "=== Resultados ==="
total=$(ls "$results_dir"/*.txt 2>/dev/null | wc -l)
success=$(grep -c ',200$' "$results_dir"/*.txt 2>/dev/null || echo 0)
echo "Total: $total | Sucesso: $success | Falhas: $((total - success))"

# Percentis de latência
echo "=== Latências ==="
cat "$results_dir"/*.txt | cut -d',' -f2 | sort -n | awk '
    {a[NR]=$1}
    END {
        print "p50: " a[int(NR*0.5)] "s"
        print "p95: " a[int(NR*0.95)] "s"
        print "p99: " a[int(NR*0.99)] "s"
    }
'

# Verificar fila
redis-cli XLEN ai.run.request 2>/dev/null | xargs -I {} echo "Fila Redis: {}"

# Cleanup
rm -rf "$results_dir"
```

**Critério de sucesso:**
- Taxa de sucesso >= 95%
- Latência p95 < 15 segundos
- Latência p99 < 30 segundos
- Fila Redis < 100 mensagens

**Critério de falha:**
- Taxa de sucesso < 90% → instabilidade grave
- Latência p95 > 30s → performance inadequada
- Erros 5xx > 5% → servidor não suporta carga

---

### STRESS-003: Pico extremo (500 atendimentos = 50× baseline)

**Pré-condição:** STRESS-002 passou, ambiente dedicado, orçamento aprovado

**Comando:**
```bash
source env.sh

echo "=== STRESS-003: Pico Extremo (500 requests) ==="
echo "AVISO: Este teste pode sobrecarregar o ambiente!"
read -p "Pressione ENTER para continuar ou CTRL+C para cancelar..."

start_time=$(date +%s)
results_dir=$(mktemp -d)

for batch in {0..9}; do
    echo "Lote $batch/10..."
    for i in {1..50}; do
        idx=$((batch * 50 + i))
        phone="55119$(printf '%07d' $idx)"
        variant=$((idx % 5))
        
        case $variant in
            0) body="Quero saber os preços" ;;
            1) body="Como faço para comprar?" ;;
            2) body="Preciso de suporte técnico" ;;
            3) body="Qual o horário de atendimento?" ;;
            4) body="Envio o comprovante" ;;
        esac
        
        payload='{
            "type": "message",
            "phone": "'$phone'",
            "body": "'$body' (extremo '$idx')",
            "instance_token": "stress-extreme",
            "timestamp": '$(date +%s)',
            "message_id": "ext-msg-'$idx'"
        }'
        
        (
            response=$(curl -s -o /dev/null -w "%{http_code},%{time_total}" -X POST \
                -H "$CONTENT_TYPE" \
                -d "$payload" \
                --max-time 60 \
                "$BASE_URL/webhooks/uazapi/instances/stress-extreme")
            echo "$response" > "$results_dir/req-$idx.txt"
        ) &
    done
    wait
    sleep 3
done

# Análise
echo "=== Resultados ==="
total=$(ls "$results_dir"/*.txt 2>/dev/null | wc -l)
success=$(grep -c ',200$' "$results_dir"/*.txt 2>/dev/null || echo 0)
errors_5xx=$(grep -c '^5' "$results_dir"/*.txt 2>/dev/null || echo 0)
errors_429=$(grep -c '^429' "$results_dir"/*.txt 2>/dev/null || echo 0)

echo "Total: $total"
echo "Sucesso (200): $success ($(echo "scale=1; $success * 100 / $total" | bc)%)"
echo "Erros 5xx: $errors_5xx"
echo "Rate limited (429): $errors_429"

# Latências
echo "=== Latências ==="
cat "$results_dir"/*.txt | cut -d',' -f2 | sort -n | awk '
    {a[NR]=$1}
    END {
        print "p50: " a[int(NR*0.5)] "s"
        print "p95: " a[int(NR*0.95)] "s"
        print "p99: " a[int(NR*0.99)] "s"
    }
'

# Health check pós-stress
echo "=== Health Check Pós-Stress ==="
curl -s -o /dev/null -w "Status: %{http_code} (tempo: %{time_total}s)\n" "$BASE_URL/health"

# Fila Redis
redis-cli XLEN ai.run.request 2>/dev/null | xargs -I {} echo "Fila Redis: {} mensagens"

# Cleanup
rm -rf "$results_dir"
```

**Critério de sucesso:**
- Taxa de sucesso >= 90%
- Erros 5xx < 2%
- Latência p95 < 30 segundos
- Health check pós-stress retorna 200
- Fila Redis estabiliza em < 200 mensagens em 5 minutos

**Critério de falha:**
- Taxa de sucesso < 85% → colapso do sistema
- Health check falha → servidor não se recuperou
- Fila Redis cresce indefinidamente → processamento insuficiente

---

### STRESS-004: Monitoramento em tempo real

**Pré-condição:** Stress test em execução

**Comando:**
```bash
source env.sh

echo "=== Monitoramento em tempo real (CTRL+C para parar) ==="
while true; do
    clear
    echo "=== $(date) ==="
    echo ""
    
    echo "--- Redis ---"
    redis-cli XLEN ai.run.request 2>/dev/null | xargs -I {} echo "Fila ai.run.request: {}"
    redis-cli INFO memory | grep used_memory_human
    echo ""
    
    echo "--- Health ---"
    curl -s -o /dev/null -w "API: %{http_code} (%{time_total}s)\n" "$BASE_URL/health"
    echo ""
    
    echo "--- Runs em andamento ---"
    curl -s --max-time 30 -H "Authorization: Bearer $TOKEN" \
        "$BASE_URL/ai/runs?status=running&limit=1" | \
        jq -r '.data | length' 2>/dev/null | xargs -I {} echo "Runs running: {}"
    echo ""
    
    sleep 3
done
```

**Critério de sucesso:**
- Monitoramento exibe dados em tempo real
- Health check consistente durante stress
- Fila Redis não cresce de forma exponencial

**Critério de falha:**
- Redis indisponível → infraestrutura sobrecarregada
- API não responde → crash ou deadlock

---

## 5. Seed de dados para relatórios

### SEED-001: Criar massa de teste

**Pré-condição:** TOKEN obtido em INT-001, ambiente de teste limpo

**Comando:**
```bash
source env.sh

N_GATES=5
M_ATENDIMENTOS=10

echo "=== SEED-001: Criando $N_GATES gates × $M_ATENDIMENTOS atendimentos ==="

# Criar gates
declare -a GATES
for i in $(seq 1 $N_GATES); do
    case $((i % 3)) in
        0) type="whatsapp" ;;
        1) type="telegram" ;;
        2) type="webchat" ;;
    esac
    
    payload='{
        "name": "Gate Relatorio '$i' ('$type')",
        "type": "'$type'",
        "provider": "uazapi",
        "isActive": true
    }'
    
    response=$(curl -s --max-time 30 -X POST -H "$CONTENT_TYPE" -H "Authorization: Bearer $TOKEN" \
        -d "$payload" "$BASE_URL/chat/instances")
    
    gate_id=$(echo "$response" | jq -r '.data.id // .id // empty')
    if [ -n "$gate_id" ]; then
        GATES+=("$gate_id")
        echo "Gate $i criado: $gate_id ($type)"
    fi
done

# Criar atendimentos
total_atendimentos=0
total_fechados=0
total_avaliacoes=0

for gate_id in "${GATES[@]}"; do
    for j in $(seq 1 $M_ATENDIMENTOS); do
        phone="55119${total_atendimentos}$(printf '%05d' $j)"
        
        payload='{
            "type": "message",
            "phone": "'$phone'",
            "body": "Mensagem seed '$total_atendimentos'",
            "instance_token": "seed-instance",
            "timestamp": '$(date +%s)',
            "message_id": "seed-msg-'$total_atendimentos'"
        }'
        
        curl -s --max-time 30 -X POST -H "$CONTENT_TYPE" -d "$payload" \
            "$BASE_URL/webhooks/uazapi/instances/seed-instance" > /dev/null
        
        sleep 1
        
        # Buscar ticket
        tickets=$(curl -s --max-time 30 -H "Authorization: Bearer $TOKEN" \
            "$BASE_URL/chat/tickets?phone=$phone&limit=1")
        ticket_id=$(echo "$tickets" | jq -r '.data[0].id // .[0].id // empty')
        
        if [ -n "$ticket_id" ]; then
            total_atendimentos=$((total_atendimentos + 1))
            
            # Ação aleatória
            action=$((RANDOM % 3))
            case $action in
                0)
                    # Fechar
                    curl -s --max-time 30 -X POST -H "Authorization: Bearer $TOKEN" \
                        "$BASE_URL/chat/tickets/$ticket_id/close" > /dev/null
                    total_fechados=$((total_fechados + 1))
                    
                    # Avaliação CSAT
                    if [ $((RANDOM % 2)) -eq 0 ]; then
                        rating=$((RANDOM % 5 + 1))
                        curl -s --max-time 30 -X POST -H "$CONTENT_TYPE" \
                            -d '{"rating":'$rating',"comment":"Avaliacao seed"}' \
                            "$BASE_URL/chat/tickets/$ticket_id/evaluations" > /dev/null
                        total_avaliacoes=$((total_avaliacoes + 1))
                    fi
                    ;;
                1)
                    # Adicionar mensagens
                    for k in {1..3}; do
                        curl -s --max-time 30 -X POST -H "$CONTENT_TYPE" -H "Authorization: Bearer $TOKEN" \
                            -d '{"content":"Resposta '$k'","type":"text"}' \
                            "$BASE_URL/chat/tickets/$ticket_id/messages" > /dev/null
                    done
                    ;;
                2)
                    # Deixar aberto
                    ;;
            esac
        fi
    done
done

echo ""
echo "=== Resumo ==="
echo "Gates criados: ${#GATES[@]}"
echo "Atendimentos criados: $total_atendimentos"
echo "Atendimentos fechados: $total_fechados"
echo "Avaliacoes CSAT: $total_avaliacoes"
```

**Critério de sucesso:**
- Todos os gates criados com sucesso
- Atendimentos distribuídos entre os gates
- Ações variadas executadas (fechar, mensagens, aberto)

**Critério de falha:**
- Gate não criado → erro de validação ou permissão
- Ticket não encontrado após webhook → processamento falhou

---

### SEED-002: Validar relatórios

**Pré-condição:** SEED-001 executado, dados processados (aguardar 30s)

**Comando:**
```bash
source env.sh

start_date=$(date +%Y-%m-%d)

echo "=== SEED-002: Validando relatórios ==="
echo "Data: $start_date"
echo ""

# Relatório de Volume
echo "--- Relatorio de Volume ---"
volume=$(curl -s --max-time 30 -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/reports/chat-volume?start_date=$start_date&end_date=$start_date")
volume_total=$(echo "$volume" | jq -r '.data.total_tickets // .total_tickets // 0')
echo "Total tickets no relatorio: $volume_total"

# Relatório CSAT
echo ""
echo "--- Relatorio CSAT ---"
csat=$(curl -s --max-time 30 -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/reports/csat-nps?start_date=$start_date&end_date=$start_date")
csat_count=$(echo "$csat" | jq -r '.data.total_evaluations // .total_evaluations // 0')
csat_avg=$(echo "$csat" | jq -r '.data.average_rating // .average_rating // 0')
echo "Total avaliacoes: $csat_count"
echo "Media: $csat_avg"

# Relatório de Performance (se disponível)
echo ""
echo "--- Relatorio de Performance ---"
perf=$(curl -s --max-time 30 -H "Authorization: Bearer $TOKEN" \
    "$BASE_URL/reports/agent-performance?start_date=$start_date&end_date=$start_date")
perf_total=$(echo "$perf" | jq -r '.data.total_tickets // .total_tickets // 0')
echo "Total tickets: $perf_total"
```

**Critério de sucesso:**
- Relatório de Volume contém todos os atendimentos criados
- Relatório CSAT contém as avaliações inseridas
- Média de rating está entre 1 e 5

**Critério de falha:**
- Relatório vazio ou zerado → agregação não funcionou
- Dados inconsistentes → cálculo de métricas quebrado
- CSAT não encontrado → avaliações não foram persistidas

---

## 6. Riscos e avisos

### 6.1 Edge cases não cobertos

| Edge Case | Por que ficou de fora | Impacto | Mitigação |
|-----------|----------------------|---------|-----------|
| **Retry cascata do Gateway** | Requer acesso ao código NestJS para observar loops silenciosos | Consumo de tokens 10× maior | Adicionar métrica `retry_count` na run |
| **Race condition: takeover durante tool call** | Requer sincronização de milissegundos entre dois clients | Resposta IA após humano assumir | Teste com `wrk` ou script paralelo |
| **Redis Stream partition loss** | Requer teste de caos (redis restart) | Runs presas em `running` | Implementar chaos engineering |
| **Guardrail bloqueando 100%** | Depende de configuração de sentiment threshold | Autopilot inutilizado | Teste de "guardrail bypass rate" |
| **Webhook duplicado (idempotência)** | Uazapi pode reenviar mesmo `message_id` | Ticket/mensagem duplicado | Reenviar mesmo payload 3× no teste |
| **LLM timeout/degraded** | Requer mock do LLM | Run falha sem erro claro | Mockar latência e resposta inválida |
| **Tenant isolation no Gateway** | Requer acesso ao código NestJS | Dados de tenant A no tenant B | Auditar chaves de cache |
| **Token expira durante poll** | Timeout entre poll attempts | Falso negativo no teste | Implementar refresh automático |

### 6.2 Dependências de produção

| Teste | Depende de | Não roda em staging sem | Mitigação |
|-------|-----------|------------------------|-----------|
| Stress com RAG | 1000+ documentos indexados | Embedding model e volume | Seed de knowledge base antes |
| Stress com LLM real | API key OpenAI/Anthropic | Configuração de API key | Mockar LLM com servidor fake |
| Webhook Uazapi real | Instância WhatsApp ativa | Instância Uazapi | Usar rota genérica ou mock |
| Email de proposta | SendGrid/SES | Serviço de email | Verificar fila de jobs |
| Billing Asaas | Conta de teste Asaas | Integração configurada | Mockar webhook Asaas |
| Push notifications | Firebase/APNs | Certificados push | Verificar job enfileirado |

### 6.3 Falsos positivos em contrato

| Endpoint | Risco | Como detectar |
|----------|-------|--------------|
| `/internal/ai/runs/{runId}` | `toolCalls[]` vazio em run completed | Validar `toolCalls.length > 0` quando playbook tem tools |
| `/chat/tickets/{id}` | `messages[]` truncado por paginação | Validar `meta.total` vs `data.length` |
| `/ai/agents/{id}` | `tools` desatualizado no cache | Incluir `updatedAt` e validar timestamp |
| `/reports/chat-volume` | Dados desatualizados (async) | Aguardar 30s e validar `lastUpdated` |
| `/webhooks/uazapi/{token}` | 200 mas não processou | Buscar ticket por phone após webhook |

### 6.4 Side effects do stress test

**ALTA probabilidade:**
- Emails reais disparados (se CRM tools ativas)
- Custo LLM (500 runs × tokens)
- Fila Redis saturada
- Logs gigantes (GB de logs)

**MÉDIA probabilidade:**
- Webhooks para Uazapi (mensagens WhatsApp reais)
- Jobs enfileirados sem fim (Horizon sobrecarregado)

**BAIXA probabilidade:**
- Push notifications para devices de teste

**CHECKLIST ANTES DE STRESS:**
- [ ] Agente usa `model=gpt-4o-mini` (mais barato)
- [ ] `maxTokens=200` (limita custo)
- [ ] Tools de email/notificação **desabilitadas**
- [ ] Instância Uazapi é **sandbox**
- [ ] Redis com `maxmemory-policy=allkeys-lru`
- [ ] Horizon rodando (`php artisan horizon:work`)
- [ ] Budget de teste aprovado ($50-100)

### 6.5 Ordem de execução

```
FASE A: SETUP (1x por sessão)
  1. source env.sh
  2. Verificar dependências
  3. Health check
  4. Login (INT-001)

FASE B: INTEGRAÇÃO
  5. Criar gate (INT-002)
  6. Criar atendimento (INT-004)
  7. Testar auth completo (INT-010, INT-011)
  8. Testar Autopilot (INT-008)
  9. Testar takeover (INT-009)
  10. Testar erros (INT-012, INT-013, INT-014)

FASE C: CONTRATOS
  11. Validar schemas (CONTRACT-001 a 006)

FASE D: SEED
  12. Criar massa (SEED-001)
  13. Validar relatórios (SEED-002)

FASE E: STRESS
  14. Baseline (STRESS-001)
  15. Pico (STRESS-002)
  16. Extremo (STRESS-003)

FASE F: CLEANUP
  17. Remover dados de teste
```

**Regras:**
- Nunca execute stress antes de seed
- Nunca execute contratos antes de integração
- Sempre execute health + login antes de tudo
- Stress baseline pode rodar antes de seed (não valida estado)

---

## 7. Checklist de execução

### 7.1 Pré-requisitos

- [ ] Ambiente de teste configurado (nunca produção)
- [ ] `curl`, `jq`, `bc` instalados
- [ ] `k6` instalado (opcional)
- [ ] `redis-cli` instalado
- [ ] Credenciais válidas no `env.sh`
- [ ] Canal/gate de teste configurado
- [ ] Agente Autopilot ativo com triggers

### 7.2 Execução rápida

```bash
# 1. Configurar
source env.sh

# 2. Login
./scripts/INT-001-login.sh

# 3. Integração
./scripts/INT-002-create-gate.sh
./scripts/INT-004-create-ticket.sh
./scripts/INT-008-autopilot.sh

# 4. Contratos
./scripts/CONTRACT-001-gate-schema.sh

# 5. Seed
./scripts/SEED-001-create-data.sh
./scripts/SEED-002-validate-reports.sh

# 6. Stress
./scripts/STRESS-001-baseline.sh
./scripts/STRESS-002-peak.sh
```

### 7.3 Checklist de GO/NO-GO

- [ ] **INT-001 a INT-014:** Todos os P0 executados sem erro
- [ ] **CONTRACT-001 a CONTRACT-006:** Nenhum endpoint com divergência de contrato
- [ ] **STRESS-001 a STRESS-003:** Autopilot estável até 100 atendimentos simultâneos (p95 < 15s)
- [ ] **SEED-001 e SEED-002:** Relatórios batem com seed data inserida
- [ ] **Regressão:** Zero regressão nos 80% de cobertura existente (frontend Angular + Gateway)
- [ ] **Side effects:** Nenhum email/webhook real disparado durante stress
- [ ] **Performance:** Health check passa antes e após stress
- [ ] **Cleanup:** Todos os dados de teste removidos (opcional, se necessário)

**Decisão:**
- **GO:** Todos os checkmarks acima estão marcados
- **NO-GO:** Qualquer checkmark não marcado → investigar antes de prosseguir

---

> **IMPORTANTE:** Todos os curls neste documento são 100% executáveis no terminal.
> Execute `source env.sh` antes de qualquer teste para carregar as variáveis.
> Substitua `BASE_URL`, `TEST_EMAIL` e `TEST_PASSWORD` pelos valores do seu ambiente.
> Nunca execute testes de stress em produção.
