# Checklist de Validação E2E Mobile — InteraZap

> **Contexto:** FEAT-047 TASK-047.20  
> **Validação em devices reais antes de submissão para as stores**  
> **Responsável:** QA + PM

---

## Dispositivos de Referência

| Plataforma | Device        | OS         | Status      |
| ---------- | ------------- | ---------- | ----------- |
| iOS        | iPhone 15 Pro | iOS 17.4+  | ⏳ Pendente |
| Android    | Pixel 8       | Android 14 | ⏳ Pendente |

---

## Como usar este checklist

1. Instalar build de **release** (não debug): `.ipa` via TestFlight, `.aab` via Play Internal Testing
2. Para cada CA abaixo: executar o teste, marcar `[x]` e anexar evidência (screenshot ou vídeo curto)
3. Bugs encontrados → criar task `FIX-047-NNN` antes de prosseguir para submissão

---

## CA-001 — Login e autenticação Bearer

**Pré-condição:** Conta de atendente válida em tenant de staging

| #   | Passo                                             | iOS | Android | Evidência |
| --- | ------------------------------------------------- | --- | ------- | --------- |
| 1.1 | Abrir app pela primeira vez                       | ⏳  | ⏳      |           |
| 1.2 | Tela de login aparece (hash routing: `#/login`)   | ⏳  | ⏳      |           |
| 1.3 | Digitar e-mail + senha → botão Login              | ⏳  | ⏳      |           |
| 1.4 | Token Bearer recebido e salvo (Keychain/Keystore) | ⏳  | ⏳      |           |
| 1.5 | App navega para `#/chat` após login bem-sucedido  | ⏳  | ⏳      |           |
| 1.6 | Reabrir app: sessão restaurada, sem re-login      | ⏳  | ⏳      |           |
| 1.7 | Logout: token revogado, volta para `#/login`      | ⏳  | ⏳      |           |

---

## CA-002 — Caixa de entrada (inbox)

| #   | Passo                                                     | iOS | Android | Evidência |
| --- | --------------------------------------------------------- | --- | ------- | --------- |
| 2.1 | Lista de conversas carrega após login                     | ⏳  | ⏳      |           |
| 2.2 | Filtros de status (aberto, pendente, resolvido) funcionam | ⏳  | ⏳      |           |
| 2.3 | Pull-to-refresh atualiza a lista                          | ⏳  | ⏳      |           |
| 2.4 | Scroll suave em lista com 50+ conversas                   | ⏳  | ⏳      |           |
| 2.5 | Tap em conversa navega para o chat                        | ⏳  | ⏳      |           |

---

## CA-003 — Conversa de chat

| #   | Passo                                               | iOS | Android | Evidência |
| --- | --------------------------------------------------- | --- | ------- | --------- |
| 3.1 | Histórico de mensagens carrega (paginação)          | ⏳  | ⏳      |           |
| 3.2 | Campo de texto focado sem ser coberto pelo teclado  | ⏳  | ⏳      |           |
| 3.3 | Enviar mensagem de texto → aparece na lista         | ⏳  | ⏳      |           |
| 3.4 | Mensagem enviada aparece no painel web do atendente | ⏳  | ⏳      |           |
| 3.5 | Mensagem recebida em tempo real via WebSocket       | ⏳  | ⏳      |           |
| 3.6 | Scroll para o final ao receber nova mensagem        | ⏳  | ⏳      |           |
| 3.7 | Indicador de "digitando..." visível                 | ⏳  | ⏳      |           |

---

## CA-004 — Upload de mídia (câmera/galeria)

| #   | Passo                                                        | iOS | Android | Evidência |
| --- | ------------------------------------------------------------ | --- | ------- | --------- |
| 4.1 | Botão de anexo abre menu: "Tirar foto", "Galeria", "Arquivo" | ⏳  | ⏳      |           |
| 4.2 | "Tirar foto" → câmera nativa abre → foto enviada para chat   | ⏳  | ⏳      |           |
| 4.3 | "Galeria" → picker de fotos abre → imagem enviada            | ⏳  | ⏳      |           |
| 4.4 | Permissão de câmera negada → mensagem de erro clara          | ⏳  | ⏳      |           |
| 4.5 | Imagem enviada aparece no chat do cliente                    | ⏳  | ⏳      |           |
| 4.6 | PDF selecionado → enviado e visualizável pelo destinatário   | ⏳  | ⏳      |           |

