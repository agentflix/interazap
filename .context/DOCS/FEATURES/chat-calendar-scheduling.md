# Feature: Chat Calendar Scheduling

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-001 |
| **Nome** | `chat-calendar-scheduling` |
| **Bounded Context** | Ai, CRM, Chat, Configuration |
| **Workspaces** | api, app |
| **Complexidade** | G |
| **Status** | 🟡 Em Planning |
| **Data** | 2026-05-18 |
| **Autor** | Rafael Silva |

## Flags

- [x] ⚠️ MULTI-TENANT — BelongsToTenant em todos os modelos
- [ ] ⚠️ RISCO FINANCEIRO
- [x] ⚠️ WHATSAPP — envio bidirecional via UazAPI / Z-API
- [ ] 🚨 BREAKING CHANGE
- [ ] 🔒 SEGURANÇA

---

## Resumo

O AI agent, durante uma conversa de WhatsApp, pode buscar horários livres no calendário CRM do operador, sugerir ao cliente e agendar um evento. Após o agendamento, o sistema dispara automaticamente uma mensagem de confirmação ao cliente com antecedência configurável pelo tenant. O cliente responde em texto livre — o AI interpreta a intenção — e o tenant é notificado sobre confirmação ou cancelamento.

---

## Problema que Resolve

Hoje o agente AI consegue criar eventos CRM via `ScheduleEventTool`, mas não tem visão de disponibilidade real do operador, não sugere horários livres proativamente e não gera um fluxo de confirmação pós-agendamento. O tenant não recebe notificação quando o cliente confirma ou cancela. O cliente não é lembrado do compromisso, gerando no-shows.

---

## Solução Proposta

1. **Nova AI Tool `GetAvailableSlotsTool`** — o agente consulta `CRMEvent` do operador e retorna slots livres no período solicitado.
2. **Sugestão de horários via chat** — o agente envia os slots disponíveis e o cliente escolhe via texto livre.
3. **Agendamento** — ao confirmar a escolha, o agente chama `ScheduleEventTool` que cria `CRMEvent` + registra `CRMEventClientConfirmation` (status=pending).
4. **Lembrete de confirmação** — um Job agendado dispara com a antecedência configurada pelo tenant, reengage o AI agent no ticket de origem para perguntar ao cliente se confirma o evento.
5. **Interpretação da resposta** — o AI agent interpreta texto livre (confirmar / recusar / remarcar). Se recusar, reabre o fluxo de sugestão de slots. Se remarcar, vai para seleção de nova data.
6. **Notificação ao tenant** — `NotificationDispatcherService` notifica o operador sobre confirmação ou cancelamento via UI + push.

---

## Escopo

### Incluído ✅

- [ ] Tool `GetAvailableSlotsTool` — retorna slots livres baseados em `CRMEvent` do operador no período
- [ ] Extensão de `ScheduleEventTool` — cria `CRMEventClientConfirmation` após agendar
- [ ] Model `CRMEventClientConfirmation` com status (pending / confirmed / declined)
- [ ] Job `ProcessEventConfirmationReminderJob` — dispara reengajamento do AI agent no ticket
- [ ] Config de tenant `event_confirmation_advance_minutes` — antecedência do lembrete
- [ ] Fluxo de re-sugestão de slots quando cliente recusa
- [ ] Notificação ao tenant (UI + push) na confirmação e no cancelamento
- [ ] Permissão da tool `GET_AVAILABLE_SLOTS` no role `appointment` da `AiPermissionMatrixService`
- [ ] Testes Pest: tool, job, model, isolamento multi-tenant
- [ ] UI (Angular): campo de configuração de antecedência na tela de Configurações do tenant

### Fora de Escopo ❌

- Integração com Google Calendar / Outlook Calendar
- Botões interativos WhatsApp (LIST / BUTTONS) — será texto livre com AI
- Reagendamento automático sem interação do cliente
- Notificação por e-mail ou webhook
- Sincronização bidirecional de calendários externos

---

## Dependências

| Tipo | Descrição | Status |
|------|-----------|--------|
| Módulo | `Ai` — AiToolEnum, AiPermissionMatrixService, ToolDispatcherService | ativo |
| Módulo | `CRM` — CRMEvent, CRMEventReminder, CRMEventActions | ativo |
| Módulo | `Configuration` — NotificationDispatcherService, ConfigurationTenantSettings | ativo |
| Módulo | `Chat` — ChatTicket, ChatMessage, AI agent re-engagement | ativo |
| Módulo | `Shared` — BelongsToTenant, BaseDTO | ativo |

---

## Critérios de Aceite

