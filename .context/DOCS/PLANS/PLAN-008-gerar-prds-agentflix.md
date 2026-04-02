# PLAN-008 — Gerar PRDs para Módulos InteraZap

## Objetivo

Criar Product Requirements Documents (PRDs) completos com **mínimo 1000 linhas cada** para todos os módulos do InteraZap. Cada PRD será baseado exclusivamente no código existente, documentando a implementação real.

## Módulo relacionado

Todos os módulos — InteraZap Platform

## PRD relacionado

N/A — criação de novos PRDs

---

## Lista de PRDs a Gerar (14 total)

| #   | PRD ID               | Módulo                 | Prioridade  | Linhas Mínimas |
| --- | -------------------- | ---------------------- | ----------- | -------------- |
| 1   | `PRD-ARCH-001`       | Arquitetura do Sistema | 🔴 Alta     | 1500           |
| 2   | `PRD-AUTH-001`       | Auth                   | ✅ Aprovado | —              |
| 3   | `PRD-REPORTS-001`    | Reports                | 🔴 Alta     | 1000           |
| 4   | `PRD-DASHBOARD-001`  | Dashboard              | 🔴 Alta     | 1000           |
| 5   | `PRD-CHAT-001`       | Chat                   | 🔴 Alta     | 1000           |
| 6   | `PRD-BILLING-001`    | Billing                | 🔴 Alta     | 1000           |
| 7   | `PRD-AI-001`         | AI                     | 🔴 Alta     | 1000           |
| 8   | `PRD-CRM-001`        | CRM                    | 🔴 Alta     | 1000           |
| 9   | `PRD-GATEWAY-001`    | Gateway                | 🔴 Alta     | 1000           |
| 10  | `PRD-PLATFORM-001`   | Platform               | 🟡 Média    | 1000           |
| 11  | `PRD-CONFIG-001`     | Configuration          | 🟢 Baixa    | 1000           |
| 12  | `PRD-TENANTS-001`    | Tenants                | 🟡 Média    | 1000           |
| 13  | `PRD-UAZAPI-001`     | UAZAPI (WhatsApp)      | 🔴 Alta     | 1000           |
| 14  | `PRD-MONITORING-001` | Monitoring             | 🟡 Média    | 1000           |
| 15  | `PRD-KNOWLEDGE-001`  | Knowledge Base         | 🟡 Média    | 1000           |

---

## Estrutura Obrigatória do PRD (mínimo 1000 linhas)

Cada PRD deve conter **TODAS** as seguintes seções:

### 1. CONTEXTO (100+ linhas)

- Descrição do módulo
- Problema que resolve
- Valor de negócio
- Posição na arquitetura geral

### 2. OBJETIVO (50+ linhas)

- Meta do módulo
- Escopo funcional
- Limites do módulo

### 3. REGRAS DE NEGÓCIO (200+ linhas)

- RN-XXX com ID, descrição, prioridade
- Regras de validação
- Restrições técnicas

### 4. FLUXOS (300+ linhas)

- Fluxo principal com sequência de passos
- Fluxos alternativos
- Fluxos de erro
- Diagramas Mermaid

### 5. ENTIDADES E MODELOS (200+ linhas)

- Tabelas do banco
- Campos e tipos
- Relacionamentos
- Estados possíveis

### 6. ENDPOINTS (200+ linhas)

- Tabela com Método, Rota, Auth, Descrição
- Request/Response examples
- Códigos de erro

### 7. EVENTOS (100+ linhas)

- Eventos disparados
- Listeners
- Filas (Jobs)

### 8. SEGURANÇA (50+ linhas)

- Isolamento de tenant
- Validações
- Rate limiting

### 9. DTOs E RESOURCES (100+ linhas)

- Estrutura dos DTOs
- Validações
- Transformações

### 10. CRITÉRIOS DE ACEITAÇÃO (100+ linhas)

- Checklists verificáveis
- Condições de sucesso

