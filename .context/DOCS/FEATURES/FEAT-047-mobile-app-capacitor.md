# Feature: App Mobile (Capacitor + Angular) — iOS e Android

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo                  | Valor                                            |
| ---------------------- | ------------------------------------------------ |
| **ID**                 | FEAT-047                                         |
| **Nome**               | App Mobile (Capacitor + Angular) — iOS e Android |
| **Bounded Context**    | Cross-cutting (Auth, Chat, CRM, Configuration)   |
| **Complexidade**       | G                                                |
| **Prioridade**         | Should                                           |
| **Status**             | 🟡 Em Planning                                   |
| **Criada em**          | 2026-04-26                                       |
| **Última atualização** | 2026-04-26                                       |

---

## Resumo

Empacotar o aplicativo Angular existente (`app/`) como app nativo iOS e Android usando Capacitor, mantendo **um único codebase** para web + mobile. App carrega o renderer localmente (`file://` / `capacitor://`), com plugins nativos para push notifications, câmera, filesystem, teclado e network. Distribuído via App Store (iOS) e Google Play (Android), com paridade total de features para atendentes em todos os tenants.

---

## Objetivo

- **Problema:** Atendentes precisam abrir navegador no celular ou usar versão web responsiva — UX inferior, sem push real, sem integração com câmera/arquivos do dispositivo.
- **Oportunidade:** App nativo aumenta engajamento, permite push notifications de novas conversas (atendente responde mais rápido), e amplia adoção em equipes que operam em campo.
- **Restrição:** O time é de TypeScript/Angular — sem capacidade de manter codebase nativo paralelo (Swift/Kotlin). **Capacitor é a única abordagem viável** que respeita essa restrição.

---

## Escopo

### Dentro do Escopo ✅

- [ ] Setup Capacitor 6+ no projeto `app/` Angular existente
- [ ] Build configuration `mobile` (Angular) com `useHash: true` e `environment.mobile.ts`
- [ ] `PlatformService` único para detectar web/iOS/Android
- [ ] Migração Sanctum cookies → Bearer Token (backend + frontend)
- [ ] CORS Laravel para origins `capacitor://localhost` e `http://localhost` (estendendo `CORS_ALLOWED_ORIGINS` existente, sem nova env var)
- [ ] Push Notifications: APNs (iOS) + FCM (Android)
- [ ] Plugins nativos: camera, filesystem, network, status-bar, splash-screen, keyboard, haptics, app, browser, preferences, local-notifications
- [ ] Safe areas (notch iPhone, navbar Android)
- [ ] Botão voltar físico Android
- [ ] Fila offline para mensagens (envio diferido)
- [ ] Splash screen e ícones (reaproveitar assets do Desktop)
- [ ] Pipeline CI/CD: build `.aab` (Android) e `.ipa` (iOS)
- [ ] Apple Developer Program enrollment + App Store Connect setup
- [ ] Google Play Console setup
- [ ] Submissão TestFlight (iOS) + Internal Testing (Play)
- [ ] Submissão release público em ambas as stores
- [ ] **Sentry mobile (crash reporting iOS + Android com sourcemaps)**
- [ ] **Deep Links / Universal Links (tap em push abre conversa correta)**
- [ ] **WebSocket Bearer auth com tenant isolation testado explicitamente**
- [ ] **Validação de release build (ProGuard/R8 Android, sem regressão vs debug)**

### Fora do Escopo ❌

- App para clientes finais (apenas atendentes/admins/supervisores nesta iteração)
- App para Windows Phone / Huawei AppGallery
- Modo offline completo (apenas fila de envio de mensagens)
- Chamadas de voz/vídeo nativas
- Integração com contatos do dispositivo
- Widget de tela inicial iOS/Android
- App Clip (iOS) / Instant App (Android)
- Wearables (Apple Watch / Wear OS)

---

## Dependências

