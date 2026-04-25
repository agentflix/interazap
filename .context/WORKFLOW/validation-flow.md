# Validation Flow — Gates de Qualidade

> Gates inegociáveis para o InteraZap. Qualquer falha bloqueia o merge.

## Gates por Camada

### Backend Gates (API)

```bash
# 1. Análise estática
cd api && ./vendor/bin/pest --filter=Unit
echo "✅ Unit tests passing"

# 2. Code style
cd api && ./vendor/bin/pint --test
echo "✅ Pint (code style) passing"

# 3. PHPStan
cd api && ./vendor/bin/phpstan analyse
echo "✅ PHPStan passing"

# 4. Tests
cd api && ./vendor/bin/pest
echo "✅ All Pest tests passing"
```

### Frontend Gates (Angular)

```bash
cd app

# 1. Lint
npm run gate:lint
echo "✅ ESLint passing"

# 2. Tests
npm run gate:test
echo "✅ Angular tests passing"

# 3. Build
npm run gate:build
echo "✅ Build passing"
```

### Integration Gates

```bash
# 1. Type check
cd app && npx tsc --noEmit
echo "✅ TypeScript passing"

# 2. E2E tests (se aplicável)
npx playwright test
echo "✅ E2E tests passing"
```

---

## Critérios de Aceite por Tipo de Task

### Task Backend (Laravel)
- [ ] Testes unitários passando (100%)
- [ ] Testes de Feature passando
- [ ] PHPStan level 5+ sem errors
- [ ] Pint sem violations
- [ ] Migration reversible
- [ ] Policy testada

### Task Frontend (Angular)
- [ ] Testes de componente passando
- [ ] ESLint: 0 errors, 0 warnings
- [ ] Build succeeds (production)
- [ ] TypeScript: 0 errors
- [ ] Acessibilidade verificada (a11y)

### Task Database (Migrations)
- [ ] Migration up() funcional
- [ ] Migration down() funcional
- [ ] Índices criados corretamente
- [ ] Foreign keys com constraint nomeada
- [ ] Dados migrados (se aplicável)

---

## Fluxo de Validação

```
┌─────────────────┐
│  EXECUTION      │
│  (implementou)  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  VALIDATION     │
│                 │
│  ┌───────────┐  │
│  │Gate 1     │──┼──▶ FAIL ──▶ Volta pra EXECUTION
│  │Lint/Style │  │
│  └───────────┘  │
│  ┌───────────┐  │
│  │Gate 2     │──┼──▶ FAIL ──▶ Volta pra EXECUTION
│  │Tests      │  │
│  └───────────┘  │
│  ┌───────────┐  │
│  │Gate 3     │──┼──▶ FAIL ──▶ Volta pra EXECUTION
│  │Build      │  │
│  └───────────┘  │
│                 │
│  ALL PASS ──▶  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  CONFIRM        │
│                 │
│  - CHANGELOG    │
│  - MEMORY       │
│  - project-state│
└─────────────────┘
```

---

## Status de Gates

| Gate | Status | Falha | Ação |
|------|--------|-------|------|
| Lint/Style | ✅ Pass | ❌ Fail | Voltar para EXECUTION |
| Tests | ✅ Pass | ❌ Fail | Voltar para EXECUTION |
| Build | ✅ Pass | ❌ Fail | Voltar para EXECUTION |
| Type Check | ✅ Pass | ❌ Fail | Voltar para EXECUTION |

---

## Regras de Exceção

- **Hotfix crítico:** Pode pular gates com aprovação do ARCHITECT
- **Migration sem lógica:** Pode pular testes de feature
- **Config-only:** Pode pular build (se aplicável)

## Comando de Validação

```bash
# Validar task individual
/validate [feature] [TASK-NNN]

# Validar fase inteira
/validate-phase [numero-fase]

# Executar gates manualmente
cd api && ./vendor/bin/pest
cd app && npm run gate:all
```
