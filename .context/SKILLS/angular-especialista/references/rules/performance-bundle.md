# Performance e Bundle

## Build e budgets

- `app/angular.json` define budget inicial com warning em `3.0MB` e erro em `3.5MB`, além de limite de estilo por componente.
- Não adicione dependência pesada sem justificar ganho e verificar impacto no bundle.
- Prefira lazy loading para pages, módulos de charts, áreas administrativas e integrações raras.

## Angular

- Use `OnPush`, `signal`/`computed`, track em listas e templates simples.
- Evite chamadas de método custosas em template.
- Cancele streams e timers no ciclo de vida apropriado.
- Para listas grandes, considere paginação, virtual scroll ou carregamento incremental.

## Assets e UI pesada

- Otimize imagens e não inclua mídia grande no bundle sem necessidade.
- Charts devem receber dados já normalizados e não recalcular séries grandes a cada detecção de mudança.
- Ícones devem usar registry/pick existente para evitar importar biblioteca inteira quando não necessário.

## Rede

- Debounce buscas e filtros digitáveis.
- Evite requisições duplicadas em inicialização de pages e stores.
- Use cache apenas quando a consistência do domínio permitir.
