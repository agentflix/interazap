import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import {
  JsonRecord,
  getBoolean,
  getString,
} from '../../../shared/utils/type-guards';

/**
 * TicketResolverService
 *
 * Resolves WhatsApp message remote JIDs to ticket IDs with Redis caching.
 * Provides tenant-aware ticket mapping to prevent cross-tenant data leakage.
 */
@Injectable()
export class TicketResolverService {
  private readonly logger = new Logger(TicketResolverService.name);
  private readonly ticketCacheTtlSeconds: number;

  /**
   * Initializes the resolver with a Redis service and config-driven TTL for ticket mappings.
   *
   * @param configService - NestJS ConfigService for reading environment variables.
   * @param redisService  - RedisService for caching ticket-to-JID mappings.
   */
  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
  ) {
    const parsed = Number(
      this.configService.get<string | number>('TICKET_CACHE_TTL_SECONDS'),
    );
    this.ticketCacheTtlSeconds =
      Number.isFinite(parsed) && Number.isInteger(parsed) && parsed > 0
        ? parsed
        : 3600;
  }

  /**
   * Extracts the remote JID from a WhatsApp message payload.
   *
   * Prefers explicit chatId fields; falls back to the `to` field for outgoing
   * messages or the `from` field for incoming ones.
   *
   * @param message - A JsonRecord representing the raw message payload.
   * @returns The remote JID as a string, or null if none can be determined.
   */
  resolveRemoteJid(message: JsonRecord): string | null {
    const directRemoteJid =
      getString(message, 'chatid') ??
      getString(message, 'chatId') ??
      getString(message, 'remote_jid') ??
      getString(message, 'remoteJid');

    if (directRemoteJid) {
      return directRemoteJid;
    }

    const isFromMe = getBoolean(message, 'fromMe') ?? false;
    if (isFromMe) {
      return getString(message, 'to') ?? null;
    }

    return getString(message, 'from') ?? null;
  }

  /**
   * Resolves a ticket ID from a cached Redis mapping using a remote JID.
   *
   * Enforces tenant isolation by validating the cached tenant_id against the
   * requested tenant. If the cache is stale or missing, returns null.
   * Uses a 300 ms timeout to avoid blocking the event loop.
   *
   * @param tenantId  - The tenant ID used for isolation validation.
   * @param remoteJid - The normalized remote JID to look up.
   * @returns The cached ticket ID string, or null if not found or tenant mismatch.
   */
  async resolveTicketIdForRemoteJid(
    tenantId: string,
    remoteJid: string | null,
  ): Promise<string | null> {
    const normalizedRemoteJid = this.normalizeRemoteJid(remoteJid);
    if (!normalizedRemoteJid) {
      return null;
    }

    const cacheKey = `chat.ticket_by_jid:${normalizedRemoteJid}`;

    try {
      const timeoutPromise = new Promise<null>((resolve) =>
        setTimeout(() => resolve(null), 300),
      );
      const cachePayload = await Promise.race<string | null>([
        this.redisService.get(cacheKey),
        timeoutPromise,
      ]);
      if (!cachePayload) {
        return null;
      }

      const parsed = JSON.parse(cachePayload) as {
        ticket_id?: unknown;
        tenant_id?: unknown;
      };

      const cachedTenantId =
        typeof parsed.tenant_id === 'string' ? parsed.tenant_id : null;
      const cachedTicketId =
        typeof parsed.ticket_id === 'string' ? parsed.ticket_id : null;

      if (!cachedTenantId || !cachedTicketId) {
        return null;
      }

      if (cachedTenantId !== tenantId) {
        this.logger.warn(
          `Ignoring ticket mapping with tenant mismatch for remote_jid ${normalizedRemoteJid}`,
        );
        return null;
      }

      await this.redisService.set(
        cacheKey,
        JSON.stringify({
          tenant_id: cachedTenantId,
          ticket_id: cachedTicketId,
        }),
        this.ticketCacheTtlSeconds,
      );

      return cachedTicketId;
    } catch (error) {
      this.logger.debug(
        `Failed to resolve ticket mapping for remote_jid ${normalizedRemoteJid}: ${(error as Error).message}`,
      );
      return null;
    }
  }

  /**
   * Normalizes a remote JID to lowercase and trims whitespace.
   */
  private normalizeRemoteJid(remoteJid: string | null): string | null {
    const normalized = remoteJid?.trim().toLowerCase() ?? '';
    return normalized.length > 0 ? normalized : null;
  }
}
