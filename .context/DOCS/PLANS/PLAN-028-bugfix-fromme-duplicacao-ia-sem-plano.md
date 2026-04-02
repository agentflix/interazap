# PLAN-028 — Bugfix: fromMe Duplicação + IA sem Plano

## Metadados

- **ID:** PLAN-028
- **Módulo:** Chat + Platform
- **Camadas impactadas:** API (Backend apenas)
- **Fase atual:** EXECUTION
- **Tipo:** bugfix
- **Severidade:** high

---

## Contexto

Dois bugs independentes identificados no fluxo de webhook do WhatsApp:

### Bug 1 — IA responde sem plano vinculado

**Arquivo:** `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php`

`isAiEnabled()` retorna `true` quando nenhum plano é encontrado (sem fatura ativa). Assim, contas sem billing ativo recebem resposta da IA em vez do chatbot — comportamento incorreto.

```php
if (! $plan) {
    return true;  // ← BUG: deveria ser false
}
```

**Causa raiz:** Default permissivo — sem plano, IA está habilitada.

### Bug 2 — Mensagem `fromMe: true` duplicada

**Arquivo:** `api/src/Domain/Chat/Actions/ChatMessageActions.php`

Ao criar uma mensagem com `direction='outgoing'`, o método `create()` chama `sendToGateway()` sem verificar a **origem** da mensagem (`source`). Mensagens vindas de webhook (digitadas em outro dispositivo) têm `source='webhook'` mas são reenviadas para o WhatsApp, causando duplicação.

```php
// Linha ~376
if ($dto->direction === 'outgoing' && $dto->type !== 'internal_note') {
    // ← Falta verificar $dto->source !== SOURCE_WEBHOOK
    $this->sendToGateway($message, $ticket);
}
```

**O campo `ChatMessageDTO::SOURCE_WEBHOOK` já existe**, mas nunca é verificado antes do envio.

---

## Dentro do Escopo

- Corrigir `isAiEnabled()`: default `false` quando sem plano
- Corrigir `sendToGateway()`: guard `source !== SOURCE_WEBHOOK` 
- Atualizar/adicionar testes para ambos os casos

## Fora do Escopo

- Qualquer mudança em gateway ou frontend
- Migrações de banco de dados
- Alterações de rota ou contrato de API
- Outros métodos de plan enforcement (`canCreateUser`, `canCreateInstance`)

---

## Arquivos Impactados

| Arquivo | Tipo de alteração |
|---------|------------------|
| `api/src/Domain/Platform/Services/PlatformPlanEnforcementService.php` | Bugfix (1 linha) |
| `api/src/Domain/Chat/Actions/ChatMessageActions.php` | Bugfix (1 condição) |
| `api/tests/Feature/ChatMessageActionsTest.php` | Adicionar teste |
| `api/tests/Feature/PlatformPlanEnforcementServiceTest.php` (ou similar) | Adicionar teste |

---

## Correções Propostas

### Fix 1 — `PlatformPlanEnforcementService::isAiEnabled()`

```php
// ANTES
if (! $plan) {
    return true;
}

// DEPOIS
if (! $plan) {
    return false;  // Sem plano → sem IA
}
```

### Fix 2 — `ChatMessageActions::create()`

```php
// ANTES
if ($dto->direction === 'outgoing' && $dto->type !== 'internal_note') {
    $this->sendToGateway($message, $ticket);
}

// DEPOIS
if ($dto->direction === 'outgoing'
    && $dto->type !== 'internal_note'
    && $dto->source !== ChatMessageDTO::SOURCE_WEBHOOK  // ← GUARD adicionado
) {
    $this->sendToGateway($message, $ticket);
}
```

---

## Riscos

| Risco | Mitigação |
|-------|-----------|
| Contas sem plano passam a usar chatbot em vez de IA | Comportamento correto — sem billing, sem IA |
| Mensagens de agente/bot (`source=agent/bot`) continuam sendo enviadas | Guard filtra apenas `source=webhook` |
| Regressão em testes existentes | Revisar todos os testes de `ChatMessageActions` e `PlanEnforcement` |

---

## Estimativa

**Esforço:** XS (2 linhas de prod + testes)
**Risco:** Baixo
