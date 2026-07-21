import { registerAs } from '@nestjs/config';
import type {
  AiConfiguration,
  ApiConfiguration,
  AppConfiguration,
  AsaasConfiguration,
  CorsConfiguration,
  DatabaseConfiguration,
  GoogleConfiguration,
  GatewayConfiguration,
  MiniMaxConfiguration,
  MetaConfiguration,
  InternalConfiguration,
  JwtConfiguration,
  OpenAIConfiguration,
  RedisConfiguration,
  ThrottlerConfiguration,
  UazapiConfiguration,
  ZapiConfiguration,
} from './models/configuration.model';

export type {
  AiConfiguration,
  ApiConfiguration,
  AppConfiguration,
  AsaasConfiguration,
  Configuration,
  CorsConfiguration,
  DatabaseConfiguration,
  GoogleConfiguration,
  GatewayConfiguration,
  InternalConfiguration,
  JwtConfiguration,
  MetaConfiguration,
  MiniMaxConfiguration,
  OpenAIConfiguration,
  RedisConfiguration,
  UazapiConfiguration,
  ZapiConfiguration,
} from './models/configuration.model';

/**
 * Fábricas de configuração do gateway registradas com `registerAs` do NestJS Config.
 *
 * Contexto: módulo core/config. Cada fábrica mapeia variáveis de ambiente para
 * um namespace tipado acessível via `ConfigService.get<T>(namespace)`.
 * Re-exporta todos os tipos do modelo de configuração para uso nos serviços.
 */

/**
 * Converte uma variável de ambiente em inteiro positivo com fallback seguro.
 * Retorna o fallback se o valor não for um inteiro positivo finito.
 * @param value Valor bruto da variável de ambiente
 * @param fallback Valor padrão a retornar em caso de valor inválido
 * @returns Inteiro positivo ou fallback
 */
const parsePositiveInteger = (
  value: string | undefined,
  fallback: number,
): number => {
  const parsed = Number(value);
  return Number.isFinite(parsed) && Number.isInteger(parsed) && parsed > 0
    ? parsed
    : fallback;
};

export const appConfig = registerAs(
  'app',
  (): AppConfiguration => ({
    port: parseInt(process.env.PORT ?? '3000', 10),
    nodeEnv: process.env.NODE_ENV ?? 'development',
  }),
);

export const redisConfig = registerAs(
  'redis',
  (): RedisConfiguration => ({
    url: process.env.REDIS_URL ?? 'redis://localhost:6379',
  }),
);

export const databaseConfig = registerAs(
  'database',
  (): DatabaseConfiguration => ({
    url:
      process.env.DATABASE_URL ??
      'postgres://interazap:secret@localhost:5432/interazap',
  }),
);

export const uazapiConfig = registerAs(
  'uazapi',
  (): UazapiConfiguration => ({
    baseUrl: process.env.UAZAPI_BASE_URL ?? 'https://free.uazapi.com',
    adminToken: process.env.UAZAPI_ADMIN_TOKEN ?? '',
    webhookUrl: process.env.UAZAPI_WEBHOOK_URL ?? '',
    webhookEvents: (
      process.env.UAZAPI_WEBHOOK_EVENTS ?? 'connection,messages,messages_update'
    ).split(','),
    webhookExcludeMessages: (
      process.env.UAZAPI_WEBHOOK_EXCLUDE_MESSAGES ?? 'wasSentByApi,isGroupYes'
    ).split(','),
    webhookRetries: parseInt(process.env.UAZAPI_WEBHOOK_RETRIES ?? '3', 10),
  }),
);

export const zapiConfig = registerAs(
  'zapi',
  (): ZapiConfiguration => ({
    baseUrl: process.env.ZAPI_BASE_URL ?? 'https://api.z-api.io',
    clientToken: process.env.ZAPI_CLIENT_TOKEN ?? '',
    timeout: parsePositiveInteger(process.env.ZAPI_HTTP_TIMEOUT, 30000),
  }),
);

export const openaiConfig = registerAs(
  'openai',
  (): OpenAIConfiguration => ({
    apiKey: process.env.OPENAI_API_KEY ?? '',
    apiKeyFallback: process.env.OPENAI_API_KEY_FALLBACK,
    model:
      process.env.OPENAI_DEFAULT_MODEL ?? process.env.OPENAI_MODEL ?? 'gpt-4o',
    embeddingModel:
      process.env.OPENAI_EMBEDDING_MODEL ?? 'text-embedding-3-small',
    timeout: parseInt(
      process.env.OPENAI_TIMEOUT_MS ?? process.env.OPENAI_TIMEOUT ?? '180000',
      10,
    ), // 3 minutes
    maxRetries: parseInt(process.env.OPENAI_MAX_RETRIES ?? '3', 10),
  }),
);

