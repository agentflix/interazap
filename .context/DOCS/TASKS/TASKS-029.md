# TASKS-029 — Suporte Gemini 2.5 e 3.1

**Entregas:** 6 | **Tasks:** 14

| Entrega | Descrição                                     | Tasks                       | Status |
| ------- | --------------------------------------------- | --------------------------- | ------ |
| 1       | Backend: catálogo Google em pricing           | TASK-029.1.1 - TASK-029.1.2 | todo   |
| 2       | Gateway: configuração base do provider Google | TASK-029.2.1 - TASK-029.2.3 | todo   |
| 3       | Gateway: adapter Gemini e wiring DI           | TASK-029.3.1 - TASK-029.3.4 | todo   |
| 4       | Surface de configuração e dependências        | TASK-029.4.1 - TASK-029.4.2 | todo   |
| 5       | Frontend: opções de modelo no form            | TASK-029.5.1                | todo   |
| 6       | Validation e Confirm                          | TASK-029.6.1 - TASK-029.6.2 | todo   |

---

## Sequência PREVC obrigatória

- Planning: concluído em `PLAN-029-adicionar-modelos-gemini-2-5`
- Review: antes de iniciar `TASK-029.1.1`, validar na documentação oficial/SDK da Google os IDs canônicos e pricing dos modelos solicitados, principalmente `Gemini 3.1` e `Gemini 3.1 Flash`
- Execution: executar entregas 1 a 5 na ordem, respeitando dependências declaradas
- Validation: executar `TASK-029.6.1` com todos os gates das camadas afetadas
- Confirm: executar `TASK-029.6.2`, registrar evidências, acionar `@QA` e `@REVIEWER`, e só então seguir para commit semântico com `@GIT_COMMIT`

> Nenhuma entrega pode ser marcada como `done` sem gate local verde e sem evidência preenchida.

---

## Entrega 1 — Backend: catálogo Google em pricing ✅ testável

**Entrega:** Catálogo persistido para Gemini 2.5 e 3.1 no backend, sem alterar schema e sem quebrar a tabela existente | **Agente:** @BACKEND

**Gate:** `cd /Users/rafael.silva/Documents/agentflix/api && composer gate:all`

### TASK-029.1.1 — Criar migration aditiva para o catálogo Gemini

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Criar uma migration aditiva e idempotente para inserir os 4 modelos Google Gemini solicitados na tabela `ai_model_pricings`, sem depender da execução do seeder em produção.

**Constraints**

- Não alterar schema de `ai_model_pricings`; somente inserir/remover dados
- Validar antes da implementação os IDs oficiais aceitos pelo SDK Google; se o nome comercial divergir do ID canônico, usar `display_name` comercial e `model_name` oficial
- `down()` deve remover apenas os 4 registros novos do provider `google`
- Não duplicar registros já existentes (`provider + model_name` é unique)

**Context**

- Módulos afetados: Ai (Backend)
- Dependências: Review técnico da Google concluído
- Tabela já suporta múltiplos modelos por provider

**Context References**

- `api/database/migrations/2026_01_01_000050_create_ai_core_tables.php` _(required in context)_
- `api/database/seeders/AiModelPricingSeeder.php` _(required in context)_

**Code Context**

