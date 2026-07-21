---
feature: meta-window-webhook
tipo: component-spec
status: rascunho
aprovado-por: pending
data: 2026-07-21
---

# Design: Badge de Janela Meta no Composer do Chat

> Badge inline acima do composer indicando o tipo de janela de atendimento Meta (24h padrão / 72h CTWA)
> e o tempo restante, para o operador decidir se pode enviar texto livre ou precisa de template aprovado.
> Complementa (não substitui) o comportamento já existente de `composerMode` (`free`/`mixed`/`template-only`).

---

## Visão Geral

Hoje o composer do chat (`app/src/app/pages/chat/chat.html`) só reage ao `composerMode` — alterna entre texto livre, texto+template e template-only — sem que o operador veja **por que** está em cada modo nem **quanto tempo falta** até a janela fechar. Este badge expõe essa informação de forma visual e acessível, com os 4 estados descritos abaixo, atualizando o tempo restante em tempo real e refletindo a reabertura automática da janela quando o cliente responde.

---

## Fluxo do Usuário

```
[Ticket Meta selecionado no chat]
                │
                ▼
   ChatStore.windowStatus() já carregado (WindowVerificationService)
                │
                ▼
   ChatStore.windowBadge computed deriva:
   - provider da instância (via instanceProviders())
   - windowType ('24h' | '72h' | null)
   - expiresAt (Date | null)
                │
        ┌───────┴────────┐
        ▼                ▼
  provider !== 'meta'   provider === 'meta'
        │                │
        ▼                ▼
   badge oculto      badge renderizado acima do composer
                          │
                ┌─────────┼─────────┐
                ▼         ▼         ▼
          expiresAt   expiresAt   expiresAt
          no futuro   no futuro   no passado/null
          + type=24h  + type=72h
                │         │         │
                ▼         ▼         ▼
          "24h aberta"  "72h CTWA   "expirada"
          + countdown   aberta"     (template-only)
                        + countdown
                │
                ▼
   [Cliente responde no WhatsApp]
                │
                ▼
   realtimeListener.incomingMessage$ dispara (chat.ts:353-370)
                │
                ▼
   windowVerification.invalidateCache(contactId) + checkStatus(contactId)
                │
                ▼
   chatStore.setWindowStatus(status) → windowBadge recalcula
                │
                ▼
   Badge volta para "24h aberta" (ou "72h CTWA aberta") SEM reload de página
```

---

## Estados do Badge

| Estado | Condição | Aparência | Composer associado |
|---|---|---|---|
| `24h aberta` | `windowType === '24h'` e `expiresAt` no futuro | pill verde/âmbar/vermelho conforme tempo restante, texto `24h · {tempo}` | `mixed` |
| `72h CTWA aberta` | `windowType === '72h'` e `expiresAt` no futuro | pill verde/âmbar/vermelho conforme tempo restante, texto `72h CTWA · {tempo}` | `mixed` |
| `expirada / template-only` | `expiresAt` ausente ou no passado | pill cinza neutra, texto `Janela expirada` | `template-only` |
| `não-Meta` | provider da instância ≠ `meta` | badge **não renderiza** (`@if` remove do DOM) | `free` |

O corte de cor por tempo restante (independe de `24h`/`72h`) segue o mesmo threshold para os dois tipos de janela, medido sobre o tempo restante absoluto:

| Tempo restante | Tom |
|---|---|
| ≥ 4h | neutro/sucesso (`safe`) |
| 1h – 4h | atenção (`warning`) |
| < 1h | alerta (`danger`) |
| ≤ 0 (expirada) | neutro cinza (`expired`) |

> Nota de consistência: o corte de 4h aqui reaproveita o mesmo threshold do componente `Window24hBadgeComponent` já existente no código (`app/src/app/pages/chat/components/window-24h-badge/window-24h-badge.ts`, hoje não utilizado em nenhuma tela — ver seção "Inconsistências encontradas" no fim deste documento).

---

## Wireframes / Layout

### Estado 1 — `24h aberta` (> 4h restantes, tom neutro/sucesso)

