# Controllers e DTOs

## Controllers

- Controllers ficam em `src/domains/{domain}/controllers`.
- Controllers devem ser finos: validar entrada, aplicar guards/interceptors quando necessário, chamar service e retornar contrato.
- Use `Logger` dedicado quando houver logs no controller.
- Não faça chamada externa, acesso direto ao Redis ou regra complexa dentro do controller.
- Endpoints internos devem usar `InternalApiKeyGuard` ou proteção equivalente.

## DTOs

- DTOs ficam em `src/domains/{domain}/dto` ou `interfaces` quando o domínio já usa esse padrão.
- Use `class-validator` e `class-transformer` para payloads HTTP.
- Confie no `ValidationPipe` global com `whitelist`, `forbidNonWhitelisted` e `transform`.
- Modele campos opcionais explicitamente e evite `any`.
- Preserve nomes de campos compatíveis com API Laravel, providers e App.

## Contratos HTTP

- Retorne erros HTTP coerentes: `400` validação, `401` autenticação, `403` autorização, `404` inexistente, `409` conflito, `429` rate limit e `5xx` falha inesperada.
- Não exponha stack trace, segredo, token ou resposta completa de provider em erro ao cliente.
- Para webhooks, retorne ACK rápido e processe o trabalho pesado fora do caminho crítico.
