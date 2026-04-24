# Tasks: Indicador de Scroll para Novas Mensagens

> Decomposição T.A.C.E das tasks da feature FEAT-046

---

## Feature: Chat Scroll-to-Bottom Indicator

**ID:** FEAT-046
**Bounded Context:** Chat (Frontend only)
**Total Tasks:** 6
**Concluídas:** 0

---

## 📋 Sumário das Tasks

| Task       | Camada   | Título                                                              | Status      |
| ---------- | -------- | ------------------------------------------------------------------- | ----------- |
| TASK-046.1 | Frontend | Lógica TS — `UserChatThreadComponent` (signals + scroll state)     | ⏳ Pendente |
| TASK-046.2 | Frontend | Template HTML — `UserChatThreadComponent` (botão flutuante)        | ⏳ Pendente |
| TASK-046.3 | Frontend | Lógica TS — `ChatWindowComponent` (signals + scroll state)         | ⏳ Pendente |
| TASK-046.4 | Frontend | Template HTML — `ChatWindowComponent` (botão flutuante)            | ⏳ Pendente |
| TASK-046.5 | Frontend | Testes unitários — `user-chat-thread.component.spec.ts`            | ⏳ Pendente |
| TASK-046.6 | Frontend | Testes unitários — `chat-window.component.spec.ts`                 | ⏳ Pendente |

---

## 🔄 FASE FRONTEND — Execution (FRONTEND agent)

---

### TASK-046.1 ⏳ — Lógica TS do `UserChatThreadComponent`

**T — Tarefa:** Adicionar signals de controle do indicador e atualizar a lógica de scroll para expor estado reativo ao template.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.ts`

**C — Comportamento:**

```
ANTES:
- wasNearBottom é private boolean (não acessível ao template)
- Novas mensagens enquanto scrollado para cima são ignoradas silenciosamente
- Não há estado para mostrar/esconder botão de scroll

DEPOIS:
Novos membros adicionados ao componente:

  protected readonly showScrollToBottom = signal(false);
  protected readonly unreadCount = signal(0);
  private prevMessageCount = 0;

Regras de atualização:

1. No effect que reage a messages().length (segundo effect do constructor):
   - Calcular delta = count - this.prevMessageCount
   - Se delta > 0 AND !this.wasNearBottom AND this.scrollElement !== null:
       this.unreadCount.update(n => n + delta)
       this.showScrollToBottom.set(true)
   - Atualizar this.prevMessageCount = count
   - (Manter toda lógica de scroll existente intacta)

2. Em handleScroll():
   - Quando isNearBottom() passa a ser true (wasNearBottom muda de false para true):
       this.showScrollToBottom.set(false)
       this.unreadCount.set(0)

3. Novo método público scrollToBottomClick():
   this.scrollToBottom('smooth')
   this.showScrollToBottom.set(false)
   this.unreadCount.set(0)

4. No effect que reseta estado ao mudar calledId() (primeiro effect):
   - Adicionar reset: this.showScrollToBottom.set(false); this.unreadCount.set(0); this.prevMessageCount = 0;
```

**E — Evidência:**

- [ ] `showScrollToBottom` é `signal(false)` protegido
- [ ] `unreadCount` é `signal(0)` protegido
- [ ] `scrollToBottomClick()` chama `scrollToBottom('smooth')` e zera ambos os signals
- [ ] Troca de `calledId` reseta `showScrollToBottom`, `unreadCount` e `prevMessageCount`
- [ ] Nenhuma lógica de scroll/paginação existente foi removida ou alterada
- [ ] `tsc --noEmit` sem erros

**Dependências:** Nenhuma

**Status:** ⏳ Pendente

---

### TASK-046.2 ⏳ — Template HTML do `UserChatThreadComponent`

**T — Tarefa:** Adicionar botão flutuante de scroll no template, posicionado sobre o rodapé do container de scroll.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.html`

**C — Comportamento:**

