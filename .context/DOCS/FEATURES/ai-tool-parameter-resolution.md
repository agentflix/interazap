# Feature: ai-tool-parameter-resolution

**Status:** [x] Em planejamento | [ ] Em execução | [ ] Concluída
**Data:** 2026-05-20
**PRD:** Não existe PRD separado; escopo nasceu de evidência real da suíte `SIM30`.

## Visão Geral

Corrigir o contrato entre agentes IA, tools e orquestrador para que o LLM não precise conhecer UUIDs internos nem consiga finalizar uma conversa sem resposta ao cliente. A feature torna tools críticas tolerantes a parâmetros humanos, hidrata contexto operacional antes da chamada ao modelo e adiciona uma barreira anti-silêncio no gateway.

## Problema

No cenário real `SIM30-01`, o fluxo público de webchat criou o ticket `WC-2605200004` e o agente `Atendimento` delegou corretamente para `Vendas` com `delegate_to_agent` e `return_after=false`. Depois da delegação, o agente `Vendas` executou tools de bastidor com parâmetros inválidos:

- `notify_seller` recebeu valores como `"Vendas"` e `"vendedor_id"` em `seller_id`, mas a tool espera UUID de `auth_users`.
- `create_task` recebeu `ticket_id` no campo `negotiation_id`, retornando `Negotiation not found`.
- As runs foram marcadas como `completed`, mas a última mensagem do cliente ficou sem `send_message` posterior.

O bug não é falta de tool no agente: `Vendas` tem `send_message`, `notify_seller`, `create_task`, `create_negotiation` e demais tools vinculadas. O problema é que as tools expostas ao LLM usam contrato interno demais e o orquestrador aceita conclusão silenciosa.

## Módulos Afetados

- [x] api/ (Laravel 12)
- [x] gateway/ (NestJS 11)
- [ ] app/ (Angular 20)
- [ ] Infraestrutura

## Objetivos

- Permitir que tools chamadas pelo LLM aceitem parâmetros humanos/semânticos e resolvam entidades internas com `tenant_id`.
- Enviar contexto operacional útil ao modelo, incluindo seller padrão, agentes disponíveis, ticket, contato e negociação ativa.
- Garantir que toda run de chat termine com resposta ao cliente, handoff humano, fechamento ou delegação válida.
- Criar uma tool de alto nível para fluxo comercial, reduzindo composição frágil de `notify_seller + create_task + send_message` pelo modelo.

## Fora de Escopo

- Trocar modelo/provider de IA.
- Fazer gateway acessar PostgreSQL diretamente.
- Reescrever todos os prompts como única solução.
- Remover compatibilidade das tools atuais.
- Alterar UI do operador.

## Princípios de Arquitetura

| Princípio | Decisão |
|---|---|
| Tenant isolation | Toda resolução de entidade ocorre na API Laravel com filtro `tenant_id`. |
| Gateway sem PostgreSQL | Gateway continua usando API/internal client para execução e resolução. |
| Tools amigáveis para LLM | Tools aceitam nomes, emails, aliases, intenção e IDs opcionais. |
| Resposta obrigatória | Run de chat não pode finalizar silenciosa após mensagem inbound. |
| Compatibilidade | Parâmetros UUID atuais continuam válidos. |

## Solução Técnica

### 1. Resolver Entidades no Backend

Criar um serviço na API, por exemplo `AiToolEntityResolver`, para resolver entidades usadas por tools:

- `resolveSeller($tenantId, $input, $ticketId = null)`
- `resolveNegotiation($tenantId, $input, $ticketId = null, $contactId = null)`
- `resolveAgent($tenantId, $input)`

Ordem sugerida para seller:

1. `seller_id` UUID válido dentro do tenant.
2. `seller_email`.
3. `seller_name` ou `seller` por match normalizado.
4. responsável do ticket (`assigned_to`).
5. seller padrão do tenant.
6. owner/admin ativo do tenant.

As tools devem retornar erro estruturado recuperável quando não conseguirem resolver entidade, sem SQL exception.

### 2. Atualizar Tools Críticas

`notify_seller`:

- Aceitar `seller_id`, `seller`, `seller_name`, `seller_email`, `use_default_seller`.
- Validar UUID antes de persistir.
- Resolver seller via serviço.
- Retornar `error_code=seller_not_found` quando não resolver.

`create_task`:

- Manter `negotiation_id` como caminho feliz.
- Aceitar `ticket_id` e `contact_id` para buscar negociação ativa.
- Nunca tratar `ticket_id` como `negotiation_id`.
- Retornar `error_code=negotiation_not_found` recuperável.

