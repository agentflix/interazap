# PLAN-001 — Documentar Módulos Existentes com PRDs

## Objetivo

Criar PRDs para todos os 11 módulos de domínio do InteraZap, estabelecendo a documentação funcional como fonte de verdade para requisitos de produto e regras de negócio. Cada PRD documentará o estado atual do módulo (as-is), servindo como baseline para evolução futura.

## Módulo relacionado

Todos: Auth | Ai | Billing | Chat | Configuration | CRM | Dashboard | Gateway | Platform | Reports | Shared

## PRD relacionado: N/A (este plano GERA os PRDs)

## Escopo

### Incluído

- Análise do código-fonte de cada módulo (routes, controllers, models, policies, tests)
- Documentação de regras de negócio implementadas
- Mapeamento de fluxos e estados existentes
- Criação de 1 PRD por módulo seguindo o template padrão
- Definição de critérios de aceitação verificáveis
- Registro de contratos de segurança e padrões explícitos

### Excluído

- Implementação de novas funcionalidades
- Refatoração de código existente
- Criação de testes (isso será escopo das tasks de implementação)
- Documentação de funcionalidades planejadas mas não implementadas

## Etapas propostas

1. **[DONE]** PRD-AUTH-001: Autenticação e Multi-Tenancy
2. PRD-CHAT-001: Conversas WhatsApp e Mensageria
3. PRD-CRM-001: Gestão de Contatos e Pipeline
4. PRD-BILLING-001: Cobrança e Assinaturas
5. PRD-AI-001: Autopilot e Base de Conhecimento
6. PRD-DASHBOARD-001: Analytics e Métricas
7. PRD-CONFIGURATION-001: Configurações do Sistema
8. PRD-PLATFORM-001: Multi-tenancy e Onboarding
9. PRD-GATEWAY-001: API Gateway e Webhooks
10. PRD-REPORTS-001: Relatórios e Exportação
11. PRD-SHARED-001: Utilitários Compartilhados

## Tasks derivadas

| Task     | Descrição                                    | Agente | Status |
| -------- | -------------------------------------------- | ------ | ------ |
| TASK-001 | PRD Auth — Autenticação e Multi-Tenancy      | PM     | done   |
| TASK-002 | PRD Chat — Conversas WhatsApp e Mensageria   | PM     | todo   |
| TASK-003 | PRD CRM — Gestão de Contatos e Pipeline      | PM     | todo   |
| TASK-004 | PRD Billing — Cobrança e Assinaturas         | PM     | todo   |
| TASK-005 | PRD AI — Autopilot e Base de Conhecimento    | PM     | todo   |
| TASK-006 | PRD Dashboard — Analytics e Métricas         | PM     | todo   |
| TASK-007 | PRD Configuration — Configurações do Sistema | PM     | todo   |
| TASK-008 | PRD Platform — Multi-tenancy e Onboarding    | PM     | todo   |
| TASK-009 | PRD Gateway — API Gateway e Webhooks         | PM     | todo   |
| TASK-010 | PRD Reports — Relatórios e Exportação        | PM     | todo   |
| TASK-011 | PRD Shared — Utilitários Compartilhados      | PM     | todo   |

## Riscos e dependências

### Riscos

| Risco                                                                                   | Probabilidade | Impacto | Mitigação                                                                           |
| --------------------------------------------------------------------------------------- | ------------- | ------- | ----------------------------------------------------------------------------------- |
| Funcionalidades implementadas não documentadas no código (lógica implícita)             | Alta          | Médio   | Analisar routes, controllers, actions e tests para mapear funcionalidades completas |
| Regras de negócio divergentes entre frontend e backend                                  | Média         | Alto    | Cross-check entre Angular services/guards e Laravel policies/middleware             |
| Módulos com baixa cobertura de testes dificultam mapeamento de comportamentos esperados | Média         | Médio   | Priorizar análise de routes + FormRequests como fonte primária                      |
| PRDs ficarem desatualizados com evolução do código                                      | Alta          | Alto    | Vincular PRDs ao ciclo de desenvolvimento — todo feature deve atualizar PRD         |

### Dependências

- Acesso ao código-fonte de todos os módulos em `api/src/Domain/`
- Acesso às rotas em `api/src/Domain/*/Routes/`
- Acesso aos componentes Angular em `app/src/app/pages/`
- Módulo Auth (PLAN etapa 1) deve ser documentado primeiro — é dependência de todos os outros

## Estimativa

- 11 PRDs no total
- Ordem de prioridade: Auth → Chat → CRM → Billing → AI → Dashboard → Configuration → Platform → Gateway → Reports → Shared
- Auth já concluído como referência/template para os demais
