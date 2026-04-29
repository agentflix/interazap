# Bearer Auth (Sanctum PAT) já existia no Auth domain

**Data:** 2026-04-26
**Contexto:** TASK-047.3 (FEAT-047 — mobile app)
**Tipo:** Descoberta / correção de premissa

## Descoberta

Ao iniciar TASK-047.3 (criar `AuthTokenController` para emitir Bearer), BACKEND
detectou que **endpoints `/api/auth/login` e `/api/auth/logout` JÁ EXISTEM** e
já emitem Personal Access Tokens via Sanctum.

### Evidência

- `api/src/Domain/Auth/Routes/auth.php:21-33` — rotas `auth.login` e `auth.logout` registradas
- `api/src/Domain/Auth/Actions/AuthLoginActions.php:255` — `$user->createToken('auth-token')->plainTextToken`
- `api/src/Domain/Auth/Actions/AuthLoginActions.php:204-211` — `$request->user()->currentAccessToken()->delete()`
- Suporte a 2FA challenge antes de emitir token
- Inactive-check (status != active → 403)
- `throttle:login` (5/min por **email** — mais seguro que `throttle:5,1` por IP)

## Decisão

**Estender, não duplicar.** Adicionar `device_name` opcional em `AuthLoginRequest`
e repassar em `createToken()`. Manter:

- Mesma rota
- Mesmo controller/action
- Mesmo throttle (por email)
- 2FA, inactive-check, formato de resposta

Criar `AuthTokenController` paralelo seria anti-pattern: 2º caminho de auth
divergindo de policies (2FA, throttle, inactive-check) — repete o erro que
evitamos com tenant em abilities.

## Lição

**Antes de criar qualquer endpoint, fazer grep da rota.** Convenção REST + DDD
faz com que rotas óbvias frequentemente já existam. Custo de descoberta: 5min
de grep. Custo de duplicação descoberta tarde: refactor de 6 arquivos +
divergência de comportamento.

## Impacto na FEAT-047

- TASK-047.3 reescrita: de "criar 6 arquivos novos" para "delta de ~15 LOC + testes"
- Frontend (TASK-047.5) **não** muda: continua chamando `POST /api/auth/login`
  com `device_name` adicional
- Token continua SEM abilities customizadas → respeita decisão MEMORY
  `2026-04-26-feat-047-mobile-architecture-decisions.md` (tenant via middleware)

## Referências cruzadas

- `.context/DOCS/TASKS/FEAT-047-tasks.md` (TASK-047.3 reescrita)
- `.context/DOCS/MEMORY/2026-04-26-feat-047-mobile-architecture-decisions.md`
