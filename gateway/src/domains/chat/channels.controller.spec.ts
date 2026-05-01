import { Test, TestingModule } from '@nestjs/testing';
import { HttpException, HttpStatus, NotFoundException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { ChannelsController } from './channels.controller';
import { MetaAdapter } from './providers/meta/meta.adapter';
import { DatabaseService } from '../../infrastructure/database/database.service';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { MetaTemplate } from './contracts/meta-provider.interface';
import type { MetaTemplateCreatePayload } from './providers/meta/meta.dto';

describe('ChannelsController', () => {
  let controller: ChannelsController;
  let metaAdapter: {
    listTemplates: jest.Mock;
    invalidateTemplatesCache: jest.Mock;
    createTemplate: jest.Mock;
    deleteTemplate: jest.Mock;
  };
  let databaseService: { query: jest.Mock };

  const channelId = 'channel-123';
  const accessToken = 'access-token-xyz';
  const wabaId = 'waba-456';
  const channelRow = {
    id: channelId,
    provider: 'meta',
    settings_json: { access_token: accessToken, waba_id: wabaId },
  };
  const approved: MetaTemplate = {
    name: 'welcome',
    status: 'APPROVED',
    category: 'MARKETING',
    language: 'pt_BR',
    components: [],
  };

  beforeEach(async () => {
    metaAdapter = {
      listTemplates: jest.fn(),
      invalidateTemplatesCache: jest.fn().mockResolvedValue(undefined),
      createTemplate: jest.fn(),
      deleteTemplate: jest.fn(),
    };
    databaseService = {
      query: jest.fn().mockResolvedValue({ rows: [channelRow] }),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [ChannelsController],
      providers: [
        { provide: MetaAdapter, useValue: metaAdapter },
        { provide: DatabaseService, useValue: databaseService },
        { provide: ConfigService, useValue: { get: jest.fn() } },
        InternalApiKeyGuard,
      ],
    }).compile();

    controller = module.get<ChannelsController>(ChannelsController);
  });

  describe('GET :id/templates', () => {
    it('lists approved templates by default', async () => {
      metaAdapter.listTemplates.mockResolvedValue([approved]);

      const result = await controller.listTemplates(channelId);

      expect(result).toEqual([approved]);
      expect(metaAdapter.listTemplates).toHaveBeenCalledWith(
        accessToken,
        false,
      );
    });

    it('forwards include_all=true to adapter', async () => {
      metaAdapter.listTemplates.mockResolvedValue([approved]);

      await controller.listTemplates(channelId, 'true');

      expect(metaAdapter.listTemplates).toHaveBeenCalledWith(accessToken, true);
    });

    it('treats include_all=1 as true', async () => {
      metaAdapter.listTemplates.mockResolvedValue([]);

      await controller.listTemplates(channelId, '1');

      expect(metaAdapter.listTemplates).toHaveBeenCalledWith(accessToken, true);
    });

    it('throws 404 when channel not found', async () => {
      databaseService.query.mockResolvedValueOnce({ rows: [] });

      await expect(controller.listTemplates(channelId)).rejects.toBeInstanceOf(
        NotFoundException,
      );
      expect(metaAdapter.listTemplates).not.toHaveBeenCalled();
    });

    it('throws 404 when channel is not Meta', async () => {
      databaseService.query.mockResolvedValueOnce({
        rows: [{ ...channelRow, provider: 'wuzapi' }],
      });

      await expect(controller.listTemplates(channelId)).rejects.toBeInstanceOf(
        NotFoundException,
      );
    });

    it('throws 404 when access_token is missing', async () => {
      databaseService.query.mockResolvedValueOnce({
        rows: [{ ...channelRow, settings_json: {} }],
      });

      await expect(controller.listTemplates(channelId)).rejects.toBeInstanceOf(
        NotFoundException,
      );
    });
  });

  describe('POST :id/templates', () => {
    const payload: MetaTemplateCreatePayload = {
      name: 'welcome',
      language: 'pt_BR',
      category: 'MARKETING',
      components: [{ type: 'BODY', text: 'Hello' }],
    };

    it('returns 200 with adapter payload wrapped in { data }', async () => {
      metaAdapter.createTemplate.mockResolvedValue({
        id: 'tpl-1',
        status: 'PENDING',
      });

      const result = await controller.createTemplate(channelId, payload);

      expect(result).toEqual({ data: { id: 'tpl-1', status: 'PENDING' } });
      expect(metaAdapter.createTemplate).toHaveBeenCalledWith(
        `${wabaId}:${accessToken}`,
        payload,
      );
    });

    it('returns 502 when adapter throws', async () => {
      metaAdapter.createTemplate.mockRejectedValue(
        new Error('Template name already exists'),
      );

      try {
        await controller.createTemplate(channelId, payload);
        fail('expected HttpException');
      } catch (error) {
        expect(error).toBeInstanceOf(HttpException);
        expect((error as HttpException).getStatus()).toBe(
          HttpStatus.BAD_GATEWAY,
        );
        expect((error as HttpException).getResponse()).toEqual({
          error: 'Template name already exists',
        });
      }
    });

    it('throws 404 when channel has no waba_id configured', async () => {
      databaseService.query.mockResolvedValueOnce({
        rows: [{ ...channelRow, settings_json: { access_token: accessToken } }],
      });

      await expect(
        controller.createTemplate(channelId, payload),
      ).rejects.toBeInstanceOf(NotFoundException);
      expect(metaAdapter.createTemplate).not.toHaveBeenCalled();
    });
  });

  describe('DELETE :id/templates/:name', () => {
    it('returns 200 with { data: { success: true } }', async () => {
      metaAdapter.deleteTemplate.mockResolvedValue({ success: true });

      const result = await controller.deleteTemplate(channelId, 'welcome');

      expect(result).toEqual({ data: { success: true } });
      expect(metaAdapter.deleteTemplate).toHaveBeenCalledWith(
        `${wabaId}:${accessToken}`,
        'welcome',
      );
    });

    it('returns 502 when adapter throws', async () => {
      metaAdapter.deleteTemplate.mockRejectedValue(
        new Error('Template not found'),
      );

      try {
        await controller.deleteTemplate(channelId, 'missing');
        fail('expected HttpException');
      } catch (error) {
        expect(error).toBeInstanceOf(HttpException);
        expect((error as HttpException).getStatus()).toBe(
          HttpStatus.BAD_GATEWAY,
        );
      }
    });
  });

  describe('POST :id/templates/sync', () => {
    it('invalidates the templates cache and returns success', async () => {
      const result = await controller.syncTemplates(channelId);

      expect(result).toEqual({ success: true });
      expect(metaAdapter.invalidateTemplatesCache).toHaveBeenCalledWith(
        accessToken,
      );
    });

    it('throws 404 when channel does not exist', async () => {
      databaseService.query.mockResolvedValueOnce({ rows: [] });

      await expect(controller.syncTemplates(channelId)).rejects.toBeInstanceOf(
        NotFoundException,
      );
      expect(metaAdapter.invalidateTemplatesCache).not.toHaveBeenCalled();
    });
  });
});