- [ ] `GetAvailableSlotsTool` retorna apenas slots dentro do horário de trabalho do tenant sem conflito com `CRMEvent` existente
- [ ] Após cliente escolher slot, `CRMEvent` é criado com participante e `CRMEventClientConfirmation` com status `pending`
- [ ] Tenant consegue configurar antecedência (mínimo 15 min) na tela de Configurações
- [ ] Job dispara mensagem de confirmação no ticket de origem com a antecedência exata configurada
- [ ] AI interpreta "sim/confirmo/pode ser" como `confirmed` e "não/cancela/não posso" como `declined`
- [ ] Ao confirmar: `CRMEventClientConfirmation.status = confirmed`, tenant notificado via UI + push
- [ ] Ao recusar: AI sugere novos slots no mesmo chat e inicia novo ciclo de agendamento
- [ ] `CRMEventClientConfirmation` é scoped por `tenant_id` — tenant A não vê dados de tenant B
- [ ] `composer gate:all` passa sem falhas no workspace `api/`

---

## Wireframes / Mockups

> Baseado no design system definido em `DESIGN.md` — tokens de cor, tipografia, elevation e shape.

### 1. Fluxo no Chat — Sugestão de Horários

```
┌─────────────────────────────────────────────────────────────┐
│  💬 WhatsApp Chat                                           │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ┌───────────────────────────────────────────────────┐     │
│  │ 🤖 AI Agent                              10:32 ✓✓ │     │
│  │                                                   │     │
│  │ Olá! Encontrei os seguintes horários disponíveis  │     │
│  │ para você com o Dr. João:                         │     │
│  │                                                   │     │
│  │  📅 *Terça, 20 de maio*                           │     │
│  │     • 14h00 — 14h30                               │     │
│  │     • 16h00 — 16h30                               │     │
│  │                                                   │     │
│  │  📅 *Quarta, 21 de maio*                          │     │
│  │     • 09h00 — 09h30                               │     │
│  │     • 11h00 — 11h30                               │     │
│  │                                                   │     │
│  │ Qual horário prefere? Responda com a data e hora. │     │
│  └───────────────────────────────────────────────────┘     │
│                                                             │
│  ┌────────────────────────────────────────┐                 │
│  │ 👤 Cliente                  10:35 ✓✓  │                 │
│  │ Terça às 14h está ótimo!               │                 │
│  └────────────────────────────────────────┘                 │
│                                                             │
│  ┌───────────────────────────────────────────────────┐     │
│  │ 🤖 AI Agent                              10:35 ✓✓ │     │
│  │                                                   │     │
│  │ ✅ Agendado! Consulta confirmada:                 │     │
│  │                                                   │     │
│  │  📅 Terça-feira, 20 de maio às 14h00              │     │
│  │  👤 Dr. João Silva                                │     │
│  │  ⏱ 30 minutos                                    │     │
│  │                                                   │     │
│  │ Te enviarei uma confirmação em breve. 😊           │     │
│  └───────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

**Tokens aplicados:** mensagens usam tipografia `{typography.body-md}`, bolhas com `{rounded.lg}`, fundo `{colors.canvas}`.

---

### 2. Mensagem de Confirmação (lembrete automático)

```
┌─────────────────────────────────────────────────────────────┐
│  💬 WhatsApp Chat — Lembrete de Confirmação                 │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  ┌───────────────────────────────────────────────────┐     │
│  │ 🤖 AI Agent                              09:00 ✓✓ │     │
│  │                                                   │     │
│  │ Olá! 👋 Passando para confirmar sua consulta:     │     │
│  │                                                   │     │
│  │  📅 *Amanhã, Terça 20 de maio às 14h00*           │     │
│  │  👤 Dr. João Silva                                │     │
│  │                                                   │     │
│  │ Você ainda pode comparecer?                       │     │
│  │ Responda *SIM* para confirmar ou *NÃO* para       │     │
│  │ cancelar ou escolher outro horário.               │     │
│  └───────────────────────────────────────────────────┘     │
│                                                             │
│  ─── Cenário A: Confirmação ───────────────────────────    │
│  ┌────────────────────────────────────────┐                 │
│  │ 👤 Cliente                  09:05 ✓✓  │                 │
│  │ Sim, estarei lá!                       │                 │
│  └────────────────────────────────────────┘                 │
│                                                             │
│  ┌───────────────────────────────────────────────────┐     │
│  │ 🤖 AI Agent                              09:05 ✓✓ │     │
│  │ Ótimo! ✅ Consulta confirmada. Até amanhã! 😊     │     │
│  └───────────────────────────────────────────────────┘     │
│                                                             │
│  ─── Cenário B: Recusa → Novo ciclo ───────────────────    │
│  ┌────────────────────────────────────────┐                 │
│  │ 👤 Cliente                  09:06 ✓✓  │                 │
│  │ Não consigo ir, pode remarcar?         │                 │
│  └────────────────────────────────────────┘                 │
│                                                             │
│  ┌───────────────────────────────────────────────────┐     │
│  │ 🤖 AI Agent                              09:06 ✓✓ │     │
│  │ Sem problemas! Aqui estão novos horários: [...]   │     │
│  │ → Reinicia fluxo de sugestão de slots             │     │
│  └───────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

