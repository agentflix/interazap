# 🧠 Memory

Memória persistente do projeto. Decisões, aprendizados e armadilhas.
Consultado pela IA para NÃO repetir erros e manter consistência.

## Tipos de Registro

| Tipo | Emoji | Quando Registrar |
|------|-------|-----------------|
| Decisão | 🧠 | Quando uma decisão técnica ou de produto é tomada |
| Aprendizado | 📚 | Quando algo é descoberto que outros devem saber |
| Armadilha | ⚠️ | Quando algo deu errado e não deve se repetir |
| Insight | 💡 | Quando uma observação pode melhorar o projeto |

## Convenções
- Um arquivo por decisão/aprendizado: `[YYYY-MM-DD]-[titulo-kebab].md`
- Template: `_TEMPLATE.md`
- Tags para facilitar busca
- Sempre referenciar feature/task que gerou

## Quando Registrar
- Fase CONFIRM do PREVC → decisões tomadas durante a feature
- Após resolver um bug difícil → armadilha para não repetir
- Após discussão técnica → decisão com alternativas descartadas
- Quando descobrir algo não óbvio do código → aprendizado

## Como Consultar (IA)
- Antes de implementar: `grep -r "[termo]" .context/DOCS/MEMORY/`
- Antes de decidir: buscar decisões anteriores sobre o mesmo tema
- Antes de refatorar: verificar armadilhas conhecidas
