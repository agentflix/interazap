import { INestApplication, ValidationPipe } from '@nestjs/common';
import { Test } from '@nestjs/testing';
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

describe('Security Headers (e2e)', () => {
  let app: INestApplication;

  beforeAll(async () => {
    const moduleFixture = await Test.createTestingModule({
      imports: [AppModule],
    }).compile();

    app = moduleFixture.createNestApplication();

    // Apply helmet like in main.ts
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
  }, 30000);

  describe('Helmet Security Headers', () => {
    it('should include X-Content-Type-Options header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['x-content-type-options']).toBe('nosniff');
    });

    it('should include X-DNS-Prefetch-Control header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['x-dns-prefetch-control']).toBe('off');
    });

    it('should include X-Download-Options header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['x-download-options']).toBe('noopen');
    });

    it('should include X-Frame-Options header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
    });

    it('should include X-XSS-Protection header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      // Helmet v8+ sets this to 0 (disabled) as per modern best practices
      expect(response.headers['x-xss-protection']).toBe('0');
    });

    it('should include Strict-Transport-Security header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['strict-transport-security']).toContain(
        'max-age=',
      );
    });

    it('should include Content-Security-Policy header', async () => {
      const response = await request(getRequestTarget(app)).get('/health');

      expect(response.headers['content-security-policy']).toBeDefined();
    });

    it('should include all security headers in error responses', async () => {
      const response = await request(getRequestTarget(app)).get(
        '/nonexistent-endpoint',
      );

      expect(response.headers['x-content-type-options']).toBe('nosniff');
      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
      expect(response.headers['x-dns-prefetch-control']).toBe('off');
    });
  });

  describe('Security Headers for API endpoints', () => {
    it('should include security headers on chat endpoints', async () => {
      const response = await request(getRequestTarget(app))
        .post('/chat/webhook/test-instance')
        .send({ event: 'test' });

      expect(response.headers['x-content-type-options']).toBe('nosniff');
      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
    });

    it('should include security headers on uazapi endpoints', async () => {
      const response = await request(getRequestTarget(app))
        .post('/uazapi/instances/token-abc/connect')
        .send({});

      expect(response.headers['x-content-type-options']).toBe('nosniff');
      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
    });
  });
});
