# Design Spec — FEAT-050 Meta Templates + Composer 24h

> **Escopo:** TASK-050.C2 (lista admin), TASK-050.C3 (form criar/editar), TASK-050.D1+D2 (composer condicional + selector compartilhado).
> **Stack visual:** Tailwind tokens + design system InteraZap (`app/src/app/shared/components/`).
> **Regras invioláveis:** apenas tokens semânticos, sempre 4 estados (loading/empty/error/default), nenhum componente novo se houver shared equivalente.

---

## 0. Mapa de status Meta → tokens visuais

| Status Meta | Label PT-BR       | Componente   | Variante / cor      | Notas                                                                  |
| ----------- | ----------------- | ------------ | ------------------- | ---------------------------------------------------------------------- |
| `approved`  | Aprovado          | `<af-badge>` | `variant="success"` | dot opcional                                                           |
| `pending`   | Em aprovação      | `<af-badge>` | `variant="warning"` | `dot` para indicar atividade                                           |
| `rejected`  | Rejeitado         | `<af-badge>` | `variant="danger"`  | sufixo: ícone `lucideAlertCircle` + `<af-tooltip>` com rejected_reason |
| `paused`    | Pausado pela Meta | `<af-badge>` | `variant="warning"` | tooltip explicando pausa                                               |
| `disabled`  | Desativado        | `<af-badge>` | `variant="default"` | linha em `opacity-60`                                                  |
| `local`     | Local (sem Meta)  | `<af-badge>` | `variant="info"`    | usado na coluna **Provider**                                           |

`af-status-badge` **não** é usado aqui — sua enum (`online/offline/...`) não cobre o domínio Meta. Usamos `af-badge` que tem variantes semânticas.

---

## 1. Página `/chat/templates` — TASK-050.C2

### Layout