<details>
<summary>Expected contract</summary>

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        DB::table('ai_model_pricings')->upsert([
            // 4 registros Google Gemini novos
        ], ['provider', 'model_name'], [
            'display_name',
            'input_cost_per_1m',
            'output_cost_per_1m',
            'max_context_tokens',
            'max_output_tokens',
            'is_active',
            'pricing_effective_date',
            'notes',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        DB::table('ai_model_pricings')
            ->where('provider', 'google')
            ->whereIn('model_name', [
                'gemini-2.5-pro',
                'gemini-2.5-flash',
                'gemini-3.1',
                'gemini-3.1-flash',
            ])
            ->delete();
    }
};
```

</details>

**Etapas**

- [ ]   1. Confirmar IDs oficiais, limites e pricing do catálogo Google antes de fixar `model_name`
- [ ]   2. Criar `api/database/migrations/2026_03_31_000001_add_gemini_models_to_ai_model_pricings.php`
- [ ]   3. Implementar `up()` com `upsert` idempotente para os 4 modelos
- [ ]   4. Implementar `down()` removendo apenas os 4 registros novos
- [ ]   5. Verificar `cd /Users/rafael.silva/Documents/agentflix/api && php artisan migrate --pretend`

**Critérios de conclusão**

- [ ] A migration insere ou atualiza exatamente os 4 modelos Google previstos sem duplicidade
      -> `test_ai_model_pricing_migration_upserts_google_gemini_catalog`
- [ ] O rollback remove apenas os 4 modelos novos e preserva `gemini-1.5-pro`
      -> `test_ai_model_pricing_migration_down_removes_only_new_google_models`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.1.2 — Atualizar o seeder `AiModelPricingSeeder.php` com o catálogo Gemini

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Atualizar o seeder `AiModelPricingSeeder.php` para incluir os modelos Gemini 2.5 e 3.1, mantendo o comportamento atual de update-or-create e sem sobrescrever indevidamente outros providers.

**Constraints**

- Preservar o loop atual com update quando o registro já existir
- Preencher `max_output_tokens` quando houver valor confirmado; se ainda não houver validação oficial, manter a decisão documentada no `notes`
- Não remover `gemini-1.5-pro`
- Manter `declare(strict_types=1)` e estilo atual do arquivo

**Context**

- Módulos afetados: Ai (Backend)
- Dependências: `TASK-029.1.1`

**Context References**

- `api/database/seeders/AiModelPricingSeeder.php` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```php
// Current code
// Gemini 1.5 Pro
[
    'model_name' => 'gemini-1.5-pro',
    'provider' => 'google',
    'display_name' => 'Gemini 1.5 Pro',
    'input_cost_per_1m' => 1.25,
    'output_cost_per_1m' => 5.0,
    'max_context_tokens' => 1000000,
    'is_active' => true,
    'notes' => 'Large-context model suitable for long-context tasks.',
],
```

```php
// Expected code
// Gemini family
[
    'model_name' => 'gemini-1.5-pro',
    'provider' => 'google',
    'display_name' => 'Gemini 1.5 Pro',
    // ...
],
[
    'model_name' => 'gemini-2.5-pro',
    'provider' => 'google',
    'display_name' => 'Gemini 2.5 Pro',
    // pricing + limits validados
],
[
    'model_name' => 'gemini-2.5-flash',
    'provider' => 'google',
    'display_name' => 'Gemini 2.5 Flash',
    // pricing + limits validados
],
[
    'model_name' => 'gemini-3.1',
    'provider' => 'google',
    'display_name' => 'Gemini 3.1',
    // usar ID oficial validado caso difira do nome comercial
],
[
    'model_name' => 'gemini-3.1-flash',
    'provider' => 'google',
    'display_name' => 'Gemini 3.1 Flash',
    // usar ID oficial validado caso difira do nome comercial
],
```

</details>

**Etapas**

- [ ]   1. Atualizar o array `$models` em `api/database/seeders/AiModelPricingSeeder.php`
- [ ]   2. Incluir os 4 modelos novos com `provider = google`
- [ ]   3. Garantir que `notes` documente fallback quando os nomes comerciais divergirem do ID oficial
- [ ]   4. Verificar que o fluxo de update/create permanece idempotente
- [ ]   5. Verificar `cd /Users/rafael.silva/Documents/agentflix/api && composer gate:all`

**Critérios de conclusão**

- [ ] O seeder atualiza registros existentes e cria os ausentes sem duplicar modelos Google
      -> `test_ai_model_pricing_seeder_upserts_google_gemini_catalog`
- [ ] `gemini-1.5-pro` continua presente após a atualização do catálogo
      -> `test_ai_model_pricing_seeder_preserves_existing_google_models`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Entrega 2 — Gateway: configuração base do provider Google ✅ testável

**Entrega:** Configuração tipada e runtime base do provider Google disponível para o domínio AI | **Agente:** @DEV

**Gate:** `cd /Users/rafael.silva/Documents/agentflix/gateway && pnpm lint && pnpm test`

### TASK-029.2.1 — Estender `configuration.model.ts` com `GoogleConfiguration`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Adicionar a interface `GoogleConfiguration` e registrá-la na estrutura consolidada `Configuration`, mantendo o padrão usado por `OpenAIConfiguration`.

**Constraints**

- A interface deve ser suficiente para `apiKey`, `model`, `timeout` e `maxRetries`
- Não remover nem alterar contratos existentes de outros providers
- Manter comentários e estilo de tipagem do arquivo

**Context**

- Módulos afetados: Gateway / AI / Config
- Dependências: Nenhuma

**Context References**

- `gateway/src/core/config/models/configuration.model.ts` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code
export interface OpenAIConfiguration {
    apiKey: string;
    apiKeyFallback?: string;
    model: string;
    embeddingModel: string;
    timeout: number;
    maxRetries: number;
}
```

