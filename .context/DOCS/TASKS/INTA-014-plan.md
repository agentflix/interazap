# Plano de Teste — FEAT-047 (Mobile App)

> INTA-14 | Status: Em Progresso | Criado: 2026-04-28

## Objetivo

Validar a entrega completa do FEAT-047 (Mobile App com Capacitor) verificando todos os módulos implementados, fluxos de usuário e integrações.

---

## Tarefas de Validação

### 1. Push Notifications (TASK-047.10, TASK-047.11)

| T | Validar fan-out APNs/FCM para novas mensagens |
|---|---|
| A | `api/tests/Feature/Chat/NewMessageNotificationTest.php` |
| C | Endpoint de mensagem deve disparar notificação push para todos os device tokens válidos do tenant |
| E | 4 testes verdes em NewMessageNotificationTest |

| T | Validar reativação de token revogado |
|---|---|
| A | `api/tests/Feature/Auth/DeviceTokenRegistrationTest.php` |
| C | Token revogado por erro deve ser reativado automaticamente ao receber push válido |
| E | 4 testes verdes em DeviceTokenRegistrationTest |

| T | Validar revogação em erro de token inválido |
|---|---|
| A | `api/src/Domain/Chat/Listeners/RevokeInvalidPushTokenListener.php` |
| C | Listener deve revogar token quando NotificationFailed indicar token inválido |
| E | Suite de integração passa com 7 testes em MobileBearerAuthTest |

### 2. Anexos e Fila Offline (TASK-047.12, TASK-047.13)

| T | Validar fluxo de anexos mobile (câmera/galeria) |
|---|---|
| A | `app/src/app/core/services/native-bridge.service.spec.ts` |
| C | Bridge nativa deve abrir câmera/galeria e retornar arquivo selecionado |
| E | 5 testes verdes incluindo `native-bridge.service.spec.ts` |

| T | Validar fila offline persistida |
|---|---|
| A | `app/src/app/core/services/offline-queue.service.spec.ts` |
| C | Mensagens offline devem ser persistidas e enviadas em ordem FIFO ao reconectar |
| E | 5 testes verdes em offline-queue.service.spec.ts |

| T | Validar interceptor de conectividade |
|---|---|
| A | `app/src/app/core/utils/offline-detection.interceptor.spec.ts` |
| C | HTTP interceptor deve detectar conectividade e enfileirar requisições quando offline |
| E | Testes do interceptor passam |

### 3. Build e CI/CD (TASK-047.14, TASK-047.15, TASK-047.16)

| T | Validar build Android (.aab) |
|---|---|
| A | `.github/workflows/mobile-android.yml` |
| C | Workflow deve compilar, assinar e fazer upload para Play Internal Testing |
| E | Workflow executa sem erros |

| T | Validar build iOS (.ipa) |
|---|---|
| A | `.github/workflows/mobile-ios.yml` |
| C | Workflow deve compilar, assinar e fazer upload para TestFlight |
| E | Workflow executa sem erros |

### 4. Sentry e Observabilidade (TASK-047.24)

| T | Validar inicialização do Sentry |
|---|---|
| A | `app/src/app/core/services/platform/sentry.service.ts` |
| C | Sentry deve inicializar apenas em ambiente mobile com config válida |
| E | `SentryAngularErrorHandler` registrado e capturando erros |

| T | Validar scrubbing de PII |
|---|---|
| A | SentryService com `request.data` removido |
| C | Dados sensíveis não devem ser enviados ao Sentry |
| E | Evidência: Memory guide em `.context/DOCS/MEMORY/2026-04-27-mobile-sentry-setup.md` |

### 5. Deep Links (TASK-047.25)

| T | Validar Universal Links iOS |
|---|---|
| A | `ios/App/App.entitlements` + `landing/.well-known/apple-app-site-association` |
| C | Links `app.interazap.com.br` devem abrir o app |
| E | Entitlements configurados com `applinks:app.interazap.com.br` |

| T | Validar App Links Android |
|---|---|
| A | `android/app/src/main/AndroidManifest.xml` com `intent-filter autoVerify=true` |
| C | Links devem acionar DeepLinkService e extrair conversationId |
| E | Manifesto configurado corretamente |

### 6. Autenticação Mobile (TASK-047.26)

| T | Validar autorização por tenant |
|---|---|
| A | `api/tests/Feature/Auth/MobileBearerAuthTest.php` |
| C | Bearer token deve permitir acesso apenas a canais/tickets do tenant do usuário |
| E | 7 testes verdes cobrindo: tenant próprio, tenant alheio, sem Bearer, token inválido/revogado |

### 7. Checklist E2E Mobile (CA-001 a CA-016)

| T | Executar checklist desmoke mobile |
|---|---|
| A | `app/e2e/mobile-checklist.md` |
| C | Validação manual de login, inbox, chat, mídia, push, WebSocket, offline, safe areas, back button, performance |
| E | Relatório de validação manual |

---

## Critérios de Aceitação

- [ ] Todos os testes unitários e de feature passando (backend)
- [ ] Todos os testes Vitest passando (frontend)
- [ ] Builds Android e iOS executando com sucesso
- [ ] Workflows CI passando (mobile-android.yml, mobile-ios.yml)
- [ ] Sentry configurado e capturando erros
- [ ] Deep links funcionando em ambos platforms
- [ ] Autenticação mobile validada com 7/7 testes
- [ ] Checklist E2E mobile CA-001 a CA-016 validado manualmente

---

## Riscos Residuais

1. **Push em background**: Requer teste em device real iOS/Android (não coberto por testes unitários)
2. **Apple Developer enrollment**: Requer conta ativa para certificados
3. **Google Play enrollment**: Requer conta ativa para upload
4. **Deep links production**: Requer domínio propagado com arquivos `.well-known/`

---

## Evidências

- Backend: `api/tests/Feature/Auth/DeviceTokenRegistrationTest.php` (4 testes)
- Backend: `api/tests/Feature/Chat/NewMessageNotificationTest.php` (4 testes)
- Backend: `api/tests/Feature/Auth/MobileBearerAuthTest.php` (7 testes)
- Frontend: Suite TASK-047 (33 testes, 33/33 verdes)
- CI: Workflows `mobile-android.yml` e `mobile-ios.yml` verdes
- Docs: Memory guides de Sentry, Apple Developer, Google Play