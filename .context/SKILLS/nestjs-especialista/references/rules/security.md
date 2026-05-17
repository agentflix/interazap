# Segurança

## Autenticação e autorização

- Use JWT para WebSocket e API key interna para endpoints internos.
- `INTERNAL_API_KEY`, `JWT_SECRET`, tokens de providers e webhooks secrets nunca devem aparecer em logs ou código.
- Guards devem falhar fechados quando segredo/configuração obrigatória estiver ausente.
- Frontend não deve chamar endpoints internos diretamente.

## Hardening HTTP

- Preserve Helmet no bootstrap.
- Preserve CORS por allowlist; não use `*` em produção.
- `ValidationPipe` global deve manter `whitelist`, `forbidNonWhitelisted` e `transform`.
- Limite body size conscientemente; payload grande deve ser exceção documentada.

## Dados sensíveis

- Use mascaramento para tokens, senhas, API keys, assinaturas e payloads pessoais.
- Não registre stack trace de provider com segredo embutido.
- Não retorne erro bruto de provider ao cliente.

## Rate limit

- Preserve throttling HTTP e WebSocket.
- Aplique rate limit específico a providers caros ou sensíveis: WhatsApp, Telegram, OpenAI, Asaas e webhooks públicos.
