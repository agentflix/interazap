---
name: deep-plan
description: Deep codebase investigation followed by a machine-executable implementation plan. Launches parallel Explore agents to trace full data pipelines, discover hidden prerequisites, and map existing patterns — then writes a structured plan file another LLM can execute without additional context. Triggers: "planejar implementação", "criar plano técnico", "deep plan", "implementation plan", "como implementar isso", "plan this", "plan before implementing". NOT for direct code implementation (use prevec-execute-task), high-level feature ideation (use prevec-new-plan), or simple single-file edits.
metadata:
  author: Rafael Silva
  version: 1.0.0
---

# deep-plan

Produz um plano de implementação auto-suficiente — rico o suficiente para outra LLM executar sem re-investigar o código. O valor está na profundidade da investigação, não na velocidade.

## Princípio Central

> **Investigar fundo primeiro. Planejar depois. Nunca ao contrário.**

Um plano baseado em investigação superficial vai errar prerrequisitos ocultos, chamar funções que não existem ou propor padrões que divergem do código real. Gasta mais tempo corrigindo do que teria gasto investigando.

---

## Fase 1 — Investigação Profunda (obrigatória)

### 1.1 — Lançar Explore agents em paralelo

Nunca investigue sequencialmente quando paralelo for possível. Lance 2-3 agents Explore cobrindo ângulos distintos:

**Agent 1 — Pipeline tracer**: trace o fluxo completo de dados do ponto de entrada ao ponto de saída. Não pare no arquivo óbvio — siga cada `dispatch`, `event`, `job`, `service`, `action` até chegar ao efeito final.

**Agent 2 — Prerequisites hunter**: busque o que deve ser verdadeiro ANTES da feature funcionar. Procure: feature flags, colunas de banco que habilitam comportamento (`ai_enabled`, `is_active`), config env vars, índices, permissões, seeds necessários.

**Agent 3 — Pattern extractor** (quando há código análogo): encontre implementações similares já existentes no projeto. A nova feature deve seguir o mesmo padrão — não inventar.

Prompt modelo para Explore agent:

```
Read-only investigation. I need to understand [specific question].
Trace the full flow from [entry point] to [end effect].
Follow every dispatch/event/job/service call — do not stop at the obvious file.
Report: exact file:line references, method signatures, data shapes, hidden gates.
```

### 1.2 — O que cada agent deve reportar

Exija dos agents — não aceite respostas vagas:

| Pergunta | Resposta exigida |
|---|---|
| Como X é chamado? | `Service::method(arg1: type, arg2: type)` com arquivo e linha |
| O que dispara Y? | Evento, job ou command exato com namespace |
| O que bloqueia Z? | Condição booleana exata com arquivo:linha |
| Qual o shape dos dados? | Array keys ou DTO fields listados |
| Existe padrão similar? | Arquivo de exemplo com linha de referência |

### 1.3 — Validar antes de planejar

Antes de escrever o plano, confirme:

- [ ] Fluxo completo traçado ponta a ponta (não só os arquivos óbvios)
- [ ] Todos os pré-requisitos ocultos identificados
- [ ] Padrão existente encontrado (ou ausência documentada)
- [ ] Nenhuma função/método assumido sem ter lido a assinatura real

Se algum ponto estiver incerto, lance outro Explore agent antes de avançar.

---

## Fase 2 — Construção do Plano

### 2.1 — Escrever o arquivo de plano

Use o template em `references/plan-template.md` como estrutura base.

O plano deve ser **auto-suficiente**: outra LLM deve conseguir executar sem ter lido nenhum arquivo de contexto adicional além do plano.

**Regras de ouro para o plano:**

1. **Context vem primeiro** — explique o POR QUÊ antes do COMO. Uma LLM sem contexto toma decisões melhores quando entende a motivação.

2. **Pré-requisitos são obrigatórios** — liste tudo que deve ser verdadeiro antes da implementação começar. Sem isso, a LLM executora vai falhar em ponto obscuro sem entender por quê.

3. **Caminhos de arquivo absolutos** — nunca `src/Domain/Ai/...`, sempre `api/src/Domain/Ai/...`. A LLM executora não sabe qual é o diretório raiz.

4. **Code snippets são evidência, não decoração** — inclua trechos reais do código existente para ancorar onde a mudança se encaixa. Copie signatures reais, não as invente.

5. **Verificação é parte do plano** — sempre inclua como testar que funcionou. Sem isso, a LLM executora vai reportar "done" sem evidência.

### 2.2 — Nível de detalhe esperado por seção

**Seção Context:**
- O problema que justifica a mudança
- O que falha sem essa implementação
- O resultado esperado após implementar

**Seção Prerequisites:**
- Estado de banco/config necessário
- Migrações que devem ter rodado
- Feature flags a habilitar para testar
- Services/processes externos necessários (ex: Gateway online)

**Seção Files:**
- Cada arquivo a criar: path completo + o que deve conter
- Cada arquivo a modificar: path + seção específica + natureza da mudança

**Seção Implementation:**
- Dividido em fases sequenciais numeradas
- Cada fase: objetivo, código de referência, padrão a seguir
- Inclui snippets reais copiados da investigação

**Seção Verification:**
- Comando exato para testar
- Resultado esperado (status codes, output, DB state)
- O que checar se falhar

---

## Fase 3 — Entrega

O plano é escrito em arquivo, não em chat. Use Write tool.

Path padrão para o plano:
```
.claude/plans/[slug-descritivo].md
```

Após escrever, reporte ao usuário:
- O que o plano cobre
- Os pré-requisitos descobertos (especialmente os não-óbvios)
- Qual o comando de verificação principal
- O path do arquivo criado

Não implemente. Não altere código. Só investiga e planeja.

---

## Anti-padrões

| Anti-padrão | Por que falha |
|---|---|
| Investigar apenas os arquivos óbvios | Perde gates ocultos (ex: `plan.ai_enabled`, `is_bot_active`) |
| Assumir assinaturas de método sem ler | Invoca com parâmetros errados |
| Plano sem pré-requisitos | Executora falha em ponto obscuro sem diagnóstico |
| Paths relativos | Executora não sabe de onde contar |
| Code snippets inventados | Código gerado não compila / não integra |
| Verificação ausente | Executora reporta done sem evidência |
| Investigação sequencial | 3x mais lento que paralela |

---

## Referências

- `references/plan-template.md`: template completo do documento de plano com todas as seções e instruções de preenchimento. Leia antes de escrever qualquer plano.
