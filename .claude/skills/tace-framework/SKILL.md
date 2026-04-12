---
name: tace-framework
description: "Framework estruturado para decomposição de tasks (Tarefa, Arquivo, Comportamento, Evidência)."
license: MIT
metadata:
  domain: framework
---

# T.A.C.E Framework

> Framework para decomposição de tasks com especificidade máxima.

---

## Visão Geral

T.A.C.E é um acrônimo que garante que toda task seja:
- **T** rage: O QUE fazer?
- **A** rquivo: ONDE fazer?
- **C** omportamento: COMO funciona (antes→depois)?
- **E** vidência: COMO SABER que está pronto?

---

## As 4 Dimensões

### T — Tarefa (O QUE)

Descreve **exatamente** o que precisa ser feito.
- Use verbos no imperativo: "Criar", "Atualizar", "Remover"
- Seja específico: não "melhorar" algo, mas "adicionar validação X"
- Uma task = uma responsabilidade

**Exemplo:**
```
T — Tarefa: Criar DTO CreateChatMessageDTO com validação de tipo de canal
```

### A — Arquivo (ONDE)

Caminho **completo** do arquivo a modificar.
- Backend: `api/src/Domain/Chat/DTOs/CreateChatMessageDTO.php`
- Frontend: `app/src/app/pages/chat/channel/channel.component.ts`

**Exemplo:**
```
A — Arquivo: api/src/Domain/Chat/DTOs/CreateChatMessageDTO.php
```

### C — Comportamento (ANTES → DEPOIS)

Descreve a mudança de comportamento:

**ANTES:** (estado atual)
```
- Usuário pode enviar mensagem sem validar tipo do canal
- Sistema aceita qualquer string como channel_type
```

**DEPOIS:** (novo estado)
```
- Validação rejecta channel_type inválido (não existe)
- Retorna erro 422 com mensagem "Tipo de canal inválido: {tipo}"
```

### E — Evidência (COMO SABER QUE ESTÁ PRONTO)

Lista **verificável** de critérios:
- [ ] Teste unitário passando que valida channel_type válido
- [ ] Teste unitário passando que rejecta channel_type inválido
- [ ] Migration cria constraint check channel_type
- [ ] API endpoint retorna 422 para channel_type inválido

---

## Estrutura Completa

```markdown
#### TASK-X.Y.Z ⏳: [Título da Task]

**T — Tarefa:** [Descrição exata do que fazer]

**A — Arquivo:** [Caminho completo do arquivo]

**C — Comportamento:**
```
ANTES:
- [comportamento atual 1]
- [comportamento atual 2]

DEPOIS:
- [novo comportamento 1]
- [novo comportamento 2]
```

**E — Evidência:**
- [ ] Critério 1 (verificável)
- [ ] Critério 2 (verificável)

**Status:** ⏳ Pendente
```

---

## Exemplo Prático

### Task: Adicionar validação de channel_type

**T — Tarefa:**
Criar constraint check no banco e validação no DTO para garantir que only accept valid channel types (whatsapp, telegram, webchat)

**A — Arquivo:**
`api/src/Domain/Chat/DTOs/CreateChatMessageDTO.php`
`api/database/migrations/2026_04_11_000000_add_channel_type_constraint.php`

**C — Comportamento:**
```
ANTES:
- Tabela chat_messages permite qualquer string em channel_type
- Não há validação no banco

DEPOIS:
- Migration adiciona CHECK constraint
- channel_type só aceita: 'whatsapp', 'telegram', 'webchat'
- DTO valida e retorna 422 para valores inválidos
```

**E — Evidência:**
- [ ] Migration executa com sucesso
- [ ] Teste INSERT com channel_type='invalid' falha com erro
- [ ] Teste INSERT com channel_type='whatsapp' funciona
- [ ] DTO retorna erro 422 com mensagem clara

---

## Quando Usar

- **Toda task** antes de implementar
- **Decomposição** de feature em tasks menores
- **Review** de task antes de implementar
- **Validation** de task concluída

---

## Armadilhas

1. **Task vaga:** "Melhorar o chat" → ❌
   - Tarefa deve ser específica: "Adicionar validação de channel_type"

2. **Múltiplas responsabilidades:** "Criar migration e service" → ❌
   - Dividir em duas tasks: TASK-X.1 e TASK-X.2

3. **Evidência não verificável:** "Código funciona" → ❌
   - Critério deve ser objetivo: "Teste passando"

4. **Arquivo genérico:** "Api/Chat" → ❌
   - Caminho completo: `api/src/Domain/Chat/Http/Requests/CreateMessageRequest.php`
