import { Module } from '@nestjs/common';
import { HttpModule } from '@nestjs/axios';
import { ConfigModule } from '@nestjs/config';
import { TelegramClientService } from './services/telegram-client.service';
import { TelegramNormalizerService } from './services/telegram-normalizer.service';
import {
  CircuitBreakerService,
  CIRCUIT_BREAKER_OPTIONS,
} from './services/circuit-breaker.service';
import { LongPollingStrategy } from './services/strategies/long-polling.strategy';
import { WebhookStrategy } from './services/strategies/webhook.strategy';
import { PollingStrategyFactory } from './services/strategies/polling-strategy.factory';
import { TelegramWebhookController } from './controllers/telegram-webhook.controller';
import { WebhookHmacSignatureGuard } from './guards/webhook-hmac-signature.guard';
import { TelegramWebSocketGateway } from './gateways/telegram-websocket.gateway';

/**
 * BotModule
 *
 * Módulo raiz para integração com Telegram Bot API.
 * Bounded context isolado — não importa nada de domains/chat/.
 */
@Module({
  imports: [HttpModule, ConfigModule],
  controllers: [TelegramWebhookController],
  providers: [
    TelegramClientService,
    TelegramNormalizerService,
    WebhookHmacSignatureGuard,
    {
      provide: CIRCUIT_BREAKER_OPTIONS,
      useValue: {
        failureThreshold: 5,
        failureWindowMs: 10_000,
        openDurationMs: 30_000,
        halfOpenTestRequests: 3,
        maxBackoffMs: 32_000,
        jitterPercent: 0.2,
      },
    },
    CircuitBreakerService,
    LongPollingStrategy,
    WebhookStrategy,
    PollingStrategyFactory,
    TelegramWebSocketGateway,
  ],
  exports: [
    TelegramClientService,
    TelegramNormalizerService,
    CircuitBreakerService,
    PollingStrategyFactory,
    LongPollingStrategy,
    WebhookStrategy,
    TelegramWebSocketGateway,
  ],
})
export class BotModule {}