```typescript
// Expected code
export interface GoogleConfiguration {
    apiKey: string;
    model: string;
    timeout: number;
    maxRetries: number;
}

export interface Configuration {
    // ...
    openai: OpenAIConfiguration;
    google: GoogleConfiguration;
    // ...
}
```

</details>

**Etapas**

- [ ]   1. Adicionar `GoogleConfiguration` em `gateway/src/core/config/models/configuration.model.ts`
- [ ]   2. Registrar `google` na interface `Configuration`
- [ ]   3. Verificar tipos e imports/export do arquivo

**Critérios de conclusão**

- [ ] O tipo `Configuration` expõe `google` com contrato forte
      -> `it('should expose GoogleConfiguration in gateway configuration types')`
- [ ] Nenhum provider existente perde tipagem ou compatibilidade
      -> `it('should preserve existing gateway configuration contracts')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.2.2 — Registrar `googleConfig` e circuit breaker no runtime do gateway

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Adicionar `googleConfig` ao `configuration.ts` e registrar a chave `google` no `circuit-breaker.config.ts`, deixando o runtime pronto para chamadas ao provider Google.

**Constraints**

- Usar prefixo de env `GOOGLE_`
- Definir defaults explícitos para model, timeout e retries
- O circuit breaker de `google` deve seguir o baseline de `openai`, salvo justificativa explícita

**Context**

- Módulos afetados: Gateway / AI / Config
- Dependências: `TASK-029.2.1`

**Context References**

- `gateway/src/core/config/configuration.ts` _(required in context)_
- `gateway/src/core/config/circuit-breaker.config.ts` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code (configuration.ts)
export const openaiConfig = registerAs(
    'openai',
    (): OpenAIConfiguration => ({
        apiKey: process.env.OPENAI_API_KEY ?? '',
        apiKeyFallback: process.env.OPENAI_API_KEY_FALLBACK,
        model: process.env.OPENAI_DEFAULT_MODEL ?? process.env.OPENAI_MODEL ?? 'gpt-4o',
        embeddingModel: process.env.OPENAI_EMBEDDING_MODEL ?? 'text-embedding-3-small',
        timeout: parseInt(process.env.OPENAI_TIMEOUT_MS ?? process.env.OPENAI_TIMEOUT ?? '180000', 10),
        maxRetries: parseInt(process.env.OPENAI_MAX_RETRIES ?? '3', 10),
    }),
);
```

```typescript
// Expected code (configuration.ts)
export const googleConfig = registerAs(
    'google',
    (): GoogleConfiguration => ({
        apiKey: process.env.GOOGLE_AI_API_KEY ?? '',
        model: process.env.GOOGLE_DEFAULT_MODEL ?? 'gemini-2.5-flash',
        timeout: parseInt(process.env.GOOGLE_TIMEOUT_MS ?? '180000', 10),
        maxRetries: parseInt(process.env.GOOGLE_MAX_RETRIES ?? '3', 10),
    }),
);
```

```typescript
// Current code (circuit-breaker.config.ts)
export type CircuitBreakerKey = 'openai' | 'whatsapp' | 'asaas';
```

```typescript
// Expected code (circuit-breaker.config.ts)
export type CircuitBreakerKey = 'openai' | 'google' | 'whatsapp' | 'asaas';
```

</details>

**Etapas**

- [ ]   1. Adicionar `googleConfig` em `gateway/src/core/config/configuration.ts`
- [ ]   2. Registrar `googleConfig` em `configFactories`
- [ ]   3. Atualizar `gateway/src/core/config/circuit-breaker.config.ts` com chave `google`
- [ ]   4. Definir opções padrão do circuit breaker para `google`

**Critérios de conclusão**

- [ ] O gateway resolve `google` via `ConfigService` com defaults seguros
      -> `it('should register googleConfig with safe defaults')`
