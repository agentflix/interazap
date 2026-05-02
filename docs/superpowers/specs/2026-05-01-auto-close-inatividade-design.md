# Design Doc: Auto-fechamento de Atendimentos por Inatividade

**Data:** 2026-05-01  
**Revisão:** 2026-05-01 (v2 — correções arquiteturais pós-análise)  
**Feature:** Auto-fechamento de tickets por inatividade  
**Status:** Aprovado para implementação (v2)  
**Responsável:** DEV Team

---

## 1. Visão Geral

**Objetivo:** Criar sistema de fechamento automático de tickets por inatividade, substituindo/refatorando o modelo atual de SLA por tempo em status.

**Escopo:**
- Configuração global (nível tenant) com possibilidade de override por canal
- Opções de tempo: 5, 10, 15, 30, 45, 60, 120 minutos
- Tipos de inatividade: Ambos, Cliente, Atendente
- Mensagem final customizável ao cliente
- Feature desabilitada por default
- Processamento batch via cron (evita N queries)

**Fora do escopo:**
- Notificações push/mobile
- Relatórios de auto-fechamento
- Reabertura automática

---

## 2. Contexto e Decisões Arquiteturais

### 2.1 Problema do MVP Anterior
O MVP anterior usava um Command Laravel que lia empresas ativas em loop sequencial, executando N queries no banco. Isso causava:
- Parada da fila em caso de erro
- Locks na tabela devido a muitos acessos
- Performance degradada com volume alto

### 2.2 Solução Escolhida: Batch SQL + Eventos Assíncronos

```
Cron (5 min) → SELECT batch → UPDATE batch → Evento por ticket → Queue → Envio msg
```

**Vantagens:**
- Apenas 2 queries por execução (independente do volume)
- Sem lock prolongado (update batch rápido com índice)
- Escalável: 100 ou 100.000 tickets, mesma performance
- Mensagens enviadas via queue (não bloqueia o fechamento)

### 2.3 Decisões Técnicas Importantes

| Decisão | Escolha | Justificativa |
|---------|---------|---------------|
| Campo de timestamp | `last_message_at` (genérico) + `last_customer_message_at` + `last_agent_message_at` (novos) | Denormalização necessária para filtrar por target sem JOIN custoso |
| Evento de domínio | Reutilizar `TicketClosedEvent` com parâmetro `$closedMode` adicionado | Já invalida cache, dispara broadcast, notifica webchat; `closedMode` permite filtrar no listener |
| closed_mode | `'auto_inactivity'` | Novo valor no enum existente para rastreabilidade |
| Config global | `settings_chat` JSONB em `platform_tenants` | Campos textuais/exibição; segue padrão `settings_localization`, `settings_privacy` |
| Config por canal | **Colunas dedicadas** em `chat_instances` | Regra de negócio queryável pelo cron; padrão do projeto (`evaluation_enabled`, `evaluation_cutoff_score`) |
| Herança global | Coluna `null` = herda do tenant | Sem flag extra; `null` em qualquer coluna de canal significa "usa config do tenant" |
| Mensagem duplicada | Auto-close SUBSTITUI end_service_message | Evita spam ao cliente |
| Tickets em fila | NÃO fechados | Apenas `open` e `in_progress` são elegíveis |
| Horário de atendimento | Roda 24/7 | Simplifica lógica; horário de atendimento é feature separada |
| Target "client" | Última mensagem foi do cliente | Query verifica `last_customer_message_at` |
| Target "agent" | Última mensagem foi do atendente | Query verifica `last_agent_message_at` |
| Target "both" | Ninguém enviou mensagem no período | Compara `last_message_at` com intervalo |
| Campos legacy | Deprecar (não remover) `auto_close_queue_after_minutes` e `auto_close_in_progress_after_minutes` | Existem em `chat_tickets` (tabela core); remover exige validação de BI/scripts externos |
| MessagePersisted | Refatorar para disparar em ambas direções (incoming + outgoing) | Hoje só dispara para incoming no `ChatWebhookIngestor`; outgoing precisa ser coberto para target=agent funcionar |

---

## 3. Modelo de Dados

### 3.1 Platform Tenant (Global)

```php
// Coluna existente: settings_chat JSONB
{
  "auto_close_inactivity_enabled": false,
  "auto_close_inactivity_minutes": 30,
  "auto_close_inactivity_target": "both",
  "auto_close_inactivity_message": "Este atendimento foi encerrado automaticamente por inatividade. Caso precise de mais ajuda, por favor inicie um novo atendimento."
}
```

### 3.2 Chat Instance (Canal)

