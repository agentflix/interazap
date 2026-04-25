# Review Feature (PREVC: Review)

Uso: `/review-feature [nome]`

## Processo

1. **Ler feature doc** completa
2. **Verificar completude:**
   - [ ] Metadados preenchidos?
   - [ ] Resumo claro?
   - [ ] Escopo definido?
   - [ ] Dependências identificadas?
   - [ ] Critérios de aceite verificáveis?
3. **Validar contra arquitetura** - `.context/ARCHITECTURE/`
4. **Aprovar ou solicitar ajustes**

## Output

```
✅ Feature aprovada
ou
❌ Ajustes necessários:
- [ ] Item 1
- [ ] Item 2
```

Se aprovada → prosseguir para `/decompose [nome]`