| Feature/Sistema               | Tipo                                     | Status            | Blocker                |
| ----------------------------- | ---------------------------------------- | ----------------- | ---------------------- |
| Sanctum (Auth)                | Modificação para Bearer Token            | Funcionando       | Sim — precisa migração |
| WebSockets (Reverb/Pusher)    | Funcionar com URL absoluta + Bearer auth | Funcionando       | Sim — precisa ajuste   |
| API CORS config               | Aceitar origins Capacitor                | Funcionando (web) | Sim — precisa ampliar  |
| FEAT-043 (WebSocket realtime) | Pré-requisito para chat em tempo real    | ✅ Pronta         | Não                    |
| FCM Project (Firebase)        | Conta Firebase Cloud Messaging           | A criar           | Sim                    |
| Apple Developer Account       | $99/ano — pessoa jurídica                | A criar           | Sim                    |
| Google Play Developer Account | $25 único — pessoa jurídica              | A criar           | Sim                    |

---

## Critérios de Aceite

| ID     | Critério                                                                                             | Verificável                                          | Status |
| ------ | ---------------------------------------------------------------------------------------------------- | ---------------------------------------------------- | ------ |
| CA-001 | App Android instalado abre, autentica via Bearer Token e lista conversas em tempo real               | E2E manual + Playwright sobre WebView                | ❌     |
| CA-002 | App iOS (TestFlight) instalado abre, autentica e lista conversas                                     | E2E manual em device real                            | ❌     |
| CA-003 | Push notification chega ao receber nova mensagem (iOS e Android), com app em background              | Teste manual em device                               | ❌     |
| CA-004 | Câmera abre via plugin nativo e foto é anexada à mensagem                                            | E2E manual                                           | ❌     |
| CA-005 | Filesystem permite anexar arquivo do dispositivo                                                     | E2E manual                                           | ❌     |
| CA-006 | App detecta offline e enfileira mensagens; envia ao reconectar                                       | Teste com airplane mode                              | ❌     |
| CA-007 | Build mobile gera `.aab` (Android) e `.ipa` (iOS) via CI sem erros                                   | CI green                                             | ❌     |
| CA-008 | Build mobile não quebra build web atual (zero regressão)                                             | `pnpm gate:test` + `pnpm gate:build` web             | ❌     |
| CA-009 | App passa Apple Review (rejeição = volta para correção)                                              | Status "Approved" no App Store Connect               | ❌     |
| CA-010 | App passa Google Play Review                                                                         | Status "Published" no Play Console                   | ❌     |
| CA-011 | Auth via Bearer Token funciona em web + mobile sem regressão de tenant isolation                     | Pest tests + E2E multi-tenant                        | ❌     |
| CA-012 | Backend CORS aceita `capacitor://localhost` e `http://localhost` sem expor produção a outros origins | Pest test + revisão manual `config/cors.php`         | ❌     |
| CA-013 | Botão voltar físico Android navega corretamente em todas rotas críticas (chat, conversas, settings)  | E2E manual                                           | ❌     |
| CA-014 | Teclado virtual não sobrepõe input do chat                                                           | E2E manual em device                                 | ❌     |
| CA-015 | Safe areas respeitadas (notch iPhone, barra Android)                                                 | Inspeção visual em devices reais (iPhone 14+, Pixel) | ❌     |
| CA-016 | Crash-free sessions ≥ 99% nas primeiras 2 semanas pós-release (mobile)                               | Sentry dashboard                                     | ❌     |
| CA-017 | Tap em push notification (background) abre app na conversa correta — iOS e Android                   | E2E manual + vídeo                                   | ❌     |
| CA-018 | Universal Link `https://app.interazap.com.br/chat/{id}` abre app instalado na rota correta           | E2E manual em device                                 | ❌     |
| CA-019 | WebSocket subscription bloqueia user de tenant A em canal de tenant B (mesmo com Bearer válido)      | Pest test multi-tenant                               | ❌     |
| CA-020 | Release build Android (minificada/ofuscada R8) completa smoke flow sem crash                         | E2E em device + CI smoke                             | ❌     |

