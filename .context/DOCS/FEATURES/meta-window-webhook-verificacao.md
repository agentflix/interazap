# Roteiro de Verificação Manual — FEAT-006 Meta WhatsApp (janela 24h/72h)

> Feature: `.context/DOCS/FEATURES/meta-window-webhook.md`
> Este roteiro cobre o **item 3 da verificação do plano**: exercitar o webhook ponta a ponta.
> Nada da implementação foi validado contra a Graph API real — toda evidência automatizada é de
> teste unitário e e2e com mocks. **Este roteiro é o que decide se a feature funciona de verdade.**

---

## 0. Pré-requisitos

```bash
# Gateway rodando
pnpm --filter gateway start:dev        # sobe em http://localhost:3000

# API rodando (o gateway resolve instância via HTTP para a api)
cd api && php artisan serve

# Migration aplicada
cd api && php artisan migrate
```

Variáveis necessárias em `gateway/.env`:

| Var | Para quê |
|---|---|
| `META_APP_SECRET` | assinar/validar o HMAC `X-Hub-Signature-256` |
| `META_VERIFY_TOKEN` | handshake GET |
| `META_GRAPH_API_URL` | opcional — default já é `v25.0` |
| `GATEWAY_SECRET` | o gateway consultar a api |

E uma `chat_instances` com `provider = 'meta'` e `settings_json` contendo `phone_number_id` e `access_token`:

```sql
SELECT id, tenant_id, provider,
       settings_json->>'phone_number_id' AS phone_number_id,
       (settings_json->>'access_token') IS NOT NULL AS tem_token
FROM chat_instances WHERE provider = 'meta';
```

> **Rota real:** `POST /webhooks/meta` (sem prefixo de versão). O `version: '1'` no
> `@Controller` não tem efeito porque o `main.ts` não chama `enableVersioning()` —
> confirmado pelo e2e, que usa `/webhooks/meta`. Se um dia versionamento for ligado,
> a URL cadastrada no painel da Meta muda junto.

---

## 1. Helper de assinatura HMAC

A Meta assina o **corpo bruto**. Assine exatamente os mesmos bytes que enviar.

```bash
# salve como /tmp/meta-post.sh e dê chmod +x
#!/usr/bin/env bash
set -euo pipefail
BODY_FILE="$1"
SECRET="${META_APP_SECRET:?defina META_APP_SECRET}"
URL="${URL:-http://localhost:3000/webhooks/meta}"
SIG=$(openssl dgst -sha256 -hmac "$SECRET" -hex < "$BODY_FILE" | awk '{print $2}')
curl -sS -w '\nHTTP %{http_code}\n' -X POST "$URL" \
  -H "Content-Type: application/json" \
  -H "X-Hub-Signature-256: sha256=${SIG}" \
  --data-binary @"$BODY_FILE"
```

---

## 2. Handshake GET

```bash
curl -sS -w '\nHTTP %{http_code}\n' \
  "http://localhost:3000/webhooks/meta?hub.mode=subscribe&hub.verify_token=$META_VERIFY_TOKEN&hub.challenge=abc123"
```
**Esperado:** `abc123` como texto puro (não JSON), HTTP 200.
Com token errado: HTTP 403.

---

## 3. Lote multi-entry — o bug B

`/tmp/p-lote.json` (troque `<PHONE_NUMBER_ID>`):

