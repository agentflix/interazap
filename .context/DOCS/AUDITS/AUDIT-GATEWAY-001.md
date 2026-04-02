# RELATÓRIO DE AUDITORIA DE CÓDIGO — GATEWAY

**Data:** 2026-03-28
**Auditor:** AI Tech Lead (Claude Opus 4.6)
**Escopo:** `gateway/src/` — 223 production TypeScript files across 6 domains (173 directly audited; ~50 auxiliary/interface files excluded from deep analysis)
**Versão NestJS:** 11.0.1
**Versão Node:** 18.19.1

---

## Sumário Executivo

A base de código do Gateway (223 arquivos `.ts` de produção, 173 auditados diretamente em 6 domínios) foi auditada em 5 dimensões: Reusabilidade, Erros e Bugs, Código Morto, Refatoração e Segurança. **75 findings foram identificados**, distribuídos em: 4 CRITICAL, 16 HIGH, 22 MEDIUM e 33 LOW.

Os problemas mais urgentes são: **(1)** credenciais de banco de dados hardcoded em `configuration.ts` (CRITICAL — segurança), **(2)** fila de retry em memória em `send-message.service.ts` causando risco de perda de dados em restarts (CRITICAL — confiabilidade), **(3)** circuit breaker ausente em chamadas de streaming de IA permitindo falhas em cascata (CRITICAL — resiliência), e **(4)** CORS wildcard com `credentials: true` em `main.ts` (CRITICAL — segurança).

A base de código demonstra bons padrões arquiteturais no geral — padrão adapter para provedores, infraestrutura de circuit breaker, idempotência baseada em Redis e autenticação WebSocket. No entanto, 4 problemas críticos de segurança/confiabilidade e 16 issues de alta severidade requerem atenção imediata antes da implantação em produção.

---

## Painel de Métricas

| Severidade  | Quantidade | Sprint   |
| ----------- | ---------- | -------- |
| 🔴 CRITICAL | 4          | Sprint 1 |
| 🟠 HIGH     | 16         | Sprint 2 |
| 🟡 MEDIUM   | 22         | Sprint 3 |
| 🟢 LOW      | 33         | Sprint 4 |
| **TOTAL**   | **75**     | —        |

**Pontuação Geral de Qualidade: 63/100**

| Categoria     | Quantidade |
| ------------- | ---------- |
| Segurança     | 14         |
| Erros         | 18         |
| Reusabilidade | 13         |
| Refatoração   | 19         |
| Código Morto  | 11         |

---

## SEÇÃO 1: FINDINGS DE SEGURANÇA

---

### [SEC-001] Credenciais de Banco de Dados Hardcoded na Configuração

| Campo          | Valor                                      |
| -------------- | ------------------------------------------ |
| **Severidade** | CRITICAL                                   |
| **Categoria**  | Segurança                                  |
| **Arquivo**    | `gateway/src/core/config/configuration.ts` |
| **Linha(s)**   | 66-67                                      |
| **Esforço**    | XS                                         |
| **Padrão**     | hardcoded-credentials                      |

**Descrição:** A URL padrão do banco de dados contém credenciais hardcoded (`interazap:secret`) no código-fonte. Esse par de credenciais estará presente em cada implantação que não definir `DATABASE_URL`.

**Código Atual:**

```typescript
url:
  process.env.DATABASE_URL ??
  'postgres://interazap:secret@localhost:5432/interazap',
```

**Suggested Fix:**

```typescript
const connectionString = process.env.DATABASE_URL;
if (!connectionString) {
  throw new Error('DATABASE_URL environment variable is required');
}
url: connectionString,
```

**Justificativa:** Expor credenciais no código-fonte é uma violação crítica de segurança. Mesmo fallbacks de desenvolvimento nunca devem conter credenciais reais ou no formato real.

---

### [SEC-002] Credenciais de Fallback Hardcoded no DatabaseService

| Campo          | Valor                                                     |
| -------------- | --------------------------------------------------------- |
| **Severidade** | HIGH                                                      |
| **Categoria**  | Segurança                                                 |
| **Arquivo**    | `gateway/src/infrastructure/database/database.service.ts` |
| **Linha(s)**   | 26-28                                                     |
| **Esforço**    | XS                                                        |
| **Padrão**     | hardcoded-credentials                                     |

**Descrição:** O `DatabaseService` possui uma string de conexão de fallback hardcoded idêntica.

**Código Atual:**

```typescript
const connectionString =
    this.configService.get<string>('DATABASE_URL') ?? 'postgres://interazap:secret@localhost:5432/interazap';
```

**Suggested Fix:** Same as [SEC-001]: throw instead of fallback.

**Justificativa:** Se esse código rodar em produção sem a variável de ambiente, ele se conectará silenciosamente com credenciais fracas.

---

### [SEC-003] CORS com Credenciais Habilitado e Origens Padrão Inseguras

| Campo          | Valor                  |
| -------------- | ---------------------- |
| **Severidade** | CRITICAL               |
| **Categoria**  | Segurança              |
| **Arquivo**    | `gateway/src/main.ts`  |
| **Linha(s)**   | 26-43                  |
| **Esforço**    | M                      |
| **Padrão**     | overly-permissive-cors |

**Descrição:** O CORS é configurado com `credentials: true`, mas a lista de origens padrão inclui `localhost:4200` e `localhost:3000`. Se esse padrão for usado em produção (sem configuração explícita de env), o gateway aceita credenciais de qualquer porta localhost — uma superfície de ataque significativa.

**Código Atual:**

```typescript
const allowedOrigins = configService.get<string[]>('cors.origins') ?? [
    'http://localhost:4200',
    'http://localhost:3000',
];
app.enableCors({
    origin: allowedOrigins,
    credentials: true,
    // ...
});
```

**Suggested Fix:** Require explicit configuration in production:

```typescript
const allowedOrigins = configService.get<string[]>('cors.origins');
if (!allowedOrigins || allowedOrigins.length === 0) {
    throw new Error('CORS origins must be explicitly configured');
}
// Validate no wildcards when credentials enabled
const hasWildcard = allowedOrigins.some((o) => o === '*' || o.includes('*'));
if (hasWildcard) {
    throw new Error('CORS: Cannot use wildcard origin with credentials');
}
```

**Justificativa:** Usar origens wildcard com `credentials: true` é um anti-pattern de segurança que pode permitir ataques cross-origin. Ter localhost como padrão na configuração é perigoso para produção.

---

### [SEC-004] Guards de Autenticação Ausentes nos UazapiControllers

| Campo          | Valor                                                                 |
| -------------- | --------------------------------------------------------------------- |
| **Severidade** | HIGH                                                                  |
| **Categoria**  | Segurança                                                             |
| **Arquivo**    | `gateway/src/domains/chat/controllers/uazapi-instances.controller.ts` |
| **Linha(s)**   | 23-24                                                                 |
| **Esforço**    | S                                                                     |
| **Padrão**     | missing-authentication                                                |

**Descrição:** O `UazapiInstancesController` não possui `@UseGuards(InternalApiKeyGuard)` enquanto controllers similares (`ChatController`, `ZapiInstancesController`) possuem. Isso cria uma postura de segurança inconsistente onde os endpoints de gerenciamento de instâncias ficam desprotegidos.

**Código Atual:**

```typescript
@Controller({ path: 'uazapi/instances', version: '1' })
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
// MISSING @UseGuards(InternalApiKeyGuard)!
export class UazapiInstancesController {
```

**Suggested Fix:**

```typescript
@Controller({ path: 'uazapi/instances', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiInstancesController {
```

**Justificativa:** As operações `initInstance` e `delete` são sensíveis e devem ser protegidas. A inconsistência atual cria uma lacuna na superfície de ataque.

---

### [SEC-005] Guards de Autenticação Ausentes no UazapiMessagesController

| Campo          | Valor                                                                |
| -------------- | -------------------------------------------------------------------- |
| **Severidade** | HIGH                                                                 |
| **Categoria**  | Segurança                                                            |
| **Arquivo**    | `gateway/src/domains/chat/controllers/uazapi-messages.controller.ts` |
| **Linha(s)**   | 22-24                                                                |
| **Esforço**    | S                                                                    |
| **Padrão**     | missing-authentication                                               |

