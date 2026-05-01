# Apple Developer — Setup Guide (InteraZap iOS)

> **Criado em:** 2026-04-27  
> **Contexto:** FEAT-047 TASK-047.17  
> **Status:** ⏳ Aguardando execução pelo PM

---

## Contexto

Para publicar o app InteraZap na App Store, a empresa precisa de uma conta **Apple Developer Program (Organization)** ativa. Este guia documenta cada etapa necessária e quais artefatos gerar.

---

## Pré-requisitos

| Item                                              | Status                        |
| ------------------------------------------------- | ----------------------------- |
| CNPJ da empresa ativo                             | Verificar                     |
| DUNS Number (D-U-N-S)                             | Obter via Dun & Bradstreet    |
| Cartão de crédito internacional (Visa/Mastercard) | Necessário para pagar $99/ano |
| Conta Apple ID corporativa (não pessoal)          | Criar em appleid.apple.com    |
| Acesso ao domínio interazap.com.br                | Para verificação de e-mail    |

---

## Etapa 1 — Obter DUNS Number

1. Acesse [https://developer.apple.com/enroll/duns-lookup/](https://developer.apple.com/enroll/duns-lookup/)
2. Informe CNPJ, razão social e endereço exatamente como no contrato social
3. Se não encontrado: solicite via [Dun & Bradstreet BR](https://www.dnb.com/pt-br/business-directory/) (gratuito, 5–7 dias úteis)
4. **Anote o número** — será exigido no enrollment

**Atenção:** O nome da empresa no DUNS deve coincidir exatamente com o informado à Apple.

---

## Etapa 2 — Enrollment Apple Developer Organization

1. Acesse [https://developer.apple.com/programs/enroll/](https://developer.apple.com/programs/enroll/)
2. Selecione: **Organization** (não Individual)
3. Informe:
    - Legal entity name (razão social)
    - DUNS Number
    - Headquarters address
    - Phone number
4. Aguardar verificação pela Apple (2–5 dias úteis)
5. Pagar $99/ano com cartão internacional
6. Ativar conta em [developer.apple.com](https://developer.apple.com)

---

## Etapa 3 — Criar App ID (Bundle Identifier)

1. Acesse [Certificates, Identifiers & Profiles](https://developer.apple.com/account/resources/identifiers/list)
2. **Identifiers → +**
3. Tipo: **App IDs → App**
4. Bundle ID: `com.interazap.app` (Explicit)
5. Capabilities habilitadas:
    - [x] Push Notifications
    - [x] Associated Domains (Universal Links)
6. Register → confirmar

---

## Etapa 4 — Gerar APNs Auth Key (.p8)

> **IMPORTANTE:** Gerar apenas 1 key e salvar no vault. Keys são limitadas a 2 por conta.

1. **Keys → +** → Apple Push Notifications service (APNs)
2. Nome: `InteraZap APNs Key`
3. **Download** (única oportunidade — não é recuperável)
4. **Salvar em vault:** `APNs/AuthKey_{KEY_ID}.p8`
5. Anotar:
    - `Key ID` (10 chars alfanuméricos)
    - `Team ID` (visible em Membership → Team ID)

**Configurar no `.env` da API:**

```env
APN_KEY_ID=XXXXXXXXXX
APN_TEAM_ID=XXXXXXXXXX
APN_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
APP_BUNDLE_ID=com.interazap.app
```

---

## Etapa 5 — Distribution Certificate

1. **Certificates → +**
2. Tipo: **Apple Distribution**
3. Gerar CSR no Mac: Keychain Access → Certificate Assistant → Request a Certificate From a Certificate Authority
4. Upload CSR → Download `.cer`
5. Instalar no Keychain (duplo clique)
6. Exportar como `.p12` com senha forte → **Salvar no vault**

```
GitHub Secret: APPLE_CERT_P12_BASE64 = base64 do .p12
GitHub Secret: APPLE_CERT_PASSWORD = senha do .p12
```

---

## Etapa 6 — Provisioning Profile (App Store Distribution)

1. **Profiles → +**
2. Tipo: **App Store Connect**
3. Selecionar App ID: `com.interazap.app`
4. Selecionar Distribution Certificate gerado acima
5. Nome: `InteraZap App Store`
6. **Download** `.mobileprovision`

```
GitHub Secret: APPLE_PROVISIONING_PROFILE = base64 do .mobileprovision
```

---

## Etapa 7 — App Store Connect

1. Acesse [appstoreconnect.apple.com](https://appstoreconnect.apple.com)
2. **Apps → +** → New App
3. Preencher:
    - Platform: iOS
    - Name: `InteraZap`
    - Primary Language: Portuguese (Brazil)
    - Bundle ID: `com.interazap.app`
    - SKU: `INTERAZAP-001`
4. Salvar

---

## Etapa 8 — App Store Connect API Key (para CI)

1. **Users and Access → Integrations → App Store Connect API → +**
2. Nome: `InteraZap CI`
3. Access: **App Manager** (mínimo necessário para CI)
4. Download `.p8`
5. Anotar `Issuer ID` e `Key ID`

```
GitHub Secret: APP_STORE_CONNECT_API_KEY = base64 do .p8
GitHub Secret: APP_STORE_CONNECT_API_KEY_ID
GitHub Secret: APP_STORE_CONNECT_ISSUER_ID
GitHub Secret: APPLE_TEAM_ID = Team ID do Membership
```

---

## Checklist Final

- [ ] DUNS Number obtido
- [ ] Apple Developer Program ativo ("Active" em Membership)
- [ ] Bundle ID `com.interazap.app` criado com Push + Associated Domains
- [ ] APNs Auth Key `.p8` salva no vault
- [ ] Distribution Certificate instalado + `.p12` no vault
- [ ] Provisioning Profile App Store criado
- [ ] App entrada criada no App Store Connect (iOS 16.0+)
- [ ] App Store Connect API Key gerada
- [ ] Todos os secrets configurados no GitHub Actions
- [ ] Privacy URL: `https://interazap.com.br/privacy`

---

## Referências

- [Apple Developer Enrollment](https://developer.apple.com/programs/enroll/)
- [Creating Distribution Certificates](https://developer.apple.com/documentation/xcode/creating-distribution-certificates)
- [DUNS Lookup](https://developer.apple.com/enroll/duns-lookup/)
