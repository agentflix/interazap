import { Module } from '@nestjs/common';
import { EventsGateway } from './gateways/events.gateway';
import { EventFanoutService } from './services/event-fanout.service';
import { InternalBroadcastController } from './controllers/internal-broadcast.controller';
import { WsAuthGuard } from './guards/ws-auth.guard';
import { InternalApiKeyGuard } from './guards/internal-api-key.guard';
import { WsAuthenticationService } from './services/ws-authentication.service';
import { WsRoomAccessService } from './services/ws-room-access.service';
import { WsSessionService } from './services/ws-session.service';

@Module({
  controllers: [InternalBroadcastController],
  providers: [
    EventsGateway,
    EventFanoutService,
    WsAuthGuard,
    InternalApiKeyGuard,
    WsAuthenticationService,
    WsRoomAccessService,
    WsSessionService,
  ],
  exports: [EventsGateway],
})
export class RealtimeModule {}
