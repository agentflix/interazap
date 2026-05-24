# Wireframe — Bilhetagem por Mensagens IA

**Feature:** message-based-billing
**Referência:** `.context/DOCS/PRDS/0003-PRD-message-based-billing.md`
**Data:** 2026-05-23

---

## A — Barra "Mensagens IA" no `usage-stats`

Posição: entre Storage e Negociações no card "Estatísticas de uso" em `settings/my-plan`.

### Estado 1 — Normal (0–79%)
```
┌─ Estatísticas de uso ─────────────────────────────────────┐
│ Usuários                                          2/100   │
│ ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ [verde]           │
│                                                           │
│ Instâncias                                        1/25    │
│ ████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ [verde-accent]    │
│                                                           │
│ Storage                                  244 KB/50.0 MB  │
│ █░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ [amarelo]         │
│                                                           │
│ Mensagens IA  (15/jun – 14/jul)                 640/800  │
│ ████████████████████░░░░░░░░░░░░░░░░░ [verde]           │
│                                                           │
│ Negociações                                       0/∞    │
└───────────────────────────────────────────────────────────┘
```
- Barra: `bg-primary-500`
- Sem texto extra abaixo da barra
- `cycle_label` exibido em fonte menor cinza ao lado do label

### Estado 2 — Atenção (80–99%)
```
│ Mensagens IA  (15/jun – 14/jul)                 720/800  │
│ ██████████████████████████████░░░░░░░ [amarelo]         │
```
- Barra: `bg-warning`
- Contador: mantém `current/limit` em texto normal

### Estado 3a — Limite atingido, modo STOP (≥100%)
```
│ Mensagens IA  (15/jun – 14/jul)                 800/800  │
│ ██████████████████████████████████████ [vermelho]       │
│ IA pausada — reseta em 14/jul                           │
```
- Barra: `bg-error` (100% width)
- Texto abaixo: `text-xs text-error`
- Ação IA bloqueada

### Estado 3b — Limite atingido, modo OVERAGE (≥100%)
```
│ Mensagens IA  (15/jun – 14/jul)       820/800 (+20 extras)│
│ ██████████████████████████████████████ [vermelho]       │
│ Cobrando extras a R$ 0,05/msg                           │
```
- Barra: `bg-error` (100% width — cap visual, mesmo acima de 100%)
- Texto extras: `text-warning` `(+N extras)` ao lado do contador
- Texto abaixo: `text-xs text-neutral-400`

### Componente Angular — referência de campos
```ts
// SubscriptionUsage.ai_messages
{
  current: number;         // mensagens usadas no ciclo
  limit: number | null;    // null = ilimitado
  percentage: number;      // 0–100+ (pode ultrapassar)
  overage_count: number;   // msgs acima do limite
  mode: 'stop' | 'overage';
  overage_price: number | null;  // R$ por msg extra
  cycle_start: string;     // ISO date "2026-06-15"
  cycle_end: string;       // ISO date "2026-07-14"
  cycle_label: string;     // "15/jun – 14/jul"
}

// Computed signal para cor da barra
aiMsgBarColor(): string {
  if (pct < 80) return 'bg-primary-500';
  if (pct < 100) return 'bg-warning';
  return 'bg-error';
}
```

---

## B — Modal de Preferências (`BillingPrefsModal`)

Abre ao clicar em "Preferências de cobrança" no card "Plano atual".

```
┌─────────────────────────────────────────────────────┐
│  Preferências de cobrança de IA            [✕]      │
│─────────────────────────────────────────────────────│
│                                                     │
│  Ao atingir o limite de mensagens do plano:         │
│                                                     │
│  ◉ Pausar IA automaticamente                        │
│    O atendimento é transferido ao humano.           │
│    Sem cobranças extras.                            │
│                                                     │
│  ○ Continuar e cobrar mensagens excedentes          │
│    R$ 0,05 por mensagem adicional.                  │
│    Cobrado na próxima fatura.                       │
│                                                     │
│─────────────────────────────────────────────────────│
│                         [Cancelar]  [Salvar]        │
└─────────────────────────────────────────────────────┘
```

