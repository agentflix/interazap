# Memory: Queue dashboard com endpoint legado indisponivel

## Metadados

| Campo        | Valor                                                 |
| ------------ | ----------------------------------------------------- |
| **Tipo**     | ⚠️ Armadilha                                          |
| **Data**     | 2026-04-29                                            |
| **Autor**    | DEBUG agent                                           |
| **Contexto** | Dashboard admin de filas exibindo zeros e lista vazia |
| **Tags**     | queues, angular, api-contract, fallback               |

---

## Situacao

A tela `/admin/queues` do frontend consumia `GET /api/admin/queues/overview` e `GET /api/admin/queues/{queue}/metrics`.
No ambiente host local, esses endpoints retornavam 404, enquanto `GET /api/health/queues` e `GET /api/health/queues/{queue}` respondiam 200.

---

## Decisao / Aprendizado

Para nao quebrar o dashboard quando o endpoint administrativo nao estiver publicado no API Laravel, manter o fluxo principal e adicionar fallback para os endpoints de health, com mapeamento de payload para `QueueOverview` e `QueueMetrics`.

---

## Alternativas Consideradas

| Alternativa                                      | Por que descartada                                                          |
| ------------------------------------------------ | --------------------------------------------------------------------------- |
| Forcar consumo direto do gateway `/admin/queues` | Endpoint protegido por `x-api-key` interno; nao deve ser chamado do browser |
| Remover polling e depender apenas de websocket   | Nao havia emissor confirmado para `queue:update` no backend atual           |

---

## Consequencias

### Positivas

- Dashboard volta a mostrar dados de filas no ambiente atual.
- Polling continua funcionando sem alterar contrato de tela.

### Negativas / Trade-offs

- Fallback de health nao traz campos de `active/completed/failed`, que ficam em `0` quando esse caminho eh usado.

---

## Referencias

- Arquivo: `app/src/app/core/services/queue.service.ts`
- Teste: `app/src/app/core/services/queue.service.spec.ts`
