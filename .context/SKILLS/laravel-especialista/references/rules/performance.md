# Regras Laravel: Performance

Use estas regras ao implementar endpoints, Actions, Resources e consultas Eloquent em caminhos de alto volume.

## Eloquent E Queries

- Planejár a query na Action; controller e Resource não devem disparar consultas acidentais.
- Usar `with()` e `withCount()` para eliminar N+1; validar com teste quando a tela/listagem for critica.
- Em listagens, usar `select()` com apenas colunas necessarias e relacionamentos com select restrito.
- Preferir `exists()` a `count() > 0`.
- Evitar `->get()` em tabelas grandes; usar `paginate()`, `cursorPaginate()`, `chunkById()` ou `lazyById()`.
- Definir limite maximo de `per_page` via normalizador ou request.
- Evitar `Collection` em memória para transformar datasets grandes; empurrar filtros/agregações para SQL.

## Cache

- Chaves de cache devem incluir `tenant_id` quando o dado for tenant-scoped.
- Definir TTL pequeno para contadores em tempo quase real; TTL maior para relatórios e catálogos estáveis.
- Invalidar cache em Action, Event, Observer ou listener conhecido; não depender de expirar por sorte.
- Evitar cache stampede com locks quando popular cache caro.
- Nunca cachear payload com permissão, plano ou feature flag sem incluir o escopo correto na chave.

## Payload E API

- Resources devem usar `whenLoaded()` e não carregar relações por conta própria.
- Respostas de listagem devem ser paginadas e com envelope/meta padrão.
- Evitar retornar blobs, exports ou relatórios pesados de forma síncrona; usar job + arquivo + notificação.
- Para CSV/XLSX grande, usar streaming, chunks e sanitização de celulas.

## Validação De Performance

- Para mudanças em listagens críticas, verificar N+1 e quantidade de queries.
- Para query suspeita, usar `EXPLAIN ANALYZE` em ambiente local com dados realistas.
- Registrar em MEMORY quando uma otimização depender de índice, cache ou trade-off relevante.
