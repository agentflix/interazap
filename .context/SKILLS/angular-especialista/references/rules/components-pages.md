# Components e Pages

## Components

- Componentes reutilizáveis pertencem a `shared/components`; componentes específicos permanecem em `pages/{area}/components`.
- Use `selector` com prefixo `app-` para componentes de aplicação e preserve os componentes `Af*` já existentes quando forem o padrão visual.
- Prefira `input()`/`output()` quando a base local já estiver usando APIs modernas; ao alterar arquivo existente, siga o padrão daquele arquivo.
- Templates devem expor estados claros: carregando, erro, vazio, conteúdo e ação de retry quando aplicável.
- Evite lógica pesada em template. Mova composição de dados para `computed`, métodos puros ou store.

## Pages

- Pages roteáveis devem ser standalone, carregadas por `loadComponent` e com `data.title` na rota.
- Uma page coordena carregamento, permissões visuais, filtros e composição de componentes; regras de negócio e chamadas HTTP ficam em services/stores.
- Para formulários, prefira Reactive Forms quando houver validação, submissão ou estado complexo; mantenha mensagens de erro acessíveis e específicas.
- Estados otimistas devem ter rollback em erro e feedback com serviço local ou `ngx-sonner`, conforme padrão existente.

## UI

- Use os componentes compartilhados antes de criar variações novas.
- Preserve consistência visual com `app/src/styles.css`, `ui-kit` e layouts existentes.
- Use ícones da biblioteca configurada (`lucide-angular` ou registros existentes) quando houver equivalente.
- Não use texto em tela para explicar implementação, atalhos ou funcionamento interno.
