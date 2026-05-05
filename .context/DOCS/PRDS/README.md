# PRDs — Product Requirements Documents

Documentos de requisitos de produto para o InteraZap.

## O que é um PRD

Documento que descreve o problema, quem é afetado, a solução proposta e os critérios de sucesso de uma feature significativa.

## Quando criar

- Nova feature significativa
- Feature que toca múltiplos bounded contexts (ex: Chat + CRM + Ai)
- Feature com integrações externas críticas (Asaas, OpenAI, providers WhatsApp)
- Feature com risco técnico ou de negócio alto
- Mudanças em regras de negócio

## Template

`.context/DOCS/PRDS/_TEMPLATE.md`

## Convenções

- Nome do arquivo: `YYYY-MM-DD-nome-kebab.md`
- ID: `PRD-NNN` (incremental)
- Status: Rascunho → Em Revisão → Aprovado → Em Desenvolvimento → Concluído

## Fluxo

1. PM cria PRD usando template
2. REVIEWER valida completude
3. Stakeholders aprovam
4. PM cria feature doc baseado no PRD
5. Feature implementada via PREVC
6. PRD marcado como Concluído

## Consultar

- PRDs ativos: `grep -r "Status.*Em Revisão\|Aprovado" .context/DOCS/PRDS/`
- PRD por bounded context: `grep -r "Bounded Context.*Chat" .context/DOCS/PRDS/`
