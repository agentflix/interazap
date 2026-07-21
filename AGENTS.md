<!-- clovable:start -->
> Esta seção é mantida pelo Clovable em AGENTS.md. Edits manuais entre os marcadores serão sobrescritos no próximo start.

# Clovable

## Quem é o Clovable (identity)
Você está rodando dentro de **Clovable** — uma web UI local que envolve o Claude Code CLI. Mensagens do usuário chegam pelo chat panel da UI, não pelo terminal direto. Quando o usuário diz "abre X", "veja Y", "salve", ele se refere à UI, não a comandos shell.

## Capabilities da UI (o que o usuário vê)
- **Chat (panel esquerdo):** suas respostas em texto + cards de tool calls (Read/Write/Edit/Bash). Use markdown.
- **Files tab:** árvore do projeto + **editor Monaco**. User pode clicar em qualquer arquivo e editar manualmente. Arquivos modificados ganham badge "M" via file watcher real-time.
- **Preview tab:** iframe + botão "Start dev server" que roda `npm/pnpm/yarn/bun run <script>`. Detecta porta automaticamente.
- **Terminal tab:** PTY raw do Claude Code (modo fallback, raramente usado).

## MCP tools da Clovable (use direto, sem pedir ao user pra clicar)
Você tem 5 tools `mcp__clovable__*` disponíveis. Use-as ao invés de instruir o user a clicar:
- `clovable_show_preview({urlOrPath})` — abre a Preview tab. Aceita URL HTTP (`http://localhost:3000`) **ou** path relativo no projeto (`index.html`, `dist/index.html`). Clovable serve o path via HTTP automaticamente. **NUNCA passe `file://`** — browser bloqueia.
- `clovable_refresh_preview()` — força reload do iframe.
- `clovable_focus_tab({name})` — troca pra terminal/files/preview.
- `clovable_start_dev_server({script?})` — roda npm/pnpm/yarn/bun run <script>, foca Preview automaticamente. Sem arg, escolhe dev/start/serve.
- `clovable_notify({message, level?})` — toast info/warn/error pro user.

## Convenções de resposta
- Conciso. Chat panel é estreito.
- Markdown OK (negrito, listas, code fences).
- **Ação > explicação.** Quando user pede "crie/altere", use Write/Edit imediatamente em vez de descrever o que faria.
- `--permission-mode acceptEdits` está ativo — nunca peça aprovação pra escrever arquivo.
- Não entre em plan mode. Resposta direta + tool calls.
- Quando mencionar arquivos, use path completo (`packages/ui/src/App.tsx`) — facilita o user clicar.

## Pasta padrão: src/
**Todos os arquivos de projeto vivem em `src/`.** Convenção fixa do Clovable:
- Ao criar qualquer arquivo de projeto, crie dentro de `src/` (ex: `src/index.html`, `src/App.tsx`, `src/package.json`).
- Se o user mencionar um projeto existente, ele deve mover os arquivos para `src/` manualmente.
- **Na primeira mensagem de cada sessão**, sempre verifique: `Bash: ls src/ 2>/dev/null | head -5 || echo "__VAZIO__"`
  - Se retornar `__VAZIO__` ou pasta inexistente → chame `clovable_notify({message: "⚠️ A pasta src/ está vazia ou não existe. Mova o projeto para src/ ou peça para criar um novo projeto lá.", level: "warn"})`
  - Se tiver arquivos → continue normalmente.

## Workflow Lovable-style
**"Crie/build X":**
1. Crie arquivos com Write direto DENTRO de `src/`, na estrutura óbvia para a stack detectada. Ex: `src/index.html`, `src/package.json`, `src/App.tsx`.
2. Se houver `package.json` com script dev/start/serve: use `clovable_notify({message: "Projeto criado! Clique em Start na aba Preview para subir o servidor."})`. **NÃO chame `clovable_start_dev_server` automaticamente** — o usuário decide quando subir.
3. Se for HTML estático sem build: chame `clovable_show_preview({urlOrPath: "index.html"})` — Clovable serve via HTTP.
4. Itere baseado em feedback. Use `clovable_refresh_preview` após alterações que dev server não hot-reload.

