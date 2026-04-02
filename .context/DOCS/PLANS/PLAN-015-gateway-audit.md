# PLAN-015-gateway-audit — Gateway Code Audit

## Objetivo

Realizar auditoria técnica completa do código fonte do gateway NestJS (`gateway/src/`) em 5 dimensões: (1) Código para reutilização, (2) Erros e bugs, (3) Código morto, (4) Oportunidades de refatoração, (5) Segurança. Resultado: relatório priorizado com 75+ achados categorizados por severidade.

## Módulo relacionado

**Gateway** — NestJS 11 (`gateway/src/`)

## PRD relacionado

N/A — tarefa de auditoria de código existente.

## Escopo

### Incluído

- 100% dos arquivos `.ts` de produção em `gateway/src/` (223 arquivos — 173 diretamente auditados)
- Todos os domínios: `ai`, `billing`, `chat`, `internal`, `realtime`, `webhooks`
- Todas as camadas: `common/`, `core/`, `shared/`, `infrastructure/`, `health/`, `metrics/`
- Arquivos `.ts` de produção — **excluídos** `.spec.ts`, `main.ts`, `test-utils/`

### Excluído

- Análise de arquivos de teste (`.spec.ts`)
- Análise de configuração (`tsconfig.json`, `nest-cli.json`, `package.json`)
- Análise de migrations de banco de dados

## Etapas propostas

1. **Inventário completo** — mapear todos os 173 arquivos `.ts`
2. **Análise Paralela por Domínio** — 4 agentes simultâneos cobrindo todos os domínios
3. **Consolidação de achados** — deduplicar, categorizar, priorizar
4. **Geração do relatório** — formato estruturado com métricas e roadmap
5. **Validação do relatório** — revisão por @REVIEWER e @QA

## Tasks derivadas

| Task             | Descrição                                                         | Agente   | Status  |
| ---------------- | ----------------------------------------------------------------- | -------- | ------- |
| TASK-GW-AUDIT-01 | Auditoria common/core/shared layers                               | Explore  | done    |
| TASK-GW-AUDIT-02 | Auditoria domain AI                                               | Explore  | done    |
| TASK-GW-AUDIT-03 | Auditoria domain Chat                                             | Explore  | done    |
| TASK-GW-AUDIT-04 | Auditoria domains Billing/Realtime/Health/Infrastructure/Webhooks | Explore  | done    |
| TASK-GW-AUDIT-05 | Consolidar relatório final                                        | DEV      | pending |
| TASK-GW-AUDIT-06 | Revisão do relatório por @REVIEWER                                | REVIEWER | pending |

## Riscos e dependências

### Riscos

| Risco                             | Probabilidade | Impacto | Mitigação                                    |
| --------------------------------- | ------------- | ------- | -------------------------------------------- |
| Falsos positivos nos achados      | Baixa         | Média   | Validação com arquivo + linha para cada item |
| Escopo muito amplo (173 arquivos) | Alta          | Baixa   | Análise paralela com 4 agentes               |

### Dependências

- Nenhuma dependência externa — auditoria de código existente

## Estimativa

| Item                          | Valor                           |
| ----------------------------- | ------------------------------- |
| Complexidade                  | Alta                            |
| Camadas afetadas              | Gateway (única camada)          |
| Migrações necessárias         | Não                             |
| Impacto em módulos existentes | Não (relatório apenas)          |
| Arquivos analisados           | 223 (173 diretamente auditados) |
| Total de achados              | 75+                             |

## Métricas do Audit (output esperado)

| Severidade | Contagem | Sprint   |
| ---------- | -------- | -------- |
| CRITICAL   | 4        | Sprint 1 |
| HIGH       | 16       | Sprint 2 |
| MEDIUM     | 22       | Sprint 3 |
| LOW        | 33       | Sprint 4 |
| **TOTAL**  | **75+**  | —        |
