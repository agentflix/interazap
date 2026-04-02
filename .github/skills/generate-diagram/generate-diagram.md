# Skill: generate-diagram

## Description
Generate a Mermaid diagram for architecture, module relationships, or user flows.

## Input
- `type`: Diagram type (`architecture`, `modules`, `user-flow`, `sequence`, `er`)
- `title`: Diagram title
- `context`: Description of what the diagram should represent

## Process
1. Analyze the requested diagram type
2. For `architecture`: Show layers (Frontend → API → Domain → Database/Cache)
3. For `modules`: Show module relationships from `WORKFLOW/modules.yaml`
4. For `user-flow`: Show user interaction sequence
5. For `sequence`: Show inter-service communication
6. For `er`: Show entity relationships
7. Generate Mermaid syntax
8. Save to `.context/ARCHETURE/[name].mmd`

## Output
- File: `.context/ARCHETURE/[name].mmd`

## Validation
- [ ] Valid Mermaid syntax
- [ ] Diagram accurately represents the described system
- [ ] Node labels are quoted when containing special characters
- [ ] No HTML tags in labels
