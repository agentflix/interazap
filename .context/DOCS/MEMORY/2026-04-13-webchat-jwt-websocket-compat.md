# Memory: Compatibilidade JWT entre WebChat API e Gateway WebSocket

## Metadados

| Campo        | Valor                                                    |
| ------------ | -------------------------------------------------------- |
| **Tipo**     | ⚠️ Armadilha                                             |
| **Data**     | 2026-04-13                                               |
| **Autor**    | DEBUG Agent                                              |
| **Contexto** | Erro `websocket error` ao iniciar sessão no chat externo |
| **Tags**     | webchat, websocket, jwt, gateway                         |

---

## Situação

Após criar a sessão no webchat público, a tela entrava em estado de erro de conexão WebSocket.

---

## Decisão / Aprendizado

O token JWT emitido pela API de webchat precisa ser compatível com a autenticação do gateway:

- Assinar com o mesmo segredo esperado pelo gateway (`JWT_SECRET` compartilhado; com fallback local definido).
- Incluir claim `sub` no payload (usando `session_id`) além de `tenant_id`.

Sem esses dois pontos, o gateway rejeita o handshake e o frontend recebe `websocket error`.

---

## Alternativas Consideradas

| Alternativa                                | Por que descartada                                                     |
| ------------------------------------------ | ---------------------------------------------------------------------- |
| Manter assinatura com `APP_KEY` do Laravel | Gera assinatura inválida para o `JWT_SECRET` do gateway.               |
| Manter payload sem `sub`                   | Gateway rejeita por claims obrigatórias ausentes (`sub`, `tenant_id`). |

---

## Consequências

### Positivas

- Handshake websocket volta a autenticar com sucesso no fluxo do webchat.
- Contrato de token entre API e gateway fica explícito e testável.

### Negativas / Trade-offs

- Dependência de segredo compartilhado entre serviços exige configuração consistente de ambiente.

---

## Referências

- `api/src/Domain/Chat/Services/WebChatJwtService.php`
- `gateway/src/domains/realtime/services/ws-authentication.service.ts`
- `api/tests/Unit/Chat/Services/WebChatJwtServiceTest.php`
