# Memory: Skill-Based Routing — Decisões de Implementação FEAT-052 Fase 3

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-05-02 |
| **Autor** | ORCHESTRATOR / BACKEND / FRONTEND |
| **Contexto** | FEAT-052 Fase 3 — Skill-Based Routing |
| **Tags** | [chat, routing, skills, lock, performance, zero-downtime] |

---

## Situação
Precisávamos implementar a Fase 3 do rodízio automático: skill-based routing, onde tickets com `category` definida são direcionados apenas para agentes que possuem a skill correspondente. Dentro do grupo de agentes qualificados, a distribuição deve ser round-robin.

---

## Decisão / Aprendizado

### 1. Schema aditivo separado (zero-downtime)
A Fase 2 já tinha antecipado o campo `skills` no resource e interface, mas a tabela de skills foi criada em migration separada (`2026_05_02_000001_create_chat_routing_agent_skills_table.php`). Isso permitiu deploy sem downtime entre Fase 2 e Fase 3.

### 2. Matching por `category` do ticket (sem migration no ticket)
O campo `category` já existia em `chat_tickets`. Não foi necessária nenhuma migration no ticket — usamos o valor diretamente no `skillBased()`. Se `category` for null, cai no fallback round-robin (todos os agentes).

### 3. `FOR UPDATE SKIP LOCKED` no grupo filtrado
`skillBased()` aplica `FOR UPDATE SKIP LOCKED` apenas nos agentes que possuem a skill do ticket. Isso minimiza contenção de lock — agentes sem a skill não são bloqueados.

### 4. Subquery correlacionada para evitar lock em múltiplas tabelas
Assim como na Fase 2 (`leastBusy()`), `skillBased()` usa `WHERE EXISTS` com subquery na tabela `chat_routing_agent_skills` para evitar JOIN que forçaria lock em múltiplas tabelas.

### 5. Frontend: skills como badges inline
Em vez de modal separado, skills são renderizadas como badges inline em cada card de agente na `RoutingAgentListComponent`. Isso simplifica a UX — o usuário vê e edita skills no contexto da lista.

### 6. Interface `ChatRoutingQueueAgent.skills: string[]` obrigatório
A decisão de tornar `skills` obrigatório na interface (em vez de optional) quebrou mocks em 4 arquivos de teste. Todos foram corrigidos. Essa é uma decisão consciente: o backend sempre retorna `skills` (array vazio quando não há), então o frontend pode assumir sua existência.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Migration em `chat_tickets` para adicionar `skill_id` | Campo `category` já existia e atende ao propósito; evita duplicação de conceitos |
| Modal separado para gerenciar skills | Mais cliques para o usuário; inline é mais rápido para adicionar/remover uma skill |
| `skills` como optional na interface | Forçaria `?.` em todo template e aumentaria complexidade; backend sempre retorna array |
| Lock em JOIN com `chat_routing_agent_skills` | PostgreSQL lockaria múltiplas tabelas, aumentando contenção; subquery correlacionada resolve |

---

## Consequências

### Positivas
- Deploy gradual entre fases sem downtime
- Mínima contenção de lock no banco
- UX simples e direta para gerenciar skills
- Testes cobrem matching, fallback, concorrência

### Negativas / Trade-offs
- Se `category` do ticket for alterada após criação, o ticket não é re-roteado automaticamente (escopo da feature é apenas no momento de criação)
- Skills são strings livres (não foreign key para tabela de skills pré-definidas) — pode haver inconsistência de nomenclatura

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-052-rodizio-atendimentos.md`
- Spec: `docs/superpowers/specs/2026-05-01-rodizio-atendimentos-design.md`