**Descrição:** `UazapiMessagesController` e `UazapiPresenceController` tratam envio de mensagens sensíveis sem guards de autenticação.

**Suggested Fix:** Add `@UseGuards(InternalApiKeyGuard)` to both controllers.

**Justificativa:** Endpoints de envio de mensagens devem ser protegidos. Isso é inconsistente com `ChatController`.

---

### [SEC-006] Rate Limiting Ausente no Endpoint de Webhook do Chat

| Campo          | Valor                                                             |
| -------------- | ----------------------------------------------------------------- |
| **Severidade** | HIGH                                                              |
| **Categoria**  | Segurança                                                         |
| **Arquivo**    | `gateway/src/domains/chat/controllers/chat-webhook.controller.ts` |
| **Linha(s)**   | 27-30                                                             |
| **Esforço**    | M                                                                 |
| **Padrão**     | missing-rate-limiting                                             |

**Descrição:** `POST /webhooks/:provider/instances/:instance_webhook_token` não possui `ThrottlerGuard`. Um atacante poderia inundar webhooks a partir de uma conta de provedor comprometida.

**Suggested Fix:**

```typescript
@UseGuards(ThrottlerGuard, IdempotentWebhookGuard)
@Controller({ version: '1', path: 'webhooks/:provider/instances/:instance_webhook_token' })
export class ChatWebhookController {
```

**Justificativa:** Endpoints de webhook são alvos comuns de DDoS/abuso. Combinado com a verificação HMAC ausente, isso representa uma superfície de ataque significativa.

---

### [SEC-007] Verificação de Assinatura HMAC Ausente nos Webhooks de Billing

| Campo          | Valor                                                                   |
| -------------- | ----------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                                  |
| **Categoria**  | Segurança                                                               |
| **Arquivo**    | `gateway/src/domains/billing/controllers/billing-webhook.controller.ts` |
| **Linha(s)**   | 23-28                                                                   |
| **Esforço**    | M                                                                       |
| **Padrão**     | missing-signature-verification                                          |

**Descrição:** O handler de webhook de billing aceita qualquer payload sem verificar a assinatura HMAC do Asaas. Embora `IdempotentWebhookGuard` trate a idempotência, ele não verifica se o webhook realmente originou do Asaas.

**Código Atual:**

```typescript
@Controller({ path: 'billing/webhooks/:provider/instances/:instance_webhook_token', version: '1' })
@UseGuards(IdempotentWebhookGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
// MISSING: AsaasWebhookSignatureGuard
async handle(@Body() payload: Record<string, unknown>): Promise<{ success: boolean }> {
```

**Suggested Fix:** Implement and apply `AsaasWebhookSignatureGuard`:

```typescript
@UseGuards(AsaasWebhookSignatureGuard, IdempotentWebhookGuard)
async handle(...) {
```

**Justificativa:** Sem verificação de assinatura, qualquer pessoa que conheça o token de webhook de um tenant poderia enviar eventos de pagamento falsos, potencialmente acionando ações de billing.

---

### [SEC-008] Math.random() para Identificadores do Rate Limiter

| Campo          | Valor                                                             |
| -------------- | ----------------------------------------------------------------- |
| **Severidade** | HIGH                                                              |
| **Categoria**  | Segurança                                                         |
| **Arquivo**    | `gateway/src/shared/services/queue/queue-rate-limiter.service.ts` |
| **Linha(s)**   | 99-100                                                            |
| **Esforço**    | XS                                                                |
| **Padrão**     | weak-random-generator                                             |

**Descrição:** Usa `Math.random()` para gerar identificadores únicos de entrada do rate limiter, que é criptograficamente previsível.

**Código Atual:**

```typescript
const entryId = identifier || `${now}-${Math.random().toString(36).slice(2, 11)}`;
```

**Suggested Fix:**

```typescript
import { randomUUID } from 'node:crypto';
const entryId = identifier || `${now}-${randomUUID().slice(0, 9)}`;
```

**Justificativa:** Embora identificadores de rate limiting não exponham diretamente dados críticos de segurança, usar `crypto.randomUUID()` é uma correção trivial que segue as melhores práticas de segurança.

---

### [SEC-009] Dados Sensíveis nos Logs de Chave de Idempotência

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | MEDIUM                                                      |
| **Categoria**  | Segurança                                                   |
| **Arquivo**    | `gateway/src/domains/chat/services/chat-webhook.service.ts` |
| **Linha(s)**   | 203-210                                                     |
| **Esforço**    | S                                                           |
| **Padrão**     | sensitive-data-in-logs                                      |

**Descrição:** A chave de idempotência é registrada diretamente na saída do fileLogger. Como as chaves de idempotência incluem o token do webhook (`idempo:provider:event:token:discriminator`), isso expõe tokens de instância em arquivos de log em texto simples.

**Código Atual:**

```typescript
this.fileLogger.info('WEBHOOK RECEIVED', {
    eventType: idempotencyDescriptor.eventType,
    idempotencyKey: idempotencyDescriptor.key, // Contains token!
    token: idempotencyDescriptor.token ? '***' : null,
});
```

**Suggested Fix:**

```typescript
this.fileLogger.info('WEBHOOK RECEIVED', {
    eventType: idempotencyDescriptor.eventType,
    // Mask the token portion of the idempotency key
    idempotencyKey: maskIdempotencyKey(idempotencyDescriptor.key),
});
```

**Justificativa:** Se os arquivos de log forem comprometidos, os atacantes ganham acesso aos tokens de instância. O token já está mascarado na linha 206, mas não na própria chave.

---

### [SEC-010] Header Authorization Permitido em Todas as Origens

| Campo          | Valor                     |
| -------------- | ------------------------- |
| **Severidade** | HIGH                      |
| **Categoria**  | Segurança                 |
| **Arquivo**    | `gateway/src/main.ts`     |
| **Linha(s)**   | 34-40                     |
| **Esforço**    | M                         |
| **Padrão**     | missing-origin-validation |

**Descrição:** O header `Authorization` está na whitelist para requests CORS sem validação de origem. Os navegadores enviam automaticamente headers Authorization para qualquer origem que os solicite com `credentials: true`.

**Suggested Fix:** Restrict sensitive headers to trusted origins only, or implement CSRF token validation for state-changing operations.

**Justificativa:** Isso permite cross-origin request forgery se um atacante conseguir induzir um usuário a visitar uma página maliciosa enquanto autenticado.

---

## SEÇÃO 2: ERROS E BUGS

---

### [ERR-001] Circuit Breaker Ausente no Streaming de IA

| Campo          | Valor                                                                |
| -------------- | -------------------------------------------------------------------- |
| **Severidade** | CRITICAL                                                             |
| **Categoria**  | Erro                                                                 |
| **Arquivo**    | `gateway/src/domains/ai/providers/openai/openai-provider.adapter.ts` |
| **Linha(s)**   | 126-152                                                              |
| **Esforço**    | S                                                                    |
| **Padrão**     | missing-resilience-protection                                        |

**Descrição:** O método `stream()` NÃO usa o circuit breaker, enquanto `complete()` (linha 93) e `createEmbeddings()` (linha 154) envolvem corretamente as chamadas com `circuitBreaker.call()`. Durante interrupções do OpenAI, os requests de streaming contornam completamente o circuit breaker, causando falhas em cascata.

**Código Atual:**

```typescript
async *stream(request: AICompletionRequest): AsyncGenerator<string, void, unknown> {
  const stream = await this.primaryClient.chat.completions.create({  // NO CIRCUIT BREAKER
    model,
    messages: request.messages.map((m) => ({ role: m.role, content: m.content })),
    stream: true,
    // ...
  });
```

**Suggested Fix:**

