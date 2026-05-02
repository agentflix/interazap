# T.A.C.E — Framework para Decomposição de Tasks

**T.A.C.E** = Task / Area / Behavior / Evidence

## Quando usar

- Toda task de implementação
- Antes de começar código
- Para comunicar escopo a outro agente

## Formato

```yaml
task_id: TASK-XXX
titulo: Título claro da task

T:
  # Tarefa — O que deve ser feito
  # Seja claro, pequeno, verificável

A:
  # Area — Onde a mudança ocorre
  # Arquivos, módulos, rotas, services, components

C:
  # Comportamento — Como o sistema deve se comportar
  # Antes: ...
  # Depois: ...
  # Regras: ...

E:
  # Evidência — Como provar que está pronto
  # Teste, build, lint, screenshot, comando
```

---

## Exemplo

```yaml
task_id: TASK-001
titulo: Garantir contact_id em conversas criadas pelo chat público

T:
Criar ou vincular crm_contact ao iniciar conversa pública.

A:
gateway/src/domains/chat/services/chat.service.ts
api/src/Domain/Chat/Actions/CreateConversationAction.php

C:
Antes: conversation pode ser criada sem contact_id
Depois: toda conversation pública deve possuir contact_id válido

E:
Teste automatizado:
1. Cria conversa pública sem contact_id
2. Verifica que conversation possui contact_id preenchido
```

---

## Regras

1. **T** deve ser claro e verificável
2. **A** deve apontar para arquivos reais
3. **C** deve descrever antes/depois
4. **E** deve ser observável (teste, comando, screenshot)

## Máscara

```
T: O que fazer
A: Onde fazer
C: Como se comporta antes/depois
E: Como validar que funcionou
```

---

## Critério de Qualidade

Task boa é task que outro desenvolvedor consegue executar sem reler toda a feature.