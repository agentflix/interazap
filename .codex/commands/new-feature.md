# Nova Feature (PREVC: Planning)

Uso: `/new-feature [nome]`

## Processo (@PM + @ARCHITECT)

1. Consultar MEMORY para decisões anteriores: `grep -r "[tema]" .context/DOCS/MEMORY/`
2. Verificar PRDs relacionados em `.context/DOCS/PRDS/`
3. Analisar dependências em `.context/ARCHITECTURE/modules.yaml`
4. Identificar bounded contexts afetados (Ai, Auth, Billing, Chat, CRM, etc.)
5. Se houver UI → @DESIGNER define wireframes em `.context/LAYOUT/`
6. Criar feature doc em `.context/DOCS/FEATURES/[nome].md` usando `_TEMPLATE.md`
7. Preencher: nome, bounded context, complexidade (P/M/G), escopo, dependências, critérios de aceite

## Saída Esperada

```
Feature doc criada: .context/DOCS/FEATURES/[nome].md
Status: Pronta para REVIEW
Próximo: /review-feature [nome]
```