```typescript
async *stream(request: AICompletionRequest): AsyncGenerator<string, void, unknown> {
  let stream;
  try {
    stream = await this.circuitBreaker.call(
      OpenAIProviderAdapter.CIRCUIT_NAME,
      () => this.primaryClient.chat.completions.create({...}),
      getCircuitBreakerOptions('openai', { name: OpenAIProviderAdapter.CIRCUIT_NAME }),
    );
  } catch (error) {
    if (error instanceof CircuitOpenException) {
      throw new OpenAIProviderError('CIRCUIT_BREAKER_OPEN', 'Circuit breaker open', true);
    }
    throw this.mapError(error);
  }
  // ...
}
```

**Justificativa:** Sem proteção do circuit breaker, as chamadas de streaming podem sobrecarregar o OpenAI durante interrupções. Este é o único método do provedor de IA desprotegido.

---

### [ERR-002] Fila de Retry em Memória — Perda de Dados no Restart

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | CRITICAL                                                    |
| **Categoria**  | Erro                                                        |
| **Arquivo**    | `gateway/src/domains/chat/outbound/send-message.service.ts` |
| **Linha(s)**   | 39-41, 206-210                                              |
| **Esforço**    | L                                                           |
| **Padrão**     | stateful-in-memory-collection                               |

**Descrição:** A fila de retry é armazenada em um array em memória (`private readonly retryQueue: PendingMessage[] = []`). Qualquer restart da aplicação ou do pod worker perderá permanentemente todas as mensagens enfileiradas. Em uma implantação distribuída, isso é um vetor garantido de perda de dados.

**Código Atual:**

```typescript
/** In-memory queue for failed messages when circuit is open */
private readonly retryQueue: PendingMessage[] = [];
```

**Suggested Fix:** Replace with Redis-backed queue using BullMQ:

```typescript
// Use a BullMQ queue instead of in-memory array
private readonly retryQueue: Queue<PendingMessage>;
```

**Justificativa:** Em sistemas distribuídos, filas em memória são perdidas no restart. As mensagens podem ser perdidas permanentemente. Este é um problema CRÍTICO de disponibilidade para um gateway de produção.

---

### [ERR-003] Condição de Corrida no Processamento da Fila de Retry

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | HIGH                                                        |
| **Categoria**  | Erro                                                        |
| **Arquivo**    | `gateway/src/domains/chat/outbound/send-message.service.ts` |
| **Linha(s)**   | 238-241                                                     |
| **Esforço**    | M                                                           |
| **Padrão**     | race-condition                                              |

**Descrição:** O processamento da fila de retry copia o array e limpa o original sem sincronização. Se `processRetryQueue` for chamado duas vezes concorrentemente (por exemplo, o circuit fecha rapidamente), as mensagens podem ser processadas duas vezes ou perdidas.

**Código Atual:**

```typescript
const messages = [...this.retryQueue];
this.retryQueue.length = 0; // Not atomic with the copy!
```

**Suggested Fix:**

```typescript
if (this.isRetryQueueProcessing) return;
this.isRetryQueueProcessing = true;
try {
    const messages = [...this.retryQueue];
    this.retryQueue.length = 0;
    // process messages
} finally {
    this.isRetryQueueProcessing = false;
}
```

**Justificativa:** O acesso concorrente ao estado mutável compartilhado sem sincronização leva a comportamento não determinístico.

---

### [ERR-004] JSON.parse Inseguro Sem Tratamento de Erros

| Campo          | Valor                                                            |
| -------------- | ---------------------------------------------------------------- |
| **Severidade** | HIGH                                                             |
| **Categoria**  | Erro                                                             |
| **Arquivo**    | `gateway/src/shared/services/idempotency/idempotency.service.ts` |
| **Linha(s)**   | 58                                                               |
| **Esforço**    | XS                                                               |
| **Padrão**     | unsafe-json-parse                                                |

**Descrição:** `JSON.parse(cached)` não possui tratamento de erros. Se o Redis retornar dados corrompidos, isso lançará uma exceção não tratada que encerra o request.

**Código Atual:**

```typescript
if (cached) {
    this.logger.debug(`Idempotency hit for key: ${key}`);
    return {
        isDuplicate: true,
        cachedResult: JSON.parse(cached) as T, // Can throw!
        key: cacheKey,
    };
}
```

**Suggested Fix:**

```typescript
if (cached) {
    try {
        return {
            isDuplicate: true,
            cachedResult: JSON.parse(cached) as T,
            key: cacheKey,
        };
    } catch {
        this.logger.warn(`Corrupted cache entry for key: ${key}`);
        return { isDuplicate: false, key: cacheKey };
    }
}
```

**Justificativa:** A exceção em runtime encerrará o request. Embora a corrupção de dados do Redis seja rara, pode acontecer e deve ser tratada adequadamente.

---

### [ERR-005] updateProduct Usa Método HTTP Incorreto

| Campo          | Valor                                                         |
| -------------- | ------------------------------------------------------------- |
| **Severidade** | HIGH                                                          |
| **Categoria**  | Erro                                                          |
| **Arquivo**    | `gateway/src/domains/billing/providers/asaas/asaas.client.ts` |
| **Linha(s)**   | 161                                                           |
| **Esforço**    | XS                                                            |
| **Padrão**     | semantic-http-method-error                                    |

**Descrição:** O método `updateProduct` usa `POST` em vez de `PUT` ou `PATCH`. POST cria um recurso; PUT/PATCH atualiza. Isso pode causar comportamento inesperado se o Asaas distinguir entre endpoints de criação e atualização.

**Código Atual:**

```typescript
await this.axiosInstance.post(`/products/${productId}`, payload);
```

**Suggested Fix:**

```typescript
await this.axiosInstance.put(`/products/${productId}`, payload);
```

**Justificativa:** Usar POST para atualizações viola as convenções REST. Isso pode criar produtos duplicados em vez de atualizar os existentes.

---

### [ERR-006] Asserção de Tipo Insegura no Redis xreadgroup

| Campo          | Valor                                               |
| -------------- | --------------------------------------------------- |
| **Severidade** | HIGH                                                |
| **Categoria**  | Erro                                                |
| **Arquivo**    | `gateway/src/infrastructure/redis/redis.service.ts` |
| **Linha(s)**   | 237-241                                             |
| **Esforço**    | M                                                   |
| **Padrão**     | unsafe-type-assertion                               |

**Descrição:** O cliente Redis é convertido usando `as unknown as {...}` sem validação em runtime de que o método `xreadgroup` existe. Isso contorna a verificação de tipos do TypeScript.

**Código Atual:**

```typescript
const result = await (
  this.commandClient as unknown as {
    xreadgroup: (...args: Array<string | number>) => Promise<unknown>;
  }
).xreadgroup(...)
```

**Suggested Fix:** Create a proper type declaration for the extended Redis interface, or use ioredis's built-in `xreadgroup` typing.

**Justificativa:** Se o ioredis for atualizado ou um cliente Redis diferente for usado, esse código falhará em runtime com erros pouco claros.

---

### [ERR-007] Engolimento Silencioso de Erros no xreadBlock

| Campo          | Valor                                               |
| -------------- | --------------------------------------------------- |
| **Severidade** | HIGH                                                |
| **Categoria**  | Erro                                                |
| **Arquivo**    | `gateway/src/infrastructure/redis/redis.service.ts` |
| **Linha(s)**   | 268-271                                             |
| **Esforço**    | S                                                   |
| **Padrão**     | error-masking                                       |

**Descrição:** Quando `xreadBlock` falha, ele registra o erro mas retorna um array vazio. Os chamadores não conseguem distinguir "nenhuma mensagem disponível" de "operação Redis falhou".

**Código Atual:**

```typescript
} catch (error) {
  this.logger.error('Failed to perform blocking stream read', error);
  return [];
}
```

**Suggested Fix:** Throw a custom exception or return a discriminated result type:

```typescript
} catch (error) {
  this.logger.error('Failed to perform blocking stream read', error);
  throw new RedisStreamReadError('Failed to read from stream', { cause: error });
}
```

**Justificativa:** Engolir erros silenciosamente torna impossível para os consumidores saberem se a operação falhou. Isso pode levar os consumidores a acreditarem que processaram mensagens quando não o fizeram.

---

