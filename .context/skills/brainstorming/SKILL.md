---
name: brainstorming
description: "You MUST use this before any creative work — creating features, building components, adding functionality, or modifying behavior. Explores user intent, requirements and design before implementation. Do NOT use for implementing code, writing documentation without a design phase, or reviewing already-implemented work."
---

# Brainstorming Ideas Into Designs

Help turn ideas into fully formed designs and specs through natural collaborative dialogue.

Start by understanding the current project context, then ask questions one at a time to refine the idea. Once you understand what you're building, present the design and get user approval.

<HARD-GATE>
Do NOT invoke any implementation skill, write any code, scaffold any project, or take any implementation action until you have presented a design and the user has approved it. This applies to EVERY project regardless of perceived simplicity.
</HARD-GATE>

## Anti-Pattern: "This Is Too Simple To Need A Design"

Every project goes through this process. A todo list, a single-function utility, a config change — all of them. "Simple" projects are where unexamined assumptions cause the most wasted work. The design can be short (a few sentences for truly simple projects), but you MUST present it and get approval.

## Checklist

You MUST complete these items in order:

1. **Explore project context** — check files, docs, recent commits, `.context/ARCHITECTURE/project-brain.yaml`
2. **Offer visual companion** (if topic will involve visual questions) — this is its own message, not combined with a clarifying question. See `visual-companion.md`.
3. **Ask clarifying questions** — one at a time, understand purpose/constraints/success criteria
4. **Propose 2-3 approaches** — with trade-offs and your recommendation
5. **Present design** — in sections scaled to their complexity, get user approval after each section
6. **Write design doc (PRD)** — save to `.context/DOCS/PRDS/0000-PRD-<topic>.md` using the next available number
7. **Spec self-review** — quick inline check for placeholders, contradictions, ambiguity, scope
8. **User reviews written spec** — ask user to review the PRD file before proceeding
9. **Transition to PREVC** — invoke `@PM` with `/new-feature [nome]` to create the feature doc and begin Planning phase

## PRD Naming Convention

PRDs are saved to `.context/DOCS/PRDS/` with sequential numbering:
- Check existing files to find the next number
- Format: `0001-PRD-<topic-kebab>.md`, `0002-PRD-<topic-kebab>.md`, etc.
- Use the PRD template at `.context/DOCS/PRDS/_TEMPLATE.md` as base

## Process Flow

```
Explore context → Visual questions? → Ask clarifying questions → Propose approaches
→ Present design sections → User approves? → Write PRD → Spec self-review
→ User reviews PRD → Handoff to @PM (/new-feature)
```

## The Process

**Understanding the idea:**

- Check current project state first: files, docs, recent commits, `.context/ARCHITECTURE/`
- Before asking detailed questions, assess scope: if the request describes multiple independent subsystems, flag this immediately. Don't spend questions refining details of a project that needs to be decomposed first.
- If the project is too large for a single spec, help the user decompose into sub-projects. Each sub-project gets its own PRD → feature doc → tasks cycle.
- For appropriately-scoped projects, ask questions one at a time to refine the idea
- Prefer multiple choice questions when possible
- Only one question per message
- Focus on understanding: purpose, constraints, success criteria

**Exploring approaches:**

- Propose 2-3 different approaches with trade-offs
- Present options conversationally with your recommendation and reasoning
- Lead with your recommended option and explain why

**Presenting the design:**

- Once you believe you understand what you're building, present the design
- Scale each section to its complexity: a few sentences if straightforward, up to 200-300 words if nuanced
- Ask after each section whether it looks right so far
- Cover: architecture, components, data flow, error handling, testing
- Be ready to go back and clarify if something doesn't make sense

**Design for isolation and clarity:**

- Break the system into smaller units that each have one clear purpose
- For each unit: what does it do, how do you use it, what does it depend on?
- Smaller, well-bounded units are easier to implement and test

**Working in existing codebases:**

- Explore the current structure before proposing changes. Follow existing patterns.
- Include targeted improvements only where they serve the current goal.
- Don't propose unrelated refactoring.

## After the Design

**Writing the PRD:**

- Determine the next available PRD number from `.context/DOCS/PRDS/`
- Write to `.context/DOCS/PRDS/NNNN-PRD-<topic-kebab>.md`
- Use `.context/DOCS/PRDS/_TEMPLATE.md` as the base template
- Commit the PRD to git

**Spec Self-Review:**
After writing the PRD, look at it with fresh eyes:

1. **Placeholder scan:** Any "TBD", "TODO", incomplete sections? Fix them.
2. **Internal consistency:** Do any sections contradict each other?
3. **Scope check:** Is this focused enough for a single implementation?
4. **Ambiguity check:** Could any requirement be interpreted two different ways? Pick one and make it explicit.

Fix issues inline. No need to re-review — just fix and move on.

**User Review Gate:**
After the spec review loop passes, ask the user to review the written PRD:

> "PRD escrito em `.context/DOCS/PRDS/NNNN-PRD-<topic>.md`. Revise e me avise se quer ajustes antes de iniciar o planejamento."

Wait for the user's response. If they request changes, make them and re-run the self-review. Only proceed once the user approves.

**Transition to PREVC Planning:**

After PRD approval, invoke `@PM` with the command `/new-feature [nome-da-feature]` to create the feature doc in `.context/DOCS/FEATURES/` and begin the PREVC Planning phase.

Do NOT invoke any other skill or agent. `@PM /new-feature` is the only next step.

## Key Principles

- **One question at a time** — don't overwhelm with multiple questions
- **Multiple choice preferred** — easier to answer than open-ended
- **YAGNI ruthlessly** — remove unnecessary features from all designs
- **Explore alternatives** — always propose 2-3 approaches before settling
- **Incremental validation** — present design, get approval before moving on

## Visual Companion

When visual questions are expected, offer the companion once:
> "Algumas perguntas podem ser mais fáceis com suporte visual. Posso mostrar mockups e diagramas no browser. Quer tentar? (Requer abrir uma URL local)"

This offer MUST be its own message. Wait for response before continuing.
If they agree, read `visual-companion.md` for the full guide.

## Error Handling

- If no project context found: proceed with questions, note assumptions made
- If PRD template missing: create PRD using standard sections (Visão Geral, Problema, Solução, Critérios de Aceite)
- If user wants to skip design: explain why this phase matters, offer a compressed version instead of skipping
