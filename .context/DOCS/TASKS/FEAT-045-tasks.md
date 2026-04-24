# Tasks: Correção de Roteamento de Agentes AI

> Decomposição T.A.C.E das tasks — FEAT-045

---

## Feature: AI Agent Routing Fix

**ID:** FEAT-045
**Bounded Context:** AI
**Total Tasks:** 8
**Concluídas:** 0

---

## 📋 Sumário das Tasks

| Task       | Camada  | Título                                                                   | Status      |
| ---------- | ------- | ------------------------------------------------------------------------ | ----------- |
| TASK-045.1 | Backend | Aceitar nome ou UUID no endpoint de delegação (HTTP validation)          | ⏳ Pendente |
| TASK-045.2 | Backend | Teste: delegação por nome de agente                                      | ⏳ Pendente |
| TASK-045.3 | Backend | Teste: nome inválido retorna 422 com mensagem clara                      | ⏳ Pendente |
| TASK-045.4 | Backend | Log estruturado quando regra de delegação não existe                     | ⏳ Pendente |
| TASK-045.5 | Backend | Teste: 422 com IDs source/target quando sem regra                        | ⏳ Pendente |
| TASK-045.6 | Backend | Endpoint `GET /internal/ai/agents/available` com agentes delegáveis      | ⏳ Pendente |
| TASK-045.7 | Backend | Teste: endpoint retorna apenas agentes com regra ativa                   | ⏳ Pendente |
| TASK-045.8 | Backend | Adicionar relação `targetAgent()` em `AiAgentDelegation` se não existir  | ⏳ Pendente |

---

## 🔄 FASE BACKEND — Execution (BACKEND agent)

---

### TASK-045.1 ⏳ — Aceitar nome ou UUID no endpoint de delegação

**T — Tarefa:**
Remover a validação `uuid` obrigatória do campo `target_agent_id` em `InternalAiController::delegateRun` e normalizar internamente — se for UUID usa direto, se for string resolve por `LOWER(name)` dentro do tenant.

**A — Arquivo:**
- **Modificar:** `api/src/Domain/Ai/Http/Controllers/InternalAiController.php`
  - Método: `delegateRun` (linhas ~207-232)

**C — Comportamento:**
```
ANTES:
- 'target_agent_id' => ['required', 'uuid']
- Qualquer valor que não seja UUID válido → retorna 422 imediatamente
- Se o modelo de IA envia nome ("Agente Financeiro"), a delegação falha antes de chegar ao service

DEPOIS:
- 'target_agent_id' => ['required', 'string', 'min:1']
- Controller resolve: se for UUID → find(); se não → whereRaw('LOWER(name) = ?', [...])
- Se não encontrar por nenhum dos dois → retorna 422 com "Target agent not found or inactive: {valor}"
- $targetAgentId interno sempre usa o UUID do model resolvido
```

**E — Evidência:**
- [ ] `./vendor/bin/pest tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php` verde sem regressões
- [ ] Teste TASK-045.2 passando (delegação por nome retorna 202)
- [ ] Teste TASK-045.3 passando (nome inexistente retorna 422)
- [ ] Delegação por UUID (comportamento existente) ainda retorna 202

**Dependências:** TASK-045.2, TASK-045.3 (escrever testes antes de implementar)

**Status:** ⏳ Pendente

---

### TASK-045.2 ⏳ — Teste: delegação por nome de agente

**T — Tarefa:**
Escrever teste Pest que chama `POST /api/internal/ai/runs/delegate` com `target_agent_id` sendo o **nome** do agente (string), e confirma que retorna 202 com run filha criada.

**A — Arquivo:**
- **Modificar:** `api/tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php`

**C — Comportamento:**
```
ANTES:
- Nenhum teste cobre delegação por nome de agente

DEPOIS:
- Teste `test_delegate_endpoint_accepts_agent_name_instead_of_uuid` existe e passa
- Cria sourceAgent, targetAgent (name='Agente Financeiro'), regra de delegação e parentRun
- Envia target_agent_id = 'Agente Financeiro'
- Espera status 202 e data.status = 'queued'
- Mock do Redis confirma que xadd recebe agent_id = UUID real do targetAgent
```

**E — Evidência:**
- [ ] Teste existe no arquivo
- [ ] `./vendor/bin/pest --filter="accepts_agent_name_instead_of_uuid"` retorna FAIL antes da implementação (TASK-045.1)
- [ ] `./vendor/bin/pest --filter="accepts_agent_name_instead_of_uuid"` retorna PASS após implementação

**Dependências:** Nenhuma (escrever primeiro)

**Status:** ⏳ Pendente

---

