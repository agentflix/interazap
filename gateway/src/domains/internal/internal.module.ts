import { Module } from '@nestjs/common';
import { InternalController } from './internal.controller';
import { RealtimeModule } from '../realtime/realtime.module';

/**
 * Módulo de comunicação interna (Laravel -> Gateway).
 * Expõe endpoints para broadcast de eventos WebSocket.
 */
@Module({
  imports: [RealtimeModule],
  controllers: [InternalController],
})
export class InternalModule {}
