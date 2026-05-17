# Validação

## Gates

- Para alterações em `app/`, rode:

```bash
pnpm --filter app test && pnpm --filter app build
```

- Quando a alteração tocar lint, padrões de template, imports ou formatação, rode também comandos equivalentes do workspace, como:

```bash
pnpm --filter app lint
pnpm --filter app format:check
```

- Se estiver dentro de `app/`, os scripts locais equivalentes são `npm run gate:test`, `npm run gate:build` e `npm run gate:all`.

## Evidência

- Registre comandos executados e resultado.
- Se algum gate não puder rodar por dependência ausente, tempo, ambiente ou falha preexistente, informe claramente o motivo e o risco residual.
- Não marque a task como concluída sem teste correspondente ou justificativa explícita.

## PREVC

- Respeite `.context/SKILLS/workflow-prevc/references/prevc.md` e `.context/SKILLS/workflow-prevc/references/validation-flow.md`.
- A fase Confirm normalmente atualiza changelog, memory e project-state, mas não faça isso quando o escopo da solicitação proibir.
