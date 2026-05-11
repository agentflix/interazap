# Memory: Admin de filas na API com auditoria e CORS Telegram restrito

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-11 |
| **Autor** | BACKEND |
| **Contexto** | FEAT-005 — Queue Admin Observability & Security |
| **Tags** | platform, queues, audit, cors, security, gateway |

---

## Situação

QueueAdminController existia sem auditoria, sem policy de acesso, e com rotas dinâmicas (`/{name}`) definidas antes de rotas estáticas (`/dlq`, `/circuits`), causando route collision. Gateway Telegram WebSocket usava `cors: { origin: '*' }`, permitindo conexão de qualquer origem.

---

## Decisão / Aprendizado

1. **Admin de filas fica na API, não no Gateway**: API é ponto autoritativo para operações administrativas. Gateway é executor interno (autenticado via `x-api-key`). App chama apenas Laravel.

2. **Toda ação mutante de fila DEVE ser auditada**: pause, resume, clean, DLQ retry/purge, circuit reset/open criam registro em `audit_logs` com user_id, tenant_id, event, queue identifier, action type, e gateway endpoint.

3. **Rotas estáticas ANTES de dinâmicas**: Laravel resolve rotas na ordem de registro. `/dlq` antes de `/{name}` para evitar que "dlq" seja capturado como nome de fila.

4. **CORS WebSocket por allowlist**: `TELEGRAM_WS_ALLOWED_ORIGINS` env var com fallback para `localhost, 127.0.0.1` em dev/test. Rejeita origens desconhecidas mesmo com JWT válido.

5. **Http::facade > HttpFactory injetada para testabilidade**: `Http::fake()` intercepta o facade, não instâncias injetadas de `HttpFactory`. Controller usa `Http::baseUrl()` para permitir mocking em testes.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Gateway expor admin de filas diretamente ao App | Quebra padrão "App → API → Gateway"; expõe gateway à internet |
| Usar middleware de audit em vez de chamada explícita | Perde contexto específico da ação (queue name, action type) |
| CORS `origin: true` (dynamic) no Socket.IO | Menos explícito; allowlist via env é auditável e versionável |

---

## Consequências

### Positivas
- Rastreabilidade completa de quem operou filas e quando
- Controle de acesso granular via `platform.queues.manage`
- Route collision eliminado
- WebSocket Telegram protegido contra origens desconhecidas
- 16 testes Pest + 7 testes Jest cobrindo auth, permissão, auditoria, CORS

### Negativas / Trade-offs
- `.env.bak` já esteve no histórico git (commit 6012032) — segredos potencialmente expostos. Rotação necessária em task separada.
- Gate `platform.queues.manage` precisa ser atribuído a roles existentes via seeder ou migration

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-005-queue-admin-observability-security.md`
- AuditLogger: `api/src/Domain/Shared/Services/AuditLogger.php`
- QueueAdminPolicy: `api/src/Domain/Platform/Policies/QueueAdminPolicy.php`
