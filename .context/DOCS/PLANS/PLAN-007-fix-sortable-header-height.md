# PLAN-007 — Corrigir altura inconsistente do header em tabelas com ordenação ✅ CONCLUÍDO

## Status: ✅ DONE — Commit: `fb4ac101e` (28/03/2026)

## Objetivo

Uniformizar a altura visual dos headers de tabela entre colunas com e sem ordenação. Actualmente, `<th afSortableHeader>` com botões de ordenação (ícones chevron empilhados) ocupa mais altura que `<th>` simples com texto.

## Módulo relacionado

`Shared` (componentes `sortable-header` e `data-table`)

## PRD relacionado

N/A — Bug/UI fix

## Escopo

### Incluído

- Ajuste no componente `AfSortableHeaderComponent` (`sortable-header.ts`)
- Verificação de consistência com `af-data-table` e relatórios existentes

### Excluído

- Alterações em lógica de ordenação
- Alterações em API ou backend
- Novos testes (fix visual simples)

## Etapas propostas

1. **Inspecionar** — Ler `sortable-header.ts` e confirmar a classe `py-3` no `hostClasses()`
2. **Ajustar padding** — Reduzir `py-3` para `py-2` no `hostClasses()` do sortable-header (compensando a altura extra dos ícones empilhados)
3. **Validar** — Verificar se a altura do header com ordenação fica igual ao sem ordenação

## Tasks derivadas

| Task    | Descrição                              | Agente   | Status  |
| ------- | -------------------------------------- | -------- | ------- |
| fb4ac10 | Ajustar py-3 → py-2 no sortable-header | FRONTEND | ✅ done |

## Riscos e dependências

### Riscos

| Risco                                    | Probabilidade | Impacto | Mitigação                                    |
| ---------------------------------------- | ------------- | ------- | -------------------------------------------- |
| Ajuste de padding afectar outras tabelas | Baixa         | Baixo   | Inspeção prévia; valor é local ao componente |

### Dependências

Nenhuma — componente isolado, sem dependências externas.

## Estimativa

| Item                          | Valor                                                        |
| ----------------------------- | ------------------------------------------------------------ |
| Complexidade                  | Baixa                                                        |
| Camadas afetadas              | Frontend                                                     |
| Migrações necessárias         | Não                                                          |
| Impacto em módulos existentes | Mínimo — componente partilhado, pode tocar vários relatórios |

---

## Nota técnica (DEBUG)

**Causa raiz:** `<th afSortableHeader>` contém `<button>` com ícones `chevron-up` e `chevron-down` empilhados via `inline-flex flex-col -space-y-1`. O empilhamento com gap negativo adiciona altura visual extra que `<th>` simples (só texto) não tem.

**Solução escolhida:** Ajustar `py-3` → `py-2` em `hostClasses()` (`sortable-header.ts:77`) para compensar a alturaextra dos ícones, mantendo ambos os tipos de header com altura visual idêntica.

**Ficheiros:**

- `app/src/app/shared/components/sortable-header/sortable-header.ts` — linha 77
