# Memory — Decisões e Aprendizados

Memória persistente do InteraZap. Decisões técnicas, aprendizados e armadilhas.
A IA consulta MEMORY antes de qualquer decisão para não repetir erros.

## Tipos de Registro

| Tipo | Emoji | Quando Registrar |
|------|-------|-----------------|
| Decisão | 🧠 | Decisão técnica ou de produto tomada |
| Aprendizado | 📚 | Algo descoberto que outros devem saber |
| Armadilha | ⚠️ | Algo que deu errado, não deve repetir |
| Insight | 💡 | Observação que melhora o projeto |

## Convenções

- Nome: `[YYYY-MM-DD]-[titulo-kebab].md`
- Template: `_TEMPLATE.md`
- Tags para facilitar busca
- Sempre referenciar feature/task que gerou

## REGRA CRÍTICA

**Antes de qualquer decisão técnica, consultar MEMORY:**

```bash
grep -r "[tema]" .context/DOCS/MEMORY/
```

## Quando Registrar

- Fase CONFIRM → decisões tomadas durante a feature
- Após bug difícil resolvido → armadilha para não repetir
- Após discussão técnica → decisão com alternativas descartadas
- Padrão novo estabelecido → aprendizado

## Temas Frequentes no InteraZap

```bash
grep -r "multi-tenant\|BelongsToTenant" .context/DOCS/MEMORY/
grep -r "WhatsApp\|UazAPI\|Z-API" .context/DOCS/MEMORY/
grep -r "ASAAS\|billing\|webhook" .context/DOCS/MEMORY/
grep -r "OpenAI\|RAG\|embedding" .context/DOCS/MEMORY/
grep -r "circuit-breaker\|retry" .context/DOCS/MEMORY/
```