```php
// Colunas dedicadas em chat_instances (não settings_json)
// Padrão: null = herda do tenant | valor próprio = override

auto_close_enabled: boolean|null        // null → herda tenant
auto_close_after_minutes: integer|null  // null → herda tenant
auto_close_target: varchar|null         // null → herda tenant; valores: 'both'|'client'|'agent'
auto_close_message: text|null           // null → herda tenant
```

Semântica de herança:
- `null` em qualquer campo → lê do `settings_chat` do tenant
- valor explícito → override do canal, ignora tenant para aquele campo
- Não há flag `use_global`; null já expressa "herda"

### 3.3 Chat Ticket

```php
// Campos existentes utilizados:
- tenant_id (bigint, indexed)
- instance_id (bigint, indexed)
- status (varchar: 'open', 'in_progress', 'closed', indexed)
- last_message_at (timestamp, indexed)           // qualquer direção
- closed_mode (varchar: 'normal', 'forced', 'auto_inactivity')
- closed_at (timestamp, nullable)
- updated_at (timestamp)

// Campos novos (adicionados pela TASK-0.1):
- last_customer_message_at (timestamp, nullable) // atualizado quando direction = 'incoming'
- last_agent_message_at (timestamp, nullable)    // atualizado quando direction = 'outgoing'

// Campos DEPRECADOS (manter, não remover):
- auto_close_queue_after_minutes (int, default 0)       // sem uso; não remover ainda
- auto_close_in_progress_after_minutes (int, default 0) // sem uso; não remover ainda
```

---

## 4. Fluxo Técnico Detalhado

### 4.1 Cron Job (Command)

```php
// Command: chat:close-inactive-tickets
// Schedule: everyFiveMinutes()

1. Busca todos tenants ativos
2. Para cada tenant:
   a. Lê settings_chat
   b. Se auto_close_inactivity_enabled = false → pula
   c. Chama CloseInactiveTicketsAction::execute($tenant)
3. Loga resultado por tenant
4. Erro em um tenant não afeta os demais
```

### 4.2 Action (Batch)

```php
// CloseInactiveTicketsAction::execute(Tenant $tenant)

1. Lê config do tenant (settings_chat):
   - Se auto_close_inactivity_enabled = false → retorna []

2. Query SELECT (adaptada por target):
   SELECT id, instance_id
   FROM chat_tickets
   WHERE tenant_id = ?
     AND status IN ('open', 'in_progress')
     AND closed_mode IS NULL
     AND (
       -- target 'both': último contato (qualquer) expirou
       (COALESCE(canal.auto_close_target, tenant.target) = 'both'
         AND last_message_at < NOW() - INTERVAL '? minutes')
       OR
       -- target 'client': último contato do cliente expirou
       (COALESCE(canal.auto_close_target, tenant.target) = 'client'
         AND last_customer_message_at < NOW() - INTERVAL '? minutes')
       OR
       -- target 'agent': último contato do atendente expirou
       (COALESCE(canal.auto_close_target, tenant.target) = 'agent'
         AND last_agent_message_at < NOW() - INTERVAL '? minutes')
     )
   ORDER BY last_message_at ASC

   Nota: config por canal é resolvida via COALESCE(coluna_canal, config_tenant).
   Canais com auto_close_enabled = false são excluídos antes da query.

3. UPDATE batch:
   UPDATE chat_tickets
   SET status = 'closed',
       closed_at = NOW(),
       closed_mode = 'auto_inactivity',
       updated_at = NOW()
   WHERE id IN (...)
     AND status IN ('open', 'in_progress')
     AND closed_mode IS NULL

4. Para cada ticket fechado:
   a. Dispara TicketClosedEvent($tenantId, $ticketId, $assignedUserId, 'auto_inactivity')
   b. Agenda envio de mensagem final na queue

5. Retorna: { closedIds: int[], skippedIds: int[] }
```

### 4.3 Envio de Mensagem (Listener/Job)

```php
// Listener do TicketClosedEvent
// Assinatura correta do evento: TicketClosedEvent(tenantId, ticketId, assignedUserId, closedMode)

1. Se $event->closedMode !== 'auto_inactivity' → ignora
2. Busca ticket e canal
3. Busca config efetiva (canal → tenant → default)
4. Se mensagem vazia → não envia nada
5. Se canal tem end_service_message configurada:
   - NÃO envia end_service_message (evita duplicação)
6. Envia mensagem de auto-close via ChatTicketAutomationService
7. Marca mensagem como pending se canal offline
```

---

## 5. Telas e UX

### 5.1 Configuração Global

**Caminho:** Menu Chat → Configurações → Auto-fechamento  
**Ou:** Tenant Settings → Aba Chat

