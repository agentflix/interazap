# DTOs E Validação

## DTOs

- Crie DTOs próximos ao domínio consumidor em `dto/`, `contracts/` ou `interfaces/` conforme o padrão local.
- Use `class-validator` para contratos recebidos por HTTP; combine com `class-transformer` quando houver conversão de tipos.
- Defina campos opcionais explicitamente com `@IsOptional()` e valide limites, enums, UUIDs, URLs, strings vazias e arrays.
- Para payloads externos instáveis, aceite `raw` apenas quando necessário e mantenha normalização em service/interceptor dedicado.

## Pipes

- Preserve `whitelist: true`, `transform: true` e, quando aplicável, `forbidNonWhitelisted: true`.
- Pipes customizados devem ser pequenos, testados e usados para validação contextual, como token de instância ou parsing de provider.

## Contratos

- Contratos internos entre API Laravel e Gateway devem carregar `correlation_id`, `tenant_id`, domínio, ação, provider, payload e metadata quando o fluxo exigir rastreabilidade.
- Não aceite `tenant_id` privilegiado de cliente público sem validação por token, assinatura, API interna ou contexto autenticado.
- Valide compatibilidade com testes de DTO existentes antes de alterar nomes de campos consumidos por API, App ou Electron.