---

### 3. Tela de Configurações — Antecedência do Lembrete (Angular)

```
┌─────────────────────────────────────────────────────────────────────┐
│  Configurações › Agendamentos                                       │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │  card-feature  bg:{colors.canvas}  border:{colors.hairline} │   │
│  │  rounded:{rounded.lg}  padding:{spacing.xxl}                │   │
│  │                                                             │   │
│  │  Lembrete de Confirmação de Agendamento                     │   │
│  │  {typography.heading-5}  color:{colors.ink}                 │   │
│  │                                                             │   │
│  │  Envie automaticamente uma mensagem ao cliente pedindo      │   │
│  │  confirmação do agendamento antes do evento.                │   │
│  │  {typography.body-sm}  color:{colors.slate}                 │   │
│  │                                                             │   │
│  │  Antecedência do lembrete                                   │   │
│  │  {typography.body-sm-medium}  color:{colors.ink}            │   │
│  │                                                             │   │
│  │  ┌──────────────────────────────────────────────────────┐  │   │
│  │  │  text-input  h:44px  border:{colors.hairline-strong} │  │   │
│  │  │  rounded:{rounded.md}                                │  │   │
│  │  │                                                      │  │   │
│  │  │  [ 24 horas antes          ▼ ]                       │  │   │
│  │  │                                                      │  │   │
│  │  │    Opções: 15 min / 30 min / 1h / 2h / 6h / 12h     │  │   │
│  │  │           24h / 48h / 72h (3 dias)                   │  │   │
│  │  └──────────────────────────────────────────────────────┘  │   │
│  │                                                             │   │
│  │  Canais de notificação ao tenant                            │   │
│  │  {typography.body-sm-medium}  color:{colors.ink}            │   │
│  │                                                             │   │
│  │  [✓] Interface web    [✓] Push (app)    [ ] E-mail          │   │
│  │                                                             │   │
│  │  ─────────────────────────────────────────────────────     │   │
│  │                                                             │   │
│  │  [ Cancelar ]                        [ Salvar Configuração ]│   │
│  │  button-secondary                    button-primary         │   │
│  │  border:{colors.hairline-strong}     bg:{colors.brand-green}│   │
│  │  rounded:{rounded.full}              rounded:{rounded.full} │   │
│  └─────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

**Tokens aplicados:** `card-feature`, `text-input`, `button-primary` (pill verde `{colors.brand-green}`), `button-secondary` (outlined, `{rounded.full}`).

---

### 4. Notificação ao Tenant — Confirmação / Cancelamento

```
┌────────────────────────────────────────────────────────────────┐
│  Notificação — Canto superior direito                          │
│  ──────────────────────────────────────────────────────────    │
│                                                                │
│  ┌──────────────────────────────────────────────────────┐     │
│  │  card-base  bg:{colors.canvas}                       │     │
│  │  border:{colors.hairline}  rounded:{rounded.lg}       │     │
│  │  shadow: elevation-4 (modal)                          │     │
│  │  padding:{spacing.xl}  width: 360px                   │     │
│  │                                                       │     │
│  │  ┌────────────────────────────────────────────────┐  │     │
│  │  │ [CENÁRIO A — CONFIRMAÇÃO]                      │  │     │
│  │  │                                                │  │     │
│  │  │  ✅  Agendamento Confirmado                    │  │     │
│  │  │      badge-green-soft                          │  │     │
│  │  │      bg:{colors.brand-green-soft}              │  │     │
│  │  │      text:{colors.brand-green-dark}            │  │     │
│  │  │      rounded:{rounded.full}                    │  │     │
│  │  │                                                │  │     │
│  │  │  Maria Silva confirmou a consulta:             │  │     │
│  │  │  {typography.body-sm}  color:{colors.ink}      │  │     │
│  │  │                                                │  │     │
│  │  │  📅 Terça, 20 mai · 14h00 · Dr. João          │  │     │
│  │  │  {typography.body-sm-medium}  color:{colors.slate} │  │     │
│  │  │                                                │  │     │
│  │  │  [ Ver Agendamento ]    button-ghost           │  │     │
│  │  │  color:{colors.brand-green-dark}               │  │     │
│  │  └────────────────────────────────────────────────┘  │     │
│  │                                                       │     │
│  │  ┌────────────────────────────────────────────────┐  │     │
│  │  │ [CENÁRIO B — CANCELAMENTO]                     │  │     │
│  │  │                                                │  │     │
│  │  │  ❌  Agendamento Cancelado                     │  │     │
│  │  │      badge-orange                              │  │     │
│  │  │      bg:{colors.accent-orange}                 │  │     │
│  │  │      text:{colors.on-dark}                     │  │     │
│  │  │      rounded:{rounded.sm}                      │  │     │
│  │  │                                                │  │     │
│  │  │  Maria Silva cancelou a consulta:              │  │     │
│  │  │  {typography.body-sm}  color:{colors.ink}      │  │     │
│  │  │                                                │  │     │
│  │  │  📅 Terça, 20 mai · 14h00 · Dr. João          │  │     │
│  │  │  {typography.body-sm-medium}  color:{colors.slate} │  │     │
│  │  │                                                │  │     │
│  │  │  O cliente recebeu sugestão de novos horários. │  │     │
│  │  │  {typography.body-sm}  color:{colors.steel}    │  │     │
│  │  │                                                │  │     │
│  │  │  [ Ver Conversa ]    button-ghost              │  │     │
│  │  │  color:{colors.brand-green-dark}               │  │     │
│  │  └────────────────────────────────────────────────┘  │     │
│  └──────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────┘
```

**Tokens aplicados:** `card-base`, `badge-green-soft` (confirmação), `badge-orange` (cancelamento), `button-ghost` com `{colors.brand-green-dark}`, elevation `shadow-4`.

---

### 5. Fluxo Macro — Diagrama de Estados

```
[Conversa Ativa]
      │
      ▼
