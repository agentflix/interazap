import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ChatWebhookService } from './chat-webhook.service';
import { ChatWebhookEventNormalizer } from './chat-webhook-event-normalizer.service';
import { ChatWebhookRealtimeProcessor } from './chat-webhook-realtime-processor.service';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { UazapiProvider } from '../providers/uazapi/uazapi.provider';
import { InstanceResolverService } from './instance-resolver.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { EventsGateway } from '../../realtime/gateways/events.gateway';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { ChatWebhookFileLoggerService } from './chat-webhook-file-logger.service';
import { NormalizedUazapiEvent } from '../providers/uazapi/uazapi.dto';
import { NormalizedWebhookEvent } from '../contracts/provider.interface';
import { GatewayConfigService } from '../../../shared/services/gateway-config.service';
import { WebhookIdempotencyService } from './webhook-idempotency.service';
import { PayloadSemanticsResolver } from './payload-semantics-resolver.service';
import { WebhookRealtimeEmitter } from './webhook-realtime-emitter.service';
import { TicketResolverService } from './ticket-resolver.service';
import { ConnectionStatusService } from './connection-status.service';

const buildTestProviders = () => [
  ChatWebhookService,
  ChatWebhookEventNormalizer,
  ChatWebhookRealtimeProcessor,
  WebhookIdempotencyService,
  PayloadSemanticsResolver,
  WebhookRealtimeEmitter,
  TicketResolverService,
  ConnectionStatusService,
  {
    provide: ConfigService,
    useValue: { get: jest.fn(), getOrThrow: jest.fn() },
  },
  {
    provide: RedisService,
    useValue: {
      ensureIdempotent: jest.fn(),
      get: jest.fn(),
      set: jest.fn(),
      delete: jest.fn(),
      publishStream: jest.fn(),
      getClient: jest.fn().mockReturnValue({
        del: jest.fn().mockResolvedValue(1),
      }),
    },
  },
  {
    provide: UazapiProvider,
    useValue: { normalize: jest.fn() },
  },
  {
    provide: ZapiAdapter,
    useValue: { normalizeWebhook: jest.fn() },
  },
  {
    provide: InstanceResolverService,
    useValue: { resolveByWebhookToken: jest.fn() },
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
      broadcast: jest.fn(),
      emit: jest.fn(),
      emitToRoom: jest.fn(),
    },
  },
  {
    provide: GatewayConfigService,
    useValue: {
      isTestEnvironment: jest.fn().mockReturnValue(true),
      chatInboundStream: 'chat.inbound_message_received',
    },
  },
];

