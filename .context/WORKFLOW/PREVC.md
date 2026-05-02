# PREVC — Workflow para Features Médias e Grandes

**PREVC** = Planning → Review → Execution → Validation → Confirm

## Quando usar

- Feature média ou grande
- Mudança multi-módulo
- Nova integração
- Refatoração significativa

## Quando NÃO usar

- Bug pequeno (use Fast Path)
- Ajuste trivial
- Correção localizada

---

## Planning

**Objetivo:** Entender problema, definir escopo, identificar impacto

**Saída:**
- Feature documentada
- Escopo claro
- Critérios de aceite
- Riscos conhecidos

**Ações:**
- Consultar `.context/DOCS/MEMORY/` se houver decisão relevante
- Criar ou atualizar feature doc em `.context/DOCS/FEATURES/`
- Decompor em tasks T.A.C.E

---

## Review

**Objetivo:** Validar escopo, arquitetura, dependências

**Saída:**
- Feature aprovada ou ajustada
- Tasks claras
- Riscos documentados
- Dependências explícitas

**Ações:**
- Revisar com outro desenvolvedor ou sozinho
- Validar não viola regras do AGENTS.md do módulo
- Confirmar que arquitetura suporta a feature

---

## Execution

**Objetivo:** Implementar tasks seguindo T.A.C.E

**Saída:**
- Código implementado
- Testes ou validações aplicáveis

**Ações:**
- Manter alterações pequenas e seguras
- Respeitar padrões do módulo (API ou Gateway)
- Commits atômicos por task

---

## Validation

**Objetivo:** Validar comportamento, testes, critérios de aceite

**Saída:**
- Evidência de validação
- Critérios confirmados ou pendências claras

**Ações:**
- Rodar `composer gate:all` (API) ou `pnpm lint && pnpm test` (Gateway)
- Verificar critérios de aceite
- Reprovar se evidência insuficiente

---

## Confirm

**Objetivo:** Fechar feature, registrar evidências, atualizar estado

**Saída:**
- Feature marcada como concluída
- MEMORY atualizado se houve aprendizado relevante

**Ações:**
- Atualizar estado do projeto se necessário
- Registrar em `.context/DOCS/MEMORY/` apenas se houve decisão técnica importante