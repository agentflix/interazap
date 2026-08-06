import { INestApplication, ValidationPipe } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import request from 'supertest';
import * as crypto from 'crypto';
import { AppModule } from '../src/app.module';
import { RedisService } from '../src/infrastructure/redis/redis.service';
import { MetaLookupService } from '../src/domains/chat/http/meta-lookup.service';
import { BullMQQueueFactory } from '../src/shared/services/queue/bullmq-queue-factory.service';
import type { MetaWebhookQueueJob } from '../src/domains/chat/services/meta-webhook-queue.service';

/**
 * Teste de nivel HTTP para o webhook da Meta.
 *
 * Existe para cobrir exatamente a lacuna que deixou passar um defeito
 * bloqueante: os testes unitarios de `MetaAdapter`/`MetaProvider` chamam
 * `normalizeWebhookBatch`/`normalizeAll` diretamente, nunca exercitando o
 * `ValidationPipe` global (`whitelist: true, forbidNonWhitelisted: true`)
 * registrado em `main.ts`. Um `@Body()` tipado como classe DTO parcial
 * fazia esse pipe rejeitar (400) ou esvaziar silenciosamente (whitelist)
 * qualquer payload real da Meta, que sempre traz `value.messages`/`statuses`
 * não declarados no DTO. Este teste sobe a `AppModule` real com o MESMO
 * ValidationPipe do bootstrap e com `rawBody: true`, faz POST de um payload
 * real da Meta (com `value.messages` preenchido) e prova: responde 200
 * APÓS o enqueue durável (fila BullMQ), e o job processa o lote até o
 * stream Redis publicado com `provider=meta`.
 */
