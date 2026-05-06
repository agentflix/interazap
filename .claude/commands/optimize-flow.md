# /optimize-flow — Otimizar Execução Técnica

Uso: `/optimize-flow [tipo] [escopo] [objetivo]`

Exemplos:
- `/optimize-flow bug app Corrigir filtro de contatos por empresa`
- `/optimize-flow refactor cross Padronizar contrato canônico de campo`

## Objetivo

Aplicar a skill global `.claude/skills/flow-optimizer/SKILL.md` para reduzir tempo de execução com segurança balanceada.

## Passos

1. Definir tipo (`bug|refactor|feature|investigation`) e escopo (`api|gateway|app|electron|cross`)
2. Executar Smart Search com `rg` para mapear símbolos/fluxo/testes
3. Registrar contexto em `.claude/agent-memory/flow-optimizer/`
4. Definir test slice (focal -> contexto -> amplo)
5. Executar implementação em fatias curtas com checkpoints de contrato
6. Validar evidências e fechar PREVC (CHANGELOG + MEMORY + state)

## Saída Esperada

- Plano curto de execução
- Lista de impacto (componentes/contratos)
- Sequência de testes recomendada
- Riscos e checkpoints críticos
