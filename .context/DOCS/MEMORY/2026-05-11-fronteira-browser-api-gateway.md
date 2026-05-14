# Memory: Fronteira Browser/API/Gateway

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-11 |
| **Autor** | DEV Agent |
| **Contexto** | App/API/Gateway Boundary + Queue Admin |
| **Tags** | app, api, gateway, security, realtime |

---

## Situação

O App tinha risco de acoplamento direto ao Gateway por `environment.gateway.url`. O contrato desejado separa endpoints internos/admin do Gateway do tráfego seguro do browser.

---

## Decisão / Aprendizado

O contrato oficial fica:

- Browser chama a Laravel API autenticada para HTTP de negócio e administração.
- Laravel API chama o Gateway por `services.gateway.url` e `services.gateway.api_key` quando precisar de operação síncrona interna.
- Laravel API e Gateway usam Redis Streams idempotentes para fluxos assíncronos.
- Browser pode usar Gateway apenas para realtime autenticado via Socket.io no `RealtimeService`.

Foi adicionado gate `scripts/validate-app-gateway-boundary.sh` para falhar se `environment.gateway.url` aparecer fora do realtime permitido.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Browser chamar endpoints admin do Gateway direto | Expõe superfície interna e duplica auth/autorização fora da API Laravel |
| Manter checagem apenas por revisão manual | Regressão é fácil e barata de automatizar com `rg` |

---

## Consequências

### Positivas
- Fronteira de segurança fica explícita e testável.
- Queue admin preserva `auth:sanctum`, policy e auditoria na API.
- Realtime continua possível sem abrir endpoints internos ao browser.

### Negativas / Trade-offs
- Laravel API passa a ser proxy síncrono para operações admin de fila.
- Mudanças legítimas de realtime precisam manter a exceção documentada no gate.

---

## Referências
- Task: `.context/DOCS/TASKS/app-api-gateway-boundary-n1-tasks.md`
- Gate: `scripts/validate-app-gateway-boundary.sh`
- Workflow: `.context/WORKFLOW/validation-flow.md`
