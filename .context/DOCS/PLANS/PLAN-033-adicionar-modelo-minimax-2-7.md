# PLAN-033-adicionar-modelo-minimax-2-7 — Adicionar Modelo MiniMax 2.7

## Objetivo

Adicionar suporte completo ao modelo **MiniMax 2.7** no InteraZap, cobrindo Backend (catálogo/pricing e enum de provider), Gateway (provider adapter MiniMax com factory, config e circuit breaker) e Frontend (seleção consistente de modelo nas telas de agente). O plano inclui validação obrigatória do identificador canônico do modelo e do contrato oficial da API MiniMax antes da implementação final.

## Módulo relacionado

**Ai** | **Gateway** | **Configuration**

## PRD relacionado (se existir): PRD-AI-001

## Escopo

### Incluído

- Adicionar provider `minimax` no backend Laravel (`AiProviderType` e `GatewayProvider`)
- Adicionar modelo `minimax-2.7` no catálogo de pricing (`AiModelPricingSeeder` + migration aditiva)
- Criar `MiniMaxProviderAdapter` no Gateway seguindo o padrão de `OpenAIProviderAdapter` e `GeminiProviderAdapter`
- Criar `MiniMaxConfigService` para configuração tipada (`MINIMAX_API_KEY`, modelo default, timeout, retries)
- Criar `MiniMaxTranslator` (anti-corruption layer para normalizar resposta MiniMax -> DTO padrão)
- Criar `MiniMaxProviderModule` e registrar no `AIProviderFactory`
- Adicionar `minimax` no `CircuitBreakerKey` e na configuração global do Gateway
- Atualizar frontend para expor `MiniMax 2.7` na criação e edição de agentes
- Eliminar divergência de catálogo no frontend, centralizando opções de modelos em um ponto reutilizável
- Atualizar `.env.example` e documentação operacional das variáveis MiniMax
- Criar testes unitários de config/translator/adapter MiniMax e ajustar testes de factory/providers

### Excluído

- Suporte a streaming MiniMax
- Suporte a embeddings MiniMax
- Refatoração completa do `ai-run-orchestrator.service.ts` para provider-agnostic no mesmo ciclo (será tratado como follow-up caso necessário)
- Mudanças de UX além da inclusão/normalização do seletor de modelos

## Contexto carregado

- Memórias consultadas em `.context/DOCS/MEMORY/` (context-log, architecture-decisions, ai-decisions, project-summary)
- Workflow PREVC e validação consultados (`.context/WORKFLOW/prevc.md`, `.context/WORKFLOW/validation-flow.md`)
- Estado do projeto consultado em `.context/ARCHITECTURE/project-state.yaml`
- Evidências coletadas com agentes `@ARCHITECT`, `@BACKEND`, `@FRONTEND`, `@DEV` (modo somente leitura)

## Technical Approach e Skills obrigatorios

Antes de qualquer implementação frontend desta task:

| Skill             | Caminho                                     | Uso no plano                           |
| ----------------- | ------------------------------------------- | -------------------------------------- |
| Design            | `.claude/skills/design/SKILL.md`            | tokens, estados visuais e consistencia |
| Frontend Flow     | `.claude/skills/frontend-flow/SKILL.md`     | checklist obrigatorio de execucao      |
| Angular Architect | `.github/skills/angular-architect/SKILL.md` | padroes Angular 20+                    |
| Coding Guidelines | `.github/skills/coding-guidelines/SKILL.md` | disciplina de mudancas cirurgicas      |

Regra de orquestracao para UI:

- `@DESIGNER` deve produzir especificacao antes de qualquer execucao por `@FRONTEND` em mudancas visuais relevantes.

## Evidências da codebase

### Backend (Ai/Gateway)

| Arquivo                                                                                | Descrição                                                        |
| -------------------------------------------------------------------------------------- | ---------------------------------------------------------------- |
| `api/src/Domain/Ai/Enums/AiProviderType.php`                                           | Enum de providers ainda sem `minimax`                            |
| `api/src/Domain/Gateway/Enums/GatewayProvider.php`                                     | Enum de provider do gateway ainda sem `minimax`                  |
| `api/config/gateway.php`                                                               | Define provider default e timeout de IA                          |
| `api/database/seeders/AiModelPricingSeeder.php`                                        | Catálogo atual com OpenAI/Anthropic/Google; sem MiniMax          |
| `api/database/migrations/2026_01_01_000050_create_ai_core_tables.php`                  | Tabela `ai_model_pricings` com unique (`provider`, `model_name`) |
| `api/database/migrations/2026_03_31_000001_add_gemini_models_to_ai_model_pricings.php` | Exemplo de migration aditiva para catálogo                       |

