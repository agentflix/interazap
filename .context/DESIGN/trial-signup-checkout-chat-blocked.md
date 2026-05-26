---
feature: trial-signup-checkout
tipo: component-spec
status: rascunho
aprovado-por: pending
data: 2026-05-25
---

# Design: Chat — Card "IA pausada"

> Card inline injetado no histórico de conversa quando IA é bloqueada por trial expirado.
> Funciona em complemento à `TrialBanner` (não substitui).

---

## Visão Geral

Quando o `check-and-increment` retorna `allowed=false` por trial expirado, a IA não envia resposta automática e a conversa é transferida para humano. Para o operador entender o motivo sem precisar checar configurações, um card inline é injetado no histórico da conversa imediatamente após a última mensagem do cliente. CTA direciona ao mesmo `QuickUpgradeModal` da tarja global.

---

## Fluxo do Usuário

```
[Cliente envia msg no WhatsApp/webchat]
                │
                ▼
   Gateway gera resposta IA candidata
                │
                ▼
   POST /v1/billing/usage/check-and-increment
                │
        allowed=false (trial expirado)
                │
                ▼
   Gateway bloqueia envio + dispara handoff humano
                │
                ▼
   Operador abre conversa no app/
                │
                ▼
   Renderer detecta `subscription.usage.ai_messages.allowed=false`
   AND `subscription.plan.is_trial=true`
                │
                ▼
   Injeta card "IA pausada" abaixo da última mensagem do cliente
                │
                ▼
   Operador responde manualmente OU clica "Contratar plano"
                                            │
                                            ▼
                                  abre QuickUpgradeModal
```

---

## Wireframes / Layout

### Card injetado no chat

```
┌─ Conversa: João Silva ──────────────────────────────┐
│                                                     │
│  [10:32] João: Bom dia, ainda têm o produto X?      │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │ ⚠️  IA pausada — trial expirado              │   │
│  │                                              │   │
│  │ Esta conversa foi transferida para           │   │
│  │ atendimento humano. Para reativar IA:        │   │
│  │                                              │   │
│  │           [ Contratar plano → ]              │   │
│  └──────────────────────────────────────────────┘   │
│                                                     │
│  [ Responder manualmente ────────────────────── ]   │
└─────────────────────────────────────────────────────┘
```

### Card injetado quando trial atingiu 100 msgs (não expirou por tempo)

```
│  ┌──────────────────────────────────────────────┐   │
│  │ ⚠️  IA pausada — limite do trial atingido    │   │
│  │                                              │   │
│  │ Você atingiu as 100 mensagens IA do trial.   │   │
│  │ Contrate um plano para liberar a IA:         │   │
│  │                                              │   │
│  │           [ Contratar plano → ]              │   │
│  └──────────────────────────────────────────────┘   │
```

**Elementos obrigatórios:**
- [ ] `icon`: ⚠️
- [ ] `title`: "IA pausada — trial expirado" OU "IA pausada — limite do trial atingido"
- [ ] `description`: 1-2 frases explicando handoff humano
- [ ] `cta_button`: "Contratar plano →" abre `QuickUpgradeModal`
- [ ] `dismiss`: NÃO permitido — card persiste enquanto trial expirado

**Estados:**
- **Trial ativo:** card não renderiza
- **Trial expirado por tempo:** título "trial expirado"
- **Trial expirado por mensagens:** título "limite do trial atingido"
- **Plano contratado:** card desaparece imediatamente após webhook PAYMENT_CONFIRMED reidratar `subscription`

---

## Especificação de Componentes

### ChatAiPausedBannerComponent

**Selector:** `app-chat-ai-paused-banner`
**Standalone:** sim
**Localização:** `app/src/app/pages/conversations/components/chat-ai-paused-banner/chat-ai-paused-banner.ts`

**Inputs:**
```typescript
// nenhum — consome BillingSubscriptionService
```

**Computed:**
```typescript
visible = computed(() => {
  const sub = this.subscription.value();
  return sub.plan.is_trial === true && sub.usage.ai_messages.allowed === false;
});

reason = computed<'tempo' | 'msgs'>(() => {
  const u = this.subscription.value().usage.ai_messages;
  return u.current >= u.limit ? 'msgs' : 'tempo';
});
```

**Comportamento:**
- Inserido pelo `ConversationViewComponent` após a última mensagem do cliente
- Quando `visible() === false`, não renderiza
- Click no CTA dispara mesmo flow do `TrialBannerComponent` (abre `QuickUpgradeModal`)
- Após sucesso de upgrade, componente desaparece automaticamente (re-render quando subscription emite)

**Referência de estilo:**
- Background: `var(--color-warning-50)` ou `#FEF9C3` (amarelo bem claro)
- Border: 1px solid `var(--color-warning-300)` ou `#FDE68A`
- Border-radius: 8px
- Padding: 16px
- Margin vertical: 8px (entre mensagens do chat)
- Ícone ⚠️ tamanho 20px à esquerda do título
- CTA: botão `warning` ou `primary` médio (32px height)
- Texto: 14px peso regular

---

## Validações e Regras

| Estado | Condição | Comportamento |
|---|---|---|
| visible | `is_trial=true && allowed=false` | renderiza card |
| reason=tempo | `current < limit && cycle_end <= now()` | título "trial expirado" |
| reason=msgs | `current >= limit` | título "limite do trial atingido" |

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | Card full-width do chat (mesma largura que message bubbles) |
| Tablet (768–1024px) | Card max-width 480px alinhado à esquerda |
| Desktop (> 1024px) | Card max-width 560px alinhado à esquerda |

---

## Acessibilidade

- `role="alert"` no container
- `aria-live="assertive"` (mensagem operacional importante)
- CTA com label semântico: "Abrir modal de contratação de plano"
- Cores devem passar WCAG AA

---

## Critérios de Aprovação Visual

- [ ] Card aparece imediatamente após bloqueio IA (sem refresh manual)
- [ ] 2 variantes (tempo vs msgs) implementadas com mensagens distintas
- [ ] CTA abre o mesmo `QuickUpgradeModal` da `TrialBanner`
- [ ] Card desaparece imediatamente após upgrade confirmado
- [ ] Operador consegue responder manualmente sem que o card atrapalhe o input
- [ ] Tested em conversations longas (>50 msgs) — card permanece visível e referenciado
