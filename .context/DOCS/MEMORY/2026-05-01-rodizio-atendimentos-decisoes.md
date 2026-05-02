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

### Negativas / Trade-offs
- Lock-in no PostgreSQL: `SKIP LOCKED` não é portável para outros bancos de dados.
- Transação mais longa no `CreateChatTicketAction` pode aumentar contenção em picos extremos de criação de tickets (mitigado pelo `SKIP LOCKED`).
- Falha silenciosa no routing exige monitoramento (logs/métricas) para detectar filas mal configuradas.

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-052.md`
- Task: `.context/DOCS/TASKS/TASK-052-04.md`
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-05-01.md`