```
┌─────────────────────────────────────────────────────────────┐
│  Chat > Configurações de Auto-fechamento              [Salvar]│
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Fechamento automático por inatividade                   ││
│  │                                                          ││
│  │  [TOGGLE OFF]  Desativado                                ││
│  │                                                          ││
│  │  ─────────────────────────────────────────────────────   ││
│  │                                                          ││
│  │  Quando fechar automaticamente:                          ││
│  │  [DROPDOWN] Selecione... ▼                               ││
│  │     • Após 5 minutos                                     ││
│  │     • Após 10 minutos                                    ││
│  │     • Após 15 minutos                                    ││
│  │     • Após 30 minutos                                    ││
│  │     • Após 45 minutos                                    ││
│  │     • Após 60 minutos                                    ││
│  │     • Após 120 minutos                                   ││
│  │                                                          ││
│  │  Considerar inatividade de:                              ││
│  │  ( ) Ambos (atendente e cliente)                         ││
│  │  ( ) Apenas cliente                                      ││
│  │  ( ) Apenas atendente                                    ││
│  │                                                          ││
│  │  Mensagem ao fechar:                                     ││
│  │  [TEXTAREA]                                              ││
│  │  "Este atendimento foi encerrado automaticamente por     ││
│  │   inatividade. Caso precise de mais ajuda, por favor     ││
│  │   inicie um novo atendimento."                           ││
│  │                                                          ││
│  │  [Restam 500 caracteres]                                 ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

**Comportamento do Toggle:**
- **OFF (default):** Todos os campos abaixo ficam desabilitados/ocultos (slide suave)
- **ON:** Campos aparecem com animação de entrada, habilitados para edição

### 5.2 Configuração no Canal

**Caminho:** Canais → Editar Canal → Aba "Auto-fechamento"

```
┌─────────────────────────────────────────────────────────────┐
│  Canais > Editar Canal > Auto-fechamento              [Salvar]│
├─────────────────────────────────────────────────────────────┤
│  [Geral] [Mensagens] [Avaliação] [Auto-fechamento] [Avançado]│
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────────┐│
│  │  Usar configuração global                                ││
│  │  [TOGGLE ON]  Ativado                                    ││
│  │  ✓ Herdando: 30 minutos | Ambos | Mensagem padrão       ││
│  │                                                          ││
│  │  ─────────────────────────────────────────────────────   ││
│  │  (Quando desligar o toggle, os campos abaixo aparecem)   ││
│  │                                                          ││
│  │  [TOGGLE] [DROPDOWN] [RADIO GROUP] [TEXTAREA]           ││
│  │  (mesmos campos da tela global)                         ││
│  └─────────────────────────────────────────────────────────┘│
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

**Comportamento baseado no valor das colunas (não em flag `use_global`):**
- **`auto_close_enabled = null`:** Exibe badge "Herdando configuração global" com os valores do tenant em modo read-only; toggle aparece como "Usar configuração global" = ON
- **`auto_close_enabled = true/false`:** Toggle "Usar configuração global" = OFF; campos editáveis com os valores do canal

A UI deve enviar `null` para todas as colunas de canal quando o usuário ativa "Usar configuração global", e o valor escolhido quando desativa.

---

## 6. Decomposição T.A.C.E

> **Ordem de execução obrigatória:** TASK-0.1 e TASK-0.2 são pré-requisitos de todas as demais tasks de backend.

---

### TASK-0.1: Adicionar tracking de atividade por direção (Backend — pré-requisito)

**T — Tarefa:** Adicionar colunas `last_customer_message_at` e `last_agent_message_at` em `chat_tickets`, criar listener para atualizá-las, e refatorar `MessagePersisted` para disparar em mensagens outgoing também

**A — Arquivo:**
- `api/database/migrations/2026_05_01_000000_add_activity_timestamps_to_chat_tickets.php`
- `api/src/Domain/Chat/Listeners/UpdateTicketActivityTimestampsListener.php`
- `api/src/Domain/Chat/Actions/SendChatMessageAction.php` (ou similar — disparar `MessagePersisted` para outgoing)
- `api/app/Providers/EventServiceProvider.php` (registrar listener)

**C — Comportamento:**
```
ANTES:
- chat_tickets: apenas last_message_at (qualquer direção)
- MessagePersisted: disparado apenas em ChatWebhookIngestor para direction = 'incoming'

DEPOIS:
- chat_tickets: + last_customer_message_at (nullable) + last_agent_message_at (nullable)
- MessagePersisted: disparado para incoming E outgoing
- UpdateTicketActivityTimestampsListener:
    - incoming → atualiza last_customer_message_at
    - outgoing → atualiza last_agent_message_at
    - ambos → atualiza last_message_at (comportamento existente mantido)
```

