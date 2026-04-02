import { ExecutionContext, Module } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { APP_GUARD, APP_INTERCEPTOR } from '@nestjs/core';
import { ThrottlerModule } from '@nestjs/throttler';
import { ChatModule } from './domains/chat/chat.module';
import { BillingModule } from './domains/billing/billing.module';
import { RealtimeModule } from './domains/realtime/realtime.module';
import { InternalModule } from './domains/internal/internal.module';
import { RedisModule } from './infrastructure/redis/redis.module';
import { DatabaseModule } from './infrastructure/database/database.module';
import { AIModule } from './domains/ai/ai.module';
import { WebhooksModule } from './domains/webhooks/webhooks.module';
import { HealthModule } from './health/health.module';
import { TraceIdInterceptor } from './common/interceptors/trace-id.interceptor';
import { MetricsInterceptor } from './common/interceptors/metrics.interceptor';
import { MetricsModule } from './metrics/metrics.module';
import { WsThrottlerGuard } from './domains/realtime/guards/ws-throttler.guard';
import { CircuitBreakerModule } from './shared/services/circuit-breaker';
import { IdempotencyModule } from './shared/services/idempotency';
import { SharedModule } from './shared/shared.module';
import { configFactories } from './core/config/configuration';
import { ThrottlerTrackerRequest } from './common/models/app.model';
import { ThrottlerConfiguration } from './core/config/models/configuration.model';

/**
 * AppModule
 *
 * Módulo raiz do gateway NestJS.
 * Registra todos os módulos de domínio (Chat, Billing, AI, Webhooks, Realtime, Internal),
 * infraestrutura (Redis, Database), observabilidade (Metrics, Health) e
 * guards/interceptors globais (ThrottlerGuard, TraceId, Metrics).
 */
@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true, load: configFactories }),
    SharedModule,
    CircuitBreakerModule,
    IdempotencyModule,
    ThrottlerModule.forRootAsync({
      inject: [ConfigService],
      useFactory: (configService: ConfigService) => {
        const throttlerConfig =
          configService.get<ThrottlerConfiguration>('throttler');
        return {
          throttlers: [
            {
              name: 'http',
              ttl: throttlerConfig?.httpTtl ?? 60,
              limit: throttlerConfig?.httpLimit ?? 100,
              skipIf: (context: ExecutionContext) => context.getType() === 'ws',
            },
            {
              name: 'ws',
              ttl: throttlerConfig?.wsTtl ?? 60,
              limit: throttlerConfig?.wsLimit ?? 60,
              skipIf: (context: ExecutionContext) => context.getType() !== 'ws',
              getTracker: (req: ThrottlerTrackerRequest) =>
                req.user?.sub ?? req.clientId ?? req.ip ?? 'unknown',
              setHeaders: false,
            },
          ],
        };
      },
    }),
    RedisModule,
    DatabaseModule,
    HealthModule,
    MetricsModule,
    ChatModule,
    BillingModule,
    AIModule, // AI module with provider factory pattern
    WebhooksModule,
    RealtimeModule,
    InternalModule,
  ],
  providers: [
    {
      provide: APP_GUARD,
      useClass: WsThrottlerGuard,
    },
    {
      provide: APP_INTERCEPTOR,
      useClass: TraceIdInterceptor,
    },
    {
      provide: APP_INTERCEPTOR,
      useClass: MetricsInterceptor,
    },
  ],
})
export class AppModule {}