### TASK-045.3 ⏳ — Teste: nome inválido retorna 422 com mensagem clara

**T — Tarefa:**
Escrever teste Pest que envia `target_agent_id` com nome de agente inexistente e verifica resposta 422 com campo `message` legível.

**A — Arquivo:**
- **Modificar:** `api/tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php`

**C — Comportamento:**
```
ANTES:
- Nenhum teste cobre tentativa de delegação para agente inexistente por nome

DEPOIS:
- Teste `test_delegate_endpoint_returns_422_for_unknown_agent_name` existe
- Envia target_agent_id = 'Agente Que Nao Existe'
- Espera status 422
- Espera campo 'message' (ou 'error') contendo o nome do agente
```

**E — Evidência:**
- [ ] Teste existe no arquivo
- [ ] `./vendor/bin/pest --filter="returns_422_for_unknown_agent_name"` PASS após TASK-045.1

**Dependências:** TASK-045.1

**Status:** ⏳ Pendente

---

### TASK-045.4 ⏳ — Log estruturado quando regra de delegação não existe

**T — Tarefa:**
Em `AiAgentDelegationService::delegate`, quando não existe `AiAgentDelegation` ativa para o par source/target, adicionar `Log::warning` com campos `tenant_id`, `source_agent_id`, `target_agent_id` e retornar mensagem que inclui os dois IDs.

**A — Arquivo:**
- **Modificar:** `api/src/Domain/Ai/Services/AiAgentDelegationService.php`
  - Bloco após `if (! $delegationRule)` (~linha 82)

**C — Comportamento:**
```
ANTES:
- return ['success' => false, 'message' => 'Delegation rule not allowed for this source/target pair.']
- Nenhum log — falha invisível no gateway

DEPOIS:
- Log::warning('[AiAgentDelegation] No delegation rule found', [
      'tenant_id'       => $tenantId,
      'source_agent_id' => $sourceAgentId,
      'target_agent_id' => $targetAgentId,
  ]);
- return ['success' => false, 'message' => "No active delegation rule from agent {$sourceAgentId} to agent {$targetAgentId}."]
- Endpoint retorna 422 com essa mensagem
```

**E — Evidência:**
- [ ] TASK-045.5 passando
- [ ] `./vendor/bin/pest tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php` verde
- [ ] `use Illuminate\Support\Facades\Log;` no topo do arquivo (adicionar se faltar)

**Dependências:** TASK-045.5 (escrever teste antes)

**Status:** ⏳ Pendente

---

### TASK-045.5 ⏳ — Teste: 422 com IDs source/target quando sem regra

**T — Tarefa:**
Escrever teste Pest que cria source e target sem `AiAgentDelegation` e verifica que o endpoint retorna 422 com `message` contendo os UUIDs de source e/ou target.

**A — Arquivo:**
- **Modificar:** `api/tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php`

**C — Comportamento:**
```
ANTES:
- Nenhum teste valida o conteúdo da mensagem de erro por falta de regra

DEPOIS:
- Teste `test_delegate_endpoint_returns_422_with_reason_when_no_rule_exists` existe
- Cria sourceAgent, targetAgent, parentRun — sem AiAgentDelegation
- Envia target_agent_id = UUID do targetAgent
- Espera 422 com assertJsonPath('message', fn($m) => str_contains($m, $sourceAgent->id) || str_contains($m, $targetAgent->id))
```

**E — Evidência:**
- [ ] Teste existe no arquivo
- [ ] `./vendor/bin/pest --filter="returns_422_with_reason_when_no_rule"` FAIL antes de TASK-045.4
- [ ] `./vendor/bin/pest --filter="returns_422_with_reason_when_no_rule"` PASS após TASK-045.4

**Dependências:** Nenhuma (escrever primeiro)

**Status:** ⏳ Pendente

---

### TASK-045.6 ⏳ — Endpoint `GET /internal/ai/agents/available`

**T — Tarefa:**
Criar método `availableAgents(Request $request)` em `InternalAiController` que retorna agentes delegáveis para um agente origem (filtrado pelas regras `AiAgentDelegation` ativas), com cache de 15 minutos. Registrar a rota no grupo interno.

**A — Arquivo:**
- **Modificar:** `api/src/Domain/Ai/Http/Controllers/InternalAiController.php` (novo método)
- **Modificar:** `api/routes/api.php` (nova rota no grupo `internal/ai`)

