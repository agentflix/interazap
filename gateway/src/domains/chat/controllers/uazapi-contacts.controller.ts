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
   * Initializes the contacts controller with the Uazapi client.
   *
   * @param client - Uazapi HTTP client for contact operations
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Lists all contacts for an instance.
   *
   * @param token - Instance token
   * @returns List of contacts
   */
  @Get()
  list(@Param('token') token: string) {
    return this.client.listContacts(token);
  }

  /**
   * Lists contacts with pagination.
   *
   * @param token - Instance token
   * @param body - Pagination parameters
   * @returns Paginated list of contacts
   */
  @Post('list')
  listPaginated(@Param('token') token: string, @Body() body: ListContactsDto) {
    return this.client.listContacts(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Adds a contact to an instance.
   *
   * @param token - Instance token
   * @param body - Contact data
   * @returns Add result
   */
  @Post()
  add(@Param('token') token: string, @Body() body: ContactAddDto) {
    return this.client.addContact(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Removes a contact from an instance.
   *
   * @param token - Instance token
   * @param body - Contact removal data
   * @returns Remove result
   */
  @Post('remove')
  remove(@Param('token') token: string, @Body() body: ContactRemoveDto) {
    return this.client.removeContact(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
