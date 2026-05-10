# Relatorio de Auditoria Tecnica

**Data:** 2026-05-10  
**Repositorio:** InteraZap  
**Escopo:** API Laravel, Gateway NestJS, App Angular/Ionic, banco, filas, realtime, configuracao e testes.  
**Modo:** Diagnostico somente. Nenhuma correcao foi aplicada durante a auditoria.

---

## 1. Resumo executivo

A estrutura geral do projeto e madura, com separacao clara entre `api/`, `gateway/`, `app/`, `electron/`, `infra/` e `observability/`. O principal risco encontrado esta em contratos quebrados entre frontend, API e Gateway: o App chama endpoints internos ou inexistentes do Gateway, alguns fluxos administrativos apontam para rotas que nao existem na API e um webhook importante de templates Meta dispara evento sem listener registrado.

Foram encontrados 23 achados relevantes nesta passada: 7 altos, 10 medios e 6 baixos/informativos. A prioridade de correcao deve ser: contratos App/API/Gateway, listener Meta, migrations/PHPStan, exemplos de `.env`, testes do Gateway e limpeza de arquivos/rotas suspeitas.

Comandos executados com resultado relevante:

- `pnpm --dir gateway exec tsc -p tsconfig.build.json --noEmit`: passou.
- `pnpm --dir app exec tsc -p tsconfig.app.json --noEmit`: passou.
- `pnpm --dir app exec ng build --configuration production`: passou.
- `cd api && composer analyse -- --no-progress`: falhou com 175 erros.
- `pnpm --dir gateway exec tsc --noEmit`: falhou por divergencias em specs.
- `php artisan route:list`, `php artisan list --raw` e `php artisan schedule:list`: usados para mapear rotas, comandos e agendamentos.

---

## 2. Mapa do projeto

| Area | Caminho | Responsabilidade observada |
|---|---|---|
| API | `api/` | Laravel 12, DDD em `api/src/Domain/*`, rotas em `routes/api.php` e por contexto |
| Gateway | `gateway/` | NestJS, webhooks, realtime, integracoes, Redis Streams, health, filas |
| App | `app/` | Angular/Ionic, rotas de auth, CRM, Chat, Billing, Settings, Admin Monitoring, Reports |
| Electron | `electron/` | Desktop Angular/Electron, com bundle gerado em `electron/app/browser` |
| Landing | `landing/` | Site marketing |
| Infra | `infra/` | Ansible/nginx |
| Observability | `observability/` | Prometheus/Grafana |

### Principais pontos de entrada

- API HTTP: `api/routes/api.php`.
- Rotas Chat API: `api/src/Domain/Chat/Routes/chat.php`.
- Rotas Platform API: `api/src/Domain/Platform/Routes/platform.php`.
- Gateway HTTP/WebSocket: `gateway/src/main.ts`, `gateway/src/app.module.ts`.
- App Angular: `app/src/app/app.routes.ts`.
- Commands Laravel: registrados em `api/bootstrap/app.php`.

### Rotas e comandos relevantes

- API expoe `/api/chat/*`, `/api/chat/message-templates`, `/api/chat/tickets/{ticketId}/messages/template`, `/api/webchat/*`, `/api/health/queues/*`.
- Gateway expoe `channels/:id/templates`, `admin/queues`, `health/circuits`, mas essas rotas sao protegidas por `InternalApiKeyGuard`.
- Comandos custom encontrados: `ai:*`, `billing:*`, `chat:*`, `queue:health`, `streams:*`, `tenant:bootstrap-defaults`.
- Schedule Laravel ativo: comandos de IA por minuto, billing recorrente, `chat:close-inactive-tickets` a cada 5 minutos e limpeza diaria de audit logs.

### Testes existentes

- Backend: Pest em `api/tests`.
- Gateway: Jest specs em `gateway/src/**/*.spec.ts`.
- App: Vitest/Angular specs em `app/src/**/*.spec.ts`.

---

## 3. Achados criticos

