# Regras Laravel: Migration

Use estas regras para migrations PostgreSQL no backend.

## Estrutura

- Toda nova tabela deve usar UUID primary key.
- Toda tabela tenant-aware deve ter `tenant_id` UUID, índice e FK para tenant quando aplicável.
- Declarar FKs com `onDelete()` explícito.
- Criar índices para `tenant_id`, FKs e colunas usadas em filtros frequentes.
- Implementar `down()` reversível.

## PostgreSQL

- Usar tipos coerentes com PostgreSQL 17.
- Para busca textual simples em filtros, seguir padrão local com `ilike` e sanitização via `SearchSanitizer`.
- Para pgvector, seguir exemplos de `Ai` e validar casts/queries com cuidado.
- Evitar raw SQL quando Schema Builder cobre o caso; usar raw apenas para recurso especifico do Postgres ou performance justificada.

## Dados

- Não inserir dados de negócio em migration salvo requisito explícito.
- Seeders devem ser separados e opt-in quando forem pesados.
- Nunca commitar segredos, tokens ou URLs sensíveis em migrations/seeders.

## Validação

- Rodar `php artisan migrate --pretend` quando a task envolver schema.
- Cobrir comportamento com Pest, não apenas existência da migration.
