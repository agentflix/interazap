import {
  Body,
  Controller,
  Get,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';
import { InstanceTokenPipe } from '../pipes/instance-token.pipe';

/**
 * ZapiInstancesController
 *
 * Gerencia instâncias Z-API (provider WhatsApp).
 * Controla conexão via QR Code, status, desconexão e webhooks.
 */
@Controller({ path: 'zapi/instances', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class ZapiInstancesController {
  /**
   * Initializes the Z-API instances controller with the Zapi adapter.
   *
   * @param zapiAdapter - Adapter for Z-API WhatsApp provider
   */
  constructor(private readonly zapiAdapter: ZapiAdapter) {}

  /**
   * Initiates connection to Z-API instance via QR code.
   *
   * @param token - Instance token
   * @returns QR code data for connection
   */
  @Post(':token/connect')
  async connect(@Param('token', new InstanceTokenPipe()) token: string) {
    const qrCode = await this.zapiAdapter.getQrCode(token);

    return {
      provider: 'zapi',
      mode: 'qr',
      qr_code: qrCode,
      pair_code: null,
      expires_at: new Date(Date.now() + 5 * 60 * 1000).toISOString(),
    };
  }

  /**
   * Gets the connection status of a Z-API instance.
   *
   * @param token - Instance token
   * @returns Instance status
   */
  @Get(':token/status')
  async status(@Param('token', new InstanceTokenPipe()) token: string) {
    return this.zapiAdapter.getStatus(token);
  }

  /**
   * Disconnects a Z-API instance.
   *
   * @param token - Instance token
   * @returns Disconnection result
   */
  @Post(':token/disconnect')
  async disconnect(@Param('token', new InstanceTokenPipe()) token: string) {
    await this.zapiAdapter.disconnect(token);
    return { success: true };
  }

  /**
   * Gets the QR code for a Z-API instance.
   *
   * @param token - Instance token
   * @returns QR code data
   */
  @Get(':token/qr')
  async getQrCode(@Param('token', new InstanceTokenPipe()) token: string) {
    const qrCode = await this.zapiAdapter.getQrCode(token);
    return { qr_code: qrCode };
  }

  /**
   * Returns an error indicating webhooks must be configured via admin panel.
   *
   * @param token - Instance token
   * @returns Error response
   */
  @Post(':token/webhook')
  configureWebhook(@Param('token', new InstanceTokenPipe()) token: string) {
    return {
      success: false,
      provider: 'zapi',
      token,
      message: 'Z-API webhooks must be configured via admin panel',
    };
  }
}
