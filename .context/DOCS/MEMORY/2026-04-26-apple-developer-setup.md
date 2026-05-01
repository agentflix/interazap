# SOP — Apple Developer Program Enrollment (Organization) — InteraZap

> **Tipo:** Standard Operating Procedure / Playbook operacional
> **Task de origem:** TASK-047.17 (FEAT-047 — App Mobile iOS)
> **Owner:** Rafael Silva (representante legal / sócio InteraZap)
> **Criado em:** 2026-04-26
> **Janela estimada:** 2-3 semanas (DUNS é o gargalo: 5-10 dias úteis)
> **Custo:** USD 99/ano (~R$ 500) — cobrado pela Apple no cartão internacional

---

## 0. Por que este documento existe

Para publicar o app **InteraZap** na App Store em nome da empresa (B2B), a Apple **exige enrollment como Organization** (não Individual). Esse processo é burocrático, depende de terceiros (Dun & Bradstreet) e de disponibilidade do representante legal para atender ligação da Apple. **Não pode ser executado por agentes de IA** — exige CNPJ, cartão e identidade pessoal.

Este SOP é o passo-a-passo acionável para Rafael executar e rastrear. Cada etapa tem link oficial, dados a coletar antes de iniciar, e armadilhas conhecidas.

---

## 1. Pré-requisitos (coletar ANTES de iniciar)

