# /task — Decompor e executar task TACE

Implementa task técnica seguindo framework TACE.

## Quando usar

- Task de implementação
- Bug que requer código
- Feature pequena isolada

## Como usar

```
/task [descrição-da-task]
```

## Formato TACE

```
T: O que fazer
A: Onde fazer (arquivos reais)
C: Antes/depois (comportamento)
E: Como validar (teste/comando)
```

## Contexto carregado

- `AGENTS.md` do módulo
- Código relevante (buscar primeiro)

## Contexto NÃO carregado

- Todo projeto
- Feature docs completas

## Saída

- Código implementado
- Evidência de validação