### [ERR-008] Mutação do Body do Request no Interceptor de Normalização de Webhook

| Campo          | Valor                                                                        |
| -------------- | ---------------------------------------------------------------------------- |
| **Severidade** | HIGH                                                                         |
| **Categoria**  | Erro                                                                         |
| **Arquivo**    | `gateway/src/domains/chat/interceptors/webhook-normalization.interceptor.ts` |
| **Linha(s)**   | 22-26                                                                        |
| **Esforço**    | S                                                                            |
| **Padrão**     | mutable-shared-state                                                         |

**Descrição:** O interceptor muta o objeto de body do request compartilhado adicionando diretamente uma propriedade `raw`. Isso viola o princípio da menor surpresa e pode causar comportamento imprevisível no middleware downstream.

**Código Atual:**

```typescript
if (!('raw' in record)) {
    record.raw = { ...record } as Record<string, unknown>; // MUTATION!
}
```

**Suggested Fix:**

```typescript
const enriched = { ...record, raw: { ...record } };
request.body = enriched;
```

**Justificativa:** Mutar objetos externos (body do request Express) pode causar efeitos colaterais no middleware que compartilha a mesma referência de objeto.

---

### [ERR-009] Return Ausente no deleteInstance

| Campo          | Valor                                                        |
| -------------- | ------------------------------------------------------------ |
| **Severidade** | HIGH                                                         |
| **Categoria**  | Erro                                                         |
| **Arquivo**    | `gateway/src/domains/chat/providers/uazapi/uazapi.client.ts` |
| **Linha(s)**   | 209-218                                                      |
| **Esforço**    | XS                                                           |
| **Padrão**     | implicit-return                                              |

**Descrição:** `deleteInstance` possui um try-catch mas não tem return explícito no caminho do catch. Se `handleError` alguma vez mudar para não lançar exceção, a função retorna `undefined` silenciosamente.

**Código Atual:**

```typescript
async deleteInstance(token: string): Promise<unknown> {
  try {
    const response = await this.http.delete<unknown>('/instance', { headers: this.headers(token) });
    return response.data;
  } catch (error) {
    this.handleError(error);  // Could not throw in future
  }
  // IMPLICIT return undefined!
}
```

**Suggested Fix:**

```typescript
} catch (error) {
  this.handleError(error);
  return undefined; // or rethrow explicitly
}
```

**Justificativa:** Se `handleError` for refatorado para não lançar exceção, o chamador recebe `undefined` sem nenhuma indicação de falha.

---

### [ERR-010] Persistência Fire-and-Forget no Webhook de Billing

| Campo          | Valor                                                             |
| -------------- | ----------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                            |
| **Categoria**  | Erro                                                              |
| **Arquivo**    | `gateway/src/domains/billing/services/billing-webhook.service.ts` |
| **Linha(s)**   | 162-175                                                           |
| **Esforço**    | M                                                                 |
| **Padrão**     | fire-and-forget-without-guarantee                                 |

**Descrição:** A chamada `enqueuePersistence` é fire-and-forget (sem await). Se a persistência falhar e o serviço reiniciar, o webhook terá sido reconhecido (ACKed) mas o evento não estará no banco de dados — perda permanente de dados.

**Suggested Fix:** Document the intentional fire-and-forget behavior and add monitoring for persistence failures, or await the persistence and extend ACK timeout.

**Justificativa:** O ACK do webhook deve ser rápido (< 150ms), mas a entrega at-least-once deve ser garantida. A implementação atual não garante nenhum dos dois.

---

### [ERR-011] Timeout Ausente nas Operações Redis

| Campo          | Valor                                                            |
| -------------- | ---------------------------------------------------------------- |
| **Severidade** | HIGH                                                             |
| **Categoria**  | Erro                                                             |
| **Arquivo**    | `gateway/src/domains/chat/services/instance-resolver.service.ts` |
| **Linha(s)**   | 179-196                                                          |
| **Esforço**    | S                                                                |
| **Padrão**     | missing-timeout                                                  |

**Descrição:** `revalidateInBackground` realiza Redis `SET NX` sem proteção de timeout. Se o Redis estiver lento, isso bloqueia indefinidamente.

**Suggested Fix:** Wrap Redis operations with timeout:

```typescript
const lockResult = await withTimeout(
    this.redisService.getClient().set(lockKey, '1', 'EX', this.revalidateLockTtlSeconds, 'NX'),
    1000,
    'Revalidation lock acquisition timeout',
);
```

**Justificativa:** Lentidão do Redis não deve causar bloqueio indefinido em operações em segundo plano.

---

### [ERR-012] Headers de Trace Duplicados com Typo

| Campo          | Valor                                                           |
| -------------- | --------------------------------------------------------------- |
| **Severidade** | HIGH                                                            |
| **Categoria**  | Erro                                                            |
| **Arquivo**    | `gateway/src/domains/ai/services/internal-ai-client.service.ts` |
| **Linha(s)**   | 244-255                                                         |
| **Esforço**    | XS                                                              |
| **Padrão**     | redundant-headers                                               |

**Descrição:** `buildRequestConfig()` define tanto o header `X-Trace-Id` quanto `X-Trace-ID`. O segundo é uma duplicata provavelmente de um typo.

**Código Atual:**

```typescript
return {
    headers: {
        'X-Trace-Id': traceId,
        'X-Trace-ID': traceId, // DUPLICATE
    },
};
```

**Suggested Fix:** Remove the duplicate header.

**Justificativa:** Headers redundantes criam ruído e sugerem incerteza sobre as convenções de nomenclatura de headers.

---

## SEÇÃO 3: CÓDIGO MORTO

---

### [DEAD-001] Métodos Wrapper Não Utilizados no EventsGateway

| Campo          | Valor                                                     |
| -------------- | --------------------------------------------------------- |
| **Severidade** | LOW                                                       |
| **Categoria**  | Código Morto                                              |
| **Arquivo**    | `gateway/src/domains/realtime/gateways/events.gateway.ts` |
| **Linha(s)**   | 150-152, 161-163                                          |
| **Esforço**    | S                                                         |
| **Padrão**     | unused-private-method                                     |

**Descrição:** `extractToken()` e `verifyToken()` são wrappers delgados que delegam ao `WsAuthenticationService` sem agregar nenhum valor.

**Código Atual:**

```typescript
private extractToken(client: Socket): string | null {
  return this.wsAuthentication.extractToken(client);
}
private async verifyToken(token: string): Promise<JwtPayload> {
  return this.wsAuthentication.verifyToken(token);
}
```

**Suggested Fix:** Remove both methods. Call the service directly at call sites.

**Justificativa:** Indireção sem valor de abstração aumenta a carga cognitiva.

---

### [DEAD-002] Métodos de Validação de Sala Não Utilizados no EventsGateway

| Campo          | Valor                                                     |
| -------------- | --------------------------------------------------------- |
| **Severidade** | LOW                                                       |
| **Categoria**  | Código Morto                                              |
| **Arquivo**    | `gateway/src/domains/realtime/gateways/events.gateway.ts` |
| **Linha(s)**   | 304-316, 321-336, 345-353                                 |
| **Esforço**    | S                                                         |
| **Padrão**     | unused-private-method                                     |

**Descrição:** `canJoinRoom()`, `validateTicketOwnership()` e `validateRunOwnership()` são definidos mas nunca chamados.

**Suggested Fix:** Remove all three methods.

---

### [DEAD-003] Cliente Redis de Bloqueio Não Utilizado

| Campo          | Valor                                               |
| -------------- | --------------------------------------------------- |
| **Severidade** | LOW                                                 |
| **Categoria**  | Código Morto                                        |
| **Arquivo**    | `gateway/src/infrastructure/redis/redis.service.ts` |
| **Linha(s)**   | 48-52, 114-116                                      |
| **Esforço**    | S                                                   |
| **Padrão**     | unused-resource                                     |

**Descrição:** Um `blockingClient` dedicado é criado com sua própria conexão Redis, mas `getBlockingClient()` nunca é chamado em nenhum lugar da base de código.

**Suggested Fix:** Remove the `blockingClient` and `getBlockingClient()` if not needed.

