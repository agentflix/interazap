# Memory: Token Limits de IA pertencem ao plano da plataforma

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | Codex |
| **Contexto** | FEAT-003 — Migração de Token Limits para PlatformPlans + Cobrança de Overage |
| **Tags** | platform, billing, ai, overage, token-limits |

---

## Situação

Os campos `token_limit_monthly`, `allow_overage` e `overage_price_per_1k` estavam em `ai_prompt_plans`, mas representam política comercial do plano contratado. A cobrança mensal também precisava somar assinatura e excedente de IA sem depender da tela de prompts.

---

## Decisão / Aprendizado

Os limites de tokens passam a viver em `platform_plans`. `ai_prompt_plans` fica restrito ao conteúdo do prompt por plano.

O comando `billing:generate-monthly-invoices` aceita `reference_month` explícito e, quando omitido, usa o mês anterior. Isso combina com o schedule do dia 1 às 06:00, pois o consumo de IA do mês encerrado já está fechado.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter limites em `ai_prompt_plans` | Mistura governança de prompt com política comercial e dificulta faturamento por plano |
| Usar mês atual como default do comando | No dia 1 o mês atual ainda não tem consumo fechado para overage |
| Criar faturas direto no comando sem `BillingInvoiceActions` | Perderia o fluxo existente de criação e evento `BillingInvoiceCreatedEvent` |

---

## Consequências

### Positivas
- O contrato de plano passa a concentrar preço, limite de IA e política de excedente.
- A tela de prompts fica menor e focada em conteúdo.
- A fatura mensal tem metadata auditável com `base_price`, tokens e overage.

### Negativas / Trade-offs
- O comando mensal é uma operação administrativa cross-tenant e precisa consultar faturas sem `TenantScope` apenas para idempotência documentada.
- `composer gate:all` ainda depende da correção de erros PHPStan preexistentes fora da FEAT-003.

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-003-token-limits-platform-plans.md`
- Task: `.context/DOCS/TASKS/feat-003-token-limits-tasks.md`
