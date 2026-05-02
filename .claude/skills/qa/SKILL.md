---
name: qa
description: >
  Validar alterações, executar testes e verificar critérios de aceite.
  Use quando: validar feature implementada, verificar PR, garantir qualidade antes de commit.
---

# QA — Validação de Qualidade

Valida alterações, executa testes e verifica critérios de aceite.

## Quando usar

- Feature implementada precisa de validação
- PR precisa de review de qualidade
- Antes de commit/gate
- Verificar critérios de aceite

## Validação por módulo

### API (Laravel)

```bash
# Full gate
composer gate:all

# Ou individual
composer format
composer analyse
composer test
```

### Gateway (NestJS)

```bash
pnpm lint && pnpm test
```

## Fluxo de validação

```
1. Identify → O que foi alterado?
2. Validate → Testes passaram?
3. Check → Critérios de aceite cumpridos?
4. Report → Listar pass/fail
```

## Checklist de QA

- [ ] Código compila/build passa
- [ ] Lint/format passou
- [ ] Testes passaram (0 skipped)
- [ ] Cobertura >= 80%
- [ ] Critérios de aceite cumpridos
- [ ] Nenhum regression
- [ ] Validação proporcional ao risco

## Proporcionalidade

| Risco | Validação |
|-------|-----------|
| Bug fix local | Teste específico |
| Mudança multi-módulo | Full gate |
| Infra/arquitetura | Testes + review manual |
| Regra de negócio | Critérios de aceite + testes |

## Saída esperada

```
QA Report
- Status: PASS | FAIL
- Alterações validadas: [lista]
- Testes: [pass/fail]
- Critérios de aceite: [cumpridos/pendentes]
- Issues encontrados: [lista ou "nenhum"]
```

## Critério de qualidade

QA report claro permite decisões rápidas. Falhas devem ser acionáveis.