---

## CA-005 — Push Notifications

| #   | Passo                                                                          | iOS | Android | Evidência |
| --- | ------------------------------------------------------------------------------ | --- | ------- | --------- |
| 5.1 | Na primeira abertura: modal de permissão de push aparece                       | ⏳  | ⏳      |           |
| 5.2 | Aceitar permissão → token registrado no backend (`POST /api/devices/register`) | ⏳  | ⏳      |           |
| 5.3 | App em background: nova mensagem chega como push                               | ⏳  | ⏳      |           |
| 5.4 | App fechado (cold start): nova mensagem chega como push                        | ⏳  | ⏳      |           |
| 5.5 | Tap na notificação: abre app direto na conversa correta                        | ⏳  | ⏳      |           |
| 5.6 | Push recebido em foreground: badge incrementa + haptic                         | ⏳  | ⏳      |           |
| 5.7 | Logout: token de push revogado (`DELETE /api/devices/:id`)                     | ⏳  | ⏳      |           |

---

## CA-006 — WebSocket em tempo real

| #   | Passo                                                       | iOS | Android | Evidência |
| --- | ----------------------------------------------------------- | --- | ------- | --------- |
| 6.1 | Conexão WebSocket estabelecida após login                   | ⏳  | ⏳      |           |
| 6.2 | Canal privado autorizado com Bearer Token                   | ⏳  | ⏳      |           |
| 6.3 | Mensagem enviada via web aparece no app em < 2s             | ⏳  | ⏳      |           |
| 6.4 | App voltando do background (resume): WS reconecta           | ⏳  | ⏳      |           |
| 6.5 | Notificação de nova conversa aparece no inbox em tempo real | ⏳  | ⏳      |           |

---

## CA-007 — Modo offline

| #   | Passo                                                          | iOS | Android | Evidência |
| --- | -------------------------------------------------------------- | --- | ------- | --------- |
| 7.1 | Ativar airplane mode: app continua visível (sem crash)         | ⏳  | ⏳      |           |
| 7.2 | Tentar enviar mensagem offline: ícone "pendente" aparece       | ⏳  | ⏳      |           |
| 7.3 | Desativar airplane mode: mensagem pendente é enviada (FIFO)    | ⏳  | ⏳      |           |
| 7.4 | Fechar app offline → reabrir → mensagens pendentes preservadas | ⏳  | ⏳      |           |

---

## CA-008 — Safe Areas (notch/barra de navegação)

| #   | Passo                                                 | iOS | Android | Evidência |
| --- | ----------------------------------------------------- | --- | ------- | --------- |
| 8.1 | Header não coberto pelo notch do iPhone               | ⏳  | N/A     |           |
| 8.2 | Footer não coberto pela barra home do iOS             | ⏳  | N/A     |           |
| 8.3 | Footer não coberto pela barra de navegação do Android | N/A | ⏳      |           |
| 8.4 | Campo de input visível com teclado aberto             | ⏳  | ⏳      |           |

---

## CA-009 — Botão voltar Android

| #   | Passo                                                     | iOS | Android | Evidência |
| --- | --------------------------------------------------------- | --- | ------- | --------- |
| 9.1 | Em conversa: botão voltar volta para a lista              | N/A | ⏳      |           |
| 9.2 | Na lista (rota raiz): botão voltar mostra confirm "Sair?" | N/A | ⏳      |           |
| 9.3 | Modal aberto: botão voltar fecha o modal primeiro         | N/A | ⏳      |           |

---

## CA-010 — Splash Screen e Status Bar

