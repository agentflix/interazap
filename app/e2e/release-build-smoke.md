# Smoke Test — Release Build Mobile

> Checklist manual de validação de build release (R8 minificado).  
> Executar antes de publicar na Google Play ou App Store.

## Pré-requisitos

- [ ] Build `.aab` gerado com `minifyEnabled true` e `shrinkResources true`
- [ ] Build `.ipa` gerado em modo Release
- [ ] Instalado em dispositivo físico (ou emulador Release)
- [ ] Back-end de staging apontado ou produção

---

## Fluxo Crítico — Android

| #   | Ação                                               | Resultado esperado                                |
| --- | -------------------------------------------------- | ------------------------------------------------- |
| 1   | Abrir app                                          | Splash screen exibe logo sem crash                |
| 2   | Tela de login → credenciais válidas                | Login bem-sucedido, redireciona para Inbox        |
| 3   | Inbox carrega                                      | Lista de conversas renderiza (sem tela branca)    |
| 4   | Abrir conversa                                     | Mensagens históricas exibidas                     |
| 5   | Enviar mensagem de texto                           | Mensagem aparece com status "enviado"             |
| 6   | Tirar foto via ícone de câmera                     | Camera Plugin abre, foto capturada e enviada      |
| 7   | Receber push notification                          | Notificação aparece no sistema; tap abre conversa |
| 8   | Minimizar e retomar app                            | Sessão mantida, WebSocket reconecta               |
| 9   | Deep link `https://app.interazap.com.br/chat/{id}` | App abre e navega para conversa                   |
| 10  | Logout e relogin                                   | Tokens limpos; novo login funciona                |

---

## Fluxo Crítico — iOS

| #   | Ação                                                    | Resultado esperado              |
| --- | ------------------------------------------------------- | ------------------------------- |
| 1   | Abrir app                                               | Splash screen sem crash         |
| 2   | Login                                                   | Bem-sucedido                    |
| 3   | Inbox                                                   | Renderiza corretamente          |
| 4   | Enviar mensagem                                         | Enviada com sucesso             |
| 5   | Tirar foto                                              | Camera Plugin funciona          |
| 6   | Universal Link `https://app.interazap.com.br/chat/{id}` | App abre e navega para conversa |
| 7   | Push notification → tap                                 | Abre conversa correta           |
| 8   | Background + retorno                                    | Sessão mantida                  |

---

## Validações de R8/ProGuard

| Verificação                                         | Como validar                                                             |
| --------------------------------------------------- | ------------------------------------------------------------------------ |
| Sem crash ao abrir (ProGuard não quebrou Capacitor) | App abre e login funciona                                                |
| Plugins nativos respondem                           | Câmera, haptics, status bar, push — todos funcionais                     |
| Stack trace legível no Sentry                       | Forçar crash de teste → verificar no Sentry se linhas são identificáveis |
| Assets comprimidos                                  | `aab` / `ipa` menor que build anterior sem R8                            |

---

## Critério de Aprovação

- Todos os 10 itens Android marcados ✅
- Todos os 8 itens iOS marcados ✅
- Nenhum crash `ClassNotFoundException` ou `NoSuchMethodException` nos logs

---

## Histórico

| Data | Versão | Resultado | Testador |
| ---- | ------ | --------- | -------- |
|      |        |           |          |