```html
<!-- ANTES: apenas o div#scrollContainer e seu conteúdo -->
@if (!calledId()) {
  ...
} @else {
  <div #scrollContainer class="h-full overflow-y-auto ...">
    <section class="..."> ... </section>
  </div>
}

<!-- DEPOIS: adicionar wrapper relativo + botão flutuante após o <div #scrollContainer> -->
@if (!calledId()) {
  ...
} @else {
  <div class="relative h-full overflow-hidden">
    <div
      #scrollContainer
      class="h-full overflow-y-auto scrollbar-thin ..."
    >
      <section class="..."> ... </section>
    </div>

    @if (showScrollToBottom()) {
      <button
        type="button"
        class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10
               flex items-center gap-1.5 rounded-full
               bg-accent-500 px-3 py-1.5 text-white shadow-lg
               hover:bg-accent-600 focus-visible:outline-none
               focus-visible:ring-2 focus-visible:ring-accent-500
               focus-visible:ring-offset-2 transition-colors"
        aria-label="Rolar para novas mensagens"
        (click)="scrollToBottomClick()"
      >
        <lucide-icon name="chevron-down" [size]="16" aria-hidden="true" />
        @if (unreadCount() > 0) {
          <span class="text-xs font-medium leading-none">{{ unreadCount() }}</span>
        }
      </button>
    }
  </div>
}
```

Obs: o componente host já tem `class="block h-full overflow-hidden"` — o wrapper `div.relative` substitui o atual `div#scrollContainer` como container de posicionamento.

**E — Evidência:**

- [ ] Botão renderiza sobre o chat quando `showScrollToBottom()` é true
- [ ] Badge com número aparece quando `unreadCount() > 0`
- [ ] `aria-label="Rolar para novas mensagens"` presente
- [ ] Botão focalizável via Tab (sem `tabindex="-1"`)
- [ ] LucideAngularModule importado em `imports[]` do componente (se não estiver)
- [ ] Visual correto em dark mode

**Dependências:** TASK-046.1

**Status:** ⏳ Pendente

---

### TASK-046.3 ⏳ — Lógica TS do `ChatWindowComponent`

**T — Tarefa:** Adicionar signals de controle do indicador e expandir o listener de scroll para manter estado reativo.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`

**C — Comportamento:**

```
ANTES:
- pendingScrollToBottom: boolean (não reativo, não acessível ao template)
- scrollToBottomIfNear() não distingue "estava perto do fundo mas não mais"
- Nenhum estado de mensagens não lidas

DEPOIS:
Novos membros adicionados:

  protected readonly showScrollToBottom = signal(false);
  protected readonly unreadCount = signal(0);
  private prevMessageCount = 0;

1. Atualizar o effect no constructor (reage a messages().length):
   ANTES:
     queueMicrotask(() => this.scrollToBottomIfNear())
   DEPOIS:
     queueMicrotask(() => {
       const count = this.messages().length;
       const delta = count - this.prevMessageCount;
       this.prevMessageCount = count;
       if (delta > 0 && !this.isNearBottom()) {
         this.unreadCount.update(n => n + delta);
         this.showScrollToBottom.set(true);
       } else {
         this.scrollToBottomIfNear();
       }
     })

2. Extrair helper privado isNearBottom():
   private isNearBottom(): boolean {
     if (!this.scrollElement) return true;
     const { scrollTop, clientHeight, scrollHeight } = this.scrollElement;
     return scrollTop + clientHeight >= scrollHeight - 100;
   }
   (O corpo de scrollToBottomIfNear() passa a usar este helper)

3. Expandir attachScrollListener() para resetar estado ao chegar no fundo:
   fromEvent(this.scrollElement, 'scroll').pipe(
     debounceTime(100),
     takeUntilDestroyed(this.destroyRef),
   ).subscribe(() => {
     this.pendingScrollToBottom = false;
     if (this.isNearBottom()) {
       this.showScrollToBottom.set(false);
       this.unreadCount.set(0);
     }
   });