| #    | Passo                                          | iOS | Android | Evidência |
| ---- | ---------------------------------------------- | --- | ------- | --------- |
| 10.1 | Splash screen aparece ao abrir app             | ⏳  | ⏳      |           |
| 10.2 | Splash some após app carregar (sem travamento) | ⏳  | ⏳      |           |
| 10.3 | Status bar com cor teal (#14b8a6)              | ⏳  | ⏳      |           |
| 10.4 | Ícone do app na home com branding correto      | ⏳  | ⏳      |           |

---

## CA-011 — Tenants distintos (isolamento)

| #    | Passo                                                               | iOS | Android | Evidência |
| ---- | ------------------------------------------------------------------- | --- | ------- | --------- |
| 11.1 | Login com user de tenant A → vê apenas conversas do tenant A        | ⏳  | ⏳      |           |
| 11.2 | Mesmo device: logout tenant A → login tenant B → vê apenas tenant B | ⏳  | ⏳      |           |
| 11.3 | Token de push do tenant A revogado no logout                        | ⏳  | ⏳      |           |

---

## CA-012 — Performance

| #    | Teste                                           | iOS | Android | Métrica    |
| ---- | ----------------------------------------------- | --- | ------- | ---------- |
| 12.1 | Tempo de cold start (tap ícone → inbox visível) | ⏳  | ⏳      | < 3s       |
| 12.2 | Scroll em conversa com 200 mensagens            | ⏳  | ⏳      | 60fps      |
| 12.3 | Scroll em inbox com 50 conversas                | ⏳  | ⏳      | 60fps      |
| 12.4 | Upload de foto 5MB: tempo de envio              | ⏳  | ⏳      | < 10s (4G) |

---

## CA-013 — Sessão de 30 minutos (Crash-Free)

| #    | Passo                                                  | iOS | Android | Evidência        |
| ---- | ------------------------------------------------------ | --- | ------- | ---------------- |
| 13.1 | Usar o app continuamente por 30 minutos: zero crashes  | ⏳  | ⏳      | Sentry dashboard |
| 13.2 | Trocar de apps (background/foreground) múltiplas vezes | ⏳  | ⏳      |                  |
| 13.3 | Receber 10+ mensagens em sequência rápida              | ⏳  | ⏳      |                  |
| 13.4 | Enviar 10+ mensagens em sequência rápida               | ⏳  | ⏳      |                  |

---

## CA-014 — Acessibilidade mínima

| #    | Teste                                                     | iOS | Android | Evidência |
| ---- | --------------------------------------------------------- | --- | ------- | --------- |
| 14.1 | Texto legível em fonte grande (acessibilidade do sistema) | ⏳  | ⏳      |           |
| 14.2 | Botões com área de toque mínima de 44×44pt                | ⏳  | ⏳      |           |
| 14.3 | Contraste de texto adequado (WCAG AA)                     | ⏳  | ⏳      |           |

---

## CA-015 — Build Release (não Debug)

| #    | Teste                                                       | iOS | Android | Evidência |
| ---- | ----------------------------------------------------------- | --- | ------- | --------- |
| 15.1 | Build instalado via TestFlight (não Xcode direto)           | ⏳  | N/A     |           |
| 15.2 | Build instalado via Play Internal Testing (não adb direto)  | N/A | ⏳      |           |
| 15.3 | ProGuard/R8 não quebrou funcionalidade de plugins Capacitor | N/A | ⏳      |           |
| 15.4 | Tamanho do app: `.ipa` < 80MB, `.aab` < 50MB                | ⏳  | ⏳      |           |

---

## CA-016 — Sentry Crash Reporting

| #    | Teste                                          | iOS | Android | Evidência     |
| ---- | ---------------------------------------------- | --- | ------- | ------------- |
| 16.1 | Crash forçado aparece no Sentry em < 5 min     | ⏳  | ⏳      | Dashboard URL |
| 16.2 | Stack trace desobfuscado (sourcemap funcional) | ⏳  | ⏳      |               |
| 16.3 | Evento contém `tenant_id` e `platform`         | ⏳  | ⏳      |               |
| 16.4 | Evento NÃO contém conteúdo de mensagem         | ⏳  | ⏳      |               |

---

## Resultado Final

| Plataforma | Total CAs | Aprovados | Com ressalva | Reprovados |
| ---------- | --------- | --------- | ------------ | ---------- |
| iOS        | 16        | —         | —            | —          |
| Android    | 16        | —         | —            | —          |

### Decisão de go/no-go

- **GO:** 0 reprovados + crash-free sessions > 99% em Sentry
- **NO-GO:** Qualquer CA crítico (001–006, 013, 015) reprovado sem fix imediato

---

## Log de Bugs Encontrados

| Bug | CA  | Device | Severidade | Task | Status |
| --- | --- | ------ | ---------- | ---- | ------ |
| —   | —   | —      | —          | —    | —      |

---

## Evidências

Adicionar links de screenshots/vídeos abaixo após execução:

```
iOS:
- CA-001:
- CA-003:
- CA-005:

Android:
- CA-001:
- CA-003:
- CA-005:
```
