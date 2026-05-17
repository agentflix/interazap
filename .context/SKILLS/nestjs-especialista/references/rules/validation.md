# Validação

## Gates

Para alterações em `gateway/`, rode:

```bash
pnpm --filter gateway test && pnpm --filter gateway build
```

Quando aplicável, rode também:

```bash
pnpm --filter gateway test:e2e
pnpm --filter gateway typecheck:specs
pnpm --filter gateway lint
```

## Evidência

- Registre comandos executados e resultado.
- Se algum gate não puder rodar por dependência, tempo, ambiente ou falha preexistente, informe motivo e risco residual.
- Não conclua task sem teste correspondente ou justificativa explícita.

## PREVC

- Respeite `.context/SKILLS/workflow-prevc/references/prevc.md` e `.context/SKILLS/workflow-prevc/references/validation-flow.md`.
- Na fase Confirm, atualize changelog, MEMORY e project-state quando a regra do repositório exigir.