**E — Evidência:**
- [ ] Migration executa e faz rollback sem erros
- [ ] Mensagem incoming atualiza last_customer_message_at
- [ ] Mensagem outgoing atualiza last_agent_message_at
- [ ] last_message_at continua sendo atualizado para ambos

---

### TASK-0.2: Adicionar closedMode ao TicketClosedEvent (Backend — pré-requisito)

**T — Tarefa:** Adicionar parâmetro `$closedMode` ao construtor de `TicketClosedEvent` e atualizar todos os pontos de dispatch

**A — Arquivo:**
- `api/src/Domain/Configuration/Events/TicketClosedEvent.php`
- Todos os arquivos que disparam `TicketClosedEvent::dispatch(...)` (buscar por grep)

**C — Comportamento:**
```
ANTES:
public function __construct(
    public readonly string $tenantId,
    public readonly string $ticketId,
    public readonly ?string $assignedUserId,
) {}

DEPOIS:
public function __construct(
    public readonly string $tenantId,
    public readonly string $ticketId,
    public readonly ?string $assignedUserId,
    public readonly ?string $closedMode = null,  // 'normal', 'forced', 'auto_inactivity'
) {}

- Todos os dispatch existentes passam null (default) → comportamento inalterado
- CloseInactiveTicketsAction passará 'auto_inactivity'
```

**E — Evidência:**
- [ ] Evento compila sem erros
- [ ] Todos os dispatch existentes continuam funcionando (null default)
- [ ] Listener de auto-close filtra por closedMode === 'auto_inactivity'

---

### TASK-1.0: Refatorar modelo de dados (Backend)

**T — Tarefa:** Criar migrations para adicionar `settings_chat` JSONB em `platform_tenants` e 4 colunas dedicadas em `chat_instances`. NÃO remover campos legacy de `chat_tickets` (deprecar apenas)

**A — Arquivo:**
- `api/database/migrations/2026_05_01_000001_add_settings_chat_to_platform_tenants.php`
- `api/database/migrations/2026_05_01_000002_add_auto_close_columns_to_chat_instances.php`

**C — Comportamento:**
```
ANTES:
- platform_tenants NÃO tem settings_chat
- chat_instances NÃO tem colunas de auto-close
- chat_tickets tem auto_close_queue_after_minutes e auto_close_in_progress_after_minutes
  (campos DEPRECADOS — existem na tabela core, não remover)

DEPOIS:
- platform_tenants: nova coluna settings_chat (JSONB, nullable, default null)
  Formato:
  {
    "auto_close_inactivity_enabled": false,
    "auto_close_inactivity_minutes": 30,
    "auto_close_inactivity_target": "both",
    "auto_close_inactivity_message": "Este atendimento foi encerrado automaticamente..."
  }

- chat_instances: 4 novas colunas dedicadas
  auto_close_enabled (boolean, nullable, default null)
  auto_close_after_minutes (unsignedSmallInteger, nullable, default null)
  auto_close_target (varchar(10), nullable, default null)  -- 'both'|'client'|'agent'
  auto_close_message (text, nullable, default null)

- chat_tickets: auto_close_queue_after_minutes e auto_close_in_progress_after_minutes
  MANTIDOS com comentário "deprecated - não usar em código novo"

RESTRIÇÕES:
- Migration deve ser reversível (down method)
- NÃO tocar em settings_json de chat_instances
- Índice composto em chat_tickets: (tenant_id, status, last_message_at DESC) — verificar se já existe antes de criar
```

**E — Evidência:**
- [ ] Migration executa sem erros em ambiente de staging
- [ ] Rollback da migration funciona
- [ ] Teste de integração: settings_chat é salvo e recuperado corretamente

---

### TASK-1.1: Atualizar modelos e casts (Backend)

**T — Tarefa:** Atualizar `PlatformTenant` e `ChatInstance` para suportar novos campos de configuração

**A — Arquivo:**
- `api/src/Domain/Platform/Models/PlatformTenant.php`
- `api/src/Domain/Chat/Models/ChatInstance.php`

**C — Comportamento:**
```
ANTES:
- PlatformTenant: casts para settings_localization, settings_privacy
- ChatInstance: fillable com campos existentes; sem colunas de auto-close

DEPOIS:
- PlatformTenant: novo cast 'settings_chat' => 'array'
- ChatInstance: adicionar ao fillable:
    'auto_close_enabled', 'auto_close_after_minutes', 'auto_close_target', 'auto_close_message'
  Adicionar cast: 'auto_close_enabled' => 'boolean' (nullable)
  Adicionar cast: 'auto_close_after_minutes' => 'integer' (nullable)
- ChatInstance: método helper getEffectiveAutoCloseConfig(Tenant $tenant): array
    → retorna COALESCE(coluna_canal, valor_tenant) para cada campo
```

