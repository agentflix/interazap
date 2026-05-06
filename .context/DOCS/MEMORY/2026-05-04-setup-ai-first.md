# Memory: Setup da Estrutura AI-First com PREVC

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-04 |
| **Autor** | Setup automático |
| **Contexto** | Configuração inicial do projeto InteraZap para desenvolvimento com IA |
| **Tags** | setup, estrutura, prevc, ai-first, monorepo |

---

## Situação

O projeto InteraZap (monorepo Laravel 12 + NestJS 11 + Angular 19 + Electron 33) precisava de uma estrutura que permitisse desenvolvimento eficiente com IA, considerando:

- 4 stacks distintas (PHP, NestJS, Angular web/mobile, Electron desktop)
- 11 bounded contexts no backend (DDD)
- Multi-tenancy crítico
- Integrações externas sensíveis (OpenAI, Asaas, UazAPI, Z-API)
- Necessidade de manter qualidade alta (PHPStan L6, Pest, Vitest)

---

## Decisão

Adotar estrutura AI-First com:

- **AGENTS.md** como fonte da verdade (symlink via CLAUDE.md)
- **PREVC** como workflow obrigatório (Planning → Review → Execution → Validation → Confirm)
- **T.A.C.E** como framework de decomposição de tarefas
- **Agents especializados** gerados para cada stack:
  - BACKEND (Laravel 12 / PHP 8.3 / DDD)
  - GATEWAY (NestJS 11 / TS 5.7)
  - FRONTEND (Angular 19 / Ionic / Capacitor / Electron)
  - DBA (PostgreSQL 17 / pgvector / Redis 7)
  - QA, DEBUG, REVIEWER, ARCHITECT, PM, DOC, GIT_COMMIT, DESIGNER, ORCHESTRATOR
- **Hook** com router.js que detecta keywords da stack (laravel, nestjs, angular, ionic, capacitor, whatsapp, uazapi, z-api, etc.) para roteamento automático
- **CHANGELOG** diário para registro factual
- **MEMORY** para decisões e aprendizados persistentes

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Um único agent generalista | Perderia especialização por stack — Laravel, NestJS e Angular têm convenções muito diferentes |
| Estrutura sem PREVC | Falta de gates levaria a regressões em multi-tenancy e integrações |
| Hook em Python | Node.js já está disponível em todos workspaces (pnpm) |

---

## Consequências

### Positivas
- IA sempre tem contexto adequado via AGENTS.md
- Tasks nunca são vagas (T.A.C.E garante especificidade)
- Qualidade garantida via gates (composer gate:all, pnpm test, pnpm lint)
- Conhecimento não se perde (MEMORY)
- Histórico rastreável (CHANGELOG)
- Roteamento automático para o agent correto via keywords da stack

### Trade-offs
- Setup inicial requer investimento de tempo
- Disciplina necessária para manter docs atualizados
- Overhead de processo para mudanças muito pequenas
- Cada nova bounded context requer atualização de modules.yaml + dependencies.yaml
