import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ZapiClient } from './zapi.client';
import axios from 'axios';

jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('ZapiClient', () => {
  let client: ZapiClient;
  let mockAxiosInstance: {
    post: jest.Mock;
    get: jest.Mock;
    delete: jest.Mock;
  };

  beforeEach(async () => {
    mockAxiosInstance = {
      post: jest.fn(),
      get: jest.fn(),
      delete: jest.fn(),
    };

    mockedAxios.create.mockReturnValue(
      mockAxiosInstance as unknown as ReturnType<typeof axios.create>,
    );

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        ZapiClient,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockReturnValue({
              baseUrl: 'https://api.z-api.io',
              clientToken: 'test-client-token',
            }),
          },
        },
      ],
    }).compile();

    client = module.get<ZapiClient>(ZapiClient);
  });

  describe('sendText', () => {
    it('should send text message successfully', async () => {
      const expectedResponse = { zapiMessageId: 'msg-123' };
      mockAxiosInstance.post.mockResolvedValue({ data: expectedResponse });

      const result = await client.sendText('instance-1', 'token-1', {
        phone: '5511999999999',
        message: 'Hello World',
      });

      expect(result).toEqual(expectedResponse);
      expect(mockAxiosInstance.post).toHaveBeenCalledWith(
        '/instances/instance-1/token/token-1/send-text',
        { phone: '5511999999999', message: 'Hello World' },
      );
    });

    it('should throw on API error', async () => {
      mockAxiosInstance.post.mockRejectedValue(new Error('API Error'));

      await expect(
        client.sendText('instance-1', 'token-1', {
          phone: '5511999999999',
          message: 'Hello',
        }),
      ).rejects.toThrow('API Error');
    });
  });

  describe('sendImage', () => {
    it('should send image successfully', async () => {
      const expectedResponse = { messageId: 'img-123' };
      mockAxiosInstance.post.mockResolvedValue({ data: expectedResponse });

      const result = await client.sendImage('instance-1', 'token-1', {
        phone: '5511999999999',
        image: 'https://example.com/image.jpg',
        caption: 'My caption',
      });

      expect(result).toEqual(expectedResponse);
      expect(mockAxiosInstance.post).toHaveBeenCalledWith(
        '/instances/instance-1/token/token-1/send-image',
        {
          phone: '5511999999999',
          image: 'https://example.com/image.jpg',
          caption: 'My caption',
        },
      );
    });
  });

  describe('sendDocument', () => {
    it('should send document successfully', async () => {
      const expectedResponse = { id: 'doc-123' };
      mockAxiosInstance.post.mockResolvedValue({ data: expectedResponse });

      const result = await client.sendDocument('instance-1', 'token-1', {
        phone: '5511999999999',
        document: 'https://example.com/file.pdf',
        fileName: 'file.pdf',
      });

      expect(result).toEqual(expectedResponse);
    });
  });

  describe('sendAudio', () => {
    it('should send audio successfully', async () => {
      const expectedResponse = { zapiMessageId: 'audio-123' };
      mockAxiosInstance.post.mockResolvedValue({ data: expectedResponse });

      const result = await client.sendAudio('instance-1', 'token-1', {
        phone: '5511999999999',
        audio: 'https://example.com/audio.mp3',
      });

      expect(result).toEqual(expectedResponse);
    });
  });

  describe('sendVideo', () => {
    it('should send video successfully', async () => {
      const expectedResponse = { messageId: 'video-123' };
      mockAxiosInstance.post.mockResolvedValue({ data: expectedResponse });

      const result = await client.sendVideo('instance-1', 'token-1', {
        phone: '5511999999999',
        video: 'https://example.com/video.mp4',
        caption: 'Video caption',
      });

      expect(result).toEqual(expectedResponse);
    });
  });

  describe('getStatus', () => {
    it('should return connection status', async () => {
      const expectedResponse = {
        connected: true,
        session: '5511999999999',
        smartphoneConnected: true,
      };
      mockAxiosInstance.get.mockResolvedValue({ data: expectedResponse });

      const result = await client.getStatus('instance-1', 'token-1');

      expect(result).toEqual(expectedResponse);
      expect(mockAxiosInstance.get).toHaveBeenCalledWith(
        '/instances/instance-1/token/token-1/status',
      );
    });
  });

  describe('getQrCode', () => {
    it('should return QR code', async () => {
      mockAxiosInstance.get.mockResolvedValue({
        data: { value: 'base64-qr-code' },
      });

      const result = await client.getQrCode('instance-1', 'token-1');

      expect(result).toBe('base64-qr-code');
    });

    it('should return null on error', async () => {
      mockAxiosInstance.get.mockRejectedValue(new Error('Not found'));

      const result = await client.getQrCode('instance-1', 'token-1');

      expect(result).toBeNull();
    });
  });

  describe('disconnect', () => {
    it('should disconnect successfully', async () => {
      mockAxiosInstance.delete.mockResolvedValue({});

      await expect(
        client.disconnect('instance-1', 'token-1'),
      ).resolves.not.toThrow();

      expect(mockAxiosInstance.delete).toHaveBeenCalledWith(
        '/instances/instance-1/token/token-1/disconnect',
      );
    });

    it('should throw on disconnect error', async () => {
      mockAxiosInstance.delete.mockRejectedValue(
        new Error('Disconnect failed'),
      );

      await expect(client.disconnect('instance-1', 'token-1')).rejects.toThrow(
        'Disconnect failed',
      );
    });
  });

  describe('error handling', () => {
    it('should handle sendImage error', async () => {
      mockAxiosInstance.post.mockRejectedValue(new Error('Image send failed'));

      await expect(
        client.sendImage('instance-1', 'token-1', {
          phone: '5511999999999',
          image: 'https://example.com/image.jpg',
        }),
      ).rejects.toThrow('Image send failed');
    });

    it('should handle sendDocument error', async () => {
      mockAxiosInstance.post.mockRejectedValue(
        new Error('Document send failed'),
      );

      await expect(
        client.sendDocument('instance-1', 'token-1', {
          phone: '5511999999999',
          document: 'https://example.com/file.pdf',
        }),
      ).rejects.toThrow('Document send failed');
    });

    it('should handle sendAudio error', async () => {
      mockAxiosInstance.post.mockRejectedValue(new Error('Audio send failed'));

      await expect(
        client.sendAudio('instance-1', 'token-1', {
          phone: '5511999999999',
          audio: 'https://example.com/audio.mp3',
        }),
      ).rejects.toThrow('Audio send failed');
    });

    it('should handle sendVideo error', async () => {
      mockAxiosInstance.post.mockRejectedValue(new Error('Video send failed'));

      await expect(
        client.sendVideo('instance-1', 'token-1', {
          phone: '5511999999999',
          video: 'https://example.com/video.mp4',
        }),
      ).rejects.toThrow('Video send failed');
    });

    it('should handle getStatus error', async () => {
      mockAxiosInstance.get.mockRejectedValue(new Error('Status check failed'));

      await expect(client.getStatus('instance-1', 'token-1')).rejects.toThrow(
        'Status check failed',
      );
    });

    it('should handle AxiosError with response data', async () => {
      const axiosError = {
        message: 'Request failed',
        response: { data: { error: 'Invalid token' } },
        isAxiosError: true,
      };
      Object.setPrototypeOf(axiosError, Error.prototype);
      mockAxiosInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.sendText('instance-1', 'token-1', {
          phone: '5511999999999',
          message: 'Test',
        }),
      ).rejects.toEqual(axiosError);
    });
  });

  describe('getQrCode edge cases', () => {
    it('should return null when value is undefined', async () => {
      mockAxiosInstance.get.mockResolvedValue({ data: {} });

      const result = await client.getQrCode('instance-1', 'token-1');

      expect(result).toBeNull();
    });
  });

  describe('configuration', () => {
    it('should use default config when not provided', async () => {
      const moduleWithoutConfig: TestingModule = await Test.createTestingModule(
        {
          providers: [
            ZapiClient,
            {
              provide: ConfigService,
              useValue: {
                get: jest.fn().mockReturnValue(undefined),
              },
            },
          ],
        },
      ).compile();

      const clientWithoutConfig =
        moduleWithoutConfig.get<ZapiClient>(ZapiClient);

      expect(clientWithoutConfig).toBeDefined();
    });
  });
});