**E — Evidência:**
- [ ] Teste unitário: PlatformTenant retorna settings_chat como array
- [ ] Teste unitário: ChatInstance.getEffectiveAutoCloseConfig retorna valor do canal quando não null
- [ ] Teste unitário: ChatInstance.getEffectiveAutoCloseConfig retorna valor do tenant quando coluna é null

---

### TASK-1.2: Criar action de fechamento batch (Backend)

**T — Tarefa:** Criar `CloseInactiveTicketsAction` que executa o batch SQL de identificação e fechamento de tickets expirados

**A — Arquivo:**
- `api/src/Domain/Chat/Actions/CloseInactiveTicketsAction.php`

**C — Comportamento:**
```
ANTES:
- Não existe lógica de auto-close por inatividade

DEPOIS:
- Método execute(Tenant $tenant): array { closedIds: int[], failedIds: int[] }
- Lógica:
  1. Busca config do tenant (settings_chat)
  2. Se desabilitado globalmente, retorna []
  3. Para cada canal ativo do tenant:
     a. Chama ChatInstance::getEffectiveAutoCloseConfig($tenant)
     b. Se auto_close_enabled = false → pula canal
     c. Resolve minutos e target efetivos

  4. Query SELECT (agrupa canais por configuração para minimizar queries):
     SELECT id, instance_id
     FROM chat_tickets
     WHERE tenant_id = ?
       AND instance_id IN (?) -- canais habilitados
       AND status IN ('open', 'in_progress')
       AND closed_mode IS NULL
       AND (
         -- para canais com target = 'both':
         (instance_id IN (...both_ids) AND last_message_at < NOW() - INTERVAL '? minutes')
         OR
         -- para canais com target = 'client':
         (instance_id IN (...client_ids) AND last_customer_message_at < NOW() - INTERVAL '? minutes')
         OR
         -- para canais com target = 'agent':
         (instance_id IN (...agent_ids) AND last_agent_message_at < NOW() - INTERVAL '? minutes')
       )

  5. UPDATE batch (em transação):
     UPDATE chat_tickets
     SET status = 'closed',
         closed_at = NOW(),
         closed_mode = 'auto_inactivity',
         updated_at = NOW()
     WHERE id IN (...)
       AND status IN ('open', 'in_progress')
       AND closed_mode IS NULL

  6. Retorna IDs fechados
  7. Dispara TicketClosedEvent($tenantId, $ticketId, $assignedUserId, 'auto_inactivity') para cada ID

RESTRIÇÕES:
- Transação isolada para o UPDATE batch
- Nunca fechar tickets de outro tenant
- Usar getEffectiveAutoCloseConfig() do modelo (não ler settings_json)
- Target "client": usa last_customer_message_at (adicionado em TASK-0.1)
- Target "agent": usa last_agent_message_at (adicionado em TASK-0.1)
- Target "both": usa last_message_at existente
```

**E — Evidência:**
- [ ] Teste: fecha tickets expirados
- [ ] Teste: não fecha tickets dentro do prazo
- [ ] Teste: não fecha tickets de outro tenant
- [ ] Teste: respeita config por canal
- [ ] Teste: respeita target (client/agent/both)
- [ ] Teste: idempotente (rodar 2x não fecha novamente)

---

### TASK-1.3: Criar command Laravel (Backend)

**T — Tarefa:** Criar `CloseInactiveTicketsCommand` que roda via cron a cada 5 minutos

**A — Arquivo:**
- `api/app/Console/Commands/CloseInactiveTicketsCommand.php`
- `api/app/Console/Kernel.php` (agendamento)

**C — Comportamento:**
```
ANTES:
- Não existe command de auto-close por inatividade

DEPOIS:
- Command: php artisan chat:close-inactive-tickets
- Lógica:
  1. Busca todos tenants ativos
  2. Para cada tenant, chama CloseInactiveTicketsAction
  3. Loga resultado (quantos fechados por tenant)
  4. Em caso de erro em um tenant, continua com os demais
- Agendamento: $schedule->command('chat:close-inactive-tickets')->everyFiveMinutes();
- Opção: --tenant= para rodar para tenant específico

RESTRIÇÕES:
- Erro em um tenant não para execução dos demais
- Timeout adequado (não bloquear cron)
- Log de falhas para monitoramento
```

