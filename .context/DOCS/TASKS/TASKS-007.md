# TASKS-007 — Gerar PRDs AgentFlix

> Gerar 14 Product Requirements Documents (PRDs) com mínimo 1000 linhas cada.

---

## TASK-PRD-ARCH

**Arquivo:** `TASKS-007-PRD-ARCH.md`
**PRD:** `PRD-ARCH-001`
**Módulo:** Arquitetura
**Mínimo de linhas:** 1500
**Status:** todo
**Agent:** @DOC

### Descrição

Criar o PRD de Arquitetura do Sistema AgentFlix documentando:

1. **Visão Geral da Arquitetura**
   - Stack tecnológica completa
   - Diagrama de componentes
   - Fluxo de dados App → Backend → Gateway → Redis

2. **Comunicação entre Camadas**
   - REST API (Laravel → Angular)
   - WebSocket (Socket.io)
   - Redis PubSub/Streams
   - Filas (BullMQ)

3. **Módulos e Dependências**
   - Diagrama de módulos
   - Dependências circulares
   - Contratos entre módulos

4. **Segurança**
   - Autenticação (Sanctum)
   - Tenant isolation
   - Rate limiting

5. **Infraestrutura**
   - Docker Compose
   - VPS (186.202.209.180)
   - Nginx/SSL

### Locais para Consultar
- `CLAUDE.md` — stack e convenções
- `.context/ARCHETECTURE/` — diagramas existentes
- `api/src/Domain/Shared/Services/GatewayBroadcastService.php`
- `gateway/src/domains/realtime/`
- `app/src/app/core/services/realtime.service.ts`

---

## TASK-PRD-REPORTS

**Arquivo:** `TASKS-007-PRD-REPORTS.md`
**PRD:** `PRD-REPORTS-001`
**Módulo:** Reports
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-REPORTS-001 documentando o módulo de relatórios.

### Locais para Consultar
- `api/src/Domain/Reports/`
- `app/src/app/pages/reports/`
- `docs/features/034-reports-module/`

---

## TASK-PRD-DASHBOARD

**Arquivo:** `TASKS-007-PRD-DASHBOARD.md`
**PRD:** `PRD-DASHBOARD-001`
**Módulo:** Dashboard
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-DASHBOARD-001 documentando o módulo de dashboard.

### Locais para Consultar
- `api/src/Domain/Dashboard/`
- `app/src/app/pages/dashboard/`

---

## TASK-PRD-CHAT

**Arquivo:** `TASKS-007-PRD-CHAT.md`
**PRD:** `PRD-CHAT-001`
**Módulo:** Chat
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-CHAT-001 documentando o módulo de chat.

### Locais para Consultar
- `api/src/Domain/Chat/`
- `app/src/app/pages/chat/`
- `gateway/src/domains/chat/`

---

## TASK-PRD-BILLING

**Arquivo:** `TASKS-007-PRD-BILLING.md`
**PRD:** `PRD-BILLING-001`
**Módulo:** Billing
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-BILLING-001 documentando o módulo de billing.

### Locais para Consultar
- `api/src/Domain/Billing/`
- `app/src/app/pages/billing/`

---

## TASK-PRD-AI

**Arquivo:** `TASKS-007-PRD-AI.md`
**PRD:** `PRD-AI-001`
**Módulo:** AI
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-AI-001 documentando o módulo de AI.

### Locais para Consultar
- `api/src/Domain/Ai/`
- `app/src/app/pages/ai/`
- `gateway/src/domains/ai/`

---

## TASK-PRD-CRM

**Arquivo:** `TASKS-007-PRD-CRM.md`
**PRD:** `PRD-CRM-001`
**Módulo:** CRM
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-CRM-001 documentando o módulo de CRM.

### Locais para Consultar
- `api/src/Domain/CRM/`
- `app/src/app/pages/crm/`

---

## TASK-PRD-GATEWAY

**Arquivo:** `TASKS-007-PRD-GATEWAY.md`
**PRD:** `PRD-GATEWAY-001`
**Módulo:** Gateway
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-GATEWAY-001 documentando o módulo Gateway.

### Locais para Consultar
- `gateway/src/domains/realtime/`
- `gateway/src/infrastructure/redis/`
- `api/src/Domain/Shared/Services/GatewayBroadcastService.php`

---

## TASK-PRD-PLATFORM

