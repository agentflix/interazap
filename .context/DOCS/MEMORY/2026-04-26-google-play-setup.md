# Google Play Developer + Firebase FCM Setup — Playbook

**Task:** TASK-047.18
**Feature:** FEAT-047 (App Mobile)
**Owner:** Rafael Silva
**Criado:** 2026-04-26
**Status:** ⏳ Pendente — execução manual pelo Rafael (3-5 dias úteis)

---

## ⚠️ DESTAQUE CRÍTICO — BACKUP DO KEYSTORE

> **Se perder o `interazap-upload.jks`, você NUNCA mais conseguirá publicar updates do app InteraZap.**
> Não existe recuperação. Google não substitui keystore (somente Play App Signing oferece migração — opt-in).
>
> **Regra inegociável:** o `.jks` deve estar em **PELO MENOS 2 lugares independentes**:
> 1. Ansible vault (`infra/ansible/vars/vault.yml`, base64 + ansible-vault encrypt)
> 2. Cofre físico OU 1Password corporativo (família "InteraZap Production Secrets")
>
> Documente os 2 locais, o owner de cada um, e teste o restore antes de fazer o primeiro release.

---

## 1. Pré-requisitos (reunir ANTES de começar)

| Item | Detalhe | Status |
|------|---------|--------|
| CNPJ InteraZap | Razão social + CNPJ ativo na Receita | ⏳ |
| Cartão internacional | USD 25 one-time (NÃO recorrente como Apple) | ⏳ |
| Email corporativo | Recomendado: `developer@interazap.com.br` (Google Workspace dedicado) | ⏳ |
| Documento representante legal | RG ou CNH em alta resolução, sem reflexos | ⏳ |
| Selfie com documento | Boa iluminação, ângulo reto, rosto + doc visíveis | ⏳ |
| Comprovante endereço | Conta de luz ou contrato social | ⏳ |
| Endereço fiscal | Deve bater **exatamente** com CNPJ | ⏳ |
| Telefone corporativo | Para verificação SMS / call | ⏳ |
| D-U-N-S Number | **Opcional** no Google (se já tiver pela Apple, USE — acelera muito) | ⏳ |

---

## 2. Etapa 1 — Criar Google Play Developer Account (Organization)

**Link:** https://play.google.com/console/signup

1. Logar com `developer@interazap.com.br`
2. Selecionar tipo: **Organization** (NÃO Personal — B2B exige Organization)
3. Pagar **USD 25** (one-time, vitalício)
4. Preencher dados da empresa:
   - Razão social
   - CNPJ
   - Endereço fiscal (idêntico ao CNPJ)
   - Telefone
   - Site: `https://interazap.com.br`
   - Email de contato público
5. Se tiver D-U-N-S da Apple: informar — verificação fica automática.

**Armadilha:** Google verifica via D-U-N-S OU documentos manuais. Sem D-U-N-S, vai direto para Etapa 2 (verificação manual = mais lenta).

---

## 3. Etapa 2 — Verificação de Identidade (1-3 dias úteis)

Painel: https://play.google.com/console → **Identity verification**

1. Upload documento representante legal (RG/CNH frente + verso)
2. Selfie segurando o documento
3. Comprovante de endereço (conta de luz recente ou contrato social)

**Armadilhas:**
- Foto borrada → reprovação automática (perde 1-2 dias).
- Use boa iluminação natural, ângulo reto, sem flash.
- Dados do doc devem bater 100% com cadastro.

Aguardar email de confirmação antes de prosseguir.

---

## 4. Etapa 3 — Criar App no Play Console

**Link:** https://play.google.com/console → **Create app**

| Campo | Valor |
|-------|-------|
| App name | `InteraZap` |
| Default language | Portuguese (Brazil) — pt-BR |
| App or game | App |
| Free or paid | **Free** (download grátis; monetização B2B é externa) |
| Developer Program Policies | ✅ |
| US export laws | ✅ |

**Anotar Application ID:** `com.interazap.app`

> Não publicar nada ainda. Apenas criar o entry. Upload do APK/AAB virá em TASK-047.21.

---

## 5. Etapa 4 — Criar Firebase Project (FCM Push)

**Link:** https://console.firebase.google.com → **Add project**

| Campo | Valor |
|-------|-------|
| Nome do projeto | `interazap-mobile` |
| Project ID | `interazap-mobile` (Google sufixa se já existir) |
| Google Analytics | Habilitar (recomendado — crash + métricas grátis) |

### Adicionar Android App ao projeto