- [ ] O circuit breaker aceita e retorna configuração válida para `google`
      -> `it('should return circuit breaker options for google provider')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.2.3 — Criar `GeminiConfigService`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Criar `gateway/src/domains/ai/providers/google/gemini.config.ts` com um serviço de configuração tipado, validando a presença de `GOOGLE_AI_API_KEY` e expondo getters para o adapter.

**Constraints**

- Seguir o padrão de `OpenAIConfigService`
- Não lançar erro fatal no bootstrap; apenas logar ausência da chave, como o provider OpenAI já faz
- Expor `getConfig()`, `getApiKey()`, `getDefaultModel()`, `getTimeoutMs()`, `getMaxRetries()` e `isConfigured()`

**Context**

- Módulos afetados: Gateway / AI / Config
- Dependências: `TASK-029.2.2`

**Context References**

- `gateway/src/domains/ai/providers/openai/openai.config.ts` _(required in context)_

**Code Context**

<details>
<summary>Expected contract</summary>

```typescript
export interface GeminiConfig {
    apiKey: string;
    defaultModel: string;
    timeoutMs: number;
    maxRetries: number;
}

@Injectable()
export class GeminiConfigService implements OnModuleInit {
    constructor(private readonly configService: ConfigService) {}

    onModuleInit(): void {
        this.validateConfiguration();
    }

    getConfig(): GeminiConfig {
        return this.config;
    }

    getApiKey(): string {
        return this.config.apiKey;
    }

    getDefaultModel(): string {
        return this.config.defaultModel;
    }
}
```

</details>

**Etapas**

- [ ]   1. Criar `gateway/src/domains/ai/providers/google/gemini.config.ts`
- [ ]   2. Ler config `google` via `ConfigService`
- [ ]   3. Implementar validação de bootstrap com logs consistentes
- [ ]   4. Expor getters usados pelo adapter

**Critérios de conclusão**

- [ ] O serviço retorna defaults consistentes quando apenas env mínimos estão presentes
      -> `it('should expose Google Gemini config defaults')`
- [ ] O serviço sinaliza corretamente quando `GOOGLE_AI_API_KEY` não está configurada
      -> `it('should report google provider as not configured when api key is missing')`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Entrega 3 — Gateway: adapter Gemini e wiring DI ✅ testável

**Entrega:** Provider Google funcional no domínio AI, com tradução normalizada e registro completo no DI NestJS | **Agente:** @DEV

**Gate:** `cd /Users/rafael.silva/Documents/agentflix/gateway && pnpm lint && pnpm test`

### TASK-029.3.1 — Criar `GeminiTranslator`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Criar `gateway/src/domains/ai/providers/google/gemini.translator.ts` para converter a resposta do SDK Google em `AICompletionResponseDto`, normalizando tokens e `finishReason`.

**Constraints**

- O translator deve isolar o domínio das particularidades do SDK Google
- `STOP`, `MAX_TOKENS` e `SAFETY` devem ser normalizados para os valores usados internamente
- Deve suportar resposta textual vazia sem quebrar a DTO normalizada

**Context**

- Módulos afetados: Gateway / AI
- Dependências: `TASK-029.2.3`

**Context References**

- `gateway/src/domains/ai/providers/openai/openai.translator.ts` _(required in context)_
- `gateway/src/domains/ai/interfaces/ai-completion-response.dto.ts` _(required in context)_

**Code Context**

<details>
<summary>Expected contract</summary>

```typescript
@Injectable()
export class GeminiTranslator {
    translate(response: GeminiResponseLike): AICompletionResponseDto {
        return createAICompletionResponse({
            content: this.extractContent(response),
            promptTokens: response.usageMetadata?.promptTokenCount ?? 0,
            completionTokens: response.usageMetadata?.candidatesTokenCount ?? 0,
            totalTokens: response.usageMetadata?.totalTokenCount ?? 0,
            model: response.modelVersion ?? 'unknown',
            finishReason: this.normalizeFinishReason(response.candidates?.[0]?.finishReason),
        });
    }
}
```

</details>

**Etapas**

- [ ]   1. Criar `gateway/src/domains/ai/providers/google/gemini.translator.ts`
- [ ]   2. Implementar `translate()` com defaults seguros
- [ ]   3. Implementar normalização de `finishReason`
- [ ]   4. Cobrir conteúdo textual e respostas sem conteúdo

**Critérios de conclusão**

- [ ] O translator converte a resposta Google em DTO normalizada com contagem de tokens correta
      -> `it('should translate Google Gemini responses into normalized completion DTOs')`
- [ ] Finish reasons incompatíveis são normalizados sem quebrar o fluxo
      -> `it('should normalize Google Gemini finish reasons to internal values')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.3.2 — Criar `GeminiProviderAdapter`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Criar `gateway/src/domains/ai/providers/google/gemini-provider.adapter.ts`, implementando `AIProvider` com suporte ao catálogo Google Gemini definido no plano.

**Constraints**