---

## Tasks Derivadas

### TASK-PRD-ARCH

**Descrição:** Criar PRD-ARCH-001 — Arquitetura do Sistema
**PRD:** `PRD-ARCH-001`
**Mínimo de linhas:** 1500
**Agente:** @DOC
**Arquivos a consultar:**

- `CLAUDE.md`
- `.context/ARCHETECTURE/`
- `api/src/Domain/Shared/Services/GatewayBroadcastService.php`
- `gateway/src/domains/realtime/`
- `app/src/app/core/services/realtime.service.ts`

### TASK-PRD-REPORTS

**Descrição:** Criar PRD-REPORTS-001 — Módulo de Relatórios
**PRD:** `PRD-REPORTS-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Reports/`
- `app/src/app/pages/reports/`
- Feature docs: `docs/features/034-reports-module/`

### TASK-PRD-DASHBOARD

**Descrição:** Criar PRD-DASHBOARD-001 — Módulo de Dashboard
**PRD:** `PRD-DASHBOARD-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Dashboard/`
- `app/src/app/pages/dashboard/`

### TASK-PRD-CHAT

**Descrição:** Criar PRD-CHAT-001 — Módulo de Chat
**PRD:** `PRD-CHAT-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Chat/`
- `app/src/app/pages/chat/`
- `gateway/src/domains/chat/`

### TASK-PRD-BILLING

**Descrição:** Criar PRD-BILLING-001 — Módulo de Billing
**PRD:** `PRD-BILLING-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Billing/`
- `app/src/app/pages/billing/`

### TASK-PRD-AI

**Descrição:** Criar PRD-AI-001 — Módulo de AI
**PRD:** `PRD-AI-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Ai/`
- `app/src/app/pages/ai/`
- `gateway/src/domains/ai/`

### TASK-PRD-CRM

**Descrição:** Criar PRD-CRM-001 — Módulo de CRM
**PRD:** `PRD-CRM-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/CRM/`
- `app/src/app/pages/crm/`

### TASK-PRD-GATEWAY

