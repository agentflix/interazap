import 'reflect-metadata';
import { validate } from 'class-validator';
import { plainToInstance } from 'class-transformer';
import {
  TelegramUpdateDto,
  TelegramMessageDto,
  TelegramChatDto,
  TelegramUserDto,
  TelegramPhotoSizeDto,
} from '../../src/bot/dto/telegram-update.dto';

// ─── Helpers ─────────────────────────────────────────────────

function validUser(
  overrides: Partial<TelegramUserDto> = {},
): Record<string, unknown> {
  return {
    id: 123456,
    is_bot: false,
    first_name: 'John',
    ...overrides,
  };
}

function validChat(
  overrides: Partial<TelegramChatDto> = {},
): Record<string, unknown> {
  return {
    id: 789,
    type: 'private',
    ...overrides,
  };
}

function validMessage(
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  return {
    message_id: 1,
    from: validUser(),
    chat: validChat(),
    date: 1700000000,
    text: 'Hello',
    ...overrides,
  };
}

function validUpdate(
  overrides: Record<string, unknown> = {},
): Record<string, unknown> {
  return {
    update_id: 100,
    message: validMessage(),
    ...overrides,
  };
}

async function validateDto(
  plain: Record<string, unknown>,
): Promise<ReturnType<typeof validate>> {
  const instance = plainToInstance(TelegramUpdateDto, plain, {
    excludeExtraneousValues: false,
  });
  return validate(instance, { whitelist: true, forbidNonWhitelisted: true });
}

// ─── Tests ───────────────────────────────────────────────────

describe('TelegramUpdateDto', () => {
  describe('valid payloads', () => {
    it('should pass with a complete valid update', async () => {
      const errors = await validateDto(validUpdate());
      expect(errors).toHaveLength(0);
    });

    it('should pass with message-only update', async () => {
      const errors = await validateDto(validUpdate());
      expect(errors).toHaveLength(0);
    });

    it('should pass with edited_message update', async () => {
      const errors = await validateDto({
        update_id: 101,
        edited_message: {
          ...validMessage(),
          edit_date: 1700001000,
        },
      });
      expect(errors).toHaveLength(0);
    });

    it('should pass with message_reaction update', async () => {
      const errors = await validateDto({
        update_id: 102,
        message_reaction: {
          chat: validChat(),
          message_id: 5,
          user: validUser(),
          date: 1700002000,
          old_reaction: [],
          new_reaction: [{ type: 'emoji', emoji: '👍' }],
        },
      });
      expect(errors).toHaveLength(0);
    });

    it('should pass with empty update (no message/edited/reaction — all optional)', async () => {
      const errors = await validateDto({ update_id: 103 });
      expect(errors).toHaveLength(0);
    });
  });

  describe('invalid payloads', () => {
    it('should fail when update_id is missing', async () => {
      const errors = await validateDto({ message: validMessage() } as Record<
        string,
        unknown
      >);
      expect(errors.length).toBeGreaterThan(0);
      const updateIdError = errors.find((e) => e.property === 'update_id');
      expect(updateIdError).toBeDefined();
    });

    it('should fail when update_id is a string', async () => {
      const errors = await validateDto({
        update_id: 'abc',
        message: validMessage(),
      });
      expect(errors.length).toBeGreaterThan(0);
      const updateIdError = errors.find((e) => e.property === 'update_id');
      expect(updateIdError).toBeDefined();
    });

    it('should strip extra unknown fields (whitelist)', async () => {
      const instance = plainToInstance(TelegramUpdateDto, {
        ...validUpdate(),
        unknown_field: 'should be removed',
      });
      const errors = await validate(instance, {
        whitelist: true,
        forbidNonWhitelisted: true,
      });

      // forbidNonWhitelisted makes unknown fields an error
      const whitelistError = errors.find((e) => e.property === 'unknown_field');
      expect(whitelistError).toBeDefined();
    });

    it('should fail with invalid chat type in nested message', async () => {
      const errors = await validateDto(
        validUpdate({
          message: validMessage({
            chat: { id: 1, type: 'invalid_type' },
          }),
        }),
      );
      expect(errors.length).toBeGreaterThan(0);
    });

    it('should fail when message text exceeds 4096 chars', async () => {
      const longText = 'x'.repeat(4097);
      const errors = await validateDto(
        validUpdate({
          message: validMessage({ text: longText }),
        }),
      );
      expect(errors.length).toBeGreaterThan(0);
    });
  });

  describe('photo array validation', () => {
    it('should pass with valid photo array', async () => {
      const errors = await validateDto(
        validUpdate({
          message: validMessage({
            photo: [
              { file_id: 'f1', file_unique_id: 'u1', width: 100, height: 100 },
              { file_id: 'f2', file_unique_id: 'u2', width: 400, height: 400 },
            ],
          }),
        }),
      );
      expect(errors).toHaveLength(0);
    });

    it('should fail with invalid photo item (missing file_id)', async () => {
      const errors = await validateDto(
        validUpdate({
          message: validMessage({
            photo: [{ file_unique_id: 'u1', width: 100, height: 100 }],
          }),
        }),
      );
      expect(errors.length).toBeGreaterThan(0);
    });
  });

  describe('TelegramPhotoSizeDto standalone', () => {
    it('should fail when width is negative', async () => {
      const instance = plainToInstance(TelegramPhotoSizeDto, {
        file_id: 'f1',
        file_unique_id: 'u1',
        width: -1,
        height: 100,
      });
      const errors = await validate(instance);
      expect(errors.length).toBeGreaterThan(0);
    });
  });
});