- `readonly name = 'google'`
- Um único adapter deve suportar toda a família Gemini, sem criar providers por geração de modelo
- Converter `messages` para o formato do SDK Google, separando `system` em `systemInstruction`
- Usar circuit breaker com a chave `google`
- Se o modelo solicitado não vier no request, usar default do `GeminiConfigService`

**Context**

- Módulos afetados: Gateway / AI
- Dependências: `TASK-029.2.3`, `TASK-029.3.1`

**Context References**

- `gateway/src/domains/ai/interfaces/ai-provider.interface.ts` _(required in context)_
- `gateway/src/domains/ai/providers/openai/openai-provider.adapter.ts` _(required in context)_
- `gateway/src/domains/ai/models/ai-completion.model.ts` _(required in context)_

**Code Context**

<details>
<summary>Expected contract</summary>

```typescript
@Injectable()
export class GeminiProviderAdapter implements AIProvider {
    readonly name = 'google';

    static readonly metadata: AIProviderMetadata = {
        name: 'google',
        description: 'Google Gemini models',
        supportedModels: ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-3.1', 'gemini-3.1-flash'],
        supportsStreaming: false,
    };

    async complete(request: AICompletionRequest): Promise<AICompletionResponseDto> {
        // map request -> Google SDK -> translator
    }
}
```

</details>

**Etapas**

- [ ]   1. Criar `gateway/src/domains/ai/providers/google/gemini-provider.adapter.ts`
- [ ]   2. Integrar `@google/generative-ai`, `GeminiConfigService`, `GeminiTranslator` e `CircuitBreakerService`
- [ ]   3. Implementar mapeamento `AICompletionRequest -> Google SDK`
- [ ]   4. Implementar `complete()` com timeout, retry e tradução de erro
- [ ]   5. Definir catálogo suportado com os modelos do plano, ajustando IDs oficiais se necessário

**Critérios de conclusão**

- [ ] O adapter resolve provider `google` e usa o modelo default quando o request não informar `model`
      -> `it('should use Google default model when completion request does not provide one')`
- [ ] O adapter converte mensagens internas para o formato esperado pelo SDK Google
      -> `it('should map AICompletionRequest messages to Google Gemini request format')`
- [ ] Erros do SDK Google são classificados sem vazar detalhes sensíveis
      -> `it('should map Google Gemini SDK errors to gateway errors safely')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.3.3 — Criar `GeminiProviderModule`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Criar `gateway/src/domains/ai/providers/google/gemini.module.ts` para encapsular `GeminiConfigService`, `GeminiTranslator` e `GeminiProviderAdapter` no DI do NestJS.

**Constraints**

- Seguir o padrão de `OpenAIProviderModule`
- Exportar o adapter e o config service
- Não duplicar `CircuitBreakerService` fora da composição já usada por provider modules

**Context**

- Módulos afetados: Gateway / AI / DI
- Dependências: `TASK-029.2.3`, `TASK-029.3.1`, `TASK-029.3.2`

**Context References**

- `gateway/src/domains/ai/providers/openai/openai.module.ts` _(required in context)_

**Code Context**

<details>
<summary>Expected contract</summary>

```typescript
@Module({
    providers: [GeminiConfigService, GeminiTranslator, GeminiProviderAdapter, CircuitBreakerService],
    exports: [GeminiConfigService, GeminiProviderAdapter],
})
export class GeminiProviderModule {}
```

</details>

**Etapas**

- [ ]   1. Criar `gateway/src/domains/ai/providers/google/gemini.module.ts`
- [ ]   2. Registrar providers e exports necessários
- [ ]   3. Validar composição de DI com `GeminiProviderAdapter`

**Critérios de conclusão**

- [ ] O módulo exporta os providers necessários para o domínio AI consumir o provider Google
      -> `it('should export Gemini provider services from GeminiProviderModule')`
- [ ] O bootstrap do módulo não introduz conflitos com `OpenAIProviderModule`
      -> `it('should compose GeminiProviderModule alongside OpenAIProviderModule')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.3.4 — Registrar o provider Google no factory e no `AIModule`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Fazer o wiring de DI final no gateway, registrando `GeminiProviderAdapter` no `AIProviderFactory` e importando `GeminiProviderModule` no `AIModule`.

**Constraints**

- `AIProviderFactory` deve continuar compatível com OpenAI
- `listProviders()` deve passar a incluir `google`
- O `AIModule` deve importar o módulo Gemini sem remover o OpenAI

**Context**

- Módulos afetados: Gateway / AI / DI
- Dependências: `TASK-029.3.3`

**Context References**

