# 0003-PRD-message-based-billing

**Versão:** 1.0
**Data:** 2026-05-23
**Autor:** Rafael Silva
**Status:** [ ] Rascunho | [x] Em revisão | [ ] Aprovado

---

## Visão Geral

Substituir o modelo de bilhetagem por tokens da IA por bilhetagem por mensagens respondidas pela IA, com cota mensal por plano e escolha do tenant entre pausar a IA ou cobrar mensagens excedentes ao atingir o limite.

## Problema

O modelo atual cobra por tokens consumidos pela IA, métrica opaca para o tenant (não consegue prever custo, não entende a unidade) e acoplada ao provider/LLM. Cada troca de modelo ou ajuste de prompt altera o custo, gerando faturas voláteis. Não há controle previsível de gasto.

Mensagens entregues ao cliente são uma unidade de valor compreensível, alinhada à percepção de uso ("atendi 800 conversas esse mês"). Permite planos comerciais simples, comparação direta entre concorrentes e previsibilidade de fatura.

## Solução

1. Cada `platform_plans` define `message_limit_monthly` (cota de mensagens IA por ciclo).
2. Cada tenant tem ciclo aniversário (dia da assinatura, cap 28) e contador agregado simples em `tenant_message_usage` (1 linha por tenant/ciclo).
3. Gateway, antes de enviar resposta IA, consulta `POST /v1/billing/usage/check-and-increment` na api. Resposta `allowed=true` envia; `allowed=false` bloqueia e dispara handoff humano.
4. Ao atingir limite, comportamento depende de `overage_mode` (default do plano) e `overage_mode_override` (preferência do tenant): `stop` (pausa IA) ou `overage` (continua e cobra `overage_price_per_message` × excedentes na próxima fatura).
5. Alertas automáticos email + WhatsApp em 80% e 100% do consumo via job assíncrono idempotente.
6. Tela `settings/my-plan` ganha barra "Mensagens IA" no card `usage-stats` mostrando consumo/limite/cores por threshold e estado overage/stop.
7. Schema novo (sem migração): drop colunas token de `platform_plans`, criar campos message, criar tabela `tenant_message_usage`.

## Usuários

- **Primário:** Tenant owner/admin — visualiza consumo, ajusta preferência overage, recebe alertas
- **Secundário:** Super-admin InteraZap — define planos com `message_limit_monthly` e `overage_price_per_message`
- **Terciário:** Cliente final do tenant — recebe mensagens IA enquanto cota disponível; recebe handoff humano após limite em modo `stop`

## Requisitos Funcionais

1. **[RF01]** Schema de `platform_plans` perde `token_limit_monthly`, `overage_price_per_1k`, `allow_overage`; ganha `message_limit_monthly INT NOT NULL`, `overage_mode VARCHAR(10) DEFAULT 'stop'`, `overage_price_per_message DECIMAL(10,4) NULL`
2. **[RF02]** Schema de `platform_tenants` ganha `overage_mode_override VARCHAR(10) NULL` e `billing_cycle_anchor_day SMALLINT NULL`
3. **[RF03]** Criar tabela `tenant_message_usage(id, tenant_id, cycle_start, cycle_end, message_count, overage_count, alert_80_sent_at, alert_100_sent_at, created_at, updated_at)` com `UNIQUE(tenant_id, cycle_start)`; criar tabela `ai_message_usage_failed_log(id, tenant_id, ai_turn_id, channel, attempted_at, reason)` com `UNIQUE(ai_turn_id)` para fail-open
4. **[RF04]** Endpoint `POST /v1/billing/usage/check-and-increment` (auth service-to-service) recebe `{tenant_id, channel, ai_turn_id}` e retorna `{allowed, current, limit, mode, is_overage}` em transação atômica idempotente por `ai_turn_id`
5. **[RF05]** Endpoint `GET /v1/subscription/me` (existente) é estendido para incluir `usage.ai_messages` com `{current, limit, percentage, overage_count, mode, overage_price, cycle_start, cycle_end, cycle_label}`
6. **[RF06]** Endpoint `PATCH /v1/tenants/me/billing-prefs` aceita `{overage_mode_override: "stop"|"overage"|null}`, restrito a role owner/admin
7. **[RF07]** Gateway consulta `check-and-increment` antes de enviar cada resposta IA; em `allowed=false` bloqueia envio e enfileira fluxo de handoff humano
8. **[RF08]** Cálculo de ciclo: anchor = dia da assinatura, com cap 28 (dias 29-31 viram 28); ciclo ativo criado lazy no primeiro increment da janela
9. **[RF09]** Job diário `CloseExpiredCyclesJob` (03:00 UTC) fecha ciclos expirados; se `overage_count > 0`, cria fatura adicional em `billing_invoices` (status=`pending`, `reference_month` = mês fim do ciclo, `amount` = `overage_count × overage_price_per_message`, `metadata.kind` = `"overage"`, `metadata.detail` = `{cycle_start, cycle_end, message_count, overage_count, price_per_message}`)
10. **[RF10]** Job `CheckUsageThresholdsJob` (enfileirado pós-increment) marca `alert_80_sent_at`/`alert_100_sent_at` e enfileira `SendUsageAlertJob` (email + WhatsApp) sem duplicar
11. **[RF11]** Job `ReconcileFailedUsageJob` (diário) replays increments registrados em `ai_message_usage_failed_log` durante fail-open, idempotente via `ai_turn_id`
12. **[RF12]** Componente `usage-stats` (Angular) renderiza nova linha "Mensagens IA" com barra colorida (verde 0-79%, amarelo 80-99%, vermelho 100%+), contador `current/limit`, contador overage e texto de estado ("IA pausada" ou "Cobrando R$ X,XX/msg")
13. **[RF13]** Componente `plan-card` lista bullet `✓ N mensagens IA/mês`
14. **[RF14]** Modal `BillingPrefsModal` permite alternar `stop`↔`overage` chamando `PATCH /billing-prefs`
15. **[RF15]** Mudança de plano mid-cycle preserva ciclo atual mas aplica novo `message_limit_monthly` imediato

