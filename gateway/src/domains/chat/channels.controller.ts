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
import { DatabaseService } from '../../infrastructure/database/database.service';
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
    private readonly databaseService: DatabaseService,
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
   * Busca um canal pelo ID no banco de dados.
   */
  private async fetchChannel(id: string): Promise<{
    id: string;
    provider: string;
    settings: Record<string, unknown>;
  } | null> {
    try {
      const result = await this.databaseService.query<{
        id: string;
        provider: string;
        settings_json: Record<string, unknown>;
      }>(
        `SELECT id, provider, settings_json
         FROM chat_instances
         WHERE id = $1
         LIMIT 1`,
        [id],
      );

      if (!result || !result.rows || result.rows.length === 0) {
        return null;
      }

      const row = result.rows[0];
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
