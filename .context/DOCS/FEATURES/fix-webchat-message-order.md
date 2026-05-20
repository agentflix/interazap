# Feature: fix-webchat-message-order

## Problema

Mensagens no webchat público aparecem em ordem cronológica errada: a resposta da IA/atendente aparece ACIMA da mensagem do visitante, mesmo quando o visitante enviou primeiro.

## Causa Raiz

Os eventos WebSocket `webchat:ai_response` e `webchat:agent_message` chegam do backend (Laravel) com `created_at` em snake_case. O frontend mapeia explicitamente `fileUrl`, `mimeType` e `fileName`, mas esqueceu de mapear `createdAt`.

Resultado: `message.createdAt` fica `undefined` → `Date.parse('')` → `NaN` → tratado como `0` (epoch 1970) → mensagens da IA/atendente sempre ordenam primeiro.

## Solução

Adicionar `createdAt` mapping nos dois handlers em `webchat.service.ts`, mesmo padrão de `fileUrl`/`mimeType`/`fileName`.

## Escopo

- 1 arquivo Angular
- 2 lugares no mesmo arquivo
- Sem migração, sem novo endpoint, sem mudança de backend

## Status

✅ Concluída

## Tasks Implementadas

- TASK-2.1.1: Correção do mapeamento createdAt nos handlers WebSocket
- TASK-3.1.1: Correção do sort de mensagens no chat interno (agente)

## Commits

- `fix(app): corrige ordenação de mensagens no webchat e chat interno`
