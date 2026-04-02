import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import helmet from 'helmet';
import { json, urlencoded } from 'express';

/**
 * Bootstraps the NestJS Gateway application.
 *
 * Initializes the application instance, configures body parsers with configurable
 * size limits, enables CORS based on allowed origins, adds Helmet security headers,
 * registers a global ValidationPipe with whitelist and transform enabled, and starts
 * the server listening on the configured port.
 */
async function bootstrap() {
  const app = await NestFactory.create(AppModule);
  const configService = app.get(ConfigService);

  // Body parser limits — base64-encoded files can be large (PDFs, images, etc.)
  const bodyLimit = configService.get<string>('GATEWAY_BODY_LIMIT') ?? '50mb';
  app.use(json({ limit: bodyLimit }));
  app.use(urlencoded({ limit: bodyLimit, extended: true }));

  // CORS configuration
  const allowedOrigins = configService.get<string[]>('cors.origins') ?? [
    'http://localhost:4200',
    'http://localhost:3000',
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
    maxAge: 86400, // 24 hours
  });

  // SECURITY: Add helmet for security headers
  app.use(helmet());

  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
    }),
  );

  const port = configService.get<number>('PORT') ?? 3000;
  await app.listen(port);
  console.log(`Gateway listening on http://localhost:${port}`);
}
bootstrap().catch((err) => {
  console.error('Gateway failed to start:', err);
  process.exit(1);
});
