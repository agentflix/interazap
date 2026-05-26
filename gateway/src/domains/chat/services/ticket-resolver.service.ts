import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import {
  JsonRecord,
  getBoolean,
  getString,
} from '../../../shared/utils/type-guards';

/**
 * Resolve JIDs remotos de mensagens WhatsApp para IDs de ticket com cache Redis.
 *
 * Contexto: fornece mapeamento de ticket com isolamento por tenant para prevenir
 * vazamento de dados entre tenants. Utiliza timeout de 300ms nas consultas Redis
 * para evitar bloqueio do loop de eventos durante o processamento de webhooks.
 */
@Injectable()
export class TicketResolverService {
  private readonly logger = new Logger(TicketResolverService.name);
  private readonly ticketCacheTtlSeconds: number;

  /**
   * Inicializa o resolver com o servico Redis e TTL configuravel para mapeamentos de ticket.
   *
   * @param configService ConfigService do NestJS para leitura de variaveis de ambiente
   * @param redisService RedisService para cache dos mapeamentos ticket-para-JID
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
   * Extrai o JID remoto de um payload de mensagem WhatsApp.
   *
   * Prioriza campos explicitos de chatId; cai para o campo `to` em mensagens
   * enviadas ou para o campo `from` em mensagens recebidas.
   *
   * @param message JsonRecord representando o payload bruto da mensagem
   * @returns JID remoto como string ou null quando nao determinavel
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
   * Resolve um ID de ticket a partir do mapeamento em cache Redis usando um JID remoto.
   *
   * Impoe isolamento de tenant validando o tenant_id em cache contra o tenant solicitado.
   * Retorna null quando o cache nao existe ou ha divergencia de tenant.
   * Usa timeout de 300ms para evitar bloqueio do loop de eventos.
   *
   * @param tenantId ID do tenant usado para validacao de isolamento
   * @param remoteJid JID remoto normalizado para busca
   * @returns String do ticket ID em cache, ou null se nao encontrado ou tenant divergente
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
   * Normaliza um JID remoto para minusculas sem espacos em branco.
   *
   * @param remoteJid - JID remoto a normalizar
   * @returns JID normalizado ou null quando vazio
   */
  private normalizeRemoteJid(remoteJid: string | null): string | null {
    const normalized = remoteJid?.trim().toLowerCase() ?? '';
    return normalized.length > 0 ? normalized : null;
  }
}
