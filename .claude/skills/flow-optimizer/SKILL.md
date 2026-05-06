# Skill: Flow Optimizer

> Skill global de otimização de fluxo para acelerar execução técnica no monorepo InteraZap com segurança balanceada.

## Objetivo

Reduzir tempo até resultado útil com um roteiro padronizado de:
- descoberta rápida de contexto
- busca inteligente com `rg`
- mapeamento de impacto
- seleção de testes por fatia
- checkpoints mínimos de segurança
- fechamento com evidências

## Quando Usar

Use em qualquer task técnica que envolva código em `api/`, `gateway/`, `app/`, `electron/`:
- bugfix
- refactor
- feature incremental
- investigação técnica

## Perfil de Rigor

Padrão: **balanceado**.
- Velocidade com validação incremental
- Sem pular checkpoints críticos (tenant scope, contrato, regressão)

## Entradas

- Tipo: `bug` | `refactor` | `feature` | `investigation`
- Escopo: `api` | `gateway` | `app` | `electron` | `cross`
- Resultado esperado (1 frase)

## Artefatos de Contexto (cache operacional)

Salvar durante a execução em `.claude/agent-memory/flow-optimizer/`:
- `context-map.md`: arquivos e símbolos mais relevantes
- `impact-map.md`: superfícies impactadas por camada
- `test-slice.md`: testes focais e sequência
- `assumptions.md`: suposições adotadas

## Fluxo de Execução

### 1) Intake + Lock de Escopo
- Definir tipo da task e workspace principal
- Trancar escopo para evitar expansão não solicitada

### 2) Smart Search (rápido e dirigido)
- Símbolos/fluxo:
```bash
rg -n "<symbol|field|endpoint|event>" api/src gateway/src app/src electron/
```
- Arquivos-chave por stack:
```bash
rg --files api/src | rg "Controller|Request|Action|Service|Resource|Model"
rg --files gateway/src | rg "controller|service|module|dto|spec"
rg --files app/src | rg "service|component|spec|model"
```
- Testes relacionados:
```bash
rg --files api/tests app/src gateway/src | rg "(Test\.php|\.spec\.ts)$"
```

### 3) Impact Map
- Mapear mudanças por comportamento, não por arquivo isolado
- Confirmar contratos cruzados (REST, Streams, WebSocket)
- Confirmar regras críticas do domínio (ex.: multi-tenant)

### 4) Test Slice (focal -> contexto -> amplo)
1. Teste focal do ponto alterado
2. Testes do bounded context
3. Gate mais amplo (apenas antes de concluir)

Comandos base:
```bash
# API
cd api && php artisan test <teste-focal>

# Gateway
pnpm --filter gateway test

# App
cd app && pnpm test:run
```

### 5) Execution Guardrails
- Não avançar sem validar o happy path da correção
- Se houver falha fora do escopo, registrar como baseline preexistente
- Manter consistência de contrato canônico em todos os pontos tocados

### 6) Close Loop
- Evidência objetiva do que passou/falhou
- Atualizações PREVC quando a task for concluída:
  - CHANGELOG
  - MEMORY
  - `project-state.yaml`

## Heurísticas de Produtividade

- Preferir `rg` com padrão específico antes de abrir arquivos longos
- Ler 2-3 fontes de verdade primeiro (request/resource/model ou dto/service/controller)
- Aplicar mudanças em fatias verticais curtas (backend + consumo + teste)
- Evitar suíte completa cedo; rodar só após fatia focal estável

## Anti-padrões

- Começar editando sem mapa mínimo de impacto
- Rodar suíte completa antes do teste focal
- Corrigir “por arquivo” sem validar contrato ponta a ponta
- Expandir escopo sem alinhamento

## Checklist Rápido

- [ ] Escopo travado
- [ ] Contexto mínimo mapeado
- [ ] Contratos confirmados
- [ ] Test slice executado na ordem
- [ ] Evidências coletadas
- [ ] PREVC atualizado (quando aplicável)
