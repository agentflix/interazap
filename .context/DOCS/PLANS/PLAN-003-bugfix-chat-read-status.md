# PLAN-BUGFIX-002 — Correção do Status de Leitura do Chat (Read Receipt Persistence)

## Objetivo

Corrigir o bug em que o status de leitura (`read`) das mensagens do chat não persiste após reload (F5). O status verde aparece corretamente em tempo real via WebSocket, mas ao recarregar a página, os checks voltam a cinza (`sent`).

## Módulo relacionado

Chat | Gateway

## Escopo

### Incluído

- Diagnóstico da cadeia completa: Gateway (NestJS) → Backend (Laravel) → Banco (PostgreSQL) → API → Frontend (Angular)
- Adição de logs para rastreamento de webhooks de status (`ack=3` / `status='read'`)
- Correção da persistência de `status='read'` e `read_at` no banco de dados
- Verificação do Gateway NestJS como intermediário de webhooks
- Validação end-to-end: enviar mensagem → contato ler → F5 → status verde persistir

### Excluído

- Alterações no fluxo de UI (ícones, cores) — já funcionam corretamente
- Novos testes E2E automatizados (task separada)
- Mudanças no fluxo de `delivered` (ack=2) — foco exclusivo em `read`

## Etapas propostas

1. **Diagnóstico — Verificação direta no banco**
   - Executar query SQL para verificar se `read_at` está sendo populado
   - Determinar se o problema é de persistência (backend) ou de leitura (API/frontend)

2. **Diagnóstico — Logs no Backend**
   - Adicionar logs `info` nos dois caminhos de ingest do `ChatWebhookIngestor`
   - Caminho A: evento sem `message` key (linhas 89-117)
   - Caminho B: evento com `message` key (linhas 149-151)
   - Log no bloco `messages_update` (linhas 137-147) para UazAPI

3. **Diagnóstico — Inspeção do Gateway NestJS**
   - Verificar controller/service que recebe webhooks do WhatsApp
   - Confirmar que eventos de status (`ack=3`, `status='read'`) são encaminhados ao backend
   - Verificar se há transformação/filtragem que descarte o payload de status

4. **Correção — Aplicar fix baseado nos findings**
   - Se persistência falha: corrigir `updateExistingMessageStatus()` ou lookup de `external_id`
   - Se Gateway filtra: ajustar normalização de payload no NestJS
   - Se API não retorna: verificar query columns em `listByTicketCursor()`

5. **Validação — Teste end-to-end**
   - Enviar mensagem pelo chat
   - Confirmar leitura pelo contato no WhatsApp
   - Verificar status verde no frontend
   - Fazer F5 e confirmar que status persiste verde

## Tasks derivadas

| Task | Descrição | Agente | Status |
|------|-----------|--------|--------|
| TASK-003-bugfix-chat-read-status | Diagnosticar e corrigir persistência de read status no chat | @DEBUG / @BACKEND / @FRONTEND | done |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Webhook do provedor (UazAPI/Z-API) envia formato inesperado | Média | Alto | Log do payload bruto para análise |
| Race condition entre gateway confirm e webhook de status | Baixa | Médio | external_id salvo sincronamente antes do webhook chegar |
| Problema no Gateway NestJS filtrando eventos | Média | Alto | Inspecionar código do gateway antes de alterar backend |

### Dependências

- Acesso aos logs do servidor para inspecionar webhooks em tempo real
- Ambiente com instância WhatsApp ativa para testes

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Média |
| Camadas afetadas | Backend / Gateway / (Frontend apenas validação) |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Baixo (bugfix, sem mudança de API) |
