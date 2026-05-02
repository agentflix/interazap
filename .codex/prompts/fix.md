# fix — Fast Path para bugs

## Objetivo

Correção rápida para bugs pequenos.

## Trigger

```
/fix [descrição-do-bug]
```

## Fluxo

```
Detect → Fix → Validate → Summary
```

## Detect

Identificar causa raíz com busca targeted, não abrindo projeto inteiro.

## Validate

- **API:** `composer gate:all` ou teste específico
- **Gateway:** `pnpm lint && pnpm test` ou teste específico

## Summary

Responde em 1-3 linhas:
- Causa identificada
- Correção feita
- Validação realizada