describe('ChatWebhookService', () => {
  let service: ChatWebhookService;
  let redisService: jest.Mocked<RedisService>;
  let uazapiProvider: jest.Mocked<UazapiProvider>;
  let zapiAdapter: jest.Mocked<ZapiAdapter>;
  let instanceResolver: jest.Mocked<InstanceResolverService>;
  let eventsGateway: jest.Mocked<EventsGateway>;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: buildTestProviders(),
    }).compile();

    service = module.get<ChatWebhookService>(ChatWebhookService);
    redisService = module.get(RedisService);
    uazapiProvider = module.get(UazapiProvider);
    zapiAdapter = module.get(ZapiAdapter);
    instanceResolver = module.get(InstanceResolverService);
    eventsGateway = module.get(EventsGateway);
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('handle', () => {
    const mockToken = 'test-token-123';
    const mockResolvedInstance = {
      provider: 'uazapi',
      tenant_id: 'tenant-123',
      instance_id: 'instance-456',
    };

    beforeEach(() => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue();
    });

    it('should handle uazapi webhook', async () => {
      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'message.received',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          body: 'Test message',
        },
      };

      const normalized = {
        provider: 'uazapi',
        event_type: 'message.received',
        direction: 'inbound',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          text: 'Test message',
        },
        instance_webhook_token: mockToken,
        raw: {},
      };

      uazapiProvider.normalize.mockReturnValue(
        normalized as unknown as NormalizedUazapiEvent,
      );

      await service.handle('uazapi', mockToken, event);

      expect(instanceResolver.resolveByWebhookToken).toHaveBeenCalledWith(
        mockToken,
      );
      expect(uazapiProvider.normalize).toHaveBeenCalled();
      expect(redisService.ensureIdempotent).toHaveBeenCalled();
    });

    it('should handle zapi webhook', async () => {
      const event: WebhookEventDto = {
        event_type: 'message',
        raw: {
          messageId: 'msg-123',
          from: '5511999999999@c.us',
          body: 'Test message',
        },
      };

      const normalized = {
        provider: 'zapi',
        event_type: 'message.received',
        direction: 'inbound',
        message: {
          id: 'msg-123',
          from: '5511999999999',
          text: 'Test message',
        },
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        raw: event.raw,
      };

      zapiAdapter.normalizeWebhook.mockReturnValue(
        normalized as unknown as NormalizedWebhookEvent,
      );

      await service.handle('zapi', mockToken, event);

      expect(instanceResolver.resolveByWebhookToken).toHaveBeenCalledWith(
        mockToken,
      );
      expect(zapiAdapter.normalizeWebhook).toHaveBeenCalledWith(
        mockToken,
        event.raw,
        mockResolvedInstance.tenant_id,
        mockResolvedInstance.instance_id,
      );
      expect(redisService.ensureIdempotent).toHaveBeenCalled();
    });

    it('should handle unknown provider with fallback', async () => {
      const event: WebhookEventDto = {
        event_type: 'custom.event',
        direction: 'inbound',
        message: {
          id: 'msg-123',
          body: 'Test',
        },
      };

      await service.handle('unknown-provider', mockToken, event);

      expect(instanceResolver.resolveByWebhookToken).toHaveBeenCalledWith(
        mockToken,
      );
      expect(redisService.ensureIdempotent).toHaveBeenCalled();
    });

    it('should not emit realtime for duplicate non-connection events', async () => {
      const event: WebhookEventDto = {
        event_type: 'messages',
        message: {
          id: 'msg-duplicate-1',
          body: 'Duplicate message',
          fromMe: false,
        },
      };

      const normalized = {
        provider: 'uazapi',
        event_type: 'messages',
        direction: 'inbound',
        message: {
          id: 'msg-duplicate-1',
          body: 'Duplicate message',
          fromMe: false,
        },
        instance_webhook_token: mockToken,
        raw: {},
      };

      uazapiProvider.normalize.mockReturnValue(
        normalized as unknown as NormalizedUazapiEvent,
      );
      redisService.ensureIdempotent.mockResolvedValue(false);

      await service.handle('uazapi', mockToken, event);

      expect(eventsGateway.emitToRoom).not.toHaveBeenCalled();
      expect(redisService.publishStream).not.toHaveBeenCalled();
    });
  });

  describe('idempotency', () => {
    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
    });

    it('should include message id for message events', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: 'token-1',
        message: {
          id: 'msg-123',
        },
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toBe('idempo:uazapi:messages:token-1:msg-123');
    });

    it('should include connection status in idempotency key', () => {
      const basePayload = {
        provider: 'uazapi',
        event_type: 'connection',
        instance_webhook_token: 'token-1',
      } as Record<string, unknown>;

      const connecting = {
        ...basePayload,
        raw: {
          instance: {
            status: 'connecting',
          },
        },
      };

      const connected = {
        ...basePayload,
        raw: {
          instance: {
            status: 'connected',
          },
        },
      };

      const keyConnecting = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(connecting as unknown as Record<string, unknown>);
      const keyConnected = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(connected as unknown as Record<string, unknown>);

      expect(keyConnecting.key).toContain('connecting');
      expect(keyConnected.key).toContain('connected');
      expect(keyConnecting.key).not.toEqual(keyConnected.key);
    });

    it('should map zapi status events with message id', () => {
      const payload = {
        provider: 'zapi',
        event_type: 'message_status',
        instance_webhook_token: 'token-1',
        message: {
          id: 'zapi-msg-1',
          status: 'delivered',
        },
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toBe(
        'idempo:zapi:message_status:token-1:zapi-msg-1',
      );
    });

    it('should use generic discriminator when no specific data', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'unknown_event',
        instance_webhook_token: 'token-1',
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toContain('idempo:uazapi:unknown_event:token-1');
    });

    it('should handle messages_update with MessageIDs array', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages_update',
        instance_webhook_token: 'token-1',
        raw: {
          event: {
            MessageIDs: ['msg-1', 'msg-2'],
            Type: 'Delivered',
          },
        },
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toContain('msg-1,msg-2_Delivered');
    });

    it('should use edit event id for edited message idempotency key', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: 'token-1',
        message: {
          id: 'edit-event-001',
          body: 'Texto atualizado',
        },
        raw: {
          message: {
            id: 'edit-event-001',
            edited: 'msg-original-001',
            text: 'Texto atualizado',
          },
        },
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toContain('edit_event:edit-event-001');
    });

    it('should produce different edit fallback signatures for different edit payloads', () => {
      const firstPayload = {
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: 'token-1',
        raw: {
          message: {
            edited: 'msg-original-001',
            text: 'Texto v1',
            timestamp: '1706550000',
          },
        },
      } as Record<string, unknown>;

      const secondPayload = {
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: 'token-1',
        raw: {
          message: {
            edited: 'msg-original-001',
            text: 'Texto v2',
            timestamp: '1706550001',
          },
        },
      } as Record<string, unknown>;

      const firstDescriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(firstPayload as unknown as Record<string, unknown>);

      const secondDescriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(
        secondPayload as unknown as Record<string, unknown>,
      );

      expect(firstDescriptor.key).not.toEqual(secondDescriptor.key);
      expect(firstDescriptor.key).toContain('edit_sig:');
      expect(secondDescriptor.key).toContain('edit_sig:');
    });

    it('should use timestamp fallback for messages_update without MessageIDs', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages_update',
        instance_webhook_token: 'token-1',
        raw: {
          event: {
            Type: 'Read',
            Timestamp: 1706550000,
          },
        },
      } as Record<string, unknown>;

      const descriptor = (
        service as unknown as {
          buildIdempotencyKey: (data: unknown) => { key: string };
        }
      ).buildIdempotencyKey(payload as unknown as Record<string, unknown>);

      expect(descriptor.key).toContain('ts_1706550000_Read');
    });
  });

  describe('normalizeIdempotencyKey', () => {
    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
    });

    it('should keep short keys unchanged', () => {
      const normalizeKey = (
        service as unknown as {
          normalizeIdempotencyKey: (key: string) => string;
        }
      ).normalizeIdempotencyKey;

      const shortKey = 'idempo:uazapi:messages:token-1:msg-123';
      expect(normalizeKey.call(service, shortKey)).toBe(shortKey);
    });

    it('should hash keys longer than 255 characters', () => {
      const normalizeKey = (
        service as unknown as {
          normalizeIdempotencyKey: (key: string) => string;
        }
      ).normalizeIdempotencyKey;

      const longKey = 'idempo:' + 'a'.repeat(300);
      const result = normalizeKey.call(service, longKey);

      expect(result.startsWith('idempo_hash:')).toBe(true);
      expect(result.length).toBeLessThanOrEqual(255);
    });
  });

  describe('composeIdempotencyKey', () => {
    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
    });

    it('should compose key with all parts', () => {
      const composeKey = (
        service as unknown as {
          composeIdempotencyKey: (
            provider: string,
            eventType: string,
            token?: string,
            discriminator?: string,
          ) => string;
        }
      ).composeIdempotencyKey;

      const result = composeKey.call(
        service,
        'uazapi',
        'messages',
        'token-1',
        'msg-123',
      );

      expect(result).toBe('idempo:uazapi:messages:token-1:msg-123');
    });

    it('should omit empty parts', () => {
      const composeKey = (
        service as unknown as {
          composeIdempotencyKey: (
            provider: string,
            eventType: string,
            token?: string,
            discriminator?: string,
          ) => string;
        }
      ).composeIdempotencyKey;

      const result = composeKey.call(service, 'uazapi', 'connection', '', '');

      expect(result).toBe('idempo:uazapi:connection');
    });
  });

  describe('handle - duplicate detection', () => {
    const mockToken = 'test-token-123';
    const mockResolvedInstance = {
      provider: 'uazapi',
      tenant_id: 'tenant-123',
      instance_id: 'instance-456',
    };
    let scopedEventsGateway: { emit: jest.Mock; emitToRoom: jest.Mock };

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();

      service = module.get<ChatWebhookService>(ChatWebhookService);
      redisService = module.get(RedisService);
      uazapiProvider = module.get(UazapiProvider);
      zapiAdapter = module.get(ZapiAdapter);
      instanceResolver = module.get(InstanceResolverService);
      scopedEventsGateway = module.get(EventsGateway);
    });

    it('should skip duplicate events', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(false); // Duplicate

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'message.received',
        message: {
          id: 'msg-123',
          body: 'Test',
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        message: { id: 'msg-123' },
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(redisService.ensureIdempotent).toHaveBeenCalled();
      expect(redisService.publishStream).not.toHaveBeenCalled();
    });

    it('should process new events and publish to stream', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true); // New event
      redisService.publishStream.mockResolvedValue('stream-id-123');

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'message.received',
        message: {
          id: 'msg-456',
          body: 'New message',
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        message: { id: 'msg-456', body: 'New message' },
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.any(Object),
      );
    });

    it('should classify edited uazapi messages as update and emit chat.message.edit', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');
      redisService.get.mockResolvedValue(
        JSON.stringify({
          tenant_id: mockResolvedInstance.tenant_id,
          ticket_id: 'ticket-123',
        }),
      );

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'messages',
        raw: {
          message: {
            id: 'edit-event-123',
            edited: 'msg-original-123',
            chatid: '5511999999999@s.whatsapp.net',
            text: 'Mensagem editada',
            fromMe: true,
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        message: {
          id: 'edit-event-123',
          chatid: '5511999999999@s.whatsapp.net',
          body: 'Mensagem editada',
          type: 'text',
          fromMe: true,
        },
        raw: event.raw ?? {},
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(scopedEventsGateway.emitToRoom).toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.message.edit',
        expect.objectContaining({
          message_id: 'msg-original-123',
          ticket_id: 'ticket-123',
          content: 'Mensagem editada',
          is_edited: true,
        }),
      );
      expect(scopedEventsGateway.emitToRoom).toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.activity',
        expect.objectContaining({
          ticketId: 'ticket-123',
          subevents: [
            expect.objectContaining({
              type: 'msg.edit',
              data: expect.objectContaining({
                message_id: 'msg-original-123',
                ticket_id: 'ticket-123',
              }),
            }),
          ],
        }),
      );
      expect(scopedEventsGateway.emitToRoom).not.toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.message.new',
        expect.anything(),
      );
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.objectContaining({
          event_type: 'message.edit',
          source_event_type: 'messages',
          message_id: 'msg-original-123',
          semantic_type: 'update',
          change_kind: 'edited',
          message_reference_id: 'msg-original-123',
        }),
      );
    });

    it('should not emit chat.message.edit or msg.edit activity when ticket is unresolved', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');
      redisService.get.mockResolvedValue(null);

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'messages',
        raw: {
          message: {
            id: 'edit-event-404',
            edited: 'msg-original-404',
            chatid: '5511999999999@s.whatsapp.net',
            text: 'Mensagem sem ticket',
            fromMe: true,
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        message: {
          id: 'edit-event-404',
          chatid: '5511999999999@s.whatsapp.net',
          body: 'Mensagem sem ticket',
          type: 'text',
          fromMe: true,
        },
        raw: event.raw ?? {},
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      const editCalls = scopedEventsGateway.emitToRoom.mock.calls.filter(
        (call) => call[1] === 'chat.message.edit',
      );
      expect(editCalls).toHaveLength(0);

      const msgEditActivityCalls =
        scopedEventsGateway.emitToRoom.mock.calls.filter((call) => {
          if (call[1] !== 'chat.activity') {
            return false;
          }

          const payload = call[2];
          if (!payload || typeof payload !== 'object') {
            return false;
          }

          if (!('subevents' in payload)) {
            return false;
          }

          const subevents = (payload as { subevents?: unknown }).subevents;
          if (!Array.isArray(subevents)) {
            return false;
          }

          return subevents.some(
            (subevent) =>
              typeof subevent === 'object' &&
              subevent !== null &&
              'type' in subevent &&
              (subevent as { type?: unknown }).type === 'msg.edit',
          );
        });
      expect(msgEditActivityCalls).toHaveLength(0);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.objectContaining({
          event_type: 'message.edit',
          message_id: 'msg-original-404',
        }),
      );
    });

    it('should not emit preliminary edit realtime when edited payload has no resolvable content', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');
      redisService.get.mockResolvedValue(
        JSON.stringify({
          tenant_id: mockResolvedInstance.tenant_id,
          ticket_id: 'ticket-123',
        }),
      );

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'messages',
        raw: {
          message: {
            id: 'edit-event-empty-1',
            edited: 'msg-original-empty-1',
            chatid: '5511999999999@s.whatsapp.net',
            fromMe: true,
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        message: {
          id: 'edit-event-empty-1',
          chatid: '5511999999999@s.whatsapp.net',
          type: 'text',
          fromMe: true,
        },
        raw: event.raw ?? {},
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      const editCalls = scopedEventsGateway.emitToRoom.mock.calls.filter(
        (call) => call[1] === 'chat.message.edit',
      );
      expect(editCalls).toHaveLength(0);

      const msgEditActivityCalls =
        scopedEventsGateway.emitToRoom.mock.calls.filter((call) => {
          if (call[1] !== 'chat.activity') {
            return false;
          }

          const payload = call[2];
          if (!payload || typeof payload !== 'object') {
            return false;
          }

          if (!('subevents' in payload)) {
            return false;
          }

          const subevents = (payload as { subevents?: unknown }).subevents;
          if (!Array.isArray(subevents)) {
            return false;
          }

          return subevents.some(
            (subevent) =>
              typeof subevent === 'object' &&
              subevent !== null &&
              'type' in subevent &&
              (subevent as { type?: unknown }).type === 'msg.edit',
          );
        });
      expect(msgEditActivityCalls).toHaveLength(0);

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.objectContaining({
          event_type: 'message.edit',
          message_id: 'msg-original-empty-1',
        }),
      );
    });

    it('should classify edited zapi messages as update before generic create', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');
      redisService.get.mockResolvedValue(
        JSON.stringify({
          tenant_id: mockResolvedInstance.tenant_id,
          ticket_id: 'ticket-zapi-123',
        }),
      );

      const event: WebhookEventDto = {
        event_type: 'message',
        raw: {
          messageId: 'zapi-edit-123',
          phone: '5511999999999@c.us',
          connectedPhone: '5511888888888@c.us',
          isEdit: true,
          text: {
            message: 'Texto atualizado via Z-API',
          },
        },
      };

      zapiAdapter.normalizeWebhook.mockReturnValue({
        provider: 'zapi',
        tenantId: mockResolvedInstance.tenant_id,
        instanceId: mockResolvedInstance.instance_id,
        instanceWebhookToken: mockToken,
        eventType: 'message',
        direction: 'outbound',
        message: {
          id: 'zapi-edit-123',
          from: '5511888888888@c.us',
          to: '5511999999999@c.us',
          type: 'text',
          text: 'Texto atualizado via Z-API',
          timestamp: new Date('2026-03-17T12:00:00.000Z'),
          isFromMe: true,
          isGroup: false,
        },
        rawPayload: event.raw ?? {},
        idempotencyKey: 'idempo:zapi:test',
        receivedAt: new Date('2026-03-17T12:00:00.000Z'),
      } as unknown as NormalizedWebhookEvent);

      await service.handle('zapi', mockToken, event);

      expect(scopedEventsGateway.emitToRoom).toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.message.edit',
        expect.objectContaining({
          message_id: 'zapi-edit-123',
          ticket_id: 'ticket-zapi-123',
          content: 'Texto atualizado via Z-API',
          is_edited: true,
        }),
      );
      expect(scopedEventsGateway.emitToRoom).not.toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.message.new',
        expect.anything(),
      );
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.objectContaining({
          event_type: 'message.edit',
          source_event_type: 'messages',
          message_id: 'zapi-edit-123',
          semantic_type: 'update',
          change_kind: 'edited',
          message_reference_id: 'zapi-edit-123',
        }),
      );
    });

    it('should not emit chat.message.new in fast-path for inbound messages', async () => {
      instanceResolver.resolveByWebhookToken.mockResolvedValue(
        mockResolvedInstance,
      );
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-id-123');

      const event: WebhookEventDto = {
        EventType: 'messages',
        event_type: 'message.received',
        message: {
          id: 'msg-789',
          body: 'Inbound',
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        message: { id: 'msg-789', body: 'Inbound', fromMe: false },
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(scopedEventsGateway.emitToRoom).not.toHaveBeenCalledWith(
        `tenant:${mockResolvedInstance.tenant_id}`,
        'chat.message.new',
        expect.anything(),
      );
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'chat.inbound_message_received',
        expect.any(Object),
      );
    });
  });

  describe('handle - connection events', () => {
    const mockToken = 'test-token-123';
    const mockResolvedInstance = {
      provider: 'uazapi',
      tenant_id: 'tenant-123',
      instance_id: 'instance-456',
    };
    let scopedEventsGateway: { emit: jest.Mock; emitToRoom: jest.Mock };

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();

      service = module.get<ChatWebhookService>(ChatWebhookService);
      redisService = module.get(RedisService);
      uazapiProvider = module.get(UazapiProvider);
      scopedEventsGateway = module.get(EventsGateway);

      // Connection events need pre-configured mocks
      const ir = module.get<InstanceResolverService>(
        InstanceResolverService,
      ) as jest.Mocked<InstanceResolverService>;
      ir.resolveByWebhookToken.mockResolvedValue(mockResolvedInstance);
      redisService.ensureIdempotent.mockResolvedValue(true);
      redisService.publishStream.mockResolvedValue('stream-123' as any);
    });

    it('should handle connection status events', async () => {
      const event: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection',
        raw: {
          instance: {
            status: 'connected',
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'connection',
        instance_webhook_token: mockToken,
        raw: event.raw,
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(redisService.publishStream).toHaveBeenCalled();
    });

    it('should treat connection.update events as connection events', async () => {
      const event: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection.update',
        raw: {
          instance: {
            status: 'connected',
          },
          status: {
            connected: true,
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'connection.update',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        raw: event.raw,
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(redisService.ensureIdempotent).not.toHaveBeenCalled();
      expect(scopedEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'integration.connection',
        expect.objectContaining({ status: 'connected', connected: true }),
      );
    });

    it('buffers transitional connection events and emits the latest after debounce', async () => {
      jest.useFakeTimers();
      const event: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection',
        raw: {
          instance: {
            status: 'connecting',
          },
        },
      };

      uazapiProvider.normalize
        .mockReturnValueOnce({
          provider: 'uazapi',
          event_type: 'connection',
          instance_webhook_token: mockToken,
          raw: event.raw,
        } as never)
        .mockReturnValueOnce({
          provider: 'uazapi',
          event_type: 'connection',
          instance_webhook_token: mockToken,
          raw: {
            instance: {
              status: 'qr',
              qrcode: 'qr-code-1',
            },
          },
        } as never);

      await service.handle('uazapi', mockToken, event);
      await service.handle('uazapi', mockToken, event);

      expect(scopedEventsGateway.emitToRoom).not.toHaveBeenCalledWith(
        'tenant:tenant-123',
        'integration.connection',
        expect.anything(),
      );

      jest.advanceTimersByTime(120);

      expect(scopedEventsGateway.emitToRoom).toHaveBeenCalledWith(
        'tenant:tenant-123',
        'integration.connection',
        expect.objectContaining({ status: 'qr' }),
      );
      jest.useRealTimers();
    });

    it('flushes buffered transitional events before terminal statuses', async () => {
      jest.useFakeTimers();
      const connectingEvent: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection',
        raw: {
          instance: {
            status: 'connecting',
          },
        },
      };
      const connectedEvent: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection',
        raw: {
          instance: {
            status: 'connected',
          },
        },
      };

      uazapiProvider.normalize
        .mockReturnValueOnce({
          provider: 'uazapi',
          event_type: 'connection',
          instance_webhook_token: mockToken,
          raw: connectingEvent.raw,
        } as never)
        .mockReturnValueOnce({
          provider: 'uazapi',
          event_type: 'connection',
          instance_webhook_token: mockToken,
          raw: connectedEvent.raw,
        } as never);

      await service.handle('uazapi', mockToken, connectingEvent);
      await service.handle('uazapi', mockToken, connectedEvent);

      expect(scopedEventsGateway.emitToRoom).toHaveBeenNthCalledWith(
        1,
        'tenant:tenant-123',
        'integration.connection',
        expect.objectContaining({ status: 'connecting' }),
      );
      expect(scopedEventsGateway.emitToRoom).toHaveBeenNthCalledWith(
        2,
        'tenant:tenant-123',
        'integration.connection',
        expect.objectContaining({ status: 'connected' }),
      );
      jest.useRealTimers();
    });

    it('should allow reconnection by bypassing idempotency for connection events', async () => {
      const event: WebhookEventDto = {
        EventType: 'connection',
        event_type: 'connection',
        raw: {
          instance: {
            status: 'connected',
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'connection',
        instance_webhook_token: mockToken,
        raw: event.raw,
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      // Connection events bypass idempotency entirely
      expect(redisService.ensureIdempotent).not.toHaveBeenCalled();
      // But should still publish to stream
      expect(redisService.publishStream).toHaveBeenCalled();
    });

    it('should handle messages_update events', async () => {
      const event: WebhookEventDto = {
        EventType: 'messages_update',
        event_type: 'messages_update',
        raw: {
          event: {
            MessageIDs: ['msg-1', 'msg-2'],
            Type: 'Delivered',
          },
        },
      };

      uazapiProvider.normalize.mockReturnValue({
        provider: 'uazapi',
        event_type: 'messages_update',
        instance_webhook_token: mockToken,
        tenant_id: mockResolvedInstance.tenant_id,
        raw: event.raw,
      } as unknown as NormalizedUazapiEvent);

      await service.handle('uazapi', mockToken, event);

      expect(redisService.publishStream).toHaveBeenCalled();
    });
  });

  describe('normalizeMessageStatus', () => {
    let normalizer: ChatWebhookEventNormalizer;

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
      normalizer = module.get<ChatWebhookEventNormalizer>(
        ChatWebhookEventNormalizer,
      );
    });

    it('should normalize delivered status', () => {
      expect(normalizer.normalizeMessageStatus('delivered')).toBe('delivered');
      expect(normalizer.normalizeMessageStatus('delivery_ack')).toBe(
        'delivered',
      );
    });

    it('should normalize read status', () => {
      expect(normalizer.normalizeMessageStatus('read')).toBe('read');
      expect(normalizer.normalizeMessageStatus('played')).toBe('read');
      expect(normalizer.normalizeMessageStatus('viewed')).toBe('read');
    });

    it('should normalize sent status', () => {
      expect(normalizer.normalizeMessageStatus('sent')).toBe('sent');
      expect(normalizer.normalizeMessageStatus('server_ack')).toBe('sent');
    });

    it('should return null for unknown status', () => {
      expect(normalizer.normalizeMessageStatus('unknown_status')).toBeNull();
      expect(normalizer.normalizeMessageStatus('pending')).toBeNull();
    });
  });

  describe('toStreamRecord', () => {
    let normalizer: ChatWebhookEventNormalizer;

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
      normalizer = module.get<ChatWebhookEventNormalizer>(
        ChatWebhookEventNormalizer,
      );
    });

    it('should convert payload to stream record', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages',
        tenant_id: 't-1',
        instance_id: 'i-1',
      };
      const result = normalizer.toStreamRecord(payload as any);

      expect(result.provider).toBe('uazapi');
      expect(result.tenant_id).toBe('t-1');
      expect(result.instance_id).toBe('i-1');
    });

    it('should set instance_id to null when missing', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages',
        tenant_id: 't-1',
      };
      const result = normalizer.toStreamRecord(payload as any);

      expect(result.instance_id).toBeNull();
    });

    it('should append semantic update metadata for edited message payloads', () => {
      const payload = {
        provider: 'uazapi',
        event_type: 'messages',
        tenant_id: 't-1',
        instance_id: 'i-1',
        raw: {
          message: {
            id: 'edit-event-1',
            edited: 'msg-original-1',
            text: 'Mensagem editada',
          },
        },
      };

      const result = normalizer.toStreamRecord(payload as any);

      expect(result.semantic_type).toBe('update');
      expect(result.change_kind).toBe('edited');
      expect(result.event_type).toBe('message.edit');
      expect(result.source_event_type).toBe('messages');
      expect(result.message_id).toBe('msg-original-1');
      expect(result.message_reference_id).toBe('msg-original-1');
    });
  });

  describe('resolveConnectionStatus', () => {
    let normalizer: ChatWebhookEventNormalizer;

    beforeEach(async () => {
      const module: TestingModule = await Test.createTestingModule({
        providers: buildTestProviders(),
      }).compile();
      service = module.get<ChatWebhookService>(ChatWebhookService);
      normalizer = module.get<ChatWebhookEventNormalizer>(
        ChatWebhookEventNormalizer,
      );
    });

    it('should return status from connection (instance) object', () => {
      const payload = {
        raw: { instance: { status: 'connected' } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'connected',
      );
    });

    it('should return status from statusPayload.status string', () => {
      const payload = {
        raw: { status: { status: 'disconnected' } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'disconnected',
      );
    });

    it('should return connected from connected flag true', () => {
      const payload = {
        raw: { status: { connected: true } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'connected',
      );
    });

    it('should return disconnected from connected flag false', () => {
      const payload = {
        raw: { status: { connected: false } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'disconnected',
      );
    });

    it('should return connected from loggedIn flag true', () => {
      const payload = {
        raw: { status: { loggedIn: true } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'connected',
      );
    });

    it('should return disconnected from loggedIn flag false', () => {
      const payload = {
        raw: { status: { loggedIn: false } },
      };
      expect(normalizer.resolveConnectionStatus(payload as any)).toBe(
        'disconnected',
      );
    });

    it('should return undefined when no status info found', () => {
      const payload = {
        raw: { other: 'data' },
      };
      expect(
        normalizer.resolveConnectionStatus(payload as any),
      ).toBeUndefined();
    });
  });
});
