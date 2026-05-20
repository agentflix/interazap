# Tasks — ai-tool-parameter-resolution

**Feature:** `.context/DOCS/FEATURES/ai-tool-parameter-resolution.md`
**Status:** [x] Em progresso | [ ] Concluída
**Total:** 9 tasks | Pendentes: 1 | Em progresso: 0 | Concluídas: 8

---

## TASK-1.1.1 — Congelar Falha SIM30 Como Regressão

**T — Tarefa:** Atualizar/criar testes que provem o bug atual de run concluída sem resposta ao cliente.
**A — Arquivo:**
- `api/tests/E2E/sim-real-company-30-chats.php`
- `gateway/src/domains/ai/__tests__/ai-run-orchestrator.service.spec.ts`

**C — Comportamento:**
- Antes: cenário com `tool_calls` de bastidor pode terminar `completed` sem `send_message`.
- Depois: teste falha se a última mensagem inbound não tiver outgoing posterior, exceto handoff/close/delegação válida.

**E — Evidência:**
```bash
cd api && php -l tests/E2E/sim-real-company-30-chats.php
pnpm --filter gateway test -- ai-run-orchestrator
```

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

## TASK-2.1.1 — Criar Resolvedor de Entidades Para Tools

**T — Tarefa:** Criar serviço Laravel para resolver seller, negociação e agente a partir de IDs, nomes, emails e contexto de ticket.
**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiToolEntityResolver.php`
- `api/tests/Unit/Domain/Ai/Services/AiToolEntityResolverTest.php`

**C — Comportamento:**
- Antes: tools recebem strings humanas e tentam persistir como UUID.
- Depois: tools chamam resolver tenant-scoped antes de persistir qualquer entidade.

**E — Evidência:**
```bash
cd api && php artisan test --filter=AiToolEntityResolverTest
```

**Status:** ✅ Concluída
**Dependências:** TASK-1.1.1

---

## TASK-2.1.2 — Tornar notify_seller Tolerante a Parâmetros Humanos

**T — Tarefa:** Atualizar `NotifySellerTool` para aceitar seller por UUID, nome, email, alias ou seller padrão.
**A — Arquivo:**
- `api/src/Domain/Ai/Tools/NotifySellerTool.php`
- `api/tests/Unit/Domain/Ai/Tools/NotifySellerToolTest.php`

**C — Comportamento:**
- Antes: `seller_id="Lucas"` causa SQL exception por UUID inválido.
- Depois: `seller="Rosa"` resolve para usuário do tenant; `seller_id="Lucas"` retorna erro recuperável sem exception.

**E — Evidência:**
```bash
cd api && php artisan test --filter=NotifySellerToolTest
```

**Status:** ✅ Concluída
**Dependências:** TASK-2.1.1

---

## TASK-2.1.3 — Tornar create_task Resiliente a Contexto de Ticket

**T — Tarefa:** Atualizar `CreateTaskTool` para resolver negociação por `negotiation_id`, `ticket_id` ou `contact_id`, com erro recuperável quando não houver negociação.
**A — Arquivo:**
- `api/src/Domain/Ai/Tools/CreateTaskTool.php`
- `api/tests/Unit/Domain/Ai/Tools/CreateTaskToolTest.php`

**C — Comportamento:**
- Antes: o modelo usa `ticket_id` em `negotiation_id` e a tool só retorna `Negotiation not found`.
- Depois: a tool detecta o tipo errado, tenta resolver negociação ativa pelo contexto e retorna `error_code=negotiation_not_found` quando não resolver.

**E — Evidência:**
```bash
cd api && php artisan test --filter=CreateTaskToolTest
```

**Status:** ✅ Concluída
**Dependências:** TASK-2.1.1

---

## TASK-3.1.1 — Hidratar Contexto Operacional da Run

**T — Tarefa:** Enriquecer o contexto publicado para o gateway com seller padrão, agentes disponíveis, contato, ticket e negociação ativa.
**A — Arquivo:**
- `api/src/Domain/Ai/Services/AutopilotRunSnapshotResolver.php`
- `api/src/Domain/Ai/Services/AiContextBuilderService.php`
- `api/tests/Feature/Ai/AutopilotRunSnapshotResolverTest.php`

**C — Comportamento:**
- Antes: o modelo recebe contexto de conversa, mas não recebe IDs operacionais suficientes para tools.
- Depois: `ai.run.request` inclui `default_seller`, `available_agents` e `active_negotiation`.

**E — Evidência:**
```bash
cd api && php artisan test --filter=AutopilotRunSnapshotResolverTest
```

**Status:** ✅ Concluída
**Dependências:** TASK-2.1.1

---

## TASK-4.1.1 — Implementar Barreira Anti-Silêncio no Gateway

**T — Tarefa:** Impedir que run de chat termine sem resposta quando a última mensagem persistida é inbound.
**A — Arquivo:**
- `gateway/src/domains/ai/services/ai-run-orchestrator.service.ts`
- `gateway/src/domains/ai/services/tool-executor.service.ts`
- `gateway/src/domains/ai/__tests__/ai-run-orchestrator.service.spec.ts`

**C — Comportamento:**
- Antes: run com `notify_seller/create_task` falhando pode terminar `completed` sem `send_message`.
- Depois: gateway continua iteração ou executa fallback `send_message` antes de finalizar.

**E — Evidência:**
```bash
pnpm --filter gateway test -- ai-run-orchestrator
```

**Status:** ✅ Concluída
**Dependências:** TASK-1.1.1

---

## TASK-5.1.1 — Criar Tool register_sales_interest

**T — Tarefa:** Criar tool de alto nível para fluxo comercial que resolve entidades, registra interesse e responde o cliente.
**A — Arquivo:**
- `api/src/Domain/Ai/Tools/RegisterSalesInterestTool.php`
- `api/src/Domain/Ai/Enums/AiToolEnum.php`
- `api/database/seeders/AiAutopilotToolSeeder.php`
- `api/tests/Unit/Domain/Ai/Tools/RegisterSalesInterestToolTest.php`

**C — Comportamento:**
- Antes: LLM precisa compor `notify_seller`, `create_task`, `create_note` e `send_message` manualmente.
- Depois: LLM chama `register_sales_interest` com intenção, plano, urgência e mensagem ao cliente; backend resolve o resto.

**E — Evidência:**
```bash
cd api && php artisan test --filter=RegisterSalesInterestToolTest
```

**Status:** ✅ Concluída
**Dependências:** TASK-2.1.1, TASK-2.1.2, TASK-2.1.3

---

## TASK-5.1.2 — Atualizar Seeder e Prompt dos Agentes

**T — Tarefa:** Atualizar configuração inicial para orientar `Vendas` a usar `register_sales_interest` e nunca inventar UUID.
**A — Arquivo:**
- `api/database/seeders/InteraZapProductAgentsSeeder.php`
- `api/database/seeders/AiAutopilotToolSeeder.php`

**C — Comportamento:**
- Antes: prompt instrui `notify_seller + create_task` sem fornecer IDs válidos.
- Depois: prompt prioriza tool de alto nível e exige `send_message`/fallback quando tool de bastidor falhar.

**E — Evidência:**
```bash
cd api && php artisan db:seed --class=InteraZapProductAgentsSeeder
cd api && php artisan tinker --execute="echo \\Domain\\Ai\\Models\\AiAgent::where('name','Vendas')->first()->system_prompt;"
```

**Status:** ✅ Concluída
**Dependências:** TASK-5.1.1

---

## TASK-6.1.1 — Revalidar SIM30-01 e Suíte Completa

**T — Tarefa:** Rodar o fluxo real público de webchat no tenant AGENTFLX e validar evidências objetivas.
**A — Arquivo:**
- `api/tests/E2E/sim-real-company-30-chats.php`
- `api/storage/app/simulations/<batch>/summary.json`

**C — Comportamento:**
- Antes: `SIM30-01` fica sem resposta após "Pode me passar o próximo passo para fechar?".
- Depois: `SIM30-01` recebe resposta outgoing posterior, sem SQL exception em tools, com delegação para `Vendas` preservada.

**E — Evidência:**
```bash
cd api && php artisan tinker --execute="require base_path('tests/E2E/sim-real-company-30-chats.php');"
```

**Status:** ⏳ Pendente
**Dependências:** TASK-4.1.1, TASK-5.1.2
