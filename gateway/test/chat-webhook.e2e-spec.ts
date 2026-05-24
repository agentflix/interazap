import { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { RedisService } from '../src/infrastructure/redis/redis.service';
import { InstanceResolverService } from '../src/domains/chat/services/instance-resolver.service';
import { DatabaseService } from '../src/infrastructure/database/database.service';
import * as fs from 'fs';
import * as path from 'path';
import { WebhookEventDto } from '../src/domains/chat/dto/webhook-event.dto';

type WebhookFixture = {
  webhooks: WebhookEventDto[];
};

const readFixture = (filename: string): WebhookFixture => {
  const content = fs.readFileSync(filename, 'utf8');
  const parsed = JSON.parse(content) as unknown;
  if (!isWebhookFixture(parsed)) {
    throw new Error('Invalid webhook fixture');
  }
  return parsed;
};

const isWebhookFixture = (value: unknown): value is WebhookFixture => {
  return (
    typeof value === 'object' &&
    value !== null &&
    Array.isArray((value as Record<string, unknown>).webhooks)
  );
};

const getRequestTarget = (
  application: INestApplication,
): Parameters<typeof request>[0] => {
  const server = application.getHttpServer() as unknown;
  if (typeof server === 'undefined' || server === null) {
    throw new Error('HTTP server not initialized');
  }
  return server as Parameters<typeof request>[0];
};

describe('Chat Webhook (e2e)', () => {
  let app: INestApplication;
  const publishStream = jest.fn().mockResolvedValue('1-0');
  const ackSeenKeys = new Set<string>();
  const ensureIdempotent = jest
    .fn()
    .mockImplementation((key: string): boolean => {
      if (typeof key === 'string' && key.startsWith('idempo:ack:')) {
        if (ackSeenKeys.has(key)) {
          return false;
        }
        ackSeenKeys.add(key);
      }
      return true;
    });

  beforeAll(async () => {
    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideProvider(RedisService)
      .useValue({
        publish: jest.fn().mockResolvedValue(1),
        publishStream,
        ensureIdempotent,
        getClient: () => ({
          quit: jest.fn(),
          del: jest.fn().mockResolvedValue(1),
        }),
        getPubSubClient: () => ({
          on: jest.fn(),
          off: jest.fn(),
          subscribe: jest.fn().mockResolvedValue(1),
          unsubscribe: jest.fn().mockResolvedValue(1),
          quit: jest.fn(),
        }),
        onModuleDestroy: jest.fn(),
      })
      .overrideProvider(InstanceResolverService)
      .useValue({
        resolveByWebhookToken: jest.fn().mockResolvedValue({
          instance_id: 'inst-1',
          tenant_id: 'tenant-default',
          provider: 'uazapi',
        }),
      })
      .overrideProvider(DatabaseService)
      .useValue({
        query: jest.fn().mockResolvedValue({ rows: [] }),
        onModuleDestroy: jest.fn(),
      })
      .compile();

    app = moduleFixture.createNestApplication();
    await app.init();
  });

  afterAll(async () => {
    await app.close();
  });

  it('should ack webhook', async () => {
    ackSeenKeys.clear();
    publishStream.mockClear();

    const fixture = readFixture(
      path.join(__dirname, 'fixtures/uazapi-webhooks.json'),
    );
    const payload = fixture.webhooks[0];

    const res = await request(getRequestTarget(app))
      .post('/webhooks/zapi/instances/token-123')
      .send(payload)
      .expect(201);

    expect(res.body).toEqual({ success: true });
    expect(publishStream).toHaveBeenCalled();
  });

  it('should treat repeated webhook as duplicate on controller path', async () => {
    ackSeenKeys.clear();
    publishStream.mockClear();

    const fixture = readFixture(
      path.join(__dirname, 'fixtures/uazapi-webhooks.json'),
    );
    const payload = fixture.webhooks[0];

    await request(getRequestTarget(app))
      .post('/webhooks/zapi/instances/token-123')
      .send(payload)
      .expect(201);

    await request(getRequestTarget(app))
      .post('/webhooks/zapi/instances/token-123')
      .send(payload)
      .expect(201);

    expect(publishStream).toHaveBeenCalledTimes(1);
  });
});
