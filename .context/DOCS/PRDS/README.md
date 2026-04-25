# 📋 PRDs — Product Requirements Documents

Documentos de requisitos de produto para o InteraZap.

## O que é um PRD?

PRD (Product Requirements Document) é um documento que descreve:
- **O problema** que uma feature resolve
- **Quem é afetado** pelo problema
- **A solução proposta** e seus detalhes
- **Critérios de sucesso** para validar a implementação

## Quando Criar um PRD

- Nova feature significativa (mudança de produto)
- Feature que impacta múltiplos bounded contexts
- Feature com dependências externas complexas
- Feature com alto risco técnico ou de negócio
- Mudanças em regras de negócio existentes

## Template

Use `.context/DOCS/PRDS/_TEMPLATE.md` como base.

## Convenções

- Nome do arquivo: `[YYYY-MM-DD]-[nome-kebab].md`
- ID do PRD: `PRD-NNN` (incremental)
- Status: Rascunho → Em Revisão → Aprovado → Em Desenvolvimento → Concluído

## Fluxo

1. PM cria PRD usando template
2. REVIEWER valida completude
3. Stakeholders revisam e aprovam
4. PRD aprovado → Feature doc criado
5. Feature implementada → PRD marcado como Concluído

## Consultar

- PRD ativo: `grep -r "Em Revisão\|Aprovado" .context/DOCS/PRDS/`
- PRD por bounded context: `grep -r "Bounded Context: Chat" .context/DOCS/PRDS/`
