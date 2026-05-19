# Feature: Chat Externo — Validação de Tenant e Sessão

## Metadados
- ID: FEAT-001
- PRD: .context/DOCS/PRDS/0001-PRD-chat-externo-validacao-tenant-sessao.md
- Bounded Context: Chat (webchat público) + Platform (tenant info via query direta)
- Complexidade: M
- Status: ✅ Concluída

## Resumo

Ao entrar no chat externo público, validar se o tenant existe e está ativo, exibindo seu nome no cabeçalho do pré-chat e bloqueando com tela de erro se inválido. Ao restaurar sessão do `sessionStorage`, verificar no backend se o ticket ainda está aberto; se fechado, limpar a sessão e exibir o formulário de pré-chat limpo.

## Escopo

### Incluído
- [x] Novo endpoint público `GET /api/webchat/tenant/:tenantId` (Laravel) — retorna `{name}`
- [x] Proxy no Gateway NestJS para o novo endpoint
- [x] Proxy no Gateway NestJS para `GET /api/webchat/sessions/:id` (já existe no Laravel, sem proxy)
- [x] Refatorar `webchat-page.component.ts`: fluxo de validação no `ngOnInit`
- [x] Exibir nome do tenant no cabeçalho do pré-chat
- [x] Tela de erro quando tenant inválido/inativo
- [x] Limpar sessão do sessionStorage se ticket estiver fechado

### Fora de Escopo
- Autenticação do visitante
- Validação de plano/billing do tenant
- Múltiplos canais (apenas webchat público)
- Alteração no formulário de pré-chat existente

## Dependências
- Features: nenhuma
- Módulos: Chat, Platform (modelo `PlatformTenant` via query direta)
- Externas: nenhuma

## Critérios de Aceite
- [x] `GET /api/webchat/tenant/{uuid-invalido}` retorna 404
- [x] `GET /api/webchat/tenant/{tenant-valido}` retorna `{name: "Nome da Empresa"}`
- [x] Acessar `/chat/external/{uuid-invalido}` exibe tela de erro bloqueante
- [x] Acessar `/chat/external/{tenant-valido}` exibe nome da empresa no header do pré-chat
- [x] Com sessão de ticket fechado no sessionStorage + `?s=sessionId` na URL: exibe pré-chat limpo
- [x] Com sessão de ticket aberto no sessionStorage: restaura normalmente (comportamento atual)
- [x] Sem sessão no sessionStorage: exibe pré-chat normalmente (comportamento atual)

## Fases Estimadas
- [x] **Fase 1 — Planning** ✅
- [x] **Fase 3 — Backend**: endpoint `GET /api/webchat/tenant/:tenantId` (Laravel) + proxies Gateway ✅
- [x] **Fase 4 — Frontend**: refatorar `webchat-page.component`, signals, UI erro/nome tenant ✅
- [x] **Fase 5 — Integration**: verificar fluxo ponta a ponta ✅

## Arquivos Chave

| Arquivo | Mudança |
|---|---|
| `api/src/Domain/Chat/Routes/webchat.php` | Adicionar rota `GET /webchat/tenant/{tenantId}` |
| `api/src/Domain/Chat/Http/Controllers/WebChatTenantController.php` | NOVO — invokable, retorna `{name}` |
| `gateway/src/domains/realtime/services/webchat-proxy.service.ts` | Adicionar `getTenantInfo()` e `getSession()` |
| `gateway/src/domains/realtime/controllers/webchat.controller.ts` | Adicionar 2 endpoints GET |
| `app/src/app/pages/webchat/webchat.model.ts` | Adicionar `WebChatTenantInfo` e `WebChatSessionDetail` |
| `app/src/app/pages/webchat/services/webchat.service.ts` | Adicionar `getTenantInfo()` e `getSession()` |
| `app/src/app/pages/webchat/webchat-page.component.ts` | Refatorar `ngOnInit`, adicionar signals |
| `app/src/app/pages/webchat/webchat-page.component.html` | Tela de erro + nome do tenant |

## Decisões de Arquitetura

- `WebChatTenantController` no módulo Chat — query direta ao `PlatformTenant` sem Action/Resource (endpoint simples, sem regra de negócio complexa)
- Endpoint retorna apenas `{name}` — não expõe dados sensíveis
- Statuses de ticket fechado: `'closed'` | Aberto: `'open'`, `'pending'`, `'in_progress'`
- Fluxo Angular no `ngOnInit`: (1) validar tenant → (2) restaurar sessão → (3) verificar status ticket

## Tasks
> .context/DOCS/TASKS/chat-externo-validacao-tenant-sessao-tasks.md
> 8 tasks | 0 pendentes | 8 concluídas ✅

## Entrega
- **Commit:** `38b9c27`
- **Data:** 2026-05-19
- **Review:** Tier FULL (7 revisores) — 0 bloqueantes
- **Gates:** Gateway ✅ (121 suites, 1379 tests) | App ✅ (lint + build) | API ✅ (Pint + PHPStan)
- **PR Template:** `.context/.session/.archive/PR-chat-externo-validacao-tenant-sessao.md`
