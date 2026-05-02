# feature — Iniciar feature com PREVC

## Objetivo

Planejar feature média/grande seguindo workflow PREVC.

## Trigger

```
/feature [resumo-do-problema]
```

## Workflow

```
1. Planning: Entender problema → Definir escopo → Criar feature doc
2. Review: Validar arquitetura e dependências
3. Execution: Implementar tasks (TACE)
4. Validation: Testes, gates
5. Confirm: Fechar, atualizar MEMORY se relevante
```

## Contexto carregado

- AGENTS.md do módulo
- MEMORY/ (decisões relevantes)
- modules.yaml (se risco arquitetural)

## Contexto NÃO carregado

- Todo código fonte
- Histórico operacional

## Saída

Feature doc em `.context/DOCS/FEATURES/FEATURE-XXX.md`