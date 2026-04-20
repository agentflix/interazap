import 'reflect-metadata';
import { Test, TestingModule } from '@nestjs/testing';
import {
  TelegramNormalizerService,
  NormalizedTelegramEvent,
} from '../../src/bot/services/telegram-normalizer.service';
import { TelegramUpdateDto } from '../../src/bot/dto/telegram-update.dto';

// ─── Helpers ─────────────────────────────────────────────────

const WEBHOOK_TOKEN = 'wh-token-abc';

function baseUser(overrides: Record<string, unknown> = {}) {
  return {
    id: 123456,
    is_bot: false,
    first_name: 'John',
    last_name: 'Doe',
    username: 'johndoe',
    ...overrides,
  };
}

function baseChat(overrides: Record<string, unknown> = {}) {
  return {
    id: 789,
    type: 'private' as const,
    ...overrides,
  };
}

function baseMessage(overrides: Record<string, unknown> = {}) {
  return {
    message_id: 1,
    from: baseUser(),
    chat: baseChat(),
    date: 1700000000,
    text: 'Hello world',
    ...overrides,
  };
}

function baseUpdate(
  overrides: Record<string, unknown> = {},
): TelegramUpdateDto {
  return {
    update_id: 100,
    message: baseMessage(),
    ...overrides,
  } as TelegramUpdateDto;
}

// ─── Tests ───────────────────────────────────────────────────

