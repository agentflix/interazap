# PLAN-013-api-audit — API Code Audit

## Objetivo

Realizar auditoria técnica completa do código fonte da API Laravel 12 (`api/src/`) em 5 dimensões: (1) Código para reutilização, (2) Oportunidades de refatoração, (3) Bugs e erros, (4) Código morto, (5) Anti-patterns de performance. Resultado: relatório priorizado com 80+ achados categorizados por severidade.

## Módulo relacionado

**API** — Laravel 12 / PHP 8.3 (`api/src/Domain/`)

## PRD relacionado

N/A — tarefa de auditoria de código existente.

## Escopo

### Incluído

- 879 arquivos PHP de produção em `api/src/Domain/` (11 domínios)
- Todos os domínios: `Ai`, `Auth`, `Billing`, `Chat`, `Configuration`, `CRM`, `Dashboard`, `Gateway`, `Platform`, `Reports`, `Shared`
- Artefatos auditados:
    - 76 Controllers
    - 60 Actions
    - 82 Models
    - 49 Services
    - 125 Form Requests
    - 63 API Resources
    - 13 Route files
    - Policies, Listeners, Jobs, Events, Contracts
- Artefatos **excluídos**: `bootstrap/`, `config/`, `database/`, `routes/` (exceto api.php), `storage/`, `tests/`, `vendor/`

### Excluído

- Análise de arquivos de teste (`.spec.php`, `tests/`)
- Análise de migrations de banco de dados
- Análise de configuração (`config/`)
- Análise de `bootstrap/` e `vendor/`

## Etapas propostas

1. **Inventário completo** — mapear os 879 arquivos e entender estrutura
2. **Análise Paralela por Domínio** — 5 agentes simultâneos cobrindo todos os domínios
3. **Consolidação de achados** — deduplicar, categorizar, priorizar
4. **Geração do relatório** — formato estruturado com métricas e roadmap
5. **Validação do relatório** — revisão por @REVIEWER e @QA

## Tasks derivadas

| Task              | Descrição                                                     | Agente   | Status  |
| ----------------- | ------------------------------------------------------------- | -------- | ------- |
| TASK-API-AUDIT-01 | Auditoria Auth + Shared (policies, middleware, services)      | Explore  | pending |
| TASK-API-AUDIT-02 | Auditoria CRM + Chat (models, services, actions, controllers) | Explore  | pending |
| TASK-API-AUDIT-03 | Auditoria Billing + Platform + Reports                        | Explore  | pending |
| TASK-API-AUDIT-04 | Auditoria AI + Configuration (DTOs, tools, events)            | Explore  | pending |
| TASK-API-AUDIT-05 | Auditoria Routes + Dashboard + Gateways + Middleware          | Explore  | pending |
| TASK-API-AUDIT-06 | Consolidar relatório final + priorizar                        | DEV      | pending |
| TASK-API-AUDIT-07 | Revisão do relatório por @REVIEWER                            | REVIEWER | pending |

## Riscos e dependências

### Riscos

| Risco                                 | Probabilidade | Impacto | Mitigação                                      |
| ------------------------------------- | ------------- | ------- | ---------------------------------------------- |
| Escopo muito amplo (879 arquivos)     | Alta          | Baixa   | Análise paralela com 5 agentes                 |
| Falsos positivos nos achados          | Média         | Média   | Validação com arquivo + linha para cada item   |
| Agentes retornando achados duplicados | Alta          | Baixa   | Consolidador deduplica por arquivo + categoria |

### Dependências

- Nenhuma dependência externa — auditoria de código existente

## Estimativa

| Item                          | Valor                  |
| ----------------------------- | ---------------------- |
| Complexidade                  | Crítica                |
| Camadas afetadas              | API (única camada)     |
| Migrações necessárias         | Não                    |
| Impacto em módulos existentes | Não (relatório apenas) |
| Arquivos analisados           | 879                    |
| Controllers                   | 76                     |
| Actions                       | 60                     |
| Models                        | 82                     |
| Services                      | 49                     |
| Form Requests                 | 125                    |
| API Resources                 | 63                     |
| Route files                   | 13                     |
| Total de achados estimado     | 80+                    |

## Métricas do Audit (output esperado)

| Severidade | Estimativa | Sprint   |
| ---------- | ---------- | -------- |
| CRITICAL   | 4-6        | Sprint 1 |
| HIGH       | 18-22      | Sprint 2 |
| MEDIUM     | 25-30      | Sprint 3 |
| LOW        | 30-35      | Sprint 4 |
| **TOTAL**  | **80+**    | —        |
