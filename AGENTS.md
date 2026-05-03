# AGENTS.md — InteraZap AI Kernel

Você é um agente de desenvolvimento sênior trabalhando neste repositório.

Responda em português brasileiro.

## Projeto

**InteraZap** — Plataforma de automação de WhatsApp com CRM integrado, gateway de integrações (UazAPI, Z-API, Asaas), e AI para classificação e streaming de mensagens.

## Stack Detectada

| Módulo  | Stack                                                           |
| ------- | --------------------------------------------------------------- |
| API     | Laravel 12 / PHP 8.3 / PostgreSQL 17 / pgvector / Redis 7       |
| Gateway | NestJS 11 / TypeScript 5.7 / BullMQ / Redis Streams / WebSocket |
| App     | Angular 19 / Capacitor / Ionic                                  |

## Regras Essenciais

1. Não carregue contexto profundo por padrão
2. Use contexto mínimo até precisar de mais
3. Preserve padrões existentes em cada módulo
4. Leia `AGENTS.md` do módulo específico antes de trabalhar nele
5. Quando terminar a tarefa completamente rode gates para verificar que esta compilando ou se nenhum teste quebr

## Fluxo de Trabalho

| Situação                           | Ação                                                        |
| ---------------------------------- | ----------------------------------------------------------- |
| Bug pequeno, ajuste simples        | Fast Path: Detect → Fix → Validate → Summary                |
| Feature média/grande               | PREVC: Planning → Review → Execution → Validation → Confirm |
| Task de implementação              | T.A.C.E: Task / Area / Behavior / Evidence                  |
| Decisão técnica, padrão recorrente | Consultar `.context/DOCS/MEMORY/`                           |

## Onde Encontrar Contexto

| Necessidade          | Arquivo                                    |
| -------------------- | ------------------------------------------ |
| Entender projeto     | `.context/ARCHITECTURE/project-brain.yaml` |
| Decisão arquitetural | `.context/ARCHITECTURE/modules.yaml`       |
| Workflow PREVC/TACE  | `.context/WORKFLOW/`                       |
| Decisões passadas    | `.context/DOCS/MEMORY/`                    |
| Trabalhar na API     | `api/AGENTS.md`                            |
| Trabalhar no Gateway | `gateway/AGENTS.md`                        |

## Validação (Mandatorio)

- API: `composer gate:all` (format → analyse → test → refactor)
- Gateway: `pnpm lint && pnpm test`
- App: verificar conforme stack Angular

## Não Registre

- Logs operacionais
- Tasks pequenas concluídas
- Alterações triviais
- Histórico de ações
