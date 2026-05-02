# validate — Validar alterações

## Objetivo

Validar código após implementação.

## Trigger

```
/validate [módulo]
```

## Validação por módulo

### API (Laravel)

```bash
composer gate:all
```

Ou individualmente:
- `composer format` — formatação
- `composer analyse` — análise estática
- `composer test` — testes

### Gateway (NestJS)

```bash
pnpm lint && pnpm test
```

## Proporcionalidade

Validar mais para:
- Mudanças de infraestrutura
- Alterações multi-módulo
- Regras de negócio

Validar menos para:
- Bug fix localizado
- Ajuste visual
- Correção trivial