4. Novo método público scrollToBottomClick():
   this.scrollToBottom('smooth');
   this.showScrollToBottom.set(false);
   this.unreadCount.set(0);

5. Resetar state em init():
   this.prevMessageCount = 0;
   this.showScrollToBottom.set(false);
   this.unreadCount.set(0);
```

**E — Evidência:**

- [ ] `showScrollToBottom` e `unreadCount` são signals protegidos
- [ ] `isNearBottom()` é método privado reutilizado por `scrollToBottomIfNear()`
- [ ] Scroll até o fundo reseta ambos os signals
- [ ] `init()` reseta ambos os signals e `prevMessageCount`
- [ ] `scrollToBottomClick()` chama `scrollToBottom('smooth')` e zera signals
- [ ] `tsc --noEmit` sem erros

**Dependências:** Nenhuma (pode ser paralela a TASK-046.1)

**Status:** ⏳ Pendente

---

### TASK-046.4 ⏳ — Template HTML do `ChatWindowComponent`

**T — Tarefa:** Adicionar botão flutuante de scroll no template do webchat público.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`

**C — Comportamento:**

```html
<!-- ANTES: <main #scrollContainer class="flex-1 overflow-y-auto py-4 px-4" ...> -->

<!-- DEPOIS: adicionar relative ao <main> e o botão DENTRO dele, após o div de mensagens -->

<main
  #scrollContainer
  class="relative flex-1 overflow-y-auto py-4 px-4"
  role="log"
  aria-live="polite"
  aria-label="Mensagens do chat"
>
  <div class="flex flex-col gap-3 min-h-full justify-end">
    <!-- ...mensagens, typing indicator (sem alteração) -->
  </div>

  @if (showScrollToBottom()) {
    <button
      type="button"
      class="sticky bottom-4 left-1/2 -translate-x-1/2 z-10
             flex items-center gap-1.5 rounded-full
             bg-accent-500 px-3 py-1.5 text-white shadow-lg
             hover:bg-accent-600 focus-visible:outline-none
             focus-visible:ring-2 focus-visible:ring-accent-500
             focus-visible:ring-offset-2 transition-colors
             mx-auto w-fit"
      aria-label="Rolar para novas mensagens"
      (click)="scrollToBottomClick()"
    >
      <lucide-icon name="chevron-down" [size]="16" aria-hidden="true" />
      @if (unreadCount() > 0) {
        <span class="text-xs font-medium leading-none">{{ unreadCount() }}</span>
      }
    </button>
  }
</main>

<!-- Nota: usar sticky (em vez de absolute) dentro do scroll container
     porque o <main> é o próprio scrollable — absolute ficaria fora da viewport visível -->
```

**E — Evidência:**

- [ ] Botão aparece fixo na parte inferior visível do chat ao rolar para cima
- [ ] Badge com número aparece quando `unreadCount() > 0`
- [ ] `aria-label="Rolar para novas mensagens"` presente
- [ ] LucideAngularModule importado em `imports[]` do componente
- [ ] Visual correto em dark mode
- [ ] Não sobrepõe o composer (está dentro do `<main>`, não do `<footer>`)

**Dependências:** TASK-046.3

**Status:** ⏳ Pendente

---

### TASK-046.5 ⏳ — Testes unitários `user-chat-thread.component.spec.ts`

**T — Tarefa:** Escrever testes cobrindo os novos signals e comportamento do indicador no componente do atendente.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/chat/components/user-chat-thread/user-chat-thread.component.spec.ts`

**C — Comportamento:**

```
Cenários obrigatórios:

1. showScrollToBottom_inicia_como_false
   → componente criado → showScrollToBottom() === false

2. unreadCount_inicia_como_zero
   → componente criado → unreadCount() === 0

3. botao_aparece_quando_scrollado_para_cima_e_nova_mensagem_chega
   → simular scroll (wasNearBottom = false)
   → adicionar mensagem ao store
   → showScrollToBottom() === true, unreadCount() === 1