**E — Evidência:**
- [ ] Teste: command roda sem erros
- [ ] Teste: command processa múltiplos tenants
- [ ] Teste: falha em um tenant não afeta outros
- [ ] Teste: opção --tenant funciona

---

### TASK-1.4: Atualizar serviço de automação (Backend)

**T — Tarefa:** Atualizar `ChatTicketAutomationService` para enviar mensagem de auto-close quando closed_mode = 'auto_inactivity'

**A — Arquivo:**
- `api/src/Domain/Chat/Services/ChatTicketAutomationService.php`

**C — Comportamento:**
```
ANTES:
- Envia end_service_message quando status muda para closed
- Não diferencia modo de fechamento

DEPOIS:
- Quando closed_mode = 'auto_inactivity':
  - Busca mensagem configurada (canal → global → default)
  - Envia mensagem ao cliente via SendTicketMessageAction
  - Se canal tem end_service_message configurada, envia SÓ a mensagem de auto-close (não duplica)
  - Se mensagem estiver vazia, não envia nada (só fecha)
```

**E — Evidência:**
- [ ] Teste: envia mensagem de auto-close
- [ ] Teste: não duplica com end_service_message
- [ ] Teste: não envia se mensagem vazia
- [ ] Teste: usa config por canal quando override ativo

---

### TASK-1.5: Criar/atualizar endpoints de API (Backend)

**T — Tarefa:** Criar endpoints para salvar/recuperar configurações de auto-close global e por canal

**A — Arquivo:**
- `api/src/Domain/Platform/Http/Controllers/TenantSettingsController.php` (atualizar)
- `api/src/Domain/Platform/Http/Requests/UpdateTenantSettingsRequest.php` (atualizar)
- `api/src/Domain/Chat/Http/Controllers/ChatInstanceController.php` (atualizar)
- `api/src/Domain/Chat/Http/Requests/ChatInstanceRequest.php` (atualizar)

**C — Comportamento:**
```
ANTES:
- TenantSettingsController: salva localization, privacy
- ChatInstanceController: salva dados básicos, mensagens automáticas

DEPOIS:
- TenantSettingsController: aceita settings_chat no payload
- Validação:
  - auto_close_inactivity_enabled: boolean
  - auto_close_inactivity_minutes: integer, in [5,10,15,30,45,60,120]
  - auto_close_inactivity_target: string, in ['both', 'client', 'agent']
  - auto_close_inactivity_message: string, max:2000, nullable
- ChatInstanceController: aceita as 4 colunas dedicadas de auto-close (não settings_json)
  Payload: { auto_close_enabled: bool|null, auto_close_after_minutes: int|null, auto_close_target: string|null, auto_close_message: string|null }
  Enviar null = "usar configuração global"
```

**E — Evidência:**
- [ ] Teste: endpoint salva config global
- [ ] Teste: endpoint salva config por canal
- [ ] Teste: validação rejeita valores inválidos
- [ ] Teste: GET retorna configurações corretas

---

### TASK-1.6: Criar/atualizar resources (Backend)

**T — Tarefa:** Atualizar resources para incluir configurações de auto-close na resposta

**A — Arquivo:**
- `api/src/Domain/Platform/Http/Resources/PlatformTenantResource.php`
- `api/src/Domain/Chat/Http/Resources/ChatInstanceResource.php`

**C — Comportamento:**
```
ANTES:
- PlatformTenantResource: retorna settings_localization, settings_privacy
- ChatInstanceResource: retorna settings_json completo

DEPOIS:
- PlatformTenantResource: inclui settings_chat com valores default quando null
- ChatInstanceResource: inclui campos de auto-close com valores default
```

**E — Evidência:**
- [ ] Teste: resource retorna settings_chat com valores default
- [ ] Teste: resource retorna settings_chat com valores customizados

---

### TASK-2.0: Criar seção de config global no frontend (Frontend)

**T — Tarefa:** Adicionar seção de "Auto-fechamento por inatividade" na tela de tenant settings

**A — Arquivo:**
- `app/src/app/pages/platform/tenant-settings/tenant-settings.ts`
- `app/src/app/pages/platform/tenant-settings/tenant-settings.html`

**C — Comportamento:**
```
ANTES:
- Tenant settings tem: Localização, Privacidade
- Não tem configurações de chat

DEPOIS:
- Nova seção: "Auto-fechamento de atendimentos"
- Componentes UI Kit:
  - AfSwitchInputComponent: "Ativar fechamento automático por inatividade" (default OFF)
  - AfSelectInputComponent: "Tempo de inatividade" (options: 5,10,15,30,45,60,120 min)
  - AfRadioInputComponent: "Considerar inatividade de" (Ambos, Cliente, Atendente)
  - AfTextareaInputComponent: "Mensagem ao encerrar" (com contador de caracteres)
- Comportamento condicional (slide):
  - Toggle OFF: todos campos hidden/disabled
  - Toggle ON: campos aparecem com animação
```

