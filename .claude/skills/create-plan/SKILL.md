# Skill: create-plan

## Description
Create a development plan from requirements, ready for review and task creation.

## Input
- `title`: Plan title
- `module`: Related module name
- `prd`: Related PRD ID (optional, e.g., PRD-AUTH-001)
- `objective`: What the plan aims to achieve

## Process
1. Check existing plans in `.context/DOCS/PLANS/` for numbering
2. Determine next sequential 3-digit PLAN ID (e.g., 013 if last is 012)
3. Generate plan following the template in `.context/WORKFLOW/plan-template.md`
4. Include: Objective, Module, PRD reference, Scope (in/out), Steps, Technical Approach, Risks, Estimate
5. Save to `.context/DOCS/PLANS/PLAN-{000}-{nome-em-letra-minuscula}.md`
   - ID: zero-padded 3-digit sequential number (e.g., 001, 013)
   - Name: lowercase kebab-case (e.g., `bugfix-chat-read-status`, `api-audit`)
   - Example: `PLAN-013-api-audit.md`

## Output
- File: `.context/DOCS/PLANS/PLAN-{000}-{nome-em-letra-minuscula}.md`
- Updates: `WORKFLOW/project-state.yaml` (metrics)

## Validation
- [ ] Plan follows the mandatory template structure
- [ ] Objective is clear and bounded
- [ ] Risks and dependencies documented
- [ ] Estimate includes all relevant layers
- [ ] PRD reference included (if applicable)
