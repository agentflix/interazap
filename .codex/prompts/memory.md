# memory — Consultar ou criar memória

## Objetivo

Consultar decisões passadas ou registrar novo aprendizado.

## Trigger

```
/memory [consulta|novo]
```

## Consultar

Procura em `.context/DOCS/MEMORY/` por:
- Decisões técnicas
- Armadilhas
- Padrões
- Regras de negócio

## Novo

Registra em formato YAML:

```yaml
titulo: Nome claro
tipo: Decisão | Aprendizado | Armadilha | Padrão
data: YYYY-MM-DD
contexto: Situação que levou
conhecimento: O que foi aprendido
consequencias: Impacto
quando_consultar: Quando voltar a isso
```

## Quando NÃO usar

- Task operacional trivial
- Log de alteração
- Informação óbvia