**Arquivo:** `TASKS-007-PRD-PLATFORM.md`
**PRD:** `PRD-PLATFORM-001`
**Módulo:** Platform
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-PLATFORM-001 documentando o módulo de Platform.

### Locais para Consultar
- `api/src/Domain/Platform/`
- `app/src/app/pages/platform/`

---

## TASK-PRD-CONFIG

**Arquivo:** `TASKS-007-PRD-CONFIG.md`
**PRD:** `PRD-CONFIG-001`
**Módulo:** Configuration
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-CONFIG-001 documentando o módulo de Configuration.

### Locais para Consultar
- `api/src/Domain/Configuration/`
- `app/src/app/pages/configuration/`

---

## TASK-PRD-TENANTS

**Arquivo:** `TASKS-007-PRD-TENANTS.md`
**PRD:** `PRD-TENANTS-001`
**Módulo:** Tenants
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-TENANTS-001 documentando o módulo de Tenants.

### Locais para Consultar
- `api/src/Domain/Platform/Models/PlatformTenant.php`
- `api/src/Domain/Auth/Models/AuthUser.php`

---

## TASK-PRD-UAZAPI

**Arquivo:** `TASKS-007-PRD-UAZAPI.md`
**PRD:** `PRD-UAZAPI-001`
**Módulo:** UAZAPI
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-UAZAPI-001 documentando o módulo UAZAPI de instâncias WhatsApp.

### Locais para Consultar
- `api/src/Domain/Platform/`
- `gateway/src/domains/chat/`

---

## TASK-PRD-MONITORING

**Arquivo:** `TASKS-007-PRD-MONITORING.md`
**PRD:** `PRD-MONITORING-001`
**Módulo:** Monitoring
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-MONITORING-001 documentando o módulo de Monitoring.

### Locais para Consultar
- `api/src/Domain/Shared/Services/HealthCheckService.php`
- `api/src/Domain/Shared/Services/MetricsService.php`
- `gateway/src/health/`

---

## TASK-PRD-KNOWLEDGE

**Arquivo:** `TASKS-007-PRD-KNOWLEDGE.md`
**PRD:** `PRD-KNOWLEDGE-001`
**Módulo:** Knowledge Base
**Mínimo de linhas:** 1000
**Status:** todo
**Agent:** @DOC

### Descrição

Criar PRD-KNOWLEDGE-001 documentando o módulo de Base de Conhecimento.

### Locais para Consultar
- `api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php`

---

## Resumo

| Task | PRD | Linhas | Status | Commit |
|------|-----|--------|--------|---------|
| TASK-PRD-ARCH | PRD-ARCH-001 | 2,258 | ✅ done | 2d60e7847 |
| TASK-PRD-REPORTS | PRD-REPORTS-001 | 1,191 | ✅ done | 2d60e7847 |
| TASK-PRD-DASHBOARD | PRD-DASHBOARD-001 | 1,400 | ✅ done | 2d60e7847 |
| TASK-PRD-CHAT | PRD-CHAT-001 | 1,891 | ✅ done | 2d60e7847 |
| TASK-PRD-BILLING | PRD-BILLING-001 | 1,501 | ✅ done | 2d60e7847 |
| TASK-PRD-AI | PRD-AI-001 | 2,905 | ✅ done | 2d60e7847 |
| TASK-PRD-CRM | PRD-CRM-001 | 1,690 | ✅ done | 2d60e7847 |
| TASK-PRD-GATEWAY | PRD-GATEWAY-001 | 1,830 | ✅ done | 2d60e7847 |
| TASK-PRD-PLATFORM | PRD-PLATFORM-001 | 1,996 | ✅ done | 2d60e7847 |
| TASK-PRD-CONFIG | PRD-CONFIG-001 | 2,176 | ✅ done | 2d60e7847 |
| TASK-PRD-TENANTS | PRD-TENANTS-001 | 1,810 | ✅ done | 2d60e7847 |
| TASK-PRD-UAZAPI | PRD-UAZAPI-001 | 1,929 | ✅ done | a82f7c786 |
| TASK-PRD-MONITORING | PRD-MONITORING-001 | 2,339 | ✅ done | c66b111b8 |
| TASK-PRD-KNOWLEDGE | PRD-KNOWLEDGE-001 | 1,671 | ✅ done | c66b111b8 |

**Total:** 14 PRDs, 14 ✅ completos (~29.000 linhas), 0 🔄 pendentes
