import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError } from 'axios';
import { InstanceLookupResult } from '../providers/meta/meta.dto';

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

  constructor(private readonly configService: ConfigService) {}

  /**
   * Resolve phone_number_id da Meta para dados da ChatInstance.
   * Chamado pelo normalizeWebhook para obter tenantId e instanceId.
   *
   * @param phoneNumberId - Phone Number ID da Meta
   * @returns InstanceLookupResult ou null se nao encontrado
   */
  async resolvePhoneNumberId(phoneNumberId: string): Promise<InstanceLookupResult | null> {
    if (!phoneNumberId) {
      this.logger.warn('Empty phone_number_id provided to resolvePhoneNumberId');
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

      this.logger.debug(`Resolving phone_number_id ${phoneNumberId} via Backend`);

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
        this.logger.warn(`Instance not found for phone_number_id: ${phoneNumberId}`);
        return null;
      }

      this.logger.warn(
        `Failed to resolve phone_number_id ${phoneNumberId}: ${axiosError.message}`,
      );
      return null;
    }
  }
}