**E — Evidência:**
- [ ] Toggle OFF esconde campos
- [ ] Toggle ON mostra campos
- [ ] Validação: tempo obrigatório quando ativo
- [ ] Validação: mensagem max 2000 chars
- [ ] Teste de componente passa

---

### TASK-2.1: Adicionar aba de auto-close no canal (Frontend)

**T — Tarefa:** Adicionar aba "Auto-fechamento" no formulário de edição de canal

**A — Arquivo:**
- `app/src/app/pages/chat/channel/components/channel-form/channel-form.ts`
- `app/src/app/pages/chat/channel/components/channel-form/channel-form.html`

**C — Comportamento:**
```
ANTES:
- Channel form tem: Dados básicos, Mensagens automáticas, Avaliação
- Não tem aba de auto-close

DEPOIS:
- Nova aba: "Auto-fechamento"
- Toggle UI: "Usar configuração global"
  - Mapeado para: auto_close_enabled === null (todos campos null = herda)
  - Quando ON: mostra badge "Herdando configuração global" com valores do tenant em read-only; ao salvar, envia null para todos os campos
  - Quando OFF: mostra campos editáveis; ao salvar, envia os valores escolhidos
- Campos (visíveis quando toggle OFF):
  - AfSwitchInputComponent: "Ativar fechamento automático" → auto_close_enabled
  - AfSelectInputComponent: "Tempo de inatividade" → auto_close_after_minutes
  - AfRadioInputComponent: "Considerar inatividade de" → auto_close_target
  - AfTextareaInputComponent: "Mensagem ao encerrar" → auto_close_message
```

**E — Evidência:**
- [ ] Toggle "Usar global" ON: campos read-only com valores do tenant
- [ ] Toggle "Usar global" OFF: campos editáveis
- [ ] Salvar com toggle ON envia null para todos os campos de canal
- [ ] Teste de componente passa

---

### TASK-2.2: Atualizar modelos e serviços frontend (Frontend)

**T — Tarefa:** Atualizar interfaces e serviços para suportar novos campos

**A — Arquivo:**
- `app/src/app/shared/models/tenant-settings.model.ts`
- `app/src/app/shared/models/integration.model.ts`
- `app/src/app/core/services/tenant-settings.service.ts`

**C — Comportamento:**
```
ANTES:
- TenantSettings: localization, privacy
- Integration: dados básicos do canal
- Não tem campos de auto-close

DEPOIS:
- TenantSettings: adiciona chatSettings em settings_chat:
    autoCloseInactivityEnabled: boolean
    autoCloseInactivityMinutes: number
    autoCloseInactivityTarget: 'both' | 'client' | 'agent'
    autoCloseInactivityMessage: string

- Integration (ChatInstance): adiciona campos de auto-close como colunas (não em settings):
    autoCloseEnabled: boolean | null      // null = usa config global
    autoCloseAfterMinutes: number | null  // null = usa config global
    autoCloseTarget: 'both' | 'client' | 'agent' | null  // null = usa config global
    autoCloseMessage: string | null       // null = usa config global
```

**E — Evidência:**
- [ ] Interfaces tipadas corretamente
- [ ] Service salva e recupera configurações
- [ ] Testes unitários passam

---

### TASK-2.3: Atualizar menu lateral (Frontend)

**T — Tarefa:** Adicionar item de menu para configurações de auto-close (se nova tela) ou verificar se fica em tenant-settings

**A — Arquivo:**
- `app/src/app/layout/components/sidenav/menu-config.ts`

**C — Comportamento:**
```
ANTES:
- Menu Chat: Canais, Respostas rápidas, Templates, Listas de transmissão
- Menu Configurações: Tenant settings

DEPOIS:
- Menu Configurações > Tenant Settings: inclui seção de auto-close (não precisa novo item de menu)
- Ou: Menu Chat: adicionar "Auto-fechamento" (decisão de UX)
```

**E — Evidência:**
- [ ] Menu mostra novo item (se aplicável)
- [ ] Navegação funciona
- [ ] Permissão correta aplicada

---

### TASK-3.0: Criar testes backend (Backend)

**T — Tarefa:** Criar testes de feature e unitários para o backend

**A — Arquivo:**
- `api/tests/Feature/Chat/CloseInactiveTicketsTest.php`
- `api/tests/Unit/Chat/Actions/CloseInactiveTicketsActionTest.php`
- `api/tests/Feature/Platform/TenantSettingsChatTest.php`

