import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError } from 'axios';
import { RedisService } from '../../../infrastructure/redis/redis.service';
import {
  InstanceLookupResult,
  WabaLookupResult,
} from '../providers/meta/meta.dto';

/**
 * MetaLookupService
 *
 * Servico HTTP para comunicacao Gateway -> Backend.
 * O Gateway NAO acessa banco do Backend diretamente.
 * Usa GATEWAY_SECRET para autenticacao.
 */
@Injectable()
export class MetaLookupService {
  private readonly logger = new Logger(MetaLookupService.name);
  private readonly timeoutMs = 5000;
  private readonly wabaCacheTtlSeconds = 300; // 5 minutes

  constructor(
    private readonly configService: ConfigService,
    private readonly redisService: RedisService,
  ) {}

  /**
   * Resolve phone_number_id da Meta para dados da ChatInstance.
   * Chamado pelo normalizeWebhook para obter tenantId e instanceId.
   *
   * @param phoneNumberId - Phone Number ID da Meta
   * @returns InstanceLookupResult ou null se nao encontrado
   */
  async resolvePhoneNumberId(
    phoneNumberId: string,
  ): Promise<InstanceLookupResult | null> {
    if (!phoneNumberId) {
      this.logger.warn(
        'Empty phone_number_id provided to resolvePhoneNumberId',
      );
      return null;
    }

    const backendUrl = this.configService.get<string>('api.url');
    const gatewaySecret = this.configService.get<string>('gateway.secret');

    if (!backendUrl) {
      this.logger.warn('Backend URL not configured (api.url)');
      return null;
    }

    if (!gatewaySecret) {
      this.logger.warn('Gateway secret not configured (GATEWAY_SECRET)');
      return null;
    }

    try {
      const url = `${backendUrl}/api/chat/instances/by-phone-number/${phoneNumberId}`;

      this.logger.debug(
        `Resolving phone_number_id ${phoneNumberId} via Backend`,
      );

      const response = await axios.get<InstanceLookupResult>(url, {
        headers: {
          Authorization: `Bearer ${gatewaySecret}`,
          Accept: 'application/json',
        },
        timeout: this.timeoutMs,
      });

      if (response.data) {
        this.logger.debug(
          `Resolved phone_number_id ${phoneNumberId} to instance ${response.data.instanceId}`,
        );
        return response.data;
      }

      return null;
    } catch (error) {
      const axiosError = error as AxiosError;

      if (axiosError.response?.status === 404) {
        this.logger.warn(
          `Instance not found for phone_number_id: ${phoneNumberId}`,
        );
        return null;
      }

      this.logger.warn(
        `Failed to resolve phone_number_id ${phoneNumberId}: ${axiosError.message}`,
      );
      return null;
    }
  }

  /**
   * Resolve WABA ID (WhatsApp Business Account ID) para tenant/instance.
   * Usado por eventos `message_template_status_update`, que não trazem
   * `phone_number_id` no payload e precisam ser roteados pelo `entry.id`.
   *
   * Cacheia em Redis por 5 minutos (`meta:lookup:waba:{wabaId}`).
   *
   * @param wabaId - WhatsApp Business Account ID (entry.id do webhook)
   * @returns WabaLookupResult ou null se não encontrado / config ausente
   */
  async resolveWabaId(wabaId: string): Promise<WabaLookupResult | null> {
    if (!wabaId) {
      this.logger.warn('Empty wabaId provided to resolveWabaId');
      return null;
    }

    const cacheKey = `meta:lookup:waba:${wabaId}`;

    // Cache lookup
    try {
      const cached = await this.redisService.getClient().get(cacheKey);
      if (cached) {
        this.logger.debug(`WABA lookup cache hit for ${wabaId}`);
        return JSON.parse(cached) as WabaLookupResult;
      }
    } catch (error) {
      this.logger.warn(
        `Failed to read WABA cache for ${wabaId}: ${error instanceof Error ? error.message : String(error)}`,
      );
    }

    const backendUrl = this.configService.get<string>('api.url');
    const internalApiKey = this.configService.get<string>('internal.apiKey');

    if (!backendUrl) {
      this.logger.warn('Backend URL not configured (api.url)');
      return null;
    }

    if (!internalApiKey) {
      this.logger.warn('Internal API key not configured (INTERNAL_API_KEY)');
      return null;
    }

    try {
      const url = `${backendUrl}/api/internal/chat/instances/by-waba/${wabaId}`;

      this.logger.debug(`Resolving wabaId ${wabaId} via Backend`);

      const response = await axios.get<{
        tenant_id: string;
        instance_id: string;
      }>(url, {
        headers: {
          'x-api-key': internalApiKey,
          Accept: 'application/json',
        },
        timeout: this.timeoutMs,
      });

      if (!response.data?.tenant_id || !response.data?.instance_id) {
        this.logger.warn(
          `Malformed response for wabaId ${wabaId}: missing tenant_id/instance_id`,
        );
        return null;
      }

      const result: WabaLookupResult = {
        tenantId: response.data.tenant_id,
        instanceId: response.data.instance_id,
      };

      // Cache for 5 minutes
      try {
        await this.redisService
          .getClient()
          .setex(cacheKey, this.wabaCacheTtlSeconds, JSON.stringify(result));
      } catch (error) {
        this.logger.warn(
          `Failed to cache WABA lookup for ${wabaId}: ${error instanceof Error ? error.message : String(error)}`,
        );
      }

      this.logger.debug(
        `Resolved wabaId ${wabaId} to instance ${result.instanceId}`,
      );
      return result;
    } catch (error) {
      const axiosError = error as AxiosError;

      if (axiosError.response?.status === 404) {
        this.logger.warn(`Instance not found for wabaId: ${wabaId}`);
        return null;
      }

      this.logger.warn(
        `Failed to resolve wabaId ${wabaId}: ${axiosError.message}`,
      );
      return null;
    }
  }
}
