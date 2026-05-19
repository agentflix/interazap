# Design — InteraZap

Wireframes, mockups e specs de UI criados pelo PLANNER (modo DESIGNER).

## Como usar

1. PLANNER (modo DESIGNER) cria artefato antes da EXECUTION
2. BUILDER (modo FRONTEND) lê artefato antes de implementar — **obrigatório**
3. Todo artefato DEVE ser aprovado antes do BUILDER iniciar tasks Frontend

## Convenção de nome

```
[feature]-[tipo].md
```

Tipos: `wireframe`, `component-spec`, `ux-flow`

Exemplos:
- `chat-inbox-wireframe.md`
- `ai-agent-config-ux-flow.md`
- `billing-plans-component-spec.md`

## Template

Ver `_TEMPLATE.md` nesta pasta.

## Stack de referência

- **Framework:** Angular 17 (standalone components, signals)
- **Styling:** Tailwind CSS
- **Mobile:** Capacitor (iOS + Android) — mobile-first
- **UI Kit:** `.app/src/app/pages/ui-kit/`
