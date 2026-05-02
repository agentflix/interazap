---
name: task
description: >
  Implementar task técnica pequena/média seguindo framework TACE.
  Use quando: task de implementação, bug que requer código, feature pequena isolada.
---

# Task — Decompor e executar task com TACE

Implementa task técnica seguindo framework TACE.

## Quando usar

- Task de implementação
- Bug que requer código
- Feature pequena isolada

## NÃO usar quando

- Feature média/grande (use PREVC)
- Bug trivial (use Fast Path)

## Formato TACE

```yaml
T: O que fazer
A: Onde fazer (arquivos reais)
C: Antes/depois (comportamento)
E: Como validar (teste/comando)
```

## Contexto que carrega

- AGENTS.md do módulo
- Código relevante (buscar primeiro, não abrir tudo)
- Padrões existentes

## Contexto que NÃO carrega

- Todo projeto
- Features docs completas (usar só se necessário)
- Histórico

## Saída esperada

- Código implementado
- Evidência de validation (teste, build, lint)
- Task fechada

## Critério de qualidade

Outro agente consegue executar a task apenas lendo TACE.