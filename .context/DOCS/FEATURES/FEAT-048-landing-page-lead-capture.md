# Feature: Landing Page Lead Capture

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-048 |
| **Nome** | Landing Page Lead Capture |
| **Bounded Context** | Domain/Platform |
| **Complexidade** | M |
| **Prioridade** | Must |
| **Status** | ✅ Implementado (backend + frontend integrados) |
| **Criada em** | 2026-04-29 |
| **Última atualização** | 2026-04-29 |

---

## Resumo

Captura e persistência de leads gerados pelo formulário principal e pelo modal exit-intent da landing page pública do InteraZap. Os dados são armazenados na tabela `platform_leads` (sem `tenant_id`) via endpoint público `POST /api/public/leads`.

**Estado atual:** a integração está completa — landing já chama `submitLead()` → `POST /api/public/leads`; backend persiste, deduplica e responde sem expor dados pessoais.

---

## Objetivo

Converter visitantes da landing em prospects rastreáveis sem depender de ferramentas de terceiros, garantindo:

1. **Fonte da verdade interna** para leads do produto InteraZap (não de tenants).
2. **Conformidade LGPD** — resposta não expõe email/phone; IP/UA persistidos internamente para auditoria.
3. **Base para qualificação comercial** — lead pode ser promovido manualmente para `CRMContact` quando qualificado.

---

## Escopo

### Dentro do Escopo ✅

- [x] Formulário principal `#contact-form` (seção de contato da landing) coleta: name, phone, email, company, honeypot `website`.
- [x] Modal exit-intent `#exit-modal` / `#exit-form` coleta os mesmos campos + aceite LGPD.
- [x] `buildLeadPayload()` enriquece com: `source`, `referrer`, `user_agent`, UTMs (source, medium, campaign, term, content).
- [x] `submitLead()` faz `POST /api/public/leads` com `Content-Type: application/json`.
- [x] Backend valida via `PlatformLeadStoreRequest` (honeypot, dedupe 24h por email OU phone + source).
- [x] Backend persiste em `platform_leads` (sem `tenant_id`), responde `201 { id, name, source, created_at }`.
- [x] Evento `PlatformLeadCreated` dispatchado após persistência (hook para notificação futura).
- [x] Deduplicação: ignora re-submissão de mesmo email/phone na mesma `source` em até 24h (retorna `422`).
- [x] `localStorage` flag `iz_lead_submitted` evita re-submit na mesma sessão de navegador.
- [x] `sessionStorage` flag `iz_exit_modal_shown` evita reexibição do modal na sessão.

### Fora do Escopo ❌

- Promoção automática de `PlatformLead` → `CRMContact` (iteração futura).
- Persistência de `utm_term` e `utm_content` no backend (campos ignorados pelo Laravel Request atual; colunas não existem ainda).
- Sincronização automática com CRM externo (HubSpot, RD Station) — previsto via `PlatformLeadCreated`.
- Painel admin para visualizar/exportar leads (fora do escopo desta feature).
- Rate limiting por IP além do throttle do grupo `throttle:public` já configurado.

---

## Bounded Context

### Domain/Platform

- Entidade global do produto InteraZap — não pertence a nenhum tenant.
- **Modelo:** `PlatformLead` (`api/src/Domain/Platform/Models/`)
- **Tabela:** `platform_leads` — sem `tenant_id`, sem `BelongsToTenant`.
- **Controller:** `PlatformLeadController` (`api/src/Domain/Platform/Http/Controllers/`)
- **Action:** `PlatformLeadActions` — orquestra validação de honeypot, dedupe e persistência.
- **DTO:** `PlatformLeadDTO::fromRequest()`.
- **Request:** `PlatformLeadStoreRequest` — validação declarativa.
- **Resource:** `PlatformLeadResource` — resposta sem email/phone.
- **Evento:** `PlatformLeadCreated` — hook extensível para notificação/sync.
- **Rotas:** `api/src/Domain/Platform/Routes/platform-public.php`, incluído via grupo `throttle:public` em `api/routes/api.php`, **fora** de `auth:sanctum`.

### Landing (Frontend)

- `landing/index.html` — HTML, CSS (Tailwind inline) e JS vanilla inline.
- Variável `INTERAZAP_API_URL` configurável via `window.INTERAZAP_API_URL` (fallback `https://api.interazap.com.br`).
- `buildLeadPayload(extra)` — monta payload base (UTMs, referrer, UA) + campos do formulário.
- `submitLead(payload)` — `fetch` para `POST /api/public/leads`.
- `markLeadSubmitted()` / `hasLeadSubmitted()` — gerencia flag `localStorage`.

---

## Fluxo de Dados

```
Visitante preenche #contact-form ou #exit-form
  → buildLeadPayload({ name, phone, email, company, website })
  → submitLead(payload)
    → POST /api/public/leads
      → PlatformLeadStoreRequest (validação + honeypot)
      → PlatformLeadActions.store(dto)
        → Dedupe: platform_leads WHERE (email OR phone) AND source AND created_at > now-24h
        → Se duplicado: 422 Unprocessable
        → Se honeypot preenchido: 200 (silent discard)
        → Persiste PlatformLead
        → Dispatcha PlatformLeadCreated
      → PlatformLeadResource → { id, name, source, created_at }
      → HTTP 201
  → Landing: mostra #form-success / #exit-modal-success
  → localStorage.iz_lead_submitted = '1'
```

---

## Dependências