[GetAvailableSlotsTool]
      │
      ▼
[AI sugere slots no chat]
      │
      ▼
[Cliente escolhe horário]
      │
      ▼
[ScheduleEventTool]
 ├── CRMEvent (status: scheduled)
 └── CRMEventClientConfirmation (status: pending)
      │
      ▼
[ProcessEventConfirmationReminderJob]
 └── agenda: event.starts_at - tenant.advance_minutes
      │
      ▼
[Job dispara: AI reengage no ticket]
 └── envia mensagem de confirmação ao cliente
      │
      ▼
[Cliente responde]
      │
      ├── CONFIRMA ──────────────────────────────────────────────┐
      │    ├── CRMEventClientConfirmation.status = confirmed      │
      │    ├── CRMEvent.status = confirmed                        │
      │    └── NotificationDispatcherService → tenant (UI + push) │
      │                                                           │
      ├── RECUSA ──────────────────────────────────────────────┐  │
      │    ├── CRMEventClientConfirmation.status = declined     │  │
      │    ├── CRMEvent.status = cancelled                      │  │
      │    ├── NotificationDispatcherService → tenant (UI + push)│  │
      │    └── AI reenicia GetAvailableSlotsTool                │  │
      │                                                         │  │
      └── SEM RESPOSTA (timeout configurável) ─────────────────┘  │
           └── NotificationDispatcherService → tenant (alerta)    │
                                                                   ▼
                                                            [Evento Confirmado ✅]
```

---

## Tasks

Ver: `.context/DOCS/TASKS/chat-calendar-scheduling-tasks.md`

| Task | Título | Status | Workspace |
|------|--------|--------|-----------|
| TASK-1.1.1 | Feature doc | ✅ | — |
| TASK-3.1.1 | Migration `crm_event_client_confirmations` | ⏳ | api |
| TASK-3.1.2 | Migration `configuration_scheduling_settings` | ⏳ | api |
| TASK-3.2.1 | Model `CRMEventClientConfirmation` | ⏳ | api |
| TASK-3.2.2 | Model `ConfigurationSchedulingSetting` | ⏳ | api |
| TASK-3.3.1 | Tool `GetAvailableSlotsTool` | ⏳ | api |
| TASK-3.3.2 | Tool `ConfirmEventBookingTool` | ⏳ | api |
| TASK-3.3.3 | Estender `ScheduleEventTool` | ⏳ | api |
| TASK-3.4.1 | Job `ProcessEventConfirmationReminderJob` | ⏳ | api |
| TASK-3.5.1 | HTTP endpoint scheduling settings | ⏳ | api |
| TASK-5.1.1 | `SchedulingSettingsComponent` | ⏳ | app |

**Progresso:** 1 / 11 tasks concluídas