```json
{"object":"whatsapp_business_account","entry":[
 {"id":"WABA1","time":1784600000,"changes":[{"field":"messages","value":{
   "messaging_product":"whatsapp",
   "metadata":{"display_phone_number":"5511999990000","phone_number_id":"<PHONE_NUMBER_ID>"},
   "contacts":[{"wa_id":"5511988887777","profile":{"name":"Cliente A"}}],
   "messages":[
     {"from":"5511988887777","id":"wamid.LOTE1","timestamp":"1784600000","type":"text","text":{"body":"primeira"}},
     {"from":"5511988887777","id":"wamid.LOTE2","timestamp":"1784600060","type":"text","text":{"body":"segunda"}}
   ]}}]},
 {"id":"WABA1","time":1784600100,"changes":[{"field":"messages","value":{
   "messaging_product":"whatsapp",
   "metadata":{"display_phone_number":"5511999990000","phone_number_id":"<PHONE_NUMBER_ID>"},
   "contacts":[{"wa_id":"5511977776666","profile":{"name":"Cliente B"}}],
   "messages":[
     {"from":"5511977776666","id":"wamid.LOTE3","timestamp":"1784600100","type":"text","text":{"body":"terceira"}},
     {"from":"5511977776666","id":"wamid.LOTE4","timestamp":"1784600160","type":"text","text":{"body":"quarta"}}
   ]}}]}
]}
```

```bash
META_APP_SECRET=... /tmp/meta-post.sh /tmp/p-lote.json
```

**Esperado:** HTTP 200. E **4 mensagens** persistidas:

```sql
SELECT external_id, content, is_from_contact
FROM chat_messages
WHERE external_id LIKE 'wamid.LOTE%' ORDER BY external_id;
-- 4 linhas. Antes da correção viria só 1.
```

---

## 4. Idempotência — reentrega

Reenvie **o mesmo arquivo** do passo 3:

```bash
META_APP_SECRET=... /tmp/meta-post.sh /tmp/p-lote.json
```

**Esperado:** HTTP 200 e a query acima continua com **4 linhas** (nenhuma duplicata).

---

## 5. `phone_number_id` desconhecido — nunca 4xx

Copie `/tmp/p-lote.json` trocando o `phone_number_id` por `"000000000000000"`.

**Esperado:** **HTTP 200** + warning no log do gateway. Se voltar 400/404, é regressão grave: a Meta reentrega em loop diante de 4xx.

---

## 6. Janela de 24h reabrindo — o princípio 1

```sql
-- Estado inicial: force a janela para o passado
UPDATE chat_tickets
SET meta_window_expires_at = now() - interval '2 days', meta_window_type = '24h'
WHERE id = '<TICKET_ID>';
```

Envie um inbound novo (payload como o do passo 3, `id` e `timestamp` novos), depois:

```sql
SELECT meta_window_expires_at, meta_window_type, updated_at
FROM chat_tickets WHERE id = '<TICKET_ID>';
```

**Esperado:** `meta_window_expires_at` ≈ `timestamp da mensagem + 24h`, tipo `'24h'`.
É exatamente o bug que travou 26 conversas em produção no projeto de referência.

---

## 7. CTWA — janela de 72h

Inbound com `referral`:

```json
"messages":[{"from":"5511988887777","id":"wamid.CTWA1","timestamp":"1784600200","type":"text",
  "text":{"body":"vim do anúncio"},
  "referral":{"source_id":"1234567890","source_type":"ad","headline":"Promo Julho","ctwa_clid":"clid-abc-123"}}]
```

```sql
SELECT meta_window_type, meta_window_expires_at,
       meta_referral_source_id, meta_referral_source_type,
       meta_referral_headline, meta_referral_ctwa_clid
FROM chat_tickets WHERE id = '<TICKET_ID>';
```

**Esperado:** tipo `'72h'`, expiração = timestamp + 72h, e os 4 campos de referral preenchidos.

---

## 8. `GREATEST` — 72h não pode virar 24h

Logo após o passo 7, envie um inbound **simples** (sem `referral`, `id` novo, timestamp poucos minutos depois).

**Esperado:** a janela **continua `'72h'`** e `meta_window_expires_at` **não diminui**.
Se virar `'24h'`, o `GREATEST` quebrou (princípio 2).

---

## 9. Guard anti-escrita-redundante

Anote o `updated_at` do ticket. Reenvie **o mesmo inbound** do passo 8.

