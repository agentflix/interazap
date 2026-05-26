---
feature: trial-signup-checkout
tipo: component-spec
status: rascunho
aprovado-por: pending
data: 2026-05-25
---

# Design: QuickUpgradeModalComponent

> Modal inline de 2 steps para conversão trial → plano pago.
> Step 2 usa SDK Asaas para tokenização de cartão no browser (PCI SAQ-A).

---

## Visão Geral

Modal fullscreen-on-mobile / centered-card-on-desktop que conduz tenant em trial pelos 2 passos da conversão: escolha de plano (3 cards) e entrada de cartão tokenizado. Mostra urgência ("trial X/100 msgs"), preserva contexto ("conversas e contatos serão preservados"), e finaliza a cobrança em uma única tela sem redirect.

---

## Fluxo do Usuário

```
[clica Contratar no TrialBanner ou Chat]
                  │
                  ▼
        [QuickUpgradeModal abre — Step 1]
                  │
        escolhe Starter / Pro / Business
                  │
                  ▼
        [Step 2: Form de cartão]
                  │
   preenche dados → submit
                  │
                  ▼
        SDK Asaas tokeniza no browser
                  │
   ┌─── sucesso tokenize ───┐
   │                        │
   ▼                        ▼
POST /billing/upgrade-from-trial    erro tokenize → exibe inline
   │
   ┌── 3DS challenge ──┐
   │                   │
   ▼                   ▼
 OK 3DS              cancelado
   │                   │
   ▼                   ▼
charge CONFIRMED    erro PT-BR
   │
   ▼
[Modal Success — 2s] → fecha → recarrega subscription → tarja some
```

---

## Wireframes / Layout

### Step 1 — Escolha de plano

```
┌──────────────── Escolha seu plano ─────────────────[ × ]─┐
│                                                          │
│  🎁 Trial: 42/100 mensagens usadas                       │
│     Suas conversas e contatos serão preservados.         │
│                                                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│  │  Starter    │  │   Pro ⭐    │  │  Business   │      │
│  │             │  │ Recomendado │  │             │      │
│  │  R$ 97/mês  │  │ R$ 297/mês  │  │ R$ 897/mês  │      │
│  │             │  │             │  │             │      │
│  │ ✓ 1.000 msg │  │ ✓ 5.000 msg │  │ ✓ 20.000 msg│      │
│  │ ✓ 1 inst.   │  │ ✓ 3 inst.   │  │ ✓ 10 inst.  │      │
│  │ ✓ 2 users   │  │ ✓ 10 users  │  │ ✓ 50 users  │      │
│  │             │  │             │  │             │      │
│  │ [ Escolher ]│  │ [ Escolher ]│  │ [ Escolher ]│      │
│  └─────────────┘  └─────────────┘  └─────────────┘      │
│                                                          │
│  💳 Checkout seguro · cobrança recorrente mensal         │
│                                                          │
│           [ Ver todos os planos ]   [ Cancelar ]         │
└──────────────────────────────────────────────────────────┘
```

### Step 2 — Cartão (transparente)

```
┌──────────────── Contratar Pro ──────────────────────[ × ]─┐
│  ← Voltar         Plano Pro · R$ 297/mês · 5.000 msgs IA  │
│                                                           │
│  Cartão                                                   │
│  ┌──────────────────────────────────────────────┐         │
│  │ 1234 5678 9012 3456                          │         │
│  └──────────────────────────────────────────────┘         │
│  ┌─────────┐  ┌─────────┐                                 │
│  │ MM/AA   │  │ CVV     │                                 │
│  └─────────┘  └─────────┘                                 │
│                                                           │
│  ┌──────────────────────────────────────────────┐         │
│  │ Nome no cartão                               │         │
│  └──────────────────────────────────────────────┘         │
│                                                           │
│  ☐ Aceito os termos de uso e política de privacidade      │
│                                                           │
│              [ Confirmar e ativar — R$ 297 ]              │
│                                                           │
│  🔒 Pagamento processado por Asaas · seus dados de         │
│     cartão não passam pelos servidores InteraZap          │
└───────────────────────────────────────────────────────────┘
```

### Step 3 — Loading (charge em processamento)

