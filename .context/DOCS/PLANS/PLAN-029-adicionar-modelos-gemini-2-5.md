# PLAN-029-adicionar-modelos-gemini-2-5 — Adicionar Modelos Gemini 2.5 e 3.1

## Objetivo

Adicionar suporte completo aos modelos **Gemini 2.5 Pro**, **Gemini 2.5 Flash**, **Gemini 3.1** e **Gemini 3.1 Flash** (Google) no sistema InteraZap, cobrindo as três camadas: Backend (pricing/seeder), Gateway (provider adapter) e Frontend (seleção de modelo). O plano também passa a incluir a validação dos identificadores oficiais dos modelos no SDK/endpoint da Google antes da implementação final.

## Módulo relacionado

**Ai** | **Gateway** | **Configuration**

## PRD relacionado (se existir): N/A

## Escopo

### Incluído

- Criar `GeminiProviderAdapter` no Gateway (seguindo padrão do `OpenAIProviderAdapter`)
- Criar `GeminiConfigService` para configuração tipada (`GOOGLE_AI_API_KEY`, modelo default, timeout)
- Criar `GeminiTranslator` (anti-corruption layer para normalizar respostas Gemini → DTO padrão)
- Criar `GeminiProviderModule` com DI (equivalente ao `OpenAIProviderModule`)
- Registrar adapter Gemini no `AIProviderFactory`
- Adicionar modelos `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-3.1` e `gemini-3.1-flash` no `AiModelPricingSeeder`
- Criar migration para inserir os 4 novos modelos na tabela `ai_model_pricings`
- Adicionar config `googleConfig` no `configuration.ts` do Gateway
- Registrar `google` no `CircuitBreakerKey`
- Atualizar Frontend: tornar dropdown de modelos dinâmico (ou adicionar opções Gemini)
- Instalar SDK `@google/generative-ai` no Gateway
- Adicionar variáveis de ambiente no `.env.example` de gateway e api
- Validar durante a execução se os nomes comerciais `Gemini 3.1` e `Gemini 3.1 Flash` correspondem exatamente aos IDs oficiais aceitos pela API/SDK da Google

### Excluído

- Suporte a streaming Gemini (será fase futura, já previsto na interface `stream?()`)
- Suporte a embeddings Gemini (mantém OpenAI como provider de embeddings)
- Adapter para Anthropic (escopo separado)
- Alteração no fluxo de delegação de agentes (Autopilot)
- Alteração na UI de criação de agentes além do dropdown de modelo

## Evidências da Codebase

### Backend (Ai)

| Arquivo                                                                                                                                    | Descrição                                                            |
| ------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| [api/src/Domain/Ai/Enums/AiProviderType.php](api/src/Domain/Ai/Enums/AiProviderType.php)                                                   | Enum já possui `GOOGLE = 'google'` com default `gemini-pro`          |
| [api/src/Domain/Ai/Models/AiModelPricing.php](api/src/Domain/Ai/Models/AiModelPricing.php)                                                 | Model com `findByModel()`, `calculateTotalCost()`, schema flexível   |
| [api/database/seeders/AiModelPricingSeeder.php](api/database/seeders/AiModelPricingSeeder.php)                                             | Já tem `gemini-1.5-pro`; precisa adicionar 2.5 Pro/Flash e 3.1/Flash |
| [api/database/migrations/2026_01_01_000050_create_ai_core_tables.php](api/database/migrations/2026_01_01_000050_create_ai_core_tables.php) | Schema `ai_model_pricings` com unique(`provider`, `model_name`)      |

### Gateway (NestJS)

