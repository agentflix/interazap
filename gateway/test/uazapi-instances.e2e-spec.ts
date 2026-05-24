import { INestApplication } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import { AppModule } from '../src/app.module';
import { UazapiClient } from '../src/domains/chat/providers/uazapi/uazapi.client';
import { RedisService } from '../src/infrastructure/redis/redis.service';
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

describe('Uazapi Instances (e2e)', () => {
  let app: INestApplication;
  const clientMock = {
    initInstance: jest
      .fn()
      .mockResolvedValue({ response: 'Instance created successfully' }),
    listInstances: jest.fn().mockResolvedValue([]),
    connectInstance: jest.fn().mockResolvedValue({ connected: false }),
    disconnectInstance: jest.fn().mockResolvedValue({ success: true }),
  };

  beforeAll(async () => {
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
          off: jest.fn(),
          subscribe: jest.fn().mockResolvedValue(1),
          unsubscribe: jest.fn().mockResolvedValue(1),
          quit: jest.fn(),
        }),
        onModuleDestroy: jest.fn(),
      })
      .overrideProvider(UazapiClient)
      .useValue(clientMock)
      .overrideGuard(InternalApiKeyGuard)
      .useValue({ canActivate: () => true })
      .compile();

    app = moduleFixture.createNestApplication();
    await app.init();
  });

  afterAll(async () => {
    await app.close();
  });

  it('should create instance', async () => {
    const res = await request(getRequestTarget(app))
      .post('/uazapi/instances')
      .send({ name: 'minha-instancia' })
      .expect(201);

    expect(res.body).toHaveProperty('response');
    expect(clientMock.initInstance).toHaveBeenCalledWith({
      name: 'minha-instancia',
    });
  });

  it('should connect instance', async () => {
    const res = await request(getRequestTarget(app))
      .post('/uazapi/instances/token-abc/connect')
      .send({ phone: '5511999999999' })
      .expect(201);

    expect(res.body).toHaveProperty('connected');
    expect(clientMock.connectInstance).toHaveBeenCalled();
  });

  it('should disconnect instance', async () => {
    await request(getRequestTarget(app))
      .post('/uazapi/instances/token-abc/disconnect')
      .expect(201);

    expect(clientMock.disconnectInstance).toHaveBeenCalled();
  });
});
