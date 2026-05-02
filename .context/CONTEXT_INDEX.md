# CONTEXT_INDEX.md — Mapa de Camadas de Contexto

> Contexto não é carregado. Contexto é roteado.

## Layer 0 — Kernel (Sempre carregado)

- `AGENTS.md` na raiz do projeto

**Uso:** Regras essenciais, política de contexto, decisão inicial de fluxo.

---

## Layer 1 — Project Brain (Carregar quando precisar entender o projeto)

**Arquivos:**
- `.context/ARCHITECTURE/project-brain.yaml`

**Uso:** Stack, identidade, arquitetura resumida, convenções principais.

---

## Layer 2 — Architecture (Carregar quando houver risco arquitetural)

**Arquivos:**
- `.context/ARCHITECTURE/modules.yaml`
- `.context/ARCHITECTURE/dependencies.yaml`

**Uso:** Mudança multi-módulo, novo módulo, mudança de dependência, banco de dados, contratos, integrações.

---

## Layer 3 — Product / Feature (Carregar quando a tarefa envolver produto ou escopo)

**Arquivos:**
- `.context/DOCS/FEATURES/`
- `.context/DOCS/PRDS/`

**Uso:** Planejar feature, ver critérios de aceite, entender escopo, validar regra de negócio.

---

## Layer 4 — Tasks (Carregar quando houver execução de task)

**Arquivos:**
- `.context/DOCS/TASKS/`

**Uso:** Implementar TASK específica, ler T.A.C.E, ver evidências esperadas.

---

## Layer 5 — Workflow (Carregar quando a tarefa exigir processo formal)

**Arquivos:**
- `.context/WORKFLOW/PREVC.md`
- `.context/WORKFLOW/TACE.md`

**Uso:** Planejar, revisar, executar, validar, confirmar, classificar complexidade.

---

## Layer 6 — Memory (Carregar quando houver decisão técnica, dúvida recorrente ou risco)

**Arquivos:**
- `.context/DOCS/MEMORY/`

**Uso:** Ver decisões anteriores, evitar repetir erros, entender padrões não óbvios.

---

## Layer 7 — Source Code (Carregar quando a execução exigir código)

**Uso:** Debug, implementação, testes, refatoração, validação real.

**Regra:** Buscar primeiro. Abrir apenas arquivos diretamente relacionados. Preferir padrões existentes.

---

## Layer 8 — External Knowledge / MCP (Carregar quando o conhecimento local não for suficiente)

**Uso:** Documentação oficial, APIs recentes, frameworks, Context7, MCPs relevantes.

---

## Resumo Rápido

| Layer | Quando Carregar | Arquivos |
|-------|-----------------|----------|
| 0 | Sempre | `AGENTS.md` |
| 1 | Entender projeto | `project-brain.yaml` |
| 2 | Risco arquitetural | `modules.yaml`, `dependencies.yaml` |
| 3 | Escopo de produto | `FEATURES/`, `PRDS/` |
| 4 | Implementar task | `TASKS/` |
| 5 | Processo formal | `PREVC.md`, `TACE.md` |
| 6 | Decisão técnica | `MEMORY/` |
| 7 | Execução de código | Código fonte |
| 8 | Conhecimento externo | Docs/MCPs |