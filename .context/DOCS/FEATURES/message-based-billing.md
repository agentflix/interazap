# Feature: Bilhetagem por Mensagens IA

## Metadados
- ID: FEAT-003
- PRD: `.context/DOCS/PRDS/0003-PRD-message-based-billing.md`
- Bounded Context: Billing
- Complexidade: G
- Status: 🟡 Em Planning
- Data: 2026-05-23

## Resumo

Substituir bilhetagem por tokens da IA por bilhetagem por mensagens respondidas. Cada plano define cota mensal de mensagens IA; tenant escolhe pausar atendimento ou cobrar excedentes ao atingir limite. Ciclo aniversário com contador atômico no api e enforcement no gateway antes de cada resposta IA.

## Módulos Afetados

- [x] api/ (Laravel 12) — migrations, models, services, endpoints, jobs
- [x] gateway/ (NestJS 11) — cliente HTTP usage, integração no pipeline IA, alert producer
- [x] app/ (Angular 20) — usage-stats (barra mensagens), modal preferências, plan-card
- [ ] Infraestrutura

## Escopo

### Incluído
- [ ] Schema novo (drop tokens, add message fields, tabela `tenant_message_usage`, tabela `ai_message_usage_failed_log`)
- [ ] Endpoint atômico `check-and-increment` idempotente via `ai_turn_id`
- [ ] Endpoint estendido `GET /v1/subscription/me` com `usage.ai_messages`
- [ ] Endpoint `PATCH /v1/tenants/me/billing-prefs` (toggle overage)
- [ ] Integração no gateway: bloqueio/permissão antes do envio IA
- [ ] Jobs: `CloseExpiredCyclesJob` (diário), `CheckUsageThresholdsJob` (pós-increment), `SendUsageAlertJob` (email + WhatsApp), `ReconcileFailedUsageJob` (diário)
- [ ] Cálculo de ciclo aniversário com cap dia 28
- [ ] UI: barra "Mensagens IA" no `usage-stats`, modal `BillingPrefsModal`, bullet no `plan-card`
- [ ] Cobertura ≥ 80% em services billing/usage

### Fora de Escopo
- Pacotes top-up de mensagens extras
- Pesos diferentes por canal
- Dashboard interno super-admin de tenants próximos do limite
- Cache Redis para contador (v1 usa lock direto)
- Migração de tenants existentes (banco reset clean)
- Cypress E2E (validação manual nos cenários listados)

## Critérios de Aceite

- [ ] `cd api && composer gate:all` verde
- [ ] `pnpm --filter gateway build && pnpm --filter gateway test` verde
- [ ] `pnpm --filter app build && pnpm --filter app test` verde
- [ ] Migration `migrate:fresh --seed` cria schema novo sem erros
- [ ] Cenário 1: tenant em 0/800 → 800 msgs OK → 801ª bloqueada (stop) ou contada overage
- [ ] Cenário 2: virada de aniversário → nova row em `tenant_message_usage` → contador zera
- [ ] Cenário 3: troca plano mid-cycle → limite novo aplica imediato, ciclo preserva
- [ ] Alerta 80% enviado 1x; alerta 100% enviado 1x por ciclo (email + WhatsApp)
- [ ] `CloseExpiredCyclesJob` gera fatura overage em `billing_invoices` (metadata.kind=overage)
- [ ] Barra "Mensagens IA" aparece em `settings/my-plan` com cores verde/amarelo/vermelho

## Dependências

- **Features:** nenhuma (reset clean do banco)
- **Módulos:** `api/app/Models/PlatformPlan`, `api/app/Models/PlatformTenant`, `billing_invoices` (existente), `audits` (existente), pipeline IA gateway (existente), fila `whatsapp:outbound` (existente)
- **Externas:** API Meta WhatsApp (alerta), provedor email (alerta)

## Fases Estimadas

- [x] **Fase 1 — Planning** ✅
- [x] **Fase 2 — Design** ✅ (wireframe usage-stats + modal prefs)
- [x] **Fase 3 — Backend** ✅ (migrations + models + services + endpoints + jobs + testes)
- [x] **Fase 3.5 — Gateway** ✅ (cliente HTTP usage + integração pipeline IA + métricas + testes)
- [x] **Fase 4 — Frontend** ✅ (model TS + usage-stats + modal + plan-card + service + testes)
- [x] **Fase 5 — Integration** ⚠️ PARCIAL (gates api/app/gateway/dependências OK; cenários manuais pendentes)

## Design

Artefatos em `.context/DESIGN/message-based-billing-*.md` (criar na Fase 2 antes do Frontend).

## Tasks

Ver `.context/DOCS/TASKS/message-based-billing-tasks.md`

## Notas

**Decisões técnicas tomadas no planning:**
- 1 mensagem = 1 resposta textual final entregue ao cliente (1 turno LLM, independente de balões)
- Canais contam igual (sem pesos)
- Híbrido `stop|overage` configurável por tenant (override do default do plano)
- Ciclo aniversário com cap dia 28 (edge case fevereiro)
- Sem migração — schema reset clean
- Contador agregado simples (1 row por tenant/ciclo) sem cache Redis
- Atomicidade via SELECT FOR UPDATE no api
- Idempotência via UUID `ai_turn_id` gerado no gateway
- Fail-open em indisponibilidade api + reconciliação diária
- Overage cobrado em fatura adicional (metadata.kind=overage)
