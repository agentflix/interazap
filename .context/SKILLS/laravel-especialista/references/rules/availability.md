# Regras Laravel: Alta Disponibilidade

Use estas regras ao desenhar features que dependem de Redis, Gateway, banco, IA, WhatsApp, Asaas ou filas.

## Princípios

- Falhas externas devem degradar a funcionalidade, não derrubar a API inteira.
- Toda integração externa precisa de timeout explícito.
- Operações mutantes precisam de idempotência.
- Workloads lentos devem ir para fila.
- Health check básico deve continuar funcionando mesmo com Redis/cache indisponível.

## Resiliência

- Usar retry com backoff apenas para falhas transientes.
- Não repetir automaticamente erro de validação, permissão, payload inválido ou regra de negócio.
- Aplicar circuit breaker em providers externos quando houver helper/padrão local.
- Definir fallback seguro para Gateway/Redis/OpenAI/Asaas/WhatsApp indisponível.
- Registrar falha com `tenant_id`, `correlation_id` e provider, sem segredo.

## Multi-instância

- Não depender de estado local em disco ou memória para decisão de negócio.
- Usar Redis/DB para locks, idempotência e coordenação entre instâncias.
- Scheduler deve ter lock (`onOneServer` ou equivalente) para tarefas globais.
- Deploy deve considerar workers de queue e Octane: encerrar/recarregar processos graciosamente.

## Operações Críticas

- Pagamentos, webhooks, envio de mensagem e criação de tenant devem ser transacionais ou compensáveis.
- Para eventos duplicados, responder sucesso quando o efeito desejado já ocorreu.
- Para operações administrativas, App/Electron chamam API; Gateway fica interno.

## Evidência

- Testar cenário de provider indisponível quando a feature depender de integração externa.
- Testar reexecução/idempotência em webhooks e jobs.
- Documentar em MEMORY decisões de fallback, DLQ, timeout ou circuit breaker.
