---
name: create-task
description: Create a task from an approved plan, ready for execution.
---

# Skill: create-task

## Description

Create tasks from an approved plan, grouped by testable deliveries (Entregas).

## Input

- `plan_id`: Origin plan ID (e.g., PLAN-013)
- `prd_id`: Related PRD ID (optional)
- `goals`: Array of goals, each representing one testable delivery (Entrega)
- `agents`: Map of agents per delivery

## Process

1. Identify the plan number from `plan_id` (e.g., `PLAN-013` -> `013` -> `13`)
2. Count total deliveries (Entregas) and assign sequential numbers
3. Generate the TASKS file using the template in `.context/WORKFLOW/task-template.md`
4. For each delivery:
   - Assign delivery number (1, 2, 3...)
   - Assign task IDs: `TASK-{plan}.{delivery}.{seq}` (e.g., TASK-13.1.1, TASK-13.1.2)
   - Group tasks under delivery header with gate criteria
   - Each task = one atomic deliverable (one file, one function, one component)
5. Add index table at the top with all deliveries and task ranges
6. Set initial status to `todo`
7. Save to `.context/DOCS/TASKS/TASKS-{plan-number}.md`
    - One TASKS file per plan
8. If the task will be consumed by AI agents, apply the `## AI-Readiness Checklist` below (sections 1-5) using the template fields (Context References, Code Context, Critérios de conclusão)

## Output

- File: `.context/DOCS/TASKS/TASKS-{plan-number}.md`
- Structure:
  ```
  TASKS-{plan}
  ├── Entrega 1 — {description} ✅ testável
  │   ├── TASK-{plan}.1.1
  │   ├── TASK-{plan}.1.2
  │   └── Gate: {criteria}
  ├── Entrega 2 — {description} ✅ testável
  │   ├── TASK-{plan}.2.1
  │   └── Gate: {criteria}
  ```

## ID Format

`TASK-{plan}.{entrega}.{seq}`

- `{plan}`: 3-digit plan number (e.g., 013, 022)
- `{entrega}`: Sequential delivery number (1, 2, 3...)
- `{seq}`: Sequential task number within delivery (1, 2, 3...)

Example: `TASK-22.3.2` = Plan 22, Entrega 3, Task 2

## Validation

- [ ] TASKS file follows the mandatory template structure
- [ ] Index table with all deliveries is at the top
- [ ] Each delivery has gate criteria defined
- [ ] Tasks are atomic (one file/function/component per task)
- [ ] Plan origin is referenced in each task
- [ ] PRD reference included (if applicable)
- [ ] Responsible agent is assigned per delivery
- [ ] Completion criteria are specific and verifiable
- [ ] Initial status is `todo`
- [ ] If consumed by AI agents, `## AI-Readiness Checklist` was fully applied

---

## AI-Readiness Checklist (mandatory for tasks consumed by AI)

Scope:

- Validate once per task document
- Rules 1, 4, and 5: apply per finding (for audit-derived tasks only; for generic tasks, apply once)

Before finalizing any task document intended for AI agents, validate every item below. Missing details in these points will lead to incorrect code even with a capable agent.

---

### 1. Code context: before and after

**Problem**: Referencing `L74-88` without showing code forces the agent to infer, and inference causes wrong fixes.

**Rule**: Every finding that changes existing code MUST include:

````markdown
#### Current code (problem)

```php
// Real snippet from file, enough to understand the issue
```

#### Expected code (solution)

```php
// How it should look after the fix
```
````

If the snippet is large (>40 lines), include only the relevant function/method and use `// ...` for omitted parts.

**When optional**: brand new file creation where no pre-existing code exists.

---

### 2. Completion criteria as test names

**Problem**: Criteria like "runs at most 1 query per request" are only verifiable at runtime, and the agent may not know how to prove compliance.

**Rule**: Each completion criterion must map to a corresponding test name using the target stack convention:

- Backend (Pest/PHPUnit): `test_<expected_behavior>`
- Frontend (Vitest): `it('should <expected behavior>')`
- Gateway (Jest): `it('should <expected behavior>')`

```markdown
#### Completion Criteria

- [ ] A second concurrent trigger with the same `event_id` returns 200 without duplicate processing
      -> `test_duplicate_webhook_event_returns_200_without_processing`
- [ ] `getCurrentPlan()` performs at most 1 query per request
      -> `test_get_current_plan_queries_database_only_once_per_request`
```

The agent will use these names to create or update Feature/Unit tests without ambiguity.

**When optional**: tasks with no executable/testable code scope (e.g., documentation-only alignment with no behavior change).

---

### 3. External references: embed or declare explicitly

