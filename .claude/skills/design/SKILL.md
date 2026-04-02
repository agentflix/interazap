---
name: design
description: >
    Visual design direction for InteraZap. Read this skill before any UI task.
    Defines aesthetic, tokens, typography, spacing rules, and what to avoid.
    Mandatory for @DESIGNER and @FRONTEND agents.
---

# Skill: design

## Aesthetic Direction

**Style**: Dark professional SaaS — clean, high-contrast, data-dense but breathable.
**Tone**: Confident, modern, minimal decoration. Every element earns its place.
**Reference**: Linear.app, Vercel Dashboard, Resend.com

---

## Typography

| Role    | Token / Class              | Usage                            |
| ------- | -------------------------- | -------------------------------- |
| Display | `text-2xl font-semibold`   | Page titles, modal headers       |
| Heading | `text-lg font-medium`      | Section titles, card headers     |
| Body    | `text-sm`                  | Default content, descriptions    |
| Label   | `text-xs font-medium`      | Form labels, table headers       |
| Caption | `text-xs text-neutral-400` | Helper text, timestamps          |
| Mono    | `font-mono text-xs`        | IDs, code snippets, status codes |

**Font**: Defined via Tailwind config — never override inline.
**Never use**: Inter, Roboto, Arial, or system-ui directly.

---

## Color Tokens

Always use semantic Tailwind tokens. Never hardcode hex values.

### Backgrounds

| Token            | Usage                               |
| ---------------- | ----------------------------------- |
| `bg-neutral-950` | App background (darkest)            |
| `bg-neutral-900` | Page/section background             |
| `bg-neutral-800` | Card background                     |
| `bg-neutral-700` | Elevated card, hover surface        |
| `bg-white`       | Light mode base (with `dark:` pair) |

### Text

| Token              | Usage                       |
| ------------------ | --------------------------- |
| `text-neutral-50`  | Primary text (dark mode)    |
| `text-neutral-400` | Secondary/muted text        |
| `text-neutral-600` | Disabled text               |
| `text-white`       | Text on colored backgrounds |

### Semantic

| Token              | Usage                      |
| ------------------ | -------------------------- |
| `text-green-400`   | Success, active, online    |
| `text-red-400`     | Error, critical, danger    |
| `text-yellow-400`  | Warning, pending           |
| `text-blue-400`    | Info, link, primary action |
| `text-neutral-400` | Inactive, disabled         |

### Always pair light/dark

```html
<!-- Correct -->
<div class="bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-50">
    <!-- Wrong — missing dark pair -->
    <div class="bg-white text-neutral-900"></div>
</div>
```

---

## Spacing Scale

Use **only** these values. Never arbitrary numbers.

| Token   | Value | Usage                      |
| ------- | ----- | -------------------------- |
| `p-1`   | 4px   | Tight internal padding     |
| `p-2`   | 8px   | Component internal padding |
| `p-4`   | 16px  | Standard component padding |
| `p-6`   | 24px  | Section padding            |
| `p-8`   | 32px  | Page section padding       |
| `p-12`  | 48px  | Large section separation   |
| `gap-2` | 8px   | Tight item gap             |
| `gap-4` | 16px  | Standard gap               |
| `gap-6` | 24px  | Section gap                |

---

## Component Rules

### Always use shared components

Check before creating anything new:

- `app/src/app/shared/components/` — 35+ components
- `http://localhost:4200/ui-kit` — visual reference

| Need              | Use                         | Never use                       |
| ----------------- | --------------------------- | ------------------------------- |
| Status pill/badge | `status-badge`              | Inline div with hardcoded color |
| CRUD listing      | `CrudPageComponent`         | Raw `<table>`                   |
| Any button        | `button` / `loading-button` | Raw `<button>`                  |
| Text field        | `text-input`                | Raw `<input>`                   |
| Select/dropdown   | `select-input`              | Raw `<select>`                  |
| Empty state       | `empty-state`               | Custom empty div                |
| Loading rows      | `skeleton-table-row`        | Spinner-only                    |
| Row actions       | `table-actions`             | Custom action buttons           |
| Modal             | `modal` / `confirm-modal`   | Custom overlay                  |
| Page header       | `page-title`                | Custom `<h1>` with inline style |

---

## Mandatory Visual States

Every component or page **must** implement all 4 states:

| State   | Implementation                                    |
| ------- | ------------------------------------------------- |
| Loading | `skeleton-table-row` for lists; spinner for forms |
| Empty   | `empty-state` component with icon + message + CTA |
| Error   | Error banner or inline error with retry action    |
| Default | Full content, properly formatted                  |

Missing any state = incomplete implementation.

---

## Layout Principles

- **1 primary CTA per page** — visually dominant, positioned top-right or inline with header
- **Visual hierarchy**: title > subtitle > content > actions > meta
- **Negative space** — breathe. Don't fill every pixel.
- **Alignment**: consistent left-alignment for content, right for actions
- **Density**: data tables can be compact; forms should be relaxed

---

## What to Avoid

```
❌ Purple gradients on white backgrounds
❌ Inline badges (use status-badge component)
❌ Hardcoded hex colors
❌ Arbitrary spacing (margin: 13px, padding: 7px)
❌ Missing dark mode pair
❌ Raw HTML elements when shared component exists
❌ More than 1 primary CTA per screen
❌ Skeleton-less loading states (never blank + spinner only)
❌ Empty states without a CTA
❌ Font sizes outside the typography scale
```

---

## Golden Model

Reference implementation for any new CRUD page:

```
app/src/app/pages/crm/contacts/
```

Study this before building any new page. It demonstrates:

- Correct use of `CrudPageComponent`
- Loading + empty + error states
- `status-badge` usage
- `table-actions` placement
- Signal-based state management
- `OnPush` change detection pattern
