# 📋 Features

Documentação de features do InteraZap.
Cada feature segue o workflow PREVC.

## Estrutura

| Campo | Descrição |
|-------|-----------|
| ID | `FEAT-NNN` (incremental) |
| Nome | Nome descritivo em kebab-case |
| Bounded Context | Módulo DDD que a feature impacta |
| Complexidade | P (Pequena), M (Média), G (Grande) |
| Prioridade | Must / Should / Could |
| Status | 🟡 Planning → 🔄 Execução → ✅ Concluída |

## Template

Use `_TEMPLATE.md` como base.

## Convenções

- Nome do arquivo: `[YYYY-MM-DD]-[nome-kebab].md`
- ID do PRD: `FEAT-NNN` (incremental)
- Status evolui: `🟡 Em Planning` → `🔄 Em Execução` → `✅ Concluída`

## Fluxo

1. PM ou ARCHITECT cria Feature doc
2. REVIEWER valida completude
3. Feature aprovada → Decomposição em Tasks
4. Tasks implementadas → Validation
5. Tasks concluídas → CONFIRM com CHANGELOG + MEMORY
6. Feature marcada como ✅ Concluída

## Como usar

```bash
# Criar nova feature
/new-feature nome-da-feature

# Ver progresso
/feature-status nome-da-feature
```

## Consultar

- Feature ativa: `grep -r "Em Planning\|Em Execução" .context/DOCS/FEATURES/`
- Feature por bounded context: `grep -r "Bounded Context: Chat" .context/DOCS/FEATURES/`
