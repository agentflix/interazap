# Prompt — Documento de Testes SaaS

> Cole este prompt completo para a IA gerar seu plano de testes executável.

---

## FASE 1 — EXPLORAÇÃO (não gere nada ainda)

Você é um engenheiro sênior de QA especializado em testes de integração, contratos de API e stress testing.

Antes de qualquer output, leia e mapeie:

1. A stack tecnológica do sistema:
    - Frontend: Angular (cobertura 80% já existente — não replicar)
    - Gateway/BFF: [descreva seu gateway — ex: Kong, NestJS BFF, etc.]
    - Backend: [descreva — ex: Laravel, NestJS, etc.]
    - Módulo Autopilot: [descreva brevemente o que ele faz]
    - Banco de dados: [ex: PostgreSQL, Redis]

2. Os endpoints críticos expostos pelo gateway
3. Os contratos de API disponíveis (OpenAPI/Swagger, Postman collections, ou descreva-os)
4. O fluxo do Autopilot: como ele recebe, processa e responde atendimentos

Ao final desta fase, liste:

- Áreas de risco não cobertas pelos 80% de testes existentes
- Dependências entre módulos que precisam de teste de integração
- Pontos de falha em volumetria (gargalos esperados)

---

## FASE 2 — PLANO DE TESTES (não implemente ainda — apenas raciocine)

Com base na exploração, produza um plano de testes dividido em 4 categorias:

**A) TESTES DE INTEGRAÇÃO VIA CURL (simulando o frontend Angular)**

- Quais fluxos precisam ser cobertos end-to-end?
- Quais dados de entrada são necessários para cada cenário?
- Como criar dados para relatórios (seed data via curl encadeado)?

**B) VALIDAÇÃO DE CONTRATOS DE API (backend vs gateway)**

- Quais endpoints têm risco de divergência de schema?
- Como confrontar o contrato esperado pelo gateway com o que o backend retorna?
- Quais campos são obrigatórios, opcionais, ou podem ser nulos?

**C) TESTES DE PERFORMANCE E STRESS DO AUTOPILOT**

- Qual é o volume baseline esperado de atendimentos?
- Como simular picos de volumetria (ex: 10x, 50x, 100x o baseline)?
- O que monitorar durante o stress: latência p95/p99, error rate, fila, timeouts?

**D) GERAÇÃO DE DADOS PARA RELATÓRIOS**

- Quais relatórios precisam de dados reais para validação?
- Como encadear chamadas curl para criar estado consistente (ex: criar gate → criar atendimento → fechar → exportar relatório)?

Para cada categoria, indique: prioridade (P0/P1/P2), risco coberto, e ferramentas recomendadas.

---

## FASE 3 — IMPLEMENTAÇÃO

Gere o documento de testes completo e executável com as seguintes seções:

### SEÇÃO 1: Setup e variáveis de ambiente

Defina variáveis reutilizáveis:

```bash
BASE_URL="https://seu-gateway.com"
TOKEN=""         # preenchido após login
CONTENT_TYPE="Content-Type: application/json"
GATE_ID=""       # preenchido após criar gate
ATENDIMENTO_ID="" # preenchido após criar atendimento
```

### SEÇÃO 2: Testes de integração via curl

Para cada fluxo identificado, gere:

- curl completo (método, headers, body JSON)
- Dado esperado na resposta (status code + campos-chave)
- Como usar o ID/token retornado no próximo passo

Cenários mínimos obrigatórios:

- Criar gate → validar criação → buscar por ID
- Criar atendimento via gate → validar payload → buscar no relatório
- Fluxo de autenticação completo (login → uso do token → refresh)
- Cenário de erro esperado (400, 401, 404, 422) para cada endpoint crítico

### SEÇÃO 3: Validação de contrato de API

Para cada endpoint crítico, gere:

- Schema esperado (JSON Schema ou comparação de campos)
- curl para comparar resposta real vs contrato
- Como detectar campos ausentes, tipos incorretos, ou nulls inesperados

### SEÇÃO 4: Stress do Autopilot

Gere scripts de stress progressivo:

- Nível 1 (baseline): X atendimentos simultâneos
- Nível 2 (pico normal): 10x baseline
- Nível 3 (pico extremo): 50x baseline com variação de payload

Use: curl em paralelo com `&`, ou `ab` (Apache Bench), ou `k6` (se disponível).
Monitore: tempo de resposta, status 5xx, tamanho da fila.

### SEÇÃO 5: Seed de dados para relatórios

Script encadeado que:

1. Cria N gates
2. Cria M atendimentos por gate com dados variados
3. Executa ações (fechar, escalar, responder)
4. Valida o relatório final vs os dados inseridos

---

## FASE 4 — VALIDAÇÃO (autocrítica obrigatória)

Após gerar o documento, responda:

1. Quais cenários de edge case ficaram de fora e por quê?
2. Quais testes dependem de dados de produção e não podem rodar em staging?
3. Há algum endpoint onde o teste de contrato pode dar falso positivo?
4. O stress do Autopilot pode causar side effects (emails disparados, webhooks reais)?
5. Qual a ordem correta de execução dos scripts para não gerar inconsistência de dados?

Adicione ao documento uma seção "Riscos e Avisos" com estas respostas.

---

## FASE 5 — FORMATO DO DOCUMENTO FINAL

Entregue o documento no seguinte formato:

```
# Plano de Testes — [Nome do SaaS]
## Versão: 1.0 | Data: [data] | Cobertura existente: 80% (frontend + gateway)

### Índice
1. Setup e variáveis de ambiente
2. Testes de integração (curl)
3. Validação de contratos de API
4. Stress e performance (Autopilot)
5. Seed de dados para relatórios
6. Riscos e avisos
7. Checklist de execução
```

Para cada teste inclua:

- ID rastreável (ex: INT-001, CONTRACT-003, STRESS-002)
- Pré-condição (o que precisa existir antes)
- Comando executável (curl copiável e funcional)
- Critério de sucesso (o que valida que passou)
- Critério de falha (o que indica problema)

### Checklist de GO/NO-GO (ao final do documento):

- [ ] Todos os P0 executados sem erro
- [ ] Nenhum endpoint com divergência de contrato
- [ ] Autopilot estável até [X] atendimentos simultâneos
- [ ] Relatórios batem com seed data inserida
- [ ] Zero regressão nos 80% de cobertura existente

> **IMPORTANTE:** Todos os curls devem ser 100% executáveis no terminal,
> com variáveis substituídas ou documentadas. Nada genérico como
> "seu-token-aqui" sem instrução de como obtê-lo.
