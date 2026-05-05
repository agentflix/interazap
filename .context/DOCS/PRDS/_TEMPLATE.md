# PRD: [Nome do Produto/Feature]

> Product Requirements Document — Requisitos de Produto

---

## 1. Visão Geral

| Campo | Descrição |
|-------|-----------|
| Nome | |
| Versão | 1.0.0 |
| Data | YYYY-MM-DD |
| Autor | |
| Status | Rascunho / Em Revisão / Aprovado / Em Desenvolvimento / Concluído |
| Bounded Context | (Ex: Chat, CRM, Billing) |

### Resumo Executivo
[2-3 frases entendíveis por qualquer stakeholder]

### Objetivo
[Qual problema de negócio resolve? Qual oportunidade captura?]

### Benefícios Esperados
| Benefício | Métrica | Atual | Esperado |
|-----------|---------|-------|----------|
| | | | |

---

## 2. Problema

### Descrição
### Dor do Usuário
| Persona | Dor | Impacto | Frequência |
|---------|-----|---------|------------|
| | | | |

### Situação Atual
### Análise de Impacto
| Dimensão | Impacto | Observação |
|----------|---------|------------|
| Receita | | |
| Custo | | |
| Conversão | | |
| Retenção | | |

---

## 3. Solução Proposta

### Descrição
### Como funciona
### Escopo
#### Dentro do Escopo
- [ ]
#### Fora do Escopo
-
### Diferenciação Competitiva
### Suposições
| Suposição | Status | Impacto se Incorreta |
|-----------|--------|----------------------|
| | | |

---

## 4. Usuários

### Personas
| Persona | Papel | Objetivo | Frustrações |
|---------|-------|----------|-------------|
| | | | |

### Comportamento
| Persona | Antes | Durante | Depois |
|---------|-------|---------|--------|
| | | | |

### Permissões
| Persona | Criar | Ler | Atualizar | Deletar |
|---------|-------|-----|-----------|---------|
| Admin | | | | |
| Usuário | | | | |

---

## 5. Requisitos Funcionais

### Principais
| ID | Requisito | Descrição | Prioridade | Complexidade |
|----|-----------|-----------|------------|--------------|
| RF-001 | | | Must / Should / Could / Won't | Alta / Média / Baixa |

### Interface
| ID | Tela | Elemento | Comportamento | Prioridade |
|----|------|----------|---------------|------------|
| RI-001 | | | | |

### Dados
| ID | Entidade | Campo | Tipo | Validação | Obrigatório |
|----|----------|-------|------|-----------|-------------|
| RD-001 | | | | | |

### Integração
| ID | Sistema | Tipo | Descrição | Prioridade |
|----|---------|------|-----------|------------|
| RI-001 | | API/Webhook | | |

---

## 6. Requisitos Não-Funcionais

### Performance
| Métrica | Meta | Atual | Referência |
|---------|------|-------|------------|
| Tempo de resposta API | < 300ms (p95) | | |
| Latência WebSocket | < 200ms | | |

### Segurança
| Requisito | Implementação | Prioridade |
|-----------|--------------|------------|
| Autenticação Sanctum | | Must |
| RBAC Spatie | | Must |
| HMAC em webhooks | | Must |

### Escalabilidade
| Cenário | Capacidade Atual | Capacidade Planejada |
|---------|-----------------|----------------------|
| Tenants ativos | | |
| Mensagens/dia | | |

### Compatibilidade
| Plataforma | Versão Mínima |
|------------|---------------|
| Chrome | |
| Safari iOS | |
| Chrome Android | |
| Electron desktop | macOS 12+ / Windows 10+ / Ubuntu 20+ |

### Acessibilidade
| Critério WCAG | Nível | Conformidade |
|---------------|-------|--------------|
| | A / AA / AAA | |

### Disponibilidade
| Métrica | Meta |
|---------|------|
| Uptime | 99.9% |
| MTTR | < 30 min |

---

## 7. Critérios de Aceite

### Principais
| ID | Critério | Verificação | Status |
|----|----------|-------------|--------|
| CA-001 | | | |

### Detalhados (BDD)
**CA-001: [Nome]**
- Dado [condição]
- Quando [ação]
- Então [resultado]

### Definition of Done
- [ ] Código implementado e revisado
- [ ] Testes (Pest / Vitest) passando
- [ ] Coverage atendido
- [ ] Gates de cada workspace passando
- [ ] Documentação atualizada
- [ ] UX/UI aprovado
- [ ] Security review aprovado
- [ ] CHANGELOG + MEMORY atualizados

---

## 8. Wireframes / Layout

[Links e referências em `.context/LAYOUT/[feature]/`]

| Tela | Desktop | Mobile | Estado |
|------|---------|--------|--------|
| | | | |

### Estados de Interface
| Tela | Estado | Descrição |
|------|--------|-----------|
| | Empty | |
| | Loading | |
| | Error | |
| | Success | |

---

## 9. Dependências

### Internas
| Feature/Sistema | Tipo | Status | Blocker |
|-----------------|------|--------|---------|
| | | | |

### Externas
| Sistema | Tipo | Necessidade | Status |
|---------|------|-------------|--------|
| UazAPI / Z-API | API | | |
| OpenAI | API | | |
| Asaas | API + Webhook | | |

---

## 10. Riscos

| Risco | Probabilidade | Impacto | Mitigação | Status |
|-------|--------------|---------|-----------|--------|
| Vazamento entre tenants | Média | Crítico | Trait BelongsToTenant + testes | |
| Custo OpenAI alto | Média | Alto | Cache embeddings + budget | |
| Webhook duplicado | Alta | Médio | Idempotência Redis | |

---

## 11. Cronograma

| Fase | Início | Fim | Responsável | Status |
|------|--------|-----|-------------|--------|
| Discovery | | | | |
| Design | | | | |
| Development | | | | |
| QA | | | | |
| Launch | | | | |

---

## 12. Revisões

| Versão | Data | Autor | Mudanças |
|--------|------|-------|----------|
| 1.0.0 | | | Versão inicial |

### Aprovações
| Papel | Nome | Data |
|-------|------|------|
| Product Manager | | |
| Tech Lead | | |
| Design Lead | | |
| QA Lead | | |