describe('Meta Webhook (e2e)', () => {
  let app: INestApplication;
  const appSecret = 'test-meta-app-secret';
  const verifyToken = 'test-meta-verify-token';
  const originalAppSecret = process.env.META_APP_SECRET;
  const originalVerifyToken = process.env.META_VERIFY_TOKEN;

  const publishStream = jest.fn().mockResolvedValue('1-0');
  const ensureIdempotent = jest.fn().mockResolvedValue(true);

  const resolvePhoneNumberId = jest.fn();
  const resolveWabaId = jest.fn();

  // Fila BullMQ fake: captura jobs enfileirados e o processor do worker
  // para que o teste prove ACK-após-enqueue e o processamento do lote.
  const enqueuedJobs: MetaWebhookQueueJob[] = [];
  let capturedProcessor:
    | ((job: { data: MetaWebhookQueueJob }) => Promise<void>)
    | null = null;

  const queueFactoryMock = {
    createQueue: jest.fn().mockReturnValue({
      add: jest.fn().mockImplementation(
        async (_name: string, data: MetaWebhookQueueJob) => {
          enqueuedJobs.push(data);
          return 'job-id';
        },
      ),
    }),
    createWorker: jest
      .fn()
      .mockImplementation(
        (_name: string, processor: (job: { data: MetaWebhookQueueJob }) => Promise<void>) => {
          capturedProcessor = processor;
          return { close: jest.fn() };
        },
      ),
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

  const sign = (rawBody: string): string =>
    `sha256=${crypto.createHmac('sha256', appSecret).update(rawBody).digest('hex')}`;

  beforeAll(async () => {
    process.env.META_APP_SECRET = appSecret;
    process.env.META_VERIFY_TOKEN = verifyToken;

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
          get: jest.fn().mockResolvedValue(null),
          setex: jest.fn().mockResolvedValue('OK'),
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
      .overrideProvider(MetaLookupService)
      .useValue({
        resolvePhoneNumberId,
        resolveWabaId,
      })
      .overrideProvider(BullMQQueueFactory)
      .useValue(queueFactoryMock)
      .compile();

    // Reproduz exatamente o bootstrap de main.ts relevante para este bug:
    // rawBody habilitado (HMAC sobre bytes reais) + o mesmo ValidationPipe
    // global (whitelist + forbidNonWhitelisted), que foi a causa raiz.
    app = moduleFixture.createNestApplication({ rawBody: true });
    app.useGlobalPipes(
      new ValidationPipe({
        whitelist: true,
        forbidNonWhitelisted: true,
        transform: true,
        transformOptions: { enableImplicitConversion: true },
      }),
    );
    await app.init();
  });

  afterAll(async () => {
    process.env.META_APP_SECRET = originalAppSecret;
    process.env.META_VERIFY_TOKEN = originalVerifyToken;
    await app.close();
  });

  beforeEach(() => {
    publishStream.mockClear();
    ensureIdempotent.mockClear();
    resolvePhoneNumberId.mockReset();
    resolveWabaId.mockReset();
    enqueuedJobs.length = 0;
    capturedProcessor = null;
  });

  it('accepts a real Meta payload with 200 only AFTER durable enqueue and processes the job to the stream with provider=meta', async () => {
    resolvePhoneNumberId.mockResolvedValue({
      tenantId: 'tenant-http-e2e',
      instanceId: 'inst-http-e2e',
      webhookToken: 'whk-http-e2e',
    });

    const payload = {
      object: 'whatsapp_business_account',
      entry: [
        {
          id: 'WABA_HTTP_1',
          time: 1700000000,
          changes: [
            {
              field: 'messages',
              value: {
                messaging_product: 'whatsapp',
                metadata: {
                  display_phone_number: '5511999999999',
                  phone_number_id: 'PHN_HTTP_ID',
                },
                contacts: [
                  {
                    wa_id: '5511888888888',
                    profile: { name: 'Cliente HTTP' },
                  },
                ],
                messages: [
                  {
                    from: '5511888888888',
                    id: 'wamid.HTTP_TEST_1',
                    timestamp: '1700000000',
                    type: 'text',
                    text: { body: 'Mensagem real da Meta via HTTP' },
                  },
                ],
              },
            },
          ],
        },
      ],
    };

    const rawBody = JSON.stringify(payload);
    const signature = sign(rawBody);

    const res = await request(getRequestTarget(app))
      .post('/webhooks/meta')
      .set('x-hub-signature-256', signature)
      .set('Content-Type', 'application/json')
      .send(rawBody)
      .expect(200);

    expect(res.body).toEqual({ success: true });

    // ACK ocorre após o enqueue durável — e o processamento NÃO é inline
    expect(enqueuedJobs).toHaveLength(1);
    expect(enqueuedJobs[0].payload).toEqual(payload);
    expect(publishStream).not.toHaveBeenCalled();
    expect(resolvePhoneNumberId).not.toHaveBeenCalled();

    // O job enfileirado é processado pelo worker (processor real)
    expect(capturedProcessor).not.toBeNull();
    await capturedProcessor!({ data: enqueuedJobs[0] });

    // Prova que o payload NÃO foi filtrado/rejeitado pelo ValidationPipe e
    // efetivamente chegou ao processamento (stream Redis publicado).
    expect(resolvePhoneNumberId).toHaveBeenCalledWith('PHN_HTTP_ID');
    expect(publishStream).toHaveBeenCalledTimes(1);
    const [, streamRecord] = publishStream.mock.calls[0] as [
      string,
      Record<string, unknown>,
    ];
    expect(streamRecord).toMatchObject({
      provider: 'meta',
      tenant_id: 'tenant-http-e2e',
      instance_id: 'inst-http-e2e',
    });
  });

  it('acks 200 after enqueue even when phone_number_id is unknown (processor discards without 4xx retry loop)', async () => {
    resolvePhoneNumberId.mockResolvedValue(null);

    const payload = {
      object: 'whatsapp_business_account',
      entry: [
        {
          id: 'WABA_HTTP_2',
          time: 1700000001,
          changes: [
            {
              field: 'messages',
              value: {
                messaging_product: 'whatsapp',
                metadata: {
                  display_phone_number: '5511999999999',
                  phone_number_id: 'UNKNOWN_PHN',
                },
                messages: [
                  {
                    from: '5511888888889',
                    id: 'wamid.HTTP_TEST_2',
                    timestamp: '1700000001',
                    type: 'text',
                    text: { body: 'ignorado' },
                  },
                ],
              },
            },
          ],
        },
      ],
    };

    const rawBody = JSON.stringify(payload);
    const signature = sign(rawBody);

    const res = await request(getRequestTarget(app))
      .post('/webhooks/meta')
      .set('x-hub-signature-256', signature)
      .set('Content-Type', 'application/json')
      .send(rawBody)
      .expect(200);

    expect(res.body).toEqual({ success: true });
    expect(enqueuedJobs).toHaveLength(1);
    expect(publishStream).not.toHaveBeenCalled();
  });

  it('rejects with 403 when the HMAC signature does not match (never a 4xx that would leak unsigned payloads)', async () => {
    const payload = { object: 'whatsapp_business_account', entry: [] };

    await request(getRequestTarget(app))
      .post('/webhooks/meta')
      .set('x-hub-signature-256', 'sha256=deadbeef')
      .set('Content-Type', 'application/json')
      .send(JSON.stringify(payload))
      .expect(403);

    expect(enqueuedJobs).toHaveLength(0);
    expect(publishStream).not.toHaveBeenCalled();
  });

  it('still answers the GET verification handshake (hub.challenge) as a plain string with the global pipe active', async () => {
    const res = await request(getRequestTarget(app))
      .get('/webhooks/meta')
      .query({
        'hub.mode': 'subscribe',
        'hub.verify_token': verifyToken,
        'hub.challenge': 'challenge-123',
      })
      .expect(200);

    expect(res.text).toBe('challenge-123');
  });

  it('rejects the GET handshake with 403 when the verify_token does not match', async () => {
    await request(getRequestTarget(app))
      .get('/webhooks/meta')
      .query({
        'hub.mode': 'subscribe',
        'hub.verify_token': 'wrong-token',
        'hub.challenge': 'challenge-123',
      })
      .expect(403);
  });
});