## Subindo o projeto (workflow de "rodar" / "abrir preview")

Quando o user pedir para **"rodar"**, **"subir"**, **"abrir o app"**, **"mostrar no preview"**:

### Step 1 — diagnosticar o projeto
Use Read/Glob para entender o conteúdo de `src/`. Identifique a stack pelos sinais:
- `vite.config.*` ou dep `vite` → **Vite** (script comum: `dev`)
- `next.config.*` ou dep `next` → **Next.js** (script: `dev`)
- `astro.config.*` ou dep `astro` → **Astro** (script: `dev`)
- `nuxt.config.*` ou dep `nuxt` → **Nuxt** (script: `dev`)
- `svelte.config.*` → **SvelteKit** (script: `dev`)
- Só `index.html` na raiz, **sem** `package.json` → **HTML estático**, NÃO precisa dev server. Use `clovable_show_preview({urlOrPath: "index.html"})` direto.
- Dependências React/Vue/Svelte sem framework detectado → provavelmente Vite (`npm create vite@latest`).

### Step 2 — preparar
- Se `node_modules` não existe → `npm install` (ou `pnpm install` / `yarn` / `bun install` conforme o lockfile).
- Liste os scripts do `package.json` antes de escolher. Se nenhum é claramente dev/start/serve, **pergunte ao user**: "Qual script deve subir o projeto?".

### Step 3 — projeto vazio (greenfield, nada na raiz)
- Pergunte ao user qual stack quer (ex: "Vite + React?", "Next.js?", "HTML estático?").
- Crie a estrutura mínima dentro de `src/` com Write direto ou `npm create vite src/nome`. Faça install dentro de `src/`, depois Step 4.
- **Crie sempre um arquivo `START.md` na raiz** descrevendo como subir esse projeto:
  - Comando(s) de install
  - Comando dev/start/serve (o que entra em `clovable_start_dev_server`)
  - Porta esperada
  - Dependências externas (banco, .env, etc) se houver
  - Esse arquivo serve pra você (Claude) lembrar em sessões futuras e pro user consultar.

### Step 4 — subir
- Chame `clovable_start_dev_server({script: "<nome>"})`. Sem argumento ele escolhe `dev` → `start` → `serve` na ordem.
- **Prefira sempre essa tool sobre `Bash(npm run dev)`** — assim Clovable captura a URL do stdout e foca a Preview tab automaticamente.
- Em até ~5s o iframe carrega a URL real (Vite, Next, etc), mesmo em portas não-padrão (Vite fallback 5174+).
- Se demorar mais de 10s, leia o log: `Bash: curl "http://127.0.0.1:$CLOVABLE_PORT/api/dev/log?cwd=$PWD"` e diagnostique o erro.

### Step 5 — refresh / parar / trocar
- Edição não disparou hot-reload → `clovable_refresh_preview()`.
- Parar: `Bash: curl -X POST "http://127.0.0.1:$CLOVABLE_PORT/api/dev/stop" -H "Content-Type: application/json" -d "{\"cwd\": \"$PWD\"}"`.
- Trocar script: pare, depois `clovable_start_dev_server({script: "outro"})`.

**"Altere/refatore Y":**
1. Read antes de Edit.
2. Edit mínimo, sem cleanup colateral.
3. Resuma mudança em 1-2 linhas.

**"Não funcionou / quebrou":**
1. Read o arquivo mencionado.
2. Run lint/typecheck via Bash se útil.
3. Edit fix + explicar causa em 1 linha.
<!-- clovable:end -->

# InteraZap — AI-First + PREVC V7

**Stack:** Laravel 12 (`api/`) · NestJS 11 (`gateway/`) · Angular 20 (`app/`) · PostgreSQL 17 · Redis 7
**Arquitetura:** Microservices — Presentation → Domain/Application → Execution/Realtime → Infrastructure
**Integração:** api → gateway por Redis Streams · gateway → api por HTTP `/internal` · app → gateway por WebSocket `/ws`

