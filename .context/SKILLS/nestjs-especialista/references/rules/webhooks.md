# Webhooks

## Entrada

- Webhooks devem validar assinatura, token ou HMAC quando o provider suportar.
- ACK deve ser rápido; regra do Gateway: resposta de webhook em menos de 150 ms sempre que possível.
- Faça parse seguro do payload e normalize antes de enviar ao domínio.
- Não confie em campos de tenant vindos do provider sem resolver instância/canal pelo backend ou banco.

## Idempotência

- Use chave idempotente com provider, tenant/instance, event id e tipo de evento.
- Eventos duplicados devem ser ignorados sem erro quando já processados.
- Eventos fora de ordem devem ser tratados de forma defensiva.

## Processamento

- Trabalho pesado deve ir para fila, stream ou service assíncrono.
- Webhook handler não deve bloquear em IA, upload grande, envio externo demorado ou relatório.
- Logs devem mascarar segredos e limitar payload.

## Testes

- Cubra assinatura inválida, payload inválido, duplicidade, provider indisponível e caso de sucesso.
- Use fixtures realistas em `gateway/test/fixtures` quando fizer sentido.
