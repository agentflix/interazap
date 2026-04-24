# MEMORY — Autopilot: Snapshot publishing (publisher-side hydration)

> Data: 2026-04-23
> Escopo: `Domain\Ai` (api) + `gateway/src/domains/ai` (consumer)
> Status: Decidido e implementado (QW1)

## Decisão

A publicação de runs do Autopilot em `ai.run.request` (Redis Stream) passa a **carregar snapshots hidratados** de `prompt`, `context` e `tools` no próprio payload do XADD, com timestamp `hydrated_at`. O gateway já consumia esses campos opcionais via `getPromptSnapshot/getContextSnapshot/getToolsSnapshot` com gate de frescor de 90s (`isRecent`). Quando o snapshot está presente e fresco, o gateway pula a chamada HTTP para `/internal/ai/{prompt,context,tools}`.

Falha em hidratar qualquer slice é **silenciosa**: o resolver retorna `null` para aquele campo e o gateway faz o fallback HTTP normal. Mudança 100% backward-compatible.

## Por que (em ordem de peso)

1. **Latência**: cada run pagava 2–3 RTTs HTTP gateway↔API (~30–100ms cada em rede local; pior em prod). Eliminar é o ganho mais barato/seguro.
2. **Carga**: as rotas internas `/internal/ai/*` viram pico proporcional ao volume de runs. Menos chamadas = menos pressão em PHP-FPM.
3. **Locality**: o publisher (Laravel) já tem o `AiAgent` carregado e os services (`AiPromptResolverService`, `ToolDispatcherService`) já têm cache. O custo marginal de hidratar no publisher é trivial — e amortizado.
4. **Backward compat**: o consumer continua aceitando payload sem snapshots. Rollback = remover a injeção no job, sem migration nem deploy coordenado.

## Alternativas consideradas e descartadas

- **Cache no gateway** para `fetchPrompt/fetchContext` (igual ao que já existe para `fetchTools`): resolveria parte do problema, mas (a) ainda paga 1º RTT por chave nova, (b) duplica fonte de verdade entre PHP e gateway, (c) invalidação fica acoplada (precisaria invalidar cache do gateway quando o prompt mudar no PHP).
- **gRPC ou TCP keep-alive** entre gateway e API: ganho marginal vs complexidade alta. Não justifica.
- **Inlining dos snapshots em outro stream/topic dedicado**: complexidade desnecessária. O stream `ai.run.request` já carrega o run; agregar snapshots é natural.

## Armadilhas / Pontos de atenção

- O contexto cacheado em `autopilot:snapshot:context:{ticketId}` por 300s pode ficar **stale** se o ticket mudar `status`/`subject` no meio. Aceitável: o gateway só usa context para metadados de conversa; mudança de status a cada 5min não impacta resposta da IA.
- `hydrated_at` é gravado **sempre** (mesmo quando snapshots estão `null`). O gateway só pula HTTP se o campo do snapshot **estiver presente E** `isRecent(hydrated_at)`. Threshold no gateway = 90s.
- `prompt` e `tools` herdam o cache existente dos services (60min para prompt, 3600s para tool definitions). Não criar duplicação de cache aqui.
- Se um campo do snapshot vier mal-formatado, o gateway parseia tolerantemente e cai para HTTP. Logs do gateway mostram `[INFO] Using snapshot...` quando consumiu o snapshot.

## Próximos passos (não bloqueantes)

1. Instrumentar métrica no gateway: ratio `snapshot_hit / total_runs` por slice (prompt/context/tools).
2. Medir latência p50/p95 do consumer antes vs depois.
3. Se cache hit < 80%, investigar (pode ser que `hydrated_at` esteja envelhecendo no stream — sugere reduzir lag do consumer, não aumentar TTL).
4. **NÃO** prosseguir com QW2 (cache-friendly system prompt prefix) sem antes ter telemetria de `cached_prompt_tokens`.
5. **NÃO** prosseguir com QW3 (truncar tool history no PHP) — o caminho PHP é deprecated em favor do gateway.
