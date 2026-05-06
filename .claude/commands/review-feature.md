# /review-feature — Revisar Feature Doc (PREVC: Review)

Uso: `/review-feature [nome]`

## Quem executa
- @REVIEWER (com apoio de @ARCHITECT)

## Checklist
- [ ] Bounded context(s) listados
- [ ] Workspaces listados
- [ ] Escopo claro (incluído + fora)
- [ ] Critérios de aceite verificáveis
- [ ] Complexidade estimada
- [ ] Dependências identificadas
- [ ] Riscos sinalizados
- [ ] Conformidade com `.context/ARCHITECTURE/`

## Decisão
- ✅ Aprovado → marcar status `Em Review` → `Aprovada` → executar `/decompose [nome]`
- 🔄 Solicitar ajustes → comentários no doc, status volta para `Em Planning`

## Output
Feature aprovada (ou com ajustes solicitados).