```
┌─ Composer (chat-conversation-component) ────────────────────────┐
│                                                                  │
│   ⏱  24h · 23h 47m                                              │  ← badge, alinhado à esquerda,
│                                                                  │    acima da área de input
│  ┌────────────────────────────────────────────────────────┐    │
│  │ 😊  Digite uma mensagem...                        🎤  ➤ │    │
│  └────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

### Estado 2 — `72h CTWA aberta` (2h05m restantes → tom warning)

```
┌─ Composer (chat-conversation-component) ────────────────────────┐
│                                                                  │
│   ⏱  72h CTWA · 2h 05m                                          │  ← pill amarelo/âmbar
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ 😊  Digite uma mensagem...                        🎤  ➤ │    │
│  └────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

### Estado 2b — `24h aberta`, < 1h restante (tom danger)

```
┌─ Composer ───────────────────────────────────────────────────────┐
│                                                                  │
│   ⏱  24h · 18m                                                  │  ← pill vermelho, chama atenção
│                                                                  │    para enviar antes de fechar
│  ┌────────────────────────────────────────────────────────┐    │
│  │ 😊  Digite uma mensagem...                        🎤  ➤ │    │
│  └────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

### Estado 3 — `expirada / template-only`

```
┌─ Composer (chat-conversation-component) ────────────────────────┐
│                                                                  │
│   ⏱  Janela expirada                                            │  ← pill cinza neutro
│                                                                  │
│  ⚠ Janela de 24h expirada. O cliente não responde há mais de    │
│    24h. Para reabrir a conversa, envie um template aprovado.    │  ← af-banner já existente
│                                                                  │    (chat-conversation-component.html:222-225)
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              [ Selecionar template ]                     │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

### Estado 4 — `não-Meta` (badge oculto)

```
┌─ Composer (chat-conversation-component) ────────────────────────┐
│                                                                  │
│   (nenhum badge — instância não é Meta, ex: Z-API/Evolution)    │
│                                                                  │
│  ┌────────────────────────────────────────────────────────┐    │
│  │ 😊  Digite uma mensagem...                        🎤  ➤ │    │
│  └────────────────────────────────────────────────────────┘    │
└──────────────────────────────────────────────────────────────────┘
```

---

## Formato do tempo restante

Calculado a partir de `expiresAt - now()`, atualizado a cada 60s (mesmo padrão do `Window24hBadgeComponent` existente, que usa `interval(60_000)`):

| Faixa | Formato | Exemplo |
|---|---|---|
| ≥ 1h | `{h}h {mm}m` | `23h 47m`, `2h 05m` (minutos sempre com 2 dígitos) |
| < 1h | `{m}m` | `18m` |
| ≤ 0 | — | (estado muda para `expirada`, não mostra tempo) |

Regra de alerta: abaixo de 1h restante, o badge troca para o tom `danger` (vermelho), reforçando o texto curto (`18m`) com cor de urgência — nunca depender só da cor (ver Acessibilidade).

---

## Especificação de Componentes

### `windowBadge` — computed em `ChatStore`

**Localização:** `app/src/app/pages/chat/chat.store.ts` (ao lado de `composerMode`, linhas 75-90)

```typescript
export interface WindowBadgeViewModel {
  visible: boolean;
  type: '24h' | '72h' | null;
  label: string;            // "24h · 23h 47m" | "72h CTWA · 2h 05m" | "Janela expirada"
  tone: 'safe' | 'warning' | 'danger' | 'expired';
  remainingMinutes: number | null; // null quando expirada ou não-Meta
  ariaLabel: string;         // descritivo por extenso
}

readonly windowBadge = computed<WindowBadgeViewModel>(() => { /* ... */ });
```

**Regras de derivação:**
- `visible = false` quando `provider !== 'meta'` (mesma checagem já usada em `composerMode`, `chat.store.ts:81-84`).
- `type` e `remainingMinutes` vêm de `windowStatus().windowType` e `windowStatus().expiresAt` (campos novos do `WindowStatus`, adicionados na TASK-4.1.1).
- Recalcula minuto a minuto: o valor de `now()` usado no cálculo deve vir de um `signal` atualizado por `interval(60_000)`, análogo ao `Window24hBadgeComponent` já existente — **não** recalcular via `Date.now()` direto dentro do `computed` (não reatividade).
- `tone`: thresholds da tabela acima, aplicados sobre `remainingMinutes` independente do `type`.

### Renderização no template

**Localização:** `app/src/app/pages/chat/chat.html`, imediatamente acima do bloco `<app-chat-conversation>` (ou repassado como `input()` para `chat-conversation-component` e renderizado no topo do composer, dentro de `chat-conversation-component.html`, antes do `@if (composerMode() === 'template-only')` na linha 219 — decisão de implementação do BUILDER, ambos os locais atendem à spec).

