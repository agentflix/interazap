# Feature: [Nome da Feature]

## Metadados

| Campo | Valor |
|-------|-------|
| ID | FEAT-NNN |
| Bounded Context | (Ex: Chat, CRM, Auth) |
| Workspaces afetados | (api, gateway, app, electron) |
| Complexidade | P / M / G |
| Status | Em Planning / Em Review / Em Execução / Concluída |
| Autor (PM) | |
| Aberta em | YYYY-MM-DD |
| Fechada em | |

---

## Resumo

[1 parágrafo: o que é e por que importa]

---

## Objetivo de Negócio

[Qual problema resolve? Qual oportunidade captura?]

---

## Bounded Context(s)

- (Ex: `Chat` — recebe mensagem; `CRM` — atualiza deal)

---

## Escopo

### Incluído

- [ ] Item 1
- [ ] Item 2

### Fora de Escopo

- Item A não será feito agora
- Item B será feito em outra feature

---

## Critérios de Aceite

- [ ] CA-1: critério verificável
- [ ] CA-2: critério verificável

---

## Dependências

- Bounded contexts: ver `.context/ARCHITECTURE/modules.yaml`
- Integrações externas: (ex: UazAPI, Z-API, Asaas, OpenAI)
- Features prévias: FEAT-XXX

---

## Riscos

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Multi-tenant leak? | Alto | Trait `BelongsToTenant` + teste isolamento |
| Webhook duplicado? | Médio | Idempotência via Redis |
| Custo OpenAI? | Médio | Cache embeddings + budget alert |

---

## Tasks

> Decomposição feita pelo ARCHITECT após Review.
> Ver `.context/DOCS/TASKS/[feature]-tasks.md`

---

## Histórico

- YYYY-MM-DD: Criada por @PM
- YYYY-MM-DD: Aprovada por @REVIEWER
- YYYY-MM-DD: Decomposta por @ARCHITECT
- YYYY-MM-DD: Concluída
