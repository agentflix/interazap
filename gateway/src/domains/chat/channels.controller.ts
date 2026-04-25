import {
  Controller,
  Get,
  Param,
  NotFoundException,
  Logger,
  UseGuards,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { InternalApiKeyGuard } from '../realtime/guards/internal-api-key.guard';
import { MetaAdapter } from './providers/meta/meta.adapter';
import { DatabaseService } from '../../infrastructure/database/database.service';
import { MetaTemplate } from './contracts/meta-provider.interface';

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
   * Lista templates aprovados de um canal Meta.
   * GET /channels/{id}/templates
   *
   * @param id - ID do canal (chat_instance)
   * @returns Lista de templates APPROVED
   */
  @Get(':id/templates')
  async listTemplates(@Param('id') id: string): Promise<MetaTemplate[]> {
    this.logger.debug(`Listing templates for channel ${id}`);

    // Fetch channel from database
    const channel = await this.fetchChannel(id);

    if (!channel) {
      throw new NotFoundException(`Channel ${id} not found`);
    }

    // Validate provider is meta
    if (channel.provider !== 'meta') {
      throw new NotFoundException(
        `Channel ${id} is not a Meta channel (provider: ${channel.provider})`,
      );
    }

    // Extract access_token from settings
    const accessToken = channel.settings?.access_token as string | undefined;
    if (!accessToken) {
      throw new NotFoundException(
        `Channel ${id} does not have an access_token configured`,
      );
    }

    // Get templates using the Meta adapter
    const templates = await this.metaAdapter.listTemplates(accessToken);

    this.logger.debug(`Found ${templates.length} templates for channel ${id}`);

    return templates;
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