```html
@if (windowBadge().visible) {
  <span
    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full"
    [class]="badgeToneClasses(windowBadge().tone)"
    role="status"
    [attr.aria-label]="windowBadge().ariaLabel"
  >
    <ng-icon name="lucideClock3" size="12" />
    {{ windowBadge().label }}
  </span>
}
```

**Comportamento:**
- Não bloqueia a digitação — é puramente informativo; quem controla o que pode ser enviado continua sendo `composerMode`.
- Some do DOM (não apenas `display:none`) quando `visible() === false`, para não confundir leitores de tela.
- Atualiza sozinho a cada 60s enquanto o ticket estiver aberto (sem poll ao backend — só recalcula o tempo restante local a partir do `expiresAt` já carregado).

---

## Cores / Tokens (design system real do app — `af-badge` + tokens Tailwind de `app/src/styles.css`)

> Este componente vive dentro do painel do app (`app/`), que é claro por padrão com variante dark via classe `dark:` — diferente da paleta sempre-escura do `InteraZap-design-system.md` (landing page). Os tokens abaixo são os efetivamente usados no chat (`af-badge`, `Window24hBadgeComponent`) e devem ser reaproveitados, não recriados.

| Tom | Light — classes Tailwind | Dark — classes Tailwind | Hex de referência (`styles.css`) |
|---|---|---|---|
| `safe` (≥ 4h) | `bg-primary-50 text-primary-700` | `dark:bg-primary-900 dark:text-primary-300` | `--color-primary-50:#eafaf0` / `--color-primary-700:#1f803a` |
| `warning` (1h–4h) | `bg-warning-50 text-warning-600` | `dark:bg-neutral-800 dark:text-warning-500` | `--color-warning-50:#fffbeb` / `--color-warning-500:#f59e0b` |
| `danger` (< 1h) | `bg-danger-50 text-danger-600` | `dark:bg-neutral-800 dark:text-danger-500` | `--color-danger-50:#fef2f2` / `--color-danger-500:#ef4444` |
| `expired` (template-only) | `bg-neutral-100 text-neutral-600` | `dark:bg-[#191d1a] dark:text-neutral-300` | neutros padrão do tema |

Essas classes são as mesmas já usadas por `af-badge` (`app/src/app/shared/components/badge/badge.ts:42-47`, variantes `success`/`warning`/`danger`/`default`) — o badge de janela pode ser implementado com `<af-badge>` diretamente (reuso) ou com classes próprias equivalentes; **não introduzir uma nova paleta**.

Ícone: `lucideClock3` (biblioteca `ng-icon`/Lucide já usada no composer, ex. `lucideMic`, `lucideSend` em `chat-conversation-component.html`), 12–14px, cor herdada do texto do badge (não fixa).

---

## Validações e Regras

| Estado | Condição | Comportamento |
|---|---|---|
| `visible` | `instanceProviders()[instanceId] === 'meta'` | renderiza badge |
| `type = 24h` | `windowStatus().windowType === '24h'` e `expiresAt > now` | label `24h · {tempo}` |
| `type = 72h` | `windowStatus().windowType === '72h'` e `expiresAt > now` | label `72h CTWA · {tempo}` |
| `expired` | `expiresAt` ausente ou `<= now` | label `Janela expirada`; `composerMode` cai em `template-only` |
| `tone = danger` | `remainingMinutes < 60` | pill vermelha, sem exigir nenhuma ação adicional do operador (apenas visual) |

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | Badge ocupa a largura necessária, alinhado à esquerda, acima da barra de input; quebra de linha independente se o composer estiver estreito |
| Tablet (768–1024px) | Igual ao mobile, mesmo componente sem alteração de layout |
| Desktop (> 1024px) | Badge alinhado à esquerda, mesma linha reservada acima do composer, sem competir com os botões de anexo/template à direita |

---

## Acessibilidade

- `role="status"` no `<span>` do badge — muda de estado sem exigir foco do usuário, mas anuncia para leitores de tela quando o texto muda (equivalente ao `Window24hBadgeComponent` já existente).
- `aria-label` descritivo por extenso, nunca apenas o texto visual abreviado. Exemplos:
  - `"Janela Meta 24 horas — 23 horas e 47 minutos restantes"`
  - `"Janela Meta 72 horas CTWA — 2 horas e 5 minutos restantes"`
  - `"Janela Meta 18 minutos restantes — envie logo, a janela está prestes a expirar"` (quando `tone = danger`)
  - `"Janela Meta expirada — apenas templates aprovados podem ser enviados"`
