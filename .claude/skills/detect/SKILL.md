---
name: detect
description: >
  Identificar stack, módulos e convenções antes de começar trabalho.
  Use quando precisar entender o ambiente, confirmar ambiente ou mudar de módulo (API → Gateway).
---

# Detect — Detectar contexto do projeto

Identifica stack, módulos e convenções antes de começar trabalho.

## Quando usar

- Primeira vez trabalhando no projeto
- Quando precisar confirmar ambiente
- Ao mudar de módulo (API → Gateway)

## Contexto que carrega

- `AGENTS.md` (kernel)
- `project-brain.yaml` (stack)
- `modules.yaml` (módulos)
- `AGENTS.md` do módulo específico

## Contexto que NÃO carrega

- `MEMORY/` (só se detectar decisão relevante)
- `FEATURES/` (só se task envolver escopo)
- Workflow docs (só se task formal)

## Saída esperada

```
Stack: Laravel 12 / PHP 8.3 / PostgreSQL
Módulo: Chat
Convenção: Controller → DTO → Action → Resource
Área: gateway/src/domains/chat/
```

## Critério de qualidade

Detecção deve identificar corretamente:
- Módulo-alvo
- Stack principal
- Arquivos relevantes para a task