- `gateway/src/domains/ai/providers/ai-provider.factory.ts` _(required in context)_
- `gateway/src/domains/ai/ai.module.ts` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code (ai-provider.factory.ts)
constructor(
  private readonly openaiAdapter: OpenAIProviderAdapter,
  // Futuros providers serão injetados aqui:
  // private readonly geminiAdapter: GeminiProviderAdapter,
  // private readonly anthropicAdapter: AnthropicProviderAdapter,
) {
  this.registerProviders();
}

private registerProviders(): void {
  this.registerProvider(this.openaiAdapter);
}
```

```typescript
// Expected code (ai-provider.factory.ts)
constructor(
  private readonly openaiAdapter: OpenAIProviderAdapter,
  private readonly geminiAdapter: GeminiProviderAdapter,
) {
  this.registerProviders();
}

private registerProviders(): void {
  this.registerProvider(this.openaiAdapter);
  this.registerProvider(this.geminiAdapter);
}
```

```typescript
// Current code (ai.module.ts)
imports: [RedisModule, OpenAIProviderModule, MetricsModule],
```

```typescript
// Expected code (ai.module.ts)
imports: [RedisModule, OpenAIProviderModule, GeminiProviderModule, MetricsModule],
```

</details>

**Etapas**

- [ ]   1. Atualizar `gateway/src/domains/ai/providers/ai-provider.factory.ts`
- [ ]   2. Registrar `GeminiProviderAdapter` no constructor e no `registerProviders()`
- [ ]   3. Atualizar `gateway/src/domains/ai/ai.module.ts` para importar `GeminiProviderModule`
- [ ]   4. Verificar que `AIProviderFactory.listProviders()` incluirá `google`

**Critérios de conclusão**

- [ ] O factory resolve o provider `google` sem regressão para `openai`
      -> `it('should register and resolve both openai and google providers')`
- [ ] O `AIModule` importa o módulo Gemini e continua inicializando o domínio AI
      -> `it('should wire GeminiProviderModule into AIModule')`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Entrega 4 — Surface de configuração e dependências ✅ testável

**Entrega:** Dependência do SDK e variáveis de ambiente documentadas para uso do provider Google | **Agente:** @DEV

**Gate:** `cd /Users/rafael.silva/Documents/agentflix/gateway && pnpm lint && pnpm test`

### TASK-029.4.1 — Adicionar SDK Google ao `gateway/package.json`

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Adicionar a dependência `@google/generative-ai` ao `gateway/package.json`, mantendo compatibilidade com a stack NestJS atual.

**Constraints**

- Pinar uma versão explícita e estável
- Não remover `openai`
- Não alterar scripts do projeto sem necessidade

**Context**

- Módulos afetados: Gateway / Build
- Dependências: Nenhuma

**Context References**

- `gateway/package.json` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```json
// Current code
"dependencies": {
  "@nestjs/bullmq": "^11.0.4",
  "@nestjs/common": "^11.0.1",
  "openai": "^6.16.0",
  "pg": "^8.13.1"
}
```

```json
// Expected code
"dependencies": {
  "@google/generative-ai": "<versao-estavel>",
  "@nestjs/bullmq": "^11.0.4",
  "@nestjs/common": "^11.0.1",
  "openai": "^6.16.0",
  "pg": "^8.13.1"
}
```

</details>

**Etapas**

- [ ]   1. Atualizar `gateway/package.json`
- [ ]   2. Definir versão estável de `@google/generative-ai`
- [ ]   3. Verificar instalação e resolução de tipos no gateway

**Critérios de conclusão**

- [ ] O gateway compila com a dependência oficial do Google instalada
      -> `it('should compile gateway with google generative ai sdk installed')`
- [ ] Nenhuma dependência existente do provider OpenAI é removida
      -> `it('should preserve existing AI provider dependencies when adding Google SDK')`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.4.2 — Atualizar `.env.example` do gateway e da API com variáveis Google

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Documentar as variáveis de ambiente mínimas para o provider Google no `gateway/.env.example` e, se necessário para coerência do ecossistema, no `api/.env.example`.

**Constraints**

- Seguir a organização já usada no bloco OpenAI do gateway
- No backend API, documentar apenas o que for realmente necessário ao runtime local do ecossistema
- Não introduzir segredos reais

**Context**

- Módulos afetados: Gateway / API / DX
- Dependências: `TASK-029.2.2`

**Context References**

- `gateway/.env.example` _(required in context)_
- `api/.env.example` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```dotenv
# Current code (gateway/.env.example)
# OPENAI CONFIGURATION
OPENAI_API_KEY=sk-your-api-key
OPENAI_DEFAULT_MODEL=gpt-4o
OPENAI_TIMEOUT_MS=180000
OPENAI_MAX_RETRIES=3
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