1. No projeto criado → **Add app** → Android
2. Package name: `com.interazap.app` (deve bater com Play Console)
3. App nickname: `InteraZap Android`
4. SHA-1: **deixar em branco agora** (preencher após Etapa 6)
5. Baixar **`google-services.json`**
6. Salvar em: `app/android/app/google-services.json`

> ✅ **OK commitar** `google-services.json` no repositório. É config público, não contém segredos sensíveis (apenas project ID, API key restrita por package name).

---

## 6. Etapa 5 — FCM Service Account (backend Laravel)

**Link:** Firebase Console → ⚙️ **Project Settings** → **Service Accounts** → **Generate new private key**

1. Baixa um JSON tipo `interazap-mobile-firebase-adminsdk-xxxxx.json`
2. **🔒 SECRETO — NUNCA commitar em claro.**
3. Adicionar ao Ansible vault:

```bash
ansible-vault edit infra/ansible/vars/vault.yml
# adicionar:
# fcm_service_account_json: |
#   { "type": "service_account", ... }
# fcm_project_id: interazap-mobile
```

Variáveis para o backend Laravel (`.env` no servidor, populadas via Ansible):

```env
FCM_PROJECT_ID=interazap-mobile
FCM_CREDENTIALS_PATH=/var/www/interazap/storage/app/firebase/service-account.json
```

> O arquivo é renderizado pelo playbook Ansible a partir do vault, com perms `0600` e owner `www-data`.

---

## 7. Etapa 6 — Gerar Upload Keystore (.jks)

No Mac do Rafael:

```bash
cd ~/secure/interazap-keystore   # diretório local fora do repo
keytool -genkey -v \
  -keystore interazap-upload.jks \
  -keyalg RSA \
  -keysize 2048 \
  -validity 10000 \
  -alias upload
```

**Anotar (em 1Password):**
- Keystore password
- Key alias: `upload`
- Key password (pode ser igual à keystore password — simplifica)

### Extrair SHA-1

```bash
keytool -list -v -keystore interazap-upload.jks -alias upload | grep SHA1
```

Adicionar SHA-1 no **Firebase Console → Android App → Add fingerprint**.

### Salvar em vault

```bash
# Base64 do .jks
base64 -i interazap-upload.jks -o interazap-upload.jks.b64

# Adicionar ao vault
ansible-vault edit infra/ansible/vars/vault.yml
# android_keystore_base64: "<conteúdo do .b64>"
# android_key_alias: upload
# android_keystore_password: "<senha>"
# android_key_password: "<senha>"
```

### Variáveis para CI (GitHub Actions / GitLab CI)

```env
ANDROID_KEYSTORE_BASE64=<base64 do .jks>
ANDROID_KEY_ALIAS=upload
ANDROID_KEYSTORE_PASSWORD=<...>
ANDROID_KEY_PASSWORD=<...>
```

> 🔒 **SECRETO** — todas as 4 variáveis acima.
> 🔒 **CATASTRÓFICO se perdido** — ver destaque no topo deste documento.

### Backup (OBRIGATÓRIO antes de seguir)

1. ✅ Vault Ansible (encrypted)
2. ✅ 1Password corporativo — vault "InteraZap Production"
3. ⚠️ (Opcional, mas recomendado) cofre físico com pen drive criptografado

---

## 8. Etapa 7 — Closed Testing Track (14 dias obrigatórios)

Política Google (em vigor desde nov/2023): **contas novas Organization** precisam de:
- **12+ testers ativos**
- **14 dias contínuos** de teste
- ANTES do primeiro release público (Production track).

### Plano de recrutamento (alvo: 14 testers para folga)

| Grupo | Alvo | Responsável |
|-------|------|-------------|
| Time interno InteraZap (devs, suporte, vendas) | 5-7 | Rafael |
| Clientes piloto convidados | 5-7 | Comercial |
| Reserva (caso alguém saia) | 2 | — |

Convite: email com link do Closed Testing track + instruções de instalação via Play Store.

> Configuração técnica do track será feita em **TASK-047.22**, não nesta task.

---

## 9. Checklist de Status

| # | Etapa | Status | Data | Observações |
|---|-------|--------|------|-------------|
| 1 | Pré-requisitos reunidos | ⏳ | — | — |
| 2 | Play Developer Account criada (Organization) | ⏳ | — | USD 25 pago |
| 3 | Verificação de identidade aprovada | ⏳ | — | 1-3 dias |
| 4 | App `InteraZap` criado no Play Console | ⏳ | — | App ID `com.interazap.app` |
| 5 | Firebase Project `interazap-mobile` criado | ⏳ | — | — |
| 6 | `google-services.json` em `app/android/app/` | ⏳ | — | OK commitar |
| 7 | FCM Service Account JSON em vault | ⏳ | — | 🔒 secreto |
| 8 | `interazap-upload.jks` gerado | ⏳ | — | 🔒 catastrófico se perder |
| 9 | SHA-1 registrado no Firebase | ⏳ | — | — |
| 10 | Keystore + senhas em vault Ansible | ⏳ | — | 🔒 |
| 11 | Backup keystore em 1Password | ⏳ | — | 🔒 redundância obrigatória |
| 12 | Plano de recrutamento de 12+ testers | ⏳ | — | Para TASK-047.22 |

