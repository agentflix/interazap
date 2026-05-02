# Memory: Decisões de Arquitetura — Rodízio Automático de Atendimentos (FEAT-052 Fase 1)

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-05-01 |
| **Autor** | DEV / BACKEND / FRONTEND / QA |
| **Contexto** | FEAT-052 Fase 1 — implementação do rodízio automático de atendimentos (round-robin por fila) |
| **Tags** | chat, routing, postgres, transactions, zero-downtime |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Durante a Fase 1 da FEAT-052 foi necessário definir a arquitetura do algoritmo de distribuição de tickets entre agentes, o modelo de dados para filas de roteamento, o ponto de hook no fluxo de criação de tickets e a estratégia de deploy das próximas fases sem indisponibilidade.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

1. **SKIP LOCKED para concorrência no round-robin:** O `ChatRoutingService` utiliza `FOR UPDATE SKIP LOCKED` do PostgreSQL para selecionar o próximo agente disponível, garantindo que requests concorrentes não bloqueiem uns aos outros.
2. **Escopo via `instance_id` nullable:** Uma única coluna `instance_id` (NULL = fila global, NOT NULL = fila de canal) define o escopo da fila, eliminando a necessidade de uma coluna `scope` separada.
3. **Fallback global → canal:** Quando um ticket é criado em um canal, o sistema busca primeiro uma fila ativa específica daquele canal; se não existir, busca a fila global. Isso permite que o gestor defina um padrão global e sobrescreva apenas onde necessário.
4. **Transação única para routing + assign:** O hook no `CreateChatTicketAction` executa a lógica de routing e o assign do ticket ao agente dentro da mesma transação de banco da criação do ticket.
5. **Falha no routing não bloqueia criação do ticket:** O bloco de routing está envolvido em `try/catch`; se não houver fila ativa ou o routing falhar por qualquer motivo, o ticket ainda é criado e ficará na fila manual.
6. **Schema preparado para Fases 2 e 3 com zero-downtime:** A tabela `chat_routing_queues` já inclui `max_open_tickets_per_agent` (NULL = ilimitado). A tabela de skills da Fase 3 será uma migration aditiva separada, evitando alterações destrutivas no deploy.

## Decisão / Aprendizado — Fase 2 (Least Busy)

7. **Subquery correlacionada para contagem de tickets:** O `leastBusy()` usa subquery correlacionada (`SELECT COUNT(*) FROM chat_tickets WHERE assigned_to = chat_routing_queue_agents.user_id`) em vez de `LEFT JOIN`. Isso evita que o PostgreSQL tente lockar a tabela `chat_tickets` junto com `chat_routing_queue_agents` durante o `FOR UPDATE SKIP LOCKED`, mantendo o lock exclusivo na tabela de agentes.
8. **Contagem de tickets em status `pending` e `open`:** Apenas tickets ativos contam para a "carga" do agente. Tickets fechados, em espera ou arquivados não entram no cálculo. Isso reflete a carga de trabalho real.
9. **Ordenação composta no least_busy:** Contagem ASC → `last_assigned_at ASC NULLS FIRST` → `position ASC`. Garante distribuição justa mesmo quando múltiplos agentes têm a mesma carga.
10. **max_open_tickets_per_agent como filtro (não ordenação):** Agentes que atingiram o limite são excluídos da seleção. Se todos os agentes ativos atingirem o limite, o routing retorna `null` e o ticket fica sem atribuição automática — comportamento intencional para evitar sobrecarga.
11. **Sem migration na Fase 2:** A coluna `max_open_tickets_per_agent` já existia desde a Fase 1 (NULL = ilimitado). A Fase 2 foi puramente código + testes + UI, confirmando a decisão de schema antecipado.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|------------|-------------------|
| `lockForUpdate()` genérico do Laravel | Bloqueia requests concorrentes em fila de espera; `SKIP LOCKED` permite que cada request pegue imediatamente o próximo agente disponível sem contenção. |
| Coluna `scope` (enum: global/canal) além de `instance_id` | Duplica a representação do mesmo conceito, criando risco de inconsistência (ex: `scope=global` com `instance_id!=NULL`). |
| Fila global OU canal (sem fallback) | Forçaria o gestor a replicar a configuração em todos os canais sempre que quisesse um comportamento padrão. |
| Routing em transação separada da criação do ticket | Cria janela de inconsistência: o agente poderia ter seu "turno" consumido sem que o ticket fosse de fato atribuído a ele. |
| Falha no routing abortar a criação do ticket | Regra de negócio prioritária: o cliente deve sempre conseguir abrir um ticket, mesmo que o sistema de routing esteja indisponível. |
| Migration destrutiva para adicionar skills na Fase 3 | Quebraria compatibilidade e exigiria downtime coordenado; migrations aditivas permitem deploy contínuo. |
| LEFT JOIN com `FOR UPDATE SKIP LOCKED` | Lockaria ambas as tabelas (`chat_routing_queue_agents` + `chat_tickets`), aumentando contenção e risco de deadlock; subquery correlacionada mantém lock apenas na tabela de agentes. |
| Contar todos os tickets (incluindo fechados) | Não reflete carga atual do agente; tickets fechados não consomem atenção ativa. |
| max_open_tickets_per_agent como soft limit (ainda ordena agentes no limite) | Agentes no limite seriam selecionados se fossem os "menos ocupados", violando a intenção do limite; exclusão garante respeito à regra. |
| Implementar least_busy com cache Redis | Adicionaria complexidade de infra e inconsistência eventual; query SQL direta é atômica e sempre consistente. |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Escalabilidade sob carga concorrente graças ao `SKIP LOCKED`.
- Modelo de dados normalizado e imune a inconsistências de escopo.
- Experiência do gestor simplificada com configuração global + override por canal.
- Consistência transacional garantida entre criação do ticket e atribuição ao agente.
- Resiliência do fluxo de atendimento: ticket nunca é perdido por falha de routing.
- Deploy contínuo preservado entre as fases da feature.
- **Fase 2:** Distribuição justa por carga real (não apenas ordem fixa), com proteção contra sobrecarga via `max_open_tickets_per_agent`.
- **Fase 2:** Zero migrations → deploy imediato sem risco de schema.

### Negativas / Trade-offs
- Lock-in no PostgreSQL: `SKIP LOCKED` não é portável para outros bancos de dados.
- Transação mais longa no `CreateChatTicketAction` pode aumentar contenção em picos extremos de criação de tickets (mitigado pelo `SKIP LOCKED`).
- Falha silenciosa no routing exige monitoramento (logs/métricas) para detectar filas mal configuradas.
- **Fase 2:** Subquery correlacionada em `least_busy` pode ter performance inferior a um JOIN em filas muito grandes (mitigado por índices em `chat_tickets.assigned_to` e `status`).
- **Fase 2:** Se todos os agentes atingirem `max_open_tickets_per_agent`, o ticket fica sem atribuição automática — requer alerta/monitoreamento para o gestor.

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-052.md`
- Task: `.context/DOCS/TASKS/TASK-052-04.md`
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-05-01.md`
