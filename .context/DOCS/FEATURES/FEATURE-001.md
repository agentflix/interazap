# FEATURE-001 — Auto-fechar chamado ao sair do chat público

## Problema
Quando o visitante fecha a aba/navegador durante um atendimento WebChat, o ticket permanece
com status `open` no backend indefinidamente. O socket desconecta mas o `handleDisconnect`
no Gateway apenas loga — não executa nenhuma ação de limpeza.

## Usuários Impactados
- **Operadores:** veem chamados ativos sem visitante na outra ponta
- **Supervisores:** métricas de chamados abertos ficam infladas
- **Visitantes:** se voltarem, precisam iniciar novo chamado (comportamento já existente)

## Solução
Implementar auto-close no **Gateway NestJS** via `handleDisconnect` com grace period.

Quando o socket desconecta, o Gateway inicia um timer de 60s. Se o cliente reconectar
com o mesmo `sessionId` antes do timer disparar, o timer é cancelado. Se o timer disparar,
o Gateway chama o endpoint `POST /api/webchat/close` via `webchatProxyService`.

### Por que não usar `beforeunload` + `sendBeacon` no frontend?
O evento `beforeunload` dispara também em **refresh de página**, o que fecharia o chamado
de um visitante que apenas atualizou a aba. Isso exigiria lógica extra de "reabrir chamado"
que aumenta a complexidade sem benefício real. O Gateway cobre todos os cenários com
uma única implementação.

## Requisitos Funcionais
- [RF-01]: Ao detectar desconexão de socket, iniciar timer de 60 segundos antes de fechar
- [RF-02]: Se o cliente reconectar com o mesmo `sessionId` dentro do grace period, cancelar o timer
- [RF-03]: Após o timer, chamar `POST /api/webchat/close` usando o token armazenado em `socket.data`
- [RF-04]: Não fechar chamados já marcados como `closed`
- [RF-05]: Registrar em log quando o auto-close for disparado (sessionId, tenantId, motivo)

## Requisitos Não Funcionais
- [RNF-01]: Grace period configurável via variável de ambiente (`WEBCHAT_AUTOCLOSE_DELAY_MS`, default 60000)
- [RNF-02]: A estrutura de timers pendentes deve ser limpa corretamente para evitar memory leak
- [RNF-03]: Não bloquear o event loop do NestJS (usar `setTimeout`, não sleep)

## Critérios de Aceite
- [CA-01]: Dado que um visitante está em um chamado ativo, Quando fechar a aba do navegador, Então após 60s o chamado deve estar com status `closed` no backend
- [CA-02]: Dado que um visitante fecha e reabre a aba dentro de 60s, Quando reconectar com mesmo sessionId, Então o chamado deve continuar `open`
- [CA-03]: Dado que a rede do visitante cai e volta dentro de 60s, Quando o socket reconectar, Então o chamado deve continuar `open`
- [CA-04]: Dado que um chamado já está `closed`, Quando o socket desconectar, Então o Gateway não deve tentar fechar novamente

## Fora de Escopo
- Notificar o operador em tempo real que o visitante saiu (pode ser feature futura)
- Reabrir chamado automaticamente se o visitante voltar após o fechamento
- Frontend `sendBeacon` (complexidade extra sem ganho suficiente)

## Riscos
- [R-01]: Token expirado quando o timer disparar → Mitigação: o token tem validade suficiente (verificar TTL do JWT webchat vs grace period)
- [R-02]: Memory leak por timers não cancelados → Mitigação: usar Map com limpeza explícita em `handleDisconnect` e `handleConnection`
- [R-03]: Múltiplas instâncias do Gateway (horizontal scaling) → Mitigação: usar Redis para coordenar o timer (fase futura; para MVP com uma instância, setTimeout é suficiente)

## Arquivos Afetados
- `gateway/src/domains/realtime/gateways/webchat.gateway.ts` — adicionar lógica de timer
- `gateway/src/domains/realtime/services/webchat-proxy.service.ts` — verificar se já tem método de close ou adicionar
- Nenhuma mudança no frontend ou na API Laravel

---

## Tasks (TACE)

### TASK-001: Implementar auto-close no Gateway
**T (Tarefa):** Modificar `webchat.gateway.ts` para gerenciar timers de auto-close no `handleDisconnect` e cancelá-los no `handleConnection`

**A (Ação):**
1. Adicionar `private pendingCloses = new Map<string, NodeJS.Timeout>()` na classe
2. Em `handleConnection`: se existir timer para o `sessionId`, cancelar com `clearTimeout`
3. Em `handleDisconnect`: se o ticket não estiver fechado, iniciar timer de `WEBCHAT_AUTOCLOSE_DELAY_MS`
4. No callback do timer: chamar `webchatProxyService.closeSession(token)` e logar

**C (Critério):** Timer cancelado no reconect; chamado fechado após grace period sem reconexão; sem memory leak

**E (Entrega):** `webchat.gateway.ts` modificado com os timers funcionando

### TASK-002: Garantir método de close no ProxyService
**T:** Verificar se `webchat-proxy.service.ts` já expõe método para fechar chamado via token; adicionar se necessário

**A:** Ler o arquivo; se não houver método `closeSession(token)`, adicionar chamada `POST /api/webchat/close` com o token no header

**C:** Serviço consegue chamar o endpoint de close de forma autônoma (sem req do cliente)

**E:** Método `closeSession` disponível no `WebChatProxyService`
