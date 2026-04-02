import axios, {
  AxiosHeaders,
  AxiosInstance,
  AxiosResponse,
  InternalAxiosRequestConfig,
} from 'axios';
import { ConfigService } from '@nestjs/config';
import { UazapiClient } from './uazapi.client';
import {
  getRecord,
  getString,
  isRecord,
} from '../../../../shared/utils/type-guards';

jest.mock('axios');

const axiosFactory = jest.mocked(axios, { shallow: false });

const createAxiosInstanceMock = (): {
  instance: jest.Mocked<AxiosInstance>;
  postMock: jest.Mock;
} => {
  const postMock = jest.fn();
  const instance: Partial<jest.Mocked<AxiosInstance>> = {
    post: postMock as unknown as jest.Mocked<AxiosInstance>['post'],
    get: jest.fn(),
    delete: jest.fn(),
    interceptors: {
      request: { use: jest.fn(), eject: jest.fn(), clear: jest.fn() },
      response: { use: jest.fn(), eject: jest.fn(), clear: jest.fn() },
    },
    defaults: {
      headers: {
        common: {},
      } as AxiosInstance['defaults']['headers'],
    } as AxiosInstance['defaults'],
  };

  return {
    instance: instance as jest.Mocked<AxiosInstance>,
    postMock,
  };
};

const buildAxiosResponse = <T>(data: T): AxiosResponse<T> => ({
  data,
  status: 200,
  statusText: 'OK',
  headers: {},
  config: {
    headers: new AxiosHeaders(),
  } as InternalAxiosRequestConfig,
});

const buildConfigService = (
  overrides: Record<string, unknown> = {},
): ConfigService => {
  const defaults: Record<string, unknown> = {
    UAZAPI_BASE_URL: 'https://free.uazapi.com',
    UAZAPI_ADMIN_TOKEN: 'admin-token',
    UAZAPI_WEBHOOK_URL: 'https://hooks.interazap.local',
    UAZAPI_WEBHOOK_EVENTS: 'connection,messages',
    UAZAPI_WEBHOOK_EXCLUDE_MESSAGES: 'wasSentByApi',
    UAZAPI_WEBHOOK_RETRIES: '2',
    GATEWAY_DEBUG_HTTP: 'false',
  };

  const store = { ...defaults, ...overrides };
  const get = <T = string>(key: string): T | undefined =>
    store[key] as T | undefined;

  return { get } as ConfigService;
};