`send_message`:

- Manter normalização de `message`, `text`, `body` para `content`.
- Garantir `ticket_id` a partir do contexto quando ausente.

### 3. Hidratar Contexto Operacional

Estender o snapshot/contexto enviado ao gateway com:

```json
{
  "ticket_id": "uuid",
  "contact_id": "uuid",
  "current_agent": {"id": "uuid", "name": "Vendas"},
  "default_seller": {"id": "uuid", "name": "Rosa Lopes Pontes", "email": "rosa@interazap.com.br"},
  "active_negotiation": {"id": "uuid|null", "title": "string|null"},
  "available_agents": [
    {"id": "uuid", "name": "Vendas"},
    {"id": "uuid", "name": "Suporte"}
  ]
}
```

Fonte recomendada: API Laravel, em `AutopilotRunSnapshotResolver`, `AiContextBuilderService` ou endpoint internal equivalente. O gateway apenas consome esse payload.

### 4. Barreira Anti-Silêncio no Gateway

Ao finalizar uma run de chat, o gateway deve detectar se houve ação terminal:

- `send_message` com sucesso;
- `transfer_to_human` com sucesso;
- `close_ticket` com sucesso;
- `delegate_to_agent` com `return_after=false` e child run criado.

Se a run executou somente tools de bastidor (`notify_seller`, `create_task`, `create_note`, etc.) e a última mensagem persistida é inbound, o gateway deve continuar a iteração com os resultados das tools ou executar fallback via `send_message`.

Fallback mínimo:

```txt
Perfeito, registrei seu interesse e vou acionar nosso time comercial para continuar com você.
```

### 5. Tool Comercial de Alto Nível

Criar `register_sales_interest` para encapsular o fluxo comercial:

```json
{
  "ticket_id": "uuid",
  "plan": "Professional",
  "team_size": 8,
  "urgency": "this_week",
  "intent": "close_deal",
  "message_to_customer": "Perfeito, vou acionar nosso especialista comercial para te passar os próximos passos."
}
```

Responsabilidades:

- Resolver ticket e contato.
- Buscar ou criar negociação quando necessário.
- Resolver seller padrão.
- Criar notificação/tarefa/nota.
- Enviar mensagem ao cliente.
- Retornar IDs criados e status consolidado.

## Critérios de Aceite

- [ ] `notify_seller` com `seller="Rosa"` resolve para UUID do tenant e cria notificação.
- [ ] `notify_seller` com `seller_id="Lucas"` não lança SQL exception e retorna erro recuperável.
- [ ] `create_task` com `ticket_id` em vez de `negotiation_id` não tenta persistir usando ID errado.
- [ ] Payload `ai.run.request` contém `default_seller` e `available_agents`.
- [ ] Run de chat não termina silenciosa quando a última mensagem é inbound.
- [ ] `register_sales_interest` envia mensagem ao cliente e cria os artefatos comerciais possíveis.
- [ ] `SIM30-01` passa: última mensagem “Pode me passar o próximo passo para fechar?” recebe outgoing posterior.

## Observabilidade

Adicionar eventos/logs:

- `ai.tool.entity_resolved`
- `ai.tool.entity_resolution_failed`
- `ai.tool.recoverable_error`
- `ai.run.silent_completion_detected`
- `ai.run.silent_prevented`

Campos obrigatórios:

- `tenant_id`
- `run_id`
- `ticket_id`
- `agent_id`
- `tool_name`
- `error_code`
- `recoverable`

## Rollback

- Desabilitar `register_sales_interest` em `ai_agent_tools`.
- Manter resolvers defensivos nas tools antigas, pois são compatíveis.
- Desativar a barreira anti-silêncio por feature flag se houver duplicidade.

Feature flags sugeridas:

- `AI_TOOL_ENTITY_RESOLUTION=true`
- `AI_CHAT_ANTI_SILENCE=true`
- `AI_REGISTER_SALES_INTEREST=true`

## Tasks

Ver `.context/DOCS/TASKS/ai-tool-parameter-resolution-tasks.md`.

## Dependências

- Gateway deve continuar sem acesso direto a PostgreSQL.
- Workers/consumidores de IA e Redis precisam estar ativos para validação E2E.

## Notas

- Evidência inicial: batch `sim30-20260520-015257`, ticket `WC-2605200004`.
- Usuários reais do tenant AGENTFLX incluem `Rosa Lopes Pontes` (`019e397e-95bd-70db-a734-7a320689f815`), candidato a seller padrão.
- Próximo comando: `/prevec-decompose-task .context/DOCS/FEATURES/ai-tool-parameter-resolution.md`
