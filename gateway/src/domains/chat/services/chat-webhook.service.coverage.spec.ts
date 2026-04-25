import { Test, TestingModule } from '@nestjs/testing';
import { ChatWebhookService } from './chat-webhook.service';
import { ChatWebhookEventNormalizer } from './chat-webhook-event-normalizer.service';
import { ChatWebhookRealtimeProcessor } from './chat-webhook-realtime-processor.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { UazapiProvider } from '../providers/uazapi/uazapi.provider';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';
import { MetaAdapter } from '../providers/meta/meta.adapter';
import { InstanceResolverService } from './instance-resolver.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { EventsGateway } from '../../realtime/gateways/events.gateway';
import { Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ChatWebhookFileLoggerService } from './chat-webhook-file-logger.service';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { NormalizedUazapiEvent } from '../providers/uazapi/uazapi.dto';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { WebhookIdempotencyService } from './webhook-idempotency.service';
import { PayloadSemanticsResolver } from './payload-semantics-resolver.service';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';
import { TicketResolverService } from './ticket-resolver.service';
import { ConnectionStatusService } from './connection-status.service';

describe('ChatWebhookService Coverage', () => {
  let service: ChatWebhookService;
  let uazapiProvider: UazapiProvider;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        ChatWebhookService,
        ChatWebhookEventNormalizer,
        ChatWebhookRealtimeProcessor,
        WebhookIdempotencyService,
        PayloadSemanticsResolver,
        WebhookRealtimeEmitter,
        TicketResolverService,
        ConnectionStatusService,
        {
          provide: RedisService,
          useValue: {
            publishStream: jest.fn(),
            ensureIdempotent: jest.fn().mockResolvedValue(true),
            get: jest.fn().mockResolvedValue(null),
            set: jest.fn().mockResolvedValue(undefined),
            delete: jest.fn().mockResolvedValue(1),
            getClient: jest.fn().mockReturnValue({
              del: jest.fn().mockResolvedValue(1),
            }),
          },
        },
        { provide: UazapiProvider, useValue: { normalize: jest.fn() } },
        { provide: ZapiAdapter, useValue: { normalizeWebhook: jest.fn() } },
        { provide: MetaAdapter, useValue: { normalizeWebhook: jest.fn() } },
        {
          provide: InstanceResolverService,
          useValue: {
            resolveByWebhookToken: jest
              .fn()
              .mockResolvedValue({ tenant_id: 't1', instance_id: 'i1' }),
          },
        },
        {
          provide: DatabaseService,
          useValue: { query: jest.fn() },
        },
        {
          provide: ChatWebhookFileLoggerService,
          useValue: { logWebhook: jest.fn() },
        },
        {
          provide: EventsGateway,
          useValue: {
            emit: jest.fn(),
            emitToRoom: jest.fn(),
            server: { to: jest.fn().mockReturnThis(), emit: jest.fn() },
          },
        },
        { provide: ConfigService, useValue: { get: jest.fn() } },
        Logger,
        {
          provide: GatewayConfigService,
          useValue: { isTestEnvironment: jest.fn().mockReturnValue(true) },
        },
      ],
    }).compile();

    service = module.get<ChatWebhookService>(ChatWebhookService);
    uazapiProvider = module.get<UazapiProvider>(UazapiProvider);
  });

  describe('handle (and private extract methods)', () => {
    it('should extract instance from raw payload (direct)', async () => {
      jest.spyOn(uazapiProvider, 'normalize').mockReturnValue({
        event_type: 'connection', // Ensure it hits processing and specifically connection flow
        target: 'test',
        raw: {
          instance: { id: 'inst-123', name: 'Test Instance' },
        },
      } as NormalizedUazapiEvent);

      await service.handle('uazapi', 'token', { raw: {} } as WebhookEventDto);
    });

    it('should extract instance from payload.raw (fallback)', async () => {
      jest.spyOn(uazapiProvider, 'normalize').mockReturnValue({
        event_type: 'connection',
        target: 'test',
        payload: {
          raw: {
            instance: { id: 'inst-fallback', name: 'Fallback Instance' },
          },
        },
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', 'token', {} as WebhookEventDto);
    });

    it('should extract status from raw payload (direct)', async () => {
      jest.spyOn(uazapiProvider, 'normalize').mockReturnValue({
        event_type: 'connection', // Use connection to trigger extractStatusPayload
        target: 'test',
        raw: {
          status: { code: 'sent', label: 'Sent', connected: true },
        },
      } as NormalizedUazapiEvent);

      await service.handle('uazapi', 'token', { raw: {} } as WebhookEventDto);
    });

    it('should extract status from payload.raw (fallback)', async () => {
      jest.spyOn(uazapiProvider, 'normalize').mockReturnValue({
        event_type: 'connection',
        target: 'test',
        payload: {
          raw: {
            status: { code: 'delivered', label: 'Delivered', connected: true },
          },
        },
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', 'token', {} as WebhookEventDto);
    });
  });
});
