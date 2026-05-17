# Integrações Externas

## WhatsApp E Meta

- Isole Uazapi, Z-API e Meta em clients/adapters/providers próprios.
- Normalize eventos de webhook antes de processar regra de negócio.
- Preserve `instance_webhook_token`, provider e identificadores externos como base para resolução de tenant e idempotência.
- Mensagens de saída devem respeitar retry policy, rate limit do provider e status observável.

## Telegram

- Use os services e strategies de `bot/` para polling, webhook, normalização e client Telegram.
- Valide HMAC/assinatura quando configurado.
- Proteja polling/long polling contra loops apertados e falhas transitórias sem backoff.

## IA

- Use provider factory e adapters existentes para OpenAI, Gemini/Google e MiniMax.
- Aplique timeouts, max retries, controle de custo, guardrails e métricas por provider/modelo.
- Streaming deve lidar com cancelamento, erro parcial e resposta final correlacionada.
- Nunca registre prompt completo com dados sensíveis sem política explícita de mascaramento.

## Asaas

- Webhooks Asaas devem validar segredo/assinatura quando disponível, deduplicar evento e resolver tenant com service dedicado.
- Clients de cobrança devem tratar paginação, erro 4xx/5xx, timeout e circuit breaker.
- Não registre API key, customer data sensível ou payload financeiro completo.

## HTTP Externo

- Todo client externo deve ter timeout configurável, circuit breaker, logs mascarados e testes para sucesso, erro, timeout e fallback.
