# Validate Tasks (PREVC: Review)

Uso: `/validate-tasks [nome]`

## Processo

1. **Ler tasks doc** completo
2. **Verificar cada task:**
   - [ ] T (Tarefa) definido?
   - [ ] A (Arquivo) com caminho completo?
   - [ ] C (Comportamento) com antes→depois?
   - [ ] E (Evidência) com critérios verificáveis?
3. **Verificar dependências:**
   - Tasks backend (FASE 3) devem preceder frontend (FASE 4)
4. **Verificar cobertura:**
   - Feature doc coberta por tasks?
   - Todos os critérios de aceite cobertos?

## Output

```
✅ Tasks validadas
[0/N] tasks pendentes
- TASK-X.Y.Z: ✅ Válida
- TASK-X.Y.Z: ⚠️ Falta [campo]
```

Se inválida → ajustar com `/decompose [nome]` novamente