**C — Comportamento:**
```
ANTES:
- Não existe endpoint listando agentes delegáveis
- O modelo de IA não tem como saber quais agentes pode chamar
- Nomes de agentes são inventados ou conhecidos só via prompt manual

DEPOIS:
- GET /api/internal/ai/agents/available?tenant_id=X&agent_id=Y
- Retorna: { data: { agents: [{ id, name, description, role }] } }
- Filtra apenas agentes com AiAgentDelegation ativa onde source_agent_id = Y
- Filtra apenas agentes is_active = true
- Cache Redis por 15 minutos por chave tenant+agent
```

**E — Evidência:**
- [ ] TASK-045.7 passando
- [ ] `GET /api/internal/ai/agents/available?tenant_id=X&agent_id=Y` retorna 200 com lista
- [ ] Agente sem delegação ativa NÃO aparece na lista
- [ ] Agente inativo NÃO aparece na lista
- [ ] `./vendor/bin/pest tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php` verde

**Dependências:** TASK-045.7 (escrever teste antes), TASK-045.8 (relação no model)

**Status:** ⏳ Pendente

---

### TASK-045.7 ⏳ — Teste: endpoint retorna apenas agentes com regra ativa

**T — Tarefa:**
Escrever teste Pest para `GET /internal/ai/agents/available` que cria 3 agentes (um delegável, um sem regra, um inativo com regra) e verifica que apenas o delegável ativo aparece na resposta.

**A — Arquivo:**
- **Modificar:** `api/tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php`

**C — Comportamento:**
```
ANTES:
- Nenhum teste cobre o endpoint de agentes disponíveis

DEPOIS:
- Teste `test_available_agents_endpoint_returns_delegatable_agents` existe
- Cria: sourceAgent, delegatableAgent (com regra ativa), otherAgent (sem regra)
- assertJsonCount(1, 'data.agents')
- assertJsonPath('data.agents.0.id', UUID do delegatableAgent)
- assertJsonPath('data.agents.0.name', 'Agente Financeiro')
```

**E — Evidência:**
- [ ] Teste existe no arquivo
- [ ] `./vendor/bin/pest --filter="available_agents_endpoint_returns_delegatable"` FAIL antes de TASK-045.6
- [ ] `./vendor/bin/pest --filter="available_agents_endpoint_returns_delegatable"` PASS após TASK-045.6

**Dependências:** Nenhuma (escrever primeiro)

**Status:** ⏳ Pendente

---

### TASK-045.8 ⏳ — Relação `targetAgent()` em `AiAgentDelegation`

**T — Tarefa:**
Verificar se `AiAgentDelegation` possui `targetAgent(): BelongsTo`. Se não existir, adicionar. Esta relação é necessária para o eager load do endpoint `availableAgents`.

**A — Arquivo:**
- **Verificar/Modificar:** `api/src/Domain/Ai/Models/AiAgentDelegation.php`

**C — Comportamento:**
```
ANTES:
- Possível ausência do método targetAgent() no model

DEPOIS:
- public function targetAgent(): BelongsTo
  {
      return $this->belongsTo(AiAgent::class, 'target_agent_id');
  }
- Método sourceAgent() também verificado (adicionar se faltar)
```

**E — Evidência:**
- [ ] `php artisan ide-helper:models` sem erros relacionados a AiAgentDelegation
- [ ] `./vendor/bin/phpstan analyse src/Domain/Ai/Models/AiAgentDelegation.php` sem erros
- [ ] Eager load em TASK-045.6 funciona (TASK-045.7 passa)

**Dependências:** Nenhuma

**Status:** ⏳ Pendente

---

## 📊 Revisão de Tasks

| Task       | Status      | Validada por | Data |
| ---------- | ----------- | ------------ | ---- |
| TASK-045.1 | ⏳ Pendente | -            | -    |
| TASK-045.2 | ⏳ Pendente | -            | -    |
| TASK-045.3 | ⏳ Pendente | -            | -    |
| TASK-045.4 | ⏳ Pendente | -            | -    |
| TASK-045.5 | ⏳ Pendente | -            | -    |
| TASK-045.6 | ⏳ Pendente | -            | -    |
| TASK-045.7 | ⏳ Pendente | -            | -    |
| TASK-045.8 | ⏳ Pendente | -            | -    |

---

## 🔁 Ordem de Execução Recomendada

```
Escrever testes (TDD first):
  TASK-045.2 → TASK-045.3 → TASK-045.5 → TASK-045.7

Implementar:
  TASK-045.8 → TASK-045.1 → TASK-045.4 → TASK-045.6

Gate final:
  ./vendor/bin/pest tests/Feature/Domain/Ai/Http/Controllers/InternalAiControllerTest.php
  ./vendor/bin/phpstan analyse src/Domain/Ai/
```

---

## Progresso

- [0/8] Tasks concluídas
- [ ] Feature completa
