---
name: memory
description: >
  Salvar conhecimento relevante e reutilizável em .context/DOCS/MEMORY/.
  Use quando: decisão técnica importante, armadilha descoberta, padrão implementado, regra de negócio não óbvia.
---

# Memory — Registrar decisão em MEMORY

Salva conhecimento relevante e reutilizável.

## Quando usar

- Decisão técnica importante
- Armadilha descoberta
- Padrão implementado
- Regra de negócio não óbvia
- Bug difícil com causa relevante
- Integração com comportamento inesperado

## O que NÃO registrar

- Tasks concluídas
- Alterações triviais
- Logs operacionais
- "Arquivo X foi alterado"

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

## Exemplo de memória boa

```yaml
titulo: Normalização de telefones E.164 por organização
tipo: Decisão técnica
data: 2024-XX-XX
contexto: Duplicidade em CRM por variação de telefone
conhecimento: Todo telefone normalizado antes de vincular
consequencias: Evita duplicidade de contatos
quando_consultar: CRM, conversas, tickets, WhatsApp
```

## Critério

Memória boa evita erro futuro. Memória ruim é só log.