**Esperado:** `updated_at` **inalterado** — nenhum `UPDATE` disparado.
Escrita redundante dispara realtime e custa CPU; há teste unitário espiando o query log, mas confirme no fluxo real.

---

## 10. Status `failed` não mascarado

```json
"statuses":[{"id":"wamid.OUT1","recipient_id":"5511988887777","status":"failed","timestamp":"1784600300",
  "errors":[{"code":131047,"title":"Re-engagement message","message":"Fora da janela de 24h"}]}]
```

```sql
SELECT status, error_message, metadata
FROM chat_messages WHERE external_id = 'wamid.OUT1';
```

**Esperado:** `status = 'failed'` (jamais `sent`) e o código `131047` presente em `metadata`.

---

## 11. Status com `expiration_timestamp`

```json
"statuses":[{"id":"wamid.OUT2","recipient_id":"5511988887777","status":"sent","timestamp":"1784600400",
  "conversation":{"id":"conv-1","origin":{"type":"referral_conversion"},"expiration_timestamp":"1784859600"},
  "pricing":{"billable":true,"pricing_model":"CBP","category":"referral_conversion"}}]
```

**Esperado:** `meta_window_expires_at` = `1784859600` convertido, tipo `'72h'` — **desde que seja maior** que a janela vigente.

---

## 12. Endpoint de janela (o que a UI consome)

```bash
curl -sS -H "Authorization: Bearer <TOKEN>" \
  "http://localhost:8000/api/chat/contacts/<CONTACT_ID>/window-status" | jq
```

**Esperado:** `canSendFreeText`, `lastMessageAt`, `expiresAt`, `windowType`.

**Teste do fallback (defesa em profundidade):**
```sql
UPDATE chat_tickets SET meta_window_expires_at = now() - interval '1 hour' WHERE id = '<TICKET_ID>';
```
Com uma inbound de ~1h atrás, o endpoint deve responder `canSendFreeText: true` — caindo no cálculo por mensagens em vez de confiar no campo desatualizado.

---

## 13. Envio (exige Graph API real)

Dentro da janela:
```bash
curl -sS -X POST -H "Authorization: Bearer <TOKEN>" -H 'Content-Type: application/json' \
  "http://localhost:8000/api/chat/tickets/<TICKET_ID>/messages" \
  -d '{"content":"teste texto livre","type":"text"}'
```
**Esperado:** mensagem sai, `external_id` = wamid retornado pela Meta. Antes da correção, `sendText` sempre falhava.

Fora da janela: texto livre deve retornar erro tratado de `131047`; template aprovado deve enviar **sem** "Invalid instance token format".

```bash
curl -sS -X POST -H "Authorization: Bearer <TOKEN>" -H 'Content-Type: application/json' \
  "http://localhost:8000/api/chat/tickets/<TICKET_ID>/messages/template" \
  -d '{"template_name":"<TEMPLATE_APROVADO>","variables":{"1":"João"}}'
```

---

## 14. UI

`pnpm --filter app start` → abra um ticket de instância Meta:

- Badge com `24h` ou `72h CTWA` e tempo restante
- Abaixo de 1h, tom de alerta
- Fora da janela, composer em `template-only`
- Cliente responde → badge volta a "aberta" sem reload
- No modal de nova conversa, trocar a instância Meta reconsulta a janela

---

## Checklist de saída

- [ ] 2 handshake · [ ] 3 lote=4 · [ ] 4 idempotência · [ ] 5 desconhecido=200
- [ ] 6 janela 24h reabre · [ ] 7 CTWA 72h · [ ] 8 GREATEST · [ ] 9 sem escrita redundante
- [ ] 10 failed não mascarado · [ ] 11 expiration_timestamp · [ ] 12 endpoint + fallback
- [ ] 13 envio texto e template · [ ] 14 UI

Qualquer item reprovado → registrar o payload enviado, a resposta HTTP e o estado do
`chat_tickets`/`chat_messages` antes de abrir correção.