| Severidade | Categoria | Arquivo | Evidencia | Impacto | Recomendacao |
|---|---|---|---|---|---|
| Alto | Frontend x Gateway | `app/src/app/shared/components/template-selector/template-selector.ts:184` | App chama Gateway interno sem `x-api-key`; Gateway exige `InternalApiKeyGuard` | Selector de templates falha com 401 ou resposta vazia | Usar API Laravel autenticada ou proxy server-side |
| Alto | Frontend x Gateway | `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts:326` | App chama `/channels/:id/send-template`; rota nao existe no Gateway | Nova conversa por template falha | Reaproveitar `/api/chat/tickets/{ticketId}/messages/template` ou criar action backend |
| Alto | Eventos/Webhooks | `api/app/Providers/EventServiceProvider.php:63` | Listener `UpdateMetaTemplateStatusListener` existe, evento e disparado, mas nao registrado | Status de template Meta nao sincroniza | Registrar listener e testar webhook |
| Alto | Banco/API | `api/database/migrations/2026_01_01_000020_create_crm_base_tables.php:20` | PHPStan acusa `ForeignKeyDefinition::comment()` inexistente em varias migrations | Gate falha; fresh install pode quebrar | Mover `comment()` antes de `constrained()` |
| Alto | Admin/Observabilidade | `app/src/app/core/services/queue.service.ts:138` | App usa `/api/admin/queues/*`; API so expoe `/api/health/queues/*` | Painel admin parcialmente quebrado | Criar endpoints Laravel autenticados ou ajustar UI |

---

## 4. Achados por categoria

### Flags e variaveis

#### [MEDIO] `.env.example` e README divergentes do codigo

- Categoria: Flags e variaveis
- Status: Confirmado
- Arquivo(s): `api/.env.example:19`, `api/.env.example:152`, `gateway/README.md:64`, `gateway/src/core/config/configuration.ts:83`
- Evidencia: `QUEUE_CONNECTION` aparece duplicado no API example; README documenta `UAZAPI_API_KEY`, mas o codigo usa `UAZAPI_ADMIN_TOKEN`; README cita `OPENAI_MODEL`, mas o codigo prefere `OPENAI_DEFAULT_MODEL`.
- Por que e um problema: ambientes podem ser provisionados com variavel errada ou fila em driver inesperado.
- Impacto: deploy instavel, integracao Uazapi sem token, comportamento diferente entre dev/prod.
- Como validar: comparar `rg "process.env|env\\("` com `.env.example` e README.
- Sugestao de correcao: consolidar exemplos, separar required/optional/deprecated e remover duplicidade de `QUEUE_CONNECTION`.
- Risco da correcao: Baixo.

#### [MEDIO] `env()` usado fora de config

- Categoria: Configuracao
- Status: Confirmado
- Arquivo(s): `api/src/Domain/Ai/Console/Commands/DetectStaleRunsCommand.php:37`
- Evidencia: `DetectStaleRunsCommand` usa `env('AI_STALE_RUN_THRESHOLD_MINUTES', ...)` dentro do `handle()`.
- Por que e um problema: com `config:cache`, uso direto de `env()` fora de config pode retornar valor inesperado.
- Impacto: deteccao de runs stale pode usar threshold errado em producao.
- Como validar: rodar PHPStan/Larastan ou `php artisan config:cache` e executar o comando.
- Sugestao de correcao: mover para `config/ai.php` e ler via `config()`.
- Risco da correcao: Baixo.

#### [MEDIO] Flag `chat_rewrite_v1` parece sem efeito real

- Categoria: Feature flag / Codigo morto
- Status: Provavel
- Arquivo(s): `app/src/app/pages/chat/store/chat-realtime.adapter.ts:24`, `app/src/app/core/services/chat-rewrite-rollout.service.ts:25`
- Evidencia: `ChatRealtimeAdapter` e `providedIn: 'root'`, mas busca textual encontrou uso apenas na propria classe e nos specs.
- Por que e um problema: servicos Angular `providedIn: 'root'` nao sao instanciados se ninguem injeta; a flag pode controlar codigo que nunca roda.
- Impacto: rollout realtime/batching/dedup pode estar morto em producao.
- Como validar: instrumentar inicializacao da tela Chat e confirmar se o constructor do adapter executa.
- Sugestao de correcao: injetar o adapter no bootstrap da pagina/store de chat ou remover a flag se o rollout foi abandonado.
- Risco da correcao: Medio.

### Codigo morto

#### [BAIXO] Codigo deprecated/legado ainda presente

