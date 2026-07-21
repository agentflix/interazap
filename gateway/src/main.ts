import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import { LogLevel, Logger, ValidationPipe } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import helmet from 'helmet';
import { json, urlencoded } from 'express';
import { StructuredLoggerService } from './common/logger';

const LOG_LEVEL_PRIORITY: Record<string, number> = {
  error: 0,
  warn: 1,
  log: 2,
  info: 2,
  debug: 3,
  verbose: 4,
};

const ORDERED_LOG_LEVELS: LogLevel[] = [
  'error',
  'warn',
  'log',
  'debug',
  'verbose',
];

function parseBooleanEnv(
  value: string | undefined,
  fallback: boolean,
): boolean {
  if (value === undefined) {
    return fallback;
  }

  const normalized = value.trim().toLowerCase();
  if (['1', 'true', 'yes', 'on'].includes(normalized)) {
    return true;
  }

  if (['0', 'false', 'no', 'off'].includes(normalized)) {
    return false;
  }

  return fallback;
}

function resolveLogLevels(level: string | undefined): LogLevel[] {
  const normalized = (level ?? 'log').trim().toLowerCase();
  const maxPriority = LOG_LEVEL_PRIORITY[normalized];

  if (maxPriority === undefined) {
    return ['error', 'warn', 'log'];
  }

  return ORDERED_LOG_LEVELS.filter(
    (candidate) => LOG_LEVEL_PRIORITY[candidate] <= maxPriority,
  );
}

/**
 * Bootstraps the NestJS Gateway application.
 *
 * Initializes the application instance, configures body parsers with configurable
 * size limits, enables CORS based on allowed origins, adds Helmet security headers,
 * registers a global ValidationPipe with whitelist and transform enabled, and starts
 * the server listening on the configured port.
 */
async function bootstrap() {
  const app = await NestFactory.create(AppModule, {
    bufferLogs: true,
    // Expõe req.rawBody (Buffer) — necessário para validar o HMAC do webhook
    // Meta sobre o corpo exatamente como recebido, sem risco de o
    // body-parser reordenar chaves do JSON antes da assinatura ser calculada.
    rawBody: true,
  });

  const configService = app.get(ConfigService);
  const nodeEnv = configService.get<string>('app.nodeEnv') ?? 'development';
  const useStructuredLogger = parseBooleanEnv(
    configService.get<string>('LOG_STRUCTURED'),
    nodeEnv === 'production',
  );

  const structuredLogger = useStructuredLogger
    ? app.get(StructuredLoggerService)
    : undefined;

  if (structuredLogger !== undefined) {
    structuredLogger.setContext('Gateway');
    app.useLogger(structuredLogger);
  } else {
    const logLevels = resolveLogLevels(configService.get<string>('LOG_LEVEL'));
    app.useLogger(logLevels);
  }

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
      'X-Session-ID',
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
      forbidNonWhitelisted: true,
      transform: true,
      transformOptions: { enableImplicitConversion: true },
    }),
  );

  const port = configService.get<number>('PORT') ?? 3000;
  await app.listen(port);

  if (structuredLogger !== undefined) {
    structuredLogger.log(`Gateway listening on http://localhost:${port}`);
  } else {
    Logger.log(`Gateway listening on http://localhost:${port}`, 'Gateway');
  }
}
bootstrap().catch((err) => {
  console.error('Gateway failed to start:', err);
  process.exit(1);
});
