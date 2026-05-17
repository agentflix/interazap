# Regras Laravel: Job Assíncrono

Use estas regras ao criar jobs, filas, eventos assíncronos ou consumidores.

## Estrutura

- Criar jobs em `api/src/Domain/{Context}/Jobs/`.
- Implementar `ShouldQueue`; usar `ShouldBeUnique` quando duplicidade causar efeito colateral.
- Usar traits Laravel padrão: `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`.
- Passar IDs escalares no construtor, não models inteiros, para reduzir payload e serialização frágil.
- Resolver models dentro de `handle()` com filtro de tenant quando aplicável.

## Robustez

- Definir `$tries`, `$backoff` e, quando fizer sentido, `$uniqueFor`.
- Criar `uniqueId()` com chave de negócio estável para jobs únicos.
- Tornar o job idempotente: checar status atual, evento/processamento já feito ou chave deduplicada antes de mutar estado.
- Usar transação (`DB::transaction`) para mudanças atômicas.
- Logar contexto minimo: `tenant_id`, IDs, provider/event type e motivo de falha, sem segredos.
- Para integrações externas, exigir timeout, retry/backoff, circuit breaker quando existir helper local e tratamento de falha recuperável.

## Filas E Streaming

- Para comunicação API ↔ Gateway, preferir Redis Streams idempotentes conforme regra do repo.
- Não acoplar App/Electron direto ao Gateway para operações administrativas; App chama API.
- Para webhooks, validar assinatura quando existir, deduplicar evento e testar reenvio duplicado.

## Testes

- Cobrir dispatch, idempotência, retry/falha importante e efeitos no banco.
- Usar `Queue::fake()` apenas quando o alvo do teste for o dispatch; executar `handle()` diretamente quando o alvo for comportamento interno.
- Testar isolamento tenant quando o job le/escreve dados tenant-aware.