- Categoria: Codigo morto
- Status: Suspeito/Provavel
- Arquivo(s): `api/src/Domain/Chat/Services/ProviderResolver.php`, DTOs deprecated no Gateway
- Evidencia: `ProviderResolver` esta marcado como deprecated e sem referencias produtivas encontradas; alguns DTOs deprecated ainda sao usados em specs/normalizers.
- Por que e um problema: aumenta ruido e risco de manutencao.
- Impacto: refactors ficam mais inseguros, buscas retornam caminhos antigos.
- Como validar: executar busca por referencias, verificar container/service providers e testes.
- Sugestao de correcao: abrir task dedicada para remocao controlada com cobertura.
- Risco da correcao: Medio, por possivel uso dinamico.

#### [BAIXO] Arquivos backup/lockfile orfaos versionados

- Categoria: Arquivos orfaos
- Status: Confirmado
- Arquivo(s): `api/.env.bak`, `app/src/app/core/icons/lucide-icon-registry.ts.bak`, `package-lock.json`, `gateway/package-lock.json`
- Evidencia: `git ls-files` lista esses arquivos.
- Por que e um problema: `.env.bak` pode conter segredo; lockfiles npm competem com pnpm.
- Impacto: risco de vazamento e drift de instalacao.
- Como validar: verificar conteudo sem expor segredo e confirmar politica de lockfile.
- Sugestao de correcao: remover com autorizacao e ajustar `.gitignore`.
- Risco da correcao: Baixo, exceto se algum pipeline depender dos lockfiles.

### Logica de negocio

#### [ALTO] Nova conversa por template usa rota inexistente

- Categoria: Logica de negocio / Frontend x Backend
- Status: Confirmado
- Arquivo(s): `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts:326`, `api/src/Domain/Chat/Routes/chat.php:80`
- Evidencia: o App chama `POST /channels/{id}/send-template`; busca no Gateway nao encontrou rota correspondente. A API possui `POST /api/chat/tickets/{ticketId}/messages/template`.
- Por que e um problema: o fluxo de iniciar conversa com template nao possui backend real no caminho chamado.
- Impacto: falha de UX e perda de capacidade de abrir conversas ativas via template.
- Como validar: iniciar nova conversa em modo template e observar 404/401.
- Sugestao de correcao: criar/reusar ticket pela API e enviar template pelo endpoint Laravel existente.
- Risco da correcao: Medio.

#### [MEDIO] Contrato de `ChatChannelConnector::connect()` diverge para Telegram

- Categoria: Contrato interno
- Status: Confirmado por analise estatica
- Arquivo(s): `api/src/Domain/Chat/Services/ChatChannelConnector.php:45`
- Evidencia: PHPDoc promete shape com `mode`, `qr_code`, `pair_code`, `expires_at`, `provider`; ramo Telegram retorna outro shape.
- Por que e um problema: consumidores podem assumir campos inexistentes.
- Impacto: UI ou integrations podem quebrar em conexao Telegram.
- Como validar: chamar connect para instancia Telegram e comparar resposta com contrato documentado.
- Sugestao de correcao: criar DTO/union explicita ou normalizar resposta multi-provedor.
- Risco da correcao: Medio.

### Frontend x Backend

#### [ALTO] Selector de templates chama endpoint interno e contrato errado

- Categoria: Frontend x Backend
- Status: Confirmado
- Arquivo(s): `app/src/app/shared/components/template-selector/template-selector.ts:184`, `gateway/src/domains/chat/channels.controller.ts:28`, `gateway/src/domains/realtime/guards/internal-api-key.guard.ts:31`
- Evidencia: Angular faz `GET ${environment.gateway.url}/channels/${channelId}/templates`; Gateway aplica `@UseGuards(InternalApiKeyGuard)` e retorna `MetaTemplate[]`, enquanto o App espera `{ data: [] }`.
- Por que e um problema: browser nao deve carregar `INTERNAL_API_KEY`; mesmo com acesso, o response shape nao bate.
- Impacto: templates aprovados nao carregam ou caem em empty state.
- Como validar: abrir selector com canal Meta e observar 401 ou lista vazia.
- Sugestao de correcao: expor via Laravel `/api/chat/message-templates` com auth/tenant ou proxy server-side.
- Risco da correcao: Medio.

