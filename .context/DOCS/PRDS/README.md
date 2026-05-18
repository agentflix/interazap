# PRDs — Product Requirements Documents

Requisitos de produto para features significativas do InteraZap.

## Quando Criar um PRD

- Nova feature com impacto em múltiplos bounded contexts
- Feature com alto risco financeiro (Billing/ASAAS)
- Feature que muda fluxo existente de atendimento/CRM
- Feature com dependências externas complexas (OpenAI, ASAAS, UazAPI)

## Convenções

- Nome: `[YYYY-MM-DD]-[nome-kebab].md`
- ID: `PRD-NNN` (sequencial)
- Status: Rascunho → Em Revisão → Aprovado → Em Desenvolvimento → Concluído

## Fluxo

```
PRD aprovado → Feature doc → Tasks T.A.C.E → PREVC
```

## Consultar

```bash
# PRDs ativos
grep -r "Em Revisão\|Aprovado\|Em Desenvolvimento" .context/DOCS/PRDS/

# PRD por bounded context
grep -r "Bounded Context.*Billing" .context/DOCS/PRDS/
```
