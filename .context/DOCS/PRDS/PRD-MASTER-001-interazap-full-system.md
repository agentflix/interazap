# PRD: InteraZap — Sistema Completo para Migração Looveable

> Product Requirements Document — Requisitos Completos do Sistema InteraZap
> Este documento serve como referência única para recriação integral do sistema no Looveable.

---

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Design System](#2-design-system)
3. [Arquitetura Técnica](#3-arquitetura-técnica)
4. [Módulos e Funcionalidades](#4-módulos-e-funcionalidades)
5. [Stack de IA](#5-stack-de-ia)
6. [Canais de Chat](#6-canais-de-chat)
7. [Modelo de Dados](#7-modelo-de-dados)
8. [API Endpoints](#8-api-endpoints)
9. [Requisitos Funcionais](#9-requisitos-funcionais)
10. [Requisitos Não-Funcionais](#10-requisitos-não-funcionais)
11. [PRDs por Módulo](#11-prds-por-módulo)
12. [Referências Cruzadas](#12-referências-cruzadas)

---

## 1. Visão Geral

| Campo | Descrição |
|-------|-----------|
| **Nome** | InteraZap |
| **Versão** | 2.0.0 |
| **Data** | 2026-04-15 |
| **Autor** | Product Team |
| **Status** | Aprovado |
| **Stack Original** | Laravel 12 + Angular 20 + PostgreSQL 17 + NestJS Gateway |
| **Stack Target** | Looveable (reconstrução completa) |
| **Bounded Contexts** | Auth, Chat, CRM, AI, Billing, Dashboard, Platform, Gateway, Reports, Configuration |

### Resumo Executivo

InteraZap é uma **plataforma de comunicação multicanal com IA integrada**, permitindo que empresas gerenciem conversas de WhatsApp, Telegram e WebChat em um único lugar, com automação inteligente via agentes de IA e CRM completo para gestão de vendas e suporte.

### Objetivo do Documento

Documentar **TODO o sistema** (cores, layout, menus, módulos, RF/RNF) para que a equipe do Looveable consiga reconstruir o InteraZap integralmente, seguindo os mesmos padrões de design, arquitetura e funcionalidades.

---

## 2. Design System

### 2.1 Paleta de Cores

#### Cores Primárias (Teal)

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-primary-50` | `#f0fdfa` | Backgrounds muito leves |
| `--color-primary-100` | `#ccfbf1` | Backgrounds leves |
| `--color-primary-200` | `#99f6e4` | Borders destacados |
| `--color-primary-300` | `#5eead4` | Hovers |
| `--color-primary-400` | `#2dd4bf` | Acentos |
| `--color-primary-500` | `#14b8a6` | Cor principal (brand) |
| `--color-primary-600` | `#0d9488` | Hover em elementos primários |
| `--color-primary-700` | `#245044` | Textos em backgrounds escuros |
| `--color-primary-800` | `#115e59` | Backgrounds escuros |
| `--color-primary-900` | `#1a3c34` | Textos muito escuros |
| `--color-primary-950` | `#042f2e` | Background mais escuro |

#### Cores de Acento (Green)

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-accent-500` | `#22c55e` | Sucesso, confirmações |
| `--color-accent-600` | `#16a34a` | Hover em elementos de sucesso |

#### Cores Semânticas

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-success` | `#22c55e` | Operações bem-sucedidas |
| `--color-danger` | `#ef4444` | Erros, exclusões |
| `--color-warning` | `#f59e0b` | Avisos |
| `--color-info` | `#0ea5e9` | Informações |
| `--color-neutral-50` | `#fafafa` | Background padrão |
| `--color-neutral-900` | `#18181b` | Textos principais |
| `--color-neutral-950` | `#09090b` | Textos mais escuros |

#### Cores de Superfície

| Token | Hex | Uso |
|-------|-----|-----|
| `--color-surface-50` | `#f4f4f5` | Cards, painéis |
| `--color-surface-100` | `#e4e4e7` | Bordas sutis |
| `--color-surface-200` | `#d4d4d8` | Bordas visíveis |
| `--color-surface-300` | `#a1a1aa` | Placeholders |

### 2.2 Tipografia

```css
--font-sans: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
--font-mono: 'JetBrains Mono', ui-monospace, monospace;

/* Escala */
--text-xs: 0.75rem;    /* 12px */
--text-sm: 0.875rem;   /* 14px */
--text-base: 1rem;     /* 16px */
--text-lg: 1.125rem;   /* 18px */
--text-xl: 1.25rem;    /* 20px */
--text-2xl: 1.5rem;    /* 24px */
--text-3xl: 1.875rem;  /* 30px */
--text-4xl: 2.25rem;   /* 36px */

/* Font Weights */
--font-normal: 400;
--font-medium: 500;
--font-semibold: 600;
--font-bold: 700;
```

### 2.3 Espaçamento

```css
/* Height padrão de componentes */
--spacing-topbar-height: 70px;
--spacing-sidenav-width: 260px;
--spacing-sidenav-width-sm: 68px;

/* Border Radius */
--radius-sm: 2px;
--radius-md: 4px;
--radius-lg: 6px;
--radius-xl: 8px;
--radius-2xl: 12px;
--radius-full: 9999px;

/* Shadows */
--shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
```

### 2.4 Transições

```css
--transition-fast: 150ms ease;
--transition-normal: 200ms ease;
--transition-slow: 300ms ease;
```

---

## 3. Arquitetura Técnica

### 3.1 Stack Completa

| Camada | Tecnologia | Versão | Propósito |
|--------|------------|--------|-----------|
| **Frontend** | Angular + TypeScript + Tailwind CSS | 20 / 5.9 | SPA responsiva |
| **Gateway** | NestJS + TypeScript + BullMQ + Socket.io | 11 / 5.7 | Relay API, WebSocket, Workers |
| **Backend** | Laravel + PHP + Sanctum + Spatie | 12 / 8.3 | API REST, lógica DDD |
| **Database** | PostgreSQL + pgvector | 17 | Dados + embeddings vetoriais |
| **Cache/Fila** | Redis (Streams, PubSub, Cache) | 7 | Cache, filas, pub/sub |
| **Queue BE** | Laravel Horizon | 5.x | Monitoramento filas PHP |
| **Queue GW** | BullMQ | 5.x | Filas assíncronas Gateway |
| **WebSocket** | Socket.io (Gateway) + Laravel Reverb | - | Tempo real |
| **AI** | OpenAI API (GPT-4o, embeddings) | - | Completions, RAG |
| **Pagamentos** | Asaas API | - | Cobranças, assinaturas |
| **WhatsApp** | Z-API + UazAPI + Meta | - | Mensagens WhatsApp |
| **Storage** | AWS S3 | - | Uploads, avatares |
| **Monitoramento** | Prometheus + Grafana + Sentry | - | Métricas e erros |

### 3.2 Arquitetura de Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                     FRONTEND (Angular 20)                       │
│  SPA responsiva com 35+ componentes compartilhados               │
└──────────────────────────────┬──────────────────────────────────┘
                               │ HTTPS + Auth Bearer + Socket.io
┌──────────────────────────────▼──────────────────────────────────┐
│               GATEWAY (NestJS 11) :3000                         │
│  Relay API + WebSocket Server + BullMQ Workers                  │
│  - EventsGateway (Socket.io rooms por tenant)                   │
│  - EventFanoutService (Redis PubSub)                            │
│  - Providers: uazapi, zapi, meta                               │
└──────────────────────────────┬──────────────────────────────────┘
                               │ HTTP + Redis PubSub
┌──────────────────────────────▼──────────────────────────────────┐
│                 BACKEND (Laravel 12) :8000                     │
│  API REST + DDD Domain Modules                                 │
└──────────────────────────────┬──────────────────────────────────┘
                               │ PostgreSQL + Redis
┌──────────────────────────────▼──────────────────────────────────┐
│                    DATA LAYER                                  │
│  PostgreSQL 17 + pgvector (embeddings)                        │
│  Redis 7 (cache, filas, pub/sub)                              │
└─────────────────────────────────────────────────────────────────┘
```

### 3.3 Módulos DDD e Dependências

| ID | Módulo | Path | Descrição | Dependências |
|----|--------|------|-----------|--------------|
| MOD-001 | **Ai** | `api/src/Domain/Ai/` | Embeddings, RAG, completions, autopilot | Auth, Chat, CRM, Shared |
| MOD-002 | **Auth** | `api/src/Domain/Auth/` | Sanctum tokens, RBAC, 2FA | Shared |
| MOD-003 | **Billing** | `api/src/Domain/Billing/` | Asaas: cobranças, assinaturas | Auth, Platform, Shared |
| MOD-004 | **Chat** | `api/src/Domain/Chat/` | WhatsApp, tickets, mensagens | Auth, CRM, Gateway, Shared |
| MOD-005 | **CRM** | `api/src/Domain/CRM/` | Contatos, empresas, deals, pipelines | Auth, Shared |
| MOD-006 | **Configuration** | `api/src/Domain/Configuration/` | Configurações por tenant | Auth, Shared |
| MOD-007 | **Dashboard** | `api/src/Domain/Dashboard/` | Métricas, KPIs, widgets | Auth, Billing, Chat, CRM, Shared |
| MOD-008 | **Gateway** | `api/src/Domain/Gateway/` | Lógica relay, webhooks, circuit breaker | Auth, Shared |
| MOD-009 | **Platform** | `api/src/Domain/Platform/` | Tenants, planos, onboarding | Auth, Configuration, Shared |
| MOD-010 | **Reports** | `api/src/Domain/Reports/` | Relatórios, exportação | Auth, Chat, CRM, Dashboard, Shared |
| MOD-011 | **Shared** | `api/src/Domain/Shared/` | Traits, services, helpers | (nenhuma) |

---

## 4. Módulos e Funcionalidades

---

### 4.1 Módulo Auth (Autenticação e Permissões)

#### O Que Faz

O módulo **Auth** é responsável por toda a camada de autenticação e autorização do sistema InteraZap. Ele garante que apenas usuários identificados e autorizados possam acessar as funcionalidades da plataforma, implementando um sistema multi-tenant onde cada empresa (tenant) possui seus próprios usuários, roles e permissões isolados.

**Principais responsabilidades:**
- Autenticação de usuários via email/senha
- Autenticação em dois fatores (2FA) usando TOTP (Time-based One-Time Password)
- Geração e validação de tokens de acesso (Sanctum para HTTP, JWT HS256 para WebSocket)
- Sistema de permissões granulares baseado em RBAC (Role-Based Access Control)
- Gerenciamento de usuários por tenant
- Perfis de usuário com avatar, preferências

#### Menu do Sistema

```
👤 Meu Perfil
   ├── 👁️ Ver perfil
   ├── ✏️ Editar perfil
   ├── 🔐 Alterar senha
   └── 📱 Gerenciar 2FA

🔐 Autenticação
   ├── 📝 Login
   ├── 🔄 Esqueci minha senha
   └── ✅ Confirmação 2FA

⚙️ Gestão de Usuários (Admin)
   ├── 📋 Lista de usuários
   ├── ➕ Criar usuário
   ├── ✏️ Editar usuário
   └── 🗑️ Excluir usuário

👥 Papéis e Permissões (Admin)
   ├── 📋 Lista de papéis
   ├── ➕ Criar papel
   ├── ✏️ Editar permissões
   └── 👤 Atribuir a usuários
```

#### RBAC (Roles e Permissões)

**Roles Padrão do Sistema:**

| Role | Descrição | Quem Usa |
|------|-----------|----------|
| **Super Admin** | Acesso irrestrito a todos os recursos, incluindo gestão de tenants | Fundadores, CTO |
| **Admin** | Acesso completo exceto gestão de tenants de outras empresas | Gerentes de conta |
| **Agent** | Acesso a atendimento, CRM básico e dashboard | Agentes de suporte/vendas |
| **Billing** | Acesso apenas a faturamento e relatórios financeiros | Equipe financeira |
| **Viewer** | Apenas visualização, sem permissões de edição | Auditores |

**Permissões Granulares:**

| Categoria | Permissão | Descrição |
|-----------|-----------|-----------|
| **Dashboard** | `dashboard.view` | Visualizar dashboard principal |
| | `dashboard.export` | Exportar dados do dashboard |
| **CRM** | `crm.contact.view` | Visualizar contatos |
| | `crm.contact.create` | Criar contatos |
| | `crm.contact.update` | Editar contatos |
| | `crm.contact.delete` | Excluir contatos |
| | `crm.company.view` | Visualizar empresas |
| | `crm.company.create` | Criar empresas |
| | `crm.company.update` | Editar empresas |
| | `crm.company.delete` | Excluir empresas |
| | `crm.negotiation.view` | Visualizar negociações |
| | `crm.negotiation.create` | Criar negociações |
| | `crm.negotiation.update` | Editar negociações |
| | `crm.negotiation.delete` | Excluir negociações |
| | `crm.negotiation.won` | Marcar como ganha |
| | `crm.negotiation.lost` | Marcar como perdida |
| | `crm.funnel.view` | Visualizar funis |
| | `crm.event.view` | Visualizar eventos |
| | `crm.event.create` | Criar eventos |
| | `crm.event.update` | Editar eventos |
| | `crm.event.delete` | Excluir eventos |
| **Chat** | `chat.called.view` | Visualizar tickets |
| | `chat.called.create` | Criar tickets |
| | `chat.called.update` | Editar tickets |
| | `chat.called.delete` | Excluir tickets |
| | `chat.called.transfer` | Transferir tickets |
| | `chat.called.close` | Fechar tickets |
| | `chat.channel.view` | Visualizar canais |
| | `chat.channel.create` | Criar canais |
| | `chat.channel.update` | Editar canais |
| | `chat.channel.delete` | Excluir canais |
| | `chat.channel.connect` | Conectar canais |
| | `chat.channel.disconnect` | Desconectar canais |
| **AI** | `ai.feature.enabled` | Usar funcionalidades de IA |
| | `ai.agents.manage` | Gerenciar agentes IA |
| | `ai.knowledge.manage` | Gerenciar base de conhecimento |
| | `ai.prompts.manage` | Gerenciar prompts |
| | `ai.usage.view` | Visualizar uso de IA |
| **Billing** | `billing.view` | Visualizar faturas |
| | `billing.pay` | Pagar faturas |
| | `billing.manage` | Gerenciar faturamento |
| **Reports** | `reports.viewCrm` | Relatórios de CRM |
| | `reports.viewChat` | Relatórios de Chat |
| | `reports.viewAi` | Relatórios de IA |
| | `reports.viewBilling` | Relatórios de Billing |
| | `reports.export` | Exportar relatórios |
| **Platform** | `platform.tenants.manage` | Gerenciar tenants (super-admin) |
| | `platform.plans.manage` | Gerenciar planos |
| | `platform.settings.manage` | Gerenciar configurações |
| **Users** | `users.user.view` | Visualizar usuários |
| | `users.user.create` | Criar usuários |
| | `users.user.update` | Editar usuários |
| | `users.user.delete` | Excluir usuários |
| | `users.role.manage` | Gerenciar papéis |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Usuários | 1 | 5 | 20 | Ilimitado |
| 2FA | ✅ | ✅ | ✅ | ✅ |
| Roles customizados | ❌ | ❌ | ✅ | ✅ |
| Permissões granulares | ❌ | Básico | Completo | Completo |
| Audit log | ❌ | 7 dias | 30 dias | Ilimitado |

#### Estrutura de Dados

**Users**
- id (uuid), tenant_id, name, email, password, avatar_url
- role_id, is_2fa_enabled, 2fa_secret
- last_login_at, created_at, updated_at

**Roles**
- id, name, guard_name, tenant_id
- created_at, updated_at

**Permissions**
- id, name, guard_name
- Pivot: role_permissions

---

### 4.2 Módulo CRM (Gestão de Relacionamento com Clientes)

#### O Que Faz

O módulo **CRM** é o coração da gestão comercial do InteraZap. Ele permite que empresas gerenciem todo seu relacionamento com clientes, desde o primeiro contato até o fechamento de negócios. O CRM oferece uma visão 360° do cliente, combinando dados de contatos, empresas, negociações, propostas e atividades.

**Principais responsabilidades:**
- Cadastro e gestão de contatos e empresas
- Pipeline de vendas com visualização Kanban
- Gestão de negociações com valores e responsáveis
- Criação e envio de propostas comerciais
- Agenda de eventos e atividades
- Funis de vendas customizáveis
- Campos customizados para extensibilidade
- Análise de desempenho comercial

#### Menu do Sistema

```
📇 CRM
   ├── 👥 Contatos
   │    ├── 📋 Lista de contatos
   │    ├── ➕ Novo contato
   │    ├── 📤 Importar CSV
   │    ├── 📤 Exportar CSV
   │    └── 🏷️ Gerenciar tags
   │
   ├── 🏢 Empresas
   │    ├── 📋 Lista de empresas
   │    ├── ➕ Nova empresa
   │    ├── 📤 Importar CSV
   │    └── 📤 Exportar CSV
   │
   ├── 💰 Negociações
   │    ├── 📋 Todas (Kanban)
   │    ├── ➕ Nova negociação
   │    ├── 📊 Por funnel
   │    └── 📈 Métricas
   │
   ├── 📄 Propostas
   │    ├── 📋 Lista de propostas
   │    ├── ➕ Nova proposta
   │    ├── 📝 Templates
   │    └── 🔗 Links públicos
   │
   ├── 📅 Agenda
   │    ├── 📆 Calendário
   │    ├── ➕ Novo evento
   │    └── 🔔 Lembretes
   │
   ├── 🔀 Funis
   │    ├── 📋 Lista de funis
   │    ├── ➕ Novo funil
   │    └── ✏️ Editar etapas
   │
   └── ⚙️ Campos Customizados
        ├── 📋 Lista de campos
        └── ➕ Novo campo
```

#### RBAC (Permissões CRM)

| Permissão | Agente | Admin | Billing | Viewer |
|-----------|--------|-------|---------|--------|
| crm.contact.view | ✅ | ✅ | ❌ | ✅ |
| crm.contact.create | ✅ | ✅ | ❌ | ❌ |
| crm.contact.update | ✅ (próprios) | ✅ | ❌ | ❌ |
| crm.contact.delete | ❌ | ✅ | ❌ | ❌ |
| crm.company.* | ❌ | ✅ | ❌ | ✅ |
| crm.negotiation.view | ✅ | ✅ | ✅ | ✅ |
| crm.negotiation.create | ✅ | ✅ | ❌ | ❌ |
| crm.negotiation.update | ✅ (próprios) | ✅ | ❌ | ❌ |
| crm.negotiation.won/lost | ❌ | ✅ | ❌ | ❌ |
| crm.proposal.* | ✅ (próprios) | ✅ | ❌ | ✅ |
| crm.event.* | ✅ | ✅ | ❌ | ✅ |
| crm.funnel.view | ✅ | ✅ | ❌ | ✅ |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Contatos | 100 | 1.000 | 10.000 | Ilimitado |
| Empresas | 10 | 100 | 1.000 | Ilimitado |
| Negociações | 50 | 500 | 5.000 | Ilimitado |
| Propostas | 10/mês | 50/mês | 200/mês | Ilimitado |
| Eventos | ✅ | ✅ | ✅ | ✅ |
| Funis customizados | 1 | 3 | 10 | Ilimitado |
| Campos customizados | ❌ | 5 | 20 | Ilimitado |
| Importação CSV | ❌ | ✅ | ✅ | ✅ |
| Links públicos proposta | ❌ | ✅ | ✅ | ✅ |

#### Autopilot (Automação CRM)

O Autopilot do CRM permite automatizar ações baseadas em eventos do funil:

```
TRIGGERS:
├── Negociação criada
├── Negociação movida para etapa X
├── Negociação com X dias sem atualização
├── Valor acima de R$ X
└── Contato sem negociação em X dias

AÇÕES:
├── Enviar mensagem via Chat
├── Criar tarefa
├── Notificar responsável
├── Mover para etapa
├── Atribuir a usuário
└── Atualizar campo
```

**Exemplos de Automação:**
1. **Lead Qualification:** Quando negociação entra em "Qualificação", atribuir automaticamente ao agente de vendas
2. **Follow-up:** Quando negociação sem atualização há 3 dias, notificar o responsável
3. **Escalation:** Quando negociação com +R$ 50k em "Proposta Enviada" há 5 dias, escalar para gerente

#### Estrutura de Dados

**Contacts**
- id (uuid), tenant_id, name, email, phone, avatar_url
- company_id (fk), tags (json), custom_fields (json)
- source, owner_id, created_at, updated_at

**Companies**
- id (uuid), tenant_id, name, document (CNPJ/CPF)
- address, phone, email, website
- created_at, updated_at

**Negotiations**
- id (uuid), tenant_id, title, value, currency
- funnel_step_id (fk), contact_id (fk), company_id (fk)
- owner_id (fk), loss_reason_id, probability
- expected_close_date, created_at, updated_at

**Proposals**
- id (uuid), tenant_id, negotiation_id
- title, content (markdown), public_token
- status (draft, sent, approved, rejected)
- sent_at, viewed_at, created_at, updated_at

**Events**
- id (uuid), tenant_id, title, description
- datetime_start, datetime_end, contact_id
- negotiation_id, owner_id, reminder
- created_at, updated_at

**Funnels**
- id (uuid), tenant_id, name, description
- is_default, created_at, updated_at

**Funnel Steps**
- id (uuid), funnel_id, name, color, order
- probability_default, created_at, updated_at

---

### 4.3 Módulo Chat (Atendimento Multicanal)

#### O Que Faz

O módulo **Chat** é o sistema central de atendimento ao cliente do InteraZap. Ele unifica todas as conversas de WhatsApp, Telegram e WebChat em uma única interface, permitindo que agentes gerenciem tickets, enviem mensagens e automatizem respostas. O módulo integra-se nativamente com CRM para vincular conversas a contatos e empresas, e com IA para automação inteligente.

**Principais responsabilidades:**
- Conexão com múltiplos provedores de WhatsApp (UazAPI, Z-API, Meta)
- Gestão de tickets de atendimento
- Envio e recebimento de mensagens multicanal
- Chat em tempo real via WebSocket
- Sistema de auto-respostas e regras de automação
- Análise de sentimento em tempo real
- Pesquisa de satisfação (CSAT)
- Transferência de tickets entre agentes

#### Menu do Sistema

```
💬 Chat
   │
   ├── 🎫 Atendimentos
   │    ├── 📋 Lista de tickets (Inbox)
   │    ├── 📊 Kanban (por status)
   │    ├── 🔍 Buscar conversa
   │    └── ➕ Novo ticket manual
   │
   ├── 📱 Canais
   │    ├── 📋 Lista de canais
   │    ├── ➕ Adicionar canal
   │    ├── ✏️ Configurar webhook
   │    ├── 🔗 QR Code (conexão)
   │    └── 🔄 Sincronizar
   │
   ├── ⚡ Auto-Respostas
   │    ├── 📋 Regras ativas
   │    ├── ➕ Nova regra
   │    ├── 🔧 Configurar triggers
   │    └── 📊 Histórico de execuções
   │
   ├── 📝 Respostas Rápidas
   │    ├── 📋 Biblioteca de respostas
   │    ├── ➕ Nova resposta
   │    ├── 🏷️ Categorias
   │    └── 📤 Importar/Exportar
   │
   ├── 📈 Métricas
   │    ├── 📊 Volume de mensagens
   │    ├── ⏱️ Tempo de resposta
   │    ├── ✅ CSAT
   │    └── 🏷️ Análise de sentimento
   │
   └── 🔔 Notificações
        ├── ⚙️ Configurar
        └── 📋 Histórico
```

#### RBAC (Permissões Chat)

| Permissão | Agente | Admin | Super Admin |
|-----------|--------|-------|-------------|
| chat.called.view | ✅ (atribuídos) | ✅ (todos) | ✅ |
| chat.called.create | ✅ | ✅ | ✅ |
| chat.called.update | ✅ (atribuídos) | ✅ | ✅ |
| chat.called.delete | ❌ | ✅ | ✅ |
| chat.called.transfer | ✅ | ✅ | ✅ |
| chat.called.close | ✅ (atribuídos) | ✅ | ✅ |
| chat.called.evaluate | ✅ | ✅ | ✅ |
| chat.channel.view | ❌ | ✅ | ✅ |
| chat.channel.create | ❌ | ✅ | ✅ |
| chat.channel.update | ❌ | ✅ | ✅ |
| chat.channel.delete | ❌ | ✅ | ✅ |
| chat.channel.connect | ❌ | ✅ | ✅ |
| chat.channel.disconnect | ❌ | ✅ | ✅ |
| chat.quick_answers.* | ❌ | ✅ | ✅ |
| chat.auto_reply.* | ❌ | ✅ | ✅ |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Canais | 1 | 3 | 10 | Ilimitado |
| Agentes simultâneos | 1 | 5 | 20 | Ilimitado |
| Tickets/mês | 100 | 1.000 | 10.000 | Ilimitado |
| Mensagens/mês | 500 | 5.000 | 50.000 | Ilimitado |
| Quick Answers | 10 | 50 | 200 | Ilimitado |
| Regras Auto-Reply | 3 | 10 | 50 | Ilimitado |
| CSAT | ❌ | ✅ | ✅ | ✅ |
| Análise Sentimento | ❌ | ❌ | ✅ | ✅ |
| Transferência | ❌ | ✅ | ✅ | ✅ |
| Human Takeover | ❌ | ✅ | ✅ | ✅ |
| WebChat Widget | ❌ | ✅ | ✅ | ✅ |

#### Autopilot (Automação de Chat)

O Autopilot de Chat permite criar fluxos de automação inteligente:

```
TRIGGERS DE AUTO-REPLY:
├── 📝 Palavra-chave específica
├── 🕐 Horário específico
├── 👋 Primeira mensagem do cliente
├── 🔗 Mensagem de um canal específico
├── 📊 Intenção detectada por IA
└── 🏷️ Tag do contato

TIPOS DE RESPOSTA:
├── 💬 Texto simples
├── 📝 Resposta rápida (Quick Answer)
├── 🤖 Agente IA específico
├── 📋 Formulário de coleta
└── 🔗 Redirecionamento para humano

FLUXO AUTOPILOT:
1. Mensagem entra → Trigger avaliado
2. IA classifica intenção (se habilitado)
3. Regra de Auto-Reply verificada
4. Se match: Resposta automática disparada
5. Se agente IA: Autopilot AI executa
6. Se sem match: Ticket fica na fila
```

**Exemplos de Automação:**
1. **Fala, Rodrigo!:** Quando cliente envia "oi" ou "olá" fora do horário, responder com mensagem de absence
2. **Lead Capture:** Quando nova conversa inicia, coletar nome, email e telefone via formulário
3. **Classificação:** IA classifica intent da mensagem e rotula o ticket automaticamente
4. **Roteamento:** Tickets de "financeiro" vão automaticamente para fila de billing

#### Estrutura de Dados

**Chat Channels**
- id (uuid), tenant_id, name, provider (uazapi/zapi/meta)
- provider_token, provider_token_secret, webhook_url
- qr_code_url, status (connected/disconnected/error)
- created_at, updated_at

**Chat Tickets**
- id (uuid), tenant_id, channel_id, external_id
- contact_id (fk), assigned_user_id (fk)
- status (open/pending/in_progress/closed), priority (low/normal/high/urgent)
- sentiment (positive/neutral/negative), csat_score (1-5)
- last_message_at, closed_at, created_at, updated_at

**Chat Messages**
- id (uuid), ticket_id, tenant_id
- direction (inbound/outbound), type (text/image/audio/video/document/sticker/location/contact/reaction/template)
- content (texto ou JSON), provider_message_id
- is_from_bot, created_at

**Chat Sessions**
- id (uuid), tenant_id, channel_id, external_id
- started_at, ended_at

**Quick Answers**
- id (uuid), tenant_id, shortcut, message
- category, is_active, created_at, updated_at

**Auto-Reply Rules**
- id (uuid), tenant_id, channel_id
- name, trigger_type (keyword/intent/time), conditions (json)
- response_type (text/quick_answer/ai_agent), ai_agent_id (fk)
- is_active, created_at, updated_at

---

### 4.4 Módulo AI (Inteligência Artificial)

#### O Que Faz

O módulo **AI** é o cérebro de automação inteligente do InteraZap. Ele permite criar agentes de IA especializados para diferentes tarefas (vendas, suporte, qualificação de leads), gerenciar bases de conhecimento para RAG (Retrieval-Augmented Generation), e automatizar conversas com tool calling. O módulo conecta-se com OpenAI, Anthropic e Google para completions e embeddings.

**Principais responsabilidades:**
- Criação e gestão de agentes de IA especializados
- Base de conhecimento com embeddings vetoriais
- Buscas semânticas via pgvector
- Autopilot: execução automática em tickets
- Tool calling: integração com APIs externas
- Templates de prompts customizáveis
- Métricas de uso e custos
- Gerenciamento de context window

#### Menu do Sistema

```
🤖 Inteligência Artificial
   │
   ├── 🤖 Agentes
   │    ├── 📋 Lista de agentes
   │    ├── ➕ Criar agente
   │    ├── ✏️ Configurar
   │    ├── 🧪 Testar
   │    └── 📊 Performance
   │
   ├── 📚 Base de Conhecimento
   │    ├── 📋 Documentos
   │    ├── ➕ Adicionar documento
   │    ├── 📤 Importar
   │    ├── 🔄 Reindexar
   │    └── 📊 Status de embedding
   │
   ├── 📝 Prompts
   │    ├── 📋 Biblioteca
   │    ├── ➕ Criar prompt
   │    ├── 🧪 Testar
   │    └── 📤 Importar/Exportar
   │
   ├── 🛠️ Ferramentas
   │    ├── 📋 Tools configuradas
   │    ├── ➕ Criar tool
   │    ├── 🔧 Configurar schema
   │    └── 🧪 Testar
   │
   ├── 🚀 Autopilot
   │    ├── 📋 Fluxos ativos
   │    ├── ➕ Criar fluxo
   │    ├── 📊 Execuções
   │    └── ⚙️ Configurações globais
   │
   ├── 📈 Métricas
   │    ├── 📊 Uso de tokens
   │    ├── 💰 Custos
   │    ├── 🤖 Performance de agentes
   │    └── 📈 Conversões
   │
   └── ⚙️ Configurações
        ├── 🔑 API Keys
        ├── 📊 Orçamentos
        └── 🔔 Alertas
```

#### Tipos de Agentes de IA

| Tipo | Código | Descrição | Uso Típico |
|------|--------|-----------|------------|
| **Qualificador de Vendas** | `sales_qualifier` | Qualifica leads fazendo perguntas-chave | "Este lead está qualificado para compra?" |
| **Suporte N1** | `support_l1` | Responde dúvidas básicas de suporte | FAQ, status de pedido, rastreio |
| **Retenção** | `cs_retention` | Identifica clientes insatisfeitos e tenta reter | "Cliente quer cancelar" |
| **Pós-Venda** | `post_sales` | Acompanha cliente após compra | Onboarding, upsell, satisfaction |
| **Agendamento** | `appointment` | Agenda reuniões e demos | "Quero falar com um vendedor" |
| **Financeiro** | `finance` | Responde dúvidas sobre faturas e pagamentos | "Minha fatura está correta?" |
| **Roteamento** | `routing` | Classifica e roteia tickets | "Este ticket é de suporte ou vendas?" |
| **Geral** | `general` | Agente conversacional genérico | Atendimento geral |
| **Customizado** | `custom` | Comportamento customizado via prompt | Qualquer caso de uso específico |

#### Configuração de Agente

```
AGENTE:
├── Nome: "Qualificador de Vendas VIP"
├── Tipo: sales_qualifier
├── Modelo: GPT-4o
├── Prompt de Sistema: [textarea]
├── Max Tokens: 500
├── Temperature: 0.7
├── Budget por Execução: 1000 tokens
├── Ativo: ✅
│
├── CANAIS:
│   ├── WhatsApp Principal ✅
│   └── WebChat ✅
│
├── BASES DE CONHECIMENTO:
│   ├── FAQ Produtos
│   └── Políticas de Preço
│
└── FERRAMENTAS:
    ├── AgendarReuniao (HTTP)
    ├── AtualizarCRM (Function)
    └── EnviarEmail (HTTP)
```

#### RBAC (Permissões AI)

| Permissão | Agente | Admin | Super Admin |
|-----------|--------|-------|-------------|
| ai.feature.enabled | ✅ | ✅ | ✅ |
| ai.agents.view | ✅ | ✅ | ✅ |
| ai.agents.manage | ❌ | ✅ | ✅ |
| ai.knowledge.view | ✅ | ✅ | ✅ |
| ai.knowledge.manage | ❌ | ✅ | ✅ |
| ai.prompts.view | ❌ | ✅ | ✅ |
| ai.prompts.manage | ❌ | ✅ | ✅ |
| ai.usage.view | ❌ | ✅ | ✅ |
| ai.tools.manage | ❌ | ✅ | ✅ |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Agentes | 0 | 2 | 10 | Ilimitado |
| Base de conhecimento | ❌ | 1 | 5 | Ilimitado |
| Tamanho por documento | - | 5MB | 50MB | 200MB |
| Embeddings/mês | - | 1M | 10M | Ilimitado |
| Mensagens AI/mês | - | 500 | 5.000 | Ilimitado |
| Tools por agente | - | 3 | 10 | Ilimitado |
| Autopilot | ❌ | ❌ | ✅ | ✅ |
| Modelos avançados | ❌ | GPT-4o | GPT-4o + Claude | Todos |

#### Autopilot (Fluxo Completo)

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUXO AUTOPILOT AI                           │
└─────────────────────────────────────────────────────────────────┘

1. 🔄 TRIGGER
   └── Nova mensagem em ticket
   └── Regra de auto-reply ativada
   └── Horário específico
   └── Command manual do agente

2. 🎯 CLASSIFICAÇÃO DE INTENÇÃO (opcional)
   └── IA analiza mensagem
   └── Extrai: intent, entities, sentiment
   └── Decide: responder, escalar, ignorar

3. 📝 ASSEMBLER DE PROMPT
   └── Carrega system prompt do agente
   └── Hidrata com variáveis ({{contact_name}}, etc)
   └── Adiciona contexto do ticket
   └── Busca relevante na base de conhecimento (RAG)

4. 🔧 CONTEXT WINDOW
   └── Verifica limite de tokens
   └── Se exceder: trunca ou resume histórico
   └── Conta tokens de entrada

5. 🤖 LLM PROVIDER
   └── Envia para OpenAI/Anthropic/Gemini
   └── Recebe response (text ou tool_calls)

6. 🛠️ TOOL EXECUTOR (se tool_calls)
   └── Para cada tool call:
       ├── Valida parâmetros
       ├── Executa HTTP call ou function
       └── Retorna resultado

7. 📤 RESPOSTA
   └── Se text: envia mensagem ao cliente
   └── Se tool: adiciona ao contexto, re-executa LLM
   └── Atualiza ticket (sentiment, intent)

8. 📊 BROADCAST
   └── Dispara evento WebSocket
   └── Atualiza frontend em tempo real
   └── Log em ai_runs

┌─────────────────────────────────────────────────────────────────┐
│                    LIMITAÇÕES E CONTROLES                       │
└─────────────────────────────────────────────────────────────────┘

• Máx 5 iterações de tool calling por execução
• Timeout de 30 segundos por execução
• Budget de tokens por execução (configurável)
• Circuit breaker: pausa agente após 5 erros consecutivos
```

#### Estrutura de Dados

**AI Agents**
- id (uuid), tenant_id, name
- type (enum: 9 tipos), model_id (openai/gemini/anthropic)
- system_prompt, max_tokens, temperature
- token_budget_per_run, is_active
- created_at, updated_at

**AI Agent Channels** (pivot)
- agent_id, channel_id

**AI Agent Tools** (pivot)
- agent_id, tool_id

**AI Knowledge Bases**
- id (uuid), tenant_id, name
- file_type (txt/csv/markdown/json/pdf/url)
- file_path, file_url, embedding_status (pending/processing/ready/failed)
- chunk_count, token_count
- created_at, updated_at

**AI Knowledge Chunks**
- id (uuid), knowledge_id
- content (text), embedding (vector[1536])
- token_count

**AI Prompts**
- id (uuid), tenant_id, name
- prompt_type (system/user/assistant)
- content (template), variables (json)
- is_active

**AI Runs**
- id (uuid), tenant_id, agent_id, ticket_id
- run_type (manual/autopilot)
- status (pending/running/completed/failed)
- input_tokens, output_tokens, total_tokens
- cost_usd, error_message
- started_at, ended_at

**AI Tools**
- id (uuid), tenant_id, name, description
- tool_type (http/function), schema (json)
- headers (json, para HTTP), is_active

---

### 4.5 Módulo Billing (Faturamento e Pagamentos)

#### O Que Faz

O módulo **Billing** gerencia todo o ciclo de vida financeiro do InteraZap, desde a cobrança até o recebimento. Ele integra-se com a API do Asaas para processar pagamentos via PIX, cartão de crédito e boleto, gerencia inadimplência com grace periods e bloqueios, e permite que empresas visualizem e gerenciem suas faturas.

**Principais responsabilidades:**
- Geração automática de faturas mensais
- Processamento de pagamentos via Asaas (PIX, cartão, boleto)
- Gestão de inadimplência (grace period, bloqueio, purge)
- Troca de planos (upgrade/downgrade com prorata)
- Budget e alertas de uso
- Webhooks para atualização de status de pagamento

#### Menu do Sistema

```
💳 Billing
   │
   ├── 📄 Faturas
   │    ├── 📋 Lista de faturas
   │    ├── 👁️ Ver fatura
   │    ├── 💰 Pagar fatura
   │    └── 📧 Enviar por email
   │
   ├── 💰 Pagamentos
   │    ├── 📋 Métodos cadastrados
   │    ├── ➕ Adicionar método
   │    ├── ✏️ Editar método
   │    └── 🗑️ Remover método
   │
   ├── 📊 Orçamentos
   │    ├── 📋 Limite mensal
   │    ├── 📈 Uso atual
   │    └── 🔔 Configurar alertas
   │
   ├── 📈 Histórico
   │    ├── 📋 Transações
   │    ├── 📤 Exportar
   │    └── 📧 Comprovantes
   │
   └── ⚙️ Preferências
        ├── 🔔 Notificações
        └── 📧 Email de cobrança
```

#### RBAC (Permissões Billing)

| Permissão | Agente | Admin | Billing | Super Admin |
|-----------|--------|-------|---------|-------------|
| billing.view | ❌ | ✅ | ✅ | ✅ |
| billing.pay | ❌ | ✅ | ✅ | ✅ |
| billing.manage | ❌ | ❌ | ✅ | ✅ |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Trial | 7 dias | 14 dias | 14 dias | Sob consulta |
| Faturas | Mensal | Mensal | Mensal | Sob consulta |
| Métodos de pagamento | PIX | PIX + Cartão | PIX + Cartão | Todos |
| Budget alerts | ❌ | ✅ | ✅ | ✅ |
| Prorata upgrade | ❌ | ✅ | ✅ | ✅ |
| Prorata downgrade | ❌ | ❌ | Fim do ciclo | Fim do ciclo |

**Estrutura de Planos:**

| Plan | Preço | Limite Contatos | Limite Tickets | Limite Agentes | Limite AI |
|------|-------|-----------------|----------------|----------------|-----------|
| Free | Grátis | 100 | 100/mês | 1 | ❌ |
| Starter | R$ 97/mês | 1.000 | 1.000/mês | 5 | 500 msgs |
| Professional | R$ 297/mês | 10.000 | 10.000/mês | 20 | 5.000 msgs |
| Enterprise | Sob consulta | Ilimitado | Ilimitado | Ilimitado | Ilimitado |

#### Fluxo de Inadimplência

```
┌─────────────────────────────────────────────────────────────────┐
│              FLUXO DE INADIMPLÊNCIA                             │
└─────────────────────────────────────────────────────────────────┘

📅 Fatura gerada (D+0)
   └── Status: pending
   └── Vencimento: D+7 (PIX) ou D+30 (Cartão)

⏰ Venceu (D+7 ou D+30)
   └── Status: overdue
   └── Notificação enviada ao cliente

🕐 GRACE PERIOD (D+8 a D+12 ou D+31 a D+35)
   └── Status: grace_period
   └── Sistema continua funcionando
   └── Novos tentativas de cobrança
   └── Email diário de lembrete

🚫 BLOQUEIO (D+13 ou D+36)
   └── Status: suspended
   └── Acesso ao sistema bloqueado
   └── Apenas visualização de faturas
   └── Negociações e tickets mantidos (leitura)
   └── Chat recebe mensagem automática de bloqueio

🗑️ PURGE (D+43 ou D+66 - 30 dias após suspensão)
   └── Status: canceled
   └── Dados mantidos por 30 dias
   └── Após: exclusão de dados
   └── Tenant é removido permanentemente
```

#### Estrutura de Dados

**Billing Invoices**
- id (uuid), tenant_id, asaas_id
- value, net_value, gross_value
- status (pending/confirmed/reviewed/paid/overdue/grace_period/suspended/canceled)
- billing_type (credit_card/pix/ticket)
- due_date, payment_date, paid_at
- external_url (PIX QR code),asaas_response (json)
- created_at, updated_at

**Billing Budgets**
- id (uuid), tenant_id, plan_id
- monthly_limit, current_usage
- alert_threshold (percentage)
- reset_at

**Plans**
- id (uuid), name, description
- price (decimal), trial_days
- features (json), limits (json)
- is_active, created_at, updated_at

**Tenants**
- id (uuid), name, domain, logo_url
- plan_id (fk), status (trial/active/suspended/canceled)
- settings (json), asaas_customer_id
- grace_expires_at, suspended_at, canceled_at
- created_at, updated_at

---

### 4.6 Módulo Dashboard

#### O Que Faz

O módulo **Dashboard** é a tela inicial do InteraZap, consolidando as principais métricas de CRM, Chat e IA em um único lugar. Ele oferece aos gestores uma visão panorâmica do negócio, com KPIs atualizados em tempo real, gráficos interativos e acesso rápido às principais ações.

**Principais responsabilidades:**
- Consolidação de KPIs de todos os módulos
- Gráficos de receita e funil de vendas
- Métricas de atendimento (tickets, CSAT, SLA)
- Atividades recentes
- Widgets customizáveis

#### Menu do Sistema

```
📊 Dashboard
   │
   ├── 🏠 Visão Geral
   │    ├── 📈 KPIs principais
   │    ├── 📊 Gráfico de receitas
   │    ├── 🔀 Funil de vendas
   │    └── 📉 Tendências
   │
   ├── 💬 Chat
   │    ├── 🎫 Tickets abertos
   │    ├── ⏱️ Tempo médio de resposta
   │    ├── ✅ CSAT do período
   │    └── 📊 Volume por canal
   │
   ├── 💰 Vendas
   │    ├── 💵 Receita do mês
   │    ├── 📈 Pipeline aberto
   │    ├── 🏆 Top vendedores
   │    └── 📉 Conversões
   │
   ├── 🤖 IA
   │    ├── 💬 Mensagens processadas
   │    ├── 💰 Custo do período
   │    ├── 📈 Tendência de uso
   │    └── 🤖 Performance agents
   │
   └── ⚙️ Configurar
        ├── 🎨 Adicionar widgets
        ├── 📐 Layout
        └── 💾 Salvar preset
```

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| KPIs principais | ✅ | ✅ | ✅ | ✅ |
| Gráfico de receitas | ❌ | 6 meses | 12 meses | Ilimitado |
| Funil de vendas | ✅ | ✅ | ✅ | ✅ |
| Atividades recentes | 10 | 50 | 100 | Ilimitado |
| Widgets customizáveis | ❌ | 3 | 10 | Ilimitado |
| Dashboards salvos | ❌ | 1 | 5 | Ilimitado |
| Exportação | ❌ | ✅ | ✅ | ✅ |

#### Estrutura de Dados

**Dashboard Summary**
```typescript
{
  total_revenue_won: number;      // Soma de negociações "won" no mês
  pipeline_open_value: number;   // Soma de negociações abertas
  active_tickets_count: number;  // Tickets não fechados
  csat_average: number;           // Média de CSAT (1-5)
}
```

**Dashboard Data**
```typescript
{
  summary: DashboardSummary;
  funnel: FunnelStep[];           // Etapas do funil com contagem
  revenue: RevenueMonth[];        // Receitas por mês (12 meses)
  negotiations: NegotiationStats; // Deals, conversão, tempo
  tickets: TicketStats;           // Volume, SLA, CSAT
  csat: CsatStats;                // Detalhado de CSAT
  activities: RecentActivity[];   // Últimas atividades
}
```

---

### 4.7 Módulo Platform (Gestão Multi-Tenant)

#### O Que Faz

O módulo **Platform** é a camada de gestão do ecossistema InteraZap, permitindo que administradores globais (Super Admins) gerenciem tenants, planos, configurações globais e monitorem a saúde do sistema. É a interface de administração do SaaS.

**Principais responsabilidades:**
- CRUD de tenants (empresas clientes)
- CRUD de planos e assinaturas
- Bootstrap de novos tenants
- Configurações globais de plataforma
- Monitoramento de health e filas
- Gestão de integrações globais (UazAPI, Asaas)

#### Menu do Sistema

```
⚙️ Plataforma (Super Admin)
   │
   ├── 🏢 Tenants
   │    ├── 📋 Lista de tenants
   │    ├── ➕ Criar tenant
   │    ├── ✏️ Editar tenant
   │    ├── 🚫 Suspender
   │    ├── 🗑️ Cancelar
   │    └── 📊 Uso de recursos
   │
   ├── 📦 Planos
   │    ├── 📋 Lista de planos
   │    ├── ➕ Criar plano
   │    ├── ✏️ Editar plano
   │    ├── 💰 Preços
   │    └── 📋 Features e limites
   │
   ├── 🔧 Configurações Globais
   │    ├── 🔑 Integrações
   │    │    ├── 📱 UazAPI (WhatsApp)
   │    │    ├── 📱 Z-API
   │    │    ├── 💳 Asaas (Pagamentos)
   │    │    └── 🤖 OpenAI
   │    ├── 📧 Email
   │    ├── 🔐 Segurança
   │    └── 📜 Termos e políticas
   │
   ├── 📊 Monitoramento
   │    ├── 🏥 Health check
   │    ├── 📬 Filas
   │    ├── 💾 Redis
   │    └── 📈 Prometheus
   │
   ├── 📈 Analytics
   │    ├── 👥 Total de tenants
   │    ├── 💰 MRR/ARR
   │    ├── 📉 Churn
   │    └── 📊 Conversão trial → paid
   │
   └── 🕐 Audit Log
        ├── 📋 Operações
        └── 🔍 Detalhes
```

#### Planos de Tenants

| Funcionalidade | Descrição |
|----------------|-----------|
| CRUD Tenants | Criar, editar, suspender, cancelar |
| Bootstrap | Setup automático de novo tenant |
| Plan Assignment | Atribuir plano, fazer upgrade/downgrade |
| Monitoring | Ver uso de recursos por tenant |
| Audit | Log de todas operações |

#### Estrutura de Dados

**Platform Tenants** (mesma tabela de tenants principal com status expandido)
- Todos os campos de tenants
- Audit de mudanças

**Platform Plans** (mesma tabela de plans)
- Todos os campos de plans
- Created_by, updated_by (Super Admin)

**Platform Settings**
- id, key, value (json)
- description, is_encrypted

---

### 4.8 Módulo Reports (Relatórios)

#### O Que Faz

O módulo **Reports** oferece uma suite completa de relatórios analíticos para que gestores possam acompanhar performance de vendas, atendimento, IA e finanças. Com 14 tipos de relatórios nativos, filtros avançados e exportação, o módulo atende desde startups até enterprise.

**Principais responsabilidades:**
- Relatórios de CRM (funil, receita, vendedores)
- Relatórios de Chat (SLA, CSAT, volume)
- Relatórios de IA (custos, performance)
- Relatórios de Billing (faturas, inadimplência)
- Relatórios de Team Activity
- Exportação CSV/PDF
- Agendamento de relatórios

#### Menu do Sistema

```
📊 Relatórios
   │
   ├── 📈 Visão Geral
   │    └── 📋 Todos os relatórios
   │
   ├── 💼 CRM
   │    ├── 🔀 Funil de Vendas
   │    ├── 💰 Receita por Período
   │    ├── 👤 Performance de Vendedores
   │    ├── 📉 Análise de Perdas
   │    ├── 📦 Performance de Produtos
   │    └── 👥 Relatório de Contatos
   │
   ├── 💬 Chat
   │    ├── ⏱️ Resolução SLA
   │    ├── 👤 Performance de Agentes
   │    ├── ✅ CSAT/NPS
   │    └── 📊 Volume de Mensagens
   │
   ├── 🤖 IA
   │    ├── 💰 Custos de IA
   │    └── 🚀 Performance do Autopilot
   │
   ├── 💳 Billing
   │    └── 📄 Relatório Financeiro
   │
   ├── 👥 Equipe
   │    └── 📊 Atividades da Equipe
   │
   └── ⚙️ Configurações
        ├── 📅 Agendar relatórios
        └── 📤 Exportar padrão
```

#### RBAC (Permissões Reports)

| Permissão | Agente | Admin | Billing | Super Admin |
|-----------|--------|-------|---------|-------------|
| reports.viewCrm | ✅ | ✅ | ❌ | ✅ |
| reports.viewChat | ✅ | ✅ | ❌ | ✅ |
| reports.viewAi | ❌ | ✅ | ❌ | ✅ |
| reports.viewBilling | ❌ | ❌ | ✅ | ✅ |
| reports.viewAdmin | ❌ | ❌ | ❌ | ✅ |
| reports.export | ✅ | ✅ | ✅ | ✅ |

#### Planos e Acesso

| Funcionalidade | Free | Starter | Professional | Enterprise |
|----------------|------|---------|--------------|------------|
| Relatórios CRM | 1 | 3 | 6 | Todos |
| Relatórios Chat | 1 | 2 | 4 | Todos |
| Relatórios AI | 0 | 0 | 2 | Todos |
| Relatórios Billing | 0 | 0 | 1 | Todos |
| Filtros | Básico | Básico | Avançado | Avançado |
| Exportação | ❌ | CSV | CSV + PDF | CSV + PDF |
| Agendamento | ❌ | ❌ | Mensal | Semanal |

#### Detalhamento dos 14 Relatórios

**CRM (6 relatórios):**
1. **Sales Funnel** - Conversões por etapa do funil
2. **Revenue Sales** - Receita por período (dia/semana/mês/ano)
3. **Salesperson Performance** - Performance individual de vendedores
4. **Loss Reason Analysis** - Análise de motivos de perda
5. **Product Performance** - Performance por produto/serviço
6. **Contact CRM** - Relatório detalhado de contatos

**Chat (4 relatórios):**
7. **SLA Resolution** - Tempo de primeira resposta e resolução
8. **Agent Performance** - Tickets atendidos, tempo, CSAT por agente
9. **CSAT/NPS** - Satisfação do cliente e Net Promoter Score
10. **Chat Volume** - Volume de mensagens por canal/período

**AI (2 relatórios):**
11. **AI Usage Cost** - Custos de tokens por agente/período
12. **Autopilot Performance** - Taxa de automação, escalações

**Billing (1 relatório):**
13. **Billing Report** - Faturas, pagamentos, inadimplência

**Admin (1 relatório):**
14. **Team Activity** - Log de atividades da equipe

---

## 5. Stack de IA

### 5.1 Provedores Suportados

| Provedor | Modelos | Uso | Status |
|----------|---------|-----|--------|
| **OpenAI** | GPT-4o, text-embedding-3-small | Completions, RAG | Produção |
| **Anthropic** | Claude 3.5 | Completions | Configurável |
| **Google** | Gemini Pro | Completions | Configurável |

### 5.2 Arquitetura RAG

```
Upload → Extract Text → Chunking (~500 tokens, 50 overlap)
    → OpenAI Embeddings (ada-002, 1536 dim) → PostgreSQL + pgvector
    → Busca Semântica (cosine < 0.8) → Contexto para LLM
```

### 5.3 Tool Calling

Tipos de tools suportadas:
- **HTTP Calls** - Chamadas a APIs externas
- **Functions** - Funções customizadas

---

## 6. Canais de Chat

### 6.1 Provedores Suportados

| Provedor | Tipo | Status | Endpoint Gateway |
|----------|------|--------|------------------|
| **UazAPI** | WhatsApp | Produção | `/webhooks/uazapi/{token}` |
| **Z-API** | WhatsApp | Produção | `/webhooks/zapi/{token}` |
| **Meta WhatsApp Cloud** | WhatsApp | Implementação | `/webhooks/meta/{token}` |

### 6.2 Tipos de Mensagem Suportadas

| Tipo | WhatsApp | Telegram | WebChat |
|------|----------|----------|---------|
| Text | ✅ | ✅ | ✅ |
| Image | ✅ | ✅ | ✅ |
| Audio | ✅ | ❌ | ✅ |
| Video | ✅ | ❌ | ✅ |
| Document | ✅ | ✅ | ✅ |
| Sticker | ✅ | ❌ | ❌ |
| Location | ✅ | ❌ | ✅ |
| Contact | ✅ | ❌ | ✅ |
| Reaction | ✅ | ❌ | ❌ |
| Template | ✅ | ❌ | ❌ |

### 6.3 WebChat Widget

**Funcionalidades**
- Embed via script
- Cores customizáveis
- Chat em tempo real
- Pre-chat form
- Avaliação CSAT inline
- Auto-reply com IA

---

## 7. Modelo de Dados

### 7.1 Entidades Principais

```
users
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, email, password, avatar_url
  ├── role_id (fk)
  └── timestamps

tenants
  ├── id (uuid)
  ├── name, domain, logo_url
  ├── plan_id (fk)
  ├── status, settings (json)
  ├── asaas_customer_id
  └── timestamps

contacts
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, email, phone, avatar_url
  ├── company_id (fk, nullable)
  ├── custom_fields (json)
  └── timestamps

companies
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, document, address, phone, email
  └── timestamps

negotiations
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── title, value, currency
  ├── funnel_step_id (fk)
  ├── contact_id (fk)
  ├── user_id (fk)
  ├── status
  └── timestamps

proposals
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── negotiation_id (fk)
  ├── title, content (markdown)
  ├── status
  └── timestamps

chat_channels
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, provider
  ├── provider_token, provider_token_secret
  ├── webhook_url
  ├── status
  └── timestamps

chat_tickets
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── channel_id (fk)
  ├── external_id
  ├── contact_id (fk)
  ├── status, priority
  ├── assigned_user_id (fk)
  ├── sentiment, csat_score
  └── timestamps

chat_messages
  ├── id (uuid)
  ├── ticket_id (fk)
  ├── tenant_id (fk)
  ├── direction, type, content
  ├── provider_message_id
  ├── is_from_bot
  └── timestamps

ai_agents
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, type, model_id
  ├── system_prompt
  ├── max_tokens, temperature
  ├── token_budget_per_run
  └── timestamps

ai_knowledge
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── name, file_type
  ├── file_path, file_url
  ├── embedding_status
  └── timestamps

ai_knowledge_chunks
  ├── id (uuid)
  ├── knowledge_id (fk)
  ├── content
  ├── embedding (vector)
  └── token_count

billing_invoices
  ├── id (uuid)
  ├── tenant_id (fk)
  ├── asaas_id, value
  ├── status, billing_type
  ├── due_date, payment_date
  └── timestamps

plans
  ├── id (uuid)
  ├── name, description
  ├── price, trial_days
  ├── features (json), limits (json)
  └── is_active
```

---

## 8. API Endpoints

### 8.1 Auth

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/api/auth/login` | Login email/senha |
| POST | `/api/auth/login-with-2fa` | Login com 2FA |
| POST | `/api/auth/refresh` | Refresh token |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/auth/profile` | Perfil usuário |
| PUT | `/api/auth/profile` | Atualizar perfil |

### 8.2 CRM

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET/POST | `/api/crm/contacts` | CRUD Contatos |
| GET/POST | `/api/crm/companies` | CRUD Empresas |
| GET/POST | `/api/crm/negotiations` | CRUD Negociações |
| POST | `/api/crm/negotiations/{id}/move` | Mover Kanban |
| POST | `/api/crm/negotiations/{id}/won` | Marcar ganha |
| POST | `/api/crm/negotiations/{id}/lost` | Marcar perdida |
| GET/POST | `/api/crm/proposals` | CRUD Propostas |
| GET/POST | `/api/crm/events` | CRUD Eventos |
| GET/POST | `/api/crm/funnels` | CRUD Funis |

### 8.3 Chat

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET/POST | `/api/chat/tickets` | Listar tickets |
| GET | `/api/chat/tickets/{id}` | Detalhe ticket |
| POST | `/api/chat/tickets/{id}/messages` | Enviar mensagem |
| POST | `/api/chat/tickets/{id}/close` | Fechar ticket |
| POST | `/api/chat/tickets/{id}/transfer` | Transferir |
| POST | `/api/chat/tickets/{id}/evaluate` | Avaliar CSAT |
| GET/POST | `/api/chat/channels` | CRUD Canais |
| POST | `/api/chat/channels/{id}/connect` | Conectar |
| POST | `/api/chat/channels/{id}/disconnect` | Desconectar |
| GET/POST | `/api/chat/quick-answers` | CRUD Respostas Rápidas |
| GET/POST | `/api/chat/auto-reply/rules` | CRUD Auto-Reply |
| POST | `/api/chat/webhooks/{provider}/{token}` | Webhook |

### 8.4 AI

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET/POST | `/api/ai/agents` | CRUD Agentes |
| POST | `/api/ai/agents/{id}/test` | Testar agente |
| GET/POST | `/api/ai/knowledge` | CRUD Knowledge Base |
| POST | `/api/ai/knowledge/{id}/reindex` | Reindexar |
| GET/POST | `/api/ai/prompts` | CRUD Prompts |
| GET | `/api/ai/usage/summary` | Resumo de uso |
| GET | `/api/ai/usage/costs` | Custos por período |

### 8.5 Billing

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/billing/invoices` | Listar faturas |
| GET | `/api/billing/invoices/{id}` | Detalhe fatura |
| POST | `/api/billing/invoices/{id}/pay` | Pagar fatura |
| GET | `/api/billing/budget` | Ver budget |

### 8.6 Dashboard

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/dashboard` | Métricas consolidadas |

### 8.7 Reports

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/reports/{type}` | Buscar relatório |
| POST | `/api/reports/{type}/export` | Exportar |

### 8.8 Platform (SuperAdmin)

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET/POST | `/api/admin/tenants` | CRUD Tenants |
| GET/POST | `/api/admin/plans` | CRUD Planos |
| GET | `/api/admin/queues/health` | Health filas |

---

## 9. Requisitos Funcionais

### 9.1 Autenticação e Autorização

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-AUTH-001 | Login com email e senha | Must |
| RF-AUTH-002 | Autenticação em 2 fatores (TOTP) | Must |
| RF-AUTH-003 | Tokens de acesso (Sanctum) | Must |
| RF-AUTH-004 | RBAC com permissões granulares | Must |
| RF-AUTH-005 | Sessões múltiplas por usuário | Should |
| RF-AUTH-006 | Recuperação de senha por email | Must |

### 9.2 CRM

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-CRM-001 | CRUD completo de contatos | Must |
| RF-CRM-002 | CRUD completo de empresas | Must |
| RF-CRM-003 | Pipeline Kanban com drag-and-drop | Must |
| RF-CRM-004 | CRUD de negociações com valores | Must |
| RF-CRM-005 | Geração de propostas com link público | Must |
| RF-CRM-006 | Agenda com eventos vinculados | Should |
| RF-CRM-007 | Campos customizados por tenant | Could |
| RF-CRM-008 | Tags e segmentação de contatos | Should |
| RF-CRM-009 | Importação em massa (CSV) | Could |
| RF-CRM-010 | Funis customizáveis | Should |

### 9.3 Chat

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-CHAT-001 | Inbox unificada de tickets | Must |
| RF-CHAT-002 | Chat em tempo real (WebSocket) | Must |
| RF-CHAT-003 | Envio de mensagens de texto | Must |
| RF-CHAT-004 | Envio de mídias (img, audio, video, doc) | Must |
| RF-CHAT-005 | Transferência entre agentes | Must |
| RF-CHAT-006 | Fechamento de tickets | Must |
| RF-CHAT-007 | Análise de sentimento | Should |
| RF-CHAT-008 | Avaliação CSAT | Should |
| RF-CHAT-009 | Respostas rápidas (Quick Answers) | Must |
| RF-CHAT-010 | Auto-reply por regras | Must |
| RF-CHAT-011 | Conexão com WhatsApp via API | Must |
| RF-CHAT-012 | Webhook para mensagens | Must |
| RF-CHAT-013 | Human Takeover (bot → humano) | Must |

### 9.4 AI

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-AI-001 | Agentes de IA pré-configurados | Must |
| RF-AI-002 | Base de conhecimento (RAG) | Must |
| RF-AI-003 | Geração de embeddings | Must |
| RF-AI-004 | Busca semântica em documentos | Must |
| RF-AI-005 | Autopilot em tickets | Must |
| RF-AI-006 | Tool calling (HTTP, functions) | Should |
| RF-AI-007 | Templates de prompts | Should |
| RF-AI-008 | Métricas de uso e custos | Must |
| RF-AI-009 | Limite de tokens por execução | Should |
| RF-AI-010 | Integração com OpenAI | Must |
| RF-AI-011 | Integração com Anthropic | Could |
| RF-AI-012 | Integração com Google Gemini | Could |

### 9.5 Billing

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-BILL-001 | Geração automática de faturas | Must |
| RF-BILL-002 | Pagamento via PIX | Must |
| RF-BILL-003 | Pagamento via cartão | Should |
| RF-BILL-004 | Gerenciamento de inadimplência | Must |
| RF-BILL-005 | Troca de planos | Should |
| RF-BILL-006 | Budget e alertas de uso | Should |

### 9.6 Dashboard

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-DASH-001 | KPIs consolidados | Must |
| RF-DASH-002 | Gráfico de receita | Must |
| RF-DASH-003 | Funil de vendas | Must |
| RF-DASH-004 | Métricas de tickets | Must |
| RF-DASH-005 | Atividades recentes | Should |

### 9.7 Reports

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-REP-001 | 14 tipos de relatórios | Must |
| RF-REP-002 | Filtros por período | Must |
| RF-REP-003 | Exportação CSV | Must |
| RF-REP-004 | Exportação PDF | Should |

### 9.8 Platform

| ID | Requisito | Prioridade |
|----|-----------|------------|
| RF-PLAT-001 | CRUD de tenants | Must |
| RF-PLAT-002 | CRUD de planos | Must |
| RF-PLAT-003 | Onboarding de tenants | Must |
| RF-PLAT-004 | Suspend/cancel tenants | Must |

---

## 10. Requisitos Não-Funcionais

### 10.1 Performance

| Métrica | Meta | Método de Verificação |
|---------|------|----------------------|
| Tempo de resposta API (p95) | < 200ms | APM |
| Tempo de carregamento página | < 2s | Lighthouse |
| WebSocket latency | < 100ms | Socket.io metrics |
| Rebuild do Angular | < 30s | Build output |
| Migration execution | < 5s por tabela | Benchmarks |

### 10.2 Escalabilidade

| Métrica | Meta |
|---------|------|
| Usuários simultâneos | 1000+ por tenant |
| Tickets por dia | 10.000+ |
| Mensagens por segundo | 100+ |

### 10.3 Disponibilidade

| Métrica | Meta |
|---------|------|
| Uptime | 99.5% |
| Planned maintenance | Janelas de baixa utilização |
| SLA de incidentes | P1 < 4h, P2 < 8h |

### 10.4 Segurança

| Requisito | Implementação |
|-----------|---------------|
| HTTPS em todo lugar | TLS 1.3 |
| Tokens expiram | 1h (access), 7d (refresh) |
| Senhas hasheadas | bcrypt |
| 2FA | TOTP |
| RBAC | Spatie Permission |
| Audit log | Todas operações |
| Secrets | Nunca em código (env vars) |

### 10.5 Observabilidade

| Componente | Ferramenta |
|------------|-------------|
| Metrics | Prometheus + Grafana |
| Errors | Sentry |
| Logs | Structured JSON logs |
| Traces | OpenTelemetry (gateway) |

### 10.6 Compatibilidade

| Browser | Versão Mínima |
|---------|---------------|
| Chrome | 90+ |
| Firefox | 88+ |
| Safari | 14+ |
| Edge | 90+ |

### 10.7 Responsividade

| Dispositivo | Breakpoints |
|-------------|-------------|
| Mobile | < 640px |
| Tablet | 640px - 1024px |
| Desktop | > 1024px |

---

## 11. PRDs por Módulo

Para detalhes completos de cada módulo, consulte:

| Módulo | PRD | Status |
|--------|-----|--------|
| Auth | [PRD-AUTH-001](./PRD-AUTH-001-autenticacao-multi-tenant.md) | Aprovado |
| Chat | [PRD-CHAT-001](./PRD-CHAT-001.md) | Aprovado |
| CRM | [PRD-CRM-001](./PRD-CRM-001.md) | Aprovado |
| AI | [PRD-AI-001](./PRD-AI-001.md) | Aprovado |
| Billing | [PRD-BILLING-001](./PRD-BILLING-001.md) | Aprovado |
| Dashboard | [PRD-DASHBOARD-001](./PRD-DASHBOARD-001.md) | Aprovado |
| Reports | [PRD-REPORTS-001](./PRD-REPORTS-001.md) | Aprovado |
| Platform | [PRD-PLATFORM-001](./PRD-PLATFORM-001.md) | Aprovado |
| Gateway | [PRD-GATEWAY-001](./PRD-GATEWAY-001.md) | Aprovado |
| Configuration | [PRD-CONFIG-001](./PRD-CONFIG-001.md) | Aprovado |
| Knowledge Base | [PRD-KNOWLEDGE-001](./PRD-KNOWLEDGE-001.md) | Aprovado |
| Tenants | [PRD-TENANTS-001](./PRD-TENANTS-001.md) | Aprovado |
| UazAPI | [PRD-UAZAPI-001](./PRD-UAZAPI-001.md) | Aprovado |
| Meta WhatsApp | [FEAT-039](./../FEATURES/PLAN-039-meta-whatsapp-business-api.md) | Implementando |
| Telegram | [FEAT-041](./../FEATURES/PLAN-041-telegram-integration.md) | Planejado |
| WebChat Widget | [FEAT-040](./../FEATURES/FEAT-040-webchat-widget.md) | Implementando |

---

## 12. Referências Cruzadas

### 12.1 Estrutura de Pastas (Backend)

```
api/src/Domain/
├── Ai/
│   ├── Actions/
│   ├── Contracts/
│   ├── DTOs/
│   ├── Enums/
│   ├── Events/
│   ├── Http/Controllers/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Models/
│   ├── Policies/
│   └── Repositories/
├── Auth/
├── Billing/
├── Chat/
│   ├── Actions/
│   ├── Enums/
│   ├── Events/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Models/
│   ├── Repositories/
│   └── Services/
├── Configuration/
├── CRM/
│   ├── Http/Controllers/
│   ├── Models/
│   └── Services/
├── Dashboard/
├── Platform/
├── Reports/
└── Shared/
    ├── Traits/
    ├── Services/
    └── Scopes/
```

### 12.2 Estrutura de Pastas (Frontend)

```
app/src/app/
├── pages/
│   ├── admin/
│   ├── ai/
│   ├── auth/
│   ├── billing/
│   ├── chat/
│   │   ├── ticket/
│   │   ├── channel/
│   │   └── components/
│   ├── configuration/
│   ├── crm/
│   │   ├── contacts/
│   │   ├── companies/
│   │   └── negotiations/
│   ├── dashboard/
│   ├── platform/
│   ├── public/
│   ├── reports/
│   ├── settings/
│   ├── ui-kit/
│   └── webchat/
├── shared/
│   └── components/ (35+)
├── core/
│   ├── guards/
│   ├── models/
│   └── services/
└── layout/
    ├── main-layout/
    └── auth-layout/
```

---

## Aprovações

| Papel | Nome | Data |
|-------|------|------|
| Product Manager | | 2026-04-15 |
| Tech Lead | | 2026-04-15 |
| Architecture | | 2026-04-15 |
