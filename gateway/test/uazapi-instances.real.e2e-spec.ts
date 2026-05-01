import { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { RedisService } from '../src/infrastructure/redis/redis.service';

const getRequestTarget = (
  application: INestApplication,
): Parameters<typeof request>[0] => {
  const server = application.getHttpServer() as unknown;
  if (typeof server === 'undefined' || server === null) {
    throw new Error('HTTP server not initialized');
  }
  return server as Parameters<typeof request>[0];
};

/**
 * Testes reais (integração) com a API Uazapi.
 * Requer variáveis de ambiente:
 * - UAZAPI_BASE_URL
 * - UAZAPI_ADMIN_TOKEN
 * - INTERNAL_API_KEY
 */
describe('Uazapi Instances (real e2e)', () => {
  let app: INestApplication;
  let createdToken: string | undefined;
  const adminConfigured =
    !!process.env.UAZAPI_BASE_URL && !!process.env.UAZAPI_ADMIN_TOKEN;
  const apiKey = (): string => process.env.INTERNAL_API_KEY ?? '';

  beforeAll(async () => {
    if (!adminConfigured) {
      // Skip if no env configured
      return;
    }
    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    })
      .overrideProvider(RedisService)
      .useValue({
        publish: jest.fn(),
        publishStream: jest.fn(),
        ensureIdempotent: jest.fn().mockResolvedValue(true),
        getClient: () => ({ quit: jest.fn() }),
        getPubSubClient: () => ({
          on: jest.fn(),
          subscribe: jest.fn().mockResolvedValue(1),
          unsubscribe: jest.fn().mockResolvedValue(1),
          quit: jest.fn(),
        }),
        onModuleDestroy: jest.fn(),
      })
      .compile();
    app = moduleFixture.createNestApplication();
    await app.init();
  });

  afterAll(async () => {
    if (app) {
      await app.close();
    }
  });

  it('should create instance (real)', async () => {
    if (!adminConfigured || !app) {
      return;
    }

    const name = `test-${Date.now()}`;
    const res = await request(getRequestTarget(app))
      .post('/uazapi/instances')
      .set('x-api-key', apiKey())
      .send({ name })
      .expect(201);

    const body = res.body as unknown;
    createdToken = extractToken(body);
    expect(createdToken).toBeTruthy();
  });

  it('should check status of created instance (real)', async () => {
    if (!adminConfigured || !createdToken || !app) {
      return;
    }

    const res = await request(getRequestTarget(app))
      .get(`/uazapi/instances/${createdToken}/status`)
      .set('x-api-key', apiKey())
      .expect(200);

    expect(res.body).toHaveProperty('instance');
  });

  it('should delete instance (real)', async () => {
    if (!adminConfigured || !createdToken || !app) {
      return;
    }

    await request(getRequestTarget(app))
      .post(`/uazapi/instances/${createdToken}/delete`)
      .set('x-api-key', apiKey())
      .expect(201);
  });
});

const extractToken = (body: unknown): string | undefined => {
  if (
    typeof body === 'object' &&
    body !== null &&
    typeof (body as Record<string, unknown>).token === 'string'
  ) {
    return (body as Record<string, string>).token;
  }

  if (
    typeof body === 'object' &&
    body !== null &&
    typeof (body as Record<string, unknown>).instance === 'object' &&
    (body as { instance?: Record<string, unknown> }).instance?.token &&
    typeof (body as { instance?: Record<string, unknown> }).instance?.token ===
      'string'
  ) {
    return (body as { instance?: { token?: string } }).instance?.token;
  }

  return undefined;
};