## 🧭 Agent padrão

**ORCHESTRATOR é o ponto de entrada de toda sessão** (`agent: ORCHESTRATOR` em `.claude/settings.json`).
Classifica o escopo e delega — nunca implementa.

| Escopo | Rota |
|---|---|
| Ideia, feature, decisão de arquitetura | PLANNER — `/prevec-decompose-plan` |
| Task T.A.C.E já definida | BUILDER — `/prevec-execute-task` |
| 1–2 arquivos, um bounded context, sem arquivo novo | VIBE-CODER |
| Fase implementada aguardando fechamento | REVIEWER — `/prevec-phase-close` |

> 3+ arquivos, múltiplos bounded contexts ou criação de arquivo → sempre planejamento formal.
> VIBE-CODER redireciona sozinho para `/prevec-decompose-plan` ao detectar task complexa.

## 🚫 Regras de Arquitetura

- Gateway nunca acessa PostgreSQL — todo dado passa pela api
- Migrations só em `api/database/migrations` via `php artisan make:migration`
- BullMQ só em `gateway/`; jobs Horizon só em `api/src/Domain/*/Jobs`
- Secrets do AWS Secrets Manager só em `gateway/`
- Frontend nunca acessa banco, Redis ou provedor de LLM diretamente
- Inferência conversacional vai pelo gateway — exceções: `AiMediaTranscriptionService` e `AiGuardianService` chamam OpenAI direto
- Isolamento por tenant obrigatório em toda leitura e escrita
- Business logic em Action · validação em FormRequest · Controller só orquestra
- PSR-12 (Pint) em PHP · Angular Style Guide em TS · zero `any` · Conventional Commits em português

## 📐 Regras de Negócio

- Janela de 24h do WhatsApp define resposta livre vs. template aprovado
- Humano assumiu o ticket durante um run → resposta da IA é bloqueada
- Cobrança por mensagem de IA: cota mensal por aniversário + modo `stop|overage`
- Webhook de canal é idempotente — reprocessar o mesmo evento não duplica mensagem
- Webhooks: uazapi e Telegram entram na api; Meta WABA entra no gateway

## ⚙️ Regras de Processo

- Sem testes por task — rodam no `/prevec-phase-close` ao fim de cada fase
- Fase não fecha com gate vermelho; última fase exige `cd api && composer gate:all`
- Última fase roda `code-review-confiavel` (7 subagents) em subagent distinto do BUILDER
- Todo agent termina mostrando o próximo comando com argumentos reais
- Frontend: consultar `.context/DESIGN/` antes de qualquer componente ou página — obrigatório
- Antes de escrever código: ler o canônico do padrão em `.context/ARCHITECTURE/canonicals.md`

## 🗂️ Contexto

| Path | Conteúdo |
|---|---|
| `.context/ARCHITECTURE/context-snapshot.md` | **Leia primeiro** — cache lean de stack, regras e dependências |
| `.context/ARCHITECTURE/canonicals.md` | Arquivo canônico de cada padrão nas 3 camadas |
| `.context/ARCHITECTURE/` | architecture · modules · dependencies · project-brain · user-flow |
| `.context/WORKFLOW/` | PREVC.md · validation-flow.md (gates reais por camada) |
| `.context/DESIGN/` | Wireframes, specs de UI, fluxos visuais |
| `.context/DOCS/` | PRDS · FEATURES · TASKS · MEMORY |
| `.context/agents/` · `.context/skills/` | 4 agents + 6 subagents · skills PREVEC |
| `.context/.session/` | Session file por feature — troca de contexto entre agents (gitignored) |

## 🗺️ Mapa de Diretórios

**`api/src/Domain/{Contexto}/`** — mesmo esqueleto em todos: `Actions/` (business logic) · `Http/Controllers|Requests|Resources/` · `Models/` · `DTOs/` · `Jobs/` (Horizon) · `Routes/` · `Policies/`
Contextos: Ai · Auth · Billing · Chat · Configuration · CRM · Dashboard · Gateway (client Redis Streams) · Platform · Reports · Shared
Migrations: `api/database/migrations/` — nunca fora daqui.

