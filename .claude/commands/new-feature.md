# /new-feature — Criar Feature Doc (PREVC: Planning)

Uso: `/new-feature [nome-kebab]`

## Quem executa
- @PM (com apoio de @ARCHITECT e @DESIGNER se necessário)

## Pré-requisitos
- Verificar se já existe PRD em `.context/DOCS/PRDS/`
- Consultar `.context/DOCS/MEMORY/` para decisões anteriores sobre o tema
- Ler `.context/ARCHITECTURE/modules.yaml`

## Passos

1. Criar `.context/DOCS/FEATURES/[nome-kebab].md` a partir de `_TEMPLATE.md`
2. Preencher metadados:
   - ID `FEAT-NNN` (próximo incremental)
   - Bounded context(s) afetado(s)
   - Workspaces (api, gateway, app, electron)
   - Complexidade (P/M/G)
   - Status `Em Planning`
3. Definir escopo (incluído + fora de escopo)
4. Escrever critérios de aceite verificáveis
5. Identificar dependências (modules.yaml + integrações externas)
6. Sinalizar riscos (multi-tenant, billing, providers, OpenAI)
7. Encaminhar para `/review-feature [nome]`

## Output
Arquivo `.context/DOCS/FEATURES/[nome-kebab].md` pronto para Review.