| Item | Detalhe | Status |
|------|---------|--------|
| CNPJ ativo | Cartão CNPJ atualizado (baixar em https://solucoes.receita.fazenda.gov.br/Servicos/cnpjreva/Cnpjreva_Solicitacao.asp) | ⏳ |
| Razão social EXATA | Conforme Receita Federal — copiar com acentos, pontuação e "LTDA" | ⏳ |
| Endereço fiscal completo | Logradouro, número, complemento, bairro, cidade, UF, CEP | ⏳ |
| Telefone corporativo | Linha que possa ser atendida em horário comercial (Apple liga em inglês) | ⏳ |
| Cartão de crédito internacional | Visa/Master habilitado para compras em USD; verificar limite ≥ USD 200 | ⏳ |
| Email corporativo dedicado | Recomendado: `developer@interazap.com.br` (criar antes via provedor de email) | ⏳ |
| Apple ID corporativo NOVO | Criar em https://appleid.apple.com com o email acima — **NÃO reutilizar Apple ID pessoal** | ⏳ |
| Two-Factor Authentication | Habilitar 2FA no Apple ID corporativo (obrigatório para Developer Program) | ⏳ |
| Contrato Social (PDF) | Versão consolidada mais recente, assinada e registrada na Junta | ⏳ |
| Comprovante de endereço (PDF) | Conta de luz/internet em nome da empresa, ≤ 90 dias | ⏳ |
| Identificação do signatário | Quem vai assinar pela empresa? Tem poderes no Contrato Social? | ⏳ |

> ⚠️ **Crítico:** Apple ID **deve ser novo** e **dedicado à organização**. Misturar com Apple ID pessoal de um sócio causa problemas de transferência futura e bloqueio em troca de representante legal.

---

## 2. Etapa 1 — DUNS Number (5-10 dias úteis — BLOQUEIA TUDO)

### 2.1 Verificar se já existe DUNS para a InteraZap

Acessar: https://developer.apple.com/enrollment/duns-lookup/

Preencher:
- **Legal Entity Name:** razão social EXATA da Receita
- **Country/Region:** Brazil
- **Address:** endereço fiscal
- **Phone:** telefone corporativo
- **Your work email:** `developer@interazap.com.br`

Possíveis resultados:
- ✅ **DUNS encontrado e dados batem:** anotar o número e ir para Etapa 2
- 🟡 **DUNS encontrado mas dados divergem:** corrigir via Dun & Bradstreet antes de prosseguir
- ❌ **Não encontrado:** Apple inicia solicitação gratuita junto à D&B (próximo passo)

### 2.2 Se não tiver DUNS — solicitar via Apple

A própria Apple dispara a solicitação gratuita à D&B Brasil. Você receberá email de:
- `dnbsupport@apple.com` ou diretamente da D&B

Prazo D&B Brasil: **5 a 10 dias úteis** (já vimos casos de 14 dias).

### 2.3 Armadilhas conhecidas

| Armadilha | Prevenção |
|-----------|-----------|
| Razão social com acentuação errada (`InteraZap LTDA` vs `INTERAZAP LTDA.`) | Copiar do cartão CNPJ — não digitar de memória |
| Endereço com abreviação (`R.` vs `Rua`) | Usar formato completo, igual ao cartão CNPJ |
| Telefone sem DDI/DDD | Usar formato `+55 11 99999-9999` |
| D&B liga para validar e ninguém atende | Avisar recepção/secretária; usar celular do sócio |
| Email de aprovação cair no spam | Adicionar `@dnb.com` e `@apple.com` à allowlist |

### 2.4 Como rastrear

- Caixa de entrada de `developer@interazap.com.br`
- Portal Apple: https://developer.apple.com/account → seção Enrollment

---

## 3. Etapa 2 — Enrollment Apple Developer Program (1-2 dias úteis após DUNS OK)

### 3.1 Iniciar enrollment

1. Acessar https://developer.apple.com/programs/enroll/
2. Login com Apple ID corporativo (com 2FA)
3. Selecionar **Entity Type: Organization** (NÃO Individual; NÃO Government; NÃO Non-Profit)
4. Preencher:
   - Legal Entity Name (idêntico ao DUNS)
   - DUNS Number
   - Headquarters address (idêntico ao DUNS)
   - Website: `https://interazap.com.br` (deve estar online e ativo)
   - Você tem autoridade legal para vincular a empresa? **Sim** (se Rafael for sócio/admin no Contrato)
5. Aceitar Apple Developer Program License Agreement
6. Pagar USD 99 com cartão internacional

### 3.2 Verificação por telefone

Após submissão, Apple liga **no telefone do DUNS** (não no celular pessoal) para confirmar:
- Identidade do signatário
- Autoridade legal
- Que a solicitação é genuína

**Janela típica:** 09:00–18:00 PT (horário Pacífico USA) → **13:00–22:00 BRT**.

**Armadilhas:**
- Apple liga em **inglês**. Se inglês não for confortável, pedir educadamente: *"Could you please call back in Portuguese, or send the verification by email?"* — funciona em ~70% dos casos.
- Se ninguém atender em 3 tentativas, Apple envia email pedindo callback agendado.
- **Não desligar nem fingir que não é a empresa** — bloqueia a conta.

### 3.3 Documentos que podem ser solicitados

Se Apple suspeitar de algo, pedirá upload de:
- Contrato Social consolidado (PDF assinado)
- Comprovante de endereço (PDF, ≤ 90 dias)
- Procuração (se signatário não for sócio)

Já deixe estes PDFs prontos em `~/Documents/InteraZap/legal/` para upload imediato.

### 3.4 Como rastrear

- Email do Apple ID corporativo
- Portal: https://developer.apple.com/account → Membership

Status esperado ao final: **"Active"**, com Team ID visível (formato `XXXXXXXXXX`, 10 caracteres alfanuméricos). Anotar este Team ID — será usado em todas as etapas seguintes.

---

## 4. Etapa 3 — Configurar App Store Connect

### 4.1 Registrar Bundle ID (App ID)

1. Acessar https://developer.apple.com/account/resources/identifiers/list
2. Clicar em **+** → **App IDs** → **App**
3. Preencher:
   - **Description:** `InteraZap Mobile App`
   - **Bundle ID:** `com.interazap.app` (Explicit)
   - **Capabilities a habilitar:**
     - ✅ Push Notifications
     - ✅ Sign In with Apple (se aplicável)
     - ✅ Associated Domains (para deep links)
4. Continue → Register

> ⚠️ Se `com.interazap.app` já estiver tomado por outra entidade, fallback: `br.com.interazap.app` ou `com.interazap.mobile`. **Atualizar TASK-047.16 e configs do projeto Xcode** se mudar.

### 4.2 Criar App entry no App Store Connect

1. Acessar https://appstoreconnect.apple.com
2. **My Apps** → **+** → **New App**
3. Preencher:
   - **Platforms:** iOS
   - **Name:** `InteraZap`
   - **Primary Language:** Portuguese (Brazil)
   - **Bundle ID:** selecionar `com.interazap.app`
   - **SKU:** `INTERAZAP-MOBILE-001`
   - **User Access:** Full Access

### 4.3 Adicionar membros do time

**Users and Access** → **+** → criar convites:

| Role | Quem | Permissões típicas |
|------|------|---------------------|
| Admin | Rafael Silva | Tudo (mas evitar usar conta-mestre do dia a dia) |
| App Manager | Tech Lead Mobile | Gerenciar app, builds, TestFlight |
| Developer | Devs iOS | Upload de builds, ler crash logs |
| Marketing | PM/Marketing | Editar metadata, screenshots, descrição |

> ⚠️ Cada membro precisa ter Apple ID com 2FA antes do convite ser aceito.

---

## 5. Etapa 4 — APNs Auth Key (.p8) para Push Notifications

### 5.1 Gerar a key

1. Acessar https://developer.apple.com/account/resources/authkeys/list
2. **+** (Create a new key)
3. **Key Name:** `InteraZap APNs Production`
4. ✅ Habilitar **Apple Push Notifications service (APNs)**
5. Continue → Register
6. **Download** → arquivo `AuthKey_XXXXXXXXXX.p8`

### 5.2 ⚠️ ALERTA CRÍTICO

> O `.p8` **só pode ser baixado UMA ÚNICA VEZ**. Se perder, é necessário revogar e gerar nova key (e atualizar todos os ambientes). Faça backup imediato em **2 lugares** antes de fechar a página.

### 5.3 Anotar metadados

Após download, anotar (ainda na página de confirmação):
- **Key ID:** 10 caracteres (ex: `ABC123DEF4`)
- **Team ID:** 10 caracteres (mesmo do Membership)

### 5.4 Salvar no Ansible Vault

```bash
# 1. Mover o .p8 para local temporário seguro (NÃO commitar)
mv ~/Downloads/AuthKey_*.p8 ~/secure/interazap-apns-prod.p8

# 2. Editar o vault (ele pedirá a senha do vault)
ansible-vault edit infra/ansible/vars/vault.yml
```

Adicionar ao vault as seguintes variáveis (formato YAML):

```yaml
# APNs (Apple Push Notifications) — Produção
apn_key_id: "ABC123DEF4"           # substituir pelo Key ID real
apn_team_id: "XXXXXXXXXX"          # substituir pelo Team ID real
apn_bundle_id: "com.interazap.app"
apn_production: true
apn_private_key: |
  -----BEGIN PRIVATE KEY-----
  MIGTAgEAMBMGByqGSM49AgEGCCqGSM49AwEH...
  ...conteúdo completo do .p8...
  -----END PRIVATE KEY-----
```

> Para colar o conteúdo do `.p8`: `cat ~/secure/interazap-apns-prod.p8` e copiar tudo (incluindo as linhas BEGIN/END).

### 5.5 Backend Laravel — variáveis esperadas

O backend (`api/`) consumirá do vault via Ansible em deploy. Em desenvolvimento local, usar `.env` (sem commitar):

```env
APN_KEY_ID=ABC123DEF4
APN_TEAM_ID=XXXXXXXXXX
APN_BUNDLE_ID=com.interazap.app
APN_PRODUCTION=true
APN_PRIVATE_KEY_PATH=/path/local/AuthKey.p8
```

### 5.6 Após salvar no vault

```bash
# Apagar cópias em claro
shred -u ~/secure/interazap-apns-prod.p8   # Linux
# ou no macOS:
rm -P ~/secure/interazap-apns-prod.p8

# Verificar que vault está commitável (criptografado)
head -1 infra/ansible/vars/vault.yml
# Deve começar com: $ANSIBLE_VAULT;1.1;AES256
```

---

## 6. Etapa 5 — Distribution Certificate + Provisioning Profile

> Pré-requisito: ter um Mac com Xcode (TASK-047.16 detalha o ambiente CI).

### 6.1 Gerar CSR (Certificate Signing Request)

No Mac:
1. Abrir **Keychain Access** (`/Applications/Utilities/Keychain Access.app`)
2. Menu: **Keychain Access → Certificate Assistant → Request a Certificate From a Certificate Authority...**
3. Preencher:
   - **User Email Address:** `developer@interazap.com.br`
   - **Common Name:** `InteraZap Distribution`
   - **CA Email Address:** deixar em branco
   - **Request is:** ✅ Saved to disk
4. Continue → salvar `CertificateSigningRequest.certSigningRequest` no Desktop

### 6.2 Distribution Certificate

1. https://developer.apple.com/account/resources/certificates/list
2. **+** → **Apple Distribution** (cobre App Store + Ad Hoc)
3. Upload do `.certSigningRequest`
4. Continue → **Download** → `distribution.cer`
5. Duplo-clique no `.cer` → instala no Keychain

### 6.3 Provisioning Profile (App Store)

1. https://developer.apple.com/account/resources/profiles/list
2. **+** → **Distribution → App Store**
3. **App ID:** selecionar `com.interazap.app`
4. **Certificate:** selecionar a Distribution Certificate criada
5. **Profile Name:** `InteraZap App Store Distribution`
6. Continue → Generate → **Download** → `InteraZap_App_Store_Distribution.mobileprovision`

### 6.4 Exportar tudo para CI (TASK-047.16)

```bash
# 1. Exportar a Distribution Cert + private key como .p12 (Keychain Access)
#    Botão direito no certificado → Export → .p12, definir senha forte
mv ~/Desktop/InteraZapDistribution.p12 ~/secure/

# 2. Salvar no vault
ansible-vault edit infra/ansible/vars/vault.yml
```

Adicionar ao vault:

```yaml
# iOS Code Signing
ios_distribution_p12_base64: "<base64 do .p12>"
ios_distribution_p12_password: "<senha do .p12>"
ios_provisioning_profile_base64: "<base64 do .mobileprovision>"
```

Comandos para gerar base64 (macOS):
```bash
base64 -i ~/secure/InteraZapDistribution.p12 | pbcopy
base64 -i ~/Downloads/InteraZap_App_Store_Distribution.mobileprovision | pbcopy
```

---

## 7. Checklist de Status (Rafael atualiza conforme avança)

| # | Etapa | Status | Data | Observações |
|---|-------|--------|------|-------------|
| 0.1 | Pré-requisitos coletados | ⏳ | — | |
| 0.2 | Apple ID corporativo criado + 2FA | ⏳ | — | |
| 1.1 | DUNS lookup feito | ⏳ | — | |
| 1.2 | DUNS Number obtido | ⏳ | — | Número: ____________ |
| 2.1 | Enrollment iniciado em developer.apple.com | ⏳ | — | |
| 2.2 | Pagamento USD 99 confirmado | ⏳ | — | |
| 2.3 | Ligação de verificação atendida | ⏳ | — | |
| 2.4 | Documentos extras enviados (se solicitado) | ⏳ | — | |
| 2.5 | Membership "Active" + Team ID anotado | ⏳ | — | Team ID: ____________ |
| 3.1 | Bundle ID `com.interazap.app` registrado | ⏳ | — | |
| 3.2 | App entry criado no App Store Connect | ⏳ | — | |
| 3.3 | Membros do time convidados | ⏳ | — | |
| 4.1 | APNs `.p8` gerado e baixado | ⏳ | — | Key ID: ____________ |
| 4.2 | APNs salvo em `infra/ansible/vars/vault.yml` | ⏳ | — | |
| 4.3 | Cópia em claro do `.p8` apagada | ⏳ | — | |
| 5.1 | CSR gerado no Mac | ⏳ | — | |
| 5.2 | Distribution Certificate baixado e instalado | ⏳ | — | |
| 5.3 | Provisioning Profile (App Store) gerado | ⏳ | — | |
| 5.4 | `.p12` + profile exportados em base64 para vault | ⏳ | — | |

**Legenda:** ⏳ pendente · 🟡 em andamento · ✅ concluído · ❌ bloqueado

---

## 8. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| DUNS reprovado por discrepância de razão social/endereço | Alta | Atrasa 5-10 dias úteis adicionais | Copiar dados literalmente do cartão CNPJ; revisar 2x antes de submeter |
| Apple liga fora do horário e ninguém atende (3 tentativas) | Média | Atrasa 3-7 dias | Avisar recepção; deixar celular do sócio como contato secundário; preparar resposta padrão em inglês |
| Cartão de crédito internacional recusado | Média | Bloqueia pagamento USD 99 | Avisar banco antes (compra internacional Apple Inc.); ter cartão de backup |
| Bundle ID `com.interazap.app` já registrado por terceiro | Baixa | Refator de configs Xcode + backend | Fallback: `br.com.interazap.app`; atualizar TASK-047.16 e env vars |
| Apple ID pessoal usado por engano | Média | Conta fica vinculada a indivíduo, não à empresa | **NUNCA** reutilizar Apple ID pessoal; criar novo dedicado ANTES de iniciar |
| `.p8` perdido após download | Média | Revogar key + atualizar todos os ambientes | Backup imediato em 2 locais (vault + cofre pessoal cifrado) |
| Renovação anual esquecida | Alta (recorrente) | App removido da Store após expiração | Calendar reminder 30 dias antes do vencimento; cartão atualizado no Apple ID |
| Representante legal sai da empresa | Baixa | Necessária transferência de Account Holder | Documentar Apple ID + senha em cofre corporativo (não pessoal); processo de transferência via Apple suporta |
| Site `interazap.com.br` fora do ar durante review | Baixa | Apple pode rejeitar enrollment | Verificar uptime antes de submeter |
| Procuração necessária se signatário não for sócio | Média | Atrasa 1-3 dias | Preparar procuração reconhecida em cartório se Rafael não estiver no Contrato Social |

---

## 9. Definition of Done (TASK-047.17)

A task está concluída quando **TODOS** os itens abaixo forem ✅:

- [ ] DUNS Number obtido e validado pela Apple
- [ ] Apple Developer Program Membership com status **Active**
- [ ] Team ID anotado e documentado
- [ ] Bundle ID `com.interazap.app` registrado em developer.apple.com
- [ ] App entry "InteraZap" criado no App Store Connect com SKU `INTERAZAP-MOBILE-001`
- [ ] APNs Auth Key (.p8) gerada e salva em `infra/ansible/vars/vault.yml` (criptografado)
- [ ] Distribution Certificate gerado e instalado no Keychain
- [ ] Provisioning Profile (App Store) gerado
- [ ] `.p12` + provisioning profile exportados em base64 e salvos no vault
- [ ] Checklist da Seção 7 deste documento todo em ✅
- [ ] CHANGELOG entry em `.context/DOCS/CHANGELOG/YYYY-MM-DD.md` registrando a conclusão

---

## 10. Próximos passos (após esta task)

1. **TASK-047.16 — CI iOS:** consumir os assets gerados (`.p12`, `.mobileprovision`, APNs `.p8`) no pipeline (GitHub Actions/Bitrise/Xcode Cloud). Os secrets já estarão no vault.
2. **TASK-047.21 — TestFlight upload:** primeiro build assinado enviado via `altool` ou `xcrun notarytool`; convidar internal testers.
3. **(Futuro) Configurar Sign In with Apple** se aplicável ao fluxo de auth.
4. **(Futuro) Configurar Apple Pay / In-App Purchases** se houver monetização in-app.

---

## 📅 Calendário Estimado (cenário realista)

| Dia | Marco |
|-----|-------|
| **D+0 (hoje)** | Coletar pré-requisitos; criar Apple ID corporativo + 2FA; submeter DUNS lookup |
| **D+1 a D+10** | 🟡 Janela DUNS (Dun & Bradstreet processa) — sem ação, só monitorar email |
| **D+10** | DUNS aprovado → submeter enrollment Apple Developer + pagamento USD 99 |
| **D+11 a D+13** | Apple liga para verificação; possível upload de Contrato Social |
| **D+14** | ✅ Membership "Active" — Team ID disponível |
| **D+14** | Registrar Bundle ID + criar App entry no App Store Connect |
| **D+14** | Gerar APNs `.p8` + salvar no vault |
| **D+15** | Gerar CSR no Mac + Distribution Cert + Provisioning Profile |
| **D+15** | ✅ TASK-047.17 done → desbloqueia TASK-047.16 e TASK-047.21 |

> Cenário pessimista: +7 a +14 dias se DUNS exigir correção ou Apple solicitar documentos extras.

---

## 🚀 Ações que Rafael pode fazer HOJE (D+0) para destravar

1. **Baixar cartão CNPJ atualizado** em https://solucoes.receita.fazenda.gov.br/Servicos/cnpjreva/Cnpjreva_Solicitacao.asp e salvar PDF
2. **Criar email `developer@interazap.com.br`** no provedor corporativo (se ainda não existe)
3. **Criar Apple ID corporativo** novo em https://appleid.apple.com usando o email acima + ativar 2FA
4. **Verificar limite e habilitação internacional** do cartão de crédito empresarial (ligar para banco e avisar que haverá cobrança da Apple Inc. em USD)
5. **Submeter DUNS lookup** em https://developer.apple.com/enrollment/duns-lookup/ — esse é o passo que mais consome tempo de espera, então quanto antes melhor
6. **Reunir PDFs de Contrato Social consolidado e comprovante de endereço** em pasta `~/Documents/InteraZap/legal/` para upload rápido se Apple solicitar
7. **Adicionar `@dnb.com` e `@apple.com` à allowlist do email** para não cair no spam
8. **Confirmar com sócios** quem será o signatário (Account Holder) — preferencialmente quem está no Contrato Social com poderes de administração

---

**Referências oficiais:**
- Apple Developer Program: https://developer.apple.com/programs/
- Enrollment FAQ: https://developer.apple.com/support/enrollment/
- App Store Connect Help: https://developer.apple.com/help/app-store-connect/
- DUNS Lookup: https://developer.apple.com/enrollment/duns-lookup/
- Push Notifications guide: https://developer.apple.com/documentation/usernotifications
