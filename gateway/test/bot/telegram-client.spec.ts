import 'reflect-metadata';
import { Test, TestingModule } from '@nestjs/testing';
import { HttpService } from '@nestjs/axios';
import { of, throwError } from 'rxjs';
import { AxiosResponse, AxiosError, AxiosHeaders } from 'axios';
import { TelegramClientService } from '../../src/bot/services/telegram-client.service';

// ─── Helpers ─────────────────────────────────────────────────

const BOT_TOKEN = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
const CHAT_ID = '789';

function axiosResponse<T>(data: T): AxiosResponse<T> {
  return {
    data,
    status: 200,
    statusText: 'OK',
    headers: {},
    config: { headers: new AxiosHeaders() },
  };
}

function tgOk<T>(result: T) {
  return { ok: true, result };
}

// ─── Tests ───────────────────────────────────────────────────

describe('TelegramClientService', () => {
  let service: TelegramClientService;
  let httpService: { post: jest.Mock };

  beforeEach(async () => {
    httpService = { post: jest.fn() };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        TelegramClientService,
        { provide: HttpService, useValue: httpService },
      ],
    }).compile();

    service = module.get(TelegramClientService);
  });

  // ─── sendMessage ─────────────────────────────────────────

  describe('sendMessage', () => {
    it('should call correct URL with correct params', async () => {
      httpService.post.mockReturnValue(
        of(axiosResponse(tgOk({ message_id: 1 }))),
      );

      const result = await service.sendMessage(BOT_TOKEN, CHAT_ID, 'Hello');

      expect(httpService.post).toHaveBeenCalledWith(
        `https://api.telegram.org/bot${BOT_TOKEN}/sendMessage`,
        { chat_id: CHAT_ID, text: 'Hello' },
        expect.objectContaining({
          headers: { 'Content-Type': 'application/json' },
        }),
      );
      expect(result.ok).toBe(true);
    });

    it('should include reply_parameters when replyToMessageId is provided', async () => {
      httpService.post.mockReturnValue(
        of(axiosResponse(tgOk({ message_id: 2 }))),
      );

      await service.sendMessage(BOT_TOKEN, CHAT_ID, 'Reply', 10);

      expect(httpService.post).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({ reply_parameters: { message_id: 10 } }),
        expect.anything(),
      );
    });
  });

  // ─── sendPhoto ───────────────────────────────────────────

  describe('sendPhoto', () => {
    it('should send photo with optional caption', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendPhoto(BOT_TOKEN, CHAT_ID, 'photo_url', 'Nice pic');

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/sendPhoto'),
        { chat_id: CHAT_ID, photo: 'photo_url', caption: 'Nice pic' },
        expect.anything(),
      );
    });

    it('should send photo without caption when omitted', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendPhoto(BOT_TOKEN, CHAT_ID, 'photo_url');

      const body = httpService.post.mock.calls[0][1];
      expect(body).not.toHaveProperty('caption');
    });
  });

  // ─── sendVideo ───────────────────────────────────────────

  describe('sendVideo', () => {
    it('should send video with optional caption', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendVideo(BOT_TOKEN, CHAT_ID, 'video_url', 'A video');

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/sendVideo'),
        { chat_id: CHAT_ID, video: 'video_url', caption: 'A video' },
        expect.anything(),
      );
    });
  });

  // ─── sendDocument ────────────────────────────────────────

  describe('sendDocument', () => {
    it('should send document', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendDocument(BOT_TOKEN, CHAT_ID, 'doc_url');

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/sendDocument'),
        { chat_id: CHAT_ID, document: 'doc_url' },
        expect.anything(),
      );
    });
  });

  // ─── sendLocation ────────────────────────────────────────

  describe('sendLocation', () => {
    it('should send latitude and longitude', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendLocation(BOT_TOKEN, CHAT_ID, -23.55, -46.63);

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/sendLocation'),
        { chat_id: CHAT_ID, latitude: -23.55, longitude: -46.63 },
        expect.anything(),
      );
    });
  });

  // ─── sendChatAction ──────────────────────────────────────

  describe('sendChatAction', () => {
    it('should send typing action', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(true))));

      await service.sendChatAction(BOT_TOKEN, CHAT_ID, 'typing');

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/sendChatAction'),
        { chat_id: CHAT_ID, action: 'typing' },
        expect.anything(),
      );
    });
  });

  // ─── setWebhook ──────────────────────────────────────────

  describe('setWebhook', () => {
    it('should send URL and secret_token', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(true))));

      await service.setWebhook(
        BOT_TOKEN,
        'https://example.com/webhook',
        'secret123',
      );

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/setWebhook'),
        { url: 'https://example.com/webhook', secret_token: 'secret123' },
        expect.anything(),
      );
    });
  });

  // ─── deleteWebhook ───────────────────────────────────────

  describe('deleteWebhook', () => {
    it('should send request', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(true))));

      await service.deleteWebhook(BOT_TOKEN);

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/deleteWebhook'),
        {},
        expect.anything(),
      );
    });

    it('should include drop_pending_updates when provided', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(true))));

      await service.deleteWebhook(BOT_TOKEN, true);

      expect(httpService.post).toHaveBeenCalledWith(
        expect.any(String),
        { drop_pending_updates: true },
        expect.anything(),
      );
    });
  });

  // ─── getMe ───────────────────────────────────────────────

  describe('getMe', () => {
    it('should return bot info', async () => {
      const botInfo = { id: 123456, is_bot: true, first_name: 'TestBot' };
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(botInfo))));

      const result = await service.getMe(BOT_TOKEN);

      expect(result.ok).toBe(true);
      expect(result.result).toEqual(botInfo);
    });
  });

  // ─── getFile ─────────────────────────────────────────────

  describe('getFile', () => {
    it('should return file path', async () => {
      const fileData = { file_id: 'f1', file_path: 'photos/file_0.jpg' };
      httpService.post.mockReturnValue(of(axiosResponse(tgOk(fileData))));

      const result = await service.getFile(BOT_TOKEN, 'f1');

      expect(result.result).toEqual(fileData);
      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/getFile'),
        { file_id: 'f1' },
        expect.anything(),
      );
    });
  });

  // ─── getUpdates ──────────────────────────────────────────

  describe('getUpdates', () => {
    it('should send offset and timeout', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk([]))));

      await service.getUpdates(BOT_TOKEN, 100, 55);

      expect(httpService.post).toHaveBeenCalledWith(
        expect.stringContaining('/getUpdates'),
        { offset: 100, timeout: 55 },
        expect.anything(),
      );
    });

    it('should omit undefined params', async () => {
      httpService.post.mockReturnValue(of(axiosResponse(tgOk([]))));

      await service.getUpdates(BOT_TOKEN);

      const body = httpService.post.mock.calls[0][1];
      expect(body).toEqual({});
    });
  });

  // ─── getFileUrl ──────────────────────────────────────────

  describe('getFileUrl', () => {
    it('should build correct URL', () => {
      const url = service.getFileUrl(BOT_TOKEN, 'photos/file_0.jpg');
      expect(url).toBe(
        `https://api.telegram.org/file/bot${BOT_TOKEN}/photos/file_0.jpg`,
      );
    });
  });

  // ─── Error handling ──────────────────────────────────────

  describe('error handling', () => {
    it('should throw AxiosError as-is (for CircuitBreaker)', async () => {
      const axiosError = new AxiosError(
        'Request failed',
        '400',
        undefined,
        undefined,
        {
          status: 400,
          statusText: 'Bad Request',
          data: { ok: false, description: 'Bad Request: chat not found' },
          headers: {},
          config: { headers: new AxiosHeaders() },
        },
      );

      httpService.post.mockReturnValue(throwError(() => axiosError));

      await expect(
        service.sendMessage(BOT_TOKEN, CHAT_ID, 'Hi'),
      ).rejects.toThrow(AxiosError);
    });
  });

  // ─── Token masking ──────────────────────────────────────

  describe('token masking', () => {
    it('should mask token in logs (not expose full token)', async () => {
      const logSpy = jest.spyOn(service['logger'], 'debug');
      httpService.post.mockReturnValue(of(axiosResponse(tgOk({}))));

      await service.sendMessage(BOT_TOKEN, CHAT_ID, 'Test');

      expect(logSpy).toHaveBeenCalled();
      const logMessage = logSpy.mock.calls[0][0] as string;
      expect(logMessage).not.toContain('ABC-DEF1234ghIkl-zyx57W2v1u123ew11');
      expect(logMessage).toContain('123456:***');
    });
  });
});