### Estados do modal
- **Loading (ao salvar):** botão "Salvar" mostra spinner + desabilitado
- **Erro:** toast `text-error` abaixo dos radios
- **Default:** herda `overage_mode` do plano se `overage_mode_override` é null
- **Plano sem overage_price:** ocultar linha `R$ X,XX/msg`

### Posição no card "Plano atual"
```
┌─ Plano atual ─────────────────────────────────────────────┐
│  ┌─────────────────────┐  ┌─────────────────────────────┐ │
│  │  Nome               │  │  Próxima fatura              │ │
│  │  Business           │  │  ...                         │ │
│  │  R$ 897,00/mês      │  │  ...                         │ │
│  └─────────────────────┘  └─────────────────────────────┘ │
│  [Preferências de cobrança ↗]  ← botão secundário sm     │
└───────────────────────────────────────────────────────────┘
```
- Botão: `variant="outline" size="sm"` (padrão design system)
- Abre modal com `(click)="openBillingPrefs()"`

---

## C — Bullet no `plan-card`

Adicionado na lista de features de cada plano na seção "Planos disponíveis".

### Posição na lista de features
```
┌─ Starter ──────────────────────────────┐
│  R$ 97,00/mês                          │
│                                        │
│  ✓ 5 usuários                          │
│  ✓ 1 canal WhatsApp                    │
│  ✓ 1,0 GB de storage                   │
│  ✓ Chatbot (respostas automáticas)     │
│  ✓ Inteligência Artificial             │
│  ✓ 800 mensagens IA/mês          ← novo│
└────────────────────────────────────────┘
```

- Formato: `{{ plan.message_limit_monthly | number:'1.0-0':'pt-BR' }} mensagens IA/mês`
- Plano com `message_limit_monthly = null` → exibir "Mensagens IA ilimitadas"
- Posição: última da lista de features com IA ativo

---

## D — Fluxo de bloqueio (cliente → IA → api → canal)

```
Cliente envia mensagem
         │
         ▼
   Gateway recebe
         │
         ▼
  AiRunOrchestratorService
  gera aiTurnId (UUID v4)
         │
         ▼
  LLM processa (Google AI)
  gera resposta textual final
         │
         ▼
  RunCompletionService
  ┌──────────────────────────────────────────────────────┐
  │  BillingUsageClient.checkAndIncrement(               │
  │    tenantId, channel, aiTurnId                       │
  │  )                                                   │
  │  → POST /api/v1/billing/usage/check-and-increment    │
  └──────────────────────────────────────────────────────┘
         │
    ┌────┴────┐
    │         │
allowed=true  allowed=false
    │         │
    ▼         ▼
Envia msg  NÃO envia msg IA
ao canal   Dispara handoff humano
           Msg padrão ao cliente
           (definida no fluxo
            de handoff existente)

─── Caminho paralelo (fail-open) ─────────────────────────
  api indisponível (timeout/error):
  → Gateway: envia msg IA (fail-open)
  → Gateway: POST /api/v1/billing/usage/fail-open-log
  → ReconcileFailedUsageJob replaya no dia seguinte
──────────────────────────────────────────────────────────

─── Alertas (assíncrono, pós-increment) ──────────────────
  UsageCounterService commita
  → enfileira CheckUsageThresholdsJob
        │
        ├─ pct ≥ 80 + alert_80_sent_at IS NULL
        │    → SendUsageAlertJob (email + WhatsApp 80%)
        │
        └─ pct ≥ 100 + alert_100_sent_at IS NULL
             → SendUsageAlertJob (email + WhatsApp 100%)
               template diferencia stop vs overage
──────────────────────────────────────────────────────────
```

### Templates WhatsApp alertas

**80%:**
```
⚠️ [Tenant]: você usou 80% das mensagens IA do plano
atual (640/800) no ciclo 15/jun–14/jul.

Ajuste suas preferências em: {link_painel}
```

**100% — modo STOP:**
```
🛑 [Tenant]: limite de mensagens IA atingido (800/800).
A IA está pausada até o próximo ciclo (14/jul).

Para continuar, mude para modo "cobrar extras" em:
{link_painel}
```

**100% — modo OVERAGE:**
```
💸 [Tenant]: limite atingido (800/800). Mensagens extras
sendo cobradas a R$ 0,05 cada até o ciclo encerrar (14/jul).

Acompanhe o consumo em: {link_painel}
```
