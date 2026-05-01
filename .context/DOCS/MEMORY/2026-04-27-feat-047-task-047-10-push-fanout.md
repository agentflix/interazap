# Memory: FEAT-047 TASK-047.10 — Fan-out push e revogação por falha

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-27 |
| **Autor** | BACKEND (Copilot) |
| **Contexto** | FEAT-047 / TASK-047.10 |
| **Tags** | push, apns, fcm, tenant-isolation, laravel-notifications |

---

## Situação
> O que estava acontecendo? Qual o contexto?

A task exigia entrega de push notifications para novas mensagens com APNs e FCM, mantendo isolamento por tenant e ciclo de vida seguro dos tokens de dispositivo (registro, reativação e revogação soft).

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

A decisão foi centralizar o fan-out de push no fluxo já consolidado do evento MessagePersisted (MessagePersistorListener), filtrando usuários ativos do tenant com tokens não revogados por plataforma. A revogação automática foi ligada ao evento NotificationFailed, com listener dedicado para detectar sinais de token inválido (ex.: BadDeviceToken, unregistered) e marcar revoked_at.

Aprendizado técnico relevante: no eager loading com with(), quando a relation é HasMany, a closure deve tipar HasMany (ou Relation/Builder compatível). Tipar estritamente como Builder nessa closure causou TypeError durante os testes e quebrou o fluxo.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|------------|-------------------|
| Disparar push diretamente do controller de chat | Acopla a entrega de push ao entrypoint HTTP e cria risco de divergência com outros fluxos de persistência de mensagem |
| Revogar token apenas manualmente por endpoint | Não cobre tokens inválidos detectados em runtime pelos providers, mantendo retries inúteis |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Fan-out de push fica consistente para qualquer mensagem persistida no domínio
- Isolamento de tenant preservado no envio e na revogação
- Token inválido sai automaticamente da rotação sem hard delete

### Negativas / Trade-offs
- A lógica de detecção de falha inválida depende da forma como APNs/FCM reportam erros
- Aumento de responsabilidade no listener de persistência de mensagem

---

## Referências
- Feature: .context/DOCS/FEATURES/FEAT-047-mobile-app-capacitor.md
- Task: .context/DOCS/TASKS/FEAT-047-tasks.md
- Arquivos: api/src/Domain/Chat/Listeners/MessagePersistorListener.php, api/src/Domain/Chat/Listeners/RevokeInvalidPushTokenListener.php, api/src/Domain/Chat/Notifications/NewMessageNotification.php
