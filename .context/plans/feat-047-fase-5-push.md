---
status: in_progress
generated: 2026-04-26
feature_id: FEAT-047
scope: fase-5-push-notifications
agents:
    - BACKEND
    - FRONTEND
    - QA
    - DOC
phases:
    - id: plan
      name: Planning
      prevc: P
      agent: ORCHESTRATOR
    - id: review
      name: Review
      prevc: R
      agent: REVIEWER
    - id: execution-backend
      name: Execution Backend
      prevc: E
      agent: BACKEND
    - id: execution-frontend
      name: Execution Frontend
      prevc: E
      agent: FRONTEND
    - id: validation
      name: Validation
      prevc: V
      agent: QA
    - id: confirm
      name: Confirmacao e Documentacao
      prevc: C
      agent: DOC
---

# Plano FEAT-047 Fase 5 - Push Notifications

## Objetivo

Implementar integralmente a Fase 5 da FEAT-047 com entrega das tasks:

- TASK-047.10 (backend): registro/revogacao de tokens de device e envio push via APNs/FCM com isolamento por tenant.
- TASK-047.11 (frontend): solicitacao de permissao, registro do token no backend e handlers de notificacao em foreground/background.

## Escopo

### Incluido

- Model, migration, actions, requests, controller e rotas para tokens de dispositivo no backend.
- Notification de nova mensagem com fan-out por plataforma iOS/Android.
- Revogacao automatica de token invalido e revogacao no logout.
- PushService no app Angular com registro, recebimento e acao de toque.
- Integracao do bootstrap para iniciar push apos autenticacao mobile.
- Testes Pest e Vitest cobrindo os cenarios de evidencia das tasks 047.10 e 047.11.
- Atualizacao de task file, changelog e memory ao final.

### Fora de escopo

- Deep links/universal links (TASK-047.25).
- Sentry mobile (TASK-047.24).
- Offline queue/anexos (TASK-047.12 e TASK-047.13).

## Dependencias e premissas

- TASK-047.3 e TASK-047.4 ja concluidas (auth bearer e CORS mobile).
- TASK-047.5 e TASK-047.6 ja concluidas (PlatformService e AuthStorageService).
- Ambientes APNs/FCM continuarao por env vars, sem hardcode de segredo.
- Execucao e testes no host local (sem Docker).

## Execucao por fase (PREVC)

### P - Planning (ORCHESTRATOR)

1. Confirmar requisitos na feature FEAT-047 e arquivo de tasks.
2. Validar abordagens e riscos de regressao em auth e tenant isolation.
3. Gerar plano com ordem de execucao: BACKEND -> FRONTEND -> QA -> DOC.

Entrega:

- Plano preenchido em .context/plans/feat-047-fase-5-push.md.

### R - Review (REVIEWER)

1. Revisar se escopo atende exatamente 047.10 e 047.11.
2. Validar nao-objetivos para evitar overbuild.
3. Aprovar inicio da fase E.

Entrega:

- Aprovacao de plano registrada no workflow.

### E - Execution Backend (BACKEND)

1. Implementar tabela auth_device_tokens com tenant isolation e soft revoke.
2. Implementar endpoints:
    - POST /api/devices/register
    - DELETE /api/devices/{id}
3. Integrar notificacao de nova mensagem para APNs/FCM.
4. Implementar testes Pest das evidencias da TASK-047.10.
5. Rodar suite alvo de auth/chat.

Entrega:

- Codigo backend + testes passando para TASK-047.10.

### E - Execution Frontend (FRONTEND)

1. Criar PushService com requestPermissions, register e listeners.
2. Integrar inicializacao no app apos login mobile.
3. Implementar fluxo de unregister/revoke no logout.
4. Adicionar/ajustar specs Vitest para cenarios mobile.
5. Implementar `pushNotificationActionPerformed` navegando para `/chat/:conversationId` com payload valido.
6. Implementar re-registro de token quando invalidado pelo provedor ou ao receber novo token de registration.
7. Rodar testes do escopo frontend.

Entrega:

- Codigo frontend + testes passando para TASK-047.11.

### V - Validation (QA)

1. Executar validacao automatizada (Pest + Vitest target).
2. Executar validacao manual em device real iOS para recebimento em background e tap abrindo conversa correta.
3. Executar validacao manual em device real Android para recebimento em background e tap abrindo conversa correta.
4. Validar cenario de token invalidado com re-registro no backend.
5. Auditar risco de regressao web (cookies) e mobile bearer.
6. Consolidar evidencias (logs/tests/capturas) para cada item da checklist das tasks 047.10 e 047.11.