#### [ALTO] Painel admin de filas aponta para rotas inexistentes ou internas

- Categoria: Frontend x Backend / Seguranca
- Status: Confirmado
- Arquivo(s): `app/src/app/core/services/queue.service.ts:138`, `api/src/Domain/Platform/Routes/platform.php:22`, `gateway/src/health/queue-dashboard.controller.ts:34`
- Evidencia: App usa `/api/admin/queues/*`; API expoe apenas `/api/health/queues/*`; Gateway tem `/admin/queues`, mas com `InternalApiKeyGuard`.
- Por que e um problema: endpoints administrativos nao existem no caminho consumido pelo App e fallback tenta rota interna do Gateway.
- Impacto: pause/resume/clean/DLQ/circuit breakers falham.
- Como validar: acessar tela admin de filas e acionar operacoes.
- Sugestao de correcao: criar controller Laravel autenticado/autorizado que faca proxy server-side para Gateway.
- Risco da correcao: Medio.

### Banco de dados

#### [ALTO] Migrations usam `comment()` depois de `constrained()`

- Categoria: Banco de dados
- Status: Confirmado no PHPStan; provavel em runtime
- Arquivo(s): `api/database/migrations/2026_01_01_000020_create_crm_base_tables.php:20` e varias migrations CRM
- Evidencia: `composer analyse -- --no-progress` reporta `Call to an undefined method Illuminate\\Database\\Schema\\ForeignKeyDefinition::comment()`.
- Por que e um problema: `constrained()` muda o builder para definicao de foreign key; `comment()` pertence a coluna.
- Impacto: gate falha; ambiente novo pode falhar ao migrar.
- Como validar: rodar PHPStan e/ou `php artisan migrate:fresh` em banco descartavel.
- Sugestao de correcao: trocar para `foreignUuid(...)->comment(...)->constrained(...)`.
- Risco da correcao: Baixo.

#### [MEDIO] `tenant_id` nullable em tabelas sensiveis

- Categoria: Banco de dados / Multi-tenant
- Status: Suspeito
- Arquivo(s): `api/database/migrations/2026_01_01_000020_create_crm_base_tables.php:170`, `api/database/migrations/2026_03_29_000001_add_tenant_id_to_ai_autopilot_approvals_table.php:24`
- Evidencia: `crm_contact_tags.tenant_id` e `ai_autopilot_approvals.tenant_id` sao nullable.
- Por que e um problema: a regra do projeto exige isolamento por tenant sem excecoes nao documentadas.
- Impacto: risco de registros sem escopo forte de tenant.
- Como validar: verificar dados existentes e models/queries dessas tabelas.
- Sugestao de correcao: backfill e `SET NOT NULL`, ou documentar excecao real.
- Risco da correcao: Alto se houver dados legados invalidos.

### Testes/E2E

#### [MEDIO] Specs do Gateway divergiram dos tipos atuais

- Categoria: Testes
- Status: Confirmado
- Arquivo(s): varios specs em `gateway/src`
- Evidencia: `pnpm --dir gateway exec tsc --noEmit` falhou em specs com mocks sem campos obrigatorios, DTO antigo `waitQrCode`, `handshake` readonly e variavel `databaseService` inexistente.
- Por que e um problema: build de producao passa, mas os testes nao acompanham contratos TypeScript atuais.
- Impacto: regressao de contrato pode ficar escondida.
- Como validar: rodar `pnpm --dir gateway exec tsc --noEmit`.
- Sugestao de correcao: criar tsconfig/spec typecheck ou ajustar specs para tipos atuais.
- Risco da correcao: Medio.

#### [BAIXO] Testes com `dump()` e skip permanente

- Categoria: Testes
- Status: Confirmado
- Arquivo(s): `api/tests/Feature/Domain/CRM/CRMNegotiationN+1Test.php:100`, `api/tests/Feature/Domain/CRM/CRMContactN+1Test.php:81`, `api/tests/Feature/Platform/QueueHealthControllerTest.php:79`
- Evidencia: testes N+1 usam `dump()`; teste de rate limit esta com `skip`.
- Por que e um problema: CI ruidoso e cobertura incompleta.
- Impacto: baixa confiabilidade do feedback de teste.
- Como validar: rodar suite Pest e observar output.
- Sugestao de correcao: remover dumps ou condicionar a debug; substituir skip por setup configuravel.
- Risco da correcao: Baixo.