4. botao_nao_aparece_quando_usuario_esta_no_fundo
   → wasNearBottom = true (padrão)
   → adicionar mensagem
   → showScrollToBottom() === false

5. scrollToBottomClick_zera_estado
   → showScrollToBottom = true, unreadCount = 3
   → chamar scrollToBottomClick()
   → showScrollToBottom() === false, unreadCount() === 0

6. trocar_calledId_reseta_estado
   → showScrollToBottom = true, unreadCount = 2
   → store.setContext(novoId, true)
   → showScrollToBottom() === false, unreadCount() === 0

7. scroll_manual_ate_o_fundo_zera_estado
   → showScrollToBottom = true, unreadCount = 2
   → simular evento scroll com posição no fundo
   → showScrollToBottom() === false, unreadCount() === 0
```

**E — Evidência:**

- [ ] Todos os 7 cenários passam (`npm run gate:test`)
- [ ] Nenhum teste existente quebra
- [ ] Sem warnings de `ExpressionChangedAfterItHasBeenCheckedError`

**Dependências:** TASK-046.1, TASK-046.2

**Status:** ⏳ Pendente

---

### TASK-046.6 ⏳ — Testes unitários `chat-window.component.spec.ts`

**T — Tarefa:** Escrever testes cobrindo os novos signals e comportamento do indicador no webchat público.

**A — Arquivo:**

- **Modificar:** `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`

**C — Comportamento:**

```
Cenários obrigatórios:

1. showScrollToBottom_inicia_como_false
   → componente criado → showScrollToBottom() === false

2. unreadCount_inicia_como_zero
   → componente criado → unreadCount() === 0

3. botao_aparece_quando_nao_esta_no_fundo_e_nova_mensagem_chega
   → scrollElement com scrollTop longe do fundo
   → webchatService.messages() recebe nova mensagem
   → showScrollToBottom() === true, unreadCount() === 1

4. botao_nao_aparece_quando_usuario_esta_no_fundo
   → scrollElement no fundo (scrollTop + clientHeight >= scrollHeight - 100)
   → nova mensagem chega
   → showScrollToBottom() === false

5. scrollToBottomClick_zera_estado
   → state: showScrollToBottom = true, unreadCount = 2
   → chamar scrollToBottomClick()
   → showScrollToBottom() === false, unreadCount() === 0

6. scroll_ate_o_fundo_zera_estado
   → state: showScrollToBottom = true, unreadCount = 2
   → simular evento scroll com elemento no fundo
   → showScrollToBottom() === false, unreadCount() === 0

7. init_reseta_estado
   → showScrollToBottom = true, unreadCount = 3
   → chamar init('session-id', 'Nome')
   → showScrollToBottom() === false, unreadCount() === 0
```

**E — Evidência:**

- [ ] Todos os 7 cenários passam (`npm run gate:test`)
- [ ] Nenhum teste existente quebra
- [ ] Mock de `WebChatService` usando `jasmine.createSpyObj` ou `TestBed` com spy

**Dependências:** TASK-046.3, TASK-046.4

**Status:** ⏳ Pendente

---

## 📊 Ordem de Execução

```
TASK-046.1 ──► TASK-046.2 ──► TASK-046.5
                    (pode ser paralelo a ▼)
TASK-046.3 ──► TASK-046.4 ──► TASK-046.6
```

> As trilhas do `UserChatThreadComponent` e do `ChatWindowComponent` são **independentes** e podem ser executadas em paralelo.

---

## ✅ Gate de Conclusão da Feature

- [ ] `npm run gate:test` — todos os testes passam (incluindo novos)
- [ ] `tsc --noEmit` no projeto Angular sem erros
- [ ] `npm run lint` sem erros novos nos arquivos modificados
- [ ] Teste manual (chat do atendente): rolar para cima → receber mensagem → botão aparece → clicar → scroll suave até o fundo → botão desaparece
- [ ] Teste manual (webchat público): mesmo fluxo no chat externo
- [ ] Verificado em dark mode
- [ ] Verificado com teclado (Tab → Enter no botão)