---

## Arquitetura

### Estrutura de pastas (após setup)

```
app/
├── capacitor.config.ts                          ← config Capacitor
├── android/                                     ← gerado pelo cap (commitado)
├── ios/                                         ← gerado pelo cap (commitado)
├── src/
│   ├── environments/
│   │   ├── environment.ts                       ← web dev
│   │   ├── environment.production.ts            ← web prod
│   │   └── environment.mobile.ts                ← mobile (URL API absoluta)
│   └── app/
│       └── core/
│           └── services/
│               └── platform/
│                   ├── platform.service.ts      ← detecta plataforma
│                   ├── push.service.ts          ← APNs + FCM
│                   ├── native-bridge.service.ts ← câmera, fs, haptics
│                   ├── offline-queue.service.ts ← fila de mensagens
│                   └── auth-storage.service.ts  ← token via Preferences
└── angular.json                                 ← + configuration "mobile"
```

### Auth Flow (mudança crítica)

**ANTES (atual — Sanctum cookies):**

```
Browser → POST /sanctum/csrf-cookie → cookie XSRF-TOKEN
       → POST /login → cookie laravel_session
       → GET /api/* (cookies enviados automaticamente)
```

**DEPOIS (web continua + mobile novo):**

```
Web (cookies):              ← preserva fluxo atual
Mobile (Bearer Token):
  POST /api/auth/login → { token, user }
  Token salvo via @capacitor/preferences (Keychain iOS / Keystore Android)
  Header Authorization: Bearer <token> em todas as requests
```

**Notas críticas de implementação (validadas contra `api/config/sanctum.php` e `api/bootstrap/app.php` reais):**

- `sanctum.guard` real é `['web']` — **não alterar**. O guard `auth:sanctum` em rotas `/api/*` aceita Bearer Token nativamente sem mudança de config.
- Tenant isolation **continua sendo resolvida exclusivamente** por `Domain\Shared\Http\Middleware\TenantContextMiddleware` (já no grupo `api`), lendo `$user->tenant_id` após `auth:sanctum` resolver o user. **NÃO** colocar `tenant_id` em `abilities` do PersonalAccessToken — isso cria um segundo caminho de verificação que pode divergir e abrir bypass cross-tenant.
- Web continua usando o fluxo SPA stateful (`EnsureFrontendRequestsAreStateful` para domínios em `sanctum.stateful`); mobile usa Bearer puro sem estado.

### Build Pipeline

```
                       ┌─ ng build (web)         → dist/ → Nginx
ng build --config=mobile├─ cap sync               → android/ + ios/
                       │   └─ Android: ./gradlew bundleRelease → .aab
                       └─  iOS:    xcodebuild     → .ipa
```

---

## Plugins Capacitor Necessários

| Plugin                                  | Versão | Justificativa                          |
| --------------------------------------- | ------ | -------------------------------------- |
| `@capacitor/core` + `@capacitor/cli`    | ^6     | Base                                   |
| `@capacitor/android` + `@capacitor/ios` | ^6     | Plataformas                            |
| `@capacitor/push-notifications`         | ^6     | APNs + FCM (CRÍTICO para Apple Review) |
| `@capacitor/local-notifications`        | ^6     | Alertas offline                        |
| `@capacitor/camera`                     | ^6     | Anexos foto                            |
| `@capacitor/filesystem`                 | ^6     | Anexos arquivo                         |
| `@capacitor/preferences`                | ^6     | Token storage seguro                   |
| `@capacitor/network`                    | ^6     | Detecção offline                       |
| `@capacitor/status-bar`                 | ^6     | Cor barra status                       |
| `@capacitor/splash-screen`              | ^6     | Splash (exigido stores)                |
| `@capacitor/keyboard`                   | ^6     | Reposicionar input                     |
| `@capacitor/haptics`                    | ^6     | Feedback tátil                         |
| `@capacitor/app`                        | ^6     | Deep links + back button               |
| `@capacitor/browser`                    | ^6     | OAuth flows                            |

