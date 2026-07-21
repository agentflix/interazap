import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { MetaClient } from './meta.client';
import type { MetaTemplateCreatePayload } from './meta.dto';

jest.mock('axios');

describe('MetaClient', () => {
  let client: MetaClient;
  let httpInstance: {
    post: jest.Mock;
    delete: jest.Mock;
    get: jest.Mock;
    interceptors: {
      request: { use: jest.Mock };
      response: { use: jest.Mock };
    };
  };

  beforeEach(async () => {
    httpInstance = {
      post: jest.fn(),
      delete: jest.fn(),
      get: jest.fn(),
      interceptors: {
        request: { use: jest.fn() },
        response: { use: jest.fn() },
      },
    };
    (axios.create as jest.Mock).mockReturnValue(httpInstance);

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        MetaClient,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string) => {
              if (key === 'meta.graphApiUrl') {
                return 'https://graph.facebook.com/v25.0';
              }
              return '';
            }),
          },
        },
      ],
    }).compile();

    client = module.get<MetaClient>(MetaClient);
  });

  describe('createTemplate', () => {
    const wabaId = 'waba-123';
    const accessToken = 'token-xyz';
    const payload: MetaTemplateCreatePayload = {
      name: 'welcome',
      language: 'pt_BR',
      category: 'MARKETING',
      components: [{ type: 'BODY', text: 'Hello {{1}}' }],
    };

    it('POSTs to /{wabaId}/message_templates with access_token query param', async () => {
      httpInstance.post.mockResolvedValue({
        data: { id: 'tpl-1', status: 'PENDING', category: 'MARKETING' },
      });

      const result = await client.createTemplate(wabaId, accessToken, payload);

      expect(httpInstance.post).toHaveBeenCalledWith(
        `/${wabaId}/message_templates`,
        payload,
        { params: { access_token: accessToken } },
      );
      expect(result).toEqual({
        id: 'tpl-1',
        status: 'PENDING',
        category: 'MARKETING',
      });
    });

    it('propagates Meta error message on 400', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 400,
          data: {
            error: {
              message: 'Template name already exists',
              code: 100,
            },
          },
        },
        message: 'Request failed with status code 400',
      };
      httpInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.createTemplate(wabaId, accessToken, payload),
      ).rejects.toThrow('Template name already exists');
    });
  });

  describe('deleteTemplate', () => {
    const wabaId = 'waba-123';
    const accessToken = 'token-xyz';

    it('DELETEs /{wabaId}/message_templates with access_token and name as query', async () => {
      httpInstance.delete.mockResolvedValue({ data: { success: true } });

      const result = await client.deleteTemplate(
        wabaId,
        accessToken,
        'welcome',
      );

      expect(httpInstance.delete).toHaveBeenCalledWith(
        `/${wabaId}/message_templates`,
        { params: { access_token: accessToken, name: 'welcome' } },
      );
      expect(result).toEqual({ success: true });
    });

    it('throws Meta error message on failure', async () => {
      const axiosError = {
        isAxiosError: true,
        response: {
          status: 404,
          data: {
            error: { message: 'Template not found' },
          },
        },
        message: 'Request failed with status code 404',
      };
      httpInstance.delete.mockRejectedValue(axiosError);

      await expect(
        client.deleteTemplate(wabaId, accessToken, 'missing'),
      ).rejects.toThrow('Template not found');
    });
  });
});
