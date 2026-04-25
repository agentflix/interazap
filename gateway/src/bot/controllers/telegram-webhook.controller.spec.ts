import { Logger } from '@nestjs/common';
import { TelegramNormalizerService } from '../services/telegram-normalizer.service';
import type { NormalizedTelegramEvent } from '../services/telegram-normalizer.service';
import { RedisService } from '../../infrastructure/redis/redis.service';
import { TelegramWebhookController } from './telegram-webhook.controller';
import { TelegramUpdateDto } from '../dto/telegram-update.dto';

describe('TelegramWebhookController', () => {
  let controller: TelegramWebhookController;
  let normalizer: jest.Mocked<TelegramNormalizerService>;
  let redisService: jest.Mocked<RedisService>;

  beforeEach(() => {
    normalizer = {
      normalize: jest.fn(),
    } as unknown as jest.Mocked<TelegramNormalizerService>;

    redisService = {
      publishStream: jest.fn().mockResolvedValue('stream-id-123'),
    } as unknown as jest.Mocked<RedisService>;

    controller = new TelegramWebhookController(normalizer, redisService);

    // Suppress logs in tests
    jest.spyOn(Logger.prototype, 'debug').mockImplementation();
    jest.spyOn(Logger.prototype, 'warn').mockImplementation();
    jest.spyOn(Logger.prototype, 'error').mockImplementation();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  function createUpdate(
    overrides: Partial<TelegramUpdateDto> = {},
  ): TelegramUpdateDto {
    const dto = new TelegramUpdateDto();
    dto.update_id = overrides.update_id ?? 123456;
    if (overrides.message) dto.message = overrides.message;
    if (overrides.edited_message) dto.edited_message = overrides.edited_message;
    if (overrides.message_reaction)
      dto.message_reaction = overrides.message_reaction;
    return dto;
  }

  function createNormalizedEvent(
    overrides: Partial<NormalizedTelegramEvent> = {},
  ): NormalizedTelegramEvent {
    return {
      provider: 'telegram',
      webhookToken: 'tok-abc',
      idempotencyKey: 'tg-123456',
      direction: 'inbound',
      eventType: 'message',
      chatId: '999',
      remoteJid: '999',
      senderName: 'John Doe',
      isFromMe: false,
      messageId: '42',
      type: 'text',
      text: 'Hello',
      timestamp: new Date('2026-01-01T00:00:00Z'),
      rawUpdateId: 123456,
      ...overrides,
    };
  }

  describe('handleWebhook', () => {
    it('should normalize and publish a valid update to Redis stream', async () => {
      const event = createNormalizedEvent();
      normalizer.normalize.mockReturnValue(event);

      const result = await controller.handleWebhook('tok-abc', createUpdate());

      expect(result).toEqual({ ok: true });
      expect(normalizer.normalize).toHaveBeenCalledWith(
        'tok-abc',
        expect.any(TelegramUpdateDto),
      );
      expect(redisService.publishStream).toHaveBeenCalledWith(
        'telegram.inbound',
        expect.objectContaining({
          provider: 'telegram',
          idempotency_key: 'tg-123456',
          direction: 'inbound',
          event_type: 'message',
          chat_id: '999',
          text: 'Hello',
        }),
      );
    });

    it('should return ok:true when normalization returns null (unsupported update)', async () => {
      normalizer.normalize.mockReturnValue(null);

      const result = await controller.handleWebhook('tok-abc', createUpdate());

      expect(result).toEqual({ ok: true });
      expect(redisService.publishStream).not.toHaveBeenCalled();
    });

    it('should return ok:true even when Redis publish fails', async () => {
      normalizer.normalize.mockReturnValue(createNormalizedEvent());
      redisService.publishStream.mockRejectedValue(new Error('Redis down'));

      const result = await controller.handleWebhook('tok-abc', createUpdate());

      expect(result).toEqual({ ok: true });
    });

    it('should return ok:true even when normalizer throws', async () => {
      normalizer.normalize.mockImplementation(() => {
        throw new Error('Unexpected format');
      });

      const result = await controller.handleWebhook('tok-abc', createUpdate());

      expect(result).toEqual({ ok: true });
    });

    it('should include reactions in stream payload when present', async () => {
      const event = createNormalizedEvent({
        reactions: [{ type: 'emoji', emoji: '👍' }],
      });
      normalizer.normalize.mockReturnValue(event);

      await controller.handleWebhook('tok-abc', createUpdate());

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'telegram.inbound',
        expect.objectContaining({
          reactions: [{ type: 'emoji', emoji: '👍' }],
        }),
      );
    });

    it('should default optional fields to empty strings or zero', async () => {
      const event = createNormalizedEvent({
        senderUsername: undefined,
        senderPhone: undefined,
        text: undefined,
        caption: undefined,
        fileId: undefined,
        fileName: undefined,
        mimeType: undefined,
        fileSize: undefined,
        latitude: undefined,
        longitude: undefined,
        edited: undefined,
        editDate: undefined,
        replyToMessageId: undefined,
      });
      normalizer.normalize.mockReturnValue(event);

      await controller.handleWebhook('tok-abc', createUpdate());

      expect(redisService.publishStream).toHaveBeenCalledWith(
        'telegram.inbound',
        expect.objectContaining({
          sender_username: '',
          sender_phone: '',
          text: '',
          caption: '',
          file_id: '',
          file_name: '',
          mime_type: '',
          file_size: 0,
          latitude: '',
          longitude: '',
          edited: false,
          edit_date: '',
          reply_to_message_id: '',
        }),
      );
    });
  });

  describe('health', () => {
    it('should return health status', () => {
      expect(controller.health()).toEqual({
        status: 'ok',
        service: 'telegram-webhook',
      });
    });
  });
});
