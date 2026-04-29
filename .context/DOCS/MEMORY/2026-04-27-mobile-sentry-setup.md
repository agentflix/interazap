# Sentry Mobile — Setup e Configuração

## Metadados

| Campo        | Valor                                                                    |
| ------------ | ------------------------------------------------------------------------ |
| **Tipo**     | 📋 Processo + 📚 Aprendizado                                             |
| **Data**     | 2026-04-27                                                               |
| **Contexto** | FEAT-047 TASK-047.24                                                     |
| **Tags**     | mobile, sentry, crash-reporting, ios, android, sourcemaps, observability |

---

## O que foi implementado

- `SentryService` em `app/src/app/core/services/platform/sentry.service.ts`
- `SentryAngularErrorHandler` como `ErrorHandler` global no mobile
- Init via `APP_INITIALIZER` em `app/src/app/app.config.ts` (apenas quando `environment.mobile = true`)
- Upload de sourcemaps em CI (`.github/workflows/mobile-android.yml` e `mobile-ios.yml`)
- Scrubbing de PII: `request.data` removido antes do envio (sem conteúdo de mensagem)

---

## Configuração necessária (humano)

### 1. Criar projeto no Sentry

1. Acesse [sentry.io](https://sentry.io) → New Project → **Capacitor**
2. Nome do projeto: `interazap-mobile`
3. Copie o DSN gerado (formato: `https://xxx@xxx.ingest.sentry.io/xxx`)

### 2. Configurar DSN

```typescript
// app/src/environments/environment.mobile.ts
sentry: {
  dsn: 'https://SEU_DSN_AQUI@o000000.ingest.sentry.io/0000000',
  tracesSampleRate: 0.1,
  profilesSampleRate: 0.1,
}
```

**NUNCA commitar o DSN em plain text no repo público.** Para CI/CD:

- Adicionar como secret: `SENTRY_DSN` no GitHub
- Injetar em `environment.mobile.ts` no step de build:
    ```yaml
    - name: Inject Sentry DSN
      run: |
          sed -i "s|dsn: ''|dsn: '${{ secrets.SENTRY_DSN }}'|" \
            app/src/environments/environment.mobile.ts
    ```

### 3. Configurar GitHub Secrets e Variables para upload de sourcemaps

| Item                | Tipo     | Valor                                           |
| ------------------- | -------- | ----------------------------------------------- |
| `SENTRY_AUTH_TOKEN` | Secret   | Token de API do Sentry (Settings → Auth Tokens) |
| `SENTRY_ORG`        | Variable | Slug da organização Sentry                      |
| `SENTRY_PROJECT`    | Variable | Nome do projeto (`interazap-mobile`)            |

### 4. Verificar que o upload funcionou

```bash
npx @sentry/cli releases list --org=SUA_ORG
```

---

## Como adicionar contexto de usuário após login

```typescript
// Em auth.service.ts ou no store de autenticação após login bem-sucedido
private readonly sentry = inject(SentryService);

// Após login:
this.sentry.setUser({
  id: user.id,
  tenantId: user.tenant_id,
  role: user.role,
});

// No logout:
this.sentry.clearUser();
```

---

## Regras de PII (LGPD)

| Campo                    | Enviado ao Sentry | Razão                               |
| ------------------------ | ----------------- | ----------------------------------- |
| `user.id` (UUID)         | ✅ Sim            | Identificador técnico, não pessoal  |
| `tenant_id`              | ✅ Sim            | Contexto de isolamento              |
| `platform` (ios/android) | ✅ Sim            | Debug técnico                       |
| Conteúdo de mensagens    | ❌ Nunca          | LGPD Art. 7 — dado pessoal sensível |
| E-mail do usuário        | ❌ Nunca          | PII direto                          |
| Nome do usuário          | ❌ Nunca          | PII direto                          |
| `request.data` (body)    | ❌ Removido       | Pode conter conteúdo de mensagem    |

---

## Validar que Sentry funciona (antes de ir para produção)

```typescript
// Em um componente de debug temporário — REMOVER ANTES DE PRODUÇÃO:
import * as Sentry from '@sentry/capacitor';

// Botão "Force Crash" para testar:
onForceCrash(): void {
  throw new Error('Sentry test crash — InteraZap mobile');
}
```

Verificar no Sentry dashboard que:

- [ ] Evento aparece em < 5 minutos
- [ ] Stack trace contém nomes de funções (não apenas offsets)
- [ ] Tags `tenant_id` e `platform` presentes
- [ ] Campo `request.data` ausente no evento

---

## Problemas conhecidos

| Problema                                   | Solução                                                   |
| ------------------------------------------ | --------------------------------------------------------- |
| Sentry não inicializa no web               | Comportamento esperado — `isMobile = false` bloqueia init |
| DSN vazio na build → SDK não inicializa    | Verificar se o secret foi injetado no CI                  |
| Sourcemap não desofusca → verificar versão | Release name deve incluir `+android` ou `+ios` sufixo     |
| iOS App Store rejeita por Bitcode          | `@sentry/capacitor` desabilita Bitcode automaticamente    |
