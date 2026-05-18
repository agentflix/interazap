---
name: write-feature
description: Creates an InteraZap feature doc in .context/DOCS/FEATURES/ following the PREVC Planning phase. Use when /new-feature [nome] is called, user says "nova feature", "criar feature", "documentar feature", "planejar feature", or starts describing something to be built. Do NOT use for decomposing features into tasks (use decompose-feature), writing PRDs (those go in .context/DOCS/PRDS/), or implementing anything.
license: CC-BY-4.0
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# Write Feature

Skill for creating InteraZap feature docs during the PREVC Planning phase.

## Before Writing — Consult Existing Context

Run these before creating any file:

```bash
# Check for prior decisions on the topic
grep -r "[keyword from feature name]" .context/DOCS/MEMORY/

# Check for related PRD
ls .context/DOCS/PRDS/

# Check for related existing feature
ls .context/DOCS/FEATURES/

# Check module dependencies
cat .context/ARCHITECTURE/modules.yaml
```

If a relevant memory entry exists, surface it to the user before proceeding.

## Feature Doc Structure

Create the file at `.context/DOCS/FEATURES/[nome-kebab-case].md` using this structure:

### Required Fields

```markdown
# Feature: [Nome Legível]

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-NNN |
| **Nome** | [nome-kebab-case] |
| **Bounded Context** | [Ai / Auth / Billing / Chat / Configuration / CRM / Dashboard / Gateway / Platform / Reports / Shared] |
| **Workspaces** | [api / gateway / app / electron] |
| **Complexidade** | [P / M / G] |
| **Status** | 🟡 Em Planning |
| **Data** | YYYY-MM-DD |
| **Autor** | [Nome] |
```

Complexity guide: P = under 1 day | M = 1–3 days | G = over 3 days.

### Mandatory Flags

Mark every flag that applies. Missing flags on relevant features will cause REVIEWER to reject the doc.

```markdown
## Flags

- [ ] ⚠️ MULTI-TENANT — toca Platform ou BelongsToTenant
- [ ] ⚠️ RISCO FINANCEIRO — toca Billing ou ASAAS
- [ ] ⚠️ WHATSAPP — verificar compatibilidade UazAPI + Z-API
- [ ] 🚨 BREAKING CHANGE
- [ ] 🔒 SEGURANÇA — toca Auth ou tokens
```

### Content Sections

```markdown
## Resumo

[2–3 sentences. What it is and why it matters.]

## Problema que Resolve

[What is broken or missing today? Who is affected?]

## Solução Proposta

[What will be built. Functional language — no implementation details here.]

## Escopo

### Incluído ✅
- [ ] [item 1]
- [ ] [item 2]

### Fora de Escopo ❌
- [what will NOT be built in this iteration]

## Dependências

| Tipo | Descrição | Status |
|------|-----------|--------|
| Feature | [related feature] | [pronta / em progresso] |
| Módulo | [module consumed] | [ativo] |
| API Externa | [ex: ASAAS, OpenAI, UazAPI] | [disponível] |

## Critérios de Aceite

- [ ] [verifiable criterion 1 — no "funciona corretamente"]
- [ ] [verifiable criterion 2]
- [ ] [verifiable criterion 3]

## Tasks

> Preenchido na fase REVIEW via /decompose [nome]

Ver: .context/DOCS/TASKS/[nome]-tasks.md
```

## Rules for Good Acceptance Criteria

Every criterion must be answerable with a binary yes/no test.

| Bad | Good |
|-----|------|
| "Proposta funciona" | "POST /crm/proposals retorna 201 com UUID em < 300ms" |
| "Interface está bonita" | "Modal abre em < 200ms, campos validados no frontend antes do submit" |
| "Testes passam" | "Pest: test_proposal_creates_with_tenant_isolation passa com 2 tenants distintos" |
| "Integração está ok" | "Webhook ASAAS processa sem duplicar cobrança (idempotência via Redis)" |

## Special Requirements by Flag

When a flag is marked, add these criteria automatically:

**⚠️ MULTI-TENANT:** Include `- [ ] Isolamento verificado: tenant A não acessa dados do tenant B`

**⚠️ RISCO FINANCEIRO:** Include `- [ ] Idempotência em webhook/evento testada com payload duplicado`

**⚠️ WHATSAPP:** Include `- [ ] Testado com conta sandbox UazAPI e Z-API adapter`

**🔒 SEGURANÇA:** Include `- [ ] Code review obrigatório por @REVIEWER + @ARCHITECT antes de CONFIRM`

## After Creating the File

1. Tell the user the feature doc path: `.context/DOCS/FEATURES/[nome].md`
2. Suggest next step: `/review-feature [nome]`
3. If the feature needs a PRD (high impact, multiple stakeholders, external dependencies), suggest creating one in `.context/DOCS/PRDS/` first.

## Examples

### Example 1: Simple backend feature

User: "Quero adicionar campo expires_at nas propostas do CRM"

Result path: `.context/DOCS/FEATURES/crm-proposal-expiry.md`

Key fields:
- Bounded Context: CRM
- Workspaces: api
- Complexidade: P
- Flag: ⚠️ MULTI-TENANT (propostas têm tenant_id)
- Critério: "Proposta com `expires_at` definido não é listada após a data de expiração"

### Example 2: Full-stack feature

User: "Nova feature de lembretes de eventos no CRM — o usuário configura um lembrete para um contato e recebe notificação"

Result path: `.context/DOCS/FEATURES/crm-event-reminders.md`

Key fields:
- Bounded Context: CRM
- Workspaces: api, app
- Complexidade: M
- Flag: ⚠️ MULTI-TENANT
- Critérios: "Lembrete criado aparece na lista do contato", "Job dispara notificação no horário configurado ±1min", "Teste de isolamento: lembrete do tenant A não aparece para tenant B"

### Example 3: High-risk feature

User: "Integração com nova modalidade de pagamento ASAAS"

Result path: `.context/DOCS/FEATURES/billing-new-payment-method.md`

Key fields:
- Bounded Context: Billing
- Workspaces: api, gateway
- Complexidade: G
- Flags: ⚠️ RISCO FINANCEIRO, 🔒 SEGURANÇA
- Suggest PRD first before writing feature doc

## Common Issues

**User provides a vague description:** Ask for the bounded context and at least one acceptance criterion before writing. Do not proceed with "a feature that improves UX".

**No scoping:** Always define "Fora de Escopo" explicitly. It prevents scope creep during EXECUTION.

**All criteria are vague:** If you cannot write a concrete criterion, the feature is not ready for REVIEW. Flag it and ask the user to clarify.