### Logs

#### [BAIXO] Health/admin observability pode carecer de trilha auditavel

- Categoria: Logs/Observabilidade
- Status: Suspeito
- Arquivo(s): `app/src/app/core/services/queue.service.ts`, `gateway/src/health/queue-dashboard.controller.ts`
- Evidencia: operacoes administrativas de fila existem no Gateway, mas o App aponta para API inexistente; nao foi identificado fluxo auditavel API-side para pause/resume/clean/DLQ.
- Por que e um problema: operacoes administrativas deveriam ter auth, auditoria e contexto de usuario.
- Impacto: dificuldade de rastrear acoes em incidentes.
- Como validar: revisar logs ao executar operacoes no Gateway e API.
- Sugestao de correcao: centralizar operacoes no Laravel com audit log.
- Risco da correcao: Medio.

### Seguranca

#### [MEDIO] CORS wildcard com credenciais no WebSocket Telegram

- Categoria: Seguranca
- Status: Confirmado
- Arquivo(s): `gateway/src/bot/gateways/telegram-websocket.gateway.ts:51`
- Evidencia: `cors: { origin: '*', credentials: true }`.
- Por que e um problema: amplia superficie de conexao cross-origin; JWT mitiga, mas origem nao e restringida.
- Impacto: maior exposicao de endpoint realtime.
- Como validar: tentar conexao Socket.IO de origem nao confiavel com token valido.
- Sugestao de correcao: usar allowlist de origens via config.
- Risco da correcao: Baixo/Medio.

#### [BAIXO] Arquivo `.env.bak` versionado

- Categoria: Seguranca
- Status: Confirmado como arquivo versionado; conteudo nao inspecionado no relatorio para evitar expor segredo
- Arquivo(s): `api/.env.bak`
- Evidencia: `git ls-files api/.env.bak`.
- Por que e um problema: backups de env frequentemente contem segredos.
- Impacto: risco de vazamento se houver segredo real.
- Como validar: revisar localmente o conteudo e historico Git.
- Sugestao de correcao: remover com autorizacao, rotacionar qualquer segredo exposto e atualizar `.gitignore`.
- Risco da correcao: Baixo.

### Performance

#### [BAIXO] Testes N+1 existem, mas com dumps ruidosos

- Categoria: Performance/Testes
- Status: Confirmado
- Arquivo(s): `api/tests/Feature/Domain/CRM/CRMNegotiationN+1Test.php`, `api/tests/Feature/Domain/CRM/CRMContactN+1Test.php`
- Evidencia: testes medem query count, mas despejam queries via `dump()`.
- Por que e um problema: dificulta leitura de CI e pode ocultar falhas reais em ruido.
- Impacto: manutencao pior de testes de performance.
- Como validar: executar testes CRM.
- Sugestao de correcao: usar mensagens de assert ou log condicional.
- Risco da correcao: Baixo.

### Arquitetura

#### [ALTO] Browser esta acoplado a endpoints internos do Gateway

- Categoria: Arquitetura
- Status: Confirmado
- Arquivo(s): `app/src/app/shared/components/template-selector/template-selector.ts:184`, `app/src/app/pages/chat/components/new-conversation-modal/new-conversation-modal.ts:326`, `app/src/app/core/services/queue.service.ts:343`
- Evidencia: App chama `environment.gateway.url` para operacoes que exigem `InternalApiKeyGuard` ou nao existem.
- Por que e um problema: quebra fronteira API <-> Gateway. Gateway deveria ser interno para operacoes administrativas/sensiveis; browser deve passar por API autenticada.
- Impacto: falhas funcionais, risco de expor chaves internas e divergencia de tenant/auth.
- Como validar: buscar `environment.gateway.url` e classificar uso por publico vs interno.
- Sugestao de correcao: definir contrato oficial: App -> API Laravel; API -> Gateway via secret/streams.
- Risco da correcao: Medio/Alto por mexer em fluxos de chat/admin.

---

## 5. Itens suspeitos

