# Confirm Task (PREVC: Confirm)

Uso: `/confirm-task [feature] [TASK-NNN]`

## Processo Obrigatório

### 1. Verificar que passou na VALIDATION
- Todos os gates passaram?
- Todos os critérios de aceite (seção E) atendidos?
- Se não → abortar e voltar para EXECUTION

### 2. Registrar Evidências na Task
- Output dos testes (copiar resultado)
- Output dos gates (copiar resultado)
- Resumo do que foi implementado
- Marcar task como `✅ Concluída`

### 3. 📜 Atualizar CHANGELOG
Abrir/criar `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
Adicionar entrada:
```text
- [HH:MM] [TIPO] [escopo]: [Descrição concisa do que mudou]
  - Arquivos principais: [lista]
  - Ref: TASK-NNN / FEAT-NNN
```

### 4. 🧠 Atualizar MEMORY (se aplicável)
Pergunte-se:
- Foi tomada alguma decisão técnica relevante?
- Algo inesperado aconteceu que outros devem saber?
- Alguma armadilha foi encontrada?
- Algum padrão novo foi estabelecido?

Se SIM → criar arquivo em `.context/DOCS/MEMORY/[DATA]-[titulo-kebab].md`

### 5. 📊 Atualizar project-state.yaml
Em `.context/ARCHITECTURE/project-state.yaml`:
- Incrementar `tasks_completed`
- Decrementar `tasks_in_progress`
- Atualizar `last_validation`

### 6. Verificar Feature Completa
- Todas as tasks da feature estão ✅?
- Se sim → marcar feature doc como `✅ Concluída`

## Output
```
✅ TASK-X.Y.Z Concluída

📜 CHANGELOG: [entrada adicionada]
🧠 MEMORY: [criado / não necessário]
📊 Métricas: [X/Y] tasks da feature
➡️  Próxima: [TASK-NNN] ou "🎉 Feature completa!"
```
