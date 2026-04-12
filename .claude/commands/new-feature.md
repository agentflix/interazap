# Nova Feature (PREVC: Planning)

Uso: `/new-feature [nome]`

## Processo

1. **Verificar se já existe** feature com mesmo nome
2. **Identificar Bounded Context** - qual módulo DDD é impactado
3. **Criar feature doc** usando template em `.context/DOCS/FEATURES/_TEMPLATE.md`
4. **Definir complexidade** (P/M/G)
5. **Documentar escopo** (incluído + fora)
6. **Estabelecer critérios de aceite**

## Output

Feature doc criada em `.context/DOCS/FEATURES/[nome].md`

## Exemplo

```
/new-feature importacao-csv-contatos
```

Gera: `.context/DOCS/FEATURES/2026-04-11-importacao-csv-contatos.md`
