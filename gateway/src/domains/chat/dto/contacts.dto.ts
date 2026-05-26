import { IsInt, IsOptional, IsString, Matches, Min } from 'class-validator';

/**
 * Parametros de paginacao para listagem de contatos de uma instancia Uazapi.
 */
export class ListContactsDto {
  @IsOptional()
  @IsInt()
  @Min(1)
  page?: number;

  @IsOptional()
  @IsInt()
  @Min(1)
  pageSize?: number;

  @IsOptional()
  @IsInt()
  @Min(0)
  offset?: number;
}

/**
 * Payload para adicionar um novo contato a uma instancia Uazapi.
 */
export class ContactAddDto {
  @IsString()
  @Matches(/^[\d@.swhatsappnet+()\-\s]{10,30}$/i)
  phone!: string;

  @IsString()
  name!: string;
}

/**
 * Payload para remover um contato de uma instancia Uazapi.
 */
export class ContactRemoveDto {
  @IsString()
  @Matches(/^[\d@.swhatsappnet+()\-\s]{10,30}$/i)
  phone!: string;
}
