---
name: fast-path
description: >
  Correção rápida para bugs pequenos e isolados.
  Use quando: bug pequeno, correção visual simples, ajuste localizado.
---

# Fast-Path — Bug fix rápido

Correção rápida para bugs pequenos e isolados.

## Fluxo

```
Detect → Fix → Validate → Summary
```

## Quando usar

- Bug pequeno
- Correção visual simples
- Ajuste localizado
- Fix rápido

## Quando NÃO usar

- Feature média/grande (use PREVC)
- Task complexa (use TACE)

## Detect

Identificar causa raíz com busca targeted, não abrindo projeto inteiro.

## Fix

Implementar correção mínima e segura.

## Validate

- API: `composer gate:all` ou teste específico
- Gateway: `pnpm lint && pnpm test` ou teste específico

## Summary

Responde em 1-3 linhas:
- Causa identificada
- Correção feita
- Validação realizada