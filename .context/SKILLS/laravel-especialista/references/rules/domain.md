# Regras Laravel: Domínio

Use estas regras ao criar ou alterar código em `api/src/Domain/{Context}/`.

## Padrão do Repositório

- Seguir DDD por bounded context: `Controller -> DTO -> Action -> Resource`.
- Manter namespaces como `Domain\{Context}\...`.
- Criar arquivos no contexto correto: `Ai`, `Auth`, `Billing`, `Chat`, `Configuration`, `CRM`, `Dashboard`, `Gateway`, `Platform`, `Reports` ou `Shared`.
- Usar `Shared` apenas para kernel reutilizável, suporte transversal e infraestrutura comum.
- Consultar a task ou documentação funcional correspondente antes de implementar e `.context/DOCS/MEMORY/` antes de decidir padrões novos.

## Estrutura Esperada

- `Http/Controllers/{Context}{Entity}Controller.php`
- `Http/Requests/{Context}{Entity}{Verb}Request.php`
- `Http/Resources/{Context}{Entity}Resource.php`
- `Actions/{Context}{Entity}Actions.php` ou uma Action focada quando já existir esse padrão no contexto.
- `DTOs/{Context}{Entity}DTO.php`
- `Models/{Context}{Entity}.php`
- `Policies/{Context}{Entity}Policy.php`
- `Routes/{context}.php`

## Regras de Código

- Iniciar todo PHP novo com `declare(strict_types=1);`.
- Preferir `final class` para Controllers, Actions e DTOs.
- Usar phpDoc em classes e métodos públicos, conforme `api/AGENTS.md`.
- Não colocar regra de negócio em controller, request, resource ou model quando ela pertence a uma Action.
- Evitar novo serviço abstrato quando uma Action focada resolve o caso de uso.

## Multi-tenancy

- Toda entidade tenant-aware deve ter `tenant_id` e usar `Domain\Shared\Concerns\BelongsToTenant`.
- Toda busca por recurso de tenant deve filtrar por `tenant_id` ou usar método existente que já faça isso.
- Nunca usar `withoutGlobalScope(TenantScope::class)` sem justificativa documentada em MEMORY.
- Testar isolamento entre tenants para novas superfícies de leitura/escrita.
