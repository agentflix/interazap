---
name: write-feature
description: "Criar feature docs completos e bem estruturados."
license: MIT
metadata:
  domain: planning
---

# Write Feature

> Skill para criar feature docs completos e bem estruturados.

---

## Quando Usar

Na fase **PLANNING** do PREVC, quando uma nova feature é identificada.

**Comando:** `/new-feature [nome-da-feature]`

---

## Estrutura de Feature Doc

### Metadados

```markdown
## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-NNN |
| **Nome** | [Nome] |
| **Bounded Context** | [Módulo DDD] |
| **Complexidade** | [P/M/G] |
| **Prioridade** | [Must/Should/Could] |
| **Status** | 🟡 Em Planning |
| **Criada em** | [YYYY-MM-DD] |
```

### Seções Obrigatórias

1. **Resumo** — Descrição clara e concisa
2. **Objetivo** — Por que existe? Qual problema resolve?
3. **Escopo** — O que inclui E o que NÃO inclui
4. **Dependências** — Features ou sistemas necessários
5. **Critérios de Aceite** — Condições verificáveis de sucesso

---

## Como Preencher Cada Seção

### Resumo (1-2 frases)

```
Adicionar importação de contatos via arquivo CSV com validação
de formato e relatório de erros por linha.
```

### Objetivo (parágrafo)

```
Permitir que usuários façam upload de arquivos CSV com listas de
contatos para importação em lote, reduzindo tempo de entrada manual
e diminuindo erros de digitação.
```

### Escopo

**Dentro do Escopo ✅:**
- [ ] Upload de arquivo CSV
- [ ] Validação de formato
- [ ] Relatório de erros por linha

**Fora do Escopo ❌:**
- Importação de empresas (future)
- Integração com Google Contacts

### Dependências

| Feature | Tipo | Status |
|---------|------|--------|
| Autenticação | Pré-requisito | ✅ Pronta |
| CRM Base | Base | ✅ Pronta |

### Critérios de Aceite

| ID | Critério | Verificável |
|----|----------|-------------|
| CA-001 | Upload de CSV funciona | [ ] |
| CA-002 | Erro em linha X não bloqueia linhas Y-Z | [ ] |
| CA-003 | Relatório mostra linha e erro específico | [ ] |

---

## Template Completo

```markdown
# Feature: [Nome da Feature]

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-NNN |
| **Nome** | [Nome] |
| **Bounded Context** | [Módulo DDD] |
| **Complexidade** | [P/M/G] |
| **Prioridade** | [Must/Should/Could] |
| **Status** | 🟡 Em Planning |
| **Criada em** | [YYYY-MM-DD] |

---

## Resumo

[Descrição clara e concisa do que é esta feature]

---

## Objetivo

[Por que esta feature existe? Qual problema resolve?]

---

## Escopo

### Dentro do Escopo ✅

- [ ] [Item 1]
- [ ] [Item 2]

### Fora do Escopo ❌

- [Item fora]

---

## Dependências

| Feature | Tipo | Status |
|---------|------|--------|
| [Feature] | [Pré-requisito/Dependência] | [✅/🔄/❌] |

---

## Critérios de Aceite

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | [Critério] | [ ] | ❌ |

---

## Notas

[Any informação adicional]
```

---

## Dicas

1. **Seja específico nos critérios de aceite**
   - ❌ "Sistema funciona bem"
   - ✅ "Usuário recebe email em < 5s após submissão"

2. **Documente o "fora de escopo"**
   - Evita scope creep
   - Alinha expectativas

3. **Identifique dependências cedo**
   - Feature bloqueada? Defina como dependência

4. **Complexidade P/M/G**
   - P: < 1 dia, 1-2 tasks
   - M: 2-3 dias, 3-5 tasks
   - G: > 1 semana, múltiplas fases
