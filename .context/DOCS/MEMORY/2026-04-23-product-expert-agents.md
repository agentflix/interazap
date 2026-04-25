# Memory: Seeder de agentes especialistas no produto InteraZap

## Metadados

| Campo        | Valor                                                                                       |
| ------------ | ------------------------------------------------------------------------------------------- |
| **Tipo**     | 🧠 Decisão                                                                                  |
| **Data**     | 2026-04-23                                                                                  |
| **Autor**    | ORCHESTRATOR                                                                                |
| **Contexto** | Pedido do usuario para popular agentes que respondem com maestria sobre o produto InteraZap |
| **Tags**     | seeders, ai-agents, knowledge-base, multi-tenant, idempotencia                              |

---

## Situação

O usuario pediu agentes ja configurados no banco que respondam sobre o produto (precos, modulos, canais, recursos). O `PlatformTenantBootstrapAction` ja cria agentes genericos por segmento, mas nenhum deles e especialista no proprio produto InteraZap nem conhece os planos/recursos com precisao.

---

## Decisão / Aprendizado

1. **Seeder dedicado** `InteraZapProductAgentsSeeder` — separado do bootstrap de tenant porque:
    - Aplica-se SO ao tenant default `AGENTFLX` (nao a tenants de cliente)
    - Tem agenda propria de evolucao (catalogo de produto muda independente da estrutura de tenant)

2. **5 agentes com nomes neutros** (Atendimento, Vendas, Suporte, Qualificacao, Reativacao) ao inves de mascotes — facilita manutencao e e mais profissional para o tenant principal.

3. **Tool linkage via lookup**: `ai_agent_tools` usa `tool_id` (FK) e nao string. O seeder lista nomes de tools (`search_knowledge`, `send_message`, etc.) e busca em `AiAutopilotTool` por (`tenant_id`, `name`). Convencao: `name = Str::snake(str_replace('Tool', '', handler_class))`.

4. **Garantir tools existem antes de vincular**: descobrimos que `AiAutopilotToolSeeder` so era chamado dentro de `DemoSeeder` (opt-in). Para o seeder de produto funcionar fora de modo demo, adicionamos chamada explicita em `DatabaseSeeder` antes do novo seeder.

5. **Knowledge document via `AiKnowledgeDocument` + 1 chunk** com `embedding_status = PENDING` e `embedding = null` — embeddings reais serao gerados pelo job de processamento existente (nao queremos chamar OpenAI no seed).

---

## Alternativas Consideradas

| Alternativa                                            | Por que descartada                                                                                                  |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------- |
| Estender `PlatformTenantBootstrapAction::syncAgents()` | Roda para TODOS os tenants e os agentes especialistas em produto so fazem sentido para AGENTFLX                     |
| Salvar prompts em arquivos `.md` separados             | Aumenta complexidade do seed e dificulta idempotencia; preferimos heredoc inline                                    |
| Gerar embeddings no seed                               | Custo + latencia + dependencia de gateway disponivel; deixamos status `pending` para o pipeline existente processar |
| Usar nomes de mascote (Aria, Vito, etc.)               | Usuario preferiu nomes neutros e descritivos no plano aprovado                                                      |

---

## Consequências

### Positivas

- Tenant default sai do `migrate:fresh --seed` ja com agentes prontos para responder sobre o produto
- Idempotencia garantida (`updateOrCreate` em todos os recursos)
- `AiAutopilotToolSeeder` agora roda por padrao — beneficia qualquer ambiente novo
- Knowledge base inicial pronta para RAG quando o pipeline de embedding rodar

### Negativas / Trade-offs

- Knowledge document fica com `embedding_status = pending` ate algum job processar — agentes que dependem de `search_knowledge` para o catalogo so terao recall semantico apos isso
- Os system_prompts contem precos hardcoded (R$ 97/297/897) — se mudar tabela de precos, rodar seeder novamente para refletir
- `AiAutopilotToolSeeder` agora escala por tenant (cria 28 tools por tenant a cada `migrate:fresh`); aceitavel pois e idempotente

---

## Atualizacao (2026-04-23) — Saudacao humanizada e prioridade do agente de inbound

### Situacao

Mesmo apos o seeding dos 5 agentes de produto, a resposta inicial podia sair generica (ex.: "Ola! Estou aqui para ajudar...") porque o pipeline de dispatch escolhe trigger ativo primeiro e, na ausencia, cai em fallback para o primeiro agente `type=general` ativo. Com coexistencia de agentes legados, o agente efetivo podia nao ser `Atendimento`.

