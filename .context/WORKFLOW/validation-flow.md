# Validation Flow — InteraZap

Gates executados pelo REVIEWER (modo VALIDATION) após cada task implementada.

---

## API — Laravel 12 (api/)

```bash
# Lint/format (se configurado)
cd api && ./vendor/bin/pint --test

# Testes unitários e de integração
php artisan test

# Testes com coverage (opcional)
php artisan test --coverage

# Verificar migration sem erros
php artisan migrate:fresh --seed

# Static analysis (se configurado)
./vendor/bin/phpstan analyse
```

**Gates mínimos:** `php artisan test` deve passar sem falhas.

---

## Gateway — NestJS 11 (gateway/)

```bash
# Build (detecta erros de tipo TypeScript)
pnpm --filter gateway build

# Testes unitários
pnpm --filter gateway test

# Testes com coverage
pnpm --filter gateway test:cov

# Lint
pnpm --filter gateway lint
```

**Gates mínimos:** `pnpm --filter gateway build` + `pnpm --filter gateway test` sem falhas.

---

## App — Angular 20 (app/)

```bash
# Build de produção (detecta erros de tipo e template)
pnpm --filter app build

# Testes unitários
pnpm --filter app test

# Lint
pnpm --filter app lint
```

**Gates mínimos:** `pnpm --filter app build` + `pnpm --filter app test` sem falhas.

---

## Code Review — code-review-confiavel

```bash
# 1. Ler a skill
cat .context/skills/code-review-confiavel/SKILL.md

# 2. Abrir 7 subagents (um por revisor em references/reviewers.md)
# 3. Consolidar achados com severidade
# 4. Executar meta-review (descartar especulativos)
```

**Achados bloqueantes:** task volta para BUILDER com lista de correções.
**Sem bloqueantes:** avançar para CONFIRM.

---

## Verificação de Dependências (antes do commit)

```bash
# Verificar que gateway não importa módulos de DB diretamente
grep -r "pg\|postgres\|knex\|typeorm\|prisma" gateway/src/ --include="*.ts" | grep -v "node_modules"

# Verificar que migrations existem apenas em api/
find gateway/ -name "*migration*" -o -name "*migrate*" | grep -v "node_modules"
```

---

## Ordem de Execução

1. Gates isolados da task (BUILDER — testes do arquivo modificado)
2. `code-review-confiavel` em subagent (REVIEWER — 7 revisores)
3. Gates completos da camada afetada (REVIEWER)
4. Verificação de dependências (REVIEWER)
5. CONFIRM se tudo passou