| Arquivo                                                                                                                                  | Descrição                                                                             |
| ---------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| [gateway/src/domains/ai/interfaces/ai-provider.interface.ts](gateway/src/domains/ai/interfaces/ai-provider.interface.ts)                 | Interface `AIProvider` com `complete()`, `stream?()`, `isHealthy?()`                  |
| [gateway/src/domains/ai/providers/ai-provider.factory.ts](gateway/src/domains/ai/providers/ai-provider.factory.ts)                       | Factory com `getProvider(name)` — só OpenAI registrado; comentários já preveem Gemini |
| [gateway/src/domains/ai/providers/openai/openai-provider.adapter.ts](gateway/src/domains/ai/providers/openai/openai-provider.adapter.ts) | Padrão de referência para novo adapter (circuit breaker, fallback, error mapping)     |
| [gateway/src/domains/ai/providers/openai/openai.config.ts](gateway/src/domains/ai/providers/openai/openai.config.ts)                     | Padrão de `ConfigService` para replicar com Google                                    |
| [gateway/src/domains/ai/providers/openai/openai.translator.ts](gateway/src/domains/ai/providers/openai/openai.translator.ts)             | Padrão de translator (anti-corruption layer) para replicar                            |
| [gateway/src/domains/ai/providers/openai/openai.module.ts](gateway/src/domains/ai/providers/openai/openai.module.ts)                     | Padrão de módulo NestJS para replicar                                                 |
| [gateway/src/domains/ai/ai.module.ts](gateway/src/domains/ai/ai.module.ts)                                                               | Módulo raiz AI — importar `GeminiProviderModule` aqui                                 |
| [gateway/src/core/config/configuration.ts](gateway/src/core/config/configuration.ts)                                                     | Adicionar `googleConfig` com `registerAs('google', ...)`                              |
| [gateway/src/core/config/models/configuration.model.ts](gateway/src/core/config/models/configuration.model.ts)                           | Definir `GoogleConfiguration` interface                                               |
| [gateway/src/core/config/circuit-breaker.config.ts](gateway/src/core/config/circuit-breaker.config.ts)                                   | Adicionar `'google'` ao `CircuitBreakerKey`                                           |

### Frontend (Angular)

| Arquivo                                                                                                                  | Descrição                                                            |
| ------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------- |
| [app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts](app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts) | Dropdown hardcoded com GPT-4o e GPT-4o Mini — precisa incluir Gemini |

### Shared Components

- `AfSelectInput` disponível em `app/src/app/shared/components/select-input/`

---

## Etapas propostas

### Entrega 1 — Backend: Seeder + Migration (XS)

1. Criar migration `2026_03_31_000001_add_gemini_models_to_ai_model_pricings.php` para inserir os 4 modelos
2. Atualizar `AiModelPricingSeeder.php` adicionando `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-3.1` e `gemini-3.1-flash`

### Entrega 2 — Gateway: Gemini Provider Adapter (M)

3. Instalar `@google/generative-ai` no Gateway
4. Definir `GoogleConfiguration` em `configuration.model.ts`
5. Criar `googleConfig` em `configuration.ts` (`GOOGLE_AI_API_KEY`, `GOOGLE_DEFAULT_MODEL`, `GOOGLE_TIMEOUT_MS`)
6. Adicionar `'google'` ao `CircuitBreakerKey` em `circuit-breaker.config.ts`
7. Criar `gateway/src/domains/ai/providers/google/gemini.config.ts` (config service)
8. Criar `gateway/src/domains/ai/providers/google/gemini.translator.ts` (anti-corruption layer)
9. Criar `gateway/src/domains/ai/providers/google/gemini-provider.adapter.ts` (implements `AIProvider`)
10. Criar `gateway/src/domains/ai/providers/google/gemini.module.ts` (NestJS module)
11. Registrar `GeminiProviderAdapter` no `AIProviderFactory`
12. Importar `GeminiProviderModule` no `AiModule`

### Entrega 3 — Frontend: Dropdown de Modelos (S)

13. Adicionar opções Gemini 2.5 Pro, 2.5 Flash, 3.1 e 3.1 Flash no dropdown de modelos do `agent-form.ts`

### Entrega 4 — Configuração e Testes (S)

14. Adicionar variáveis de ambiente nos `.env.example`
15. Criar testes unitários: `gemini-provider.adapter.spec.ts`, `gemini.translator.spec.ts`, `gemini.config.spec.ts`
16. Rodar gates em todas as camadas