describe('UazapiClient', () => {
  let axiosInstance: jest.Mocked<AxiosInstance>;
  let postMock: jest.Mock;

  beforeEach(() => {
    const mock = createAxiosInstanceMock();
    axiosInstance = mock.instance;
    postMock = mock.postMock;
    axiosFactory.create.mockReturnValue(axiosInstance);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('configures webhook automatically when URL is provided', async () => {
    postMock
      .mockResolvedValueOnce(
        buildAxiosResponse({ token: 'abc-token', name: 'inst-1' }),
      )
      .mockResolvedValueOnce(buildAxiosResponse({ success: true }));

    const client = new UazapiClient(buildConfigService());
    const response = await client.initInstance({ name: 'inst-1' });

    expect(response).toMatchObject({
      token: 'abc-token',
      webhook: {
        url: 'https://hooks.interazap.local/webhooks/uazapi/instances/abc-token',
      },
    });

    expect(postMock).toHaveBeenCalledTimes(2);
    const webhookCall = postMock.mock.calls[1];
    if (!webhookCall) {
      throw new Error('Webhook configuration call not performed');
    }

    const [, payload, config] = webhookCall;
    if (!isRecord(payload)) {
      throw new Error('Webhook payload must be an object');
    }
    expect(getString(payload, 'url')).toBe(
      'https://hooks.interazap.local/webhooks/uazapi/instances/abc-token',
    );

    const configRecord = isRecord(config) ? config : null;
    const headers = configRecord ? getRecord(configRecord, 'headers') : null;
    if (!headers) {
      throw new Error('Webhook headers missing');
    }
    expect(getString(headers, 'token')).toBe('abc-token');
  });

  it('skips webhook configuration when URL is missing', async () => {
    postMock.mockResolvedValue(
      buildAxiosResponse({ token: 'abc-token', name: 'inst-1' }),
    );

    const client = new UazapiClient(
      buildConfigService({ UAZAPI_WEBHOOK_URL: undefined }),
    );
    const response = await client.initInstance({ name: 'inst-1' });

    expect(response).toMatchObject({ token: 'abc-token' });
    expect(response).not.toHaveProperty('webhook');
    expect(postMock).toHaveBeenCalledTimes(1);
  });

  describe('listInstances', () => {
    it('should list all instances using admin token', async () => {
      axiosInstance.get.mockResolvedValue(
        buildAxiosResponse([{ name: 'inst-1', token: 'token-1' }]),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.listInstances();

      expect(axiosInstance.get).toHaveBeenCalledWith('/instance/all', {
        headers: { admintoken: 'admin-token' },
      });
      expect(result).toEqual([{ name: 'inst-1', token: 'token-1' }]);
    });
  });

  describe('connectInstance', () => {
    it('should connect an instance', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ connected: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.connectInstance('inst-token', {
        key: 'value',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/instance/connect',
        { key: 'value' },
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ connected: true });
    });

    it('should connect an instance without body', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ connected: true }));

      const client = new UazapiClient(buildConfigService());
      await client.connectInstance('inst-token');

      expect(postMock).toHaveBeenCalledWith(
        '/instance/connect',
        {},
        {
          headers: { token: 'inst-token' },
        },
      );
    });
  });

  describe('disconnectInstance', () => {
    it('should disconnect an instance', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ disconnected: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.disconnectInstance('inst-token');

      expect(postMock).toHaveBeenCalledWith(
        '/instance/disconnect',
        {},
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ disconnected: true });
    });
  });

  describe('instanceStatus', () => {
    it('should get instance status', async () => {
      axiosInstance.get.mockResolvedValue(
        buildAxiosResponse({ status: 'connected' }),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.instanceStatus('inst-token');

      expect(axiosInstance.get).toHaveBeenCalledWith('/instance/status', {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual({ status: 'connected' });
    });
  });

  describe('deleteInstance', () => {
    it('should delete an instance', async () => {
      axiosInstance.delete.mockResolvedValue(
        buildAxiosResponse({ deleted: true }),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.deleteInstance('inst-token');

      expect(axiosInstance.delete).toHaveBeenCalledWith('/instance', {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual({ deleted: true });
    });
  });

  describe('sendText', () => {
    it('should send text message', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ messageId: 'msg-123' }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.sendText('inst-token', {
        phone: '5511999999999',
        message: 'Hello',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/send/text',
        {
          phone: '5511999999999',
          message: 'Hello',
        },
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ messageId: 'msg-123' });
    });
  });

  describe('sendFile', () => {
    it('should send file via /send/media endpoint', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ messageId: 'msg-456' }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.sendFile('inst-token', {
        number: '5511999999999',
        file: 'https://example.com/file.pdf',
        type: 'document',
        text: 'PDF document',
      });

      expect(postMock).toHaveBeenCalledWith('/send/media', expect.any(Object), {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual({ messageId: 'msg-456' });
    });
  });

  describe('sendPresence', () => {
    it('should send presence', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.sendPresence('inst-token', {
        phone: '5511999999999',
        state: 'composing',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/message/presence',
        expect.any(Object),
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ success: true });
    });
  });

  describe('markAsRead', () => {
    it('should mark message as read', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.markAsRead('inst-token', {
        phone: '5511999999999',
        messageId: 'msg-123',
      });

      expect(postMock).toHaveBeenCalledWith('/chat/read', expect.any(Object), {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual({ success: true });
    });
  });

  describe('listContacts', () => {
    it('should list contacts via GET when no body', async () => {
      axiosInstance.get.mockResolvedValue(
        buildAxiosResponse([{ name: 'John', phone: '5511999999999' }]),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.listContacts('inst-token');

      expect(axiosInstance.get).toHaveBeenCalledWith('/contacts', {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual([{ name: 'John', phone: '5511999999999' }]);
    });

    it('should list contacts via POST when body provided', async () => {
      postMock.mockResolvedValue(
        buildAxiosResponse([{ name: 'John', phone: '5511999999999' }]),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.listContacts('inst-token', {
        search: 'John',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/contacts/list',
        { search: 'John' },
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual([{ name: 'John', phone: '5511999999999' }]);
    });
  });

  describe('addContact', () => {
    it('should add contact', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.addContact('inst-token', {
        name: 'John',
        phone: '5511999999999',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/contact/add',
        expect.any(Object),
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ success: true });
    });
  });

  describe('removeContact', () => {
    it('should remove contact', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.removeContact('inst-token', {
        phone: '5511999999999',
      });

      expect(postMock).toHaveBeenCalledWith(
        '/contact/remove',
        expect.any(Object),
        {
          headers: { token: 'inst-token' },
        },
      );
      expect(result).toEqual({ success: true });
    });
  });

  describe('configureWebhook', () => {
    it('should configure webhook', async () => {
      postMock.mockResolvedValue(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.configureWebhook('inst-token', {
        url: 'https://example.com/webhook',
        events: ['messages'],
      });

      expect(postMock).toHaveBeenCalledWith('/webhook', expect.any(Object), {
        headers: { token: 'inst-token' },
      });
      expect(result).toEqual({ success: true });
    });
  });

  describe('initInstance edge cases', () => {
    it('should return instance without webhook when token not found', async () => {
      postMock.mockResolvedValue(
        buildAxiosResponse({ name: 'no-token-instance' }),
      );

      const client = new UazapiClient(buildConfigService());
      const result = await client.initInstance({ name: 'test' });

      expect(result).toEqual({ name: 'no-token-instance' });
      expect(postMock).toHaveBeenCalledTimes(1);
    });

    it('should extract token from nested instance object', async () => {
      postMock
        .mockResolvedValueOnce(
          buildAxiosResponse({
            instance: { token: 'nested-token' },
            name: 'nested-inst',
          }),
        )
        .mockResolvedValueOnce(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.initInstance({ name: 'test' });

      expect(result).toMatchObject({
        instance: { token: 'nested-token' },
        webhook: expect.objectContaining({
          url: expect.stringContaining('nested-token'),
        }),
      });
    });

    it('should return non-object instance as is', async () => {
      postMock.mockResolvedValue(buildAxiosResponse('string-response'));

      const client = new UazapiClient(buildConfigService());
      const result = await client.initInstance({ name: 'test' });

      expect(result).toBe('string-response');
    });
  });

  describe('error handling', () => {
    it('should throw HttpException on axios error with response', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 400,
          data: { error: 'Invalid token' },
        },
        message: 'Request failed',
      };
      axiosInstance.get.mockRejectedValue(axiosError);
      (axios.isAxiosError as unknown as jest.Mock).mockReturnValue(true);

      const client = new UazapiClient(buildConfigService());

      await expect(client.listInstances()).rejects.toThrow('Invalid token');
    });

    it('should throw HttpException with message from response.message', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 401,
          data: { message: 'Unauthorized access' },
        },
        message: 'Request failed',
      };
      axiosInstance.get.mockRejectedValue(axiosError);
      (axios.isAxiosError as unknown as jest.Mock).mockReturnValue(true);

      const client = new UazapiClient(buildConfigService());

      await expect(client.instanceStatus('token')).rejects.toThrow(
        'Unauthorized access',
      );
    });

    it('should throw generic error on non-axios error', async () => {
      axiosInstance.get.mockRejectedValue(new Error('Network error'));
      (axios.isAxiosError as unknown as jest.Mock).mockReturnValue(false);

      const client = new UazapiClient(buildConfigService());

      await expect(client.listInstances()).rejects.toThrow('Erro interno');
    });

    it('should throw generic error on unknown error type', async () => {
      axiosInstance.get.mockRejectedValue('string error');
      (axios.isAxiosError as unknown as jest.Mock).mockReturnValue(false);

      const client = new UazapiClient(buildConfigService());

      await expect(client.listInstances()).rejects.toThrow('Erro interno');
    });

    it('should throw error when admin token is not configured', async () => {
      const client = new UazapiClient(
        buildConfigService({ UAZAPI_ADMIN_TOKEN: undefined }),
      );

      await expect(client.listInstances()).rejects.toThrow(
        'UAZAPI_ADMIN_TOKEN is not configured',
      );
    });

    it('should handle delete instance errors', async () => {
      const axiosError = {
        isAxiosError: true,
        response: { status: 404, data: { error: 'Instance not found' } },
        message: 'Not found',
      };
      axiosInstance.delete.mockRejectedValue(axiosError);
      (axios.isAxiosError as unknown as jest.Mock).mockReturnValue(true);

      const client = new UazapiClient(buildConfigService());

      await expect(client.deleteInstance('token')).rejects.toThrow(
        'Instance not found',
      );
    });
  });

  describe('webhook retry logic', () => {
    it('should retry webhook configuration on failure', async () => {
      postMock
        .mockResolvedValueOnce(
          buildAxiosResponse({ token: 'retry-token', name: 'inst' }),
        )
        .mockRejectedValueOnce(new Error('Temporary failure'))
        .mockResolvedValueOnce(buildAxiosResponse({ success: true }));

      const client = new UazapiClient(buildConfigService());
      const result = await client.initInstance({ name: 'test' });

      expect(result).toMatchObject({
        webhook: expect.objectContaining({ url: expect.any(String) }),
      });
      expect(postMock).toHaveBeenCalledTimes(3);
    });

    it('should throw after max retries', async () => {
      postMock
        .mockResolvedValueOnce(
          buildAxiosResponse({ token: 'retry-token', name: 'inst' }),
        )
        .mockRejectedValueOnce(new Error('Failure 1'))
        .mockRejectedValueOnce(new Error('Failure 2'));

      const client = new UazapiClient(buildConfigService());

      await expect(client.initInstance({ name: 'test' })).rejects.toThrow(
        'Failure 2',
      );
    });
  });

  describe('HTTP debug mode', () => {
    it('should enable interceptors when debug is true', () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      expect(axiosInstance.interceptors.request.use).toHaveBeenCalled();
      expect(axiosInstance.interceptors.response.use).toHaveBeenCalled();
    });

    it('should log request details in request interceptor', () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const requestCallback = (
        axiosInstance.interceptors.request.use as jest.Mock
      ).mock.calls[0][0];
      const mockConfig = {
        method: 'post',
        url: '/test-endpoint',
        headers: { token: 'secret-token' },
        data: { password: 'secret123' },
      };

      const result = requestCallback(mockConfig);

      expect(result).toBe(mockConfig);
    });

    it('should log response details in response interceptor', () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const [successCallback] = (
        axiosInstance.interceptors.response.use as jest.Mock
      ).mock.calls[0];
      const mockResponse = {
        status: 200,
        config: { url: '/test' },
        data: { secret: 'value' },
      };

      const result = successCallback(mockResponse);

      expect(result).toBe(mockResponse);
    });

    it('should log and reject AxiosError in response interceptor', async () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const [, errorCallback] = (
        axiosInstance.interceptors.response.use as jest.Mock
      ).mock.calls[0];
      const axiosError = {
        isAxiosError: true,
        response: { status: 500, data: { error: 'Server error' } },
        config: { url: '/test' },
        message: 'Request failed',
      };

      // Mock axios.isAxiosError to return true
      jest.spyOn(axios, 'isAxiosError').mockReturnValue(true);

      await expect(errorCallback(axiosError)).rejects.toBe(axiosError);
    });

    it('should log AxiosError without response', async () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const [, errorCallback] = (
        axiosInstance.interceptors.response.use as jest.Mock
      ).mock.calls[0];
      const axiosError = {
        isAxiosError: true,
        response: undefined,
        config: { url: '/test' },
        message: 'Network error',
      };

      jest.spyOn(axios, 'isAxiosError').mockReturnValue(true);

      await expect(errorCallback(axiosError)).rejects.toBe(axiosError);
    });

    it('should handle non-AxiosError Error objects', async () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const [, errorCallback] = (
        axiosInstance.interceptors.response.use as jest.Mock
      ).mock.calls[0];
      const error = new Error('Some error');

      jest.spyOn(axios, 'isAxiosError').mockReturnValue(false);

      await expect(errorCallback(error)).rejects.toBe(error);
    });

    it('should handle unknown error types', async () => {
      new UazapiClient(buildConfigService({ GATEWAY_DEBUG_HTTP: 'true' }));

      const [, errorCallback] = (
        axiosInstance.interceptors.response.use as jest.Mock
      ).mock.calls[0];
      const unknownError = { weird: 'object' };

      jest.spyOn(axios, 'isAxiosError').mockReturnValue(false);

      await expect(errorCallback(unknownError)).rejects.toThrow(
        'Uazapi request failed',
      );
    });
  });

  describe('listContacts with empty body', () => {
    it('should use GET for empty object body', async () => {
      axiosInstance.get.mockResolvedValue(buildAxiosResponse([]));

      const client = new UazapiClient(buildConfigService());
      await client.listContacts('inst-token', {});

      expect(axiosInstance.get).toHaveBeenCalledWith('/contacts', {
        headers: { token: 'inst-token' },
      });
    });
  });
});
