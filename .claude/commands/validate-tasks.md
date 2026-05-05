# /validate-tasks — Validar Qualidade das Tasks

Uso: `/validate-tasks [nome]`

## Quem executa
- @QA (com apoio de @REVIEWER)

## Checklist
- [ ] Toda task tem T (frase imperativa específica)
- [ ] Toda task tem A (paths de arquivos exatos)
- [ ] Toda task tem C (estado antes → depois)
- [ ] Toda task tem E (critérios verificáveis com gates)
- [ ] Toda task tem responsável (agent identificável)
- [ ] Tasks em ordem topológica correta
- [ ] Checkpoint de fase definido no final de cada fase
- [ ] Sem tasks vagas ("melhorar X", "refatorar Y")

## Decisão
- ✅ Aprovado → tasks prontas para Execution
- 🔄 Solicitar ajustes → @ARCHITECT refina

## Output
Tasks prontas para `/implement-task`.