---

## Entregas derivadas

**Entregas:** 4 | **Tasks:** 6

| Entrega | Descrição                                    | Tasks                       | Esforço | Status |
| ------- | -------------------------------------------- | --------------------------- | ------- | ------ |
| 1       | Backend: Migration + Seeder Gemini 2.5 e 3.1 | TASK-029.1.1                | XS      | todo   |
| 2       | Gateway: Gemini Provider Adapter completo    | TASK-029.2.1 — TASK-029.2.3 | M       | todo   |
| 3       | Frontend: Opções de modelo Gemini no form    | TASK-029.3.1                | S       | todo   |
| 4       | Config .env + Testes unitários               | TASK-029.4.1                | S       | todo   |

---

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                 | Ação          | Caminho                                                                                |
| ----------------------- | ------------- | -------------------------------------------------------------------------------------- |
| Migration Gemini models | **criar**     | `api/database/migrations/2026_03_31_000001_add_gemini_models_to_ai_model_pricings.php` |
| AiModelPricingSeeder    | **modificar** | `api/database/seeders/AiModelPricingSeeder.php`                                        |

### Gateway (NestJS)

| Arquivo                         | Ação          | Caminho                                                                   |
| ------------------------------- | ------------- | ------------------------------------------------------------------------- |
| GoogleConfiguration interface   | **modificar** | `gateway/src/core/config/models/configuration.model.ts`                   |
| googleConfig factory            | **modificar** | `gateway/src/core/config/configuration.ts`                                |
| CircuitBreakerKey type          | **modificar** | `gateway/src/core/config/circuit-breaker.config.ts`                       |
| GeminiConfigService             | **criar**     | `gateway/src/domains/ai/providers/google/gemini.config.ts`                |
| GeminiTranslator                | **criar**     | `gateway/src/domains/ai/providers/google/gemini.translator.ts`            |
| GeminiProviderAdapter           | **criar**     | `gateway/src/domains/ai/providers/google/gemini-provider.adapter.ts`      |
| GeminiProviderModule            | **criar**     | `gateway/src/domains/ai/providers/google/gemini.module.ts`                |
| AIProviderFactory               | **modificar** | `gateway/src/domains/ai/providers/ai-provider.factory.ts`                 |
| AiModule                        | **modificar** | `gateway/src/domains/ai/ai.module.ts`                                     |
| gemini-provider.adapter.spec.ts | **criar**     | `gateway/src/domains/ai/providers/google/gemini-provider.adapter.spec.ts` |
| gemini.translator.spec.ts       | **criar**     | `gateway/src/domains/ai/providers/google/gemini.translator.spec.ts`       |
| gemini.config.spec.ts           | **criar**     | `gateway/src/domains/ai/providers/google/gemini.config.spec.ts`           |
| package.json                    | **modificar** | `gateway/package.json` (adicionar `@google/generative-ai`)                |

### Frontend (Angular)

| Arquivo       | Ação          | Caminho                                                      |
| ------------- | ------------- | ------------------------------------------------------------ |
| agent-form.ts | **modificar** | `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts` |

### Configuração

| Arquivo                | Ação          | Caminho                         |
| ---------------------- | ------------- | ------------------------------- |
| .env.example (gateway) | **modificar** | `gateway/.env.example`          |
| .env.example (api)     | **modificar** | `api/.env.example` (se existir) |

---

## Tarefas Derivadas para Execução Paralela

| Task         | Descrição                                   | Agente    | Paralelo com           |
| ------------ | ------------------------------------------- | --------- | ---------------------- |
| TASK-029.1.1 | Migration + Seeder Gemini 2.5 e 3.1 (BE)    | @BACKEND  | TASK-029.2.1           |
| TASK-029.2.1 | Config + Circuit Breaker Google (GW)        | @DEV      | TASK-029.1.1           |
| TASK-029.2.2 | Gemini Adapter + Translator + Module (GW)   | @DEV      | - (depende de 029.2.1) |
| TASK-029.2.3 | Registrar Gemini no Factory + AiModule (GW) | @DEV      | - (depende de 029.2.2) |
| TASK-029.3.1 | Adicionar modelos Gemini no agent-form (FE) | @FRONTEND | TASK-029.2.2           |
| TASK-029.4.1 | .env.example + Testes unitários (GW)        | @DEV      | - (depende de 029.2.3) |