**Descrição:** Criar PRD-GATEWAY-001 — Módulo Gateway
**PRD:** `PRD-GATEWAY-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `gateway/src/domains/realtime/`
- `gateway/src/infrastructure/redis/`
- `api/src/Domain/Shared/Services/GatewayBroadcastService.php`

### TASK-PRD-PLATFORM

**Descrição:** Criar PRD-PLATFORM-001 — Módulo de Platform
**PRD:** `PRD-PLATFORM-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Platform/`
- `app/src/app/pages/platform/`

### TASK-PRD-CONFIG

**Descrição:** Criar PRD-CONFIG-001 — Módulo de Configuration
**PRD:** `PRD-CONFIG-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Configuration/`
- `app/src/app/pages/configuration/`

### TASK-PRD-TENANTS

**Descrição:** Criar PRD-TENANTS-001 — Módulo de Tenants
**PRD:** `PRD-TENANTS-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Platform/Models/PlatformTenant.php`
- `api/src/Domain/Auth/Models/AuthUser.php` (relação com tenant)

### TASK-PRD-UAZAPI

**Descrição:** Criar PRD-UAZAPI-001 — Módulo UAZAPI
**PRD:** `PRD-UAZAPI-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Platform/`
- `gateway/src/domains/chat/`
- Chat webhooks

### TASK-PRD-MONITORING

**Descrição:** Criar PRD-MONITORING-001 — Módulo de Monitoring
**PRD:** `PRD-MONITORING-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Shared/Services/HealthCheckService.php`
- `api/src/Domain/Shared/Services/MetricsService.php`
- `gateway/src/health/`

### TASK-PRD-KNOWLEDGE

**Descrição:** Criar PRD-KNOWLEDGE-001 — Módulo de Knowledge Base
**PRD:** `PRD-KNOWLEDGE-001`
**Mínimo de linhas:** 1000
**Agente:** @DOC
**Arquivos a consultar:**

- `api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php`
- Feature docs relacionadas

---

## Metodologia de Geração

### Passo 1: Leitura do Código Fonte

Para cada módulo:

1. Listar todos os controllers e seus métodos
2. Listar todos os models e seus campos
3. Listar todas as actions
4. Listar todos os eventos e listeners
5. Listar todos os jobs
6. Mapear dependências entre módulos

### Passo 2: Análise de Regras de Negócio

1. Identificar validações nos FormRequests
2. Identificar scopes nos models
3. Identificar policies e autorizações
4. Mapear eventos disparados

### Passo 3: Documentação

1. Escrever contexto baseado no código real
2. Documentar fluxos seguindo a sequência código
3. Incluir exemplos de request/response
4. Adicionar diagramas Mermaid

### Passo 4: Validação

1. Verificar que todos os endpoints estão documentados
2. Verificar que todas as entidades estão descritas
3. Verificar consistência com o código

---

## Estimativa

| Item                  | Valor                     |
| --------------------- | ------------------------- |
| Complexidade          | Alta (15 PRDs)            |
| PRDs a criar          | 14 (AUTH já existe)       |
| Linhas totais mínimas | 14.000                    |
| Tempo estimado        | ~30 dias (2 dias por PRD) |

---

## Validação e Gates

- [ ] Cada PRD tem mínimo 1000 linhas
- [ ] Codebase consultada para cada módulo
- [ ] Endpoints e eventos documentados
- [ ] Critérios de aceitação verificáveis
- [ ] Diagramas Mermaid incluídos
- [ ] PRD-ARCH-001 criado primeiro (dependência)

---

## Arquivos a Criar

| Arquivo                 | Módulo         | Linhas Mínimas |
| ----------------------- | -------------- | -------------- |
| `PRD-ARCH-001.md`       | Arquitetura    | 1500           |
| `PRD-REPORTS-001.md`    | Reports        | 1000           |
| `PRD-DASHBOARD-001.md`  | Dashboard      | 1000           |
| `PRD-CHAT-001.md`       | Chat           | 1000           |
| `PRD-BILLING-001.md`    | Billing        | 1000           |
| `PRD-AI-001.md`         | AI             | 1000           |
| `PRD-CRM-001.md`        | CRM            | 1000           |
| `PRD-GATEWAY-001.md`    | Gateway        | 1000           |
| `PRD-PLATFORM-001.md`   | Platform       | 1000           |
| `PRD-CONFIG-001.md`     | Configuration  | 1000           |
| `PRD-TENANTS-001.md`    | Tenants        | 1000           |
| `PRD-UAZAPI-001.md`     | UAZAPI         | 1000           |
| `PRD-MONITORING-001.md` | Monitoring     | 1000           |
| `PRD-KNOWLEDGE-001.md`  | Knowledge Base | 1000           |

---

## Ordem de Execução

1. **PRD-ARCH-001** (primeiro — define a arquitetura)
2. **PRD-AUTH-001** (já existe, apenas verificar)
3. **PRD-CHAT-001** (módulo central)
4. **PRD-BILLING-001** (depende de CHAT para contexto)
5. **PRD-AI-001** (integrado com CHAT)
6. **PRD-CRM-001** (independente)
7. **PRD-GATEWAY-001** (suporte a CHAT e AI)
8. **PRD-REPORTS-001** (depende de CRM e CHAT)
9. **PRD-DASHBOARD-001** (depende de todos)
10. **PRD-PLATFORM-001** (gestão de tenants)
11. **PRD-CONFIG-001** (configurações)
12. **PRD-TENANTS-001** (substituto de Platform para tenants)
13. **PRD-UAZAPI-001** (instâncias WhatsApp)
14. **PRD-MONITORING-001** (observabilidade)
15. **PRD-KNOWLEDGE-001** (base de conhecimento AI)