```
┌────────────────────────────────────────────────────────────────────────────┐
│ <af-crud-page                                                              │
│   title="Templates de mensagens"                                           │
│   subtitle="WhatsApp Business — Meta"                                      │
│   createLabel="Novo template"                                              │
│   searchPlaceholder="Buscar por nome..."                                   │
│ >                                                                          │
│   ── slot [filters] ────────────────────────────────────────────────────── │
│   [Canal ▾] [Status ▾]                          [↻ Sincronizar com Meta]  │
│   ── conteúdo: <af-data-table> ────────────────────────────────────────── │
│   Nome | Canal | Idioma | Categoria | Status | Atualizado em | Ações      │
│   ── paginação (interna do crud-page) ────────────────────────────────── │
│ </af-crud-page>                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

### Component map

| Elemento             | Componente                                 | Notas                                                                                                                    |
| -------------------- | ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| Wrapper de página    | `af-crud-page`                             | usa `title`, `subtitle`, `createLabel`, slot `filters`, paginação inclusa                                                |
| Filtro canal         | `af-select-input` (`size="sm"`)            | options carregadas de `chatInstances$` filtrados por `provider in ['meta','local']`                                      |
| Filtro status        | `af-select-input` (`size="sm"`)            | opções: Todos, Aprovado, Em aprovação, Rejeitado, Pausado, Desativado                                                    |
| Busca                | usar `searchPlaceholder` do `af-crud-page` | evita criar `af-search-input` separado                                                                                   |
| Botão sincronizar    | `af-button variant="outline" size="sm"`    | ícone `lucideRefreshCw`; `loading` enquanto request roda; só visível quando filtro canal != "todos"                      |
| Botão criar          | `af-crud-page createLabel`                 | dispara `(create)` → abre `af-modal` com `<app-template-form>`                                                           |
| Tabela               | `af-data-table` (`hoverable`)              | reuso do padrão CRM contacts                                                                                             |
| Coluna Status        | `af-badge` (mapa § 0)                      | rejected → ícone + `af-tooltip`                                                                                          |
| Coluna Provider      | `af-badge variant="info"` quando `local`   | omitir badge quando `meta` (provider implícito pelo filtro canal)                                                        |
| Coluna Atualizado em | texto + `text-xs text-neutral-400`         | formato `pt-BR` (ex.: "29 abr 2026, 14:32"); fallback "—" se `last_synced_at` nulo                                       |
| Ações por linha      | `app-table-actions` (existente)            | itens: **Visualizar**, **Editar** (disabled se Meta+approved), **Excluir** (danger)                                      |
| Loading inicial      | `af-crud-page [loading]="true"`            | usa `skeleton-table-row` interno (`skeletonColumns=7`, `skeletonRows=8`)                                                 |
| Estado vazio         | `af-crud-page [empty]="true"`              | `emptyTitle="Nenhum template ainda"` + `emptyDescription="Crie seu primeiro template e envie-o para aprovação da Meta."` |
| Estado de erro       | `af-alert variant="danger"` + `af-button`  | dentro do slot principal, padrão do golden model (CRM contacts)                                                          |

### Visual hierarchy

1. Título "Templates de mensagens" + CTA "Novo template" (top-right do header).
2. Filtros (canal, status) + ação secundária "Sincronizar".
3. Tabela com **status** sendo a coluna de maior peso visual (badges coloridos).
4. Meta-info (idioma, categoria, última sync) em `text-xs text-neutral-400`.

### States

| State                             | Comportamento                                                                                                   |
| --------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Loading (initial)                 | `af-crud-page [loading]="true"` — skeleton 8 linhas × 7 colunas                                                 |
| Loading (filtro/paginação)        | overlay leve `opacity-50` no `<af-data-table>` (mantém estrutura)                                               |
| Empty (sem dados, sem filtro)     | `emptyTitle="Nenhum template ainda"` + CTA "Criar template"                                                     |
| Empty (filtro vazio)              | `emptyTitle="Nenhum template encontrado"` + `emptyDescription="Ajuste os filtros ou limpe a busca."`            |
| Error                             | `af-alert variant="danger" title="Erro ao carregar templates"` + botão "Tentar novamente" (`af-button outline`) |
| Sync em andamento                 | botão sync com `loading` + toast `success` ao concluir (`X templates sincronizados`)                            |
| Sync com erro                     | toast `danger` + manter lista anterior                                                                          |
| Webhook chega (status atualizado) | linha pisca brevemente (`bg-neutral-100 dark:bg-neutral-800` por 1.2s) + badge atualiza                         |

### Spacing & typography

- Padding página: `p-6` (desktop) / `p-4` (mobile)
- Gap entre filtros: `gap-2`
- Header da tabela: `text-xs font-medium uppercase tracking-wide text-neutral-500`
- Linhas: `text-sm`
- Meta-info inline: `text-xs text-neutral-400`

### Tokens de cor (apenas semânticos)

- Background: `bg-neutral-50 dark:bg-neutral-950`
- Card/tabela: `bg-white dark:bg-neutral-900`
- Bordas: `border-neutral-200 dark:border-neutral-800`
- Hover de linha: `hover:bg-neutral-50 dark:hover:bg-neutral-800/50`

### Interações

| Elemento                | Hover                           | Focus              | Active  |
| ----------------------- | ------------------------------- | ------------------ | ------- |
| Linha da tabela         | `bg-neutral-50 dark:bg-...`     | —                  | —       |
| Botão "Novo template"   | herdado de `af-button`          | herdado            | herdado |
| Tooltip rejected_reason | aparece em hover/focus do ícone | `aria-describedby` | —       |
| Ações por linha         | herdado de `app-table-actions`  | herdado            | herdado |

### Responsive

| Breakpoint | Comportamento                                                                                                                                          |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `< 768px`  | Colunas Idioma + Categoria + Atualizado em colapsam em uma única coluna "Detalhes" (sub-texto). Filtros viram `af-sheet` acionada por botão "Filtros". |
| `>= 768px` | Layout completo com 7 colunas.                                                                                                                         |

### Acessibilidade

- `<table>` interna do `af-data-table` já provê `<thead><th scope="col">`.
- Cada `af-badge` de status acompanha texto **legível** ("Rejeitado") — nunca apenas cor.
- Ícone de aviso em rejected: `<button aria-label="Ver motivo da rejeição" aria-describedby="reason-{{id}}">`; tooltip com `role="tooltip"`.
- Botão "Sincronizar" anuncia resultado via `aria-live="polite"` (toast).
- Foco visível: `focus-visible:ring-2 focus-visible:ring-accent-500` (padrão do design system).

### What NOT to do

- [ ] Não criar inline `<span class="...rounded-full bg-green-500">` — usar `af-badge`.
- [ ] Não criar `<table>` cru — usar `af-data-table`.
- [ ] Não exibir motivo de rejeição como texto solto na linha (ruído visual) — sempre dentro de tooltip.
- [ ] Não traduzir status no payload (o backend mantém `pending|approved|...` em inglês); a tradução fica **só na camada visual**.
- [ ] Não criar componente novo `template-status-badge` — `af-badge` + helper `templateStatusVariant(status)` já resolve.

---

## 2. Form criar/editar — TASK-050.C3

### Layout

Apresentado dentro de `af-modal` (size `lg`) ou rota `/chat/templates/new` (escolha do FRONTEND). Layout em **2 colunas** no desktop:

```
┌─────────────────────────────┬───────────────────────────────────────┐
│ COLUNA ESQUERDA (form)      │ COLUNA DIREITA (preview sticky)       │
│                             │                                       │
│ [Canal Meta ▾]              │  ┌─────────────────────────────┐     │
│ [Nome (snake_case)]         │  │ 🟢 WhatsApp preview          │     │
│ [Idioma ▾] [Atalho]         │  │ ┌────────────────────────┐  │     │
│ [Categoria ◉ ◯ ◯]           │  │ │ {header (se houver)}   │  │     │
│                             │  │ │ {body com placeholders}│  │     │
│ ── Componentes ──           │  │ │ {footer}               │  │     │
│ [Header (opcional) ▾]       │  │ │ [btn 1] [btn 2]        │  │     │
│ [Body * (textarea)]         │  │ └────────────────────────┘  │     │
│ [Footer (opcional)]         │  └─────────────────────────────┘     │
│ [Botões + Adicionar (até 3)]│                                       │
│                             │  ⓘ Templates Meta requerem            │
│ [⚠ aviso de aprovação]      │     aprovação. Após enviar,           │
│                             │     status = "Em aprovação".          │
│ [Cancelar] [Enviar p/ Meta] │                                       │
└─────────────────────────────┴───────────────────────────────────────┘
```

No mobile a coluna preview vira **collapsible** (`af-collapsible`) acima do form, default `open`.

### Component map

| Campo                   | Componente                                                    | Validação                                                                                         |
| ----------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Canal                   | `af-select-input` (Meta only)                                 | required; readonly em edição                                                                      |
| Nome                    | `af-text-input` + helper                                      | required; pattern `^[a-z0-9_]{1,512}$`; helper "snake_case, sem espaços"; readonly em edição Meta |
| Idioma                  | `af-select-input`                                             | required; default `pt_BR`; readonly em edição Meta                                                |
| Atalho                  | `af-text-input` (`size="sm"`)                                 | optional; pattern `^/?[a-z0-9_-]+$`; **editável mesmo em Meta** (campo local)                     |
| Ativo                   | `af-switch-input`                                             | default `true`; **editável mesmo em Meta** (campo local)                                          |
| Categoria               | `af-radio-input` em `flex gap-3`                              | required; opções: Marketing / Utility / Authentication; readonly em edição Meta                   |
| Header tipo             | `af-select-input` (`size="sm"`)                               | opções: Nenhum / Texto; (rich-media fora de escopo v1)                                            |
| Header texto            | `af-text-input` (max 60 chars)                                | required se tipo=Texto                                                                            |
| Body                    | `af-textarea-input` (rows=6, max 1024)                        | required; placeholders `{{1}}`, `{{2}}`... destacados via `<mark>` no preview                     |
| Body — exemplos         | `af-text-input` repetido (1 por placeholder)                  | required quando body tem `{{N}}`                                                                  |
| Footer                  | `af-text-input` (max 60 chars)                                | optional                                                                                          |
| Botões                  | `af-button-group` + lista de `af-text-input`                  | máx 3; tipo fixo `QUICK_REPLY` na v1; cada botão `text` (max 25 chars)                            |
| Adicionar/remover botão | `af-icon-button` (`lucidePlus`/`lucideTrash2`)                | desabilita "+" quando 3 botões                                                                    |
| Aviso de aprovação      | `af-alert variant="info"` (criação) / `warning` (edição Meta) | mensagens distintas (ver § Estados)                                                               |
| Submit                  | `af-loading-button variant="primary"`                         | label dinâmico: "Enviar para aprovação Meta" / "Salvar alterações"                                |
| Cancelar                | `af-button variant="ghost"`                                   | confirma descarte se form `dirty`                                                                 |
| Erros de campo          | `af-form-error`                                               | aria-describedby; cor `text-danger`                                                               |
| Preview                 | div estilizada (mock visual de bolha WhatsApp)                | reusa `af-chat-bubble` com variante `outgoing` se aceitar slot livre; senão div local             |

### Modos do form

| Modo               | Comportamento                                                                                                                                                                                                                                                                                                           |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Criar (Meta)**   | Todos os campos editáveis. `af-alert variant="info"` topo: "Templates Meta requerem aprovação. Após enviar, o status ficará como **Em aprovação** até a Meta responder (24–48h)."                                                                                                                                       |
| **Editar (Meta)**  | Campos `name`, `language`, `category`, `components.*` com `[readonly]="true"` + `disabled` visual (`opacity-60 cursor-not-allowed`). Apenas `is_active` (switch) e `shortcut` editáveis. `af-alert variant="warning"` topo: "Templates Meta não podem ser alterados. Para mudanças no conteúdo, exclua e crie um novo." |
| **Criar (local)**  | (fora do escopo C3, mas form deve suportar) sem aviso de aprovação.                                                                                                                                                                                                                                                     |
| **Submitting**     | `af-loading-button [loading]="true"`; campos `disabled`; cancelar permanece habilitado.                                                                                                                                                                                                                                 |
| **Erro de submit** | `af-alert variant="danger" [dismissible]="true"` no topo + foco automático no primeiro campo com erro server-side.                                                                                                                                                                                                      |

### Validações & UX

- **snake_case visual:** abaixo do input nome, mostrar preview transformado em snake_case quando o usuário digitar com espaço/maiúscula (auto-suggestion).
- **Placeholders detectados:** ao digitar `{{1}}` no body, automaticamente cria input "Exemplo para {{1}}" abaixo (até 10).
- **Contador de caracteres:** `text-xs text-neutral-400` em body/footer/header (`{{count}} / {{max}}`).
- **Preview reativo:** `effect()` que reage às mudanças do form e atualiza a bolha (com `untracked()` para evitar loops).

### States

| State                               | Implementação                                                                                       |
| ----------------------------------- | --------------------------------------------------------------------------------------------------- |
| Loading (load template para edição) | `af-spinner` central + form oculto até carregar                                                     |
| Default                             | Form completo + preview vazio se body vazio ("Comece digitando o corpo da mensagem...")             |
| Validação client-side falha         | inline `af-form-error` por campo; submit `disabled` enquanto inválido                               |
| Submitting                          | `af-loading-button` + form `disabled`                                                               |
| Erro server-side                    | `af-alert danger` topo + erros mapeados para campos                                                 |
| Sucesso                             | toast `success` "Template enviado para aprovação" / "Template atualizado" + fecha modal/redireciona |

### Spacing & typography

- Modal padding: `p-6`
- Espaço entre seções (Identificação / Componentes / Botões): `gap-6`
- Espaço entre campos: `gap-4`
- Labels: `text-xs font-medium text-neutral-500`
- Helpers: `text-xs text-neutral-400`
- Preview: `text-sm` dentro de bolha verde WhatsApp (`bg-green-100 dark:bg-green-950/40`)

### Acessibilidade

- Cada `af-radio-input` em fieldset/legend semântico ("Categoria do template").
- `aria-invalid="true"` em campos com erro.
- Modal com `role="dialog"`, `aria-modal="true"`, foco inicial no primeiro input (Canal em criação; Atalho em edição Meta).
- Trap de foco dentro do modal; `Esc` fecha (com confirmação se dirty).
- Botões de adicionar/remover botão têm `aria-label="Adicionar botão de resposta rápida"` / `"Remover botão {{label}}"`.

### What NOT to do

- [ ] Não permitir editar `name`/`language`/`category`/`components` em template Meta — usar `[readonly]` + visual disabled, **não** apenas validar no submit.
- [ ] Não esconder o preview em desktop — é parte da hierarquia visual.
- [ ] Não usar cor verde WhatsApp fora do preview (mantém marca alheia isolada).
- [ ] Não dispensar o aviso de aprovação — sempre exibir antes do submit em criação.

---

## 3. Composer condicional — TASK-050.D1, D2

### Estados (recap do feature doc)

```
provider !== 'meta'                                → 'free'           (atual)
provider === 'meta'  &&  canSendFreeText === true  → 'mixed'
provider === 'meta'  &&  canSendFreeText === false → 'template-only'
```

### Layout — modo `free` (atual, sem mudança)

Mantém o `<footer>` em `chat-conversation-component.html` exatamente como está hoje:
`[+ anexo] [emoji] <textarea> [send/mic]`.

### Layout — modo `mixed` (Meta + dentro da janela)

Adiciona **um único** botão à esquerda do textarea, na mesma linha do `+ anexo`:

```
[+ anexo] [📋 Template] [emoji] <textarea> [send/mic]
```

- **Botão Template:** `af-icon-button` com ícone `lucideMessageSquareText`, `aria-label="Enviar template aprovado"`, `tooltip="Enviar template"`.
- Ao clicar: abre `app-template-selector` em **popover** (desktop) ou **`af-sheet`** (mobile).
- Após selecionar template + preencher params + confirmar: o composer entra em estado "template pronto" (substituindo o textarea por um chip resumo `Template: boas_vindas (pt_BR) · 2 params`) com botões `[Editar] [Cancelar] [Enviar]`.

### Layout — modo `template-only` (Meta + janela expirada)

Substitui **completamente** o bloco do textarea (mas mantém o `+ anexo` desabilitado com tooltip "Anexos só após reabrir a janela com um template"):

```
┌────────────────────────────────────────────────────────────────────────┐
│ <af-banner variant="warning">                                          │
│   ⚠ Janela de 24h expirada                                             │
│   O cliente não responde há mais de 24h. Para reabrir a conversa,      │
│   envie um template aprovado pela Meta.                                │
│ </af-banner>                                                           │
│                                                                        │
│ ┌─────────────────────────────────────────────────────────────────┐   │
│ │  📋  Selecione um template aprovado                              │   │
│ │                                                                  │   │
│ │  [<af-button variant="primary" size="md">                        │   │
│ │    Selecionar template                                           │   │
│ │  </af-button>]                                                   │   │
│ └─────────────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────────────┘
```

Ao clicar em "Selecionar template" → abre `app-template-selector` em **modal centrado** (`af-modal` size `md`) com preview e form de variáveis (já existente, ver § 3.4).

### Component map

| Elemento                         | Componente                                               | Notas                                                                    |
| -------------------------------- | -------------------------------------------------------- | ------------------------------------------------------------------------ |
| Banner janela expirada           | `af-banner variant="warning"`                            | `[message]` longa: ver § Texto canônico abaixo                           |
| CTA "Selecionar template"        | `af-button variant="primary" size="md"`                  | ícone `lucideMessageSquareText`; full-width no mobile                    |
| Botão template inline (mixed)    | `af-icon-button`                                         | mesma altura do `+ anexo`                                                |
| Picker container (desktop)       | `af-popover` (anchor no botão)                           | largura fixa 380px; scroll interno                                       |
| Picker container (mobile)        | `af-sheet` (bottom)                                      | altura `70vh`                                                            |
| Picker container (template-only) | `af-modal` size `md`                                     | trap de foco                                                             |
| Conteúdo do picker               | `app-template-selector` (movido p/ shared em D1)         | já contém: search, select, params form, preview                          |
| Chip de template selecionado     | `af-badge variant="info"` + `af-icon-button` (`lucideX`) | aparece em modo `mixed` quando há template em rascunho                   |
| Send                             | botão `lucideSend` existente                             | mesmo handler para texto/template — diferenciação no `chat.ts` (TASK-D6) |

### Texto canônico

| Estado             | Texto                                                                                                                             |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------- |
| Banner warning     | **Janela de 24h expirada.** O cliente não responde há mais de 24h. Para reabrir a conversa, envie um template aprovado pela Meta. |
| Botão              | Selecionar template                                                                                                               |
| Tooltip mixed      | Enviar template aprovado                                                                                                          |
| Toast sucesso      | Template enviado. Aguardando entrega.                                                                                             |
| Toast erro 422     | Não foi possível enviar: {{error}}                                                                                                |
| Anexo desabilitado | Anexos disponíveis após reabrir a conversa com um template.                                                                       |

### States do composer

| Mode                   | windowStatus                          | Composer renderiza                                                                    |
| ---------------------- | ------------------------------------- | ------------------------------------------------------------------------------------- |
| `free`                 | `null`                                | textarea atual                                                                        |
| `mixed`                | `{ canSendFreeText: true }`           | textarea + botão template inline; chip se template em rascunho                        |
| `template-only`        | `{ canSendFreeText: false}`           | banner warning + CTA `Selecionar template` (textarea oculto via `@if`)                |
| Loading status         | `null` enquanto carrega               | mostrar `af-skeleton` no rodapé (`h-24 w-full rounded-md`) — evita flash de UI errado |
| Erro carregando status | reverter para `free` + log silencioso | (defesa em profundidade: o backend bloqueará via 422 se for o caso)                   |
| Reconexão WS           | revalidar windowStatus                | sem flash visual — apenas trocar `composerMode` se mudou                              |

### Spacing & typography

- Banner: `mx-3 mt-2` (alinha com padding do `<footer>`)
- CTA card: `m-3 p-4 rounded-lg border border-warning/20 bg-warning/5`
- Texto banner: `text-sm`
- Tooltip: `text-xs`

### Tokens de cor

- Banner warning: handled by `af-banner variant="warning"` (não hardcodar).
- Card CTA: `bg-warning/5 border-warning/20` (tokens semânticos do design system).
- Chip template: `af-badge variant="info"` (não criar variante nova).

### Interações

| Elemento                  | Hover                          | Focus                  | Active      |
| ------------------------- | ------------------------------ | ---------------------- | ----------- |
| Botão template (mixed)    | `bg-neutral-200 dark:bg-700`   | ring-2 ring-accent-500 | scale(0.98) |
| CTA `Selecionar template` | herdado de `af-button primary` | herdado                | herdado     |
| Chip template             | mostra X de remover            | foco no chip → Tab → X | —           |

### Responsive

| Breakpoint | Comportamento                                                                                                    |
| ---------- | ---------------------------------------------------------------------------------------------------------------- |
| `< 768px`  | Modo `mixed`: botão template aparece em linha separada acima do textarea (espaço estreito); picker = `af-sheet`. |
| `>= 768px` | Modo `mixed`: botão inline; picker = `af-popover` ancorado.                                                      |

Modo `template-only` é idêntico em ambos breakpoints (banner + CTA empilhados).

### Acessibilidade

- Banner com `role="status"` + `aria-live="polite"`; ao mudar de `free`/`mixed` → `template-only`, leitores anunciam.
- CTA "Selecionar template" recebe foco automático ao entrar em `template-only` (via `effect()` + `ElementRef`).
- Modal/sheet do picker: trap de foco; primeiro foco no `af-search-input`; `Esc` fecha.
- Chip de template selecionado: `role="status"` + botão remover com `aria-label="Remover template selecionado"`.
- Contraste do banner warning: já garantido pelo design system (token `text-warning-foreground`).
- Sem informação transmitida apenas por cor — sempre acompanhada de texto.

### What NOT to do

- [ ] Não usar `bg-yellow-100` direto — sempre `af-banner variant="warning"` ou tokens `warning/*`.
- [ ] Não esconder o `+ anexo` em `template-only`; mantê-lo `disabled` com tooltip explicativo.
- [ ] Não duplicar lógica de janela no frontend; sempre confiar no `composerMode` computed do `ChatStore`.
- [ ] Não trocar o componente quando o status flutua rapidamente — debounce de 200ms na transição visual para evitar flicker.
- [ ] Não criar novo banner/alert local — `af-banner` já cobre.
- [ ] Não enviar texto livre via UI no modo `template-only`; o backend (TASK-A8) é a defesa, mas a UI **nunca** deve permitir.

---

## 4. Componentes shared a criar / extender

| Componente                                         | Ação      | Justificativa                                                                                                                                                                                                                                       |
| -------------------------------------------------- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `app/src/app/shared/components/template-selector/` | **Mover** | Já existe em `pages/chat/components/new-conversation-modal/components/template-selector/`. TASK-D1 move e torna reutilizável (input `chatInstanceId`, `contactId`; output `templateSelected`). Manter API, garantir `providedIn:'root'` no service. |
| `helpers/template-status.ts`                       | **Criar** | Função pura `templateStatusVariant(status): BadgeVariant` + `templateStatusLabel(status): string`. Evita lógica espalhada nos templates.html.                                                                                                       |
| **Nenhum outro componente novo**                   | —         | Tudo o mais é composição de shared existentes.                                                                                                                                                                                                      |

---

## 5. Checklist de validação (DESIGNER → FRONTEND handoff)

- [x] Todos os 4 estados cobertos por tela (loading, empty, error, default)
- [x] Nenhum componente novo proposto sem checar shared library
- [x] Cores via tokens semânticos (variant), nunca hex
- [x] Spacing apenas escala 4/8/16/24/32/48px
- [x] Typography pelos roles definidos em `design.md`
- [x] Padrão CRM contacts (golden model) seguido na lista
- [x] Acessibilidade: ARIA, foco em modais, contraste, anúncios live region
- [x] Responsivo: mobile (`<768px`) e desktop (`>=768px`) descritos
- [x] Texto em **PT-BR** em toda UI

---

## 6. Resumo de decisões visuais

1. **Status badges:** `af-badge` (não `af-status-badge`) com variantes `success/warning/danger/info/default`. Helper `templateStatusVariant()` centraliza mapping. Ícone + tooltip em `rejected` para mostrar `rejected_reason` sem poluir a linha.
2. **Lista usa `af-crud-page`** (golden model) com slot `filters` para canal/status/sync. Não reinventar header.
3. **Form em 2 colunas (form + preview)** desktop / collapsible no mobile. Edição de template Meta é **fortemente restritiva** — apenas `is_active` e `shortcut` editáveis, com aviso warning explícito.
4. **Composer 3 modos** controlados por `composerMode` computed (TASK-D2): `free` (atual), `mixed` (Meta + janela aberta, com botão template inline), `template-only` (Meta + janela expirada, banner + CTA dominante).
5. **Picker reutilizado** (TASK-D1): `app-template-selector` movido para shared, exibido em `popover` (desktop mixed), `sheet` (mobile mixed) ou `modal` (template-only).
6. **Textos canônicos** definidos em § 3 para garantir consistência entre implementações e evitar copy improvisado.
7. **Defesa em profundidade visual:** UI nunca deixa o atendente "tropeçar" no 422 do backend (TASK-A8) — em `template-only` o textarea simplesmente não existe.

---

**Handoff para `@FRONTEND`:**

- Comece por **TASK-D1** (mover selector → shared) — destrava D2/D4/D5.
- Use o helper `templateStatusVariant` (criar em `app/src/app/pages/chat/templates/helpers/`) já na **C2**.
- Em **C3**, prefira **rota dedicada** `/chat/templates/new` e `/chat/templates/:id/edit` ao invés de modal — o form é grande (preview + builder) e modal fica apertado em laptops 13".
- Validar visualmente cada modo do composer no UI Kit (`http://localhost:4200/ui-kit`) antes de plugar no `chat.ts`.
