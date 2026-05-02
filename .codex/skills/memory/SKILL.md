---
name: memory
description: >
  Salvar conhecimento relevante e reutilizável em .context/DOCS/MEMORY/.
  Use quando: decisão técnica, armadilha descoberta, padrão implementado, regra de negócio.
---

# Memory — Registrar em MEMORY

Salva conhecimento relevante e reutilizável.

## Quando usar

- Decisão técnica importante
- Armadilha descoberta
- Padrão implementado
- Regra de negócio não óbvia
- Bug difícil com causa relevante

## Formato

```yaml
titulo: Nome claro
tipo: Decisão | Aprendizado | Armadilha | Padrão
data: YYYY-MM-DD
contexto: Situação que levou
conhecimento: O que foi aprendido
alternativas: (opcional)
consequencias: Impacto
quando_consultar: Quando voltar a isso
referencias: Links, arquivos
```

## O que NÃO registrar

- Tasks concluídas
- Alterações triviais
- Logs operacionais

## Critério

Memória boa evita erro futuro. Memória ruim é só log.