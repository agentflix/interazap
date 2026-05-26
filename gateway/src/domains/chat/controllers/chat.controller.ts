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
   * Inicializa o controller de chat com o cliente Uazapi.
   *
   * @param client Cliente HTTP Uazapi para operacoes de chat
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Marca mensagens como lidas na instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Payload de marcacao como lido
   * @returns Resultado da operacao
   */
  @Post('read')
  markAsRead(@Headers('token') token: string, @Body() body: MarkReadDto) {
    return this.client.markAsRead(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
