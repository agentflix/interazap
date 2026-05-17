# Services e Stores

## Services

- Services HTTP usam `HttpClient`, `inject()`, `environment.apiUrl` e retornam `Observable` tipado.
- Defina `baseUrl` por domínio (`${environment.apiUrl}/dashboard`, por exemplo) e monte query string com `HttpParams`.
- Não faça subscribe em services HTTP comuns; deixe a page/store controlar ciclo de vida e side effects.
- Use `withCredentials: true` quando o contrato de autenticação/cookies exigir, seguindo serviços existentes.
- Trate integração mobile por services de plataforma (`core/services/platform/*`) em vez de espalhar chamadas Capacitor.

## Stores

- Stores locais ou globais podem usar `signal`, `computed` e métodos de ação explícitos.
- Exponha sinais como leitura pública e mantenha mutação encapsulada quando o estado for compartilhado.
- Use nomes consistentes: `isLoading`, `isSaving`, `error`, `data`, `selected...`, `has...`, `can...`.
- Proteja ações contra concorrência duplicada quando houver envio/salvamento (`isSaving`, `isSending`).
- Para efeitos com subscribe dentro da store, use `DestroyRef` e `takeUntilDestroyed` quando a instância tiver ciclo de vida relevante.

## Modelos e cache

- Tipos compartilhados ficam em `shared/models` ou `core/models`; tipos exclusivos da feature ficam próximos da page.
- Cache local deve ter invalidação clara, escopo por usuário/tenant quando aplicável e fallback em erro.
- Nunca armazene payload sensível sem necessidade. Tokens e auth devem seguir serviços existentes.
