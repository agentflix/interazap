import { INestApplication, ValidationPipe } from '@nestjs/common';
import { Test } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import request from 'supertest';
import helmet from 'helmet';
import { AppModule } from '../src/app.module';

const getRequestTarget = (
  application: INestApplication,
): Parameters<typeof request>[0] => {
  const server = application.getHttpServer() as unknown;
  if (typeof server === 'undefined' || server === null) {
    throw new Error('HTTP server not initialized');
  }
  return server as Parameters<typeof request>[0];
};

describe('CORS (e2e)', () => {
  let app: INestApplication;

  beforeAll(async () => {
    process.env.CORS_ORIGINS = 'http://localhost:4200';

    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();

    const configService = app.get(ConfigService);
    const allowedOrigins = configService.get<string[]>('cors.origins') ?? [
      'http://localhost:4200',
    ];

    app.enableCors({
      origin: allowedOrigins,
      methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
      allowedHeaders: [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Trace-ID',
        'X-Idempotency-Key',
      ],
      credentials: true,
      maxAge: 86400,
    });

    app.use(helmet());

    app.useGlobalPipes(
      new ValidationPipe({
        whitelist: true,
        transform: true,
      }),
    );

    await app.init();
  }, 30000);

  afterAll(async () => {
    await app.close();
  }, 10000);

  it('should allow requests from whitelisted origins', async () => {
    const response = await request(getRequestTarget(app))
      .options('/health')
      .set('Origin', 'http://localhost:4200')
      .set('Access-Control-Request-Method', 'GET')
      .expect(204);

    expect(response.headers['access-control-allow-origin']).toBe(
      'http://localhost:4200',
    );
    expect(response.headers['access-control-allow-credentials']).toBe('true');
  });

  it('should block requests from non-whitelisted origins', async () => {
    const response = await request(getRequestTarget(app))
      .options('/health')
      .set('Origin', 'http://malicious-site.com')
      .set('Access-Control-Request-Method', 'GET')
      .expect(204);

    expect(response.headers['access-control-allow-origin']).toBeUndefined();
  });
});
