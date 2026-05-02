---
name: brainstorm
description: >
  Explorar feature: entende o problema, questiona, documenta e decompõe em TACE.
  Use quando: escopo vago, necessidade de validar ideias, feature complexa.
---

# Brainstorm — Explorar e Documentar Feature

Explora problema, questiona, documenta e decompõe em TACE.

## Fases

### 1. QUÊ — Entender problema
- Qual problema estamos resolvendo?
- Quem é afetado?
- Qual impacto se não fizermos?

### 2. COMO — Explorar soluções
- Quais abordagens existem?
- Vantagens/desvantagens?
- Qual é a mais adequada?

### 3. DOCUMENTAR — Criar FEATURE-XXX.md

```markdown
# FEATURE-XXX — [Título]

## Problema
## Usuários Impactados
## Solução
## Requisitos Funcionais
## Critérios de Aceite
## Riscos
## Tasks (TACE)
```

### 4. DECOMPOR — PREVC + TACE

```
Planning → Review → Decompose → Validate → Confirm
```

## Output

- Feature doc em `.context/DOCS/FEATURES/`
- Tasks TACE decompostas
- Riscos com mitigação

## Exemplo

```
Você: Melhorar notificações
Brainstorm: O que está ruim? Notificações não chegam? Lentas? Perdidas?
Você: 30% de perda, afetando operadores
Brainstorm: Causas? Provider? Gateway? DB?
```

## Critério

Feature doc clara permite implementação sem perguntas.