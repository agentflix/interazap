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
   * Inicializa o controller de instancias Z-API com o adaptador Zapi.
   *
   * @param zapiAdapter Adaptador do provedor Z-API WhatsApp
   */
  constructor(private readonly zapiAdapter: ZapiAdapter) {}

  /**
   * Inicia conexao da instancia Z-API via QR code.
   *
   * @param token Token da instancia
   * @returns Dados do QR code para conexao
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
   * Consulta o status de conexao de uma instancia Z-API.
   *
   * @param token Token da instancia
   * @returns Status atual da instancia
   */
  @Get(':token/status')
  async status(@Param('token', new InstanceTokenPipe()) token: string) {
    return this.zapiAdapter.getStatus(token);
  }

  /**
   * Desconecta uma instancia Z-API.
   *
   * @param token Token da instancia
   * @returns Resultado da desconexao
   */
  @Post(':token/disconnect')
  async disconnect(@Param('token', new InstanceTokenPipe()) token: string) {
    await this.zapiAdapter.disconnect(token);
    return { success: true };
  }

  /**
   * Recupera o QR code de uma instancia Z-API.
   *
   * @param token Token da instancia
   * @returns Dados do QR code
   */
  @Get(':token/qr')
  async getQrCode(@Param('token', new InstanceTokenPipe()) token: string) {
    const qrCode = await this.zapiAdapter.getQrCode(token);
    return { qr_code: qrCode };
  }

  /**
   * Retorna indicacao de que webhooks Z-API devem ser configurados via painel admin.
   *
   * @param token Token da instancia
   * @returns Resposta de erro informando a restricao
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
