# Skill: Escrever Feature Doc

> Como o PM redige um feature doc para o InteraZap.

## Estrutura mínima

1. **Metadados** (ID, contexto, complexidade, status)
2. **Resumo** (1 parágrafo)
3. **Objetivo de negócio**
4. **Bounded context(s) afetado(s)**
5. **Escopo**
   - Incluído (lista checklist)
   - Fora de escopo (lista clara)
6. **Critérios de aceite** (verificáveis)
7. **Dependências** (modules.yaml, integrações externas)
8. **Riscos** (multi-tenant, billing, integrações sensíveis)
9. **Tasks** (preenchido após decomposição pelo ARCHITECT)

## Sinais de feature mal definida

- "Melhorar X" sem critérios
- Sem bounded context identificado
- Critérios de aceite não verificáveis ("usuário fica satisfeito")
- Escopo amplo demais (várias features misturadas)

## Quando consultar MEMORY

- Antes de definir escopo: existe decisão anterior sobre o tema?
- Antes de estimar complexidade: já existem armadilhas registradas em features similares?

## Template

Usar `.context/DOCS/FEATURES/_TEMPLATE.md`.