---

## Stores — Processo, Custos e Prazos

### Apple App Store

#### Custos

- **Apple Developer Program (Organization):** $99/ano (~R$ 500)
- Validação CNPJ via Dun & Bradstreet (DUNS Number) — **gratuito**, mas leva 5–10 dias úteis

#### Processo passo a passo

1. **Obter DUNS Number** (Dun & Bradstreet) para o CNPJ — 5–10 dias úteis
2. Enrollar em [developer.apple.com/programs](https://developer.apple.com/programs/) como **Organization** ($99/ano)
3. Aguardar validação Apple — 1–2 dias úteis após DUNS
4. Criar **App ID** (bundle ID `com.interazap.app`) no portal
5. Gerar certificados de Distribuição + Provisioning Profile (App Store)
6. Criar app no **App Store Connect**
7. Configurar APNs:
    - Criar APNs Key (.p8) — usar com Laravel para enviar push
8. Build `.ipa` (Xcode ou CI com `xcodebuild`)
9. Upload via **Xcode** ou `altool` ou Transporter
10. Submeter ao **TestFlight** (beta interno) — review automático em 24h
11. Coletar feedback de beta testers (mínimo 1 semana recomendado)
12. Submeter para **App Store Review** — review humano em 24–72h
13. **Possíveis rejeições** (e como evitar — ver seção abaixo)

#### Apple Review — Diretrizes críticas

| Diretriz                        | Risco para apps Capacitor                           | Mitigação                                                                                                    |
| ------------------------------- | --------------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| **4.2 (Minimum Functionality)** | Apps que são "só um wrapper de site" são rejeitados | Push notifications + Camera + Filesystem nativos demonstram valor real                                       |
| **4.3 (Spam)**                  | App muito similar a outros do mesmo dev             | N/A (app único)                                                                                              |
| **5.1.1 (Privacy)**             | Coleta de dados sem privacy policy                  | Privacy Policy em URL pública + `NSCameraUsageDescription`, `NSPhotoLibraryUsageDescription` no `Info.plist` |
| **2.5.1 (APIs)**                | Uso de APIs privadas                                | Capacitor usa apenas APIs públicas — OK                                                                      |
| **3.1.1 (In-App Purchase)**     | Vender features digitais sem IAP                    | InteraZap é B2B SaaS pago externamente — aplica isenção 3.1.3(b) "Reader" / "Enterprise Services"            |
| **2.1 (Performance)**           | App crasha ou trava                                 | Test em dispositivos reais antes de submeter                                                                 |
| **4.5.4 (Push Notifications)**  | Push usado para spam/ads                            | Push só para eventos legítimos (nova mensagem) — OK                                                          |

#### Prazo total Apple

- DUNS: 5–10 dias úteis
- Enrollment + setup: 2 dias úteis
- Build + TestFlight: 1 dia
- Review: 1–3 dias úteis
- **Total: ~3 semanas (otimista) a 5 semanas (com 1 rejeição)**

---

### Google Play Store

#### Custos

- **Google Play Developer Account:** $25 (one-time, vitalício)

#### Processo passo a passo

1. Criar conta em [play.google.com/console](https://play.google.com/console) ($25)
2. Verificação de identidade (documento + selfie) — 1–3 dias úteis
3. Criar app no Console
4. Configurar **Firebase Project** + obter `google-services.json`
5. Gerar **upload keystore** (.jks) — guardar em vault
6. Build `.aab` (Android App Bundle) via `./gradlew bundleRelease`
7. Upload para **Internal Testing** (até 100 testers, sem review)
8. Promover para **Closed Testing** ou direto **Production**
9. Submeter para **Play Review** — review automatizado + humano em 1–7 dias

#### Google Play Review — Diretrizes críticas

| Diretriz                                                    | Risco                                                                           | Mitigação                                            |
| ----------------------------------------------------------- | ------------------------------------------------------------------------------- | ---------------------------------------------------- |
| **Closed Testing antes de Production** (nova política 2023) | Conta nova precisa de 12 testers reais durante 14 dias antes de release público | Recrutar 12 testers (time interno + clientes piloto) |
| **Data Safety**                                             | Não declarar coleta de dados causa rejeição                                     | Preencher Data Safety form com precisão              |
| **Target SDK**                                              | App precisa target Android 14+ (API 34) em 2025+                                | Capacitor 6 já target API 34                         |
| **64-bit**                                                  | Apps 32-bit-only são rejeitados                                                 | Capacitor builda 64-bit por padrão                   |
| **Permissions sensitive**                                   | Pedir SMS, Contacts, Location sem justificativa                                 | NÃO pedimos essas permissões                         |
| **Privacy Policy**                                          | URL obrigatória                                                                 | Mesma do iOS                                         |

#### Prazo total Google

- Setup conta: 3 dias úteis
- Build + Internal Testing: 1 dia
- Closed Testing 14 dias (obrigatório para conta nova): 14 dias
- Review final: 1–7 dias
- **Total: ~3–4 semanas**

---

### Cronograma Consolidado

```
Semana 1   ━━━━━━━━━━━━━ Setup Capacitor + plugins + ajustes Angular críticos
Semana 2   ━━━━━━━━━━━━━ Auth Bearer + CORS + PlatformService + safe areas
Semana 3   ━━━━━━━━━━━━━ Push notifications + Deep Links + camera/fs + offline queue
Semana 4   ━━━━━━━━━━━━━ Sentry + WebSocket Bearer test + E2E em devices + ícones/splash
Semana 5   ━━━━━━━━━━━━━ Setup contas Apple + Google + privacy policy + assets
Semana 6   ━━━━━━━━━━━━━ Build CI/CD + release build validation (R8) + TestFlight + Play Internal
Semana 7-8 ━━━━━━━━━━━━━ Closed Testing Play (14 dias) + correções beta
Semana 9   ━━━━━━━━━━━━━ Submissão Production ambas stores + monitoring Sentry intensivo

Total: ~9 semanas (otimista) — 12 semanas com folga para rejeições

Tasks novas (24-27) rodam em paralelo às fases existentes:
- TASK-047.24 (Sentry): semana 4 (paralelo a E2E)
- TASK-047.25 (Deep Links): semana 3 (paralelo a Push)
- TASK-047.26 (WS Bearer test): semana 4 (paralelo a E2E)
- TASK-047.27 (Release build): semana 6 (gate antes de TestFlight/Play)
```

---

## Tasks

| Task ID     | Descrição                                                        | Status | Agent              |
| ----------- | ---------------------------------------------------------------- | ------ | ------------------ |
| TASK-047.1  | Setup Capacitor + dependências no `app/`                         | ⏳     | FRONTEND           |
| TASK-047.2  | Configuração Angular `mobile` (hash routing + env)               | ⏳     | FRONTEND           |
| TASK-047.3  | Backend: migrar Sanctum para suportar Bearer Token               | ⏳     | BACKEND            |
| TASK-047.4  | Backend: ajustar CORS para origins Capacitor                     | ⏳     | BACKEND            |
| TASK-047.5  | Frontend: AuthStorageService + interceptor Bearer                | ✅     | FRONTEND           |
| TASK-047.6  | PlatformService + detecção web/iOS/Android                       | ✅     | FRONTEND           |
| TASK-047.7  | Plugins: status-bar, splash, keyboard, haptics, app              | ⏳     | FRONTEND           |
| TASK-047.8  | Safe areas CSS + viewport-fit cover                              | ⏳     | FRONTEND           |
| TASK-047.9  | Back button Android handler                                      | ⏳     | FRONTEND           |
| TASK-047.10 | Push Notifications backend (APNs + FCM via Laravel Notification) | ⏳     | BACKEND            |
| TASK-047.11 | Push Notifications frontend (registration + handlers)            | ⏳     | FRONTEND           |
| TASK-047.12 | Camera + Filesystem nos anexos do chat                           | ⏳     | FRONTEND           |
| TASK-047.13 | Offline queue (fila de mensagens persistida)                     | ⏳     | FRONTEND           |
| TASK-047.14 | Ícones + splash screen (reaproveitar assets desktop)             | ⏳     | FRONTEND           |
| TASK-047.15 | CI/CD: build `.aab` (Android)                                    | ⏳     | DEV                |
| TASK-047.16 | CI/CD: build `.ipa` (iOS)                                        | ⏳     | DEV                |
| TASK-047.17 | Setup Apple Developer + DUNS + App Store Connect                 | ⏳     | PM                 |
| TASK-047.18 | Setup Google Play Console + Firebase                             | ⏳     | PM                 |
| TASK-047.19 | Privacy Policy + assets de loja (screenshots, descrições)        | ⏳     | PM/DOC             |
| TASK-047.20 | E2E testing em devices reais (iPhone + Android)                  | ⏳     | QA                 |
| TASK-047.21 | Submissão TestFlight + Play Internal Testing                     | ⏳     | PM                 |
| TASK-047.22 | Closed Testing Play (14 dias obrigatórios)                       | ⏳     | PM                 |
| TASK-047.23 | Submissão Production ambas stores                                | ⏳     | PM                 |
| TASK-047.24 | Sentry mobile (crash reporting iOS + Android)                    | ⏳     | FRONTEND           |
| TASK-047.25 | Deep Links + Universal Links (push tap → conversa)               | ⏳     | FRONTEND           |
| TASK-047.26 | WebSocket Bearer auth multi-tenant (teste explícito)             | ⏳     | BACKEND + FRONTEND |
| TASK-047.27 | Validação release build (ProGuard/R8 + bitcode)                  | ⏳     | DEV + QA           |

---

## Riscos

| Risco                                                    | Probabilidade      | Impacto | Mitigação                                                               |
| -------------------------------------------------------- | ------------------ | ------- | ----------------------------------------------------------------------- |
| Apple rejeitar por "minimum functionality" (4.2)         | Média              | Alto    | Push + Camera + Filesystem nativos; demonstrar uso real no review notes |
| DUNS Number atrasar (5–10 dias)                          | Alta               | Médio   | Iniciar processo na Semana 1, paralelo ao desenvolvimento               |
| Google Play exigir 14 dias Closed Testing                | Certo (conta nova) | Médio   | Planejar buffer no cronograma                                           |
| Migração Sanctum cookies → Bearer quebrar web            | Média              | Alto    | Sanctum suporta ambos; manter cookies para web, Bearer só mobile        |
| WebSockets não funcionarem em `file://` / `capacitor://` | Baixa              | Alto    | Usar URL absoluta WSS + Bearer no header                                |
| Push não chegar em iOS background                        | Média              | Alto    | Configurar APNs Key corretamente + testar em device real                |
| Build iOS exigir Mac no CI                               | Certo              | Médio   | Usar `macos-15` runners GitHub (já validado no Desktop)                 |
| Tamanho do app exceder limites stores                    | Baixa              | Baixo   | Capacitor + Angular bundle ~30–50MB; sob limites confortáveis           |

---

## Notas

- Reaproveitar ícones do Desktop (raio teal `#14b8a6`) — `electron/build/icon.png` 1024×1024 é base perfeita
- App ID escolhido: **`com.interazap.app`** (consistente com `com.interazap.desktop`)
- Apple `NSAppTransportSecurity` precisa permitir HTTPS apenas (já é o padrão InteraZap)
- Considerar **Sentry mobile SDK** desde início para crash reporting
- Privacy Policy deve cobrir: dados de chat, mídia (camera/fs), push token, dados do usuário (email/nome)
- Considerar **versionCode/buildNumber automatizado via CI** (timestamp ou commit count)
- Após release público, monitorar reviews/crashes na primeira semana intensivamente
