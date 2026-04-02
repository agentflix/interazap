# Resumo do Projeto — InteraZap

## O que é

InteraZap é um **SaaS multi-tenant** para comunicação com clientes via WhatsApp, potencializado por inteligência artificial. A plataforma permite que empresas gerenciem conversas, automatizem respostas com IA, processem pagamentos, e administrem seu CRM — tudo em uma interface unificada.

## Stack Tecnológico

| Camada           | Tecnologia                                   | Versão   |
| ---------------- | -------------------------------------------- | -------- |
| Frontend         | Angular + TypeScript + Tailwind CSS          | 20 / 5.9 |
| Gateway          | NestJS + TypeScript + BullMQ + WebSocket     | 11 / 5.7 |
| Backend          | Laravel + PHP + Sanctum + Spatie Permissions | 12 / 8.3 |
| Banco de dados   | PostgreSQL + pgvector                        | 17       |
| Cache/Fila       | Redis (Streams, PubSub, Cache)               | 7        |
| Testes           | Pest (BE) / Vitest (FE) / Jest (GW)          | -        |
| Análise estática | PHPStan L6 + Larastan + Rector               | -        |
| Monitoramento    | Prometheus + Grafana + Alertmanager          | -        |
| Container        | Docker Compose                               | -        |
| Desktop          | Electron                                     | -        |

## Arquitetura

### Visão Geral

O sistema segue uma arquitetura em **3 camadas** com separação clara de responsabilidades:

```
Frontend (Angular) → Gateway (NestJS) → Backend (Laravel) → PostgreSQL
```

### Patterns

- **DDD (Domain-Driven Design)**: Backend organizado em domínios com Controllers, DTOs, Actions, Resources, Models, Policies
- **Multi-tenant**: Isolamento por tenant com schema/filtros no banco de dados. Trait `BelongsToTenant` em todos os models
- **UUID Primary Keys**: Todos os IDs são UUIDs, nunca auto-increment
- **Event-Driven**: Comunicação assíncrona via Redis Streams, BullMQ e WebSocket

### Gateway

O NestJS atua como **API relay** entre o frontend e o backend Laravel. Responsável por:

- WebSocket management (Socket.io)
- Processamento de webhooks
- Circuit breaker em chamadas externas
- Processamento assíncrono (BullMQ)

## Módulos

O projeto possui **11 módulos de domínio**:

| Módulo            | Descrição                                                 |
| ----------------- | --------------------------------------------------------- |
| **Ai**            | Motor de IA — embeddings, RAG, completions via OpenAI     |
| **Auth**          | Autenticação (Sanctum) e autorização (Spatie Permissions) |
| **Billing**       | Faturamento e pagamentos via Asaas                        |
| **Chat**          | Conversas em tempo real via WhatsApp                      |
| **Configuration** | Configurações de tenant, planos, preferências             |
| **CRM**           | Gestão de contatos, empresas, pipelines, atividades       |
| **Dashboard**     | Métricas, KPIs e visões gerais                            |
| **Gateway**       | Lógica de relay, WebSocket, webhooks                      |
| **Platform**      | Gestão da plataforma, tenants, administração global       |
| **Reports**       | Relatórios e exportações                                  |
| **Shared**        | Código compartilhado, traits, helpers, base classes       |

## Integrações Externas

| Integração    | Propósito                         | Provider                   |
| ------------- | --------------------------------- | -------------------------- |
| WhatsApp API  | Envio/recebimento de mensagens    | Z-API / UazAPI             |
| Pagamentos    | Cobrança e faturamento            | Asaas                      |
| IA            | Embeddings, completions, RAG      | OpenAI                     |
| Real-time     | WebSocket para chat em tempo real | Laravel Reverb + Socket.io |
| Monitoramento | Métricas e alertas                | Prometheus + Grafana       |

## Estado Atual

- **Fase**: Desenvolvimento ativo
- **Módulos**: 11 módulos em progresso, todos com estrutura DDD
- **Frontend**: 35+ componentes shared reutilizáveis
- **Infraestrutura**: Docker Compose para dev, Ansible para deploy
- **Monitoramento**: Stack Prometheus + Grafana configurada
- **Desktop**: Electron wrapper disponível

## Referências

| Item                        | Path                                  |
| --------------------------- | ------------------------------------- |
| Contrato de desenvolvimento | `AGENTS.md`                           |
| Arquitetura                 | `.context/ARCHITECTURE/`              |
| Workflow                    | `.context/WORKFLOW/`                  |
| Módulos (YAML)              | `.context/WORKFLOW/modules.yaml`      |
| Dependências (YAML)         | `.context/WORKFLOW/dependencies.yaml` |
