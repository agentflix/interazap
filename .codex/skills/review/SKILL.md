---
name: review
description: >
  Revisar código, arquitetura e decisões técnicas.
  Use quando: review de PR, validar implementação, verificar conformidade.
---

# Review — Revisão de Código

Revisa código e arquitetura.

## Checklist

### Código
- Nomenclatura, sem duplicação, error handling, logging

### API Laravel
- Controller → DTO → Action → Resource
- BelongsToTenant, UUID, eager loading, strict_types

### Gateway NestJS
- ValidationPipe, Logger, idempotência, circuit breaker

### Testes
- Cobertura >= 80%, sem skipped

## Tipos

| Tipo | Uso | Tempo |
|------|-----|-------|
| Quick | Bug fix | 5-10min |
| Standard | Feature | 15-30min |
| Deep | Arquitetura | 45-60min |

## Saída

```
Review: APPROVE | REQUEST_CHANGES
- Arquivos: [lista]
- Issues: [lista]
```