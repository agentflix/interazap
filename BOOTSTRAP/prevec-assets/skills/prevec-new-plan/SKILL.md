---
name: prevec-new-plan
description: Inicia plano no PREVEC — amadurece ideia em PRD via brainstorming e cria planning session para reusar contexto nos skills seguintes. Triggers: "nova ideia", "novo plano", "prevec-new-plan", "quero planejar". Do NOT use para features já documentadas.
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-new-plan

Transforma ideia bruta em PRD aprovado. Cria planning session para eliminar re-leitura de arquitetura nos skills seguintes.

## Input

```
/prevec-new-plan [ideia]
```

## Pré-condições

- `.context/DOCS/PRDS/` existe
- Skill `brainstorming` instalada (ex: `.claude/skills/brainstorming/SKILL.md`)
- `context-snapshot.md` existe em `.context/ARCHITECTURE/`

## Processo

### 1. Capturar a ideia

Argumento passado: usar como ponto de partida.
Sem argumento: perguntar "Qual é a ideia que quer desenvolver?"

### 2. Criar planning session

```bash
mkdir -p .context/.session
```

Criar `.context/.session/planning-[feature].md` seguindo `prevec-assets/planning-session-model.md`.

Preencher **Architecture Snapshot**: copiar `.context/ARCHITECTURE/context-snapshot.md` inteiro para dentro do session.
Este é o ÚNICO momento em que se lê arquivos de arquitetura nesta cadeia de skills.

Atualizar `Status: BRAINSTORMING`.

### 3. Executar brainstorming

Ler skill `brainstorming` instalada (`.claude/skills/brainstorming/SKILL.md` ou equivalente).

Usar o **Architecture Snapshot do session** para contexto do projeto — não ler `project-brain.yaml` diretamente.

Conduzir:
- Perguntas uma a uma para refinar escopo
- Propor 2-3 abordagens com trade-offs
- Apresentar design por seções e obter aprovação

### 4. Determinar número do PRD

```bash
ls .context/DOCS/PRDS/*.md 2>/dev/null | grep -E '[0-9]{4}-PRD' | sort | tail -1
```

Incrementar. Se não existir: começar em `0001`.

### 5. Criar PRD

Salvar em `.context/DOCS/PRDS/NNNN-PRD-<topic-kebab>.md` usando template em `.context/DOCS/PRDS/_TEMPLATE.md`.

### 6. Preencher PRD no session

Atualizar seção **PRD** do planning session:
- Path, objetivo (3 linhas), critérios de aceite, complexidade estimada, fora de escopo
- `Status: PRD`

### 7. Self-review

- Sem "TBD" ou seções vazias
- Critérios de aceite verificáveis
- Escopo delimitado (incluído + fora)
- Sem contradições internas

### 8. Aprovação do usuário

Apresentar path do PRD e aguardar confirmação.

### 9. Handoff

```
PRD criado: .context/DOCS/PRDS/NNNN-PRD-<topic>.md
Planning session: .context/.session/planning-<topic>.md
Próximo: /prevec-decompose-plan NNNN-PRD-<topic>
```

## Output

```
✅ PRD criado: .context/DOCS/PRDS/[NNNN]-PRD-[topic].md
📋 Planning session: .context/.session/planning-[topic].md
➡️  Próximo: /prevec-decompose-plan [nome]
```

## Error Handling

- `context-snapshot.md` ausente: ler `project-brain.yaml` diretamente e avisar para regenerar o snapshot
- Ideia vaga: perguntas de escopo antes do brainstorming
- PRD template ausente: criar com seções mínimas (Visão Geral, Problema, Solução, Critérios de Aceite)
- Escopo grande: propor decomposição em sub-planos com PRDs separados
