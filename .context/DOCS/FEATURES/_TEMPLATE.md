# Feature: [Nome da Feature]

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-NNN |
| **Nome** | [nome-kebab-case] |
| **Bounded Context** | [Ai / Auth / Billing / Chat / Configuration / CRM / Dashboard / Gateway / Platform / Reports] |
| **Workspaces** | [api / gateway / app / electron] |
| **Complexidade** | [P / M / G] |
| **Status** | 🟡 Em Planning |
| **Data** | YYYY-MM-DD |
| **Autor** | [Nome] |

## Flags

<!-- Marcar as que se aplicam -->
- [ ] ⚠️ MULTI-TENANT — toca Platform ou BelongsToTenant
- [ ] ⚠️ RISCO FINANCEIRO — toca Billing ou ASAAS
- [ ] ⚠️ WHATSAPP — verificar UazAPI + Z-API
- [ ] 🚨 BREAKING CHANGE
- [ ] 🔒 SEGURANÇA — toca Auth ou tokens

## Resumo

[2-3 frases descrevendo o que é e por que importa]

## Problema que Resolve

[O que está faltando ou quebrando hoje? Quem é afetado?]

## Solução Proposta

[O que será construído. Linguagem funcional, sem detalhes de implementação aqui.]

## Escopo

### Incluído ✅
- [ ] [item 1]
- [ ] [item 2]

### Fora de Escopo ❌
- [item que NÃO será feito nesta iteração]

## Dependências

| Tipo | Descrição | Status |
|------|-----------|--------|
| Feature | [feature relacionada] | [pronta / em progresso] |
| Módulo | [módulo que será consumido] | [ativo] |
| API Externa | [ex: ASAAS, OpenAI] | [disponível] |

## Critérios de Aceite

- [ ] [Critério verificável 1 — sem "funciona corretamente"]
- [ ] [Critério verificável 2]
- [ ] [Critério verificável 3]

## Tasks

> Preenchido na fase REVIEW após decomposição com `/decompose [nome]`

Ver: `.context/DOCS/TASKS/[nome]-tasks.md`

| Task | Título | Status | Workspace |
|------|--------|--------|-----------|
| TASK-X.Y.Z | [título] | ⏳ | [workspace] |

**Progresso:** 0 / N tasks concluídas
