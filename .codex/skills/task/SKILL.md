---
name: task
description: >
  Implementar task técnica seguindo framework TACE.
  Use quando: task de implementação, bug que requer código, feature pequena isolada.
---

# Task — Implementar task com TACE

Implementa task técnica seguindo framework TACE.

## Formato TACE

```
T: O que fazer
A: Onde fazer (arquivos reais)
C: Antes/depois (comportamento)
E: Como validar (teste/comando)
```

## Quando usar

- Task de implementação
- Bug que requer código
- Feature pequena isolada

## Contexto que carrega

- AGENTS.md do módulo
- Código relevante (buscar primeiro)

## Contexto que NÃO carrega

- Todo projeto
- Feature docs completas

## Saída

- Código implementado
- Evidência de validação (teste, lint, build)