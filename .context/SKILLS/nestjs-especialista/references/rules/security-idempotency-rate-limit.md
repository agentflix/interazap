# Segurança, Idempotência E Rate Limit

## Segredos

- Segredos ficam em `.env`, secret manager ou infraestrutura equivalente; nunca em código, fixtures reais, logs ou snapshots.
- Masque `Authorization`, bearer tokens, API keys, senhas, assinaturas HMAC, secrets OpenAI/Asaas/WhatsApp/Telegram e cookies.
- Se um segredo aparecer no histórico ou log, registre o risco e solicite rotação.

## Guards E Autorização

- Use guards para API interna, WebSocket auth, throttling e validação de assinatura.
- Endpoints internos devem exigir `INTERNAL_API_KEY` ou mecanismo equivalente.
- Não confie em `tenant_id`, `user_id`, role ou room enviados pelo cliente sem validação de posse.

## Webhooks

- Valide HMAC/assinatura quando provider suportar.
- Deduplicate com Redis `SETNX` e TTL antes de processamento pesado.
- Responda duplicatas com sucesso seguro para evitar retry infinito do provider.
- Nunca bloqueie ACK aguardando provider externo, IA ou operação lenta.

## Rate Limit

- Preserve throttling HTTP global e throttling WebSocket configurados no `AppModule`.
- Aplique rate limit por tenant/provider em filas e clients externos.
- Não use apenas IP para fluxos multi-tenant quando houver identidade melhor.

## Erros

- Mensagens públicas devem ser genéricas; detalhes técnicos ficam em logs protegidos.
- Erros de integração devem preservar código, provider, correlation id e categoria sem expor payload sensível.
