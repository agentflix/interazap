# /fix — Fast Path para bugs

Debug e correção rápida para bugs pequenos.

## Quando usar

- Bug pequeno e isolado
- Correção visual simples
- Ajuste localizado

## Como usar

```
/fix [descrição-do-bug]
```

## Fluxo

```
Detect → Fix → Validate → Summary
```

## Validação

- **API:** `composer gate:all` ou teste específico
- **Gateway:** `pnpm lint && pnpm test` ou teste específico

## Summary

Responde em 1-3 linhas com:
- Causa identificada
- Correção feita
- Validação realizada