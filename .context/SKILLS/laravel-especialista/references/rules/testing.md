# Regras Laravel: Testes

Use estas regras para testes Pest em `api/tests`.

## Padrão

- Todo comportamento novo deve ter teste.
- Preferir feature tests para endpoints e unit tests para serviços/Actions puros.
- Nomear testes por contexto, por exemplo `tests/Feature/Domain/Ai/...` ou `tests/Feature/CRM...`, seguindo o padrão existente.
- Usar factories e helpers locais; não depender de ordem global de testes.

## Multi-tenancy e Autorização

- Criar usuário autenticado com tenant correto e usar `actingAs()`.
- Testar `403`/negação quando permissão/policy for relevante.
- Testar isolamento entre tenants para leitura, atualização e exclusão.
- Não aceitar `tenant_id` vindo do request em endpoints tenant-scoped.

## Banco e Filas

- Assertar estado no banco com `assertDatabaseHas/Missing` incluindo `tenant_id`.
- Usar `Queue::fake()`, `Event::fake()` e `Http::fake()` com escopo claro.
- Para jobs, testar dispatch e comportamento interno separadamente quando ambos importam.

## Regressão

- Ao corrigir bug, criar teste que falha antes da correção.
- Não deixar `it.skip`, `test.skip`, `todo` ou asserts fracos sem justificativa documentada.
