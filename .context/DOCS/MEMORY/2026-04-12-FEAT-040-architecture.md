---
name: 'FEAT-040-webchat-architecture'
description: 'Decisões arquiteturais do WebChat Widget (FEAT-040)'
type: architecture
date: '2026-04-12'
---

# FEAT-040 — Decisões Arquiteturais

## JWT Validation no Gateway

**Decisão:** Validação HMAC-SHA256 local no Gateway (não HTTP callback)

**Alternativa descartada:** Gateway faz chamada HTTP ao Backend para validar token

**Por que Escolhi:** Elimina latência de rede + reduz acoplamento. HMAC local é suficiente pois o secret é compartilhado entre Gateway e Backend.

**Armadilha:** Se o secret vazar, token pode ser forjado — secret deve ser armazenado em env variável.

---

## Redis Pub/Sub — Reutilização do ws.events

**Decisão:** Publicar eventos webchat no canal `ws.events` existente

**Alternativa descartada:** Criar canal dedicado `webchat.events`

**Por que Escolhi:** EventFanoutService já subscribe no `ws.events`. Com rooms separados (`session:{id}`), eventos não vazam para outros clientes. Menos infraestrutura nova.

**Trade-off:** Events publicados com prefixo `webchat.` são filtrados pelo EventFanout via pattern matching.

---

## ProviderType WEB

**Decisão:** Adicionar `case WEB = 'web'` ao ProviderType enum

**Por quê:** ChatTicket.provider identifica o canal. provider='web' permite ao agente ver origem "Web Chat" vs WhatsApp/ZAPI/UAZAPI/META.

**Não confunde com existing providers:** Cada provider é um bounded context diferente (ZAPI, META, etc).

---

## Socket.io Room Strategy

**Decisão:** Cada ChatSession join room `session:{sessionId}`

**Por que rooms em vez de namespaces:** Rooms permitem broadcast direcionado por sessão sem criar múltiplas conexões. EventFanoutService faz publish para rooms específicas.

**Segurança:** JWT token contém sessionId + tenantId. Gateway valida antes de join.

---

## Contact Lookup por WhatsApp E.164

**Decisão:** Busca contact por `whatsapp` normalizado para E.164

**Regra:** Se não encontrar, cria novo contact. Se encontrar, reutiliza.

**Por que E.164:** Garantia de formato único independent de input do visitante (com/sem +55, com/sem 9, etc).

**Armadilha:** Se visitante errar número, cria contact errado — não há validação de OTP.

---

## Pending Tasks (Opcionais)

| Task | Prioridade | Motivo |
|------|------------|--------|
| TASK-6.040.1 E2E Test | Baixa | Playwright configurado mas não executado com webchat |
| TASK-6.040.2 Load Test | Baixa | Simula N sessões — só necessário antes de produção |

---

## Referências

- Feature doc: `.context/DOCS/FEATURES/FEAT-040-webchat-widget.md`
- Tasks: `.context/DOCS/TASKS/FEAT-040-tasks.md`