```dotenv
# Expected code (gateway/.env.example)
# GOOGLE GEMINI CONFIGURATION
GOOGLE_AI_API_KEY=
GOOGLE_DEFAULT_MODEL=gemini-2.5-flash
GOOGLE_TIMEOUT_MS=180000
GOOGLE_MAX_RETRIES=3
```

</details>

**Etapas**

- [ ]   1. Atualizar `gateway/.env.example` com bloco Google Gemini
- [ ]   2. Atualizar `api/.env.example` apenas se houver dependência operacional clara para o backend
- [ ]   3. Documentar defaults consistentes com `googleConfig`

**Critérios de conclusão**

- [ ] O `.env.example` do gateway descreve todas as variáveis mínimas do provider Google
      -> `it('should document required Google Gemini environment variables in gateway env example')`
- [ ] A documentação de ambiente permanece coerente com os defaults definidos em código
      -> `it('should keep Google Gemini env example aligned with gateway defaults')`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Entrega 5 — Frontend: opções de modelo no form ✅ testável

**Entrega:** Formulário de agentes exibe o catálogo Gemini suportado sem quebrar a experiência atual | **Agente:** @FRONTEND

**Gate:** `cd /Users/rafael.silva/Documents/agentflix/app && pnpm run gate:all`

**Skills obrigatórios:** `.claude/skills/design/SKILL.md`, `.claude/skills/frontend-flow/SKILL.md`, `.github/skills/angular-architect/SKILL.md`, `.github/skills/coding-guidelines/SKILL.md`

### TASK-029.5.1 — Atualizar `agent-form.ts` com opções Gemini 2.5 e 3.1

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Atualizar `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts` para expor os modelos Gemini 2.5 e 3.1 no dropdown de seleção de modelo, preservando o comportamento atual do formulário.

**Constraints**

- Manter `ChangeDetectionStrategy.OnPush`
- Não introduzir `any` ou lógica de UI fora do padrão do componente
- Preservar o default atual (`gpt-4o-mini`) até existir decisão de produto diferente
- Se os IDs oficiais de 3.1 diferirem, o `value` deve usar o ID canônico e o `label` deve manter o nome comercial solicitado

**Context**

- Módulos afetados: Frontend / AI
- Dependências: `TASK-029.1.2` e validação de IDs do Review

**Context References**

- `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts` _(required in context)_

**Code Context**

<details>
<summary>Current → Expected</summary>

```typescript
// Current code
readonly modelOptions: AfSelectOption[] = [
  { value: 'gpt-4o', label: 'GPT-4o (Avançado)' },
  { value: 'gpt-4o-mini', label: 'GPT-4o Mini (Rápido)' },
];
```

```typescript
// Expected code
readonly modelOptions: AfSelectOption[] = [
  { value: 'gpt-4o', label: 'GPT-4o (Avançado)' },
  { value: 'gpt-4o-mini', label: 'GPT-4o Mini (Rápido)' },
  { value: 'gemini-2.5-pro', label: 'Gemini 2.5 Pro (Avançado)' },
  { value: 'gemini-2.5-flash', label: 'Gemini 2.5 Flash (Rápido)' },
  { value: 'gemini-3.1', label: 'Gemini 3.1 (Avançado)' },
  { value: 'gemini-3.1-flash', label: 'Gemini 3.1 Flash (Rápido)' },
];
```

</details>

**Etapas**

- [ ]   1. Atualizar `readonly modelOptions` em `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts`
- [ ]   2. Garantir consistência entre `value` e IDs canônicos validados no Review
- [ ]   3. Verificar que o default do formulário continua estável
- [ ]   4. Verificar `cd /Users/rafael.silva/Documents/agentflix/app && pnpm run gate:all`

**Critérios de conclusão**

- [ ] O dropdown exibe os quatro novos modelos Gemini com labels legíveis
      -> `it('should show Gemini 2.5 and 3.1 models in agent form model select')`
- [ ] O formulário continua iniciando com `gpt-4o-mini` como valor padrão
      -> `it('should preserve gpt-4o-mini as default model in agent form')`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Entrega 6 — Validation e Confirm ✅ testável

**Entrega:** Implementação validada, auditada e pronta para fechamento no fluxo PREVC | **Agente:** @QA

