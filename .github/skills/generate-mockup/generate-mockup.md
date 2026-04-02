# Skill: generate-mockup

## Description
Generate a textual mockup or wireframe using Mermaid or ASCII art for UI feature planning.

## Input
- `feature`: Feature being designed
- `module`: Related module
- `screens`: List of screens to mockup
- `description`: UI behavior description

## Process
1. Analyze the feature requirements
2. Identify key UI elements and interactions
3. Generate mockup using either:
   - Mermaid flowchart for user interaction flows
   - ASCII wireframe for screen layouts
   - Mermaid sequence diagram for API interactions
4. Include state descriptions (loading, empty, error, success)
5. Reference shared components to use from `app/src/app/shared/components/`
6. Save to `.context/DOCS/PLANS/` as part of the plan or standalone

## Output
- File: Inline in plan document or `.context/ARCHETURE/mockup-[feature].mmd`

## Validation
- [ ] All required screens are represented
- [ ] Loading, empty, and error states are shown
- [ ] Shared components are referenced (not custom HTML)
- [ ] Data flow between screens is clear