---

## 10. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Verificação reprovada (foto ruim) | Média | +2 dias | Foto em alta resolução, luz natural, sem reflexos |
| Cartão internacional recusado | Baixa | +1 dia | Cartão corporativo com limite USD habilitado; backup com cartão pessoal do CEO |
| Package name `com.interazap.app` já registrado | Baixa | Médio | Reservar AGORA criando o app entry; fallback `com.interazap.mobile` |
| **Perda do keystore** | Baixa | **CATASTRÓFICO** | Backup em 2+ locais independentes, testar restore antes do 1º release |
| Não conseguir 12 testers em 14 dias | Média | +14 dias | Recrutar 14+ (folga); plano B: oferecer crédito interno como incentivo |
| Política Data Safety mal preenchida → rejeição | Média | +3 dias | Preencher com base em PRIVACY.md; revisar com jurídico antes de submeter |
| D-U-N-S não migrado da Apple | Baixa | +5 dias verificação | Ter docs manuais prontos como plano B |

---

## 11. Definição de "done" desta task

- ✅ Google Play Developer Account com status **"Active"** (verificação aprovada)
- ✅ App entry `InteraZap` criado, inicialmente apenas em **Internal testing** (sem APK ainda)
- ✅ Firebase Project `interazap-mobile` ativo
- ✅ `google-services.json` salvo em `app/android/app/google-services.json` (commitado)
- ✅ FCM Service Account JSON salvo em `infra/ansible/vars/vault.yml` (encrypted)
- ✅ Upload keystore `interazap-upload.jks` gerado **e** salvo em 2 locais (vault + 1Password)
- ✅ SHA-1 do keystore registrado no Firebase Console (Android App fingerprints)
- ✅ Restore do keystore testado a partir de pelo menos 1 dos backups

---

## 12. Próximos passos (tasks dependentes)

| Task | Depende de | O que usa |
|------|-----------|-----------|
| TASK-047.15 (CI Android build) | Etapa 6 (keystore) + Etapa 4 (`google-services.json`) | Variáveis `ANDROID_KEYSTORE_BASE64` etc. |
| TASK-047.10 (push backend Laravel) | Etapa 5 (FCM Service Account) | `FCM_PROJECT_ID`, `FCM_CREDENTIALS_PATH` |
| TASK-047.21 (Internal Testing upload) | Etapa 3 (account ativa) + Etapa 4 (app entry) | Upload do primeiro AAB |
| TASK-047.22 (Closed Testing 14 dias) | TASK-047.21 + plano de recrutamento (Etapa 7) | Lista de 12+ testers |

---

## Resumo: o que é OK commitar vs o que é SECRETO

| Arquivo | Localização | Commitar? |
|---------|-------------|-----------|
| `google-services.json` | `app/android/app/` | ✅ **SIM** — config público |
| FCM Service Account JSON | `infra/ansible/vars/vault.yml` (ansible-vault) | ❌ **NÃO** — credenciais sensíveis |
| `interazap-upload.jks` | Vault + 1Password | ❌ **NÃO** — catastrófico se vazar/perder |
| Keystore + key passwords | Vault + 1Password | ❌ **NÃO** |
| SHA-1 fingerprint | Firebase Console | (não é arquivo — config no console) |

---

## Calendário estimado

| Dia | Atividade |
|-----|-----------|
| **D+0** (hoje) | Reunir pré-requisitos, criar Google Workspace `developer@`, pagar USD 25, submeter docs |
| **D+1 a D+3** | Aguardar verificação Google (passivo) |
| **D+3** | Criar app entry no Play Console + Firebase Project + `google-services.json` |
| **D+3** | Gerar keystore + backup em vault + 1Password + registrar SHA-1 no Firebase |
| **D+4** | Gerar Service Account FCM, salvar no vault Ansible |
| **D+4** | Comunicar GO para TASK-047.10 (backend) e TASK-047.15 (CI) |
| **D+5+** | Recrutamento dos 12+ testers começa em paralelo (para TASK-047.22) |