---

## Especificações Técnicas dos Modelos

### Gemini 2.5 Pro

| Propriedade          | Valor                                                           |
| -------------------- | --------------------------------------------------------------- |
| `provider`           | `google`                                                        |
| `model_name`         | `gemini-2.5-pro`                                                |
| `display_name`       | `Gemini 2.5 Pro (Avançado)`                                     |
| `input_cost_per_1m`  | `1.25` (até 200k tokens) / `2.50` (acima)                       |
| `output_cost_per_1m` | `10.00` (até 200k) / `15.00` (acima) — usar valor médio `10.00` |
| `max_context_tokens` | `1048576` (1M tokens)                                           |
| `max_output_tokens`  | `65536` (64K tokens)                                            |
| `is_active`          | `true`                                                          |
| `notes`              | `Thinking model, melhor performance, suporta tools`             |

### Gemini 2.5 Flash

| Propriedade          | Valor                                                       |
| -------------------- | ----------------------------------------------------------- |
| `provider`           | `google`                                                    |
| `model_name`         | `gemini-2.5-flash`                                          |
| `display_name`       | `Gemini 2.5 Flash (Rápido)`                                 |
| `input_cost_per_1m`  | `0.15`                                                      |
| `output_cost_per_1m` | `0.60` (sem thinking) / `3.50` (com thinking) — usar `0.60` |
| `max_context_tokens` | `1048576` (1M tokens)                                       |
| `max_output_tokens`  | `65536` (64K tokens)                                        |
| `is_active`          | `true`                                                      |
| `notes`              | `Modelo rápido e econômico, suporta tools`                  |

### Gemini 3.1

| Propriedade          | Valor                                                                                              |
| -------------------- | -------------------------------------------------------------------------------------------------- |
| `provider`           | `google`                                                                                           |
| `model_name`         | `gemini-3.1`                                                                                       |
| `display_name`       | `Gemini 3.1 (Avançado)`                                                                            |
| `input_cost_per_1m`  | `a validar com documentação oficial antes da migration final`                                      |
| `output_cost_per_1m` | `a validar com documentação oficial antes da migration final`                                      |
| `max_context_tokens` | `a validar com documentação oficial antes da migration final`                                      |
| `max_output_tokens`  | `a validar com documentação oficial antes da migration final`                                      |
| `is_active`          | `true`                                                                                             |
| `notes`              | `Modelo solicitado pelo produto; confirmar ID canônico e pricing no SDK Google antes de persistir` |

### Gemini 3.1 Flash

| Propriedade          | Valor                                                                                              |
| -------------------- | -------------------------------------------------------------------------------------------------- |
| `provider`           | `google`                                                                                           |
| `model_name`         | `gemini-3.1-flash`                                                                                 |
| `display_name`       | `Gemini 3.1 Flash (Rápido)`                                                                        |
| `input_cost_per_1m`  | `a validar com documentação oficial antes da migration final`                                      |
| `output_cost_per_1m` | `a validar com documentação oficial antes da migration final`                                      |
| `max_context_tokens` | `a validar com documentação oficial antes da migration final`                                      |
| `max_output_tokens`  | `a validar com documentação oficial antes da migration final`                                      |
| `is_active`          | `true`                                                                                             |
| `notes`              | `Modelo solicitado pelo produto; confirmar ID canônico e pricing no SDK Google antes de persistir` |

---

## Decisão de Arquitetura: Padrão do Adapter

O `GeminiProviderAdapter` segue o padrão Ports & Adapters já estabelecido:

