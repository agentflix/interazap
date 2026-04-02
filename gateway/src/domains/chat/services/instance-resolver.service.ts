import {
  Injectable,
  Logger,
  ServiceUnavailableException,
  UnauthorizedException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import { DatabaseService } from '../../../infrastructure/database/database.service';
import { isRecord } from '../../../shared/utils/type-guards';
import { ProviderType, ResolvedInstance } from '../models/instance.model';

/**
 * Resolve instancias do chat a partir do webhook token com cache em memoria e Redis.
 */
@Injectable()
export class InstanceResolverService {
  private readonly logger = new Logger(InstanceResolverService.name);
  private readonly activeCacheTtlSeconds = 3600;
  private readonly staleCacheTtlSeconds = 86400;
  private readonly revalidateLockTtlSeconds = 15;
  private readonly processCacheTtlMs = 3_600_000; // 1 hour
  private readonly cacheGetTimeoutMs: number;
  private readonly dbLookupTimeoutMs: number;
  private readonly uuidTokenRegex =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
  private readonly processCache = new Map<
    string,
    { instance: ResolvedInstance; expiresAt: number }
  >();
  private readonly inFlightResolutions = new Map<
    string,
    Promise<ResolvedInstance>
  >();

  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
    private readonly databaseService: DatabaseService,
  ) {
    this.cacheGetTimeoutMs =
      this.configService.get<number>(
        'INSTANCE_RESOLVER_CACHE_GET_TIMEOUT_MS',
      ) ?? 300;
    this.dbLookupTimeoutMs =
      this.configService.get<number>(
        'INSTANCE_RESOLVER_DB_LOOKUP_TIMEOUT_MS',
      ) ?? 1200;
  }

  /**
   * Resolve a instancia correspondente a um token de webhook.
   */
  async resolveByWebhookToken(token: string): Promise<ResolvedInstance> {
    const processCached = this.readProcessCache(token);
    if (processCached) {
      return processCached;
    }

    const inFlight = this.inFlightResolutions.get(token);
    if (inFlight) {
      return inFlight;
    }

    const resolutionPromise = this.resolveByWebhookTokenInternal(token);
    this.inFlightResolutions.set(token, resolutionPromise);

    try {
      return await resolutionPromise;
    } finally {
      const currentInFlight = this.inFlightResolutions.get(token);
      if (currentInFlight === resolutionPromise) {
        this.inFlightResolutions.delete(token);
      }
    }
  }

  /**
   * Implementacao interna de resolucao evitando race condition com in-flight cache.
   *
   * @param token - Token do webhook da instancia
   * @returns Instancia resolvida
   */
  private async resolveByWebhookTokenInternal(
    token: string,
  ): Promise<ResolvedInstance> {
    const processCached = this.readProcessCache(token);
    if (processCached) {
      return processCached;
    }

    const activeCacheKey = this.getActiveCacheKey(token);
    const staleCacheKey = this.getStaleCacheKey(token);

    const [activeCached, staleCached] = await Promise.all([
      this.getWithTimeout(activeCacheKey, this.cacheGetTimeoutMs),
      this.getWithTimeout(staleCacheKey, this.cacheGetTimeoutMs),
    ]);

    const activeInstance = activeCached
      ? this.parseCachedInstance(activeCached)
      : null;
    if (activeInstance) {
      this.writeProcessCache(token, activeInstance);
      return activeInstance;
    }

    const staleInstance = staleCached
      ? this.parseCachedInstance(staleCached)
      : null;

    if (staleInstance) {
      this.writeProcessCache(token, staleInstance);
      void this.revalidateInBackground(token);
      return staleInstance;
    }

    return this.resolveFromDatabaseAndCache(token);
  }

  /**
   * Invalida os caches associados ao token de webhook informado.
   */
  async invalidateByWebhookToken(token: string): Promise<void> {
    this.processCache.delete(token);

    await this.redisService
      .getClient()
      .del(this.getActiveCacheKey(token), this.getStaleCacheKey(token));
  }

  /**
   * Busca a instancia no banco de dados e persiste nos caches.
   *
   * @param token - Token do webhook da instancia
   * @returns Instancia resolvida
   * @throws UnauthorizedException quando o token nao e encontrado
   */
  private async resolveFromDatabaseAndCache(
    token: string,
  ): Promise<ResolvedInstance> {
    let instance: ResolvedInstance | null = null;

    try {
      instance = await this.findByAnyToken(token);
    } catch (error) {
      if (this.isLookupTimeoutError(error)) {
        this.logger.error(
          `Instance lookup timeout: ${(error as Error).message}`,
        );
        throw new ServiceUnavailableException('Instance resolution timeout');
      }

      this.logger.warn(
        `Failed to resolve instance from database: ${(error as Error).message}`,
      );

      throw new UnauthorizedException('Invalid webhook token');
    }

    if (!instance) {
      this.logger.warn('Instance not found');
      throw new UnauthorizedException('Invalid webhook token');
    }

    await this.writeCache(token, instance);
    this.writeProcessCache(token, instance);
    return instance;
  }

  /**
   * Revalida o cache Redis em background sem bloquear o fluxo principal.
   *
   * @param token - Token do webhook a revalidar
   */
  private async revalidateInBackground(token: string): Promise<void> {
    const lockKey = `chat.instance_by_webhook_token:${token}:revalidate_lock`;
    const lockResult = await this.redisService
      .getClient()
      .set(lockKey, '1', 'EX', this.revalidateLockTtlSeconds, 'NX');

    if (lockResult !== 'OK') {
      return;
    }

    try {
      const instance = await this.findByAnyToken(token);
      if (instance) {
        await this.writeCache(token, instance);
      }
    } catch (error) {
      this.logger.debug(
        `Background revalidation skipped: ${(error as Error).message}`,
      );
    }
  }

  /**
   * Persiste a instancia resolvida nos caches ativo e stale do Redis.
   *
   * @param token - Token do webhook da instancia
   * @param instance - Instancia a ser armazenada
   */
  private async writeCache(
    token: string,
    instance: ResolvedInstance,
  ): Promise<void> {
    const serialized = JSON.stringify(instance);
    await this.redisService
      .getClient()
      .setex(
        this.getActiveCacheKey(token),
        this.activeCacheTtlSeconds,
        serialized,
      );
    await this.redisService
      .getClient()
      .setex(
        this.getStaleCacheKey(token),
        this.staleCacheTtlSeconds,
        serialized,
      );
  }

  /**
   * Le a instancia do cache em processo se ainda valida.
   *
   * @param token - Token do webhook da instancia
   * @returns Instancia em cache ou null quando expirada ou ausente
   */
  private readProcessCache(token: string): ResolvedInstance | null {
    const cached = this.processCache.get(token);
    if (!cached) {
      return null;
    }

    if (cached.expiresAt <= Date.now()) {
      this.processCache.delete(token);
      return null;
    }

    return cached.instance;
  }

  /**
   * Armazena a instancia no cache em processo com TTL configurado.
   *
   * @param token - Token do webhook da instancia
   * @param instance - Instancia a ser armazenada
   */
  private writeProcessCache(token: string, instance: ResolvedInstance): void {
    this.processCache.set(token, {
      instance,
      expiresAt: Date.now() + this.processCacheTtlMs,
    });
  }

  /**
   * Retorna a chave Redis para o cache ativo de um token.
   *
   * @param token - Token do webhook
   * @returns Chave Redis do cache ativo
   */
  private getActiveCacheKey(token: string): string {
    return `chat.instance_by_webhook_token:${token}:active`;
  }

  /**
   * Retorna a chave Redis para o cache stale de um token.
   *
   * @param token - Token do webhook
   * @returns Chave Redis do cache stale
   */
  private getStaleCacheKey(token: string): string {
    return `chat.instance_by_webhook_token:${token}:stale`;
  }

  /**
   * Consulta o banco de dados pelo token usando webhook_token ou settings_json->>'token'.
   *
   * @param token - Token do webhook da instancia
   * @returns Instancia encontrada ou null
   */
  private async findByAnyToken(
    token: string,
  ): Promise<ResolvedInstance | null> {
    const lookupStartedAt = Date.now();

    // 1. Try direct webhook_token (unique index, instant)
    const byWebhookToken: unknown =
      await this.queryWithTimeout<ResolvedInstance>(
        `SELECT id as instance_id, tenant_id, provider, status
         FROM chat_instances
         WHERE webhook_token = $1
         LIMIT 1`,
        [token],
        this.dbLookupTimeoutMs,
        'webhook_token',
      );
    const primary = this.extractRows(byWebhookToken)[0];
    if (primary) return primary;

    if (this.uuidTokenRegex.test(token)) {
      return null;
    }

    // 2. Fallback: settings_json->>'token' (expression index)
    const elapsedMs = Date.now() - lookupStartedAt;
    const remainingBudgetMs = Math.max(100, this.dbLookupTimeoutMs - elapsedMs);
    const bySettingsToken: unknown =
      await this.queryWithTimeout<ResolvedInstance>(
        `SELECT id as instance_id, tenant_id, provider, status
         FROM chat_instances
         WHERE settings_json->>'token' = $1
         LIMIT 1`,
        [token],
        remainingBudgetMs,
        "settings_json->>'token'",
      );
    return this.extractRows(bySettingsToken)[0] ?? null;
  }

  /**
   * Executa uma query no banco de dados com timeout configuravel.
   *
   * @param query - SQL a executar
   * @param params - Parametros da query
   * @param timeoutMs - Limite de tempo em milissegundos
   * @param lookupName - Nome do lookup para logging
   * @returns Resultado da query
   */
  private async queryWithTimeout<T>(
    query: string,
    params: ReadonlyArray<unknown>,
    timeoutMs: number,
    lookupName: string,
  ): Promise<unknown> {
    return this.withTimeout(
      this.databaseService.query<T>(query, params),
      timeoutMs,
      `DB lookup timeout (${lookupName}) after ${timeoutMs}ms`,
    );
  }

  /**
   * Envolve uma Promise com um timeout rejeitando caso o prazo expire.
   *
   * @param promise - Promise a ser protegida
   * @param timeoutMs - Prazo maximo em milissegundos
   * @param message - Mensagem de erro ao expirar
   * @returns Promise protegida por timeout
   */
  private withTimeout<T>(
    promise: Promise<T>,
    timeoutMs: number,
    message: string,
  ): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      const timeoutHandle = setTimeout(() => {
        reject(new Error(`INSTANCE_LOOKUP_TIMEOUT:${message}`));
      }, timeoutMs);

      void promise
        .then((result) => {
          clearTimeout(timeoutHandle);
          resolve(result);
        })
        .catch((error: unknown) => {
          clearTimeout(timeoutHandle);
          reject(
            error instanceof Error
              ? error
              : new Error(`INSTANCE_LOOKUP_ERROR:${String(error)}`),
          );
        });
    });
  }

  /**
   * Verifica se o erro e de timeout de lookup de instancia.
   *
   * @param error - Erro a inspecionar
   * @returns true quando o erro e de timeout de lookup
   */
  private isLookupTimeoutError(error: unknown): boolean {
    return (
      error instanceof Error &&
      error.message.startsWith('INSTANCE_LOOKUP_TIMEOUT:')
    );
  }

  /**
   * Extrai array de instancias de um resultado de query do banco.
   *
   * @param value - Resultado bruto da query
   * @returns Array de instancias validas
   */
  private extractRows(value: unknown): ReadonlyArray<ResolvedInstance> {
    if (!isRecord(value)) {
      return [];
    }
    const rows = value.rows;
    if (!Array.isArray(rows)) {
      return [];
    }

    return rows.filter((row): row is ResolvedInstance =>
      this.isResolvedInstance(row),
    );
  }

  /**
   * Desserializa uma string JSON de cache para uma instancia tipada.
   *
   * @param value - String JSON armazenada no Redis
   * @returns Instancia desserializada ou null quando invalida
   */
  private parseCachedInstance(value: string): ResolvedInstance | null {
    try {
      const parsed = JSON.parse(value) as unknown;
      if (this.isResolvedInstance(parsed)) {
        return parsed;
      }
    } catch (error) {
      this.logger.warn(
        `Invalid cached instance: ${(error as Error)?.message ?? 'unknown error'}`,
      );
    }
    return null;
  }

  /**
   * Valida se um valor desconhecido tem o formato minimo de ResolvedInstance.
   *
   * @param value - Valor a validar
   * @returns true quando o valor e uma ResolvedInstance valida
   */
  private isResolvedInstance(value: unknown): value is ResolvedInstance {
    if (!isRecord(value)) {
      return false;
    }

    const provider = value.provider;
    const hasValidProvider =
      provider === ('uazapi' as ProviderType) ||
      provider === ('zapi' as ProviderType);

    return (
      typeof value.instance_id === 'string' &&
      typeof value.tenant_id === 'string' &&
      hasValidProvider
    );
  }

  /**
   * Executa GET no Redis com timeout para evitar bloqueio do fluxo principal.
   *
   * @param key - Chave Redis a consultar
   * @param timeoutMs - Limite de tempo em milissegundos
   * @returns Valor em string ou null quando ausente ou timeout
   */
  private async getWithTimeout(
    key: string,
    timeoutMs: number,
  ): Promise<string | null> {
    let timeoutHandle: NodeJS.Timeout | undefined;
    try {
      const timeoutPromise = new Promise<null>((resolve) => {
        timeoutHandle = setTimeout(() => resolve(null), timeoutMs);
      });
      const result = await Promise.race<string | null>([
        this.redisService.getClient().get(key),
        timeoutPromise,
      ]);
      if (result === null) {
        this.logger.debug(
          `Redis cache GET timeout after ${timeoutMs}ms for key ${key}; falling through to DB`,
        );
      }
      return result;
    } catch {
      return null;
    } finally {
      if (timeoutHandle) clearTimeout(timeoutHandle);
    }
  }
}
