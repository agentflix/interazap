# Google Play Console + Firebase — Setup Guide (InteraZap Android)

> **Criado em:** 2026-04-27  
> **Contexto:** FEAT-047 TASK-047.18  
> **Status:** ⏳ Aguardando execução pelo PM

---

## Contexto

Para publicar o app InteraZap na Google Play Store, a empresa precisa de uma conta Google Play Developer ativa e de um projeto Firebase para FCM (push notifications).

---

## Parte 1 — Google Play Developer Account

### Pré-requisitos

| Item                                                  | Status                       |
| ----------------------------------------------------- | ---------------------------- |
| Conta Google corporativa                              | Criar em accounts.google.com |
| Cartão de crédito/débito                              | Taxa única $25               |
| Informações da empresa (CNPJ, razão social, endereço) | Obter do departamento legal  |
| Número de telefone para verificação                   | Disponível                   |

### Etapa 1.1 — Criar conta

1. Acesse [play.google.com/console](https://play.google.com/console)
2. Clique em **Create developer account**
3. Selecione: **Organization** (não Individual)
4. Pague taxa de $25 (única, não recorrente)
5. Complete o processo de verificação de identidade

**Atenção:** Verificação de identidade pode levar até 7 dias úteis via upload de documentos.

### Etapa 1.2 — Criar o App no Play Console

1. **All apps → Create app**
2. Preencher:
    - App name: `InteraZap`
    - Default language: `Portuguese (Brazil) — pt-BR`
    - App or game: **App**
    - Free or paid: **Free**
3. Aceitar Developer Program Policies
4. Create app

### Etapa 1.3 — Configurar Closed Testing (obrigatório para nova conta)

> Nova conta Google Play **exige** 14 dias de Closed Testing com 12+ testers antes de Publishing para Production.

1. **Testing → Closed testing → Create new track**
2. Nome: `Internal Beta`
3. Adicionar testers (mínimo 12 e-mails reais)
4. Aguardar 14 dias com uso ativo (ver TASK-047.22)

---

## Parte 2 — Firebase Project + FCM

### Etapa 2.1 — Criar projeto Firebase

1. Acesse [console.firebase.google.com](https://console.firebase.google.com)
2. **Add project**
3. Nome: `interazap-mobile`
4. Habilitar Google Analytics: **Sim** (projeto: InteraZap Analytics)
5. Create project

### Etapa 2.2 — Adicionar Android App ao Firebase

1. No projeto Firebase: **Add app → Android**
2. Android package name: `com.interazap.app`
3. App nickname: `InteraZap Android`
4. Debug signing certificate SHA-1 (obter via `keytool`):
    ```bash
    cd app/android
    keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android | grep SHA1
    ```
5. **Download `google-services.json`**
6. Copiar para: `app/android/app/google-services.json`
7. Registrar o SHA-256 do keystore de release também (após criar keystore)

### Etapa 2.3 — Obter FCM Server Key (para backend Laravel)

1. Firebase Console → Projeto → **Project Settings → Cloud Messaging**
2. **Server key** (Legacy) — copiar
3. OU gerar Service Account JSON para API v1 (recomendado):
    - **Project Settings → Service accounts**
    - **Generate new private key**
    - Salvar JSON no vault

**Configurar no `.env` da API:**

```env
FCM_CREDENTIALS_JSON='{"type":"service_account","project_id":"interazap-mobile",...}'
```

### Etapa 2.4 — Adicionar google-services.json ao repositório

```bash
# O arquivo google-services.json NÃO deve conter dados sensíveis de servidor
# Ele contém apenas o app_id e sender_id — é seguro commitar
git add app/android/app/google-services.json
git commit -m "feat(mobile): add google-services.json for FCM"
```

**Conteúdo esperado (template):**

```json
{
    "project_info": {
        "project_number": "XXXXXXXXXXXX",
        "project_id": "interazap-mobile",
        "storage_bucket": "interazap-mobile.appspot.com"
    },
    "client": [
        {
            "client_info": {
                "mobilesdk_app_id": "1:XXXX:android:XXXX",
                "android_client_info": {
                    "package_name": "com.interazap.app"
                }
            },
            "api_key": [{ "current_key": "XXXX" }],
            "services": {
                "appinvite_service": {
                    "other_platform_oauth_client": []
                }
            }
        }
    ],
    "configuration_version": "1"
}
```

---

## Parte 3 — Keystore de Release Android

### Etapa 3.1 — Gerar keystore

```bash
keytool -genkey -v \
  -keystore interazap-release.jks \
  -alias interazap \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000
```

**Guardar no vault:**

- `interazap-release.jks` (nunca commitar no repositório)
- Alias: `interazap`
- Senhas (keystore + key)

### Etapa 3.2 — Configurar GitHub Secrets

```
ANDROID_KEYSTORE_BASE64 = base64 -i interazap-release.jks
ANDROID_KEY_ALIAS = interazap
ANDROID_KEYSTORE_PASSWORD = <senha do keystore>
ANDROID_KEY_PASSWORD = <senha da key>
```

### Etapa 3.3 — Registrar SHA-256 no Firebase (para release)

```bash
keytool -list -v \
  -keystore interazap-release.jks \
  -alias interazap | grep SHA-256
```

Adicionar no Firebase Console → Project Settings → Your apps → Android app → Add fingerprint.

---

## Parte 4 — Data Safety Form (Play Console)

Play Console exige o preenchimento do **Data Safety** antes de publicar em production.

### Categorias a declarar

| Dados                                      | Coletados             | Compartilhados             | Propósito             |
| ------------------------------------------ | --------------------- | -------------------------- | --------------------- |
| Identificadores de dispositivo (FCM token) | ✅                    | ❌                         | Funcionalidade do app |
| Mensagens de chat                          | ✅                    | ❌ (apenas tenant interno) | Funcionalidade do app |
| Fotos/Vídeos (galeria)                     | Opcional pelo usuário | ❌                         | Funcionalidade do app |
| Nome do usuário                            | ✅                    | ❌                         | Funcionalidade do app |
| E-mail                                     | ✅                    | ❌                         | Funcionalidade do app |

Declarar que o app **não vende dados** e que os dados são **criptografados em trânsito**.

---

## Checklist Final

- [ ] Google Play Developer Account ativa e verificada
- [ ] App `com.interazap.app` criado no Play Console
- [ ] Firebase project `interazap-mobile` criado
- [ ] `google-services.json` adicionado a `app/android/app/`
- [ ] FCM Server Key / Service Account JSON salvo no vault
- [ ] Keystore de release gerado e salvo no vault
- [ ] GitHub Secrets configurados (`ANDROID_KEYSTORE_BASE64` etc.)
- [ ] SHA-256 do release keystore registrado no Firebase
- [ ] Closed Testing track criado com 12+ testers (ver TASK-047.22)
- [ ] Data Safety form preenchido no Play Console

---

## Referências

- [Google Play Console Help](https://support.google.com/googleplay/android-developer)
- [Firebase Android Setup](https://firebase.google.com/docs/android/setup)
- [FCM HTTP v1 API](https://firebase.google.com/docs/cloud-messaging/http-server-ref)
- [App Signing by Google Play](https://support.google.com/googleplay/android-developer/answer/9842756)