**`gateway/src/domains/{contexto}/`** — `controllers/` · `services/` · `dto/` · `consumers|processors/`
Contextos: ai · billing · chat · internal · realtime · webhooks
Infra: `shared/services/queue/` (BullMQ, retry, DLQ) · `core/config/` · `metrics/` (Prometheus)

**`app/src/app/`** — `pages/{domain}/` · `core/models|services|guards/` · `shared/`
Áreas: admin · ai · auth · billing · chat · configuration · crm · dashboard · platform · public · reports · settings · ui-kit · webchat

## 📖 Glossário de Aliases

| Termo de negócio | api | app |
|---|---|---|
| Inquilino / Tenant | `PlatformTenant` (`Domain/Platform`) | `Company` (`core/models/company.model.ts`) |
| Seguimento / Segment | `AiPromptSegment` (`Domain/Ai`) | `SegmentPrompt` (`pages/ai/models/ai.model.ts`) |
| Plano | `PlatformPlan` (`Domain/Platform`) | `PlatformPlan` (`pages/platform/models`) |

## 🤖 Agents

| Agent | Fase PREVC | Modelo | Papel |
|---|---|---|---|
| ORCHESTRATOR | Todas | Sonnet | Ponto de entrada — classifica e delega |
| PLANNER | Planning | Sonnet | BRANDING + PM + ARCHITECT + DESIGNER |
| BUILDER | Execution | Sonnet | Router: `builder-explore` (Haiku, read-only) → `builder-write` (Sonnet) → `builder-debug` (Opus) |
| REVIEWER | Review + Confirm | Sonnet | Router: `reviewer-doc` (Haiku) · `reviewer-code` (Sonnet, 7 subagents) · `reviewer-confirm` (Haiku) |

> Routers não executam nada diretamente. `builder-debug` só entra depois que `builder-write` falhou
> ou quando o bug é multifatorial.

## 🔄 Workflow PREVC

```
/prevec-decompose-plan [ideia|prd]     ← PLANNER: PRD → feature doc → tasks T.A.C.E por fase
  → reviewer-doc valida as tasks
    → /prevec-execute-task [feature] TASK-X.Y.Z    ← por task, sem testes
      → /prevec-phase-close [feature] [N]          ← por fase: testes + 1 commit
        → última fase: 7 subagents + composer gate:all + fix loop + PR
```

Cada task é **T.A.C.E**: Tarefa · Arquivo · Comportamento (antes → depois) · Evidência verificável.
`/prevec-new-plan`, `/prevec-decompose-task`, `/prevec-review-execution` e `/prevec-finalize-execution`
seguem disponíveis para etapas isoladas fora do fluxo de fases.

## ✅ Gates

| Camada | Durante a fase | Fechamento |
|---|---|---|
| api | `cd api && composer gate:fast` | `cd api && composer gate:all` |
| gateway | `pnpm --filter gateway test` | `+ pnpm --filter gateway build` |
| app | `pnpm --filter app test:run` | `+ pnpm --filter app build` |

> `pnpm --filter app test` entra em watch — em automação use sempre `test:run`.
> Detalhe completo em `.context/WORKFLOW/validation-flow.md`.

## ❌ Anti-patterns

**api** — business logic em Controller · validação em Action · reaproveitar migration existente · query sem filtro de tenant · queue processor de BullMQ fora do gateway
**gateway** — adicionar driver de banco (`pg`, `typeorm`, `prisma`, `knex`) · persistir estado fora da api · chamar LLM fora de `ai-provider.factory`
**app** — acessar api fora de um service HTTP · duplicar interface em vez do model centralizado · componente sem consultar `.context/DESIGN/` · usar `any`
**agents** — implementar 3+ arquivos sem passar pelo ORCHESTRATOR · `builder-debug` sem tentar `builder-write` · fechar fase com gate vermelho · ler o session file em pedaços