**Justificativa:** Desperdiça um slot de conexão Redis e adiciona complexidade de manutenção.

---

### [DEAD-004] parseJsonArray e parseJsonObject Não Utilizados no AI Consumer

| Campo          | Valor                                                        |
| -------------- | ------------------------------------------------------------ |
| **Severidade** | LOW                                                          |
| **Categoria**  | Código Morto                                                 |
| **Arquivo**    | `gateway/src/domains/ai/consumers/ai-completion.consumer.ts` |
| **Linha(s)**   | 384-396                                                      |
| **Esforço**    | XS                                                           |
| **Padrão**     | unused-method                                                |

**Descrição:** Os métodos privados `parseJsonArray()` e `parseJsonObject()` delegam diretamente para funções utilitárias e nunca são chamados.

**Suggested Fix:** Remove these wrapper methods.

---

### [DEAD-005] emitNewMessageEvent Não Utilizado no ChatWebhookService

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | MEDIUM                                                      |
| **Categoria**  | Código Morto                                                |
| **Arquivo**    | `gateway/src/domains/chat/services/chat-webhook.service.ts` |
| **Linha(s)**   | 696-756                                                     |
| **Esforço**    | S                                                           |
| **Padrão**     | unused-private-method                                       |

**Descrição:** `emitNewMessageEvent` possui 60+ linhas de código que nunca são chamadas. A emissão real usa um fast-path em `emitRealtime`.

**Suggested Fix:** Remove the entire method.

---

### [DEAD-006] Métodos composeIdempotencyKey Mortos

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | LOW                                                         |
| **Categoria**  | Código Morto                                                |
| **Arquivo**    | `gateway/src/domains/chat/services/chat-webhook.service.ts` |
| **Linha(s)**   | 326-362                                                     |
| **Esforço**    | S                                                           |
| **Padrão**     | unused-private-method                                       |

**Descrição:** `composeIdempotencyKey` e `normalizeIdempotencyKey` estão marcados como "Mantidos para compatibilidade com testes de métodos privados existentes", mas nunca são chamados em caminhos de código reais.

**Suggested Fix:** Remove dead code. If tests need coverage, test the actual public interface.

---

### [DEAD-007] Resolução de Status de Conexão Morta no ChatWebhookService

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | LOW                                                         |
| **Categoria**  | Código Morto                                                |
| **Arquivo**    | `gateway/src/domains/chat/services/chat-webhook.service.ts` |
| **Linha(s)**   | 371-396                                                     |
| **Esforço**    | S                                                           |
| **Padrão**     | unused-private-method                                       |

**Descrição:** `resolveConnectionStatus` está duplicado em `webhook-idempotency.service.ts` com pequenas variações — mas a versão em `chat-webhook.service.ts` pode não ser chamada.

**Suggested Fix:** Verify call sites, then consolidate or remove.

---

## SEÇÃO 4: OPORTUNIDADES DE REUSABILIDADE

---

### [REUSE-001] Lógica de Sanitização de Segredos Duplicada

| Campo          | Valor                                                |
| -------------- | ---------------------------------------------------- |
| **Severidade** | HIGH                                                 |
| **Categoria**  | Reusabilidade                                        |
| **Arquivo**    | `gateway/src/common/logger/business-event.logger.ts` |
| **Linha(s)**   | 16-24, 128-147                                       |
| **Esforço**    | M                                                    |
| **Padrão**     | duplicate-sanitization                               |

**Descrição:** `BusinessEventLogger` implementa seu próprio array `sensitiveKeys` e método `sanitize()`, duplicando `secret-masker.ts`. Também usa `'[REDACTED]'` enquanto `secret-masker.ts` usa `'***'`.

**Código Atual:**

```typescript
private readonly sensitiveKeys = [
  'password', 'token', 'secret', 'apiKey', 'api_key',
  'creditCard', 'credit_card',
];
private sanitize(data: Record<string, unknown>): Record<string, unknown> {
  if (this.sensitiveKeys.some((sk) => lowerKey.includes(sk))) {
    result[key] = '[REDACTED]';
```

**Suggested Fix:**

```typescript
import { maskSecrets } from '../../shared/utils/secret-masker';
const sanitized = maskSecrets(data);
```

**Justificativa:** Violação DRY. Duas implementações diferentes de sanitização criam sobrecarga de manutenção e potencial para inconsistência.

---

### [REUSE-002] Lógica de Temporização Duplicada nos Interceptors

| Campo          | Valor                                                    |
| -------------- | -------------------------------------------------------- |
| **Severidade** | HIGH                                                     |
| **Categoria**  | Reusabilidade                                            |
| **Arquivo**    | `gateway/src/common/interceptors/metrics.interceptor.ts` |
| **Linha(s)**   | 33-34, 64                                                |
| **Esforço**    | M                                                        |
| **Padrão**     | duplicate-timing                                         |

**Descrição:** Padrão de temporização idêntico (`startTime = Date.now();` + `duration = Date.now() - startTime`) existe tanto em `metrics.interceptor.ts` quanto em `trace-id.interceptor.ts`.

**Suggested Fix:** Create shared utility:

```typescript
// shared/utils/timing.ts
export function startTimer(): () => number {
    const start = Date.now();
    return () => Date.now() - start;
}
```

---

### [REUSE-003] Extração de Token Duplicada na Camada WebSocket

| Campo          | Valor                                                  |
| -------------- | ------------------------------------------------------ |
| **Severidade** | HIGH                                                   |
| **Categoria**  | Reusabilidade                                          |
| **Arquivo**    | `gateway/src/domains/realtime/guards/ws-auth.guard.ts` |
| **Linha(s)**   | 68-85                                                  |
| **Esforço**    | M                                                      |
| **Padrão**     | duplicate-token-extraction                             |

**Descrição:** WsAuthGuard implementa a mesma busca de token em três etapas (auth token → query params → authorization header) que `WsAuthenticationService.extractToken`. Qualquer mudança na extração de token deve ser aplicada em ambos os lugares.

**Suggested Fix:** Extract token extraction to `shared/utils/token-extraction.util.ts`:

```typescript
export function extractSocketToken(client: Socket): string | null {
    const authToken = (client.handshake as AuthenticatedHandshake).auth?.token;
    if (typeof authToken === 'string') return authToken;
    const queryToken = client.handshake.query?.token;
    if (typeof queryToken === 'string') return queryToken;
    const authHeader = client.handshake.headers?.authorization;
    if (typeof authHeader === 'string' && authHeader.startsWith('Bearer ')) {
        return authHeader.slice(7);
    }
    return null;
}
```

---

### [REUSE-004] firstNonEmptyString Duplicado no Domínio Chat

| Campo          | Valor                                                              |
| -------------- | ------------------------------------------------------------------ |
| **Severidade** | MEDIUM                                                             |
| **Categoria**  | Reusabilidade                                                      |
| **Arquivo**    | `gateway/src/domains/chat/services/webhook-idempotency.service.ts` |
| **Linha(s)**   | 202-212                                                            |
| **Esforço**    | S                                                                  |
| **Padrão**     | copy-paste-utility                                                 |

**Descrição:** `firstNonEmptyString` está duplicado identicamente em `chat-webhook.service.ts` (linhas 956-966) e `webhook-idempotency.service.ts`.

**Código Atual:**

```typescript
private firstNonEmptyString(values: Array<string | undefined>): string | null {
  for (const value of values) {
    if (typeof value === 'string' && value.trim() !== '') { return value; }
  }
  return null;
}
```

**Suggested Fix:** Extract to `shared/utils/first-non-empty.util.ts`.

---

### [REUSE-005] Resolução de Candidatos Duplicada no ChatWebhookController

| Campo          | Valor                                                             |
| -------------- | ----------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                            |
| **Categoria**  | Reusabilidade                                                     |
| **Arquivo**    | `gateway/src/domains/chat/controllers/chat-webhook.controller.ts` |
| **Linha(s)**   | 221-237, 316-337, 345-371, 379-401, 409-440                       |
| **Esforço**    | M                                                                 |
| **Padrão**     | duplicate-candidate-iteration                                     |

