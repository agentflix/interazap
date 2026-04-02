/**
 * Configuração do Sentry para o gateway NestJS.
 *
 * @see https://docs.sentry.io/platforms/javascript/guides/nestjs/
 */

import { ConfigService } from '@nestjs/config';
import { SentryConfig } from '../models/sentry.model';

export type { SentryConfig };

/**
 * Retorna a configuração do Sentry a partir das variáveis de ambiente.
 *
 * @param configService - Serviço de configuração do NestJS
 * @returns Objeto de configuração do Sentry
 */
export function getSentryConfig(configService: ConfigService): SentryConfig {
  return {
    dsn: configService.get<string>('SENTRY_DSN'),
    environment: configService.get<string>('NODE_ENV', 'development'),
    release: configService.get<string>('SENTRY_RELEASE', '1.0.0'),
    tracesSampleRate: parseFloat(
      configService.get<string>('SENTRY_TRACES_SAMPLE_RATE', '0.0'),
    ),
    profilesSampleRate: parseFloat(
      configService.get<string>('SENTRY_PROFILES_SAMPLE_RATE', '0.0'),
    ),
    debug: configService.get<string>('NODE_ENV') === 'development',
  };
}

/**
 * Exemplo de inicialização do Sentry no main.ts.
 * Para habilitar o Sentry, instale `@sentry/nestjs` e configure o DSN no ambiente.
 */
export const SENTRY_CONFIG_EXAMPLE = `
// To enable Sentry, install @sentry/nestjs:
// pnpm add @sentry/nestjs

// Then in main.ts:
// import * as Sentry from '@sentry/nestjs';
// Sentry.init({ dsn: process.env.SENTRY_DSN, ... });
`;
