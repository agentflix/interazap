# CORS silencioso: X-Session-ID faltando no allowedHeaders do gateway

**Data:** 2026-04-26  
**Contexto:** Bug 3 — Webchat externo retornava "Não foi possível iniciar a conversa"

---

## Problema

O browser enviava OPTIONS (preflight CORS) para `gateway.interazap.com.br` e recebia **204 com sucesso**, mas nunca enviava o POST subsequente. O nginx não registrava o POST. O Angular recebia erro de rede (status 0) e exibia o fallback.

**curl funcionava normalmente** porque curl não executa preflight CORS.

---

## Causa Raiz

O `traceIdInterceptor` Angular adiciona **dois headers** em TODAS as requisições:
- `X-Trace-ID` → estava em `allowedHeaders` ✅  
- `X-Session-ID` → **NÃO estava** em `allowedHeaders` ❌

Quando o browser enviava `Access-Control-Request-Headers: content-type, x-session-id, x-trace-id` no preflight, o servidor respondia com `Access-Control-Allow-Headers` sem `X-Session-ID`. O browser **rejeita silenciosamente** o preflight nesse caso — retorna status 0, não envia o POST, não mostra erro detalhado no nginx.

---

## Fix

`gateway/src/main.ts` — adicionar `'X-Session-ID'` ao array `allowedHeaders` em `app.enableCors()`.

```typescript
allowedHeaders: [
  'Content-Type',
  'Authorization',
  'X-Requested-With',
  'X-Trace-ID',
  'X-Session-ID',   // ← adicionado
  'X-Idempotency-Key',
],
```

---

## Lição aprendida

> **Toda vez que um interceptor Angular adicionar um header customizado, esse header DEVE ser adicionado ao `allowedHeaders` do CORS no gateway.**

O padrão "OPTIONS chega, POST não chega, curl funciona" é o diagnóstico definitivo de um CORS preflight rejeitado por header não permitido.

---

## Como diagnosticar no futuro

```bash
curl -v -X OPTIONS https://gateway.interazap.com.br/api/ROTA \
  -H "Origin: https://app.interazap.com.br" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type,x-session-id,x-trace-id"
# Verificar: access-control-allow-headers inclui TODOS os headers que o Angular envia
```

---

## Refs

- Commit: `c038dea`
- Arquivo: `gateway/src/main.ts`
- Interceptor afetado: `app/src/app/core/interceptors/trace-id.interceptor.ts`
