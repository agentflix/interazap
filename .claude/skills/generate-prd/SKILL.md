# Skill: generate-prd

## Description
Generate a structured PRD (Product Requirements Document) for a module feature.

## Input
- `module`: Module name (e.g., Auth, CRM, Chat, Ai, Billing)
- `feature`: Feature name in kebab-case
- `description`: Brief description of the feature

## Process
1. Check existing PRDs in `.context/DOCS/PRDS/` for numbering
2. Determine next sequential number for the module
3. Generate PRD following the template in AGENTS.md Section 8
4. Include: Context, Objective, Requirements (Business Rules, Flows, Validations, States), Mockup, Acceptance Criteria, Contracts, Output Format
5. Save to `.context/DOCS/PRDS/PRD-[MODULE]-[NUMBER]-[feature-name].md`

## Output
- File: `.context/DOCS/PRDS/PRD-[MODULE]-[NUMBER]-[feature-name].md`
- Updates: `WORKFLOW/project-state.yaml` (increment `prds_count`)

## Validation
- [ ] PRD follows the mandatory template structure
- [ ] All 7 sections are present
- [ ] Acceptance criteria are specific and verifiable
- [ ] Business rules have unique IDs (RN-XXX)
- [ ] Acceptance criteria have unique IDs (CA-XXX)
