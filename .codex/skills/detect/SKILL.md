---
name: detect
description: >
  Identificar stack, módulos e convenções antes de trabalhar.
  Use quando: primeira vez no projeto, confirmar ambiente, mudar de módulo.
---

# Detect — Detectar contexto do projeto

Identifica stack, módulos e convenções antes de trabalhar.

## Quando usar

- Primeira vez no projeto
- Confirmar ambiente
- Mudar de módulo (API ↔ Gateway)

## Contexto que carrega

1. `AGENTS.md` (kernel)
2. `project-brain.yaml` (stack)
3. `modules.yaml` (módulos)
4. `AGENTS.md` do módulo específico

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

Detecta corretamente:
- Módulo-alvo
- Stack principal
- Arquivos relevantes