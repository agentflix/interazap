import {
  Body,
  Controller,
  Headers,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { MarkReadDto } from '../dto/mark-read.dto';

/**
 * ChatController
 *
 * Endpoints para operações de chat via provider Uazapi.
 * Gerencia leitura de mensagens e configurações de instância.
 */
@Controller({ path: 'chat', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class ChatController {
  /**
   * Initializes the chat controller with the Uazapi client.
   *
   * @param client - Uazapi HTTP client for chat operations
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Marks messages as read.
   *
   * @param token - Instance token
   * @param body - Mark read payload
   * @returns Mark as read result
   */
  @Post('read')
  markAsRead(@Headers('token') token: string, @Body() body: MarkReadDto) {
    return this.client.markAsRead(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
