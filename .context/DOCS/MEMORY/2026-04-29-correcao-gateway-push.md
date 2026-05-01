---
date: 2026-04-29
title: "Correcao de erros no gateway - FEAT-047 Fase 5"
tags: [gateway, push-notifications, pest, paratest, composer, php]
---

# Correcao de erros no gateway - FEAT-047 Fase 5

## Problemas encontrados

### 1. Pacotes APN/FCM com versoes incompatíveis no composer.json

O `composer.json` tinha:
- `laravel-notification-channels/apn: ^2.0` — requer PHP ^7.2.5 (incompatível com PHP 8.5)
- `laravel-notification-channels/fcm: ^5.4` — versao nao existe (serie 5.x vai ate 5.1.0)

**Correcao:** Atualizado para `^6.0` em ambos.

### 2. Pacotes nao instalados (faltando no composer.lock)

O `composer.json` foi editado manualmente para adicionar os pacotes, mas `composer update` nunca foi rodado. O `composer.lock` nao continha as dependencias, entao as classes `ApnChannel`, `FcmChannel`, etc. nao existiam no vendor.

**Correcao:** `composer update laravel-notification-channels/apn laravel-notification-channels/fcm`

### 3. Testes travando por deadlocks no PostgreSQL

O Pest 4.x requer `brianium/paratest` e habilita paralelismo automaticamente. Isso causa:
- Criacao de bancos temporarios (`agentflix_test_test_1`, etc.)
- Deadlocks no PostgreSQL quando `LazilyRefreshDatabase` tenta `DROP TABLE ... CASCADE` em multiplos processos simultaneamente
- Timeouts de 120s+ nos testes

**Sintoma do deadlock:**
```
SQLSTATE[40P01]: Deadlock detected
Process X waits for AccessExclusiveLock on relation Y
Process Z waits for RowShareLock on relation W
```

**Solucao de workaround:**
1. Rodar testes com `-p1` (single process): `php vendor/bin/pest <file> -p1`
2. Criar banco temporario a partir do template antes de rodar:
   ```sql
   CREATE DATABASE agentflix_test_test_1 TEMPLATE agentflix_test;
   ```

**Solucao definitiva recomendada:**
Considerar desabilitar paralelismo no Pest para ambiente local, ou migrar para `RefreshDatabase` em vez de `LazilyRefreshDatabase` se o overhead for aceitavel.

## Evidencias de sucesso

Apos as correcoes, todos os testes do escopo gateway/push passam:

| Teste | Status | Assertions |
|-------|--------|------------|
| GatewayBroadcastServiceTest | 9 passed | 14 |
| DeviceTokenRegistrationTest | 4 passed | 17 |
| NewMessageNotificationTest | 4 passed | 7 |

## Arquivos afetados

- `api/composer.json` (versoes dos pacotes APN/FCM)
- `api/composer.lock` (atualizado com as novas dependencias)

## Decisoes

- Manter paratest instalado (requerido pelo Pest 4.x) mas documentar a necessidade de usar `-p1` em ambiente local com PostgreSQL.
- Nao modificar o codigo dos testes (mantendo `LazilyRefreshDatabase`) para nao introduzir regressoes.
