import {
  Body,
  Controller,
  Get,
  Logger,
  Param,
  Post,
  Query,
  UploadedFile,
  UseInterceptors,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { IsOptional, IsString } from 'class-validator';
import { WebChatProxyService } from '../services/webchat-proxy.service';

// ─── DTOs ────────────────────────────────────────────────────────────────────

class CreateSessionDto {
  @IsString()
  tenant_id!: string;

  @IsString()
  visitor_name!: string;

  @IsString()
  @IsOptional()
  visitor_phone?: string;
}

class SendMessageDto {
  @IsString()
  token!: string;

  @IsString()
  @IsOptional()
  content?: string;

  @IsString()
  @IsOptional()
  file_url?: string;

  @IsString()
  @IsOptional()
  mime_type?: string;

  @IsString()
  @IsOptional()
  type?: string;

  @IsString()
  @IsOptional()
  file_name?: string;
}

class CloseTicketDto {
  @IsString()
  token!: string;
}

// ─── Controller ──────────────────────────────────────────────────────────────

/**
 * WebChatController
 *
 * Endpoints públicos do webchat. Não requer autenticação Sanctum.
 * Faz proxy das requisições para a API Laravel via WebChatProxyService.
 *
 * Rotas:
 *   POST   /api/webchat/sessions
 *   GET    /api/webchat/sessions/:id/messages
 *   POST   /api/webchat/messages
 *   POST   /api/webchat/media
 *   POST   /api/webchat/close
 */
@Controller('api/webchat')
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class WebChatController {
  private readonly logger = new Logger(WebChatController.name);

  constructor(private readonly webchatProxy: WebChatProxyService) {}

  /**
   * Cria uma nova sessão de webchat para um visitante.
   * POST /api/webchat/sessions
   */
  @Post('sessions')
  createSession(@Body() body: CreateSessionDto): Promise<unknown> {
    this.logger.log(`Creating webchat session for tenant ${body.tenant_id}`);
    return this.webchatProxy.createSession(body as unknown as Record<string, unknown>);
  }

  /**
   * Busca histórico de mensagens de uma sessão.
   * GET /api/webchat/sessions/:id/messages
   */
  @Get('sessions/:id/messages')
  getSessionMessages(
    @Param('id') id: string,
    @Query('token') token: string,
  ): Promise<unknown> {
    this.logger.log(`Fetching messages for session ${id}`);
    return this.webchatProxy.getSessionMessages(id, token);
  }

  /**
   * Envia uma mensagem de texto ou mídia.
   * POST /api/webchat/messages
   */
  @Post('messages')
  sendMessage(@Body() body: SendMessageDto): Promise<unknown> {
    this.logger.log('Sending webchat message');
    return this.webchatProxy.sendMessage(body as unknown as Record<string, unknown>);
  }

  /**
   * Faz upload de um arquivo de mídia.
   * POST /api/webchat/media  (multipart/form-data)
   */
  @Post('media')
  @UseInterceptors(FileInterceptor('file'))
  uploadMedia(
    @Body('token') token: string,
    @UploadedFile() file: Express.Multer.File,
  ): Promise<unknown> {
    this.logger.log('Uploading webchat media');
    return this.webchatProxy.uploadMedia(token, file);
  }

  /**
   * Encerra o ticket do webchat.
   * POST /api/webchat/close
   */
  @Post('close')
  closeTicket(@Body() body: CloseTicketDto): Promise<unknown> {
    this.logger.log('Closing webchat ticket');
    return this.webchatProxy.closeTicket(body as unknown as Record<string, unknown>);
  }
}