**C — Comportamento:**
```
Testes a criar:
1. Feature: Command fecha tickets expirados
2. Feature: Command não fecha tickets dentro do prazo
3. Feature: Respeita tenant isolation
4. Feature: Respeita config por canal
5. Feature: Respeita target (client/agent/both)
6. Feature: Envia mensagem de auto-close
7. Feature: Não duplica mensagem
8. Unit: Action retorna IDs corretos
9. Unit: Action filtra por target
10. Feature: API salva config global
11. Feature: API valida campos inválidos
```

**E — Evidência:**
- [ ] Todos testes passam
- [ ] Cobertura > 80%

---

### TASK-3.1: Criar testes frontend (Frontend)

**T — Tarefa:** Criar testes de componente para as telas de configuração

**A — Arquivo:**
- `app/src/app/pages/platform/tenant-settings/tenant-settings.spec.ts`
- `app/src/app/pages/chat/channel/components/channel-form/channel-form.spec.ts`

**C — Comportamento:**
```
Testes a criar:
1. Toggle global mostra/esconde campos
2. Validação de formulário
3. Envio de dados para API
4. Canal herda config global
5. Canal permite override
6. Mensagem com contador de caracteres
```

**E — Evidência:**
- [ ] Todos testes passam
- [ ] Build sem erros

---

### TASK-4.0: Documentação e CHANGELOG

**T — Tarefa:** Criar CHANGELOG e MEMORY da feature

**A — Arquivo:**
- `.context/DOCS/CHANGELOG/2026-05-01.md`
- `.context/DOCS/MEMORY/2026-05-01-auto-close-inatividade.md`

**C — Comportamento:**
```
ANTES:
- Não existe registro da feature

DEPOIS:
- CHANGELOG: registra o que mudou, arquivos afetados
- MEMORY: decisões técnicas (batch vs job, reutilização de evento, etc.)
```

**E — Evidência:**
- [ ] Arquivos criados seguindo templates

---

## 7. Riscos e Mitigações

| Risco | Impacto | Mitigação |
|-------|---------|-----------|
| Migration destrói dados antigos | Alto | Backup antes de rodar migration; migration com down() reversível |
| Batch SQL causa lock | Médio | Índice adequado; UPDATE com WHERE específico; transação curta |
| Mensagem duplicada ao cliente | Médio | Lógica clara: auto-close substitui end_service_message |
| Performance em muitos tickets | Médio | Batch por tenant; cron a cada 5 min (não 1 min) |
| Confusão UX com auto-close SLA antigo | Baixo | Documentação; mensagens claras na UI |

---

## 8. Critérios de Aceitação Gerais

- [ ] Feature desabilitada por default (toggle global OFF)
- [ ] Configuração global salva e recuperada corretamente
- [ ] Canal herda config global por default
- [ ] Canal permite override com toggle "Usar global"
- [ ] Cron fecha tickets expirados em batch (não loop)
- [ ] Mensagem enviada ao cliente quando ticket fecha
- [ ] Não duplica mensagem de encerramento
- [ ] Respeita tenant isolation
- [ ] Respeita config por canal
- [ ] Respeita target de inatividade (both/client/agent)
- [ ] Testes backend passam (Pest)
- [ ] Testes frontend passam (Vitest)
- [ ] Build production passa sem erros
- [ ] CHANGELOG e MEMORY criados

---

## 9. Estimativa de Esforço

| Área | Tasks | Estimativa |
|------|-------|------------|
| Backend (pré-requisitos) | 0.1, 0.2 | 4h |
| Backend (migrations + models) | 1.0, 1.1 | 4h |
| Backend (action + command) | 1.2, 1.3 | 6h |
| Backend (service + API) | 1.4, 1.5, 1.6 | 6h |
| Frontend (telas + componentes) | 2.0, 2.1, 2.2 | 8h |
| Frontend (menu + rotas) | 2.3 | 2h |
| Testes backend | 3.0 | 6h |
| Testes frontend | 3.1 | 4h |
| Documentação | 4.0 | 2h |
| **Total** | | **~42h** |

---

## 10. Próximos Passos

1. ~~Revisão do spec~~ — Concluído (v2 aprovada)
2. **TASK-0.1** — Adicionar tracking `last_customer_message_at` + `last_agent_message_at` + refatorar `MessagePersisted`
3. **TASK-0.2** — Adicionar `closedMode` ao `TicketClosedEvent`
4. **TASK-1.0 em diante** — Execução seguindo workflow PREVC

---

*Documento criado em 2026-05-01*  
*Status: Aguardando aprovação para implementação*
