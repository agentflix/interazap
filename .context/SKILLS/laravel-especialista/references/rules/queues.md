# Regras Laravel: Filas e Horizon

Use estas regras ao criar jobs, configurar filas ou alterar processamento assíncrono.

## Filas

- Usar Redis como backend de filas em produção.
- Separar filas por criticidade e perfil de custo:
  - `critical`: operações pequenas e urgentes.
  - `auto-reply`: respostas de chatbot/webchat com baixa latência.
  - `ai`: chamadas de IA e processamento associado.
  - `media`: arquivos, transcrição e anexos.
  - `reports`: exports e relatórios pesados.
  - `default`: trabalho comum.
  - `low`: tarefas adiáveis.
- Não misturar jobs lentos com jobs interativos na mesma fila quando isso causar starvation.

## Horizon

- Ajustar supervisor por fila, com `maxProcesses`, `timeout`, `memory`, `maxJobs` e `maxTime`.
- Reiniciar workers periodicamente (`maxJobs`/`maxTime`) para conter vazamentos de memória.
- Configurar alertas de falha e fila parada via Horizon.
- Usar tags em jobs para `tenant_id`, contexto e tipo de trabalho quando isso ajudar observabilidade.

## Jobs

- Passar IDs escalares, não models.
- Definir `$tries`, `$timeout`, `$backoff`, `$maxExceptions` e `retryUntil()` quando aplicável.
- Usar backoff com jitter para integrações externas.
- Usar idempotência para jobs que podem ser duplicados por retry, webhook ou reprocessamento.
- Usar rate limiting por tenant/provider para WhatsApp, IA e pagamentos.

## DLQ e Reprocessamento

- Toda falha irreversível deve registrar contexto suficiente para reprocessar com segurança.
- Retry manual deve respeitar idempotência.
- Não apagar payload de falha antes de existir evidência ou trilha de auditoria.
- Jobs de webhook devem tolerar eventos duplicados e fora de ordem.

## Redis Streams

- Usar Redis Streams para comunicação API ↔ Gateway quando o fluxo precisar de idempotência, resposta assíncrona ou rastreabilidade.
- Incluir `correlation_id`, `tenant_id`, tipo de evento e chave idempotente no payload.
- Definir timeout e comportamento de fallback quando o Gateway não responder.
