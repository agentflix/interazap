# Feature: Queue Admin Observability & Security

## Metadados

| Campo | Valor |
|-------|-------|
| ID | FEAT-005 |
| Bounded Context | Platform |
| Workspaces afetados | api, gateway, app |
| Complexidade | M |
| Status | Concluída |
| Autor (PM) | BACKEND |
| Aberta em | 2026-05-11 |
| Fechada em | |

---

## Resumo

Adicionar auditoria e controle de acesso às operações administrativas de filas (pause, resume, clean, DLQ, circuit breaker), corrigir ordem de rotas para evitar conflito com rotas dinâmicas, restringir CORS do WebSocket Telegram a uma allowlist e validar remoção de arquivo sensível `.env.bak`.

---

## Objetivo de Negócio

Garantir rastreabilidade completa de quem operou filas, quando e o quê; prevenir acesso não autorizado; evitar que rotas estáticas sejam engolidas por rotas dinâmicas; e endurecer segurança do WebSocket Telegram contra origens desconhecidas.

---

## Bounded Context(s)

- `Platform` — QueueAdminController, políticas, auditoria
- `Shared` — AuditLogger service
- Gateway — Telegram WebSocket CORS

---

## Escopo

### Incluído

- [x] Injetar AuditLogger no QueueAdminController e logar todas as ações mutantes
- [x] Criar QueueAdminPolicy com permissão `platform.queues.manage`
- [x] Corrigir ordem das rotas: estáticas (/dlq, /circuits) antes de /{name}
- [x] Restringir CORS do Telegram WebSocket a allowlist via env
- [x] Validar .env.bak (não versionado, confirmar .gitignore)
- [x] Testes Pest para auth, permissão 403, e criação de audit_logs
- [x] Spec gateway para CORS allowlist

### Fora de Escopo

- Rotação de segredos (se .env.bak já esteve no histórico git)
- Limpeza de histórico git
- Alterações no schema de banco

---

## Critérios de Aceite

- [x] CA-1: Usuário não autenticado recebe 401 em qualquer rota de admin de filas
- [x] CA-2: Usuário sem permissão `platform.queues.manage` recebe 403
- [x] CA-3: Cada ação mutante (pause, resume, clean, DLQ retry/purge, circuit reset/open) cria registro em audit_logs
- [x] CA-4: Rota /admin/queues/dlq não é capturada por /{name}
- [x] CA-5: Rota /admin/queues/circuits não é capturada por /{name}
- [x] CA-6: Telegram WebSocket rejeita conexão de origem fora da allowlist
- [x] CA-7: .env.bak não está versionado e .gitignore cobre *.bak

---

## Dependências

- AuditLogger já existe em `Domain\Shared\Services\AuditLogger`
- Spatie Permissions já configurado
- Trait `BelongsToTenant` já disponível

---

## Riscos

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| N+1 em audit_logs? | Baixo | Queries diretas, sem relacionamentos |
| Route order bug? | Alto | Rotas estáticas antes de dinâmicas + teste |
| CORS break em dev? | Médio | Allowlist inclui localhost por padrão |

---

## Tasks

> Decomposição feita pelo ARCHITECT após Review.

---

## Histórico

- 2026-05-11: Criada por @BACKEND
- 2026-05-11: Concluída — 16 testes Pest + 7 testes Jest passando
