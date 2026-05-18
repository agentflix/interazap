# Features

Documentação funcional de features do InteraZap.

## Convenções

- Um arquivo por feature: `[nome-kebab-case].md`
- Template: `_TEMPLATE.md`
- Criado na fase **PLANNING** do PREVC via `/new-feature [nome]`
- Atualizado ao longo de todo o ciclo PREVC

## Status de Feature

| Status | Significado |
|--------|-------------|
| 🟡 Em Planning | Feature doc sendo criada |
| 🟠 Em Review | Em revisão por REVIEWER + ARCHITECT |
| 🔄 Em Execução | Tasks sendo implementadas |
| ✅ Concluída | Todas tasks validadas e confirmadas |

## Consultar

```bash
# Features em progresso
grep -r "Em Execução\|Em Review" .context/DOCS/FEATURES/

# Feature por bounded context
grep -r "Bounded Context.*Chat" .context/DOCS/FEATURES/

# Features concluídas
grep -r "Concluída" .context/DOCS/FEATURES/
```
