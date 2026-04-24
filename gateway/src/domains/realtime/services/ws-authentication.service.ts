import { ConfigService } from '@nestjs/config';
import { Injectable, Logger, Optional } from '@nestjs/common';
import { Socket } from 'socket.io';
import * as jwt from 'jsonwebtoken';
import axios from 'axios';
import { AuthenticatedHandshake, JwtPayload } from '../types/socket.types';
import { TokenCacheEntry } from '../models/ws-authentication.model';

/**
 * WsAuthenticationService
 *
 * Serviço responsável por autenticar clientes WebSocket.
 * Suporta validação de JWT e fallback para introspect Sanctum via API interna.
 * Mantém cache LRU de tokens Sanctum para evitar latência desnecessária.
 */
@Injectable()
export class WsAuthenticationService {
  private readonly logger: Logger;
  private readonly sanctumTokenCache = new Map<string, TokenCacheEntry>();
  private readonly sanctumTokenCacheMaxEntries: number;
  private readonly sanctumTokenCacheTtlMs: number;
  private cleanupInterval: ReturnType<typeof setInterval> | null = null;

  constructor(
    private readonly configService: ConfigService,
    @Optional()
    logger?: Logger,
  ) {
    this.logger = logger ?? new Logger(WsAuthenticationService.name);
    this.sanctumTokenCacheMaxEntries =
      this.resolveSanctumTokenCacheMaxEntries();
    this.sanctumTokenCacheTtlMs = this.resolveSanctumTokenCacheTtlMs();
  }

  /**
   * Initializes the cache lifecycle hooks.
   */
  onModuleInit(): void {
    this.cleanupInterval = setInterval(() => {
      this.pruneTokenCache(Date.now());
    }, 60_000);
    this.pruneTokenCache(Date.now());
  }

  /**
   * Clears in-memory cache references to avoid leaks on shutdown.
   */
  onModuleDestroy(): void {
    if (this.cleanupInterval) {
      clearInterval(this.cleanupInterval);
      this.cleanupInterval = null;
    }

    this.sanctumTokenCache.clear();
  }

  /**
   * Extracts a bearer token from websocket auth metadata.
   */
  extractToken(client: Socket): string | null {
    const handshake = client.handshake as unknown as AuthenticatedHandshake;
    const authToken = handshake.auth?.token;
    if (typeof authToken === 'string') return authToken;

    const authHeader = client.handshake.headers?.authorization;
    if (typeof authHeader === 'string' && authHeader.startsWith('Bearer ')) {
      return authHeader.slice(7);
    }

    return null;
  }

  /**
   * Verifies JWT first, then falls back to Sanctum introspection.
   */
  async verifyToken(token: string): Promise<JwtPayload> {
    const secret = this.configService.get<string>('jwt.secret');
    if (secret) {
      try {
        return await this.verifyJwt(token, secret);
      } catch {
        this.logger.verbose(
          'JWT verification failed, trying Sanctum token validation',
        );
      }
    }

    return this.verifySanctumToken(token);
  }

  /**
   * Verifica o token JWT usando a chave secreta e o algoritmo HS256.
   * Rejeita tokens sem as claims obrigatórias `sub` e `tenant_id`.
   *
   * @param token - Token JWT em formato string
   * @param secret - Chave secreta para verificação
   * @returns Payload JWT com as claims validadas
   * @throws Error se o token for inválido ou claims estiverem ausentes
   */
  private verifyJwt(token: string, secret: string): Promise<JwtPayload> {
    return new Promise((resolve, reject) => {
      jwt.verify(token, secret, { algorithms: ['HS256'] }, (err, decoded) => {
        if (err) {
          reject(new Error(`Token verification failed: ${err.message}`));
          return;
        }

        const payload = decoded as JwtPayload;

        if (!payload.sub || !payload.tenant_id) {
          reject(new Error('Missing required JWT claims'));
          return;
        }

        resolve(payload);
      });
    });
  }