| Feature/Sistema | Tipo | Status | Blocker |
|-----------------|------|--------|---------|
| `Domain/Platform` (infra DDD) | Pré-requisito | ✅ Implementado | Não |
| Migration `create_platform_leads_table` | Pré-requisito | ✅ Implementada (2026-04-26) | Não |
| `POST /api/public/leads` | Pré-requisito | ✅ Implementado | Não |
| Grupo `throttle:public` em `api/routes/api.php` | Pré-requisito | ✅ Configurado | Não |
| FEAT-041 Landing Chat Launcher | Paralela (mesma landing) | ✅ Implementado | Não |
| Promoção Lead → CRMContact | Dependente futura | ❌ Não implementado | Não |
| Sync `PlatformLeadCreated` → CRM externo | Dependente futura | ❌ Não implementado | Não |

---

## Critérios de Aceite

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | `POST /api/public/leads` com payload válido retorna `201 { id, name, source, created_at }` sem expor email/phone | Teste de integração | ✅ |
| CA-002 | Honeypot `website` preenchido resulta em resposta silenciosa `200` sem persistir | Teste de integração | ✅ |
| CA-003 | Re-submit do mesmo email/phone + source em menos de 24h retorna `422` | Teste de integração | ✅ |
| CA-004 | Formulário principal `#contact-form` exibe `#form-success` após submit bem-sucedido | Manual/E2E | ✅ |
| CA-005 | Modal exit-intent exibe `#exit-modal-success` após submit bem-sucedido | Manual/E2E | ✅ |
| CA-006 | Flag `localStorage.iz_lead_submitted` impede re-submit na mesma sessão | Manual/E2E | ✅ |
| CA-007 | Erro de rede ou `4xx` exibe mensagem amigável via `friendlyErrorMessage()` | Manual | ✅ |
| CA-008 | Rota não exige `auth:sanctum` nem `billing.delinquency` | Inspeção de rotas | ✅ |
| CA-009 | Resposta não inclui `email` nem `phone` (LGPD) | Teste unitário de Resource | ✅ |
| CA-010 | `utm_source`, `utm_medium`, `utm_campaign` são persistidos corretamente | Teste de integração | ✅ |

---

## Tasks (T.A.C.E)

> Estado: todos concluídos. Feature implementada.

| Task ID | Tarefa | Arquivo | Comportamento esperado | Evidência | Status |
|---------|--------|---------|------------------------|-----------|--------|
| TASK-048.1 | Criar migration `platform_leads` | `api/database/migrations/2026_04_26_003227_create_platform_leads_table.php` | Tabela sem `tenant_id`, com campos name/email/phone/company/source/utm_*/ip/user_agent/referrer/website | Migration executada | ✅ |
| TASK-048.2 | Criar modelo `PlatformLead` | `api/src/Domain/Platform/Models/PlatformLead.php` | Eloquent model global (sem `BelongsToTenant`) | Model criado | ✅ |
| TASK-048.3 | Criar `PlatformLeadStoreRequest` | `api/src/Domain/Platform/Http/Requests/PlatformLeadStoreRequest.php` | Valida campos obrigatórios, honeypot, regex phone BR | Request criada | ✅ |
| TASK-048.4 | Criar `PlatformLeadDTO` | `api/src/Domain/Platform/DTOs/PlatformLeadDTO.php` | DTO tipado com `fromRequest()` | DTO criado | ✅ |
| TASK-048.5 | Criar `PlatformLeadActions` | `api/src/Domain/Platform/Actions/PlatformLeadActions.php` | Orquestra honeypot, dedupe 24h, persistência, event dispatch | Action criada | ✅ |
| TASK-048.6 | Criar `PlatformLeadResource` | `api/src/Domain/Platform/Http/Resources/PlatformLeadResource.php` | Resposta sem email/phone | Resource criada | ✅ |
| TASK-048.7 | Criar `PlatformLeadCreated` event | `api/src/Domain/Platform/Events/PlatformLeadCreated.php` | Evento dispatchado após persistência | Event criado | ✅ |
| TASK-048.8 | Registrar rota pública | `api/src/Domain/Platform/Routes/platform-public.php` + `api/routes/api.php` | `POST /api/public/leads` no grupo `throttle:public`, fora de sanctum | Rota registrada | ✅ |
| TASK-048.9 | Integrar frontend | `landing/index.html` | `buildLeadPayload()`, `submitLead()`, handlers `#contact-form` e `#exit-form` | Landing integrada | ✅ |

---

## Notas Técnicas

### Regex de telefone BR

O pattern final adotado (conforme MEMORY `2026-04-26-public-leads-endpoint.md`) é:

```
/^\(?\d{2}\)?[\s-]?9?\d{4}[\s-]?\d{4}$/
```

Cobre DDD com/sem parênteses, 8 ou 9 dígitos, separadores opcionais.

### UTMs não persistidos

`utm_term` e `utm_content` são enviados pelo frontend mas ignorados pelo Laravel (colunas ainda não existem em `platform_leads`). Iteração futura deve adicionar as colunas e atualizar o whitelist no Request.

### Arquitetura de Isolamento

`PlatformLead` não usa `BelongsToTenant`. Qualquer novo modelo em `Domain/Platform` deve declarar explicitamente que é global para evitar confusão com o padrão de scoping do restante do projeto.

### LGPD

IP e User-Agent são persistidos internamente para auditoria de segurança. A resposta pública não retorna dados pessoais identificáveis — apenas `{ id, name, source, created_at }`.

---

## Referências

- Memory: `.context/DOCS/MEMORY/2026-04-26-public-leads-endpoint.md`
- Migration: `api/database/migrations/2026_04_26_003227_create_platform_leads_table.php`
- Controller: `api/src/Domain/Platform/Http/Controllers/PlatformLeadController.php`
- Rotas: `api/src/Domain/Platform/Routes/platform-public.php`
- Landing: `landing/index.html` (search: `submitLead`, `buildLeadPayload`, `#contact-form`, `#exit-form`)
</content>
</invoke>