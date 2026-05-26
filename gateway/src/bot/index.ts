/**
 * Ponto de entrada público do módulo bot.
 *
 * Contexto: módulo bot. Re-exporta os símbolos públicos do bounded context
 * de integração com a Telegram Bot API para uso pelos demais módulos do gateway.
 */
export { BotModule } from './bot.module';
export {
  CircuitBreakerService,
  CircuitState,
  CIRCUIT_BREAKER_OPTIONS,
} from './services/circuit-breaker.service';
export type { CircuitBreakerOptions } from './services/circuit-breaker.service';
export { CircuitBreakerOpenException } from './services/circuit-breaker-open.exception';
export { TelegramWebhookController } from './controllers/telegram-webhook.controller';
export { TelegramWebSocketGateway } from './gateways/telegram-websocket.gateway';
export { WebhookHmacSignatureGuard } from './guards/webhook-hmac-signature.guard';
