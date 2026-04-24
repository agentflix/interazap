# Feature: Indicador de Scroll para Novas Mensagens

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-046 |
| **Nome** | Chat Scroll-to-Bottom Indicator |
| **Bounded Context** | Chat (Frontend) |
| **Complexidade** | P |
| **Prioridade** | Should |
| **Status** | 🟡 Em Planning |
| **Criada em** | 2026-04-23 |
| **Última atualização** | 2026-04-23 |

---

## Resumo

Quando o usuário rola o chat para cima e chega uma nova mensagem que está fora da área visível, um botão com seta para baixo aparece na parte inferior do chat. Ao clicar, o chat rola automaticamente (smooth) até o final, revelando as mensagens novas.

A funcionalidade deve ser implementada em dois componentes:
- **Tela do atendente:** `UserChatThreadComponent` (`app/src/app/pages/chat/components/user-chat-thread/`)
- **Chat externo (webchat público):** `ChatWindowComponent` (`app/src/app/pages/webchat/components/chat-window/`)

---

## Objetivo

Evitar que o atendente ou cliente perca mensagens recentes ao estar com o scroll preso em posição histórica. Atualmente, novas mensagens chegam silenciosamente quando o usuário está rolado para cima, sem nenhuma indicação visual.

---

## Escopo

### Dentro do Escopo ✅

- [ ] Botão flutuante com seta para baixo (chevron-down) posicionado sobre o rodapé do chat
- [ ] O botão aparece **somente** quando: (a) o scroll não está próximo do fundo (fora do threshold de ~100px) **E** (b) existe ao menos uma mensagem nova não vista
- [ ] Ao clicar no botão, o chat rola suavemente até o fundo (`behavior: 'smooth'`)
- [ ] O botão desaparece quando o usuário rola manualmente até o fundo
- [ ] Badge opcional com contagem de mensagens novas não vistas desde que o usuário rolou para cima
- [ ] Implementação em `UserChatThreadComponent` (atendente)
- [ ] Implementação em `ChatWindowComponent` (webchat externo/público)
- [ ] Acessibilidade: `aria-label="Rolar para novas mensagens"`, role button, focusável via teclado

### Fora do Escopo ❌

- Persistência do estado de mensagens não lidas entre sessões
- Notificação sonora para mensagens novas
- Paginação retroativa ou carregamento de mensagens ao clicar no botão
- Backend — nenhuma alteração de API necessária

---

## Dependências

| Feature/Sistema | Tipo | Status | Blocker |
|-----------------|------|--------|---------|
| `UserChatThreadComponent` | Componente existente a modificar | Pronta | Não |
| `ChatWindowComponent` | Componente existente a modificar | Pronta | Não |

---

## Critérios de Aceite

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | O botão NÃO aparece quando o scroll está no fundo (últimas 100px) | [ ] | ❌ |
| CA-002 | O botão APARECE quando o scroll está acima do threshold e chega mensagem nova | [ ] | ❌ |
| CA-003 | Clicar no botão rola o chat suavemente até a última mensagem | [ ] | ❌ |
| CA-004 | O botão desaparece após rolar até o fundo (manualmente ou via clique) | [ ] | ❌ |
| CA-005 | Funciona no chat do atendente (`UserChatThreadComponent`) | [ ] | ❌ |
| CA-006 | Funciona no webchat público (`ChatWindowComponent`) | [ ] | ❌ |
| CA-007 | O botão é acessível via teclado (Tab + Enter/Space) | [ ] | ❌ |
| CA-008 | Em dark mode o botão tem contraste adequado | [ ] | ❌ |

---

## Análise Técnica

### `UserChatThreadComponent`

Arquivo: `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.ts`

- Já possui `wasNearBottom: boolean` e `isNearBottom(): boolean`
- Já possui `scrollToBottom(behavior)` e lógica de detecção de scroll
- **Mudança necessária:** Expor `showScrollToBottom = signal(false)` (computed: `!wasNearBottom && newMessageCount > 0`)
- O `effect()` que reage ao `messages().length` deve incrementar o contador de mensagens não vistas quando `!wasNearBottom`
- O clique no botão chama `scrollToBottom('smooth')` e zera o contador
- O HTML (`user-chat-thread.component.html`) recebe o botão posicionado absolutamente sobre o `#scrollContainer`

### `ChatWindowComponent`

Arquivo: `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`

- Já possui `scrollToBottomIfNear()` e `pendingScrollToBottom`
- **Mudança necessária:** Adicionar `isScrolledUp = signal(false)` e `unreadCount = signal(0)`
- O `attachScrollListener()` já ouve eventos de scroll — expandir para atualizar `isScrolledUp`
- O HTML (`chat-window.component.html`) recebe o botão flutuante dentro do `<main>` com `position: sticky` ou absolute

### Posicionamento do botão (ambos)

```html
<!-- Dentro do wrapper relativo do scroll container -->
@if (showScrollToBottom()) {
  <button
    class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-1.5
           rounded-full bg-accent-500 px-3 py-1.5 text-white shadow-lg
           hover:bg-accent-600 transition-colors"
    aria-label="Rolar para novas mensagens"
    (click)="scrollToBottom('smooth')"
  >
    <lucide-icon name="chevron-down" size="16" />
    @if (unreadCount() > 0) {
      <span class="text-xs font-medium">{{ unreadCount() }}</span>
    }
  </button>
}
```

---

## Tasks

| Task ID | Descrição | Status |
|---------|-----------|--------|
| TASK-046.1 | Implementar indicador no `UserChatThreadComponent` (TS + HTML) | ⏳ |
| TASK-046.2 | Implementar indicador no `ChatWindowComponent` (TS + HTML) | ⏳ |
| TASK-046.3 | Testes unitários para os dois componentes | ⏳ |

---

## Notas

- O threshold de "próximo ao fundo" já é 100px nos dois componentes — manter consistente.
- `UserChatThreadComponent` usa Angular Signals + `ChangeDetectionStrategy.OnPush`; `showScrollToBottom` deve ser um `signal()` para disparar re-render corretamente.
- `ChatWindowComponent` também usa OnPush + Signals — mesma abordagem.
- O wrapper do `#scrollContainer` já é `relative` no atendente (via classe host `overflow-hidden`). No webchat, o `<main>` pode precisar de `relative` adicionado.