### Gateway (NestJS)

| Arquivo                                                              | Descrição                                        |
| -------------------------------------------------------------------- | ------------------------------------------------ |
| `gateway/src/domains/ai/interfaces/ai-provider.interface.ts`         | Porta de provider reutilizável                   |
| `gateway/src/domains/ai/providers/ai-provider.factory.ts`            | Factory com OpenAI + Google; sem MiniMax         |
| `gateway/src/domains/ai/providers/google/gemini-provider.adapter.ts` | Padrão de adapter + translator + circuit breaker |
| `gateway/src/core/config/models/configuration.model.ts`              | Tipagem central de configurações                 |
| `gateway/src/core/config/configuration.ts`                           | Register de configs por provider                 |
| `gateway/src/core/config/circuit-breaker.config.ts`                  | Chaves e parâmetros de circuit breaker           |
| `gateway/src/domains/ai/ai.module.ts`                                | Registro de módulos de providers                 |
| `gateway/src/domains/ai/consumers/ai-completion.consumer.ts`         | Consome requests com provider dinâmico           |

### Frontend (Angular)

| Arquivo                                                                  | Descrição                                             |
| ------------------------------------------------------------------------ | ----------------------------------------------------- |
| `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts`             | Lista de modelos hardcoded (inclui Gemini)            |
| `app/src/app/pages/ai/pages/agents/agent-workspace/tabs/overview-tab.ts` | Lista de modelos hardcoded divergente (apenas GPT)    |
| `app/src/app/shared/components/select-input/select-input.ts`             | Contrato do dropdown compartilhado (`AfSelectOption`) |

## Escopo validado

### Incluido

- Provider MiniMax funcional no pipeline de completion do Gateway
- Catalogo de pricing persistido para MiniMax 2.7 no backend
- Exposicao de MiniMax 2.7 nas duas telas de agente (criacao e edicao)
- Testes unitarios e ajustes de factory/config

### Excluido

- Migracao ampla de todos os fluxos OpenAI-hardcoded para provider-agnostic
- Novos componentes visuais nao relacionados ao seletor de modelo

### Dependencias

- Validação do ID canônico MiniMax e do endpoint oficial (SDK/API)
- Chave `MINIMAX_API_KEY` disponível no ambiente do gateway
- Sincronização entre enum/provider backend e nome registrado no factory do gateway

## Etapas propostas

### Entrega 1 — Review técnico e contrato MiniMax (XS)

1. Validar API oficial MiniMax: endpoint, auth, nome canônico do modelo 2.7, limites e campos de usage
2. Decidir estratégia de integração: SDK oficial ou cliente HTTP tipado
3. Registrar decisão técnica em evidência da task antes da execução

### Entrega 2 — Backend catálogo e enum de provider (S)

4. Adicionar `MINIMAX` nos enums de provider (`AiProviderType`, `GatewayProvider`)
5. Criar migration aditiva para inserir `minimax-2.7` em `ai_model_pricings`
6. Atualizar `AiModelPricingSeeder` com `provider = minimax`, custos e limites validados
7. Ajustar testes/factories backend afetados

### Entrega 3 — Gateway provider MiniMax (M)

8. Criar `gateway/src/domains/ai/providers/minimax/` com config, translator, adapter e module
9. Adicionar `MiniMaxConfiguration` em `configuration.model.ts` e `minimaxConfig` em `configuration.ts`
10. Incluir `minimax` no `CircuitBreakerKey` e parâmetros default de circuit breaker
11. Registrar `MiniMaxProviderAdapter` no `AIProviderFactory`
12. Importar `MiniMaxProviderModule` no `AiModule`
13. Cobrir com testes unitários de config/translator/adapter e ajuste de testes de factory

### Entrega 4 — Frontend catálogo unificado de modelos (S)

14. Criar fonte única para opções de modelos de IA (constante compartilhada no módulo AI)
15. Atualizar `agent-form.ts` e `overview-tab.ts` para consumir a mesma lista
16. Adicionar opção `MiniMax 2.7` com `value` igual ao ID canônico aprovado

### Entrega 5 — Validation e Confirm (S)

17. Rodar gates de todas as camadas afetadas
18. Executar revisão `@QA` e `@REVIEWER` (no máximo 2 ciclos correção/revisão)
19. Consolidar evidências PREVC e encaminhar commit semântico com `@GIT_COMMIT`

## Entregas derivadas

**Entregas:** 5 | **Tasks:** 8

