# Validate Task (PREVC: Validation)

Uso: `/validate [feature] [TASK-NNN]`

## Processo

1. **Executar gates** (verificar `validation-flow.md`):
   - Backend: `./vendor/bin/pest` + PHPStan + Pint
   - Frontend: `npm run gate:lint` + `npm run gate:test` + `npm run gate:build`
2. **Verificar critérios de aceite** (seção E da task)
3. **Documentar evidências**

## Gates

### Backend (Laravel)
```bash
cd api
./vendor/bin/pest --filter=Unit
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
./vendor/bin/pest
```

### Frontend (Angular)
```bash
cd app
npm run gate:lint
npm run gate:test
npm run gate:build
```

## Output

```
✅ TASK-X.Y.Z validada

Evidências:
- [ ] Gate 1: ✅
- [ ] Gate 2: ✅
- [ ] Critério E-1: ✅
- [ ] Critério E-2: ✅

Próximo passo: /confirm-task [feature] [TASK-NNN]
```

Se falhar → volta para `/implement-task [feature] [TASK-NNN]`