- `chat:auto-close` existe como comando, mas o schedule usa `chat:close-inactive-tickets`; pode ser legado ou fluxo alternativo.
- Eventos sem listener explicito com discovery desligado: varios podem ser broadcast/subscriber, mas `MetaTemplateStatusUpdated` e problema confirmado.
- Health de filas em `/api/health/queues/*` e publico com throttle; pode ser intencional para monitoramento, mas expoe nomes/tamanhos de filas.
- `electron/app/browser/*` parece bundle gerado; se versionado, vale decidir politica de artefato.
- Variaveis usadas no Gateway e ausentes do `.env.example` sao numerosas; algumas tem fallback legitimo, outras podem ser criticas de deploy.

---

## 6. Falsos positivos provaveis

- Classes Laravel invocadas por container, policies, events e commands podem parecer nao chamadas em busca textual.
- Controllers do Gateway protegidos por `InternalApiKeyGuard` podem ser consumidos apenas server-to-server.
- Migrations backup em `api/database/migrations_backup_20260216` parecem arquivo historico, nao migration carregada.
- DTOs deprecated podem existir para compatibilidade de payload antigo.

---

## 7. Plano de correcao

### Fase 1 - Correcoes criticas e seguras

1. Registrar `MetaTemplateStatusUpdated` no `EventServiceProvider`.
2. Corrigir selector/template send para passar por Laravel autenticado.
3. Corrigir documentacao/env: `UAZAPI_ADMIN_TOKEN`, `OPENAI_DEFAULT_MODEL`, `QUEUE_CONNECTION`.
4. Remover `env()` runtime do comando de stale AI runs.

### Fase 2 - Correcoes estruturais

1. Corrigir migrations `comment()->constrained()` e rodar PHPStan.
2. Implementar/proxyar endpoints admin de filas com autenticacao e autorizacao.
3. Decidir contrato unico para conexao Telegram/Uazapi.
4. Fazer backfill e `NOT NULL` em `tenant_id` onde aplicavel.

### Fase 3 - Refatoracoes opcionais

1. Remover deprecated/backup apos validacao.
2. Normalizar lockfiles em pnpm.
3. Instanciar ou remover rollout `chat_rewrite_v1`.
4. Reduzir ruido de dumps e skips em testes.

---

## 8. Checklist de validacao

- [ ] `cd api && composer analyse -- --no-progress`
- [ ] `cd api && composer gate:all`
- [ ] `pnpm --dir gateway exec tsc -p tsconfig.build.json --noEmit`
- [ ] `pnpm --dir gateway exec tsc --noEmit`
- [ ] `pnpm --dir gateway test`
- [ ] `pnpm --dir app exec tsc -p tsconfig.app.json --noEmit`
- [ ] `pnpm --dir app test`
- [ ] `pnpm --dir app exec ng build --configuration production`
- [ ] Fluxo manual: listar templates Meta.
- [ ] Fluxo manual: iniciar conversa por template.
- [ ] Fluxo manual: receber webhook de status Meta e verificar persistencia local.
- [ ] Fluxo manual: abrir painel de filas, listar, pausar/retomar, DLQ e circuit breakers.
- [ ] Validar que nenhuma rota browser usa `InternalApiKeyGuard`.

---

## 9. Comandos recomendados

```bash
cd api && composer analyse -- --no-progress
cd api && composer gate:all

pnpm --dir gateway exec tsc -p tsconfig.build.json --noEmit
pnpm --dir gateway exec tsc --noEmit
pnpm --dir gateway test

pnpm --dir app exec tsc -p tsconfig.app.json --noEmit
pnpm --dir app test
pnpm --dir app exec ng build --configuration production

rg -n "environment.gateway.url|/admin/queues|send-template|InternalApiKeyGuard" app api gateway
rg -n "env\\(" api/src api/app api/routes
rg -n "MetaTemplateStatusUpdated|UpdateMetaTemplateStatusListener" api
```

---

## 10. Proximo passo recomendado

Corrigir primeiro o fluxo de templates App/API/Gateway, porque ele combina quebra real de UX com risco arquitetural: o frontend esta tentando acessar endpoints internos do Gateway e ainda com contrato incompatvel. Em seguida, registrar o listener `MetaTemplateStatusUpdated`, que e uma correcao pequena e destrava sincronizacao real de status da Meta.

