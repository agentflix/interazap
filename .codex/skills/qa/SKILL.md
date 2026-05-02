---
name: qa
description: >
  Validar alterações, executar testes e verificar critérios de aceite.
  Use quando: validar feature implementada, verificar PR, garantir qualidade.
---

# QA — Validação de Qualidade

Valida alterações, executa testes e verifica critérios de aceite.

## Validação por módulo

### API (Laravel)

```bash
composer gate:all
```

### Gateway (NestJS)

```bash
pnpm lint && pnpm test
```

## Checklist

- [ ] Build/Lint passou
- [ ] Testes passaram (0 skipped)
- [ ] Cobertura >= 80%
- [ ] Critérios de aceite cumpridos
- [ ] Proporcional ao risco da mudança

## Saída

```
QA: PASS | FAIL
- Testes: [pass/fail]
- Critérios: [cumpridos/pendentes]
- Issues: [lista ou "nenhum"]
```