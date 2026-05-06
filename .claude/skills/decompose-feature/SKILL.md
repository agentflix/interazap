# Skill: Decompose Feature em Tasks T.A.C.E

> Como ARCHITECT decompõe uma feature aprovada em tasks executáveis.

## Pré-requisitos

- Feature doc aprovado por REVIEWER (`.context/DOCS/FEATURES/[feature].md`)
- Bounded context(s) afetado(s) identificado(s)

## Passos

### 1. Mapear Camadas Afetadas

Pergunte para cada feature:
- Precisa de **schema** (PostgreSQL)? → Fase 3 + DBA
- Precisa de **lógica de domínio** (Laravel)? → Fase 3 + BACKEND
- Precisa de **integração externa** (OpenAI, Asaas, WhatsApp)? → Fase 4 + GATEWAY
- Precisa de **UI**? → Fase 5 + FRONTEND
- Precisa de **fluxo end-to-end**? → Fase 6 + DEV

### 2. Estrutura Hierárquica

```
TASK-X.Y.Z
├── X = Fase
│     1=Planning, 2=Design, 3=Backend, 4=Gateway, 5=Frontend, 6=Integration
├── Y = Feature dentro da fase
└── Z = Etapa de codificação
```

Exemplo: `TASK-3.2.1` = Backend (3) → Domain layer (2) → Criar Entity (1)

### 3. Para cada Task aplique T.A.C.E

Cada task DEVE ter as 4 seções: **T**, **A**, **C**, **E**.

### 4. Ordem de Execução

```
DBA (schema) → BACKEND (Domain, Action) → GATEWAY (integração) → FRONTEND (UI)
```

Tasks que podem rodar em paralelo: indicar `paraleliza com TASK-X.Y.Z` em metadata.

### 5. Gates por Fase

Ao final de cada fase, criar checkpoint de fase:

```
### Revisão de Fase 3 (REVIEWER + QA)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Migrations reviewed | DBA | aguardando |
| Domain layer puro PHP | REVIEWER | aguardando |
| Tests passing | QA | aguardando |
```

### 6. Output

Arquivo: `.context/DOCS/TASKS/[feature]-tasks.md` (use `_TEMPLATE.md`)

### 7. Validação

Rodar `/validate-tasks [feature]` para checar:
- [ ] Toda task tem T, A, C, E
- [ ] Toda task tem responsável (agent)
- [ ] Tasks em ordem topológica (dependências respeitadas)
- [ ] Critérios de aceite verificáveis