| Entrega | Descrição                              | Tasks                       | Esforço | Status |
| ------- | -------------------------------------- | --------------------------- | ------- | ------ |
| 1       | Review técnico MiniMax e ID canônico   | TASK-033.1.1                | XS      | todo   |
| 2       | Backend: enum + migration + seeder     | TASK-033.2.1 - TASK-033.2.2 | S       | todo   |
| 3       | Gateway: provider MiniMax completo     | TASK-033.3.1 - TASK-033.3.3 | M       | todo   |
| 4       | Frontend: catálogo unificado + MiniMax | TASK-033.4.1                | S       | todo   |
| 5       | Validation e Confirm                   | TASK-033.5.1                | S       | todo   |

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo                                    | Ação      | Caminho                                                                                |
| ------------------------------------------ | --------- | -------------------------------------------------------------------------------------- |
| Enum AI provider                           | modificar | `api/src/Domain/Ai/Enums/AiProviderType.php`                                           |
| Enum Gateway provider                      | modificar | `api/src/Domain/Gateway/Enums/GatewayProvider.php`                                     |
| Gateway config (se default provider mudar) | modificar | `api/config/gateway.php`                                                               |
| Seeder de pricing                          | modificar | `api/database/seeders/AiModelPricingSeeder.php`                                        |
| Migration MiniMax catalogo                 | criar     | `api/database/migrations/2026_03_31_000002_add_minimax_model_to_ai_model_pricings.php` |
| Factory de pricing (se necessario)         | modificar | `api/database/factories/AiModelPricingFactory.php`                                     |
| Testes de gateway/AI afetados              | modificar | `api/tests/Unit/Domain/Gateway/Services/AI/AIGatewayServiceTest.php`                   |

### Gateway (NestJS)

| Arquivo                              | Ação      | Caminho                                                                     |
| ------------------------------------ | --------- | --------------------------------------------------------------------------- |
| Config model tipado                  | modificar | `gateway/src/core/config/models/configuration.model.ts`                     |
| Config factory                       | modificar | `gateway/src/core/config/configuration.ts`                                  |
| Circuit breaker key                  | modificar | `gateway/src/core/config/circuit-breaker.config.ts`                         |
| AI module                            | modificar | `gateway/src/domains/ai/ai.module.ts`                                       |
| Provider factory                     | modificar | `gateway/src/domains/ai/providers/ai-provider.factory.ts`                   |
| MiniMax config service               | criar     | `gateway/src/domains/ai/providers/minimax/minimax.config.ts`                |
| MiniMax translator                   | criar     | `gateway/src/domains/ai/providers/minimax/minimax.translator.ts`            |
| MiniMax adapter                      | criar     | `gateway/src/domains/ai/providers/minimax/minimax-provider.adapter.ts`      |
| MiniMax module                       | criar     | `gateway/src/domains/ai/providers/minimax/minimax.module.ts`                |
| Teste MiniMax config                 | criar     | `gateway/src/domains/ai/providers/minimax/minimax.config.spec.ts`           |
| Teste MiniMax translator             | criar     | `gateway/src/domains/ai/providers/minimax/minimax.translator.spec.ts`       |
| Teste MiniMax adapter                | criar     | `gateway/src/domains/ai/providers/minimax/minimax-provider.adapter.spec.ts` |
| Testes factory/integracao            | modificar | `gateway/src/domains/ai/__tests__/ai-provider.factory.spec.ts`              |
| Dependencia HTTP/SDK (se necessario) | modificar | `gateway/package.json`                                                      |

### Frontend (Angular)

| Arquivo                     | Ação      | Caminho                                                                  |
| --------------------------- | --------- | ------------------------------------------------------------------------ |
| Catálogo central de modelos | criar     | `app/src/app/pages/ai/constants/ai-model-options.ts`                     |
| Formulario de criação       | modificar | `app/src/app/pages/ai/pages/agents/agent-form/agent-form.ts`             |
| Aba overview (edição)       | modificar | `app/src/app/pages/ai/pages/agents/agent-workspace/tabs/overview-tab.ts` |

### Configuração

| Arquivo                            | Ação      | Caminho                |
| ---------------------------------- | --------- | ---------------------- |
| Exemplo env gateway                | modificar | `gateway/.env.example` |
| Documentacao de env (se aplicavel) | modificar | `gateway/README.md`    |

## Tarefas Derivadas para Execução

