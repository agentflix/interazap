# Regras Laravel: Performance de Banco de Dados

Use estas regras ao tocar migrations, Actions com SQL pesado, relatórios ou tabelas grandes.

## Índices

- Toda tabela tenant-aware precisa de índice em `tenant_id`.
- Criar índices compostos iniciando por `tenant_id` quando a maioria das queries filtra pelo tenant.
- Indexar FKs e colunas usadas em `where`, `orderBy`, `join` e filtros frequentes.
- Evitar índice redundante quando um índice composto já cobre o prefixo necessário.
- Para unicidade tenant-scoped, preferir unique composto: `tenant_id + campo`.

## Queries Grandes

- Evitar offset pagination em tabelas muito grandes; preferir cursor/keyset quando possível.
- Usar `whereBetween` em ranges temporais com índice composto adequado, como `(tenant_id, created_at)`.
- Evitar `ILIKE '%termo%'` em tabelas grandes sem estratégia de índice/trigram ou busca dedicada.
- Evitar `OR` amplo quando puder separar em queries menores ou usar índice apropriado.
- Em relatórios, agregar no banco e retornar payload já resumido.

## Transações E Locks

- Manter transações curtas: sem HTTP externo, sem chamada a IA e sem operação de arquivo dentro da transação.
- Usar `lockForUpdate()` apenas quando existir risco real de corrida.
- Definir ordem consistente de locks para evitar deadlocks.
- Tratar deadlocks/retries em jobs quando a operação for segura e idempotente.

## PostgreSQL E pgvector

- Validar plano com `EXPLAIN ANALYZE` antes de assumir que um índice ajuda.
- Para pgvector, confirmar dimensão, operador, índice e filtro por tenant antes da busca vetorial.
- Separar filtros seletivos (`tenant_id`, status, data) da ordenação vetorial quando isso reduzir custo.

## Anti-padrões

- Não fazer query cross-tenant sem justificativa documentada.
- Não usar raw SQL para contornar DDD se Eloquent/Query Builder resolverem com clareza.
- Não criar índice em toda coluna nova; índice custa escrita, memória e manutenção.
