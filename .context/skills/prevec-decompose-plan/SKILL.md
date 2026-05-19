---
name: prevec-decompose-plan
description: Transforma PRD aprovado em feature doc no PREVEC. Lê planning session para contexto — sem re-leitura de arquitetura. Flag --fast combina feature doc + tasks em uma invocação para features P. Triggers: "decompor plano", "criar feature do PRD", "prevec-decompose-plan". Do NOT use sem PRD aprovado.
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-decompose-plan

Cria feature doc a partir do PRD. Usa planning session como fonte de contexto.

## Input

```
/prevec-decompose-plan [nome-do-prd]
/prevec-decompose-plan [nome-do-prd] --fast
```

`--fast`: válido apenas para `Complexidade: P` — gera feature doc + tasks T.A.C.E em uma única invocação. Pula handoff intermediário.

## Pré-condições

- PRD existe em `.context/DOCS/PRDS/`
- Planning session existe em `.context/.session/planning-[feature].md` (criado por prevec-new-plan)
- Se session ausente: rodar `/prevec-new-plan` antes

## Processo

### 1. Ler planning session

Ler `.context/.session/planning-[feature].md` — contém:
- **Architecture Snapshot:** stack, regras, módulos, dependências (não re-ler arquivos de origem)
- **PRD:** objetivo, critérios de aceite, complexidade, fora de escopo

**NÃO ler:** `project-brain.yaml`, `modules.yaml`, `dependencies.yaml`, `architecture.md`.
Tudo já está no session.

Se planning session ausente: ler o PRD + `context-snapshot.md` diretamente e criar o session.

### 2. Consultar MEMORY (se existir)

```bash
ls .context/DOCS/MEMORY/ 2>/dev/null
```

Ler apenas entradas relacionadas ao tema — não ler tudo.

### 3. Criar feature doc

Salvar em `.context/DOCS/FEATURES/[nome-kebab].md`:

```markdown
# Feature: [Nome]

## Metadados
- ID: FEAT-[NNN]
- PRD: .context/DOCS/PRDS/[NNNN]-PRD-[nome].md
- Bounded Context: [módulo do Architecture Snapshot]
- Complexidade: [P / M / G]
- Status: 🟡 Em Planning

## Resumo
[2-3 frases do objetivo]

## Escopo
### Incluído
- [ ] [item]

### Fora de Escopo
- [item]

## Dependências
- Features: [lista ou "nenhuma"]
- Módulos: [lista do Architecture Snapshot]
- Externas: [lista]

## Critérios de Aceite
- [ ] [critério verificável]

## Fases Estimadas
- [x] **Fase 1 — Planning** ✅
- [ ] **Fase 2 — Design** (UI/UX, fluxo de tela, wireframe)
- [ ] **Fase 3 — Backend** (domain, service, API, eventos)
- [ ] **Fase 4 — Frontend** (componentes, páginas, estado)
- [ ] **Fase 5 — Integration** (E2E, contrato cross-camada)

## Tasks
> Gerado por /prevec-decompose-task
```

### 4. Mapear fases

Usar Architecture Snapshot + PRD do session para marcar fases:

| Fase | Incluir quando |
|---|---|
| Fase 2 — Design | PRD menciona UI, tela nova, fluxo visual |
| Fase 3 — Backend | API, regra de negócio, domain, eventos |
| Fase 4 — Frontend | Componente, página, estado, formulário |
| Fase 5 — Integration | E2E, contrato cross-camada |

### 5. Atualizar planning session

Preencher seção **Feature Doc Draft** no session:
- Bounded context, dependências, fases marcadas, decisões de planning
- `Status: FEATURE_DOC`

### 6. Validar

- Escopo claro (incluído + fora)
- Critérios verificáveis — sem "funciona corretamente"
- Bounded context identificado
- Ao menos uma fase além de Planning marcada

### 7. Modo --fast (só para Complexidade: P)

Se `--fast` foi passado: continuar diretamente para decomposição de tasks sem handoff.
Executar os passos de `prevec-decompose-task` inline — usar o session já carregado.
Ao final: arquivar planning session.

### 8. Handoff (modo normal)

```
Feature doc: .context/DOCS/FEATURES/[nome].md
Fases: [lista]
Planning session atualizado.
Próximo: /prevec-decompose-task [nome]
```

## Output

```
✅ Feature doc: .context/DOCS/FEATURES/[nome].md
📋 Complexidade: [P/M/G]
📋 Fases: [lista]
📋 Critérios de aceite: [N]
➡️  Próximo: /prevec-decompose-task [nome]
```

## Error Handling

- Planning session ausente: criar do zero lendo PRD + `context-snapshot.md`
- PRD não encontrado: listar `.context/DOCS/PRDS/` e pedir confirmação
- Bounded context ambíguo: perguntar ao usuário
- Feature similar existente: alertar e pedir confirmação
- `--fast` em feature M ou G: recusar, informar que `--fast` só é válido para complexidade P