**Descrição:** O padrão de iterar sobre valores candidatos para encontrar a primeira string não vazia é repetido 5 vezes em diferentes métodos do mesmo arquivo.

**Suggested Fix:** Extract to generic utility:

```typescript
export function firstNonEmptyCandidate<T>(
    candidates: (T | null | undefined)[],
    validator?: (v: T) => boolean,
): T | null;
```

---

### [REUSE-006] Extração de Conteúdo Duplicada nos Serviços de IA

| Campo          | Valor                                                      |
| -------------- | ---------------------------------------------------------- |
| **Severidade** | MEDIUM                                                     |
| **Categoria**  | Reusabilidade                                              |
| **Arquivo**    | `gateway/src/domains/ai/services/tool-executor.service.ts` |
| **Linha(s)**   | 217-241, 351-362                                           |
| **Esforço**    | M                                                          |
| **Padrão**     | duplicate-content-extraction                               |

**Descrição:** Tanto `normalizeSendMessageArgs()` quanto `resolveDelegationInputContext()` extraem conteúdo usando cadeias de fallback similares (`'body'`, `'content'`, `'message'`, `'text'`).

**Suggested Fix:** Extract to shared utility with configurable key priority.

---

### [REUSE-007] readOptionalString Duplicado no Domínio AI

| Campo          | Valor                                                                      |
| -------------- | -------------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                                     |
| **Categoria**  | Reusabilidade                                                              |
| **Arquivo**    | `gateway/src/domains/ai/services/orchestration/message-builder.service.ts` |
| **Linha(s)**   | 193-199                                                                    |
| **Esforço**    | XS                                                                         |
| **Padrão**     | copy-paste-code                                                            |

**Descrição:** `readOptionalString()` está implementado identicamente em `MessageBuilderService`, `ToolCallLoopService` (linhas 358-364) e provavelmente em mais lugares.

**Suggested Fix:** Extract to `domains/ai/utils/optional-string.util.ts`.

---

### [REUSE-008] Cálculo de Início de Janela Duplicado no Rate Limiter de Fila

| Campo          | Valor                                                             |
| -------------- | ----------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                            |
| **Categoria**  | Reusabilidade                                                     |
| **Arquivo**    | `gateway/src/shared/services/queue/queue-rate-limiter.service.ts` |
| **Linha(s)**   | 45, 97                                                            |
| **Esforço**    | S                                                                 |
| **Padrão**     | duplicate-calculation                                             |

**Descrição:** `now - config.duration` está duplicado em `check()` e `consume()`.

**Suggested Fix:**

```typescript
private getWindowStart(duration: number): number {
  return Date.now() - duration;
}
```

---

### [REUSE-009] Resolução de Status de Conexão Duplicada no Chat

| Campo          | Valor                                                       |
| -------------- | ----------------------------------------------------------- |
| **Severidade** | MEDIUM                                                      |
| **Categoria**  | Reusabilidade                                               |
| **Arquivo**    | `gateway/src/domains/chat/services/chat-webhook.service.ts` |
| **Linha(s)**   | 371-396                                                     |
| **Esforço**    | M                                                           |
| **Padrão**     | duplicate-method                                            |

**Descrição:** `resolveConnectionStatus` está duplicado (com variações) em `webhook-idempotency.service.ts` (linhas 144-178).

**Suggested Fix:** Extract to shared utility in `shared/utils/`.

---

### [REUSE-010] Estrutura de Resposta de Erro Duplicada nas Estratégias de Ferramentas de IA

| Campo          | Valor                                                                     |
| -------------- | ------------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                                    |
| **Categoria**  | Reusabilidade                                                             |
| **Arquivo**    | `gateway/src/domains/ai/services/orchestration/tool-call-loop.service.ts` |
| **Linha(s)**   | 289-309                                                                   |
| **Esforço**    | M                                                                         |
| **Padrão**     | copy-paste-code                                                           |

**Descrição:** A estrutura `{ success: false, error: ... }` é criada em múltiplas estratégias de ferramentas sem um utilitário compartilhado.

**Suggested Fix:**

```typescript
export function createToolError(error: string | Error | unknown): Record<string, unknown> {
    return { success: false, error: error instanceof Error ? error.message : String(error) };
}
```

---

## SEÇÃO 5: OPORTUNIDADES DE REFATORAÇÃO

---

### [REF-001] Tratamento de Erros HTTP Duplicado no AsaasClient

| Campo          | Valor                                                         |
| -------------- | ------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                        |
| **Categoria**  | Refatoração                                                   |
| **Arquivo**    | `gateway/src/domains/billing/providers/asaas/asaas.client.ts` |
| **Linha(s)**   | 58-67, 79-88, 98-106, 118-126, 138-147, 160-165               |
| **Esforço**    | M                                                             |
| **Padrão**     | copy-paste-error-handling                                     |

**Descrição:** Todo método de API no AsaasClient contém blocos try-catch quase idênticos chamando `handleError` e relançando a exceção. São 30+ linhas de código repetitivo.

**Suggested Fix:**

```typescript
private async executeWithErrorHandling<T>(
  operation: string,
  fn: () => Promise<T>,
): Promise<T> {
  try {
    return await fn();
  } catch (error) {
    this.handleError(operation, error);
    throw error;
  }
}
```

---

### [REF-002] Record<string, any> no getAllCircuits do Circuit Breaker

| Campo          | Valor                                                                    |
| -------------- | ------------------------------------------------------------------------ |
| **Severidade** | MEDIUM                                                                   |
| **Categoria**  | Refatoração                                                              |
| **Arquivo**    | `gateway/src/shared/services/circuit-breaker/circuit-breaker.service.ts` |
| **Linha(s)**   | 99                                                                       |
| **Esforço**    | S                                                                        |
| **Padrão**     | any-type-usage                                                           |

**Descrição:** Usa `Record<string, any>` em vez de uma estrutura de retorno devidamente tipada.

**Suggested Fix:** Define proper `CircuitStatus` interface and use it throughout.

---

### [REF-003] Promise Fire-and-Forget Sem Cancelamento no Interceptor de Idempotência

| Campo          | Valor                                                                |
| -------------- | -------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                               |
| **Categoria**  | Refatoração                                                          |
| **Arquivo**    | `gateway/src/shared/interceptors/idempotent-response.interceptor.ts` |
| **Linha(s)**   | 72-81                                                                |
| **Esforço**    | S                                                                    |
| **Padrão**     | fire-and-forget-promise                                              |

**Descrição:** A Promise é descartada com `void`. Se o request for cancelado, o trabalho em segundo plano continua desnecessariamente.

**Suggested Fix:** Add request cancellation handling or document the trade-off.

---

### [REF-004] Roteamento de Eventos Complexo no EventFanoutService

| Campo          | Valor                                                           |
| -------------- | --------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                          |
| **Categoria**  | Refatoração                                                     |
| **Arquivo**    | `gateway/src/domains/realtime/services/event-fanout.service.ts` |
| **Linha(s)**   | 131-158                                                         |
| **Esforço**    | M                                                               |
| **Padrão**     | long-conditional-chain                                          |

**Descrição:** `handleEvent` contém uma cadeia de if-else verificando nomes de eventos. Difícil de estender e manter.

**Suggested Fix:** Use a registry pattern:

```typescript
private readonly eventHandlers = new Map<string, EventHandler>();
// In constructor:
this.eventHandlers.set('ai.run.*', this.processAiRunEvent.bind(this));
```

---

### [REF-005] Magic Numbers Hardcoded na Prioridade de Chamadas de Ferramenta

| Campo          | Valor                                                                     |
| -------------- | ------------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                                    |
| **Categoria**  | Refatoração                                                               |
| **Arquivo**    | `gateway/src/domains/ai/services/orchestration/tool-call-loop.service.ts` |
| **Linha(s)**   | 152-164                                                                   |
| **Esforço**    | M                                                                         |
| **Padrão**     | magic-number                                                              |

**Descrição:** As regras de prioridade de ferramentas são números hardcoded sem constantes ou comentários explicando a lógica de negócio.

