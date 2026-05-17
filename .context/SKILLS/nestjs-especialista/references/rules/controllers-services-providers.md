# Controllers, Services E Providers

## Controllers

- Use controllers para validação de entrada, autenticação/autorização declarativa, extração de parâmetros e retorno de contrato HTTP.
- Mantenha ACK de webhooks rápido; não aguarde chamadas externas, banco ou IA no caminho síncrono quando isso ameaçar 150 ms.
- Aplique `@UseGuards`, `@UseInterceptors` e `@UsePipes` conforme o endpoint. Preserve o `ValidationPipe` global e adicione pipes locais apenas para necessidade específica.
- Normalize labels de métricas antes de registrar provider, tenant, status ou outcome.

## Services

- Centralize regra de negócio e orquestração em services injetáveis.
- Use `Logger` com o nome da classe; registre IDs técnicos, `tenant_id`, `correlation_id` e outcomes, mas nunca segredos.
- Prefira funções pequenas para passos que precisam de teste isolado, como dedupe, normalização, retry e decisão de fallback.
- Para operações fire-and-forget, capture e registre erro em `.catch()` para evitar rejeições não observadas.

## Providers E Adapters

- Providers externos devem encapsular URL, autenticação, timeout, retry, circuit breaker e tradução de erro.
- Adapters/normalizers convertem payloads de terceiros para DTOs/modelos internos sem contaminar controllers.
- Factories selecionam provider por configuração ou tipo de instância; não espalhe `switch` de provider por controllers.
- Preserve os padrões existentes em `chat/providers`, `billing/providers`, `ai/providers` e `bot/services`.

## Consumers

- Consumers de filas ou streams devem processar payloads tipados, validar campos mínimos, aplicar idempotência e registrar resultado observável.
- ACK de mensagem assíncrona só deve ocorrer depois de persistir, emitir ou concluir a ação necessária com segurança.
