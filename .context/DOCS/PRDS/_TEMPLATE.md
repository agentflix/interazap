# PRD: [Nome do Produto/Feature]

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | PRD-NNN |
| **Nome** | [nome] |
| **Versão** | 1.0.0 |
| **Data** | YYYY-MM-DD |
| **Autor** | [Nome] |
| **Status** | Rascunho |
| **Bounded Context** | [contexto DDD afetado] |

---

## 1. Visão Geral

### Resumo

[2-3 frases. O que é, por que importa, impacto esperado.]

### Objetivo

[Qual problema de negócio resolve ou oportunidade captura?]

### Benefícios Esperados

| Benefício | Métrica | Valor Esperado |
|-----------|---------|----------------|
| [Benefício 1] | [Métrica] | [Esperado] |

---

## 2. Problema

### Descrição

[Quem é afetado, com que frequência, qual o impacto hoje?]

### Situação Atual

[Fluxo atual sem esta feature. Pontos de dor.]

---

## 3. Solução Proposta

### Descrição

[O que será construído. Linguagem funcional.]

### Escopo

**Dentro:** ✅
- [ ] [item 1]

**Fora:** ❌
- [item fora desta iteração]

### Suposições

| Suposição | Status |
|-----------|--------|
| [Suposição] | [Validada / Não validada] |

---

## 4. Usuários

| Persona | Papel | Objetivo |
|---------|-------|----------|
| [Nome] | [Admin/Agente/etc] | [O que quer] |

### Permissões

| Persona | Criar | Ler | Atualizar | Deletar |
|---------|-------|-----|-----------|---------|
| Admin | ✅ | ✅ | ✅ | ✅ |
| Agente | ✅ | ✅ | ✅ | ❌ |

---

## 5. Requisitos Funcionais

| ID | Requisito | Prioridade | Complexidade |
|----|-----------|------------|--------------|
| RF-001 | [Descrição] | Must | Alta |

---

## 6. Requisitos Não-Funcionais

| Requisito | Meta |
|-----------|------|
| Tempo de resposta API | < 300ms |
| Coverage testes | >= 80% (api), >= 70% (app) |
| Multi-tenancy | Isolamento total por tenant_id |

---

## 7. Critérios de Aceite

| ID | Critério | Como Verificar |
|----|----------|----------------|
| CA-001 | [Critério verificável] | [Comando/teste] |

---

## 8. Dependências

| Tipo | Descrição | Status |
|------|-----------|--------|
| Feature | [feature relacionada] | [pronta] |
| API Externa | [ASAAS / OpenAI / UazAPI] | [disponível] |

---

## 9. Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| [Risco] | Alta/Média/Baixa | Alto/Médio/Baixo | [Plano] |

---

## 10. Revisões

| Versão | Data | Mudanças |
|--------|------|---------|
| 1.0.0 | YYYY-MM-DD | Versão inicial |

### Aprovações

| Papel | Nome | Data |
|-------|------|------|
| Product Manager | | |
| Tech Lead | | |
