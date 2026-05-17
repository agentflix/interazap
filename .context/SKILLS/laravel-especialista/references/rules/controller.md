# Regras Laravel: Controller

Use estas regras ao criar ou alterar controllers, requests, resources, policies e rotas.

## Controller

- Estender `Domain\Shared\Http\Controllers\BaseController` quando precisar de respostas padronizadas.
- Manter controller fino: validar/autorização, montar DTO, chamar Action, retornar Resource.
- Injetar Actions pelo construtor com `private readonly`.
- Chamar `$this->authorize()` em cada operação protegida.
- Extrair tenant do usuário autenticado ou usar `$this->tenantId($request)`; não aceitar `tenant_id` do payload público.
- Retornar `success()`, `created()`, `accepted()`, `deleted()` ou envelope equivalente já usado no contexto.

## Request

- Criar `FormRequest` para payload mutante ou filtros complexos.
- `authorize()` deve validar usuário autenticado e tenant quando aplicável.
- `rules()` deve usar tipos explícitos, `uuid` para IDs e `exists` alinhado ao contexto.
- Normalizar dados em DTO/Action, não diretamente no controller quando houver regra de negócio.

## DTO

- Criar DTO `final readonly` para entrada de Action.
- Fornecer `fromRequest()` quando o DTO vier de `FormRequest`.
- Expor `toArray()` apenas com campos persistíveis; não incluir campos auxiliares que não pertencem ao model.

## Resource

- Preferir `BaseJsonResource` quando o contexto já usa esse padrão.
- Usar `whenLoaded()` para relacionamentos; evitar disparar queries dentro do Resource.
- Padronizar datas com helper local, como `iso()`, quando disponível.

## Rotas

- Registrar rotas no arquivo do contexto em `api/src/Domain/{Context}/Routes/`.
- Aplicar `auth:sanctum` para endpoints autenticados.
- Declarar rotas estáticas antes de rotas dinâmicas (`/export` antes de `/{id}`).
- Proteger endpoints públicos com throttle, token, assinatura ou regra equivalente.
