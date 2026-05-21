# Template: Plano de Implementação

Este é o formato canônico de um plano produzido pela skill `deep-plan`. Cada seção tem instruções inline (em _itálico_) — remova as instruções ao escrever o plano real.

---

```markdown
# [Título do Plano: verbo + substantivo]

_Ex: "Adicionar Suporte a Delegação de Agentes", "Criar Teste E2E Full-Stack de Chat"_

## Context

_Por que esta mudança existe. O problema que ela resolve. O que falha sem ela.
Escreva para uma LLM que não leu nenhuma conversa anterior._

**Problema:** [O que está faltando ou quebrado]
**Motivação:** [Por que agora, quem pediu, qual o impacto]
**Resultado esperado:** [O que deve ser verdadeiro depois da implementação]

---

## Prerequisites

_Tudo que deve ser verdadeiro ANTES de começar a implementar. A LLM executora
deve verificar cada item antes de escrever código._

- [ ] **[Nome do pré-requisito]**: [Como verificar / como garantir]
  - Ex: `plan.ai_enabled = true` no tenant de teste
  - Ex: Migration `2026_XX_XX_...` deve ter rodado
  - Ex: Gateway NestJS online em `localhost:3000`
  - Ex: Feature flag `AI_QUEUE_CONNECTION=sync` configurada

_Se o pré-requisito não estiver presente, instruir a LLM a parar e reportar._

---

## Files

_Lista exata dos arquivos envolvidos. Use paths completos a partir da raiz do repo._

### Criar

- `api/tests/E2E/Autopilot/test-14-chat-fullstack.php`
  - _O que conter: grupos de teste, setup, cleanup_

### Modificar

- `api/tests/E2E/Autopilot/run.php`
  - _Onde: array `$scripts` (linha ~27)_
  - _O que: adicionar `'test-14-chat-fullstack'`_

### Não modificar

- `api/tests/E2E/Autopilot/helpers.php` — apenas leitura, sem mudanças
- `api/tests/E2E/Autopilot/setup.php` — apenas leitura, reutilizar as fixtures existentes

---

## Implementation

_Dividido em fases sequenciais. Cada fase é auto-contida e verificável.
Inclua snippets reais copiados da investigação — não invente assinaturas._

### Phase 1 — [Nome da fase]

**Objetivo:** [O que esta fase entrega]

**Padrão de referência:** `api/tests/E2E/Autopilot/test-13-chat-simulation.php` — seguir mesmo padrão de grupos, helpers e contadores.

**Código base (copiar e adaptar):**

```php
// De: api/src/Domain/Chat/Services/ChatWebhookRouter.php:35
public function routeInbound(string $tenantId, ChatTicket $ticket, string $body, array $context = []): void

// Chamada no teste:
$router = app(\Domain\Chat\Services\ChatWebhookRouter::class);
$router->routeInbound($ctx['tenant_id'], $ticket, 'mensagem de teste', [
    'instance_id' => $ctx['instance_id'],
    'message_id'  => (string) \Illuminate\Support\Str::orderedUuid(),
    'message_type' => 'text',
    'is_first_interaction' => false,
]);
```

**Configuração necessária nesta fase:**

```php
// Forçar sync antes de qualquer dispatch
\Illuminate\Support\Facades\Config::set('queue.default', 'sync');
\Illuminate\Support\Facades\Config::set('ai.queue_connection', 'sync');
```

**O que verificar após esta fase:**

```php
$run = \Domain\Ai\Models\AiAutopilotRun::query()
    ->where('tenant_id', $ctx['tenant_id'])
    ->latest()
    ->first();
