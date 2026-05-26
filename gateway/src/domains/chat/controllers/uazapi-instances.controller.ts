import {
  Body,
  Controller,
  Get,
  Logger,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { ConnectInstanceDto } from '../dto/connect-instance.dto';
import { UpdateProfileImageDto } from '../dto/update-profile-image.dto';
import { UpdatePresenceDto } from '../dto/update-presence.dto';

/**
 * UazapiInstancesController
 *
 * Gerencia instâncias Uazapi (WhatsApp Gateway).
 * Controla ciclo de vida: init, connect, disconnect, delete,
 * além de configurações de webhook, profile image e presence.
 */
@Controller({ path: 'uazapi/instances', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiInstancesController {
  private readonly logger = new Logger(UazapiInstancesController.name);

  /**
   * Inicializa o controller de instancias com o cliente Uazapi.
   *
   * @param client Cliente HTTP Uazapi para operacoes de ciclo de vida de instancias
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Inicializa uma nova instancia Uazapi.
   *
   * @param body Payload de inicializacao da instancia
   * @returns Dados da instancia criada
   */
  @Post()
  initInstance(@Body() body: Record<string, unknown>) {
    this.logger.log('Initializing new instance');
    return this.client.initInstance(body);
  }

  /**
   * Lista todas as instancias Uazapi disponiveis.
   *
   * @returns Lista de instancias
   */
  @Get()
  listInstances() {
    this.logger.log('Listing all instances');
    return this.client.listInstances();
  }

  /**
   * Conecta uma instancia Uazapi existente.
   *
   * @param token Token da instancia
   * @param body Payload de conexao
   * @returns Resultado da conexao
   */
  @Post(':token/connect')
  connect(@Param('token') token: string, @Body() body: ConnectInstanceDto) {
    this.logger.log('Connecting instance');
    return this.client.connectInstance(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Desconecta uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @returns Resultado da desconexao
   */
  @Post(':token/disconnect')
  disconnect(@Param('token') token: string) {
    this.logger.log('Disconnecting instance');
    return this.client.disconnectInstance(token);
  }

  /**
   * Configura o webhook de uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload de configuracao do webhook
   * @returns Resultado da configuracao
   */
  @Post(':token/webhook')
  configureWebhook(
    @Param('token') token: string,
    @Body() body: Record<string, unknown>,
  ) {
    this.logger.log('Configuring webhook for instance');
    return this.client.configureWebhook(token, body);
  }

  /**
   * Consulta o status de uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @returns Status atual da instancia
   */
  @Get(':token/status')
  status(@Param('token') token: string) {
    this.logger.log('Fetching instance status');
    return this.client.instanceStatus(token);
  }

  /**
   * Remove uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @returns Resultado da remocao
   */
  @Post(':token/delete')
  delete(@Param('token') token: string) {
    this.logger.log('Deleting instance');
    return this.client.deleteInstance(token);
  }

  /**
   * Atualiza a imagem de perfil de uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload com a nova imagem de perfil
   * @returns Resultado da atualizacao
   */
  @Post(':token/profile-image')
  updateProfileImage(
    @Param('token') token: string,
    @Body() body: UpdateProfileImageDto,
  ) {
    this.logger.log('Updating profile image for instance');
    return this.client.updateProfileImage(token, body.image);
  }

  /**
   * Atualiza o status de presenca de uma instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload com o novo status de presenca
   * @returns Resultado da atualizacao
   */
  @Post(':token/presence')
  updatePresence(
    @Param('token') token: string,
    @Body() body: UpdatePresenceDto,
  ) {
    this.logger.log('Updating presence for instance');
    return this.client.updatePresence(token, body.presence);
  }
}