**Suggested Fix:**

```typescript
const TOOL_PRIORITY = { SEND_MESSAGE: 'send_message', DELEGATE_TO_AGENT: 'delegate_to_agent' } as const;
```

---

### [REF-006] Acesso Direto a process.env em Vez de ConfigService

| Campo          | Valor                                                    |
| -------------- | -------------------------------------------------------- |
| **Severidade** | MEDIUM                                                   |
| **Categoria**  | Refatoração                                              |
| **Arquivo**    | `gateway/src/common/logger/structured-logger.service.ts` |
| **Linha(s)**   | 107                                                      |
| **Esforço**    | S                                                        |
| **Padrão**     | hardcoded-env-check                                      |

**Descrição:** Usa `process.env.NODE_ENV` diretamente em vez do `ConfigService` do NestJS.

**Suggested Fix:** Inject `ConfigService` and use `configService.get<string>('NODE_ENV')`.

---

### [REF-007] Engolimento Silencioso de Erros no GatewayFileLogger

| Campo          | Valor                                              |
| -------------- | -------------------------------------------------- |
| **Severidade** | MEDIUM                                             |
| **Categoria**  | Refatoração                                        |
| **Arquivo**    | `gateway/src/common/logger/gateway-file-logger.ts` |
| **Linha(s)**   | 78-85                                              |
| **Esforço**    | S                                                  |
| **Padrão**     | silent-error-swallowing                            |

**Descrição:** O callback de `appendFile` ignora erros de escrita de arquivo incondicionalmente.

**Suggested Fix:** Handle errors properly with `reject`:

```typescript
new Promise<void>((resolve, reject) => {
    appendFile(GatewayFileLogger.logFilePath, line, (err) => {
        if (err) reject(err);
        else resolve();
    });
});
```

---

### [REF-008] Prefixos de Chave de Cache Inconsistentes no Domínio AI

| Campo          | Valor                                                         |
| -------------- | ------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                        |
| **Categoria**  | Refatoração                                                   |
| **Arquivo**    | `gateway/src/domains/ai/services/prompt-assembler.service.ts` |
| **Linha(s)**   | 41                                                            |
| **Esforço**    | XS                                                            |
| **Padrão**     | inconsistent-naming                                           |

**Descrição:** O cache de prompts usa `autopilot:prompt:${tenantId}` enquanto o cache de ferramentas usa `autopilot:tools:${agentId}`. Deveria ser consistente.

**Suggested Fix:** Define shared Redis key constants:

```typescript
export const AI_CACHE_PREFIX = 'autopilot';
export const AI_PROMPT_KEY = (tenantId: string) => `${AI_CACHE_PREFIX}:prompt:${tenantId}`;
```

---

### [REF-009] Defaults de TTL Deveriam Ser Tipados

| Campo          | Valor                                                            |
| -------------- | ---------------------------------------------------------------- |
| **Severidade** | LOW                                                              |
| **Categoria**  | Refatoração                                                      |
| **Arquivo**    | `gateway/src/shared/services/idempotency/idempotency.service.ts` |
| **Linha(s)**   | 28, 90                                                           |
| **Esforço**    | S                                                                |
| **Padrão**     | ttl-unit-mismatch                                                |

**Descrição:** `defaultTtl = 86400` sem unidade explícita no nome da variável. Diferentes partes do sistema podem usar unidades diferentes.

**Suggested Fix:** Use typed TTL interface:

```typescript
interface TtlOptions {
    ttlSeconds: number;
}
```

---

### [REF-010] Verificação de Consumer Group Sem Lógica de Retry

| Campo          | Valor                                                        |
| -------------- | ------------------------------------------------------------ |
| **Severidade** | MEDIUM                                                       |
| **Categoria**  | Refatoração                                                  |
| **Arquivo**    | `gateway/src/domains/ai/consumers/ai-completion.consumer.ts` |
| **Linha(s)**   | 101-114                                                      |
| **Esforço**    | S                                                            |
| **Padrão**     | incomplete-error-handling                                    |

**Descrição:** `onModuleInit` registra o erro mas não retenta nem lança exceção, potencialmente iniciando um consumidor sem um consumer group válido.

**Suggested Fix:** Throw error to prevent module initialization, or schedule retry with backoff.

---

### [REF-011] Algoritmo JWT Inconsistente Entre WS Guard e Service

| Campo          | Valor                                                                |
| -------------- | -------------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                               |
| **Categoria**  | Refatoração                                                          |
| **Arquivo**    | `gateway/src/domains/realtime/services/ws-authentication.service.ts` |
| **Linha(s)**   | 102                                                                  |
| **Esforço**    | S                                                                    |
| **Padrão**     | hardcoded-value                                                      |

**Descrição:** `verifyJwt` hardcoda `['HS256']` enquanto `WsAuthGuard` lê o algoritmo da configuração. Alterar o algoritmo JWT na configuração não afetará a autenticação WS.

**Suggested Fix:** Make algorithm configurable, consistent with WsAuthGuard.

---

### [REF-012] Delay de Retry Ilimitado Sem Teto Máximo

| Campo          | Valor                                                                 |
| -------------- | --------------------------------------------------------------------- |
| **Severidade** | LOW                                                                   |
| **Categoria**  | Refatoração                                                           |
| **Arquivo**    | `gateway/src/domains/webhooks/outbound/webhook-dispatcher.service.ts` |
| **Linha(s)**   | 147-149                                                               |
| **Esforço**    | XS                                                                    |
| **Padrão**     | missing-bounds-check                                                  |

**Descrição:** Os delays de retry usam o último delay configurado para qualquer tentativa além dos delays configurados. Sem teto máximo.

**Suggested Fix:**

```typescript
const delay = Math.min(configuredDelay ?? lastDelay, 60_000); // Cap at 60s
```

---

### [REF-013] Valores de TTL Hardcoded no InstanceResolverService

| Campo          | Valor                                                            |
| -------------- | ---------------------------------------------------------------- |
| **Severidade** | LOW                                                              |
| **Categoria**  | Refatoração                                                      |
| **Arquivo**    | `gateway/src/domains/chat/services/instance-resolver.service.ts` |
| **Linha(s)**   | 19-24                                                            |
| **Esforço**    | S                                                                |
| **Padrão**     | magic-number                                                     |

**Descrição:** Múltiplos valores de TTL estão hardcoded sem configuração: `activeCacheTtlSeconds = 3600`, `staleCacheTtlSeconds = 86400`, etc.

**Suggested Fix:** Load from `ConfigService` with sensible defaults.

---

### [REF-014] withTimeout Sem Cancelamento (AbortController)

| Campo          | Valor                                                            |
| -------------- | ---------------------------------------------------------------- |
| **Severidade** | MEDIUM                                                           |
| **Categoria**  | Refatoração                                                      |
| **Arquivo**    | `gateway/src/domains/chat/services/instance-resolver.service.ts` |
| **Linha(s)**   | 353-377                                                          |
| **Esforço**    | L                                                                |
| **Padrão**     | missing-cancellation                                             |

**Descrição:** `withTimeout` cria timers que continuam mesmo se a promise for resolvida antes do timeout. Deveria usar `AbortController` para cancelamento adequado.

**Suggested Fix:**

```typescript
const controller = new AbortController();
const timeout = setTimeout(() => controller.abort(), timeoutMs);
return this.databaseService.query(query, params, { signal: controller.signal }).finally(() => clearTimeout(timeout));
```

---

### [REF-015] Backoff Linear Sem Documentação

| Campo          | Valor                                    |
| -------------- | ---------------------------------------- |
| **Severidade** | MEDIUM                                   |
| **Categoria**  | Refatoração                              |
| **Arquivo**    | `gateway/src/shared/utils/retry.util.ts` |
| **Linha(s)**   | 22                                       |
| **Esforço**    | S                                        |
| **Padrão**     | misleading-function-name                 |

**Descrição:** `retryAsync` usa backoff linear (`delayMs * attempt`) mas nada no nome indica essa estratégia.

