# Memory: Endpoint público de leads vive em `Domain/Platform`, não em `Domain/CRM`

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-26 |
| **Autor** | ORCHESTRATOR + BACKEND |
| **Contexto** | Redesign da landing — captura de leads via formulário e modal exit-intent |
| **Tags** | platform, leads, landing, ddd, multi-tenant, public-api, lgpd |

---

## Situação

A landing pública precisava cadastrar leads (prospects do **próprio InteraZap**) vindos do formulário e do novo modal exit-intent. Todos os endpoints existentes (`Domain/CRM`) exigem `auth:sanctum` e usam o trait `BelongsToTenant` para isolamento por cliente. Reaproveitar `CRMContact`/`CRMCompany` significaria:

- Inventar um "tenant marketing" sintético para receber prospects → mistura conceitual entre dado do produto e dado de cliente.
- Expor uma rota pública gravando em uma tabela multi-tenant → risco real de bypass de isolamento se algum dia a rota for autenticada por engano.

---

## Decisão

Criar um **novo bounded context para esse caso já existente**: `Domain/Platform`, onde vivem entidades **globais do produto InteraZap** (não de tenants clientes). A primeira entidade é `PlatformLead`, com:

- Tabela `platform_leads` **sem `tenant_id`**.
- Endpoint `POST /api/public/leads` registrado em arquivo de rotas próprio (`Routes/platform-public.php`), incluído no grupo `throttle:public` em `api/routes/api.php`, **fora** de `auth:sanctum` e **fora** de `billing.delinquency`.
- Resposta NÃO expõe `email`/`phone` — só `{id, name, source, created_at}`.
- Honeypot `website` + dedupe 24h por email OU phone na mesma `source`.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Reusar `CRMContact` com tenant "interazap-marketing" | Mistura dado do produto com dado de cliente; risco de vazar nas queries de tenant; viola semântica do `BelongsToTenant`. |
| Endpoint autenticado com token fixo no front | Token estaria visível no HTML público; segurança falsa; complica deploy da landing. |
| Salvar em tabela única `leads` no `Domain/Shared` | `Shared` é para primitives e abstrações cross-context, não para entidades de negócio com ciclo próprio. |
| Enviar direto para CRM externo (HubSpot/RD) sem persistir | Vendor lock-in; sem fonte da verdade; sem auditoria; sem deduplicação local. |

---

## Consequências

### Positivas
- `Domain/Platform` agora é o lugar canônico para entidades globais do InteraZap (leads, billing globals, feature flags do produto, etc.).
- Endpoint público com superfície mínima e isolada — não tem como esbarrar em dados de tenant.
- Hook `PlatformLeadCreated` permite plugar notificação comercial (Slack, e-mail, sync com CRM externo) sem mexer no core.
- LGPD: resposta não vaza dados pessoais; IP/UA persistidos para auditoria, não expostos.

### Negativas / Trade-offs
- Lead capturado via landing **não vira CRMContact automaticamente**. Quando o time comercial qualificar o prospect, terá que promover manualmente (ou via job futuro). Aceitável: prospect ≠ cliente.
- Front envia `utm_term` e `utm_content` que o back ainda não persiste (ignorados por Laravel). Próxima iteração: adicionar colunas + whitelist no Request.
- Sem `tenant_id` significa que esse módulo **não usa** padrões de scoping do projeto; novos modelos em `Platform` precisam declarar explicitamente que são globais para evitar confusão.

---

## Armadilhas registradas (BACKEND salvou em MEMORY próprio)

1. **Pest com dataProvider** em testes que herdam `class extends TestCase` exige atributo PHP `#[\PHPUnit\Framework\Attributes\DataProvider('...')]` — `@dataProvider` em phpDoc não funciona.
2. **PHPStan + Laravel** estoura 128M de memória no bootstrap — rodar com `--memory-limit=1G`.
3. **Regex BR de telefone** sugerido no plano (`/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/`) não cobre espaço entre os blocos de 4 dígitos. Versão final: `/^\(?\d{2}\)?[\s-]?9?\d{4}[\s-]?\d{4}$/`.

---

## Referências
- Changelog: `.context/DOCS/CHANGELOG/2026-04-26.md`
- Migration: `api/database/migrations/2026_04_26_003227_create_platform_leads_table.php`
- Controller: `api/src/Domain/Platform/Http/Controllers/PlatformLeadController.php`
- Rotas públicas: `api/src/Domain/Platform/Routes/platform-public.php`
- Landing: `landing/index.html` (search `INTERAZAP_API_URL`, `submitLead`, exit-intent)
- Memory relacionada (não mexer): `.context/DOCS/MEMORY/2026-04-20-feat-041-landing-chat-launcher.md`
