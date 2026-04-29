# MEMORY — FEAT-050 Meta Templates Backend (24h guard, submission, send)

> Decisões arquiteturais e armadilhas descobertas durante implementação backend de TASK-050.A3/A7/A8/A9.

## Contexto

Suporte a templates Meta WhatsApp em InteraZap exige (a) submeter templates ao Meta via gateway, (b) endpoint para enviar template em ticket existente, (c) guard server-side da janela 24h, (d) sincronizar status quando o Meta aprova/rejeita.

## Decisões

### 1. 24h guard centralizado em `SendChatMessageAction`, não nos entrypoints

**Decisão:** O bloqueio de free-text fora da janela 24h vive em `SendChatMessageAction::create()` via método privado `enforceMetaWindowGuard()`, executado ANTES de qualquer side-effect.

**Alternativas consideradas:**

- Replicar checagem em cada controller (`ChatMessageController`, `WebChatMessageController`, AI tool `SendMessageTool`, listener `ChatAutoReplyResponderTest`...) — rejeitada: padrão notório de bug visto em `AiGateKeeperListener` (memória prévia: novos entrypoints esquecem o guard).
- Middleware HTTP — rejeitada: não cobre dispatchers de listeners (AI, auto-reply) e jobs.

**Skip rules** (intencionais):

- `direction !== 'outgoing'` → guard só aplica em mensagens enviadas pelo tenant.
- `type ∈ {template, internal_note}` → template REABRE a janela; internal_note não vai pro WhatsApp.
- `source ∉ {agent, ai, bot}` → não bloqueia `system`/`webhook` (sincronização do gateway).
- Canal não-Meta → uazapi não tem janela 24h obrigatória.

**Erro estruturado:** `ValidationException` com `code = WINDOW_24H_EXPIRED` (separado de `message`), permitindo frontend abrir UI de seleção de template diretamente em vez de mostrar erro genérico.

### 2. Submissão como Job assíncrono (não síncrono)

**Decisão:** `CreateMetaTemplateAction::execute()` cria a row local com `status=pending` + dispatcha `SubmitMetaTemplateJob` em queue separada `meta-templates`. Submissão ao Meta acontece fora do request HTTP.

**Alternativas:**

- Síncrono dentro do controller — rejeitada: latência do Meta (>2s típico) bloqueia UX e expõe erros transientes ao usuário.
- Filas + status `pending` permite UI mostrar "submetendo" e webhook update conclui o ciclo.

**Backoff:** `[30, 120, 300]` segundos com `tries=3` — equilibra retry rápido para falhas transientes (rede) sem martelar Meta em falhas semânticas.

### 3. `SubmitMetaTemplateJob` NÃO é `readonly`

**Armadilha resolvida:** Tentei marcar como `final readonly class implements ShouldQueue`. Não compila — traits `InteractsWithQueue`, `Queueable`, `SerializesModels` mutam estado da instância (`tries`, `queue`, `connection`, `job`...). Resultado: `final class` apenas, com `private readonly string $templateId` no construtor.

**Lição:** jobs Laravel **nunca** são `readonly` (mesmo padrão para listeners). Apenas Actions e DTOs.

### 4. Listener atualiza por (instance + external_id), com fallback (instance + name + language)

**Decisão:** `UpdateMetaTemplateStatusListener::handle()` busca primeiro por `chat_instance_id + external_id`. Se não achar, cai em `chat_instance_id + name + language`. Se ainda não achar, **loga warning e retorna** (NÃO cria).

**Por quê não criar:** webhook do Meta pode chegar para template criado fora do InteraZap (interface do Business Manager). Criar uma row "fantasma" sem tenant correto vazaria isolamento. Melhor logar e investigar do que poluir DB.

### 5. Renderização de variáveis: posicional `{{N}}` apenas

**Decisão:** `SendTemplateMessageAction::normalizeVariables()` aceita arrays com chave int (1-indexed) ou string `"1"`, `"2"`, etc. Chaves não-numéricas viram posicionais segundo a ordem de inserção.