```
AIProvider (interface/port)
├── OpenAIProviderAdapter  (existente)
└── GeminiProviderAdapter  (novo)
    ├── gemini.config.ts        — Config tipada com validação
    ├── gemini.translator.ts    — Anti-corruption layer (Gemini SDK → DTO normalizado)
    ├── gemini-provider.adapter.ts — Implementa AIProvider.complete()
    └── gemini.module.ts        — NestJS DI module
```

**SDK escolhido:** `@google/generative-ai` (SDK oficial Google para Node.js)

**Decisão adicional:** um único `GeminiProviderAdapter` atenderá toda a família Google Gemini suportada. A expansão de 2.5 para 3.1 é tratada como configuração de catálogo de modelos, e não como novos adapters ou novos providers.

**Mapeamento de parâmetros Gemini ↔ AICompletionRequest:**

| AICompletionRequest | Gemini SDK                                                                          |
| ------------------- | ----------------------------------------------------------------------------------- |
| `messages`          | `contents` (converter role: `user`/`model`, system separado em `systemInstruction`) |
| `model`             | `model` (string direta)                                                             |
| `maxTokens`         | `generationConfig.maxOutputTokens`                                                  |
| `temperature`       | `generationConfig.temperature`                                                      |
| `topP`              | `generationConfig.topP`                                                             |
| `tools`             | `tools` (formato diferente — requer tradução)                                       |

**Mapeamento de resposta Gemini → AICompletionResponseDto:**

| Gemini Response                               | AICompletionResponseDto                                                                      |
| --------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `response.text()`                             | `content`                                                                                    |
| `response.usageMetadata.promptTokenCount`     | `promptTokens`                                                                               |
| `response.usageMetadata.candidatesTokenCount` | `completionTokens`                                                                           |
| `response.usageMetadata.totalTokenCount`      | `totalTokens`                                                                                |
| `candidate.finishReason`                      | `finishReason` (normalizar: `STOP`→`stop`, `MAX_TOKENS`→`length`, `SAFETY`→`content_filter`) |

---

## Riscos e dependências

### Riscos

| Risco                                                                              | Probabilidade | Impacto | Mitigação                                                                                                                                       |
| ---------------------------------------------------------------------------------- | ------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Diferenças no formato de tools entre OpenAI e Gemini                               | Alta          | Médio   | Translator deve normalizar function_declarations ↔ tools                                                                                        |
| Rate limiting da API Google diferente do OpenAI                                    | Média         | Médio   | Circuit breaker + config de retry separado por provider                                                                                         |
| Preços dos modelos podem mudar rapidamente                                         | Média         | Baixo   | Tabela `ai_model_pricings` é editável; migration usa valores de referência                                                                      |
| SDK `@google/generative-ai` pode ter breaking changes                              | Baixa         | Alto    | Pinnar versão exata no `package.json`                                                                                                           |
| IDs oficiais de `Gemini 3.1` e `Gemini 3.1 Flash` podem diferir do nome solicitado | Alta          | Alto    | Validar nome canônico na documentação Google antes de concluir migration, se necessário manter `display_name` solicitado e `model_name` oficial |

### Dependências

- Gateway precisa ter acesso a `GOOGLE_AI_API_KEY` (variável de ambiente)
- Backend enum `AiProviderType::GOOGLE` já existe — sem alteração necessária
- Schema `ai_model_pricings` já suporta novos modelos — sem alteração de schema

---

## Validação e Gates

- [ ] Backend: `composer gate:all` em api/
- [ ] Gateway: `pnpm lint && pnpm test` em gateway/
- [ ] Frontend: `pnpm run gate:all` em app/
- [ ] Migration: `php artisan migrate` sem erros
- [ ] Testes: `gemini-provider.adapter.spec.ts` verde
- [ ] Testes: `gemini.translator.spec.ts` verde

---

## Estimativa

| Item                          | Valor                                           |
| ----------------------------- | ----------------------------------------------- |
| Complexidade                  | Média                                           |
| Camadas afetadas              | Backend / Gateway / Frontend                    |
| Migrações necessárias         | Sim (insert de dados)                           |
| Impacto em módulos existentes | Baixo (aditivo, não modifica fluxos existentes) |
