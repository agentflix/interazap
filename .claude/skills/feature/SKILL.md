---
name: feature
description: >
  Planejar e documentar feature média ou grande seguindo workflow PREVC.
  Use quando: feature multi-módulo, nova integração significativa, refatoração de escopo.
---

# Feature — Iniciar feature com PREVC

Planeja e documenta feature média ou grande.

## Quando usar

- Feature que impacta múltiplos módulos
- Nova integração significativa
- Refatoração de escopo

## NÃO usar quando

- Bug pequeno (use Fast Path)
- Ajuste trivial
- Correção localizada

## Contexto que carrega

1. Ler `AGENTS.md` do módulo
2. Consultar `MEMORY/` para decisões relevantes
3. Consultar `modules.yaml` se mudança arquitetural
4. Criar doc em `.context/DOCS/FEATURES/`

## Contexto que NÃO carrega

- Todo código fonte
- Histórico operacional
- DOCUMENTAÇÃO COMPLETA

## Workflow PREVC

```
1. Planning: Entender problema → Definir escopo → Criar feature doc
2. Review: Validar arquitetura e dependências
3. Decompor em tasks TACE
4. Execution: Implementar task por task
5. Validation: Testes, gates
6. Confirm: Fechar, atualizar MEMORY se relevante
```

## Saída esperada

```
FEATURE-XXX.md em .context/DOCS/FEATURES/
├── T: Problema e objetivo
├── A: Escopo e limites
├── C: Critérios de aceite
├── R: Riscos conhecidos
└── Tasks: [TASK-001, TASK-002, ...]
```

## Critério de qualidade

Feature doc deve ser executável por outro agente sem precisar reler conversa.

## Entrega

Sempre criar o documento `FEATURE-XXX.md` em `.context/DOCS/FEATURES/` usando a tool `Write`.

**Formato de entrega:**
```markdown
## Output

[Feature doc completo em markdown]
```

O documento deve ser escrito no filesystem ANTES de mostrar o resumo para o usuário.