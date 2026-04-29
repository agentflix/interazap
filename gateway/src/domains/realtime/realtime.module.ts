import { Module } from '@nestjs/common';
import { MulterModule } from '@nestjs/platform-express';
import { EventsGateway } from './gateways/events.gateway';
import { WebChatGateway } from './gateways/webchat.gateway';
import { EventFanoutService } from './services/event-fanout.service';
import { InternalBroadcastController } from './controllers/internal-broadcast.controller';
import { WebChatController } from './controllers/webchat.controller';
import { WsAuthGuard } from './guards/ws-auth.guard';
import { InternalApiKeyGuard } from './guards/internal-api-key.guard';
import { WsAuthenticationService } from './services/ws-authentication.service';
import { WsRoomAccessService } from './services/ws-room-access.service';
import { WsSessionService } from './services/ws-session.service';
import { WebChatProxyService } from './services/webchat-proxy.service';

@Module({
  imports: [MulterModule.register({ limits: { fileSize: 50 * 1024 * 1024 } })],
  controllers: [InternalBroadcastController, WebChatController],
  providers: [
    EventsGateway,
    WebChatGateway,
    EventFanoutService,
    WsAuthGuard,
    InternalApiKeyGuard,
    WsAuthenticationService,
    WsRoomAccessService,
    WsSessionService,
    WebChatProxyService,
  ],
  exports: [EventsGateway, WebChatGateway],
})
export class RealtimeModule {}