Entrega:

- Resultado de validacao com status aprovado/reprovado.

## Matriz de rastreabilidade T.A.C.E (Task -> Execucao -> Evidencia)

### TASK-047.10 (BACKEND)

- Migration `auth_device_tokens` com unique `(tenant_id, platform, token)` e soft revoke `revoked_at`.
  Evidencia: migrate/rollback sem erro e teste de duplicidade/reativacao.
- Endpoint `POST /api/devices/register` com upsert por tenant/plataforma/token.
  Evidencia: `DeviceTokenRegistrationTest::it_registers_new_token_with_tenant_isolation` e `it_prevents_duplicate_via_unique_index`.
- Endpoint `DELETE /api/devices/{id}` com ownership por user.
  Evidencia: teste de soft revoke no logout e tentativa de acesso indevido.
- Fan-out APNs/FCM somente para tokens ativos do tenant.
  Evidencia: `NewMessageNotificationTest::it_fans_out_only_to_tenant_users`, `it_skips_revoked_tokens`, `it_sends_apn_payload_for_ios_and_fcm_for_android`.
- Revogacao automatica em token invalido.
  Evidencia: `NewMessageNotificationTest::it_revokes_token_on_apns_invalid_token_response`.
- Integridade de autoload.
  Evidencia: `composer dump-autoload --strict-psr` sem warnings.

### TASK-047.11 (FRONTEND)

- Solicitar permissao e registrar token apos login em mobile.
  Evidencia: `push.service.spec.ts` cobrindo permission/register + validacao manual em device.
- Enviar token para backend em `POST /api/devices/register`.
  Evidencia: spec com mock de API e log de request em execucao manual.
- Foreground: `pushNotificationReceived` atualiza badge/haptics.
  Evidencia: spec do handler + verificacao manual.
- Tap em notificacao: `pushNotificationActionPerformed` abre rota `/chat/:conversationId`.
  Evidencia: spec de roteamento e video/captura em iOS/Android.
- Logout revoga token no backend.
  Evidencia: spec de logout/unregister + request DELETE confirmado.
- Re-registro apos token invalidado.
  Evidencia: teste de fluxo simulando novo registration token apos invalidacao.

### C - Confirm (DOC)

1. Atualizar status das tasks no arquivo FEAT-047-tasks.
2. Registrar mudancas factuais no changelog do dia.
3. Registrar decisao/aprendizado tecnico em memory.
4. Atualizar project-state se aplicavel.

Entrega:

- Entradas em CHANGELOG e MEMORY + task marcada concluida.

## Riscos principais e mitigacao

- Risco: vazamento cross-tenant em envio push.
  Mitigacao: filtro obrigatorio por tenant_id e testes de isolamento.
- Risco: token duplicado causando push duplicado.
  Mitigacao: unique index (tenant_id, platform, token) + upsert/reativacao.
- Risco: regressao no fluxo web por cookies.
  Mitigacao: manter auth web intacta e validar testes existentes.
- Risco: falha silenciosa em plugin push no app.
  Mitigacao: handlers explicitos para registrationError e fallback logico.
- Risco: credenciais APNs/FCM ausentes ou invalidas no ambiente.
  Mitigacao: validar env vars e chaves no inicio da fase E e bloquear merge sem check de configuracao.

## Criterios de sucesso

- TASK-047.10: todos os testes Pest previstos na task passam, incluindo isolamento por tenant, tokens revogados e payload APNs/FCM.
- TASK-047.10: migration up/down e `composer dump-autoload --strict-psr` executam sem erro.
- TASK-047.11: `push.service.spec.ts` cobre permissao, registro, recebimento, tap e revogacao.
- TASK-047.11: evidencias manuais em device real iOS e Android confirmam recebimento em background e abertura de conversa no tap.
- TASK-047.11: fluxo de re-registro de token invalidado validado.
- Sem introduzir endpoints paralelos de auth nem alterar guard sanctum.
- Sem segredos hardcoded em repositorio.

## Checkpoint de commits (referencia)

- feat(api): implementa registro de device token e notificacoes push
- feat(app): adiciona PushService e handlers de notificacao mobile
- docs(context): registra conclusao da fase 5 da FEAT-047