describe('TelegramNormalizerService', () => {
  let service: TelegramNormalizerService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [TelegramNormalizerService],
    }).compile();

    service = module.get(TelegramNormalizerService);
  });

  // ─── Text message ────────────────────────────────────────

  describe('text message', () => {
    it('should normalize to type "text" with direction "inbound"', () => {
      const result = service.normalize(WEBHOOK_TOKEN, baseUpdate())!;

      expect(result.provider).toBe('telegram');
      expect(result.type).toBe('text');
      expect(result.direction).toBe('inbound');
      expect(result.eventType).toBe('message');
      expect(result.text).toBe('Hello world');
      expect(result.webhookToken).toBe(WEBHOOK_TOKEN);
    });
  });

  // ─── Bot message → outbound ──────────────────────────────

  describe('bot message', () => {
    it('should set direction to "outbound" when from.is_bot=true', () => {
      const update = baseUpdate({
        message: baseMessage({ from: baseUser({ is_bot: true }) }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.direction).toBe('outbound');
      expect(result.isFromMe).toBe(true);
    });
  });

  // ─── Photo message ──────────────────────────────────────

  describe('photo message', () => {
    it('should normalize to type "image" using last (largest) photo', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          photo: [
            { file_id: 'small', file_unique_id: 'u1', width: 90, height: 90 },
            {
              file_id: 'medium',
              file_unique_id: 'u2',
              width: 320,
              height: 320,
            },
            {
              file_id: 'large',
              file_unique_id: 'u3',
              width: 800,
              height: 800,
              file_size: 50000,
            },
          ],
          caption: 'My photo',
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('image');
      expect(result.fileId).toBe('large');
      expect(result.caption).toBe('My photo');
      expect(result.mimeType).toBe('image/jpeg');
    });
  });

  // ─── Video message ──────────────────────────────────────

  describe('video message', () => {
    it('should normalize to type "video"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          video: {
            file_id: 'vid1',
            file_unique_id: 'uv1',
            width: 1920,
            height: 1080,
            duration: 30,
            mime_type: 'video/mp4',
          },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('video');
      expect(result.fileId).toBe('vid1');
      expect(result.mimeType).toBe('video/mp4');
    });
  });

  // ─── Voice message ──────────────────────────────────────

  describe('voice message', () => {
    it('should normalize to type "audio"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          voice: {
            file_id: 'voice1',
            file_unique_id: 'uvo1',
            duration: 5,
            mime_type: 'audio/ogg',
          },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('audio');
      expect(result.fileId).toBe('voice1');
    });
  });

  // ─── Audio message ──────────────────────────────────────

  describe('audio message', () => {
    it('should normalize to type "audio"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          audio: {
            file_id: 'aud1',
            file_unique_id: 'ua1',
            duration: 180,
            performer: 'Artist',
            title: 'Song',
            mime_type: 'audio/mpeg',
          },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('audio');
      expect(result.fileId).toBe('aud1');
    });
  });

  // ─── Document message ───────────────────────────────────

  describe('document message', () => {
    it('should normalize to type "document"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          document: {
            file_id: 'doc1',
            file_unique_id: 'ud1',
            file_name: 'report.pdf',
            mime_type: 'application/pdf',
            file_size: 102400,
          },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('document');
      expect(result.fileId).toBe('doc1');
      expect(result.fileName).toBe('report.pdf');
    });
  });

  // ─── Sticker message ────────────────────────────────────

  describe('sticker message', () => {
    it('should normalize to type "sticker"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          sticker: {
            file_id: 'stk1',
            file_unique_id: 'us1',
            type: 'regular',
            width: 512,
            height: 512,
            is_animated: false,
            is_video: false,
          },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('sticker');
      expect(result.fileId).toBe('stk1');
      expect(result.mimeType).toBe('image/webp');
    });
  });

  // ─── Location message ───────────────────────────────────

  describe('location message', () => {
    it('should normalize to type "location"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          location: { latitude: -23.55, longitude: -46.63 },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('location');
      expect(result.latitude).toBe(-23.55);
      expect(result.longitude).toBe(-46.63);
    });
  });

  // ─── Contact message ────────────────────────────────────

  describe('contact message', () => {
    it('should normalize to type "contact"', () => {
      const update = baseUpdate({
        message: baseMessage({
          text: undefined,
          contact: { phone_number: '+5511999999999', first_name: 'Jane' },
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.type).toBe('contact');
      expect(result.senderPhone).toBe('+5511999999999');
    });
  });

  // ─── Edited message ─────────────────────────────────────

  describe('edited message', () => {
    it('should set eventType "message_edit" and edited: true', () => {
      const update = baseUpdate({
        message: undefined,
        edited_message: baseMessage({ edit_date: 1700001000 }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.eventType).toBe('message_edit');
      expect(result.edited).toBe(true);
      expect(result.editDate).toBe(1700001000);
    });
  });

  // ─── Reaction ────────────────────────────────────────────

  describe('reaction', () => {
    it('should set eventType "message_status"', () => {
      const update: TelegramUpdateDto = {
        update_id: 200,
        message_reaction: {
          chat: baseChat() as TelegramUpdateDto['message_reaction'] extends undefined
            ? never
            : NonNullable<TelegramUpdateDto['message_reaction']>['chat'],
          message_id: 5,
          user: baseUser() as any,
          date: 1700002000,
          old_reaction: [],
          new_reaction: [{ type: 'emoji' as const, emoji: '❤️' }],
        },
      } as TelegramUpdateDto;

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.eventType).toBe('message_status');
      expect(result.reactions).toEqual([{ type: 'emoji', emoji: '❤️' }]);
    });
  });

  // ─── Chat ID conversion ─────────────────────────────────

  describe('chat id', () => {
    it('should convert chat.id to string', () => {
      const result = service.normalize(WEBHOOK_TOKEN, baseUpdate())!;

      expect(result.chatId).toBe('789');
      expect(typeof result.chatId).toBe('string');
    });
  });

  // ─── Idempotency key ────────────────────────────────────

  describe('idempotency key', () => {
    it('should follow format "tg-{update_id}"', () => {
      const result = service.normalize(WEBHOOK_TOKEN, baseUpdate())!;

      expect(result.idempotencyKey).toBe('tg-100');
    });
  });

  // ─── No message → null ──────────────────────────────────

  describe('update with no message', () => {
    it('should return null', () => {
      const update = { update_id: 300 } as TelegramUpdateDto;

      const result = service.normalize(WEBHOOK_TOKEN, update);

      expect(result).toBeNull();
    });
  });

  // ─── Sender name ────────────────────────────────────────

  describe('sender name', () => {
    it('should combine first_name + last_name', () => {
      const result = service.normalize(WEBHOOK_TOKEN, baseUpdate())!;

      expect(result.senderName).toBe('John Doe');
    });

    it('should use first_name only when last_name is absent', () => {
      const update = baseUpdate({
        message: baseMessage({
          from: baseUser({ last_name: undefined }),
        }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.senderName).toBe('John');
    });

    it('should return "Unknown" when from is absent', () => {
      const update = baseUpdate({
        message: baseMessage({ from: undefined }),
      });

      const result = service.normalize(WEBHOOK_TOKEN, update)!;

      expect(result.senderName).toBe('Unknown');
    });
  });
});
