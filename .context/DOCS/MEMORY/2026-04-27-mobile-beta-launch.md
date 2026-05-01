# Mobile Beta Launch — TestFlight + Play Internal Testing

## Metadados

| Campo        | Valor                                                 |
| ------------ | ----------------------------------------------------- |
| **Tipo**     | 📋 Processo                                           |
| **Data**     | 2026-04-27                                            |
| **Contexto** | FEAT-047 TASK-047.21                                  |
| **Tags**     | mobile, testflight, play-internal, beta, distribution |

---

## Pré-requisitos para Upload

Antes de executar o processo de beta launch, confirmar:

- [ ] TASK-047.17 executada: Apple Developer Account ativa, App ID criado, certificates e provisioning profile prontos
- [ ] TASK-047.18 executada: Google Play Developer Account ativa, Firebase configurado, `google-services.json` no repo
- [ ] CI `mobile-ios.yml` rodou com sucesso: `.ipa` assinado gerado
- [ ] CI `mobile-android.yml` rodou com sucesso: `.aab` assinado gerado
- [ ] TASK-047.20 executada: CA-001 a CA-016 aprovados (ou com ressalvas documentadas)

---

## Parte 1 — TestFlight (iOS)

### Upload via CI (recomendado)

O workflow `.github/workflows/mobile-ios.yml` inclui upload automático via `altool`/`xcrun notarytool`.

```yaml
# Trecho relevante do CI (já configurado em TASK-047.16)
- name: Upload to TestFlight
  env:
      APP_STORE_CONNECT_API_KEY: ${{ secrets.APP_STORE_CONNECT_API_KEY }}
  run: |
      xcrun altool --upload-app \
        --type ios \
        --file "$IPA_PATH" \
        --apiKey "$APP_STORE_CONNECT_API_KEY_ID" \
        --apiIssuer "$APP_STORE_CONNECT_ISSUER_ID"
```

### Upload manual (fallback)

```bash
# Via Xcode → Organizer → Distribute App → App Store Connect → Upload
# OU
xcrun altool --upload-app -f App.ipa -t ios \
  --apiKey <KEY_ID> --apiIssuer <ISSUER_ID>
```

### Configuração TestFlight

1. Acesse App Store Connect → **TestFlight** → sua build
2. Aguardar processamento (~15min) + review automático de compliance (~24h)
3. **External Testers** → criar grupo "InteraZap Beta" → adicionar e-mails
4. Limite máximo: 10.000 testers externos (usar internal group para < 25 pessoas primeiro)
5. Habilitar **automatic distribution** para builds futuras do mesmo grupo

### Informações do TestFlight (obrigatórias)

```
What to Test:
- Login com credenciais de staging
- Enviar e receber mensagens no chat
- Testar push notifications em background
- Upload de foto via câmera

Beta App Description:
InteraZap — Aplicativo de atendimento multicanal para atendentes.
Esta é uma versão beta para testes internos.

Feedback Email: contato@interazap.com.br
Marketing URL: https://interazap.com.br
Privacy Policy URL: https://interazap.com.br/privacy
```

### Checklist TestFlight

- [ ] Build aparece no TestFlight com status "Ready to Test"
- [ ] E-mail de convite enviado para mínimo 10 testers
- [ ] Testers confirmaram instalação
- [ ] Canal de feedback criado (Slack #mobile-beta ou form)
- [ ] Data de expiração da build notada (TestFlight expira em 90 dias)

---

## Parte 2 — Play Internal Testing (Android)

### Upload via Play Console

O Google Play não suporta upload automático via CLI pública no mesmo fluxo do Xcode. Usar **Google Play Developer API** ou upload manual.

#### Upload manual

1. Acesse [Play Console → App → Testing → Internal testing](https://play.google.com/console)
2. **Create new release**
3. Upload o `.aab` gerado pela CI
4. Preencher release notes (PT-BR + EN):

    ```
    PT-BR:
    • Login e autenticação segura
    • Caixa de entrada de conversas
    • Chat em tempo real
    • Push notifications
    • Upload de foto e arquivo

    EN:
    • Secure login and authentication
    • Conversation inbox
    • Real-time chat
    • Push notifications
    • Photo and file upload
    ```

5. **Save → Review release → Start rollout**

#### Upload via API (CI — opcional)

```bash
# Usar fastlane supply (requer service account JSON)
bundle exec fastlane supply \
  --aab app/android/app/build/outputs/bundle/release/app-release.aab \
  --track internal \
  --json_key path/to/service-account.json \
  --package_name com.interazap.app
```

### Adicionar testers ao Internal Testing

1. Play Console → Internal testing → **Testers**
2. **Create email list** → "InteraZap Testers" → adicionar e-mails
3. Copiar link de opt-in (formato: `https://play.google.com/apps/internaltest/...`)
4. Enviar link para testers via e-mail/Slack

### Checklist Play Internal Testing

- [ ] `.aab` aceito sem erros de validação no Play Console
- [ ] Release em status "Active" no track Internal testing
- [ ] Link de opt-in enviado para mínimo 10 testers
- [ ] Testers confirmaram instalação (`Install tester app` opt-in)
- [ ] Crash rate inicial < 1% nas primeiras 24h

---

## Parte 3 — Coordenação de Testers

### Perfil do tester ideal

- Atendente interno que usa o sistema web diariamente
- Mix de iOS (iPhone) e Android (variados modelos)
- Disponível para feedback em 7 dias

### Processo de feedback

1. Criar formulário Google Forms (ou Notion) com:
    - Qual device e OS?
    - O app iniciou sem erro? (S/N)
    - Conseguiu fazer login? (S/N)
    - Recebeu push notifications? (S/N)
    - Principais problemas encontrados (texto livre)
    - Nota geral de 1 a 5
2. Enviar formulário junto com convite de teste
3. Cobrar respostas após 3 dias

### SLA de resposta a bugs críticos

| Severidade   | Definição                             | SLA de Fix    |
| ------------ | ------------------------------------- | ------------- |
| P1 - Crítico | Crash, loop de login, dados errados   | 24h           |
| P2 - Alto    | Feature core quebrada, push não chega | 3 dias        |
| P3 - Médio   | UX ruim, edge case                    | Próxima build |
| P4 - Baixo   | Cosmético, textos                     | Backlog       |

---

## Cronograma de Beta

```
Semana 1 (beta launch):
  Dia 1: Upload builds + convite testers
  Dia 2-7: Coleta de feedback / fixes P1 + P2

Semana 2 (play closed testing início):
  Dia 8: Iniciar track Closed Testing (Play) — obrigatório 14 dias
  Dia 8-14: Monitoramento + fixes P3

Semana 3 (conclusão beta):
  Dia 15-21: 14 dias de Closed Testing completos
  Dia 21: Decidir go/no-go para Production
```

---

## Evidências a registrar

Após execução:

- URL da build no TestFlight: `___`
- URL do track no Play Console: `___`
- Data de início do beta: `___`
- Número de testers iOS instalados: `___`
- Número de testers Android instalados: `___`
- Data de encerramento do beta: `___`
- Resultado: ✅ GO / ❌ NO-GO
