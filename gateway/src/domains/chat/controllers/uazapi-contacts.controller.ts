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
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import {
  ContactAddDto,
  ContactRemoveDto,
  ListContactsDto,
} from '../dto/contacts.dto';

/**
 * UazapiContactsController
 *
 * Gerencia operações de contatos via provider Uazapi.
 * Suporta listagem, adição e remoção de contatos.
 */
@Controller({ path: 'uazapi/instances/:token/contacts', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiContactsController {
  /**
   * Inicializa o controller de contatos com o cliente Uazapi.
   *
   * @param client Cliente HTTP Uazapi para operacoes de contatos
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Lista todos os contatos da instancia.
   *
   * @param token Token da instancia
   * @returns Lista de contatos
   */
  @Get()
  list(@Param('token') token: string) {
    return this.client.listContacts(token);
  }

  /**
   * Lista contatos com paginacao.
   *
   * @param token Token da instancia
   * @param body Parametros de paginacao
   * @returns Lista paginada de contatos
   */
  @Post('list')
  listPaginated(@Param('token') token: string, @Body() body: ListContactsDto) {
    return this.client.listContacts(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Adiciona um contato a instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Dados do contato a adicionar
   * @returns Resultado da adicao
   */
  @Post()
  add(@Param('token') token: string, @Body() body: ContactAddDto) {
    return this.client.addContact(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Remove um contato da instancia Uazapi.
   *
   * @param token Token da instancia
   * @param body Dados de identificacao do contato a remover
   * @returns Resultado da remocao
   */
  @Post('remove')
  remove(@Param('token') token: string, @Body() body: ContactRemoveDto) {
    return this.client.removeContact(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
