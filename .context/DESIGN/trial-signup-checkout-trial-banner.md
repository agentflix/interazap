---
feature: trial-signup-checkout
tipo: component-spec
status: rascunho
aprovado-por: pending
data: 2026-05-25
---

# Design: TrialBannerComponent

> Sticky banner global no `MainLayoutComponent` que comunica estado de trial.
> Aparece em **todas as telas autenticadas** quando `subscription.plan.is_trial=true`.

---

## Visão Geral

Banner persistente que comunica progresso e urgência do trial em todas as telas logadas. Três estados visuais escalonam pressão de conversão sem bloquear navegação: normal (informativo), alerta (atenção amarela em `<48h` ou `>=80 msgs`), expirado (urgência vermelha + IA pausada).

---

## Fluxo do Usuário

```
[Login/Signup] → [Layout principal carrega] → [TrialBanner avalia subscription]
  ├─ não é trial         → banner não renderiza
  ├─ trial normal        → banner azul + dias/msgs + CTA
  ├─ trial alerta        → banner amarelo + barra + CTA
  └─ trial expirado      → banner vermelho + CTA pulsante
                              │
                              ▼
                        [clica Contratar] → abre QuickUpgradeModal
```

---

## Wireframes / Layout

### Estado 1 — Normal (azul)

```
┌────────────────────────────────────────────────────────────────────────────┐
│ 🎁 Trial ativo · 5 dias restantes · 42/100 msgs IA      [ Contratar → ]   │
├────────────────────────────────────────────────────────────────────────────┤
│ InteraZap      Conversas   Contatos   Campanhas   Configurações    👤 RS  │
```

**Trigger:** `is_trial=true` AND `cycle_end - now() > 48h` AND `message_count < 80`

### Estado 2 — Alerta (amarelo, com barra inline)

```
┌────────────────────────────────────────────────────────────────────────────┐
│ ⚠️  Trial acaba em 1 dia · 87/100 msgs IA               [ Contratar → ]   │
│    ████████████████████████████████████░░░░░  87%                          │
```

**Trigger:** `is_trial=true` AND (`cycle_end - now() <= 48h` OR `message_count >= 80`)

### Estado 3 — Expirado (vermelho, CTA pulsa)

```
┌────────────────────────────────────────────────────────────────────────────┐
│ 🔴 Trial expirado · IA pausada                  [ Contratar agora → ]     │
```

**Trigger:** `is_trial=true` AND (`cycle_end <= now()` OR `message_count >= 100`)

**Elementos obrigatórios:**
- [ ] `icon`: emoji ou ícone Material (🎁 normal, ⚠️ alerta, 🔴 expirado)
- [ ] `status_text`: descrição curta do estado em PT-BR
- [ ] `counter_text`: "N/100 msgs IA" — sempre visível
- [ ] `days_left`: "N dias restantes" — só nos estados normal/alerta
- [ ] `progress_bar`: barra inline 0-100% — só no estado alerta
- [ ] `cta_button`: "Contratar →" / "Contratar agora →" — sempre visível, abre `QuickUpgradeModal`

**Estados:**
- **Loading subscription:** banner não renderiza (skeleton invisível para evitar flash)
- **Sem trial:** componente retorna null
- **Erro ao carregar subscription:** banner não renderiza, logado em console (no fail-loud em layout)
- **Banner dispensado:** NÃO permitido — banner é sempre persistente até conversão

---

## Especificação de Componentes

### TrialBannerComponent

**Selector:** `app-trial-banner`
**Standalone:** sim (Angular 20)
**Localização:** `app/src/app/core/components/trial-banner/trial-banner.ts`

**Inputs:**
```typescript
// nenhum — consome BillingSubscriptionService diretamente
```

**Injeções:**
```typescript
private subscription = inject(BillingSubscriptionService);
private modal = inject(MatDialog); // ou wrapper interno padrão do projeto
```

**Computed signals:**
```typescript
visible = computed(() => this.subscription.plan().is_trial === true);
state = computed<'normal'|'alerta'|'expirado'>(() => {
  const usage = this.subscription.aiUsage();
  const now = Date.now();
  const cycleEnd = new Date(usage.cycle_end).getTime();
  if (cycleEnd <= now || usage.current >= usage.limit) return 'expirado';
  const hoursLeft = (cycleEnd - now) / 3.6e6;
  if (hoursLeft <= 48 || usage.current >= 80) return 'alerta';
  return 'normal';
});
```

**Comportamento:**
- Renderiza apenas se `visible() === true`
- Cor de fundo determinada por `state()` via classes CSS (`.banner--normal`, `.banner--alerta`, `.banner--expirado`)
- Botão "Contratar" abre `QuickUpgradeModalComponent`
- Atualiza automaticamente quando `BillingSubscriptionService` emite novo valor (após mensagem IA enviada → re-fetch via `EventBus` ou polling 30s)
- Mobile: stack vertical (icon+text em linha 1, CTA full-width em linha 2)

**Referência de estilo:**
- Background normal: `var(--color-info-50)` ou hex `#EBF4FF` (azul claro)
- Background alerta: `var(--color-warning-100)` ou `#FEF3C7` (amarelo)
- Background expirado: `var(--color-danger-100)` ou `#FEE2E2` (vermelho claro), CTA com `animation: pulse 2s infinite`
- Texto: peso 500, tamanho 14px desktop / 13px mobile
- CTA: botão padrão InteraZap `primary` no estado normal; `warning` no alerta; `danger` no expirado
- Altura: 48px desktop, 64px mobile (com barra), 80px expirado mobile

---

## Validações e Regras

| Campo | Tipo | Obrigatório | Validação | Mensagem |
|---|---|---|---|---|
| visible | derivado | sim | `subscription.plan().is_trial` | N/A |
| state | derivado | sim | enum 'normal'\|'alerta'\|'expirado' | N/A |
| CTA click | handler | sim | abre QuickUpgradeModal | N/A |

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | Texto quebra em 2 linhas; CTA vira full-width abaixo; barra inline ocupa 100% |
| Tablet (768–1024px) | Layout single-line com truncamento se >85 chars |
| Desktop (> 1024px) | Single-line, alinhamento space-between, CTA à direita |

---

## Acessibilidade

- `role="banner"` no container
- `aria-live="polite"` para mudanças de estado (alerta/expirado)
- CTA com `aria-label="Abrir modal de contratação de plano"` quando o texto for "Contratar agora →"
- Cores devem passar WCAG AA (contraste ≥ 4.5:1 para texto regular)
- Foco visível no CTA via outline (não remover)

---

## Critérios de Aprovação Visual

- [ ] Layout corresponde aos 3 wireframes acima
- [ ] Estados (normal, alerta, expirado) implementados e testados via fixtures
- [ ] Responsividade testada em iPhone SE (375px), iPad (768px), desktop (1440px)
- [ ] Acessibilidade: testado com leitor de tela + navegação por teclado
- [ ] Integração com `BillingSubscriptionService` real (não mock) na build de QA
- [ ] CTA abre `QuickUpgradeModalComponent`
- [ ] Banner persiste através de navegação SPA sem flash