**Problem**: "See AUDIT-001.md Section 3" without guaranteed context makes items invisible to the agent.

**Rule**: Choose one option explicitly:

**Option A - Embed** (preferred for short lists, <=15 items):

```markdown
> Remaining findings are listed below (embedded from AUDIT-001.md section 3):
> | ID | File | Description |
> |----|------|-------------|
> | ... | ... | ... |
```

**Option B - Declare as context dependency** (for long lists):

```markdown
> WARNING - REQUIRED CONTEXT: The agent must have `{path-to-reference-file}` in context before executing this task. Otherwise, items referenced by ID may be invisible.
```

Never leave an external reference without explicitly stating which option was chosen.

**When optional**: when there are no external references.

---

### 4. Uniform step granularity

**Problem**: Mixing very small and very large steps in the same list prevents the agent from estimating scope and deciding when to stop.

**Rule**: Steps estimated as M, L, XL, or XXL must be decomposed into numbered sub-steps inside the same item.

```markdown
- [ ] **API-REF-001** (XL): Extract CRMNegotiationActions into 7 classes
    - [ ]   1. Map public methods -> responsibilities (output: table in PR)
    - [ ]   2. Create `ListCRMNegotiationsAction` + move logic + update controller
    - [ ]   3. Create `CreateCRMNegotiationAction` + move logic + update controller
    - [ ]   4. Create `UpdateCRMNegotiationAction` ...
    - [ ]   5. Create `DeleteCRMNegotiationAction` ...
    - [ ]   6. Create `ConvertNegotiationToTicketAction` ...
    - [ ]   7. Create `AssignNegotiationAction` ...
    - [ ]   8. Create `ChangeNegotiationStageAction` ...
    - [ ]   9. Delete original or convert it into a facade
    - [ ]   10. Migrate tests
```

Rule of thumb: if a step is M, L, XL, or XXL, it must include sub-steps.
If the base template does not have an effort field, annotate effort in the step itself (e.g., `(XL)`).

**When optional**: atomic XS/S steps.

---

### 5. Explicit contract for service/class extraction

**Problem**: Describing contracts only in prose allows the agent to invent wrong signatures, namespaces, or inheritance.

**Rule**: Every class/service/interface extraction item must include a minimal contract block in the target stack language.

PHP example (Laravel):

```php
<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Illuminate\Http\Request;

interface WebhookSignatureValidatorInterface
{
    public function validate(Request $request, string $provider): bool;
}

final class WebhookSignatureValidator implements WebhookSignatureValidatorInterface
{
    public function validate(Request $request, string $provider): bool
    {
        // implementation here
        return true;
    }
}
```

TypeScript example (Angular/NestJS):

```ts
export interface WebhookSignatureValidatorInterface {
    validate(payload: string, provider: string): boolean;
}

export class WebhookSignatureValidator implements WebhookSignatureValidatorInterface {
    validate(payload: string, provider: string): boolean {
        return true;
    }
}
```

Include: expected path/namespace, public signatures, and interface/base-class relationship.

**When optional**: when there is no class/service/interface extraction or creation.

---

## Audit finding template (reference)

Use this template only when the task is derived from an audit.
For generic tasks (feature/bugfix/refactor), keep `.context/WORKFLOW/task-template.md` as the primary structure.

````markdown
### API-XXX-000 - [Short title]

**File**: `Domain/Module/Path/File.php` (L00-00)
**Severity**: CRITICAL | HIGH | MEDIUM | LOW
**Effort**: XS | S | M | L | XL | XXL
**Category**: Security | Performance | Architecture | Dead Code | Reusability
**Agent**: @DEV | @BACKEND | @FRONTEND | @QA | @REVIEWER

#### Problem

[Describe in 1-3 sentences what is wrong and why it is a risk]

#### Current code

```php
// Problem snippet - only relevant section
```

#### Expected fix

```php
// How it should look
```

#### Contract (if class/service extraction)

```php
// Expected public interface/signature
```

#### Steps

- [ ]   1. [Atomic sub-step]
- [ ]   2. [Atomic sub-step]

#### Completion Criteria

- [ ] [Verifiable behavior]
      -> `test_corresponding_test_name`

#### Context References

- Source file: `.context/DOCS/AUDITS/AUDIT-001.md section 2.3` _(embedded above | required in context)_

```

---

## When this checklist applies

Apply this checklist **always** when the task document will be consumed by:
- An autonomous AI agent (no human supervision per step)
- An LLM in code generation mode without direct codebase access
- An automated CI/CD pipeline based on task documents

For documents read only by human developers, items 1, 3, and 5 are recommended but not mandatory.