- Cor nunca é o único indicador de urgência: o texto muda de formato (`23h 47m` → `18m`) e o `aria-label` explicita quando `tone = danger`.
- Contraste dos 4 tons (light e dark) deve atender WCAG AA — os tokens reaproveitados de `af-badge` já são usados em outras partes do app com esse padrão validado.
- Ícone `lucideClock3` é decorativo (`aria-hidden` implícito via `ng-icon` sem label próprio) — a informação semântica está inteira no `aria-label` do container.

---

## Comportamento ao reabrir a janela (realtime, sem reload)

Quando o cliente responde no WhatsApp:

1. `realtimeListener.incomingMessage$` emite (gateway → WS já implementado, `chat.ts:353-370`).
2. Handler já existente invalida o cache do `WindowVerificationService` (`invalidateCache(contactId)`) e chama `checkStatus(contactId)`.
3. Resultado atualiza `chatStore.setWindowStatus(status)`.
4. `windowBadge` (computed) recalcula automaticamente — sem necessidade de nenhuma alteração nesse fluxo de invalidação, que já existe e é reutilizado (ver TASK-4.1.2, que só adiciona o computed derivado de `windowStatus()`, já atualizado por este fluxo).
5. Badge visualmente volta de `expirada` para `24h aberta` (ou `72h CTWA aberta`, se a resposta veio com novo `referral` de anúncio) sem que o operador precise recarregar a página ou trocar de ticket.

---

## Critérios de Aprovação Visual

- [ ] Os 4 estados (`24h aberta`, `72h CTWA aberta`, `expirada/template-only`, `não-Meta` oculto) renderizam corretamente conforme `windowStatus()` + `instanceProviders()`
- [ ] Tempo restante no formato `23h 47m` / `2h 05m` / `18m`, atualizado a cada 60s sem poll ao backend
- [ ] Tom visual muda para alerta (`danger`) abaixo de 1h restante, em light e dark
- [ ] Badge desaparece do DOM (não só visualmente) quando o provider não é Meta
- [ ] Badge volta a `aberta` automaticamente quando o cliente responde, sem reload (fluxo de invalidação de cache já existente em `chat.ts:353-370`)
- [ ] `aria-label` descritivo por extenso presente em todos os estados, incluindo o texto de urgência quando `tone = danger`
- [ ] Cores reaproveitam os tokens já usados por `af-badge` (`primary`/`warning`/`danger`/`neutral`) — nenhuma cor nova introduzida
- [ ] Testado em mobile e desktop sem sobrepor os botões de anexo/template do composer

---

## Inconsistências encontradas entre este design e o código real

1. **Componente órfão pré-existente:** `app/src/app/pages/chat/components/window-24h-badge/window-24h-badge.ts` (`Window24hBadgeComponent`) já implementa um badge de janela 24h com thresholds de cor (`safe`/`warning`/`danger`/`expired`) e formato de tempo (`23h47 restantes`), mas **não é usado em nenhum template do app** (nenhuma referência fora de seu próprio diretório e spec). Ele também:
   - só conhece janela de 24h fixa (calcula `lastInboundAt + 24h`), sem suporte a 72h CTWA nem ao campo persistido `expiresAt`/`windowType` da TASK-4.1.1;
   - usa tokens `success-*`/`warning-*`/`danger-*`/`neutral-*`, enquanto o `af-badge` real usado no composer usa `primary-*`/`warning-*`/`danger-*`/`info-*`/`neutral-*` (leve divergência de paleta já existente no código, não introduzida por este design).

   Este documento especifica o novo badge no `ChatStore`/`chat.html` (via `windowBadge` computed) para atender aos 4 estados exigidos (incluindo 72h CTWA), reaproveitando os thresholds/formato de tempo do componente órfão, mas os tokens de cor do `af-badge`. Fica como decisão do BUILDER, ao implementar a TASK-4.1.2: **substituir** o `Window24hBadgeComponent` órfão por esta implementação (removendo código morto) ou **estendê-lo** para aceitar `windowType`/`expiresAt` e reusá-lo dentro de `chat-conversation-component`. Qualquer uma das duas atende a este design; não deixar as duas implementações coexistindo.
