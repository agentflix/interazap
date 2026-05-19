# Confirmar Task (PREVC: Confirm)

Uso: `/confirm-task [feature] [TASK-NNN]`

## Pré-condição

Task DEVE ter passado na VALIDATION (gates ✅ + critérios E ✅).
Se não passou → abortar e voltar para EXECUTION.

## Processo Obrigatório

### 1. Registrar Evidências na Task
- Copiar output dos testes e gates para o arquivo de tasks
- Marcar task como `✅ Concluída`

### 2. Atualizar CHANGELOG

Abrir/criar `.context/DOCS/CHANGELOG/[YYYY-MM-DD].md`

```text
- [HH:MM] [TIPO] [[escopo]]: Descrição concisa do que mudou
  - Arquivos principais: [lista]
  - Ref: TASK-NNN
```

Tipos: `FEAT | FIX | REFACTOR | DOCS | TEST | CHORE | BREAKING`
Se não existir o arquivo do dia → criar usando `_TEMPLATE.md`.

### 3. Atualizar MEMORY (se aplicável)

Perguntas a responder:
- Decisão técnica relevante foi tomada?
- Algo inesperado que outros devem saber?
- Armadilha encontrada?
- Padrão novo estabelecido?

Se SIM para qualquer uma → criar `.context/DOCS/MEMORY/[YYYY-MM-DD]-[titulo].md` usando `_TEMPLATE.md`.

### 4. Atualizar project-state.yaml

Em `.context/ARCHITECTURE/project-state.yaml`:
- Incrementar `tasks_completed`
- Decrementar `tasks_in_progress`
- Atualizar `last_updated`

### 5. Criar Commit Semântico

```bash
git add [arquivos específicos]
git commit -m "tipo(escopo): descrição em pt-BR"
```
Sem `git push`.

### 6. Verificar Feature Completa

- Todas as tasks ✅? → marcar feature como `✅ Concluída`
- Gerar entrada de resumo no CHANGELOG
- Incrementar `features_completed` em `project-state.yaml`

## Saída Esperada

```
✅ TASK-NNN Concluída

📜 CHANGELOG: [entrada adicionada em .context/DOCS/CHANGELOG/YYYY-MM-DD.md]
🧠 MEMORY: [criado / não necessário]
📊 Métricas: project-state.yaml atualizado
🔖 Commit: [mensagem do commit]
➡️  Próxima: TASK-NNN ou "Feature [nome] concluída!"
```
