# Skill: create-task

## Description
Create a task from an approved plan, ready for execution.

## Input
- `title`: Task title
- `plan_id`: Origin plan ID (e.g., PLAN-001)
- `prd_id`: Related PRD ID (optional)
- `agent`: Responsible agent (e.g., @DEV, @BACKEND, @FRONTEND)
- `goal`: What must be delivered

## Process
1. Check existing tasks in `.context/DOCS/TASKS/` for numbering
2. Determine next sequential TASK ID
3. Generate task following the template in `.context/WORKFLOW/task-template.md`
4. Include: Status, Plan origin, PRD reference, Agent, Goal, Constraints, Context, Steps, Completion Criteria, Evidence
5. Set initial status to `todo`
6. Save to `.context/DOCS/TASKS/TASK-[ID]-[title-kebab].md`

## Output
- File: `.context/DOCS/TASKS/TASK-[ID]-[title-kebab].md`
- Updates: `WORKFLOW/project-state.yaml` (increment `tasks_total`)

## Validation
- [ ] Task follows the mandatory template structure
- [ ] Plan origin is referenced
- [ ] PRD reference included (if applicable)
- [ ] Responsible agent is assigned
- [ ] Completion criteria are specific and verifiable
- [ ] Initial status is `todo`
