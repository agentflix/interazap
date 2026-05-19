# Validation Flow — InteraZap

> Gates reais da stack. Executar na ordem antes de qualquer CONFIRM.

## Backend (api/) — PHP 8.3 + Laravel 12

```bash
# Todos os gates de uma vez (recomendado):
cd api && composer gate:all

# Individualmente:
composer format     # Laravel Pint (auto-fix)
composer analyse    # PHPStan L6 + Larastan
composer test       # Pest (≥80% coverage)
# composer refactor # Rector (auto-refactor)
```

**Critérios de aprovação:**
- Zero erros PHPStan L6
- Todos os testes Pest passando (0 skipped)
- Cobertura ≥80%
- `declare(strict_types=1)` em todos os arquivos novos/editados
- Nenhuma violação de tenant isolation

---

## Gateway (gateway/) — TypeScript + NestJS 11

```bash
cd gateway && pnpm lint && pnpm test
```

**Critérios de aprovação:**
- Zero erros ESLint
- Todos os testes Jest passando (0 skipped)
- Cobertura ≥80%
- Webhook handlers com ACK < 150ms verificado
- Nenhum log de dados sensíveis

---

## Frontend (app/) — Angular 17 + Capacitor

```bash
cd app && pnpm lint && pnpm build && pnpm test
```

**Critérios de aprovação:**
- Zero erros Angular ESLint
- Build sem erros TypeScript
- Todos os testes Vitest passando
- Cobertura ≥80%

---

## Code Review Confiável (REVIEWER em subagent)

Executar APÓS todos os gates passarem:

```
1. Abrir subagent REVIEWER
2. Fornecer: feature doc + task T.A.C.E + diff do BUILDER
3. REVIEWER carrega: .context/skills/code-review-confiavel/SKILL.md
4. Executa 7 revisores conforme references/reviewers.md
5. Executa second pass + meta-review
6. Retorna: achados por severidade + risco residual
```

**Achados bloqueantes:** CRITICAL ou HIGH com evidência → volta para BUILDER
**Sem bloqueantes:** avançar para CONFIRM

---

## Checklist Pré-CONFIRM

```
Gates Backend:
  □ composer gate:all passou sem erros

Gates Gateway (se modificado):
  □ pnpm lint passou
  □ pnpm test passou (0 skipped)

Gates Frontend (se modificado):
  □ pnpm lint passou
  □ pnpm build passou
  □ pnpm test passou (0 skipped)

Code Review:
  □ REVIEWER executou code-review-confiavel em subagent
  □ Zero achados bloqueantes (CRITICAL/HIGH)
  □ Risco residual documentado (se houver MEDIUM)

Verificações manuais:
  □ Tenant isolation: dados do tenant X não visíveis para tenant Y
  □ Auth: endpoints protegidos com authorize()
  □ N+1: nenhuma query N+1 nova
  □ Secrets: nenhum token/senha/key no código ou logs
```
