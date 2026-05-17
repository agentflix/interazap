# Regras Laravel: Octane

Use estas regras para código executando sob Laravel Octane.

## Regra Principal

Octane mantém workers vivos entre requests. Qualquer estado em singleton, static, cache local ou propriedade de serviço pode vazar entre tenants se não for resetado.

## Código Seguro Para Octane

- Não armazenar `Request`, usuário autenticado, `tenant_id`, token, permissão ou payload em propriedade de singleton.
- Preferir resolver dados por request dentro do método, não no construtor de serviços stateful.
- Limpar contexto tenant ao final de cada request quando usar `TenantContext`.
- Evitar variáveis `static` como cache de dado tenant-scoped.
- Tratar facades e singletons customizados como persistentes até prova contrária.

## Banco, Redis E Conexões

- Não manter transação aberta entre callbacks, eventos ou operações assíncronas.
- Fechar/renovar conexões problemáticas conforme suporte do Octane/Laravel.
- Não guardar instâncias de cliente externo com estado de tenant.

## Deploy E Reload

- Recarregar Octane após deploy, mudança de `.env`, config, rotas ou código.
- Combinar `config:cache`, `route:cache` e reload de Octane no processo de release.
- Definir limite de requests/tempo por worker para mitigar vazamentos.

## Testes E Revisão

- Revisar qualquer uso de `static`, singleton, cache em memória e service provider.
- Criar teste ou checklist manual para garantir que request do tenant A não influencie tenant B.
- Se uma feature depende de contexto global, documentar reset e risco em MEMORY.
