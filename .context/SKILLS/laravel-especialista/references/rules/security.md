# Regras Laravel: Segurança

Use estas regras ao alterar endpoints, policies, webhooks, exports e integrações.

## Autorização E IDOR

- Todo controller protegido deve chamar `$this->authorize()`.
- Toda busca por recurso tenant-scoped deve filtrar por `tenant_id`.
- Testar acesso negado para outro tenant quando houver risco de IDOR.
- Não confiar em IDs enviados pelo cliente sem validar posse/escopo.

## Requests E Validação

- Validar tipos, UUIDs, tamanho maximo e formatos.
- Sanitizar busca livre antes de usar `LIKE/ILIKE`.
- Não aceitar `tenant_id`, `user_id` privilegiado ou role sensível do payload sem contexto administrativo explícito.

## Webhooks

- Validar assinatura/HMAC quando provider suportar.
- Deduplicar evento com chave estável e TTL.
- Responder de forma segura a evento duplicado.
- Logar event id e provider, não segredo.

## Rate Limit

- Rotas publicas precisam de throttle.
- Rotas sensíveis autenticadas podem precisar de throttle especifico por tenant/user/IP.
- Jobs que chamam providers externos devem usar rate limit por tenant/provider quando aplicável.

## Exports

- Sanitizar CSV contra formula injection.
- Verificar autorização antes de gerar arquivo.
- Evitar export síncrono grande; preferir job com expiração de arquivo.

## Segredos

- Segredos ficam em env/secret manager.
- Nunca registrar `.env`, token, assinatura, bearer token, chave OpenAI/Asaas/WhatsApp.
- Se segredo aparecer no historico, registrar risco e pedir rotação.