## Requisitos Não-Funcionais

1. **[RNF01]** Endpoint `check-and-increment` p95 < 100ms (1 SELECT FOR UPDATE + 1 UPDATE/INSERT)
2. **[RNF02]** Idempotência garantida via `ai_turn_id` (UUID gerado pelo gateway); retry de rede não duplica contagem
3. **[RNF03]** Fail-open em indisponibilidade da api: gateway envia mensagem + registra em `ai_message_usage_failed_log` para reconciliação
4. **[RNF04]** Endpoint `check-and-increment` autenticado por token service-to-service; não exposto a clientes externos
5. **[RNF05]** Rate-limit do `check-and-increment` por `tenant_id` (proteção contra loop bug no gateway)
6. **[RNF06]** Logs estruturados: `usage.increment` (info), `usage.limit_reached` (warn), `usage.fail_open` (error)
7. **[RNF07]** Métricas Prometheus: `ai_messages_total{tenant_id, mode, allowed}`, `usage_check_duration_seconds`, `usage_check_failures_total{reason}`
8. **[RNF08]** Mudança de `overage_mode_override` registrada em `audits` (tabela existente)
9. **[RNF09]** Tenant isolation respeitado em todas as queries de `tenant_message_usage`
10. **[RNF10]** Cobertura mínima 80% em services billing/usage e endpoints novos

## Critérios de Aceite

- [ ] Migrations api criam novas colunas e tabela; drop das colunas token executado
- [ ] PHPUnit: `UsageCounterServiceTest`, `BillingCycleCalculatorTest`, `ThresholdCheckerTest`, feature tests dos 3 endpoints novos
- [ ] Jest gateway: pipeline IA respeita `allowed`; cliente HTTP retry + fail-open; alert job enfileira template correto
- [ ] Jest app: `UsageStatsComponent` renderiza barra com cores corretas, exibe overage e estado pausa; `BillingPrefsModal` salva preferência
- [ ] Cenário manual 1: tenant em 0/800 envia 800 msgs → barra 100% → 801ª bloqueada (stop) ou contada como overage (overage)
- [ ] Cenário manual 2: passar virada de aniversário → nova row criada → contador zera
- [ ] Cenário manual 3: trocar plano mid-cycle → novo limite imediato, ciclo preservado
- [ ] Alerta 80% enviado uma única vez; alerta 100% enviado uma única vez por ciclo
- [ ] Job `CloseExpiredCyclesJob` gera item de overage em `billing_invoices` na fatura do mês correto
- [ ] `GET /v1/subscription/me` retorna `usage.ai_messages` consumido pelo frontend sem regressão em outros campos
- [ ] Documentação atualizada em `.context/DOCS/FEATURES/message-based-billing.md`

## Wireframes / Fluxos

Card `usage-stats` (após implementação):

```
┌─ Estatísticas de uso ─────────────────────────┐
│ Usuários                              2/100   │
│ ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░     │
│ Instâncias                            1/25    │
│ ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░     │
│ Storage                       244 KB/50.0 MB  │
│ █░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░     │
│ Mensagens IA (15/jun – 14/jul)       640/800  │
│ ████████████████████░░░░░░░░░ 80%             │
│ Negociações                            0/∞    │
└───────────────────────────────────────────────┘
```

Fluxo de bloqueio:

```
Cliente envia msg
  → Gateway: gera resposta IA (LLM)
  → Gateway: POST /v1/billing/usage/check-and-increment
        ├─ allowed=true  → envia ao WhatsApp/webchat
        └─ allowed=false → não envia IA; abre ticket humano + msg padrão
```

## Dependências

- Tabelas existentes `platform_plans`, `platform_tenants`, `billing_invoices`, `audits`
- Endpoint `GET /v1/subscription/me` (existente) — será estendido
- Pipeline de resposta IA no gateway (existente) — receberá nova etapa
- Fila `whatsapp:outbound` no BullMQ (existente) — usada para alertas

## Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Latência adicional no envio de resposta IA (~50-100ms) | Alta | Baixo | Endpoint enxuto (1 lock + 1 UPDATE); p95 < 100ms; usuário não percebe em chat |
| Fail-open mascara contagem perdida se reconciliação falhar | Baixa | Médio | Log estruturado + alerta ops em `usage.fail_open`; reconciliação diária |
| Race condition em increment concorrente | Média | Alto | SELECT FOR UPDATE garante atomicidade; teste com paralelismo no PHPUnit |
| Alerta WhatsApp falha (Meta API down) | Média | Baixo | Email basta como canal mínimo; retry BullMQ |
| Tenant abusa toggle overage→stop pra evitar cobrança | Baixa | Médio | Mudança registrada em audits; cobrança considera mode no momento do increment |
| Edge case anchor dia 29-31 | Alta | Baixo | Cap explícito em 28 na assinatura; teste unitário cobre |

## Cronograma Estimado

- Planejamento: 1 dia (este PRD + feature doc + tasks)
- Execução Backend (api): 3 dias (migrations + endpoints + services + jobs + testes)
- Execução Gateway: 1 dia (cliente HTTP + pipeline IA + alert producer + testes)
- Execução Frontend: 1 dia (usage-stats + modal prefs + plan-card + testes)
- Validação (cenários manuais + code review): 1 dia

**Total estimado:** 7 dias úteis

## Revisões

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-23 | Rafael Silva | Criação a partir de brainstorming de 7 seções |
