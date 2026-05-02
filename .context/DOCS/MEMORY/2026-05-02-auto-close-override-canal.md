# Auto-Close Inatividade — Override de Canal vs Global

**Data:** 2026-05-02
**Módulo:** Chat / Auto-Close

## Aprendizado

O bug reportado (early return em `CloseInactiveTicketsAction` quando `auto_close_inactivity_enabled` global é `false`) **já estava corrigido** no código-fonte.

A implementação atual já delega corretamente a decisão para `getEffectiveAutoCloseConfig()` de cada canal:
- `ChatInstance::getEffectiveAutoCloseConfig()` faz merge canal → tenant via `??` (null coalesce)
- A action itera todos os canais ativos e verifica `$config['enabled']` por canal
- Canais com `auto_close_enabled = true` (override) são processados mesmo quando o tenant global está desabilitado

## Evidência

- `api/src/Domain/Chat/Actions/CloseInactiveTicketsAction.php` linhas 54-60: loop por canal com `getEffectiveAutoCloseConfig()`
- `api/src/Domain/Chat/Models/ChatInstance.php` linhas 96-106: merge correto via `??`
- Teste de feature `CloseInactiveTicketsTest.php` cenário 4 (linha 147) PASSA — documenta o comportamento correto

## Implicação

Antes de tentar "corrigir" este bug novamente, verificar se `getEffectiveAutoCloseConfig()` está sendo chamado no loop de canais. Se sim, o comportamento já está correto.

## Teste Unitário Adicionado

`api/tests/Unit/Chat/Actions/CloseInactiveTicketsActionTest.php` cobre 6 cenários:
1. Global disabled + canal override enabled → fecha
2. Global disabled + canal sem override → não fecha
3. Idempotência
4. Target client → `last_customer_message_at`
5. Target agent → `last_agent_message_at`
6. Target both → `last_message_at`

## Arquivos

- `api/tests/Unit/Chat/Actions/CloseInactiveTicketsActionTest.php`