```
┌──────────────── Processando pagamento ─────────────[ × ]─┐
│                                                          │
│                    [ Spinner animado ]                   │
│                                                          │
│           Confirmando pagamento com a operadora...       │
│                                                          │
│            (Não feche esta janela — leva 5-15s)          │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Step 4 — Sucesso

```
┌────────────────────── ✅ ──────────────────────────[ × ]─┐
│                                                          │
│              Plano Pro ativado com sucesso!              │
│                                                          │
│        Próxima cobrança: 25/jun/2026 — R$ 297            │
│                                                          │
│              [ Começar a usar →  ]                       │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

### Estado de erro (declined / 3DS cancelado)

```
┌──────────────── Pagamento não aprovado ────────────[ × ]─┐
│                                                          │
│  ⚠️  Seu cartão foi recusado pela operadora.             │
│                                                          │
│      Motivo: Cartão sem limite suficiente                │
│                                                          │
│      Você pode tentar outro cartão ou contatar           │
│      seu banco e tentar novamente.                       │
│                                                          │
│         [ Trocar cartão ]   [ Falar com suporte ]        │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

**Elementos obrigatórios Step 1:**
- [ ] `usage_summary`: "Trial: N/100 mensagens usadas · conversas preservadas"
- [ ] 3x `plan_card`: reusa `PlanCardComponent` existente. Pro marcado "Recomendado ⭐"
- [ ] `cta_choose`: botão "Escolher" em cada card
- [ ] `security_note`: "💳 Checkout seguro · cobrança recorrente mensal"
- [ ] `link_all_plans`: "Ver todos os planos" → abre `/settings/my-plan` em nova aba
- [ ] `cancel`: botão "Cancelar" fecha modal

**Elementos obrigatórios Step 2:**
- [ ] `back_button`: "← Voltar" volta para Step 1
- [ ] `plan_summary`: "Plano Pro · R$ 297/mês · 5.000 msgs IA"
- [ ] `card_number_input`: format `9999 9999 9999 9999`, máx 19 chars
- [ ] `expiry_input`: format `MM/AA`, máx 5 chars
- [ ] `cvv_input`: máx 4 chars (Amex), inputmode="numeric"
- [ ] `holder_name_input`: required, uppercase
- [ ] `terms_checkbox`: required
- [ ] `submit_button`: "Confirmar e ativar — R$ 297"
- [ ] `asaas_note`: "🔒 Pagamento processado por Asaas..."

**Estados:**
- **Step 1 default:** 3 cards visíveis, plano atual (trial) destacado com badge "Atual"
- **Step 2 default:** form vazio, submit disabled
- **Step 2 tokenizando:** spinner no botão, form readonly
- **Step 2 3DS challenge:** abre iframe Asaas inline (SDK gerencia)
- **Step 3 processando:** loading inteligente com mensagem progressiva
- **Step 4 sucesso:** mensagem + CTA "Começar a usar →" que fecha modal e força refresh do subscription state
- **Erro tokenize:** inline abaixo do form com mensagem específica do SDK
- **Erro charge:** screen dedicada "Pagamento não aprovado" com 2 CTAs

---

## Especificação de Componentes

### QuickUpgradeModalComponent

**Selector:** `app-quick-upgrade-modal`
**Standalone:** sim
**Localização:** `app/src/app/pages/billing/quick-upgrade-modal/quick-upgrade-modal.ts`
**Modo:** dialog (MatDialog ou wrapper interno)

**Inputs:**
```typescript
// nenhum — consome BillingPlansService + BillingSubscriptionService
```

**State signals:**
```typescript
step = signal<1 | 2 | 'processing' | 'success' | 'error'>(1);
selectedPlan = signal<Plan | null>(null);
errorMessage = signal<string | null>(null);
```

**Form Step 2 (Reactive Forms):**
```typescript
cardForm = this.fb.group({
  number: ['', [Validators.required, this.validators.cardNumber]],
  expiry: ['', [Validators.required, this.validators.expiry]],
  cvv: ['', [Validators.required, Validators.minLength(3)]],
  holder_name: ['', [Validators.required]],
  accept_terms: [false, Validators.requiredTrue],
});
```

**Comportamento:**
- Step 1 → escolher plano → step 2
- Step 2 submit:
  ```typescript
  const tokenResp = await this.asaasCheckout.tokenizeCard(cardForm.value);
  // tokenResp = { token, brand, last4 }
  this.step.set('processing');
  await this.api.post('/billing/upgrade-from-trial', {
    plan_id: selectedPlan.id,
    card_token: tokenResp.token,
  });
  this.step.set('success');
  ```
- Erro tokenize → permanece em step 2, exibe `errorMessage`
- Erro charge → step 'error' + CTAs trocar cartão / suporte
- Success → re-fetch `BillingSubscriptionService.subscription()` → fecha modal após 2s ou click em CTA

### AsaasCheckoutService

**Selector:** N/A
**Standalone:** sim
**Localização:** `app/src/app/core/services/asaas-checkout.service.ts`

**Métodos:**
```typescript
async tokenizeCard(payload: CardInput): Promise<{ token: string; brand: string; last4: string }>;
async handle3DSChallenge(transactionId: string): Promise<boolean>;
```

**Comportamento:**
- Carrega SDK Asaas via `script` injection no `index.html` (defer)
- `tokenizeCard` chama `window.Asaas.tokenize(...)` retornando token
- Erros do SDK convertidos para mensagens PT-BR via dicionário interno
- 3DS modal é gerenciado pelo próprio SDK (iframe inline)

**Referência de estilo:**
- Modal width desktop: 600px (Step 1), 480px (Step 2)
- Modal mobile: fullscreen
- Border-radius: 12px (desktop)
- Backdrop: rgba(0,0,0,0.5)
- Transição: fade-in 200ms + scale 0.95→1
- Cards step 1: card "Pro" tem border `var(--color-primary)` + badge "⭐ Recomendado"
- CTA confirmar: botão `primary lg` (48px height) sempre habilitado quando form válido

---

## Validações e Regras

| Campo | Tipo | Obrigatório | Validação | Mensagem |
|---|---|---|---|---|
| number | masked text | sim | Luhn algorithm | "Número de cartão inválido" |
| expiry | masked MM/AA | sim | data futura | "Data de expiração inválida" |
| cvv | numeric | sim | 3-4 dígitos | "CVV inválido" |
| holder_name | text | sim | min 2 chars | "Informe o nome do titular" |
| accept_terms | checkbox | sim | true | "Aceite os termos para continuar" |

Mapeamento de erros Asaas:
- `INVALID_CREDIT_CARD` → "Cartão inválido. Verifique os dados."
- `CARD_DECLINED_BY_ISSUER` → "Cartão recusado pela operadora. Tente outro."
- `INSUFFICIENT_FUNDS` → "Cartão sem limite suficiente."
- `EXPIRED_CARD` → "Cartão expirado."
- `3DS_CHALLENGE_FAILED` → "Verificação 3DS falhou. Tente outro cartão."
- `GENERIC` → "Não conseguimos processar. Tente novamente em alguns minutos."

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | Modal fullscreen; plan cards em coluna; form vertical com padding 16px |
| Tablet (768–1024px) | Modal 600px Step 1 / 480px Step 2; plan cards em 2 colunas |
| Desktop (> 1024px) | Modal 600px Step 1 / 480px Step 2; plan cards em 3 colunas |

---

## Acessibilidade

- `role="dialog"` + `aria-modal="true"`
- `aria-labelledby` no título "Escolha seu plano" / "Contratar Pro"
- Foco trap dentro do modal
- ESC fecha modal (com confirmação se Step 2 em progresso)
- Foco inicial: primeiro plan card (Step 1), primeiro input (Step 2)
- Anúncio aria-live para mudanças de step e mensagens de erro

---

## Critérios de Aprovação Visual

- [ ] Step 1 exibe 3 plan cards com Pro destacado
- [ ] Step 2 mantém visual minimalista de checkout
- [ ] Mensagem de Asaas processando dados é visível em Step 2
- [ ] Erros são exibidos inline (token) ou em screen dedicada (charge)
- [ ] 3DS modal funciona inline sem trocar de aba
- [ ] Estado de sucesso exibe próxima data de cobrança
- [ ] Mobile fullscreen com gestures nativos (swipe-down ainda fecha modal apenas se em Step 1)
- [ ] Após sucesso, banner trial some imediatamente