**Gate:** Todos os gates das camadas afetadas devem estar verdes e as evidências preenchidas neste documento.

### TASK-029.6.1 — Executar gates, testes e consolidar evidências técnicas

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Executar todos os gates das camadas afetadas, adicionar os testes de gateway previstos no plano e registrar os resultados nas seções de evidência das tasks anteriores.

**Constraints**

- Não encerrar com gates vermelhos
- Gateway deve ter testes para `GeminiConfigService`, `GeminiTranslator` e `GeminiProviderAdapter`
- Se um gate falhar por causa externa e não relacionada ao escopo, registrar bloqueio explicitamente antes de avançar

**Context**

- Módulos afetados: Backend / Gateway / Frontend
- Dependências: entregas 1 a 5 concluídas

**Context References**

- `PLAN-029-adicionar-modelos-gemini-2-5` _(embedded above)_
- `TASKS-029` _(embedded above)_

**Etapas**

- [ ]   1. Criar/atualizar `gateway/src/domains/ai/providers/google/gemini.config.spec.ts`
- [ ]   2. Criar/atualizar `gateway/src/domains/ai/providers/google/gemini.translator.spec.ts`
- [ ]   3. Criar/atualizar `gateway/src/domains/ai/providers/google/gemini-provider.adapter.spec.ts`
- [ ]   4. Executar `cd /Users/rafael.silva/Documents/agentflix/api && composer gate:all`
- [ ]   5. Executar `cd /Users/rafael.silva/Documents/agentflix/gateway && pnpm lint && pnpm test`
- [ ]   6. Executar `cd /Users/rafael.silva/Documents/agentflix/app && pnpm run gate:all`
- [ ]   7. Registrar resultados em `Evidências` nas tasks executadas

**Critérios de conclusão**

- [ ] O gateway possui specs cobrindo config, translator e adapter Gemini
      -> `it('should cover Gemini config, translator and adapter with Jest specs')`
- [ ] Todos os gates das camadas afetadas passam ou têm bloqueio externo documentado
      -> `test_prevc_validation_records_all_affected_layer_gates`

**Evidências**

- Gates:
- Review:
- Commit:

---

### TASK-029.6.2 — Acionar Review final, QA e fechamento Confirm

**Status:** todo

**Plano origem:** PLAN-029-adicionar-modelos-gemini-2-5

**PRD relacionado:** N/A

**Goal**

Completar a fase Confirm do PREVC: acionar `@QA`, acionar `@REVIEWER`, consolidar aprovações, preparar commit semântico com `@GIT_COMMIT` e marcar a task como concluída somente após ausência de blockers críticos.

**Constraints**

- `@QA` e `@REVIEWER` são mandatórios
- No máximo 2 loops de correção ↔ review antes de replanejamento
- Não criar commit sem evidência de gates e reviews

**Context**

- Módulos afetados: Processo PREVC
- Dependências: `TASK-029.6.1`

**Context References**

- `.context/WORKFLOW/prevc.md` _(required in context)_
- `.context/DOCS/MEMORY/architecture-decisions.md` _(required in context)_

**Etapas**

- [ ]   1. Acionar `@QA` para auditoria final do escopo
- [ ]   2. Acionar `@REVIEWER` para revisão final de código e aderência aos padrões
- [ ]   3. Corrigir eventuais blockers críticos e repetir review, respeitando o limite de 2 loops
- [ ]   4. Acionar `@GIT_COMMIT` para mensagem semântica após aprovação
- [ ]   5. Atualizar status das entregas e evidências no `TASKS-029`

**Critérios de conclusão**

- [ ] A entrega final possui aprovação de QA e review sem blockers críticos
      -> `test_prevc_confirm_requires_qa_and_reviewer_approval`
- [ ] O fechamento registra gates, reviews e commit semântico antes de marcar `done`
      -> `test_prevc_confirm_records_evidence_before_done_status`

**Evidências**

- Gates:
- Review:
- Commit:

---

## Notas

- O nome comercial pedido pelo usuário pode divergir do ID canônico aceito pela Google. Se isso ocorrer, o executor deve preservar o rótulo comercial no frontend e persistir o ID oficial em `model_name`.
- Um único provider `google` deve atender todas as variantes Gemini suportadas. Não criar providers separados por geração.
- Se a UI evoluir para catálogo dinâmico vindo do backend, isso deve ser tratado em um plano separado. O escopo atual é apenas expor as opções no formulário existente.
