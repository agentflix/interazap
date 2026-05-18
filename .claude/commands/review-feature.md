# Revisar Feature (PREVC: Review)

Uso: `/review-feature [nome]`

## Processo (@REVIEWER + @ARCHITECT)

1. Ler `.context/DOCS/FEATURES/[nome].md` completamente
2. Checklist:
   - [ ] Nome e bounded context definidos
   - [ ] Escopo incluído e fora de escopo claros
   - [ ] Dependências identificadas
   - [ ] Critérios de aceite verificáveis (não vagos)
   - [ ] Complexidade estimada (P/M/G)
   - [ ] Sem violação das regras de arquitetura (`.context/ARCHITECTURE/`)
   - [ ] Multi-tenancy: se toca Platform/Auth → flag explícita
   - [ ] Billing/ASAAS: se toca Billing → flag de risco financeiro
   - [ ] WhatsApp: se toca Chat → menciona UazAPI + Z-API
3. Se aprovada → prosseguir para decomposição
4. Se reprovada → listar ajustes necessários e retornar para @PM

## Saída Esperada

```
Revisão: [APROVADA / REPROVADA — motivo]
Próximo: /decompose [nome]  (se aprovada)
```
