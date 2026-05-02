# Memory: Parâmetro agentUserId em ChatRoutingQueuePolicy deve ser string (UUID)

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-05-01 |
| **Autor** | BACKEND |
| **Contexto** | FEAT-052 / TASK-052-04 — Criação da ChatRoutingQueuePolicy |
| **Tags** | policy, uuid, tenant-isolation, chat-routing |

---

## Situação
A task T.A.C.E especificava o método `addAgent(User $user, ChatRoutingQueue $queue, int $agentUserId): bool`. No entanto, o projeto InteraZap utiliza UUIDs (`string`) para todas as chaves primárias, incluindo `auth_users.id` e `chat_routing_queue_agents.user_id`.

---

## Decisão
O tipo do parâmetro `$agentUserId` foi mantido como `string` em vez de `int`, preservando a consistência arquitetural do projeto. Usar `int` quebraria a compatibilidade com o schema PostgreSQL e violaria a regra absoluta "UUID primary keys — never auto-increment".

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Usar `int $agentUserId` conforme task | Viol regra de UUIDs do projeto; causaria erro de tipo ao consultar `auth_users.id` (string UUID) |
| Criar cast automático | Desnecessário e confuso; type-safety é preferida |

---

## Consequências
### Positivas
- Compatibilidade total com o schema existente
- Type safety preservada entre policy, model e banco de dados

### Negativas / Trade-offs
- Divergência minúscula da especificação textual da task (o que é aceitável quando a spec conflita com regras arquiteturais absolutas)

---

## Referências
- Modelo `AuthUser`: `api/src/Domain/Auth/Models/AuthUser.php` (UUID primary key)
- Modelo `ChatRoutingQueueAgent`: `api/src/Domain/Chat/Models/ChatRoutingQueueAgent.php` (user_id como string)
- Regra: `AGENTS.md` — "UUID primary keys — never auto-increment"
