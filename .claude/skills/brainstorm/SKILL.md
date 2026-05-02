---
name: brainstorm
description: >
  Auxilia no brainstorming de feature: entende o problema, questiona, documenta e decompõe em TACE.
  Use quando: nova feature precisa ser explorada, escopo não está claro, necessidade de validar ideias.
---

# Brainstorm — Explorar e Documentar Feature

Auxilia no brainstorming de feature: entende o problema, questiona, documenta e decompõe em TACE.

## Quando usar

- Nova feature com escopo vago
- Precisa validar ideias antes de implementar
- Decisão entre múltiplas abordagens
- Feature complexa que precisa ser decomposta

## NÃO usar quando

- Bug simples (use Fast Path)
- Task já definida (use TACE direto)

---

## Fase 1 — Entender o QUÊ

```
PERGUNTAS:
1. Qual problema estamos resolvendo?
2. Quem é afetado (usuário final, sistema)?
3. Qual o impacto se não fizermos?
4. Como medimos sucesso?
```

**Objetivo:** Definir o problema claramente.

---

## Fase 2 — Entender o COMO

```
PERGUNTAS:
1. Quais abordagens existem?
2. Quais as vantagens/desvantagens de cada?
3. Qual é a mais adequada para nosso contexto?
4. Quais dependências existem?
5. O que pode dar errado?
```

**Objetivo:** Escolher a melhor abordagem.

---

## Fase 3 — Documentar

Criar `FEATURE-XXX.md` em `.context/DOCS/FEATURES/`:

```markdown
# FEATURE-XXX — [Título]

## Problema
[O que estamos resolvendo]

## Usuários Impactados
[Quem é afetado]

## Solução
[Abordagem escolhida e por quê]

## Requisitos Funcionais
- [RF-01]: ...
- [RF-02]: ...

## Requisitos Não Funcionais
- [RNF-01]: ...

## Critérios de Aceite
- [CA-01]: Dado... Quando... Então...
- [CA-02]: ...

## Fora de Escopo
- [Item excluido]

## Riscos
- [R-01]: [Descrição] → Mitigação

## Tasks (TACE)
### TASK-001: [Título]
**T:** ...
**A:** ...
**C:** ...
**E:** ...
```

---

## Fase 4 — Decompor em TACE

Usar PREVC para validar e decompor:

```
1. Planning: Feature doc criada
2. Review: Validar com constraints (stack, arquitetura, tempo)
3. Decompose: Extrair tasks TACE
4. Validate: Cada task verificável?
5. Confirm: Lista de tasks final
```

---

## Output

1. Feature doc em `.context/DOCS/FEATURES/FEATURE-XXX.md`
2. Lista de tasks TACE
3. Decisão de abordagem documentada
4. Riscos identificados com mitigação

---

## Exemplo de conversa

```
Você: Preciso melhorar o sistema de notificações
Brainstorm: O que exatamente está ruim? Notificações não chegam? São lentas? O usuário não vê?
Você: São lentas e às vezes perdemos notificações
Brainstorm: Quanto tempo em média? Qual perda percentual? Quem é afetado?
Você: Aprox 30% de perda, afetando operadores de atendimento
Brainstorm: Quais causas possíveis? O provider não entrega? Gateway não processa? DB falha?
[Continua explorando até entender completamente]
```

---

## Critério de qualidade

Feature doc clara permite que outro desenvolvedor implemente sem precisar perguntar nada.