# Regras Laravel: Contratos de API

Use estas regras ao criar endpoints, Resources, erros e integrações API ↔ Gateway.

## Envelope HTTP

- Respostas de sucesso devem seguir envelope do `BaseController`: `success`, `message`, `data`.
- Criação retorna 201; processamento assíncrono pode retornar 202.
- Exclusão sem corpo pode retornar 204.
- Listagens paginadas devem incluir `data`, `meta` e `links`.

## Erros

- 401 para não autenticado.
- 403 para autenticado sem permissão.
- 404 para recurso inexistente ou invisível ao tenant.
- 409 para conflito de estado ou operação duplicada não idempotente.
- 422 para validação.
- 429 para rate limit.
- 5xx apenas para falha inesperada.

## Idempotência

- Mutações expostas a retry de cliente ou webhook devem aceitar chave idempotente ou deduplicar por chave de negócio.
- Jobs disparados por endpoint mutante devem poder ser reprocessados sem duplicar efeito.

## API ↔ Gateway

- Frontends nunca acessam Gateway para operações de negócio quando a API e o ponto autoritativo.
- Contratos via Redis Streams devem incluir `tenant_id`, `correlation_id`, tipo, payload versionável e chave idempotente.
- Definir timeout e resposta de fallback para chamadas síncrono-assíncronas.

## Compatibilidade

- Evitar quebrar App/Electron: adicionar campos antes de remover ou renomear.
- Resources devem manter nomes estáveis.
- Mudança de contrato relevante deve ser documentada em feature/task e, se for decisão, em MEMORY.
