# Validar Task (PREVC: Validation)

Uso: `/validate [feature] [TASK-NNN]`

## Processo (@QA)

1. Executar gates do workspace afetado:

```bash
# api/
cd api && composer gate:all

# gateway/
pnpm --filter gateway lint && pnpm --filter gateway test && pnpm --filter gateway build

# app/
pnpm --filter app lint && pnpm --filter app test && pnpm --filter app build

# electron/
pnpm --filter electron build
```

2. Verificar critérios da seção **E (Evidência)** da task — 100% atendidos
3. Verificar critérios especiais se aplicável:
   - Multi-tenancy: teste de isolamento entre tenants
   - Webhook: idempotência via Redis, HMAC válido
   - Migration: `php artisan migrate --pretend` no `api/`
   - WebSocket: autenticação no handshake + eventos tipados

## Resultado

**Se PASSOU:**
```
Gates: ✅
Critérios E: ✅
Próximo: /confirm-task [feature] TASK-NNN
```

**Se FALHOU:**
```
Gate falhou: [qual gate, qual erro]
Status: ❌ Reprovada → volta para EXECUTION
Motivo: [detalhe]
```