### Decisao

1. Sincronizar no seeder um trigger canonico `INBOUND_MESSAGE` ativo para `Atendimento`.
2. Desativar triggers `INBOUND_MESSAGE` ativos dos demais agentes no tenant `AGENTFLX`.
3. Incluir regra obrigatoria de primeiro turno em todos os `system_prompt` para apresentacao humanizada com nome e papel de consultor(a) da InteraZap.
4. Humanizar `fallback_message` por agente para consistencia em cenarios de instabilidade.
5. Invalidar cache de selecao de trigger/fallback imediatamente apos sincronizacao.

### Evidencia

- `active_inbound_total=1`
- `active_inbound_agent=Atendimento`
- `active_inbound_others=0`
- Prompt com regra de primeiro turno presente para o agente Atendimento.

### Impacto

- Reduce ambiguidade de roteamento de agente no inbound.
- Aumenta consistencia de tom e identidade de atendimento na primeira mensagem.

---

## Atualizacao (2026-04-23) — Unicidade do agente generalista

### Situacao

Mesmo com trigger inbound canonico, a base podia manter mais de um agente `type=general` ativo no tenant principal, o que aumenta risco operacional em fluxos que usem fallback por tipo.

### Decisao

1. Definir `Atendimento` como unico generalista ativo permitido no tenant `AGENTFLX`.
2. No seeder, desativar automaticamente outros agentes `type=general` ativos (sem exclusao).
3. Adicionar validacao hard-fail (RuntimeException) quando a pos-condicao nao for atendida:
    - exatamente 1 generalista ativo
    - nome do ativo = `Atendimento`

### Evidencia

- `active_general_total=1`
- `active_general_name=Atendimento`

### Impacto

- Remove duplicacao de generalista no estado ativo.
- Reduz variabilidade de comportamento em fallback baseado em `type=general`.

---

## Referências

- Plano aprovado: `PLANO-SEEDER-AGENTES-INTERAZAP.md` (raiz do repo)
- Seeder: `api/database/seeders/InteraZapProductAgentsSeeder.php`
- Registro em: `api/database/seeders/DatabaseSeeder.php`
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-04-23.md`

---

## Decisao: prompts ricos com persona humana real

**Contexto:** Mesmo apos fixar Atendimento como inbound default e adicionar uma "regra de primeiro turno", a IA ainda respondia genericamente ("Ola! Como posso ajudar voce hoje?" assinada como "Atendente"). O modelo (gpt-4o-mini) nao se ancora em prompts curtos e abstratos; precisa de persona concreta, anti-padroes explicitos e template literal de saudacao.

**Decisao:** Cada agente recebe nome humano (Sofia/Lucas/Bruno/Marina/Ana) + papel (consultora de atendimento, consultor comercial, especialista de produto, analista de qualificacao, consultora de relacionamento). O wrapper `prompt()` injeta cabecalho de marca, lista de anti-padroes ("nunca se identifique como IA/bot", "nunca use 'Como posso ajudar voce hoje?'"), e bloco PRIMEIRO TURNO com template literal exato (`"Oi! Aqui e {nome}, {papel} da InteraZap..."`). Corpo de cada prompt reestruturado em secoes (Missao, Principios, Fluxo, Tools, Escalonamento, Exemplo, Limites).

**Alternativas descartadas:**
- Manter nome tecnico ("Atendimento", "Vendas") como assinatura. Soa robotico - foi exatamente a causa raiz observada.
- Reduzir temperatura para 0 para forcar literalidade. Resolveria a saudacao mas mataria o tom natural.
- Usar few-shot com 5+ exemplos. Inflaria token_budget_input (6000) sem ganho proporcional - 1 exemplo + anti-padroes explicitos basta para o gpt-4o-mini.

**Aprendizado:** Para LLM generalista pequena (gpt-4o-mini), prompt curto e abstrato => resposta generica. A receita que funciona: (1) persona com nome humano, (2) anti-padroes negativos explicitos com EXEMPLO da frase proibida, (3) template literal da saudacao, (4) regra de continuidade ("a partir do segundo turno, NAO se reapresente").

**Armadilha:** Nao basta dizer "seja humano". E preciso dizer "NAO use a frase X" e dar a frase exata a evitar. Modelos pequenos seguem instrucoes negativas concretas melhor do que diretrizes positivas abstratas.