**Por quê:** Meta WhatsApp Cloud API só suporta parâmetros posicionais no BODY no formato `{{1}}`, `{{2}}`. Named parameters não existem (ainda) no padrão. Forçar posicional evita confusão de mapeamento.

**Armadilha PHPStan:** PHP converte chaves numericas-string em int automaticamente em arrays. Assinatura `array<string, string>` precisa de `@var` cast no fim do método para narrowing.

### 6. Endpoint `POST /tickets/{id}/messages/template` separado de `POST /tickets/{id}/messages`

**Decisão:** Rota dedicada com `SendTemplateMessageRequest` (validação de `template_id` + `variables`), em vez de detectar `type=template` no endpoint genérico.

**Por quê:**

- Payload é semanticamente diferente (template_id obrigatório, body não).
- Authorization é a mesma (`create` policy on ChatMessage), mas action é especializada.
- Frontend já discrimina o fluxo (botão "Enviar template" vs caixa de texto).
- Audit log identifica claramente `chat.messages.template_sent` vs `chat.messages.created`.

### 7. Action `SendTemplateMessageAction` reutiliza `ProcessChatMessageAction::emitNewMessageEvent`

**Decisão:** Em vez de duplicar lógica de broadcast, injeta `ProcessChatMessageAction` e chama `emitNewMessageEvent($message, $ticket)` após persistir.

**Por quê:** Garante que a UI do atendente recebe o evento WS pelo mesmo caminho que mensagens normais (memória prévia: source AI/BOT precisa estar no whitelist do `SendChatMessageAction`; `template` segue a mesma porta de saída).

## Armadilhas / Aprendizados

### A. Eloquent timestamps em testes

`forceFill(['created_at' => now()->subHours(30)])->save()` — ChatMessage tem `created_at` controlado por Laravel. Para testar guard 24h foi necessário sobrescrever via `forceFill` + `save` (não `update()`, que respeita `$timestamps = true`).

### B. `Http::assertSent` em ambiente com broadcaster

`Http::fake()` captura TODAS as chamadas HTTP, incluindo as do broadcaster (Reverb/Pusher). O assertSent precisa filtrar por URL:

```php
Http::assertSent(fn ($request) => str_contains($request->url(), '/send-template'));
```

Senão acertaria callback do broadcaster que não tem o key esperado.

### C. ChatMessage factory não setou provider/language

Ao criar `ChatMessageTemplate` via factory para testes, é preciso explicitar `provider`, `language`, `status`, `body_text`. Defaults da factory não casam com os necessários para Meta (provider pode vir 'uazapi').

### D. Caller de `SendChatMessageAction` precisa atualização

A constructor signature passou de 4 para 5 args (`VerifyContactWindowAction` adicionado). Todos callers precisam atualizar:

- `ChatMessageActions` (DI auto-resolve via DI container, mas teste manual com `new` precisa ajuste).
- `ChatAutoReplyResponderTest::makeMessageActions()` — passa instâncias manuais.

## Validação realizada

- ✅ PHPStan level 9 nos 6 arquivos novos: 0 errors.
- ✅ PHPStan level 9 nos 2 arquivos editados (SendChatMessageAction, ChatMessageController): +1 erro vs baseline 65 (padrão pré-existente do arquivo, não novo tipo).
- ✅ Pest suite nova: 19/19 passing (45 asserts).
- ⏳ `composer gate:all` ainda não executado (pendente para fase Confirm completa).

## Referências

- Feature doc: `.context/DOCS/FEATURES/FEAT-050-meta-message-templates.md`
- Tasks doc: `.context/DOCS/TASKS/FEAT-050-tasks.md`
- Memória correlata: `.context/DOCS/MEMORY/2026-04-29-feat-050-template-management.md`
- Memória de padrão (entrypoints esquecem guard): debug/MEMORY.md (`AiGateKeeperListener`)
