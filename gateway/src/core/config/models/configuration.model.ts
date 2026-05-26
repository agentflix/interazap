/**
 * Modelos tipados da configuração do Gateway.
 *
 * Contexto: módulo core/config. Define todas as interfaces de configuração
 * por namespace (app, redis, database, providers, jwt, etc.) e a interface
 * consolidada `Configuration` que agrega todos os namespaces.
 */

/** Configurações gerais da aplicação. */
export interface AppConfiguration {
  port: number;
  nodeEnv: string;
}

/** Configurações de conexão Redis. */
export interface RedisConfiguration {
  url: string;
}

/** Configurações de conexão com banco de dados. */
export interface DatabaseConfiguration {
  url: string;
}

/** Configuração do provider Uazapi. */
export interface UazapiConfiguration {
  baseUrl: string;
  adminToken: string;
  webhookUrl: string;
  webhookEvents: string[];
  webhookExcludeMessages: string[];
  webhookRetries: number;
}

/** Configuração do provider Z-API. */
export interface ZapiConfiguration {
  baseUrl: string;
  clientToken: string;
  timeout?: number;
}

/** Configurações do provedor Google AI (Gemini). */
export interface GoogleConfiguration {
  apiKey: string;
  model: string;
  timeout: number;
  maxRetries: number;
}

/** Configurações do provedor OpenAI. */
export interface OpenAIConfiguration {
  apiKey: string;
  apiKeyFallback?: string;
  model: string;
  embeddingModel: string;
  timeout: number;
  maxRetries: number;
}

/** Configuração do provider Asaas. */
export interface AsaasConfiguration {
  baseUrl: string;
  apiKey: string;
  webhookSecret: string;
}

/** Configurações JWT. */
export interface JwtConfiguration {
  secret: string;
  webchatSecret: string;
  algorithm: string;
}

/** Configuração de CORS. */
export interface CorsConfiguration {
  origins: string[];
}

/** Configurações de comunicação interna entre serviços. */
export interface InternalConfiguration {
  apiKey: string;
  apiUrl: string;
  timeoutMs?: number;
}

/** Configuração da API principal do ecossistema. */
export interface ApiConfiguration {
  url: string;
  authTimeoutMs: number;
  s2sToken: string;
}

/** Configurações de runtime do domínio de IA. */
export interface AiConfiguration {
  delegationWaitTimeoutMs: number;
  toolRpcTimeoutMs: number;
}

/** Configurações de throttling. */
export interface ThrottlerConfiguration {
  httpTtl: number;
  httpLimit: number;
  wsTtl: number;
  wsLimit: number;
}

/** Configurações do provedor MiniMax. */
export interface MiniMaxConfiguration {
  apiKey: string;
  baseUrl: string;
  model: string;
  timeout: number;
  maxRetries: number;
}

/** Configuração do provider Meta WhatsApp Business API. */
export interface MetaConfiguration {
  verifyToken: string;
  appSecret: string;
  webhookCallbackUrl: string;
  graphApiUrl: string;
}

/** Configurações do Gateway para comunicação interna. */
export interface GatewayConfiguration {
  secret: string;
}

/** Estrutura consolidada de configuração do Gateway. */
export interface Configuration {
  app: AppConfiguration;
  redis: RedisConfiguration;
  database: DatabaseConfiguration;
  uazapi: UazapiConfiguration;
  zapi: ZapiConfiguration;
  openai: OpenAIConfiguration;
  google: GoogleConfiguration;
  minimax: MiniMaxConfiguration;
  asaas: AsaasConfiguration;
  jwt: JwtConfiguration;
  cors: CorsConfiguration;
  internal: InternalConfiguration;
  api: ApiConfiguration;
  ai: AiConfiguration;
  throttler: ThrottlerConfiguration;
  meta: MetaConfiguration;
  gateway: GatewayConfiguration;
  defaultTenantId: string;
}
