---
name: frontend-flow
description: >
    Mandatory workflow for any task that creates or modifies UI.
    Invoked by @ORCHESTRATOR or @FRONTEND before implementation begins.
    Ensures @DESIGNER spec exists before any code is written.
---

# Skill: frontend-flow

## Description

Orchestrates the complete frontend development cycle for UI tasks.
Guarantees that design is specified before code is written, using the existing
shared component library and following the InteraZap design system.

---

## Trigger Conditions

This workflow **must** be followed when:

- Creating a new page or route
- Creating a new shared component
- Significantly modifying an existing screen
- Any task with `@FRONTEND` as responsible agent

**Skip to Step 3** (implementation only) when:

- Minor copy or label change with no layout impact
- Adding loading/error state to existing, already-designed component
- Bug fix that does not change visual structure

---

## Workflow

```
@DESIGNER spec → Plan → Tasks → @FRONTEND implementation → Gates → Done
```

---

### Step 1 — @DESIGNER: Produce design spec

Invoke `@DESIGNER` with:

- Screen/component name
- Module it belongs to
- User story or requirement
- Any visual reference (screenshot, link, description)

`@DESIGNER` will:

1. Read `.claude/skills/design/SKILL.md`
2. Audit `app/src/app/shared/components/` for reusable components
3. Reference golden model: `app/src/app/pages/crm/contacts/`
4. Produce a complete **Design Spec** containing:
    - Layout description
    - Component map (shared components → UI elements)
    - All 4 states: loading, empty, error, default
    - Spacing and typography tokens
    - Interaction behaviors
    - What NOT to do for this specific screen

**Gate**: `@FRONTEND` must not start until `@DESIGNER` delivers and spec is approved.

---

### Step 2 — Create Plan (invoke create-plan skill)

With the approved design spec, invoke the `create-plan` skill:

```
title: [screen/component name]
module: [module name]
objective: [what this UI delivers to the user]
```

The plan must include in its Technical Approach section:

- Reference to the Design Spec produced in Step 1
- List of shared components to be used
- List of states to implement
- Angular patterns to follow (OnPush, signals, inject, takeUntilDestroyed)

---

### Step 3 — Create Tasks (invoke create-task skill)

With the approved plan, invoke the `create-task` skill:

```
plan_id: PLAN-{number}
goals:
  - Entrega 1: [Design spec + component scaffold]
  - Entrega 2: [Loading + empty + error states]
  - Entrega 3: [Default state + data binding]
  - Entrega 4: [Tests + gates]
agents:
  Entrega 1-3: @FRONTEND
  Entrega 4: @QA + @FRONTEND
```

Each task must include in its context:

- The Design Spec (embedded or referenced)
- Specific shared components to use per task
- Completion criteria mapped to test names (Vitest `it('should...')`)

---

### Step 4 — @FRONTEND: Implementation

#### Mandatory skills to read before writing any code

| Skill                                       | Purpose                                             |
| ------------------------------------------- | --------------------------------------------------- |
| `.claude/skills/design/SKILL.md`            | Design tokens, spacing, typography, component rules |
| `.claude/skills/frontend-flow/SKILL.md`     | This workflow — keep open as checklist              |
| `.claude/skills/angular-architect/SKILL.md` | Angular 20+ patterns: signals, OnPush, routing      |
| `.claude/skills/coding-guidelines/SKILL.md` | General coding discipline — avoid common LLM errors |

`@FRONTEND` reads:

1. `.claude/skills/design/SKILL.md` — aesthetic rules
2. Design Spec from Step 1 — what to build
3. Task from Step 3 — how to build it
4. Golden model — `app/src/app/pages/crm/contacts/`

Implementation rules (from AGENTS.md):

- `ChangeDetectionStrategy.OnPush` on every component
- `signal()` and `computed()` for local state
- `inject()` instead of constructor injection
- `takeUntilDestroyed` on all subscriptions
- `track` in every `@for`
- Never use `any` or `unknown`
- Never use raw `<table>`, `<button>`, `<input>` — use shared components
- Never hardcode colors — use design tokens
- Check `http://localhost:4200/ui-kit` before creating any visual component

---

### Step 5 — Gates (mandatory)

```bash
cd app && pnpm run gate:all
```

If gates fail → fix → re-run. Never advance with red gates.

Auto-fix available:

```bash
cd app && pnpm run format
cd app && pnpm run lint:fix
```

---

### Step 6 — @QA: Visual QA

`@QA` validates:

- [ ] All 4 states implemented and functional (loading, empty, error, default)
- [ ] No raw HTML elements — only shared components
- [ ] No hardcoded colors
- [ ] Spacing follows 4px scale
- [ ] Dark mode pairs present on all colored elements
- [ ] 1 primary CTA per page maximum
- [ ] Matches the Design Spec from Step 1

---

### Step 7 — @REVIEWER: Code review

`@REVIEWER` validates:

- [ ] Angular patterns: OnPush, signals, inject, takeUntilDestroyed
- [ ] No `any` or `unknown`
- [ ] jsDoc on interfaces and exported functions
- [ ] Tests written and passing
- [ ] Component follows golden model structure

---

### Step 8 — @GIT_COMMIT: Semantic commit

Format: `feat(module): description`

Example: `feat(crm): add contacts list page with status filter`

---

## Checklist Summary

```
[ ] @DESIGNER spec produced and approved
[ ] create-plan invoked — plan created
[ ] create-task invoked — tasks created with design spec embedded
[ ] @FRONTEND implemented using only shared components
[ ] All 4 states implemented: loading, empty, error, default
[ ] No hardcoded colors — design tokens only
[ ] No arbitrary spacing — 4px scale only
[ ] pnpm run gate:all green
[ ] @QA visual sign-off
[ ] @REVIEWER code sign-off
[ ] Semantic commit created
[ ] Task marked done with evidence
```

---

## Quick Reference — Shared Components

| UI Need           | Component            |
| ----------------- | -------------------- |
| CRUD page wrapper | `CrudPageComponent`  |
| Loading rows      | `skeleton-table-row` |
| Empty state       | `empty-state`        |
| Status pill       | `status-badge`       |
| Row actions       | `table-actions`      |
| Any button        | `button`             |
| Submit button     | `loading-button`     |
| Text field        | `text-input`         |
| Select            | `select-input`       |
| Search            | `search-input`       |
| Modal             | `modal`              |
| Confirm dialog    | `confirm-modal`      |
| Page header       | `page-title`         |
| Pagination        | `pagination`         |
