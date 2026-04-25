# Decompose Feature (PREVC: Review → Tasks)

Uso: `/decompose [nome]`

## Processo

1. **Ler feature doc** completa
2. **Identificar camadas impactadas:**
   - Backend (Laravel): Entities, Services, Migrations, Controllers
   - Frontend (Angular): Components, Services, Pages
   - Database: Migrations, Constraints
3. **Decompor em tasks T.A.C.E:**
   - T: Tarefa - O QUE fazer
   - A: Arquivo - ONDE fazer
   - C: Comportamento - Antes → Depois
   - E: Evidência - Critérios verificáveis
4. **Estruturar por fase:**
   - FASE 3: Backend
   - FASE 4: Frontend
   - FASE 5: Integration

## Output

Tasks doc criado em `.context/DOCS/TASKS/[feature]-tasks.md`

## Estrutura Hierárquica

```
TASK-X.Y.Z
├── X = Fase (3=Backend, 4=Frontend, 5=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa
```