// Deve existir com status 'queued', 'running' ou 'completed'
```

---

### Phase 2 — [Nome da fase]

**Objetivo:** [O que esta fase entrega]

**Depende de:** Phase 1 (usa `$run->id` criado lá)

**Condicional:** Esta fase só executa se [condição]. Verificar com:

```php
function e2e_gateway_online(): bool {
    $ch = curl_init('http://localhost:3000/health');
    curl_setopt_array($ch, [CURLOPT_TIMEOUT => 2, CURLOPT_RETURNTRANSFER => true]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200;
}
```

**Se condição não satisfeita:** exibir `[SKIP]` com motivo claro, não falhar o teste.

---

### Phase N — Cleanup

**Objetivo:** Remover todas as fixtures criadas para deixar o banco limpo.

**Ordem de deleção** (respeitar FKs — filhos antes de pais):

1. `AiAutopilotRun` (child runs primeiro, depois parent)
2. `AiAgentDelegation`
3. `AiAgent` (Sofia Sim, Lucas Sim)
4. Mensagens de chat criadas pelo teste
5. Quaisquer entidades CRM temporárias

**Restaurar estado:**

```php
// Restaurar plan.ai_enabled ao valor original
$plan->update(['ai_enabled' => $originalAiEnabled]);

// Restaurar queue config (opcional, processos tinker terminam de qualquer forma)
```

---

## Verification

_Como testar que o plano foi implementado corretamente. Comando exato._

```bash
# Sem Gateway (grupos Claude ficam pulados)
cd api && php artisan tinker --execute="require base_path('tests/E2E/Autopilot/test-14-chat-fullstack.php');"

# Com Gateway online
# Terminal 1: cd gateway && npm run start:dev
# Terminal 2: cd api && php artisan tinker --execute="require base_path('tests/E2E/Autopilot/test-14-chat-fullstack.php');"
```

**Resultado esperado sem Gateway:**

```
=== 14.1 · Pipeline → Run Created ===
  [PASS] setup: plan.ai_enabled forçado
  [PASS] pipeline: webhook router dispatched
  [PASS] pipeline: AiAutopilotRun criado
  [PASS] pipeline: run.agent_id correto
  [PASS] pipeline: run.status válido

=== 14.2 · Claude Execution ===
  [SKIP] Gateway offline — grupos 14.2 e 14.3 pulados

▶ Test-14 Total: 5/5 passou (+ 0 pulados)
```

**Resultado esperado com Gateway:**

```
▶ Test-14 Total: 11/11 passou
  Grupo 1 (Pipeline): 5/5
  Grupo 2 (Claude Execution): 3/3
  Grupo 3 (Sofia → Lucas Delegation): 3/3
```

**Falha comum e diagnóstico:**

| Erro | Causa provável | Fix |
|---|---|---|
| `[FAIL] AiAutopilotRun não criado` | `plan.ai_enabled = false` | Verificar Phase 1 setup do plan |
| `[FAIL] run.agent_id incorreto` | Agente E2E não é `type='general'` | Verificar campo `type` no `AiAgent` |
| `[FAIL] Embedding generation failed` | Gateway offline, teste não pulou | Verificar função `e2e_gateway_online()` |
| `[FAIL] Token de webhook inválido` | Teste chamou HTTP sem token | Usar `ChatWebhookRouter` direto, não HTTP |
```

---

## Notes

_Decisões técnicas não-óbvias que a LLM executora precisa saber._

- **Por que não usar HTTP endpoint**: O teste chama `ChatWebhookRouter::routeInbound()` diretamente para evitar depender do servidor HTTP rodando. Cobre 90% do pipeline real.
- **Por que QUEUE_CONNECTION=sync**: Jobs Laravel devem rodar em processo único para o teste ser determinístico. Sem isso, o run fica `queued` e o teste não pode verificar o resultado.
- **Por que `plan.ai_enabled` é crítico**: `AiGateKeeperListener` silenciosamente descarta o run se o plano não tiver AI habilitado. Não lança exceção — só loga. Sem verificar isso, o teste passa vazio.
```

---

## Como usar este template

1. Copie este arquivo como ponto de partida
2. Preencha cada seção com dados reais da investigação
3. Remova as instruções em _itálico_
4. Substitua snippets de exemplo pelos reais (copiados diretamente dos arquivos lidos)
5. Verifique: toda assinatura de método existe no código? Todo path de arquivo existe?
