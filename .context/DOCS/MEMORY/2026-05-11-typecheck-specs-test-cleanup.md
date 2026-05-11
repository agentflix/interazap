# Memory: Typecheck de specs no Gateway e limpeza de testes API

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão + Aprendizado |
| **Data** | 2026-05-11 |
| **Autor** | BACKEND Agent |
| **Contexto** | TASK-GW-TEST-001 + TASK-API-TEST-001 |
| **Tags** | gateway, testing, typescript, php, pest, typecheck |

---

## Situação

47 erros de typecheck no Gateway (specs não eram verificadas pelo tsc) e testes API com `dump()` poluindo output + teste de rate limit permanentemente skipado.

---

## Decisões

### 1. Gateway: tsconfig.spec.json dedicado
- Criar `tsconfig.spec.json` que inclui `src/**/*.ts` (specs inclusas)
- Adicionar script `typecheck:specs` no package.json
- Specs agora são verificadas no CI, não só em runtime Jest

### 2. Mock de handshake readonly em Socket.IO
- `Socket.handshake` é readonly — usar `(mockSocket as any).handshake =` para testes
- Alternativa `Object.defineProperty` é verbosa demais

### 3. Axios mock em Jest
- `axios as jest.Mocked<typeof axios>` não expõe `mockResolvedValue`
- Usar `axios as unknown as jest.Mock` para acesso direto aos métodos de mock

### 4. PHP dump() → info() com env guard
- Substituir `dump()` por `info()` com `env('DEBUG_N_PLUS1')`
- Debug disponível localmente sem poluir CI

### 5. QueueHealthService final — sem mock
- Classe marcada como `final` não pode ser mockada com Mockery
- Testes que forçavam estados agora usam service real
- Para testes que precisam de estado específico, considerar interface ou remover `final`

---

## Consequências

### Positivas
- Specs do Gateway agora são type-checked — drift de contratos é detectado em CI
- Testes API rodam sem ruído de dump()
- Rate limit test está ativo e valida o comportamento real
- Zero testes skipados no Gateway e QueueHealthController

### Trade-offs
- Testes de QueueHealthController que mockavam estados específicos agora testam estrutura genérica
- N+1 tests ainda falham (thresholds desatualizados) — requer fix separado nos controllers

---

## Referências
- Task: Plano Correção (TASK-GW-TEST-001, TASK-API-TEST-001)
- Gateway: `gateway/tsconfig.spec.json`
- API: `api/tests/Feature/Platform/QueueHealthControllerTest.php`
