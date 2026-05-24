import {
  Controller,
  Get,
  Post,
  Delete,
  Param,
  Body,
  Query,
  NotFoundException,
  HttpException,
  HttpStatus,
  Logger,
  UseGuards,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { MetaAdapter } from './providers/meta/meta.adapter';
import { InternalApiClientService } from '../../infrastructure/internal-api/internal-api-client.service';
import { MetaTemplate } from './contracts/meta-provider.interface';
import type { MetaTemplateCreatePayload } from './providers/meta/meta.dto';

/**
 * ChannelsController
 *
 * Gerencia operacoes relacionadas a canais de chat.
 * Fornece endpoints para listar templates de canais Meta.
 */
@Controller({ version: '1', path: 'channels' })
@UseGuards(InternalApiKeyGuard)
export class ChannelsController {
  private readonly logger = new Logger(ChannelsController.name);

  constructor(
    private readonly internalApiClient: InternalApiClientService,
    private readonly metaAdapter: MetaAdapter,
    private readonly configService: ConfigService,
  ) {}

  /**
   * Lista templates de um canal Meta.
   * GET /channels/{id}/templates
   *
   * @param id - ID do canal (chat_instance)
   * @param includeAll - Se 'true' ou '1', retorna todos os status
   * @returns Lista de templates
   */
  @Get(':id/templates')
  async listTemplates(
    @Param('id') id: string,
    @Query('include_all') includeAll?: string,
  ): Promise<MetaTemplate[]> {
    this.logger.debug(`Listing templates for channel ${id}`);

    const channel = await this.fetchChannel(id);
    this.validateMetaChannel(channel, id);

    const accessToken = channel!.settings?.access_token as string | undefined;
    if (!accessToken) {
      throw new NotFoundException(
        `Channel ${id} does not have an access_token configured`,
      );
    }

    const includeAllBool = includeAll === 'true' || includeAll === '1';
    const templates = await this.metaAdapter.listTemplates(
      accessToken,
      includeAllBool,
    );

    this.logger.debug(`Found ${templates.length} templates for channel ${id}`);

    return templates;
  }

  /**
   * Cria um novo template de mensagem em um canal Meta.
   * POST /channels/{id}/templates
   *
   * @param id - ID do canal (chat_instance)
   * @param body - Dados do template a criar
   * @returns ID e status do template criado
   */
  @Post(':id/templates')
  async createTemplate(
    @Param('id') id: string,
    @Body() body: MetaTemplateCreatePayload,
  ): Promise<{ data: { id: string; status: string } }> {
    this.logger.debug(`Creating template for channel ${id}`);

    const channel = await this.fetchChannel(id);
    this.validateMetaChannel(channel, id);

    const wabaId = channel!.settings?.waba_id as string | undefined;
    if (!wabaId) {
      throw new NotFoundException(
        `Channel ${id} does not have a waba_id configured`,
      );
    }

    const accessToken = channel!.settings?.access_token as string | undefined;
    if (!accessToken) {
      throw new NotFoundException(
        `Channel ${id} does not have an access_token configured`,
      );
    }

    try {
      const result = await this.metaAdapter.createTemplate(
        `${wabaId}:${accessToken}`,
        body,
      );
      return { data: result };
    } catch (error) {
      throw new HttpException(
        { error: error instanceof Error ? error.message : 'Meta API error' },
        HttpStatus.BAD_GATEWAY,
      );
    }
  }

  /**
   * Remove um template de mensagem de um canal Meta.
   * DELETE /channels/{id}/templates/{name}
   *
   * @param id - ID do canal (chat_instance)
   * @param name - Nome do template a remover
   * @returns Sucesso da operacao
   */
  @Delete(':id/templates/:name')
  async deleteTemplate(
    @Param('id') id: string,
    @Param('name') name: string,
  ): Promise<{ data: { success: boolean } }> {
    this.logger.debug(`Deleting template ${name} for channel ${id}`);

    const channel = await this.fetchChannel(id);
    this.validateMetaChannel(channel, id);

    const wabaId = channel!.settings?.waba_id as string | undefined;
    if (!wabaId) {
      throw new NotFoundException(
        `Channel ${id} does not have a waba_id configured`,
      );
    }

    const accessToken = channel!.settings?.access_token as string | undefined;
    if (!accessToken) {
      throw new NotFoundException(
        `Channel ${id} does not have an access_token configured`,
      );
    }

    try {
      const result = await this.metaAdapter.deleteTemplate(
        `${wabaId}:${accessToken}`,
        name,
      );
      return { data: result };
    } catch (error) {
      throw new HttpException(
        { error: error instanceof Error ? error.message : 'Meta API error' },
        HttpStatus.BAD_GATEWAY,
      );
    }
  }

  /**
   * Invalida o cache de templates de um canal Meta.
   * POST /channels/{id}/templates/sync
   *
   * @param id - ID do canal (chat_instance)
   * @returns Sucesso da operacao
   */
  @Post(':id/templates/sync')
  async syncTemplates(@Param('id') id: string): Promise<{ success: boolean }> {
    this.logger.debug(`Syncing templates for channel ${id}`);

    const channel = await this.fetchChannel(id);
    this.validateMetaChannel(channel, id);

    const accessToken = channel!.settings?.access_token as string | undefined;
    if (!accessToken) {
      throw new NotFoundException(
        `Channel ${id} does not have an access_token configured`,
      );
    }

    await this.metaAdapter.invalidateTemplatesCache(accessToken);
    return { success: true };
  }

  /**
   * Valida que o canal existe e eh do provider Meta.
   */
  private validateMetaChannel(
    channel: {
      id: string;
      provider: string;
      settings: Record<string, unknown>;
    } | null,
    id: string,
  ): void {
    if (!channel) {
      throw new NotFoundException(`Channel ${id} not found`);
    }

    if (channel.provider !== 'meta') {
      throw new NotFoundException(
        `Channel ${id} is not a Meta channel (provider: ${channel.provider})`,
      );
    }
  }

  /**
   * Busca um canal pelo ID via api/ HTTP.
   */
  private async fetchChannel(id: string): Promise<{
    id: string;
    provider: string;
    settings: Record<string, unknown>;
  } | null> {
    try {
      const response = await this.internalApiClient.get<{
        data: {
          id: string;
          provider: string;
          settings_json: Record<string, unknown>;
        };
      }>(
        `/api/internal/chat/instances/${encodeURIComponent(id)}`,
        'instance_by_id',
      );

      const row = response.data;
      if (!row) return null;

      return {
        id: row.id,
        provider: row.provider,
        settings: row.settings_json ?? {},
      };
    } catch (error) {
      this.logger.error(
        `Failed to fetch channel ${id}: ${error instanceof Error ? error.message : String(error)}`,
      );
      return null;
    }
  }
}