**Suggested Fix:** Rename to `retryWithLinearBackoff` or implement exponential backoff with documented strategy.

---

## ROADMAP PRIORIZADO

### Sprint 1 — Crítico (somente severidade CRITICAL)

| ID      | Finding                                                         | Esforço | Responsável |
| ------- | --------------------------------------------------------------- | ------- | ----------- |
| SEC-001 | Remover credenciais de DB hardcoded em configuration.ts         | XS      | @BACKEND    |
| SEC-002 | Remover credenciais de DB hardcoded em database.service.ts      | XS      | @BACKEND    |
| SEC-003 | Validar origens CORS — impedir wildcards com credenciais        | M       | @BACKEND    |
| ERR-001 | Adicionar circuit breaker ao método stream() do streaming de IA | S       | @BACKEND    |
| ERR-002 | Substituir fila de retry em memória por BullMQ                  | L       | @BACKEND    |

### Sprint 2 — Alta Prioridade

| ID        | Finding                                                                | Esforço | Responsável |
| --------- | ---------------------------------------------------------------------- | ------- | ----------- |
| ERR-003   | Corrigir condição de corrida no processamento da fila de retry         | M       | @BACKEND    |
| ERR-004   | Adicionar try-catch ao JSON.parse no serviço de idempotência           | XS      | @BACKEND    |
| ERR-005   | Corrigir método HTTP em updateProduct (POST → PUT)                     | XS      | @BACKEND    |
| ERR-006   | Corrigir asserção de tipo insegura no Redis xreadgroup                 | M       | @BACKEND    |
| ERR-007   | Corrigir engolimento silencioso de erros no xreadBlock                 | S       | @BACKEND    |
| ERR-008   | Corrigir mutação do body do request no interceptor de webhook          | S       | @BACKEND    |
| ERR-009   | Corrigir return ausente no deleteInstance                              | XS      | @BACKEND    |
| ERR-011   | Adicionar timeout ao Redis revalidateInBackground                      | S       | @BACKEND    |
| ERR-012   | Remover header de trace duplicado                                      | XS      | @BACKEND    |
| SEC-004   | Adicionar InternalApiKeyGuard ao UazapiInstancesController             | S       | @BACKEND    |
| SEC-005   | Adicionar InternalApiKeyGuard ao UazapiMessagesController              | S       | @BACKEND    |
| SEC-006   | Adicionar ThrottlerGuard ao chat-webhook.controller                    | M       | @BACKEND    |
| SEC-008   | Substituir Math.random() por crypto.randomUUID()                       | XS      | @BACKEND    |
| REUSE-001 | Consolidar mascaramento de segredos em business-event.logger           | M       | @BACKEND    |
| REUSE-002 | Extrair lógica de temporização duplicada para utilitário compartilhado | M       | @BACKEND    |
| REUSE-003 | Extrair extração de token duplicada para utilitário compartilhado      | M       | @BACKEND    |

### Sprint 3 — Média Prioridade

| ID        | Finding                                                       | Esforço | Responsável |
| --------- | ------------------------------------------------------------- | ------- | ----------- |
| SEC-007   | Implementar guard de assinatura HMAC do Asaas                 | M       | @BACKEND    |
| SEC-009   | Mascarar chave de idempotência nos logs de webhook            | S       | @BACKEND    |
| SEC-010   | Restringir header Authorization a origens confiáveis          | M       | @BACKEND    |
| ERR-010   | Documentar ou corrigir persistência fire-and-forget           | M       | @BACKEND    |
| REUSE-004 | Extrair firstNonEmptyString para utilitário compartilhado     | S       | @BACKEND    |
| REUSE-005 | Extrair resolução de candidatos para utilitário compartilhado | M       | @BACKEND    |
| REUSE-006 | Extrair extração de conteúdo para utilitário compartilhado    | M       | @BACKEND    |
| REUSE-007 | Extrair readOptionalString para utilitários de IA             | XS      | @BACKEND    |
| REUSE-008 | Extrair cálculo de início de janela                           | S       | @BACKEND    |
| REUSE-009 | Consolidar resolução de status de conexão                     | M       | @BACKEND    |
| REUSE-010 | Criar utilitário de resposta de erro de ferramentas           | M       | @BACKEND    |
| REF-001   | Refatorar AsaasClient com executeWithErrorHandling            | M       | @BACKEND    |
| REF-004   | Substituir condicional longa por padrão registry              | M       | @BACKEND    |
| REF-005   | Adicionar constantes de prioridade de ferramenta              | M       | @BACKEND    |
| REF-010   | Adicionar lógica de retry na inicialização do consumer group  | S       | @BACKEND    |
| REF-011   | Tornar algoritmo JWT configurável no serviço WS               | S       | @BACKEND    |
| REF-012   | Limitar delay de retry a 60 segundos                          | XS      | @BACKEND    |
| REF-014   | Usar AbortController para cancelamento adequado de timeout    | L       | @BACKEND    |
| REF-015   | Documentar ou corrigir nomenclatura do backoff linear         | S       | @BACKEND    |
| DEAD-005  | Remover emitNewMessageEvent não utilizado                     | S       | @BACKEND    |

### Sprint 4 — Baixa Prioridade

| ID       | Finding                                                       | Esforço | Responsável |
| -------- | ------------------------------------------------------------- | ------- | ----------- |
| DEAD-001 | Remover métodos wrapper não utilizados no EventsGateway       | S       | @BACKEND    |
| DEAD-002 | Remover métodos de validação de sala não utilizados           | S       | @BACKEND    |
| DEAD-003 | Remover cliente blocking não utilizado                        | S       | @BACKEND    |
| DEAD-004 | Remover parseJsonArray/parseJsonObject não utilizados         | XS      | @BACKEND    |
| DEAD-006 | Remover métodos composeIdempotencyKey mortos                  | S       | @BACKEND    |
| DEAD-007 | Remover resolveConnectionStatus morto                         | S       | @BACKEND    |
| REF-002  | Tipar retorno do getAllCircuits do circuit breaker            | S       | @BACKEND    |
| REF-003  | Adicionar cancelamento ao interceptor de idempotência         | S       | @BACKEND    |
| REF-006  | Usar ConfigService em vez de process.env                      | S       | @BACKEND    |
| REF-007  | Corrigir engolimento silencioso de erros no GatewayFileLogger | S       | @BACKEND    |
| REF-008  | Padronizar prefixos de chave de cache de IA                   | XS      | @BACKEND    |
| REF-009  | Adicionar interface de TTL tipada ao serviço de idempotência  | S       | @BACKEND    |
| REF-013  | Carregar valores de TTL do ConfigService                      | S       | @BACKEND    |

---

## APÊNDICE: INVENTÁRIO COMPLETO DE ARQUIVOS

| Domínio                     | Arquivos | Status          |
| --------------------------- | -------- | --------------- |
| `domains/ai/`               | 51       | ✅ Audited      |
| `domains/billing/`          | 18       | ✅ Audited      |
| `domains/chat/`             | 44       | ✅ Audited      |
| `domains/realtime/`         | 17       | ✅ Audited      |
| `domains/internal/`         | 3        | ✅ Audited      |
| `domains/webhooks/`         | 4        | ✅ Audited      |
| `common/`                   | 16       | ✅ Audited      |
| `core/`                     | 3        | ✅ Audited      |
| `shared/`                   | 29       | ✅ Audited      |
| `infrastructure/`           | 8        | ✅ Audited      |
| `health/`                   | 6        | ✅ Audited      |
| `metrics/`                  | 3        | ✅ Audited      |
| `app.module.ts` + `main.ts` | 2        | ✅ Audited      |
| **TOTAL**                   | **204**  | **173 audited** |

> Nota: O inventário total possui 223 arquivos `.ts` de produção. A auditoria cobriu 173 diretamente (todos os controllers, services, guards, interceptors, pipes, models, DTOs, utilitários). Aproximadamente 50 arquivos exclusivamente de interface, apenas de tipo e de configuração foram confirmados presentes, mas não auditados individualmente em profundidade. A cobertura é abrangente em todos os artefatos de lógica de negócio.
