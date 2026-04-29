# Memory: GATEWAY_TIMEOUT mínimo de 35s para connect Uazapi

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | ⚠️ Armadilha |
| **Data** | 2026-04-26 |
| **Autor** | ORCHESTRATOR |
| **Contexto** | Bug QR Code não gerava em produção |
| **Tags** | gateway, uazapi, timeout, qr-code, infra |

---

## Situação

`POST /api/channels/{id}/connect` retornava sucesso silencioso mas sem QR code. Nos logs da Uazapi aparecia `lastDisconnectReason: "QR Code timeout"` — o QR era gerado mas ninguém escaneava porque **a API Laravel nunca recebia o QR**.

Causa raiz: `GATEWAY_TIMEOUT=3` (3 segundos) em `services.gateway.timeout`. A Uazapi leva **~13 segundos** para gerar o QR no endpoint `/instance/connect` (operação blocante). O cURL do Laravel expirava com `cURL error 28: Operation timed out after 3002ms` antes de receber o response.

---

## Decisão / Aprendizado

`GATEWAY_TIMEOUT` deve ser **mínimo 35 segundos** para suportar o connect QR da Uazapi.

- O QR é retornado **no body do response** do `/instance/connect`, não via webhook
- A operação é **blocante** no lado da Uazapi (~13s)
- Default do `config/services.php` é `(int) env('GATEWAY_TIMEOUT', 3)` — **o default é inadequado para connect**
- Solução: `GATEWAY_TIMEOUT=35` em `infra/ansible/roles/app-deploy/tasks/main.yml`

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Aumentar só o timeout do connect (HTTP client separado) | Requer refatoração do `GatewayHttpClient`; GATEWAY_TIMEOUT=35 é suficiente para todos os casos |
| Tornar connect assíncrono (webhook quando QR pronto) | Requer mudança na Uazapi e no fluxo do frontend; escopo excessivo |
| Reduzir `GATEWAY_RETRY_ATTEMPTS=3` → `1` | Com timeout=35s, retries só acontecem em 5xx/ConnectionException; não afeta o QR |

---

## Consequências

### Positivas
- QR code chega ao frontend corretamente
- `status` da instância muda para `"qr"` no response
- Fluxo de conectar instância WhatsApp funciona ponta-a-ponta

### Negativas / Trade-offs
- Todos os requests ao Gateway agora têm timeout de 35s (não só connect); requests normais são muito mais rápidos (<500ms) então não há impacto prático

---

## Referências
- Commit fix: `2a2b529`
- Arquivo: `infra/ansible/roles/app-deploy/tasks/main.yml:162`
- Config: `api/config/services.php` → `services.gateway.timeout`
