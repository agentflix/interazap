# Debug: Platform Invoices — Modal cancelar e botão Criar Fatura

## Data
2026-04-30

## Problema
Na tela `http://localhost:4200/platform/invoices`:
1. Ao clicar em **Cancelar** no modal de confirmação de cancelamento de fatura, nada acontecia.
2. O botão **Criar Fatura** no modal de nova fatura aparecia vazio/quebrado (sem texto).

## Causa Raiz

### Bug 1 — Modal não fecha ao clicar "Cancelar"
O componente `af-confirm-modal` expõe os outputs `confirmed` e `cancelled`, mas **não** expõe `closed`. O `platform-invoices.html` estava escutando `(closed)="closeCancelModal()"`, evento que nunca era emitido pelo `af-confirm-modal`.

Quando o usuário clicava em "Cancelar", o `af-confirm-modal` emitia `cancelled`, mas o pai não estava ouvindo esse evento.

Arquivo: `app/src/app/pages/platform/invoices/platform-invoices.html:244`

### Bug 2 — Botão Criar Fatura sem texto
O componente `af-loading-button` renderiza seu texto via `<ng-content />` (conteúdo entre as tags de abertura e fechamento). No entanto, o `platform-invoices.html` passava o texto pelo atributo `label="Criar Fatura"` e usava self-closing tag (`/>`). Como o componente não possui input `label`, e não havia conteúdo projetado, o botão aparecia vazio.

Arquivo: `app/src/app/pages/platform/invoices/platform-invoices.html:226`

## Correção

### Bug 1
Trocar `(closed)="closeCancelModal()"` por `(cancelled)="closeCancelModal()"` no `af-confirm-modal`.

```html
<!-- Antes -->
(confirmado)="confirmCancel()"
(closed)="closeCancelModal()"

<!-- Depois -->
(confirmed)="confirmCancel()"
(cancelled)="closeCancelModal()"
```

### Bug 2
Trocar self-closing tag com atributo `label` por tag com conteúdo projetado.

```html
<!-- Antes -->
<af-loading-button
  label="Criar Fatura"
  [loading]="isCreating()"
  ...
/>

<!-- Depois -->
<af-loading-button
  [loading]="isCreating()"
  ...
>
  Criar Fatura
</af-loading-button>
```

## Validação
- Build Angular (`ng build`) passou sem erros.

## Arquivos Alterados
- `app/src/app/pages/platform/invoices/platform-invoices.html`

## Lição Aprendida
- `af-confirm-modal` nunca emite `(closed)`; sempre usar `(cancelled)` para ações de cancelamento.
- `af-loading-button` não aceita `label` como input; o texto deve ser passado como conteúdo projetado (entre as tags).
- Ao usar componentes do design system, sempre verificar a API exposta (inputs/outputs) no código-fonte do componente, não apenas no template de uso.
