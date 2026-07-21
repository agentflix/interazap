# Validation Flow — InteraZap

Gates reais da stack. Executados pelo `/prevec-phase-close` (REVIEWER → `reviewer-code`).
Rode apenas os gates da camada tocada na fase; `composer gate:all` só na última fase.

---

## api — Laravel 12

```bash
# Fase intermediária — feedback rápido
cd api && composer gate:fast

# Última fase — obrigatório (format + analyse + test + refactor)
cd api && composer gate:all

# Isolar falha
cd api && composer format        # Pint (PSR-12)
cd api && composer format:test   # só verifica
cd api && composer analyse       # Larastan
cd api && composer analyse:changed
cd api && composer test          # Pest --parallel, exclui E2E
cd api && composer test:e2e      # suíte E2E, separada
cd api && composer refactor:dry  # Rector, sem escrever
cd api && composer test:coverage
cd api && composer gate:pci      # lint de dados de cartão em logs
```

**Mínimo para fechar a última fase:** `composer gate:all` verde.

---

## gateway — NestJS 11

```bash
pnpm --filter gateway test       # Jest
pnpm --filter gateway test:cov
pnpm --filter gateway test:e2e
pnpm --filter gateway build      # nest build — pega erro de tipo
pnpm --filter gateway lint       # eslint --fix
```

**Mínimo:** `test` + `test:e2e` + `build` sem falhas. (`test:e2e` passou a ser obrigatório aqui porque
`test` não roda mais `test/**/*.e2e-spec.ts` — esses specs sobem a `AppModule` real com BullMQ/ioredis
reais e vazavam conexão entre suítes paralelas do Jest unitário, causando flakiness cruzada
não-determinística. Ver `testPathIgnorePatterns` em `gateway/package.json`.)

---

## app — Angular 20

```bash
pnpm --filter app test:run       # Vitest, sem watch
pnpm --filter app test:coverage
pnpm --filter app build          # ng build — pega erro de tipo e de template
pnpm --filter app lint
```

**Mínimo:** `test:run` + `build` sem falhas.
Nunca use `pnpm --filter app test` em automação — entra em watch.

---

## Code review — code-review-confiavel

```bash
cat .context/skills/code-review-confiavel/SKILL.md
```

1. 7 subagents, um por revisor de `references/reviewers.md`
2. Consolidação dos achados por severidade
3. Meta-review descarta o especulativo (achado sem evidência não é achado)

**Bloqueante encontrado:** volta ao BUILDER com a lista. **Nenhum:** segue para o commit/PR.

---

## Verificação de fronteiras arquiteturais

```bash
# gateway não pode ter driver de banco
grep -rE "from '(pg|typeorm|prisma|knex)'" gateway/src || echo "OK — sem driver de banco"

# migrations só na api
find gateway app -name "*migration*" -not -path "*/node_modules/*" | grep . && echo "VIOLACAO" || echo "OK"

# frontend não fala com provedor de LLM
grep -rE "api\.openai\.com|generativelanguage" app/src && echo "VIOLACAO" || echo "OK"

# TypeScript sem any explícito nos arquivos tocados
git diff --name-only origin/develop...HEAD -- '*.ts' | xargs -r grep -n ": any" | grep . && echo "REVISAR" || echo "OK"
```

---

## Ordem de execução no phase-close

1. Testes da fase (camada tocada)
2. `code-review-confiavel` em subagent distinto — última fase
3. `composer gate:all` + build do gateway/app — última fase
4. Verificação de fronteiras arquiteturais
5. Commit da fase; PR quando a feature fecha
