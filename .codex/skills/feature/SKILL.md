---
name: feature
description: >
  Planejar e documentar feature média ou grande seguindo workflow PREVC.
  Use quando: feature multi-módulo, nova integração, refatoração de escopo.
---

# Feature — Planejar feature com PREVC

Planeja e documenta feature média ou grande.

## Quando usar

- Feature multi-módulo
- Nova integração
- Refatoração de escopo

## NÃO usar quando

- Bug pequeno (use Fast Path)
- Ajuste trivial

## Workflow PREVC

```
Planning → Review → Execution → Validation → Confirm
```

## Planning

- Entender problema
- Definir escopo
- Consultar MEMORY para decisões relevantes
- Criar feature doc em `.context/DOCS/FEATURES/`

## Decomposição

- Decompor em tasks TACE
- Identificar dependências
- Mapear riscos

## Execution

- Implementar task por task
- Commits atômicos
- Respeitar convenções do módulo

## Validation

- API: `composer gate:all`
- Gateway: `pnpm lint && pnpm test`

## Confirm

- Fechar feature
- Atualizar MEMORY se houve aprendizado relevante

## Saída

Feature doc em `.context/DOCS/FEATURES/FEATURE-XXX.md`