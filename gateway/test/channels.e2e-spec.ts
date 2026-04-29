import { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { DatabaseService } from '../src/infrastructure/database/database.service';
import { MetaAdapter } from '../src/domains/chat/providers/meta/meta.adapter';
import { InternalApiKeyGuard } from '../src/domains/realtime/guards/internal-api-key.guard';

const getRequestTarget = (
  application: INestApplication,
): Parameters<typeof request>[0] => {
  const server = application.getHttpServer() as unknown;
  if (typeof server === 'undefined' || server === null) {
    throw new Error('HTTP server not initialized');
  }
  return server as Parameters<typeof request>[0];
};

describe('ChannelsController (e2e)', () => {
  let app: INestApplication;
  const apiKey = 'test-api-key';

  const channelId = 'channel-123';
  const accessToken = 'access-token-xyz';
  const wabaId = 'waba-456';
  const channelRow = {
    id: channelId,
    provider: 'meta',
    settings_json: { access_token: accessToken, waba_id: wabaId },
  };

  const metaAdapter = {
    listTemplates: jest.fn(),
    invalidateTemplatesCache: jest.fn().mockResolvedValue(undefined),
    createTemplate: jest.fn(),
    deleteTemplate: jest.fn(),
  };

  const databaseService = {
    query: jest.fn().mockResolvedValue({ rows: [channelRow] }),
    onModuleDestroy: jest.fn(),
  };

  beforeAll(async () => {
    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideProvider(DatabaseService)
      .useValue(databaseService)
      .overrideProvider(MetaAdapter)
      .useValue(metaAdapter)
      .overrideGuard(InternalApiKeyGuard)
      .useValue({ canActivate: () => true })
      .compile();

    app = moduleFixture.createNestApplication();
    await app.init();
  });

  afterAll(async () => {
    await app.close();
  });

  beforeEach(() => {
    jest.clearAllMocks();
    databaseService.query.mockResolvedValue({ rows: [channelRow] });
  });

  describe('GET /channels/:id/templates', () => {
    it('lists approved templates by default', async () => {
      metaAdapter.listTemplates.mockResolvedValue([
        {
          name: 'welcome',
          status: 'APPROVED',
          category: 'MARKETING',
          language: 'pt_BR',
          components: [],
        },
      ]);

      const res = await request(getRequestTarget(app))
        .get(`/channels/${channelId}/templates`)
        .set('x-api-key', apiKey)
        .expect(200);

      expect(res.body).toEqual([
        {
          name: 'welcome',
          status: 'APPROVED',
          category: 'MARKETING',
          language: 'pt_BR',
          components: [],
        },
      ]);
      expect(metaAdapter.listTemplates).toHaveBeenCalledWith(
        accessToken,
        false,
      );
    });

    it('lists all templates when include_all=true', async () => {
      metaAdapter.listTemplates.mockResolvedValue([]);

      await request(getRequestTarget(app))
        .get(`/channels/${channelId}/templates?include_all=true`)
        .set('x-api-key', apiKey)
        .expect(200);

      expect(metaAdapter.listTemplates).toHaveBeenCalledWith(accessToken, true);
    });

    it('returns 404 when channel not found', async () => {
      databaseService.query.mockResolvedValueOnce({ rows: [] });

      await request(getRequestTarget(app))
        .get(`/channels/${channelId}/templates`)
        .set('x-api-key', apiKey)
        .expect(404);
    });
  });

  describe('POST /channels/:id/templates', () => {
    it('creates a template and returns id and status', async () => {
      metaAdapter.createTemplate.mockResolvedValue({
        id: 'tpl-1',
        status: 'PENDING',
      });

      const res = await request(getRequestTarget(app))
        .post(`/channels/${channelId}/templates`)
        .set('x-api-key', apiKey)
        .send({
          name: 'welcome',
          language: 'pt_BR',
          category: 'MARKETING',
          components: [{ type: 'BODY', text: 'Hello' }],
        })
        .expect(201);

      expect(res.body).toEqual({ data: { id: 'tpl-1', status: 'PENDING' } });
      expect(metaAdapter.createTemplate).toHaveBeenCalledWith(
        `${wabaId}:${accessToken}`,
        {
          name: 'welcome',
          language: 'pt_BR',
          category: 'MARKETING',
          components: [{ type: 'BODY', text: 'Hello' }],
        },
      );
    });

    it('returns 502 when Meta adapter throws', async () => {
      metaAdapter.createTemplate.mockRejectedValue(
        new Error('Template name already exists'),
      );

      const res = await request(getRequestTarget(app))
        .post(`/channels/${channelId}/templates`)
        .set('x-api-key', apiKey)
        .send({
          name: 'welcome',
          language: 'pt_BR',
          category: 'MARKETING',
          components: [{ type: 'BODY', text: 'Hello' }],
        })
        .expect(502);

      expect(res.body).toEqual({ error: 'Template name already exists' });
    });
  });

  describe('DELETE /channels/:id/templates/:name', () => {
    it('deletes a template and returns success', async () => {
      metaAdapter.deleteTemplate.mockResolvedValue({ success: true });

      const res = await request(getRequestTarget(app))
        .delete(`/channels/${channelId}/templates/welcome`)
        .set('x-api-key', apiKey)
        .expect(200);

      expect(res.body).toEqual({ data: { success: true } });
      expect(metaAdapter.deleteTemplate).toHaveBeenCalledWith(
        `${wabaId}:${accessToken}`,
        'welcome',
      );
    });

    it('returns 502 when Meta adapter throws', async () => {
      metaAdapter.deleteTemplate.mockRejectedValue(
        new Error('Template not found'),
      );

      const res = await request(getRequestTarget(app))
        .delete(`/channels/${channelId}/templates/missing`)
        .set('x-api-key', apiKey)
        .expect(502);

      expect(res.body).toEqual({ error: 'Template not found' });
    });
  });

  describe('POST /channels/:id/templates/sync', () => {
    it('invalidates cache and returns success', async () => {
      const res = await request(getRequestTarget(app))
        .post(`/channels/${channelId}/templates/sync`)
        .set('x-api-key', apiKey)
        .expect(201);

      expect(res.body).toEqual({ success: true });
      expect(metaAdapter.invalidateTemplatesCache).toHaveBeenCalledWith(
        accessToken,
      );
    });

    it('returns 404 when channel does not exist', async () => {
      databaseService.query.mockResolvedValueOnce({ rows: [] });

      await request(getRequestTarget(app))
        .post(`/channels/${channelId}/templates/sync`)
        .set('x-api-key', apiKey)
        .expect(404);
    });
  });
});