  /**
   * Valida o token via endpoint `/api/auth/me` do Laravel (Sanctum).
   * Utiliza cache com TTL configurável para evitar requisições repetidas.
   *
   * @param token - Token Sanctum a ser introspectado
   * @returns Payload com `sub`, `tenant_id` e opcionalmente `email`
   * @throws Error se a requisição falhar ou as claims não estiverem presentes
   */
  private async verifySanctumToken(token: string): Promise<JwtPayload> {
    const now = Date.now();
    const cachedPayload = this.getCachedToken(token, now);
    if (cachedPayload) {
      return cachedPayload;
    }

    const apiUrl = this.configService.get<string>('api.url');
    const authTimeoutMs =
      this.configService.get<number>('api.authTimeoutMs') ?? 1500;
    if (!apiUrl) {
      throw new Error('API_URL not configured for Sanctum token validation');
    }

    try {
      const response = await axios.get<{
        success: boolean;
        data: {
          user: {
            id: string | number;
            tenant_id: string | number | null;
            email?: string;
          };
        };
      }>(`${apiUrl}/api/auth/me`, {
        headers: { Authorization: `Bearer ${token}` },
        timeout: authTimeoutMs,
      });

      const user = response.data?.data?.user;
      if (!user?.id || !user?.tenant_id) {
        throw new Error('Missing user claims from Sanctum validation');
      }

      const payload = {
        sub: String(user.id),
        tenant_id: String(user.tenant_id),
        email: user.email,
      };

      this.setCachedToken(token, {
        payload,
        expiresAt: now + this.sanctumTokenCacheTtlMs,
      });

      return payload;
    } catch (error: unknown) {
      let detail: string;
      if (axios.isAxiosError(error)) {
        if (error.response) {
          detail = `HTTP ${error.response.status}`;
        } else if (error.code) {
          detail = `${error.code} (${apiUrl})`;
        } else {
          detail = error.message || 'unknown axios error';
        }
      } else {
        detail = error instanceof Error ? error.message : String(error);
      }
      throw new Error(`Sanctum validation failed: ${detail}`);
    }
  }

  /**
   * Busca uma entrada de cache válida para o token informado.
   * Remove automaticamente entradas expiradas.
   *
   * @param token - Token a ser buscado no cache
   * @param now - Timestamp atual em millisegundos
   * @returns Payload JWT em cache ou `null` se ausente/expirado
   */
  private getCachedToken(token: string, now: number): JwtPayload | null {
    const entry = this.sanctumTokenCache.get(token);
    if (!entry) {
      return null;
    }

    if (entry.expiresAt <= now) {
      this.sanctumTokenCache.delete(token);
      return null;
    }

    // Refresh recency in insertion-ordered map.
    this.sanctumTokenCache.delete(token);
    this.sanctumTokenCache.set(token, entry);
    return entry.payload;
  }

  /**
   * Armazena uma nova entrada no cache de tokens Sanctum.
   * Garante a unicidade removendo entrada anterior antes de reinserir.
   *
   * @param token - Token a ser armazenado
   * @param entry - Entrada de cache com payload e timestamp de expiração
   */
  private setCachedToken(token: string, entry: TokenCacheEntry): void {
    if (this.sanctumTokenCache.has(token)) {
      this.sanctumTokenCache.delete(token);
    }

    this.sanctumTokenCache.set(token, entry);
    this.pruneTokenCache(Date.now());
  }

  /**
   * Remove entradas expiradas do cache e limita o tamanho máximo por LRU.
   * Remove primeiro as entradas mais antigas quando o limite é excedido.
   *
   * @param now - Timestamp atual em millisegundos usado para verificar expiração
   */
  private pruneTokenCache(now: number): void {
    for (const [token, entry] of this.sanctumTokenCache) {
      if (entry.expiresAt <= now) {
        this.sanctumTokenCache.delete(token);
      }
    }

    while (this.sanctumTokenCache.size > this.sanctumTokenCacheMaxEntries) {
      const oldestKeyIterator = this.sanctumTokenCache.keys().next();
      if (oldestKeyIterator.done) {
        break;
      }

      const oldestKey = oldestKeyIterator.value;
      this.sanctumTokenCache.delete(oldestKey);
    }
  }

  /**
   * Resolve TTL do cache Sanctum em ms com clamp para janela efetiva (5-10 min).
   */
  private resolveSanctumTokenCacheTtlMs(): number {
    const fallback = 300_000;
    const min = 300_000;
    const max = 600_000;
    const value = this.configService.get<string>('WS_SANCTUM_CACHE_TTL_MS');
    const parsed = Number(value);

    if (!Number.isFinite(parsed) || !Number.isInteger(parsed)) {
      return fallback;
    }

    return Math.min(max, Math.max(min, parsed));
  }

  /**
   * Resolve limite máximo do cache Sanctum com limites seguros para pico de conexões.
   */
  private resolveSanctumTokenCacheMaxEntries(): number {
    const fallback = 5_000;
    const min = 2_000;
    const max = 50_000;
    const value = this.configService.get<string>(
      'WS_SANCTUM_CACHE_MAX_ENTRIES',
    );
    const parsed = Number(value);

    if (!Number.isFinite(parsed) || !Number.isInteger(parsed)) {
      return fallback;
    }

    return Math.min(max, Math.max(min, parsed));
  }
}
