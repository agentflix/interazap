# Services e Providers

## Services

- Services ficam em `src/domains/{domain}/services` quando pertencem a domínio.
- Services transversais devem ficar em `src/shared/services` ou `src/common`.
- Injete dependências pelo construtor e mantenha tipos explícitos.
- Não acople service de domínio diretamente a detalhes de provider se já existir client/adapter.
- Faça logs com contexto suficiente: domínio, ação, `tenant_id`, `correlation_id`, provider e identificadores relevantes.

## Providers externos

- Encapsule HTTP externo em client/provider dedicado.
- Toda chamada externa deve ter timeout explícito.
- Use retry/backoff apenas para falhas transientes e operações idempotentes.
- Use circuit breaker para provedores externos conforme padrão existente.
- Normalize respostas de provider antes de retornar ao domínio.
- Nunca registre headers de autenticação, API keys, tokens ou payload sensível completo.

## Laravel API

- O Gateway deve tratar a API Laravel como fonte autoritativa para regras administrativas e dados de negócio.
- Use `API_URL` e timeouts configurados para chamadas internas.
- Não duplique autorização de negócio da API; aplique apenas proteção operacional e contrato.
