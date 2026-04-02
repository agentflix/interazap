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
   * Initializes the instances controller with the Uazapi client.
   *
   * @param client - Uazapi HTTP client for instance lifecycle operations
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Initializes a new Uazapi instance.
   *
   * @param body - Instance initialization payload
   * @returns Created instance data
   */
  @Post()
  initInstance(@Body() body: Record<string, unknown>) {
    this.logger.log('Initializing new instance');
    return this.client.initInstance(body);
  }

  /**
   * Lists all Uazapi instances.
   *
   * @returns List of instances
   */
  @Get()
  listInstances() {
    this.logger.log('Listing all instances');
    return this.client.listInstances();
  }

  /**
   * Connects an existing Uazapi instance.
   *
   * @param token - Instance token
   * @param body - Connection payload
   * @returns Connection result
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
   * Disconnects a Uazapi instance.
   *
   * @param token - Instance token
   * @returns Disconnection result
   */
  @Post(':token/disconnect')
  disconnect(@Param('token') token: string) {
    this.logger.log('Disconnecting instance');
    return this.client.disconnectInstance(token);
  }

  /**
   * Configures webhook for a Uazapi instance.
   *
   * @param token - Instance token
   * @param body - Webhook configuration payload
   * @returns Webhook configuration result
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
   * Gets the status of a Uazapi instance.
   *
   * @param token - Instance token
   * @returns Instance status
   */
  @Get(':token/status')
  status(@Param('token') token: string) {
    this.logger.log('Fetching instance status');
    return this.client.instanceStatus(token);
  }

  /**
   * Deletes a Uazapi instance.
   *
   * @param token - Instance token
   * @returns Deletion result
   */
  @Post(':token/delete')
  delete(@Param('token') token: string) {
    this.logger.log('Deleting instance');
    return this.client.deleteInstance(token);
  }

  /**
   * Updates the profile image of a Uazapi instance.
   *
   * @param token - Instance token
   * @param body - Profile image update payload
   * @returns Update result
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
   * Updates the presence status of a Uazapi instance.
   *
   * @param token - Instance token
   * @param body - Presence update payload
   * @returns Update result
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
