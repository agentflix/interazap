---
name: fix
description: >
  Debug e correção rápida para bugs pequenos e isolados.
  Use quando: bug pequeno e isolado, correção visual simples, ajuste localizado, fix rápido.
---

# Fix — Fast Path para bugs

Debug e correção rápida para bugs pequenos.

## Quando usar

- Bug pequeno e isolado
- Correção visual simples
- Ajuste localizado
- Fix rápido

## NÃO usar quando

- Feature média/grande (use PREVC)
- Task complexa (use TACE)

## Fluxo

```
Detect → Fix → Validate → Summary
```

## Detect

Identificar causa raíz com busca targeted, não abrindo projeto inteiro.

## Fix

Implementar correção mínima e segura.

## Contexto que carrega

- AGENTS.md do módulo
- Código do bug (buscar pelo erro, não abrir projeto inteiro)

## Contexto que NÃO carrega

- Feature docs
- MEMORY (a menos que bug seja decisão relevante)
- PREVC completo

## Validate

- **API:** `composer gate:all` ou teste específico
- **Gateway:** `pnpm lint && pnpm test` ou teste específico

## Summary

Responde em 1-3 linhas:
- Causa identificada
- Correção feita
- Validação realizada