export const googleConfig = registerAs(
  'google',
  (): GoogleConfiguration => ({
    apiKey: process.env.GOOGLE_AI_API_KEY ?? '',
    model: process.env.GOOGLE_DEFAULT_MODEL ?? 'gemini-2.5-flash',
    timeout: parseInt(process.env.GOOGLE_TIMEOUT_MS ?? '180000', 10),
    maxRetries: parseInt(process.env.GOOGLE_MAX_RETRIES ?? '3', 10),
  }),
);

export const minimaxConfig = registerAs(
  'minimax',
  (): MiniMaxConfiguration => ({
    apiKey: process.env.MINIMAX_API_KEY ?? '',
    baseUrl: process.env.MINIMAX_BASE_URL ?? 'https://api.minimax.io',
    model: process.env.MINIMAX_DEFAULT_MODEL ?? 'MiniMax-M2.5',
    timeout: parseInt(process.env.MINIMAX_TIMEOUT_MS ?? '180000', 10),
    maxRetries: parseInt(process.env.MINIMAX_MAX_RETRIES ?? '3', 10),
  }),
);

export const asaasConfig = registerAs(
  'asaas',
  (): AsaasConfiguration => ({
    baseUrl: process.env.ASAAS_BASE_URL ?? 'https://sandbox.asaas.com/api/v3',
    apiKey: process.env.ASAAS_API_KEY ?? '',
    webhookSecret: process.env.ASAAS_WEBHOOK_SECRET ?? '',
  }),
);

export const jwtConfig = registerAs('jwt', (): JwtConfiguration => {
  if (!process.env.JWT_SECRET) {
    throw new Error('JWT_SECRET environment variable is required');
  }
  if (!process.env.WEBCHAT_JWT_SECRET) {
    throw new Error('WEBCHAT_JWT_SECRET environment variable is required');
  }
  return {
    secret: process.env.JWT_SECRET,
    webchatSecret: process.env.WEBCHAT_JWT_SECRET,
    algorithm: process.env.JWT_ALGORITHM ?? 'HS256',
  };
});

export const corsConfig = registerAs(
  'cors',
  (): CorsConfiguration => ({
    origins: (process.env.CORS_ORIGINS ?? 'http://localhost:4200').split(','),
  }),
);

export const internalConfig = registerAs(
  'internal',
  (): InternalConfiguration => ({
    apiKey: process.env.INTERNAL_API_KEY ?? '',
    apiUrl:
      process.env.INTERNAL_API_URL ??
      process.env.API_URL ??
      'http://localhost:8000',
    timeoutMs: process.env.INTERNAL_API_TIMEOUT_MS
      ? parseInt(process.env.INTERNAL_API_TIMEOUT_MS, 10)
      : undefined,
  }),
);

export const apiConfig = registerAs(
  'api',
  (): ApiConfiguration => ({
    url: process.env.API_URL ?? 'http://localhost:8000',
    authTimeoutMs: parseInt(process.env.API_AUTH_TIMEOUT_MS ?? '1500', 10),
    s2sToken: process.env.API_S2S_TOKEN ?? '',
  }),
);

export const aiConfig = registerAs(
  'ai',
  (): AiConfiguration => ({
    delegationWaitTimeoutMs: parseInt(
      process.env.AI_DELEGATION_WAIT_TIMEOUT_MS ?? '8000',
      10,
    ),
    toolRpcTimeoutMs: parseInt(
      process.env.AI_TOOL_RPC_TIMEOUT_MS ?? '2000',
      10,
    ),
  }),
);

export const throttlerConfig = registerAs(
  'throttler',
  (): ThrottlerConfiguration => ({
    httpTtl: parsePositiveInteger(process.env.THROTTLE_TTL, 60),
    httpLimit: parsePositiveInteger(process.env.THROTTLE_LIMIT, 100),
    wsTtl: parsePositiveInteger(process.env.WS_THROTTLE_TTL, 60),
    wsLimit: parsePositiveInteger(process.env.WS_THROTTLE_LIMIT, 60),
  }),
);

export const metaConfig = registerAs(
  'meta',
  (): MetaConfiguration => ({
    verifyToken: process.env.META_VERIFY_TOKEN ?? '',
    appSecret: process.env.META_APP_SECRET ?? '',
    webhookCallbackUrl: process.env.META_WEBHOOK_CALLBACK_URL ?? '',
    graphApiUrl:
      process.env.META_GRAPH_API_URL ?? 'https://graph.facebook.com/v25.0',
  }),
);

export const gatewayConfig = registerAs(
  'gateway',
  (): GatewayConfiguration => ({
    secret: process.env.GATEWAY_SECRET ?? '',
  }),
);

export const configFactories = [
  appConfig,
  redisConfig,
  databaseConfig,
  uazapiConfig,
  zapiConfig,
  openaiConfig,
  googleConfig,
  minimaxConfig,
  asaasConfig,
  jwtConfig,
  corsConfig,
  internalConfig,
  apiConfig,
  aiConfig,
  throttlerConfig,
  metaConfig,
  gatewayConfig,
];

export default (): { defaultTenantId: string } => ({
  defaultTenantId: process.env.DEFAULT_TENANT_ID ?? '',
});