| Task         | Descrição                                          | Agente     | Dependência                              |
| ------------ | -------------------------------------------------- | ---------- | ---------------------------------------- |
| TASK-033.1.1 | Validar contrato MiniMax (ID/model/pricing/limits) | @ARCHITECT | nenhuma                                  |
| TASK-033.2.1 | Backend enums + migration MiniMax                  | @BACKEND   | TASK-033.1.1                             |
| TASK-033.2.2 | Seeder/factory/testes backend MiniMax              | @BACKEND   | TASK-033.2.1                             |
| TASK-033.3.1 | Config MiniMax + circuit breaker                   | @DEV       | TASK-033.1.1                             |
| TASK-033.3.2 | Adapter/translator/module MiniMax                  | @DEV       | TASK-033.3.1                             |
| TASK-033.3.3 | Factory wiring + testes gateway                    | @DEV       | TASK-033.3.2                             |
| TASK-033.4.1 | Frontend catálogo unificado + option MiniMax       | @FRONTEND  | TASK-033.3.2                             |
| TASK-033.5.1 | Gates + QA + Review + Confirm                      | @QA        | TASK-033.2.2, TASK-033.3.3, TASK-033.4.1 |

## Especificação técnica preliminar MiniMax 2.7

> Observacao: os campos abaixo devem ser confirmados contra documentacao oficial MiniMax na fase de Review antes da migration definitiva.

| Propriedade          | Valor planejado                                   |
| -------------------- | ------------------------------------------------- |
| `provider`           | `minimax`                                         |
| `model_name`         | `minimax-2.7` (ou ID canônico validado)           |
| `display_name`       | `MiniMax 2.7`                                     |
| `input_cost_per_1m`  | a validar                                         |
| `output_cost_per_1m` | a validar                                         |
| `max_context_tokens` | a validar                                         |
| `max_output_tokens`  | a validar                                         |
| `is_active`          | `true`                                            |
| `notes`              | Confirmar ID e pricing oficial antes de persistir |

## Decisão de arquitetura

MiniMax entra como **novo provider** (não apenas novo modelo) por não compartilhar a mesma fronteira de provider Google/OpenAI no código atual. O padrão seguirá Ports and Adapters:

```
AIProvider (interface)
├── OpenAIProviderAdapter
├── GeminiProviderAdapter (google)
└── MiniMaxProviderAdapter (novo)
    ├── minimax.config.ts
    ├── minimax.translator.ts
    ├── minimax-provider.adapter.ts
    └── minimax.module.ts
```

## Riscos e dependências

### Riscos

| Risco                                                                                           | Probabilidade | Impacto | Mitigação                                                                      |
| ----------------------------------------------------------------------------------------------- | ------------- | ------- | ------------------------------------------------------------------------------ |
| ID de modelo MiniMax 2.7 divergir do nome comercial                                             | Alta          | Alto    | Validar em Review via documentação/API antes da migration                      |
| Divergência de provider naming entre camadas (ex.: `google` vs `gemini`) se repetir no MiniMax  | Media         | Alto    | Definir convenção unica: `provider=minimax` em todas as camadas                |
| Adapter MiniMax funcionar na completion direta, mas fluxo orquestrado seguir acoplado ao OpenAI | Alta          | Medio   | Cobrir cenário de run com testes e registrar follow-up explícito se necessário |
| Catálogo frontend permanecer duplicado e inconsistente                                          | Alta          | Medio   | Centralizar opções de modelo em arquivo único compartilhado                    |
| Preços/capacidade mudarem rapidamente no provider                                               | Media         | Baixo   | Manter tabela de pricing editável e documentar data de referência              |

### Dependências

- Credencial `MINIMAX_API_KEY` disponível no ambiente
- Contrato de autenticação e endpoint MiniMax confirmado
- Sincronia de nomenclatura `provider` entre API Laravel, Gateway e frontend

## Validação e Gates

- [ ] Backend: `cd /Users/rafael.silva/Documents/interazap/api && composer gate:all`
- [ ] Gateway: `cd /Users/rafael.silva/Documents/interazap/gateway && pnpm lint && pnpm test`
- [ ] Frontend: `cd /Users/rafael.silva/Documents/interazap/app && pnpm run gate:all`
- [ ] Migration: `cd /Users/rafael.silva/Documents/interazap/api && php artisan migrate`
- [ ] QA: revisão de qualidade sem blockers críticos
- [ ] REVIEWER: revisão de código sem blockers críticos

## Estimativa

| Item                          | Valor                                         |
| ----------------------------- | --------------------------------------------- |
| Complexidade                  | Média                                         |
| Camadas afetadas              | Backend / Gateway / Frontend                  |
| Migrações necessárias         | Sim (catálogo de pricing)                     |
| Impacto em módulos existentes | Médio (aditivo com ajustes em factory/config) |
